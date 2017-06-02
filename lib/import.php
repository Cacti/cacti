<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function import_xml_data(&$xml_data, $import_as_new, $profile_id, $remove_orphans = false) {
	global $config, $hash_type_codes, $cacti_version_codes, $preview_only, $remove_orphans, $import_debug_info;

	include_once($config['library_path'] . '/xml.php');

	$info_array    = array();
	$debug_session = array();

	$xml_array = xml2array($xml_data);

	if (sizeof($xml_array) == 0) {
		raise_message(7); /* xml parse error */
		return $info_array;
	}

	foreach ($xml_array as $hash => $hash_array) {
		/* parse information from the hash */
		$parsed_hash = parse_xml_hash($hash);

		/* invalid/wrong hash */
		if ($parsed_hash == false) { return $info_array; }

		if (isset($dep_hash_cache{$parsed_hash['type']})) {
			array_push($dep_hash_cache{$parsed_hash['type']}, $parsed_hash);
		} else {
			$dep_hash_cache{$parsed_hash['type']} = array($parsed_hash);
		}
	}

	//print '<pre>';print_r($dep_hash_cache);print '</pre>';exit;

	$hash_cache = array();
	$repair     = 0;

	/* the order of the $hash_type_codes array is ordered such that the items
	with the most dependencies are last and the items with no dependencies are first.
	this means dependencies will just magically work themselves out :) */
	foreach ($hash_type_codes as $type => $code) {
		/* do we have any matches for this type? */
		if (isset($dep_hash_cache[$type])) {
			/* yes we do. loop through each match for this type */
			for ($i=0; $i<count($dep_hash_cache[$type]); $i++) {
				$import_debug_info = false;

				cacti_log('$dep_hash_cache[$type][$i][\'type\']: ' . $dep_hash_cache[$type][$i]['type'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$dep_hash_cache[$type][$i][\'version\']: ' . $dep_hash_cache[$type][$i]['version'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$cacti_version_codes{$dep_hash_cache[$type][$i][\'version\']}: ' . $cacti_version_codes{$dep_hash_cache[$type][$i]['version']}, false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$dep_hash_cache[$type][$i][\'hash\']: ' . $dep_hash_cache[$type][$i]['hash'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);

				$hash_array = $xml_array['hash_' . $hash_type_codes{$dep_hash_cache[$type][$i]['type']} . $cacti_version_codes{$dep_hash_cache[$type][$i]['version']} . $dep_hash_cache[$type][$i]['hash']];

				switch($type) {
				case 'graph_template':
					$hash_cache += xml_to_graph_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $dep_hash_cache[$type][$i]['version'], $remove_orphans);
					break;
				case 'data_template':
					$hash_cache += xml_to_data_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $import_as_new, $profile_id);
					$repair++;
					break;
				case 'host_template':
					$hash_cache += xml_to_host_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					break;
				case 'data_input_method':
					$hash_cache += xml_to_data_input_method($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					$repair++;
					break;
				case 'data_query':
					$hash_cache += xml_to_data_query($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					break;
				case 'gprint_preset':
					$hash_cache += xml_to_gprint_preset($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					break;
				case 'cdef':
					$hash_cache += xml_to_cdef($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					break;
				case 'vdef':
					$hash_cache += xml_to_vdef($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
					break;
				case 'data_source_profile':
					$cache_add = xml_to_data_source_profile($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $import_as_new, $profile_id);
					if ($cache_add !== false) {
						$hash_cache += $cache_add;
					}
					break;
				case 'round_robin_archive':
					// Deprecated
					break;
				}

				if (!empty($import_debug_info)) {
					$info_array[$type]{isset($info_array[$type]) ? count($info_array[$type]) : 0} = $import_debug_info;
				}
			}
		}
	}

	if ($repair) {
		repair_system_data_input_methods();
	}

	return $info_array;
}

function get_public_key() {
$public_key = <<<EOD
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAMbPpuQfwmg93oOGjdLKrAqwEPwvvNjC
bk2YZiDglh8lQJxNQI9glG1Z/ptvqprFO3iSx9rTP4vzZ0Ek2+EMYTMCAwEAAQ==
-----END PUBLIC KEY-----
EOD;

    return $public_key;
}

function import_package($xmlfile, $profile_id = 1, $remove_orphans = false, $preview = false) {
	global $config, $preview_only;

	$preview_only = $preview;

	/* set new timeout and memory settings */
	ini_set('max_execution_time', '50');
	ini_set('memory_limit', '128M');

	$public_key = get_public_key();

	$filename         = "compress.zlib://$xmlfile";
	$binary_signature = '';
	$debug_data       = array();

	$f   = fopen($filename, 'r');
	$xml = '';

	if (is_resource($f)) {
		while (!feof($f)) {
			$x = fgets($f);
			if (strpos($x, '<signature>') !== FALSE) {
				$binary_signature =  base64_decode(trim(str_replace(array('<signature>', '</signature>'), array('', ''), $x)));
				$x = "   <signature></signature>\n";

				cacti_log('NOTE: Got Package Signature', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
			}
			$xml .= "$x";
		}
		fclose($f); 
	} else {
		cacti_log('FATAL: Unable to open file ' . $filename, true, 'IMPORT', POLLER_VERBOSITY_LOW);	
		return false;
	}

	// Verify Signature
	$ok = openssl_verify($xml, $binary_signature, $public_key);
	if ($ok == 1) {
		cacti_log('NOTE: File is Signed Correctly', false, 'IMPORT', POLLER_VERBOSITY_LOW);
	} elseif ($ok == 0) {
		cacti_log('FATAL: File has been Tampered with.', false, 'IMPORT', POLLER_VERBOSITY_LOW);
		return false;
	} else {
		cacti_log('FATAL: Could not Verify Signature.', false, 'IMPORT', POLLER_VERBOSITY_LOW);
		return false;
	}

	cacti_log('Loading Plugin Information from package', false, 'IMPORT', POLLER_VERBOSITY_HIGH);

	$xmlget     = simplexml_load_string($xml); 
	$data       = xml_to_array($xmlget);
	$filestatus = array();

	$plugin = $data['info']['name'];

	cacti_log('Verifying each files signature', false, 'IMPORT', POLLER_VERBOSITY_HIGH);

	if (isset($data['files']['file']['data'])) {
		$data['files']['file'] = array($data['files']['file']);
	}

	foreach ($data['files']['file'] as $f) {
		$binary_signature = base64_decode($f['filesignature']);
		$fdata = base64_decode($f['data']);
		$ok = openssl_verify($fdata, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
		if ($ok == 1) {
			cacti_log('NOTE: File OK: ' . $f['name'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
		} else {
			cacti_log('FATAL: Could not Verify Signature for file: ' . $f['name'], true, 'IMPORT', POLLER_VERBOSITY_LOW);
			return false;
		}
	}

	cacti_log('Writing Files', false, 'IMPORT', POLLER_VERBOSITY_HIGH);

	foreach ($data['files']['file'] as $f) {
		$fdata = base64_decode($f['data']);
		$name = $f['name'];

		if (strpos($name, 'scripts/') !== false || strpos($name, '/resource/') !== false) {
			$filename = $config['base_path'] . "/$name";
			cacti_log('Writing file: ' . $filename, false, 'IMPORT', POLLER_VERBOSITY_HIGH);
			if (!$preview) {
				if (is_writable($filename)) {
					$file = fopen($filename,'wb');

					if (is_resource($file)) {
						fwrite($file ,$fdata, strlen($fdata));
						fclose($file);
						clearstatcache();
						$filestatus[$filename] = __('written');
					} else {
						$filestatus[$filename] = __('could not open');
					}

					if (!file_exists($filename)) {
						cacti_log('FATAL: Unable to create directory: ' . $filename, true, 'IMPORT', POLLER_VERBOSITY_LOW);

						$filestatus[$filename] = __('not exists');
					}
				} else {
					$filestatus[$filename] = __('not writable');
				}
			} else {
				if (!is_writable($filename)) {
					$filestatus[$filename] = __('not writable');
				} else {
					$filestatus[$filename] = __('writable');
				}
			}
		} else {
			cacti_log('Importing XML Data', false, 'IMPORT', POLLER_VERBOSITY_HIGH);

			$debug_data = import_xml_data($fdata, false, $profile_id, $remove_orphans, $preview_only);
		}
	}

	cacti_log('File creation complete', false, 'IMPORT', POLLER_VERBOSITY_HIGH);

	return array($debug_data, $filestatus);
}

function xml_to_graph_template($hash, &$xml_array, &$hash_cache, $hash_version, $remove_orphans = false) {
	global $struct_graph, $struct_graph_item, $fields_graph_template_input_edit, $cacti_version_codes, $preview_only, $graph_item_types, $import_debug_info;

	/* track changes */
	$status = 0;

	/* import into: graph_templates */
	$_graph_template_id = db_fetch_cell_prepared('SELECT id 
		FROM graph_templates 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_graph_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM graph_templates 
			WHERE id = ?', array($_graph_template_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_graph_template_id) ? '0' : $_graph_template_id);
	$save['hash'] = $hash;
	$save['name'] = $xml_array['name'];

	if (isset($xml_array['multiple'])) {
		$save['multiple'] = $xml_array['multiple'];
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'graph_templates');

	if (!$preview_only) {
		$graph_template_id = sql_save($save, 'graph_templates');
		$hash_cache['graph_template'][$hash] = $graph_template_id;
	} else {
		$graph_template_id = $_graph_template_id;
		$hash_cache['graph_template'][$hash] = $graph_template_id;
	}

	/* import into: graph_templates_graph */
	unset($save);
	$save['id'] = (empty($_graph_template_id) ? '0' : db_fetch_cell_prepared('SELECT gtg.id 
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gt.id=gtg.graph_template_id 
		WHERE gt.id = ? AND gtg.local_graph_id=0', array($graph_template_id)));

	if (!empty($_graph_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM graph_templates_graph 
			WHERE id = ?', 
			array($save['id']));
	} else {
		$previous_data = array();
	}

	$save['graph_template_id'] = $graph_template_id;

	foreach ($struct_graph as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array['graph']{'t_' . $field_name})) {
			$save{'t_' . $field_name} = $xml_array['graph']{'t_' . $field_name};
		}

		/* make sure this field exists in the xml array first */
		if (isset($xml_array['graph'][$field_name])) {
			/* Cacti pre 0.8.5 did handle a unit_exponent=0 differently
			 * so we need to know the version of the current hash code we're just working on */
			if (($field_name == 'unit_exponent_value') && (get_version_index($hash_version) < get_version_index('0.8.5')) && ($xml_array['graph'][$field_name] == '0')) { /* backwards compatability */
				$save[$field_name] = '';
			} else {
				$save[$field_name] = xml_character_decode($xml_array['graph'][$field_name]);
			}
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'graph_templates_graph');

	if (!$preview_only) {
		$graph_template_graph_id = sql_save($save, 'graph_templates_graph');
	} else {
		$graph_template_graph_id = $save['id'];
	}

	/* import into: graph_templates_item */
	if (is_array($xml_array['items'])) {
		$new_items = array();

		if ($graph_template_id > 0) {
			$items = db_fetch_assoc_prepared('SELECT *
				FROM graph_templates_item
				WHERE graph_template_id = ?
				AND local_graph_id = 0',
				array($graph_template_id));

			if (sizeof($items)) {
				foreach($items as $item) {
					$orphaned_items[$item['hash']] = $item;
				}
			}
		} else {
			$orphaned_items = array();
		}

		foreach ($xml_array['items'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* mark present hashes */
			if (isset($orphaned_items[$parsed_hash['hash']])) {
				unset($orphaned_items[$parsed_hash['hash']]);
			}

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_graph_template_item_id = db_fetch_cell_prepared('SELECT id 
				FROM graph_templates_item 
				WHERE hash = ?
				AND graph_template_id = ?
				AND local_graph_id=0', 
				array($parsed_hash['hash'], $graph_template_id));

			if (!empty($_graph_template_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM graph_templates_item 
					WHERE id = ?', 
					array($_graph_template_item_id));
			} else {
				$previous_data = array();
			}

			$save['id']                = (empty($_graph_template_item_id) ? '0' : $_graph_template_item_id);
			$save['hash']              = $parsed_hash['hash'];
			$save['graph_template_id'] = $graph_template_id;

			foreach ($struct_graph_item as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					} elseif (($field_name == 'color_id') && (preg_match('/^[a-fA-F0-9]{6}$/', $item_array[$field_name])) && (get_version_index($parsed_hash['version']) >= get_version_index('0.8.5'))) { /* treat the 'color' field differently */
						$color_id = db_fetch_cell_prepared('SELECT id 
							FROM colors 
							WHERE hex = ?', 
							array($item_array[$field_name]));

						if (empty($color_id) && !$preview_only) {
							db_execute_prepared('INSERT INTO colors (
								hex) VALUES (?)', 
								array($item_array[$field_name]));

							$color_id = db_fetch_insert_id();
						}

						$save[$field_name] = $color_id;
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			if (!empty($_graph_template_id)) {
				if (!sizeof($previous_data)) {
					$new_items[$parsed_hash['hash']] = $save;
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'graph_templates_item');

			if (!$preview_only) {
				$graph_template_item_id = sql_save($save, 'graph_templates_item');

				$hash_cache['graph_template_item']{$parsed_hash['hash']} = $graph_template_item_id;
			} else {
				$hash_cache['graph_template_item']{$parsed_hash['hash']} = $_graph_template_item_id;
			}
		}
	}

	/* import into: graph_template_input */
	if (is_array($xml_array['inputs'])) {
		foreach ($xml_array['inputs'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_graph_template_input_id = db_fetch_cell_prepared('SELECT id 
				FROM graph_template_input 
				WHERE hash = ?
				AND graph_template_id = ?', 
				array($parsed_hash['hash'], $graph_template_id));

			if (!empty($_graph_template_input_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM graph_template_input 
					WHERE id = ?', 
					array($_graph_template_input_id));
			} else {
				$previous_data = array();
			}

			$save['id']                = (empty($_graph_template_input_id) ? '0' : $_graph_template_input_id);
			$save['hash']              = $parsed_hash['hash'];
			$save['graph_template_id'] = $graph_template_id;

			foreach ($fields_graph_template_input_edit as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'graph_template_input');

			if (!$preview_only) {
				$graph_template_input_id = sql_save($save, 'graph_template_input');

				$hash_cache['graph_template_input']{$parsed_hash['hash']} = $graph_template_input_id;

				/* import into: graph_template_input_defs */
				$hash_items = explode('|', $item_array['items']);

				if (!empty($hash_items[0])) {
					for ($i=0; $i<count($hash_items); $i++) {
						/* parse information from the hash */
						$parsed_hash = parse_xml_hash($hash_items[$i]);

						/* invalid/wrong hash */
						if ($parsed_hash == false) { return false; }

						if (isset($hash_cache['graph_template_item'][$parsed_hash['hash']])) {
							db_execute_prepared('REPLACE INTO graph_template_input_defs 
								(graph_template_input_id,graph_template_item_id) 
								VALUES (?, ?)', 
								array($graph_template_input_id, $hash_cache['graph_template_item'][$parsed_hash['hash']]));
						}
					}
				}
			}
		}
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_graph_template_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($graph_template_id) ? 'fail' : 'success'));

	if (isset($new_items) && sizeof($new_items)) {
		$new_text = array();
		foreach($new_items as $item) {
			$new_text[] = 'New Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];
		}
		$import_debug_info['new_items'] = $new_text;
	}

	if (isset($orphaned_items) && sizeof($orphaned_items)) {
		$orphan_text = array();
		foreach($orphaned_items as $item) {
			if ($remove_orphans) {
				$orphan_text[] = 'Removed Orphaned Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];
				db_execute_prepared('DELETE FROM graph_templates_item WHERE hash = ?', array($item['hash']));				
				db_execute_prepared('DELETE FROM graph_templates_item WHERE local_graph_template_item_id = ?', array($item['id']));
			} else {
				$orphan_text[] = 'Found Orphaned Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];
			}
		}
		$import_debug_info['orphans'] = $orphan_text;

		if ($remove_orphans) {
			retemplate_graphs($graph_template_id);
		}
	}

	return $hash_cache;
}

function xml_to_data_template($hash, &$xml_array, &$hash_cache, $import_as_new, $profile_id) {
	global $struct_data_source, $struct_data_source_item, $import_template_id, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	/* import into: data_template */
	$_data_template_id = db_fetch_cell_prepared('SELECT id 
		FROM data_template 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_data_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM data_template 
			WHERE id = ?', 
			array($_data_template_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_data_template_id) ? '0' : $_data_template_id);
	$save['hash'] = $hash;
	$save['name'] = $xml_array['name'];

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'data_template');

	if (!$preview_only) {
		$data_template_id = sql_save($save, 'data_template');

		$hash_cache['data_template'][$hash] = $data_template_id;
	} else {
		$data_template_id = $_data_template_id;

		$hash_cache['data_template'][$hash] = $_data_template_id;
	}

	/* import into: data_template_data */
	unset($save);
	$save['id'] = (empty($_data_template_id) ? '0' : db_fetch_cell_prepared('SELECT dtd.id 
		FROM data_template AS dt
		INNER JOIN data_template_data AS dtd
		ON dt.id=dtd.data_template_id 
		WHERE dt.id = ?
		AND dtd.local_data_id=0', array($data_template_id)));

	if (!empty($save['id'])) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM data_template_data
			WHERE id = ?', 
			array($save['id']));
	} else {
		$previous_data = array();
	}

	$save['data_template_id'] = $data_template_id;
	$save['data_source_profile_id'] = $profile_id;

	foreach ($struct_data_source as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array['ds']{'t_' . $field_name})) {
			$save{'t_' . $field_name} = $xml_array['ds']{'t_' . $field_name};
		}

		/* make sure this field exists in the xml array first */
		if (isset($xml_array['ds'][$field_name])) {
			/* is the value of this field a hash or not? */
			if ($field_name == 'data_source_profile_id') {
				$save[$field_name] = $profile_id;
			} elseif (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $xml_array['ds'][$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array['ds'][$field_name], $hash_cache);
			} else {
				$save[$field_name] = xml_character_decode($xml_array['ds'][$field_name]);
			}
		}
	}
	
	/* set the rrd_step */
	if ($profile_id > 0) {
		$save['rrd_step'] = db_fetch_cell_prepared('SELECT step 
			FROM data_source_profiles 
			WHERE id = ?', 
			array($profile_id));
	} else {
		$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
		$save['rrd_step'] = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id));
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'data_template_data');

	if (!$preview_only) {
		$data_template_data_id = sql_save($save, 'data_template_data');

		$import_template_id = $data_template_data_id;
	}

	/* import into: data_template_rrd */
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_template_rrd_id = db_fetch_cell_prepared('SELECT id 
				FROM data_template_rrd 
				WHERE hash = ?
				AND data_template_id = ?
				AND local_data_id=0', 
				array($parsed_hash['hash'], $data_template_id));

			if (!empty($_data_template_rrd_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM data_template_rrd
					WHERE id = ?', 
					array($_data_template_rrd_id));
			} else {
				$previous_data = array();
			}

			$save['id']               = (empty($_data_template_rrd_id) ? '0' : $_data_template_rrd_id);
			$save['hash']             = $parsed_hash['hash'];
			$save['data_template_id'] = $data_template_id;

			foreach ($struct_data_source_item as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array{'t_' . $field_name})) {
					$save{'t_' . $field_name} = $item_array{'t_' . $field_name};
				}

				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			/* use the profiles step * 2 as the heartbeat if we are not importing a new profile */
			if ($import_as_new === false) {
				$save['rrd_heartbeat'] = db_fetch_cell_prepared('SELECT step 
					FROM data_source_profiles 
					WHERE id = ?', 
					array($profile_id)) * 2;
			}
			
			/* Fix for importing during installation - use the polling interval as the step if we are to use the default rra settings */
			if (is_array($profile_id) == true) {
				$save['rrd_heartbeat'] = read_config_option('poller_interval') * 2;
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'data_template_rrd');

			if (!$preview_only) {
				$data_template_rrd_id = sql_save($save, 'data_template_rrd');

				$hash_cache['data_template_item']{$parsed_hash['hash']} = $data_template_rrd_id;
			} else {
				$hash_cache['data_template_item']{$parsed_hash['hash']} = $_data_template_rrd_id;
			}
		}
	}

	/* import into: data_input_data */
	if (!$preview_only) {
		if (is_array($xml_array['data'])) {
			foreach ($xml_array['data'] as $item_hash => $item_array) {
				unset($save);
				$save['data_template_data_id'] = $data_template_data_id;
				$save['data_input_field_id']   = resolve_hash_to_id($item_array['data_input_field_id'], $hash_cache);
				$save['t_value']               = $item_array['t_value'];
				$save['value']                 = xml_character_decode($item_array['value']);

				sql_save($save, 'data_input_data', array('data_template_data_id', 'data_input_field_id'), false);
			}
		}

		/* push out field mappings for the data collector */
		db_execute_prepared('REPLACE INTO poller_data_template_field_mappings
			SELECT dtr.data_template_id, 
			dif.data_name, 
			GROUP_CONCAT(dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names, 
			NOW() AS last_updated
			FROM data_template_rrd AS dtr
			INNER JOIN data_input_fields AS dif
			ON dtr.data_input_field_id = dif.id
			WHERE dtr.local_data_id = 0
			AND dtr.data_template_id = ?
			GROUP BY dtr.data_template_id, dif.data_name', array($data_template_id));
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_data_template_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($data_template_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_data_query($hash, &$xml_array, &$hash_cache) {
	global $fields_data_query_edit, $fields_data_query_item_edit, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	/* import into: snmp_query */
	$_data_query_id = db_fetch_cell_prepared('SELECT id 
		FROM snmp_query 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_data_query_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM snmp_query 
			WHERE id = ?', 
			array($_data_query_id));
	} else {
		$previous_data = array();
	}

	$save['id']     = (empty($_data_query_id) ? '0' : $_data_query_id);
	$save['hash']   = $hash;

	foreach ($fields_data_query_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			/* is the value of this field a hash or not? */
			if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $xml_array[$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array[$field_name], $hash_cache);
			} else {
				$save[$field_name] = xml_character_decode($xml_array[$field_name]);
			}
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'snmp_query');

	if (!$preview_only) {
		$data_query_id = sql_save($save, 'snmp_query');

		$hash_cache['data_query'][$hash] = $data_query_id;

		update_replication_crc(0, 'poller_replicate_snmp_query_crc');
	} else {
		$data_query_id = $_data_query_id;

		$hash_cache['data_query'][$hash] = $_data_query_id;
	}

	/* import into: snmp_query_graph */
	if (is_array($xml_array['graphs'])) {
		foreach ($xml_array['graphs'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_query_graph_id = db_fetch_cell_prepared('SELECT id 
				FROM snmp_query_graph 
				WHERE hash = ?
				AND snmp_query_id = ?', 
				array($parsed_hash['hash'], $data_query_id));

			if (!empty($_data_query_graph_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM snmp_query_graph
					WHERE id = ?', 
					array($_data_query_graph_id));
			} else {
				$previous_data = array();
			}

			$save['id']            = (empty($_data_query_graph_id) ? '0' : $_data_query_graph_id);
			$save['hash']          = $parsed_hash['hash'];
			$save['snmp_query_id'] = $data_query_id;

			foreach ($fields_data_query_item_edit as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'snmp_query_graph');

			if (!$preview_only) {
				$data_query_graph_id = sql_save($save, 'snmp_query_graph');

				$hash_cache['data_query_graph']{$parsed_hash['hash']} = $data_query_graph_id;

				/* import into: snmp_query_graph_rrd */
				if (is_array($item_array['rrd'])) {
					foreach ($item_array['rrd'] as $sub_item_hash => $sub_item_array) {
						unset($save);
						$save['snmp_query_graph_id']  = $data_query_graph_id;
						$save['data_template_id']     = resolve_hash_to_id($sub_item_array['data_template_id'], $hash_cache);
						$save['data_template_rrd_id'] = resolve_hash_to_id($sub_item_array['data_template_rrd_id'], $hash_cache);
						$save['snmp_field_name']      = $sub_item_array['snmp_field_name'];

						sql_save($save, 'snmp_query_graph_rrd', array('snmp_query_graph_id', 'data_template_id', 'data_template_rrd_id'), false);
					}
				}
			} else {
				$data_query_graph_id = $_data_query_graph_id;

				$hash_cache['data_query_graph']{$parsed_hash['hash']} = $_data_query_graph_id;
			}

			/* import into: snmp_query_graph_sv */
			if (is_array($item_array['sv_graph'])) {
				foreach ($item_array['sv_graph'] as $sub_item_hash => $sub_item_array) {
					/* parse information from the hash */
					$parsed_hash = parse_xml_hash($sub_item_hash);

					/* invalid/wrong hash */
					if ($parsed_hash == false) { return false; }

					unset($save);
					$_data_query_graph_sv_id = db_fetch_cell_prepared('SELECT id 
						FROM snmp_query_graph_sv 
						WHERE hash = ?
						AND snmp_query_graph_id = ?', 
						array($parsed_hash['hash'], $data_query_graph_id));

					if (!empty($_data_query_graph_sv_id)) {
						$previous_data = db_fetch_row_prepared('SELECT * 
							FROM snmp_query_graph_sv
							WHERE id = ?', 
							array($_data_query_graph_sv_id));
					} else {
						$previous_data = array();
					}

					$save['id']                  = (empty($_data_query_graph_sv_id) ? '0' : $_data_query_graph_sv_id);
					$save['hash']                = $parsed_hash['hash'];
					$save['snmp_query_graph_id'] = $data_query_graph_id;
					$save['sequence']            = $sub_item_array['sequence'];
					$save['field_name']          = $sub_item_array['field_name'];
					$save['text']                = xml_character_decode($sub_item_array['text']);

					/* check for status changes */
					$status += compare_data($save, $previous_data, 'snmp_query_graph_sv');

					if (!$preview_only) {
						$data_query_graph_sv_id = sql_save($save, 'snmp_query_graph_sv');

						$hash_cache['data_query_sv_graph']{$parsed_hash['hash']} = $data_query_graph_sv_id;
					} else {
						$hash_cache['data_query_sv_graph']{$parsed_hash['hash']} = $_data_query_graph_sv_id;
					}
				}
			}

			/* import into: snmp_query_graph_rrd_sv */
			if (is_array($item_array['sv_data_source'])) {
				foreach ($item_array['sv_data_source'] as $sub_item_hash => $sub_item_array) {
					/* parse information from the hash */
					$parsed_hash = parse_xml_hash($sub_item_hash);

					/* invalid/wrong hash */
					if ($parsed_hash == false) { return false; }

					unset($save);
					$_data_query_graph_rrd_sv_id = db_fetch_cell_prepared('SELECT id 
						FROM snmp_query_graph_rrd_sv 
						WHERE hash = ?
						AND snmp_query_graph_id = ?', 
						array($parsed_hash['hash'], $data_query_graph_id));

					if (!empty($_data_query_graph_rrd_sv_id)) {
						$previous_data = db_fetch_row_prepared('SELECT * 
							FROM snmp_query_graph_rrd_sv
							WHERE id = ?', 
							array($_data_query_graph_rrd_sv_id));
					} else {
						$previous_data = array();
					}

					$save['id']                  = (empty($_data_query_graph_rrd_sv_id) ? '0' : $_data_query_graph_rrd_sv_id);
					$save['hash']                = $parsed_hash['hash'];
					$save['snmp_query_graph_id'] = $data_query_graph_id;
					$save['data_template_id']    = resolve_hash_to_id($sub_item_array['data_template_id'], $hash_cache);
					$save['sequence']            = $sub_item_array['sequence'];
					$save['field_name']          = $sub_item_array['field_name'];
					$save['text']                = xml_character_decode($sub_item_array['text']);

					/* check for status changes */
					$status += compare_data($save, $previous_data, 'snmp_query_graph_rrd_sv');

					if (!$preview_only) {
						$data_query_graph_rrd_sv_id = sql_save($save, 'snmp_query_graph_rrd_sv');

						$hash_cache['data_query_sv_data_source']{$parsed_hash['hash']} = $data_query_graph_rrd_sv_id;
					} else {
						$hash_cache['data_query_sv_data_source']{$parsed_hash['hash']} = $_data_query_graph_rrd_sv_id;
					}
				}
			}
		}
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_data_query_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($data_query_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_gprint_preset($hash, &$xml_array, &$hash_cache) {
	global $fields_grprint_presets_edit, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	/* import into: graph_templates_gprint */
	$_gprint_preset_id = db_fetch_cell_prepared('SELECT id 
		FROM graph_templates_gprint 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_gprint_preset_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM graph_templates_gprint
			WHERE id = ?', 
			array($_gprint_preset_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_gprint_preset_id) ? '0' : $_gprint_preset_id);
	$save['hash'] = $hash;

	foreach ($fields_grprint_presets_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'graph_templates_gprint');

	if (!$preview_only) {
		$gprint_preset_id = sql_save($save, 'graph_templates_gprint');

		$hash_cache['gprint_preset'][$hash] = $gprint_preset_id;
	} else {
		$hash_cache['gprint_preset'][$hash] = $_gprint_preset_id;
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_gprint_preset_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($gprint_preset_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_data_source_profile($hash, &$xml_array, &$hash_cache, $import_as_new, $profile_id) {
	global $fields_profile_edit, $fields_profile_rra_edit, $import_template_id, $preview_only, $import_debug_info;

	if ($import_as_new == true) {
		$save['id']   = 0;
		$save['hash'] = get_hash_data_source_profile(0);

		foreach ($fields_profile_edit as $field_name => $field_array) {
			/* make sure this field exists in the xml array first */
			if (isset($xml_array[$field_name])) {
				$save[$field_name] = xml_character_decode($xml_array[$field_name]);
			}
		}

		// Give the Profile a new name
		$save['name'] .= ' (imported)';

		if (!$preview_only) {
			$dsp_id = sql_save($save, 'data_source_profiles');

			if (!empty($dsp_id)) {
				db_execute_prepared('UPDATE data_template_data 
					SET data_source_profile_id = ? 
					WHERE id = ?', 
					array($dsp_id, $import_template_id));
			}

			$hash_cache['data_source_profiles'][$hash] = $dsp_id;

			/* import into: data_source_profiles_cf */
			$hash_items = explode('|', $xml_array['cf_items']);

			if (!empty($hash_items[0])) {
				for ($i=0; $i<count($hash_items); $i++) {
					db_execute_prepared('REPLACE INTO data_source_profiles_cf 
						(data_source_profile_id,consolidation_function_id) 
						VALUES (?, ?)', 
						array($dsp_id, $hash_items[$i]));
				}
			}

			/* import into: data_source_profiles_rra */
			if (is_array($xml_array['items'])) {
				foreach ($xml_array['items'] as $item_name => $item_array) {
					unset($save);

					$save['id']                     = 0;
					$save['data_source_profile_id'] = $dsp_id;

					foreach ($fields_profile_rra_edit as $field_name => $field_array) {
						/* make sure this field exists in the xml array first */
						if (isset($item_array[$field_name])) {
							$save[$field_name] = xml_character_decode($item_array[$field_name]);
						}
					}

					$rra_id = sql_save($save, 'data_source_profiles_rra');

					$hash_cache['data_source_profile_rra']{$item_name} = $rra_id;
				}
			}
		}

		/* status information that will be presented to the user */
		$import_debug_info['type']   = 'new';
		$import_debug_info['title']  = $xml_array['name'] . ' (imported)';
		$import_debug_info['result'] = ($preview_only ? 'preview':(empty($dsp_id) ? 'fail' : 'success'));

		return $hash_cache;
	} else {
		return false;
	}

}

function xml_to_host_template($hash, &$xml_array, &$hash_cache) {
	global $fields_host_template_edit, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	/* import into: graph_templates_gprint */
	$_host_template_id = db_fetch_cell_prepared('SELECT id 
		FROM host_template 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_host_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM host_template
			WHERE id = ?', 
			array($_host_template_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_host_template_id) ? '0' : $_host_template_id);
	$save['hash'] = $hash;

	foreach ($fields_host_template_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'host_template');

	if (!$preview_only) {
		$host_template_id = sql_save($save, 'host_template');

		$hash_cache['host_template'][$hash] = $host_template_id;

		/* import into: host_template_graph */
		$hash_items = explode('|', $xml_array['graph_templates']);

		if (!empty($hash_items[0])) {
			for ($i=0; $i<count($hash_items); $i++) {
				/* parse information from the hash */
				$parsed_hash = parse_xml_hash($hash_items[$i]);

				/* invalid/wrong hash */
				if ($parsed_hash == false) { return false; }

				if (isset($hash_cache['graph_template']{$parsed_hash['hash']})) {
					db_execute_prepared('REPLACE INTO host_template_graph 
						(host_template_id,graph_template_id) 
						VALUES (?, ?)', 
						array($host_template_id, $hash_cache['graph_template']{$parsed_hash['hash']}));
				}
			}
		}

		/* import into: host_template_snmp_query */
		$hash_items = explode('|', $xml_array['data_queries']);

		if (!empty($hash_items[0])) {
			for ($i=0; $i<count($hash_items); $i++) {
				/* parse information from the hash */
				$parsed_hash = parse_xml_hash($hash_items[$i]);

				/* invalid/wrong hash */
				if ($parsed_hash == false) { return false; }

				if (isset($hash_cache['data_query']{$parsed_hash['hash']})) {
					db_execute_prepared('REPLACE INTO host_template_snmp_query 
						(host_template_id,snmp_query_id) 
						VALUES (?, ?)', 
						array($host_template_id, $hash_cache['data_query']{$parsed_hash['hash']}));
				}
			}
		}
	} else {
		$hash_cache['host_template'][$hash] = $_host_template_id;
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_host_template_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($host_template_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_cdef($hash, &$xml_array, &$hash_cache) {
	global $fields_cdef_edit, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	$fields_cdef_item_edit = array(
		'sequence' => 'sequence',
		'type'     => 'type',
		'value'    => 'value'
	);

	/* import into: cdef */
	$_cdef_id = db_fetch_cell_prepared('SELECT id 
		FROM cdef 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_cdef_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM cdef
			WHERE id = ?', 
			array($_cdef_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_cdef_id) ? '0' : $_cdef_id);
	$save['hash'] = $hash;

	foreach ($fields_cdef_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'cdef');

	if (!$preview_only) {
		$cdef_id = sql_save($save, 'cdef');

		$hash_cache['cdef'][$hash] = $cdef_id;
	} else {
		$cdef_id = $_cdef_id;

		$hash_cache['cdef'][$hash] = $_cdef_id;
	}

	/* import into: cdef_items */
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_cdef_item_id = db_fetch_cell_prepared('SELECT id 
				FROM cdef_items 
				WHERE hash= ? 
				AND cdef_id = ?', 
				array($parsed_hash['hash'], $cdef_id));

			if (!empty($_cdef_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM cdef_items
					WHERE id = ?', 
					array($_cdef_item_id));
			} else {
				$previous_data = array();
			}

			$save['id']      = (empty($_cdef_item_id) ? '0' : $_cdef_item_id);
			$save['hash']    = $parsed_hash['hash'];
			$save['cdef_id'] = $cdef_id;

			foreach ($fields_cdef_item_edit as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* check, if an inherited cdef as to be decoded (value == 5)
					 * this whole procedure relies on the sequence during template export
					 * inherited cdef's must come first, this has to be taken care of during export
					 * so we do not have any specific dependency checks here */
					if (($field_name == 'value') && ($item_array['type'] == '5')) {
						/* parse information from the hash, which in this case
						 * is stored as a value of the current item being processed */
						$parsed_item_hash = parse_xml_hash($item_array['value']);

						/* invalid/wrong hash */
						if ($parsed_item_hash == false) { return false; }

						$_cdef_id = db_fetch_cell_prepared('SELECT id 
							FROM cdef 
							WHERE hash = ?', 
							array($parsed_item_hash['hash']));

						$save[$field_name] = $_cdef_id;
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'cdef_items');

			if (!$preview_only) {
				$cdef_item_id = sql_save($save, 'cdef_items');

				$hash_cache['cdef_item']{$parsed_hash['hash']} = $cdef_item_id;
			} else {
				$hash_cache['cdef_item']{$parsed_hash['hash']} = $_cdef_item_id;
			}
		}
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_cdef_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($cdef_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_vdef($hash, &$xml_array, &$hash_cache) {
	global $config, $preview_only, $import_debug_info;

	include_once($config['library_path'] . 'vdef.php');

	/* track changes */
	$status = 0;

	/* import into: vdef */
	$_vdef_id = db_fetch_cell_prepared('SELECT id 
		FROM vdef 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_vdef_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM vdef
			WHERE id = ?', 
			array($_vdef_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_vdef_id) ? '0' : $_vdef_id);
	$save['hash'] = $hash;

	$fields_vdef_edit = preset_vdef_form_list();
	foreach ($fields_vdef_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'vdef');

	if (!$preview_only) {
		$vdef_id = sql_save($save, 'vdef');

		$hash_cache['vdef'][$hash] = $vdef_id;
	} else {
		$vdef_id = $_vdef_id;

		$hash_cache['vdef'][$hash] = $_vdef_id;
	}

	/* import into: vdef_items */
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_vdef_item_id = db_fetch_cell_prepared('SELECT id 
				FROM vdef_items 
				WHERE hash = ? 
				AND vdef_id = ?', 
				array($parsed_hash['hash'], $vdef_id));

			if (!empty($_vdef_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM vdef_items
					WHERE id = ?', 
					array($_vdef_item_id));
			} else {
				$previous_data = array();
			}

			$save['id']      = (empty($_vdef_item_id) ? '0' : $_vdef_item_id);
			$save['hash']    = $parsed_hash['hash'];
			$save['vdef_id'] = $vdef_id;

			$fields_vdef_item_edit = preset_vdef_item_form_list();
			foreach ($fields_vdef_item_edit as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'vdef_items');

			if (!$preview_only) {
				$vdef_item_id = sql_save($save, 'vdef_items');

				$hash_cache['vdef_item']{$parsed_hash['hash']} = $vdef_item_id;
			} else {
				$hash_cache['vdef_item']{$parsed_hash['hash']} = $_vdef_item_id;
			}
		}
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_vdef_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($vdef_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_data_input_method($hash, &$xml_array, &$hash_cache) {
	global $fields_data_input_edit, $fields_data_input_field_edit, $fields_data_input_field_edit_1, $preview_only, $import_debug_info;

	/* track changes */
	$status = 0;

	/* aggregate field arrays */
	$fields_data_input_field_edit += $fields_data_input_field_edit_1;

	/* import into: data_input */
	$_data_input_id = db_fetch_cell_prepared('SELECT id 
		FROM data_input 
		WHERE hash = ?', 
		array($hash));

	if (!empty($_data_input_id)) {
		$previous_data = db_fetch_row_prepared('SELECT * 
			FROM data_input
			WHERE id = ?', 
			array($_data_input_id));
	} else {
		$previous_data = array();
	}

	$save['id']   = (empty($_data_input_id) ? '0' : $_data_input_id);
	$save['hash'] = $hash;

	foreach ($fields_data_input_edit as $field_name => $field_array) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			/* fix issue with data input method importing and white spaces */
			if ($field_name == 'input_string') {
				$xml_array[$field_name] = str_replace('><', '> <', $xml_array[$field_name]);
				$xml_array[$field_name] = str_replace('>""<', '>" "<', $xml_array[$field_name]);
				$xml_array[$field_name] = str_replace('>\'\'<', '>\' \'<', $xml_array[$field_name]);
			}

			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	/* check for status changes */
	$status += compare_data($save, $previous_data, 'data_input');

	if (!$preview_only) {
		$data_input_id = sql_save($save, 'data_input');

		$hash_cache['data_input_method'][$hash] = $data_input_id;

		update_replication_crc(0, 'poller_replicate_data_input_crc');
	} else {
		$data_input_id = $_data_input_id;

		$hash_cache['data_input_method'][$hash] = $_data_input_id;
	}

	/* import into: data_input_fields */
	if (is_array($xml_array['fields'])) {
		foreach ($xml_array['fields'] as $item_hash => $item_array) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_input_field_id = db_fetch_cell_prepared('SELECT id 
				FROM data_input_fields 
				WHERE hash= ? 
				AND data_input_id = ?', 
				array($parsed_hash['hash'], $data_input_id));

			if (!empty($_data_input_field_id)) {
				$previous_data = db_fetch_row_prepared('SELECT * 
					FROM data_input_fields
					WHERE id = ?', 
					array($_data_input_field_id));
			} else {
				$previous_data = array();
			}

			$save['id']            = (empty($_data_input_field_id) ? '0' : $_data_input_field_id);
			$save['hash']          = $parsed_hash['hash'];
			$save['data_input_id'] = $data_input_id;

			foreach ($fields_data_input_field_edit as $field_name => $field_array) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			/* check for status changes */
			$status += compare_data($save, $previous_data, 'data_input_fields');

			if (!$preview_only) {
				$data_input_field_id = sql_save($save, 'data_input_fields');

				$hash_cache['data_input_field']{$parsed_hash['hash']} = $data_input_field_id;

				update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
			} else {
				$hash_cache['data_input_field']{$parsed_hash['hash']} = $_data_input_field_id;
			}
		}
	}

	/* update field use counter cache if possible */
	if (!$preview_only) {
		if ((isset($xml_array['input_string'])) && (!empty($data_input_id))) {
			generate_data_input_field_sequences($xml_array['input_string'], $data_input_id);
		}
	}

	/* status information that will be presented to the user */
	$import_debug_info['type']   = (empty($_data_input_id) ? 'new' : ($status > 0 ? 'updated':'unchanged'));
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview':(empty($data_input_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function compare_data($save, $previous_data, $table) {
	global $preview_only, $import_debug_info;

	if ($preview_only) {
		$ignores = array(
			'task_item_id',
			'gprint_id',
			'cdef_id',
			'vdef_id',
			'consolidation_function_id',
			'data_input_id',
			'graph_template_id',
			'data_template_id',
			'data_input_field_id'
		);
	} else {
		$ignores = array();
	}

	if (!sizeof($previous_data)) {
		return 0;
	} else {
		$different = 0;
		foreach($save as $column => $value) {
			if (array_search($column, $ignores) !== false) continue;

			if ($previous_data[$column] != $value) {
				$cols = db_get_table_column_types($table);

				if (strstr($cols[$column]['type'], 'int') !== false ||
					strstr($cols[$column]['type'], 'float') !== false ||
					strstr($cols[$column]['type'], 'decimal') !== false ||
					strstr($cols[$column]['type'], 'double') !== false) {

					if (empty($previous_data[$column]) && empty($value)) {
						continue;
					}
				} elseif (empty($previous_data[$column]) && empty($value)) {
					continue;
				}

				$different++;
				$import_debug_info['differences'][] = 'Table: ' . $table . ', Column: ' . $column . ', New Value: \'' . $value . '\', Old Value: \'' . $previous_data[$column] . '\''; 
			}
		}

		return $different;
	}
}

function hash_to_friendly_name($hash, $display_type_name) {
	global $hash_type_names;

	/* parse information from the hash */
	$parsed_hash = parse_xml_hash($hash);

	/* invalid/wrong hash */
	if ($parsed_hash == false) { return false; }

	if ($display_type_name == true) {
		$prepend = '(<em>' . $hash_type_names{$parsed_hash['type']} . '</em>) ';
	} else {
		$prepend = '';
	}

	switch ($parsed_hash['type']) {
	case 'graph_template':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_template':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM data_template WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_template_item':
		return $prepend . db_fetch_cell_prepared('SELECT data_source_name FROM data_template_rrd WHERE hash = ?', array($parsed_hash['hash']));
	case 'host_template':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM host_template WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_input_method':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM data_input WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_input_field':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM data_input_fields WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_query':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE hash = ?', array($parsed_hash['hash']));
	case 'gprint_preset':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM graph_templates_gprint WHERE hash = ?', array($parsed_hash['hash']));
	case 'cdef':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM cdef WHERE hash = ?', array($parsed_hash['hash']));
	case 'vdef':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM vdef WHERE hash = ?', array($parsed_hash['hash']));
	case 'data_source_profile':
		return $prepend . db_fetch_cell_prepared('SELECT name FROM data_source_profile WHERE hash = ?', array($parsed_hash['hash']));
	case 'round_robin_archive':
		return $prepend;
	}
}

function resolve_hash_to_id($hash, &$hash_cache_array) {
	global $import_debug_info;

	/* parse information from the hash */
	$parsed_hash = parse_xml_hash($hash);

	/* invalid/wrong hash */
	if ($parsed_hash == false) { return false; }

	if (isset($hash_cache_array[$parsed_hash['type']][$parsed_hash['hash']])) {
		$import_debug_info['dep'][$hash] = 'met';
		return $hash_cache_array[$parsed_hash['type']][$parsed_hash['hash']];
	} else {
		$import_debug_info['dep'][$hash] = 'unmet';
		return 0;
	}
}

function parse_xml_hash($hash) {
	if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $hash, $matches)) {
		$parsed_hash['type']    = check_hash_type($matches[1]);
		$parsed_hash['version'] = strval(check_hash_version($matches[2]));
		$parsed_hash['hash']    = $matches[3];

		/* an error has occurred */
		if (($parsed_hash['type'] === false) || ($parsed_hash['version'] === false)) {
			cacti_log(__FUNCTION__ . ' ERROR type or version not found for hash: ' . $hash, false, 'IMPORT', POLLER_VERBOSITY_LOW);
			return false;
		}
	} else {
		cacti_log(__FUNCTION__ . ' ERROR wrong hash format for hash: ' . $hash, false, 'IMPORT', POLLER_VERBOSITY_LOW);
		return false;
	}

	return $parsed_hash;
}

function check_hash_type($hash_type) {
	global $hash_type_codes;

	/* lets not mess up the pointer for other people */
	$local_hash_type_codes = $hash_type_codes;

	foreach ($local_hash_type_codes as $type => $code) {
		if ($code == $hash_type) {
			$current_type = $type;
		}
	}

	if (!isset($current_type)) {
		raise_message(18); /* error: cannot find type */
		return false;
	}

	return $current_type;
}

function check_hash_version($hash_version) {
	global $cacti_version_codes, $config;

	$i = 0;

	foreach ($cacti_version_codes as $version => $code) {
		if ($version == CACTI_VERSION) {
			$current_version_index = $i;
		}

		if ($code == $hash_version) {
			$hash_version_index = $i;
			$current_version = $version;
		}

		$i++;
	}

	if (!isset($current_version_index)) {
		cacti_log("ERROR: $hash_version Current Cacti Version does not exist!", false, 'IMPORT', POLLER_VERBOSITY_HIGH);
		raise_message(15); /* error: current cacti version does not exist! */
		return false;
	} elseif (!isset($hash_version_index)) {
		cacti_log("ERROR: $hash_version hash version does not exist!", false, 'IMPORT', POLLER_VERBOSITY_HIGH);
		raise_message(16); /* error: hash version does not exist! */
		return false;
	} elseif ($hash_version_index > $current_version_index) {
		cacti_log("ERROR: $hash_version hash version if for a newer Cacti!", false, 'IMPORT', POLLER_VERBOSITY_HIGH);
		raise_message(17); /* error: hash made with a newer version of cacti */
		return false;
	}

	return $current_version;
}

function get_version_index($string_version) {
	global $cacti_version_codes;

	$i = 0;

	foreach ($cacti_version_codes as $version => $code) {
		if ($string_version == $version) {
			return $i;
		}

		$i++;
	}

	/* version index not found */
	return -1;
}

function xml_character_decode($text) {
	if (function_exists('html_entity_decode')) {
		return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	} else {
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		return strtr($text, $trans_tbl);
	}
}

function import_display_results($import_debug_info, $filestatus, $web = false, $preview = false) {
	global $hash_type_names;

	// Capture to a buffer
	ob_start();

	if (sizeof($import_debug_info)) {
		html_start_box(($preview ? __('Import Preview Results'):__('Import Results')), '100%', '', '3', 'center', '');

		if (sizeof($filestatus)) {
			if ($preview) {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti would make the following changes if the Package was imported:') . "</p>\n";
			} else {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti has imported the following items for the Package:') . "</p>\n";
			}

			print "<p><strong>" . __('Package Files') . "</strong></p>\n";

			print "<ul>";
			foreach($filestatus as $filename => $status) {
				print "<li>" . ($preview ? __("[preview] "):"") . $filename . " [" . $status . "]</li>\n";
			}
			print "</ul>";
		} else {
			if ($preview) {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti would make the following changes if the Template was imported:') . "</p>\n";
			} else {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti has imported the following items for the Template:') . "</p>\n";
			}
		}

		foreach ($import_debug_info as $type => $type_array) {
			print "<p><strong>" . $hash_type_names[$type] . "</strong></p>\n";

			foreach ($type_array as $index => $vals) {
				if ($vals['result'] == 'success') {
					$result_text = "<span class='success'>" . __('[success]') . "</span>";
				} elseif ($vals['result'] == 'fail') {
					$result_text = "<span class='failed'>" . __('[fail]') . "</span>";
				} else {
					$result_text = "<span class='success'>" . __('[preview]') . "</span>";
				}

				if ($vals['type'] == 'updated') {
					$type_text = "<span class='updateObject'>" . __('[updated]') . "</span>\n";
				} elseif ($vals['type'] == 'new') {
					$type_text = "<span class='newObject'>" . __('[new]') . "</span>\n";
				} else {
					$type_text = "<span class='deviceUp'>" . __('[unchanged]') . "</span>\n";
				}

				print "<span class='monoSpace'>$result_text " . htmlspecialchars($vals['title']) . " $type_text</span><br>\n";

				if (isset($vals['orphans'])) {
					print '<ul class="monoSpace">';
					foreach($vals['orphans'] as $orphan) {
						print "<li>" . htmlspecialchars($orphan) . "</li>\n";
					}
					print '</ul>';
				}

				if (isset($vals['new_items'])) {
					print '<ul class="monoSpace">';
					foreach($vals['new_items'] as $item) {
						print "<li>" . htmlspecialchars($item) . "</li>\n";
					}
					print '</ul>';
				}

				if (isset($vals['differences'])) {
					print '<ul class="monoSpace">';
					foreach($vals['differences'] as $diff) {
						print "<li>" . htmlspecialchars($diff) . "</li>\n";
					}
					print '</ul>';
				}

				if (!$preview) {
					$dep_text   = '';
					$dep_errors = false;

					if ((isset($vals['dep'])) && (sizeof($vals['dep']) > 0)) {
						foreach ($vals['dep'] as $dep_hash => $dep_status) {
							if ($dep_status == 'met') {
								$dep_status_text = "<span class='foundDependency'>" . __('Found Dependency:') . "</span>";
							} else {
								$dep_status_text = "<span class='unmetDependency'>" . __('Unmet Dependency:') . "</span>";
								$dep_errors = true;
							}

							$dep_text .= "<span class='monoSpace'>&nbsp;&nbsp;&nbsp;+ $dep_status_text " . hash_to_friendly_name($dep_hash, true) . "</span><br>\n";
						}
					}

					/* only print out dependency details if they contain errors; otherwise it would get too long */
					if ($dep_errors == true) {
						print $dep_text;
					}
				}
			}
		}

		print '</td></tr>';

		html_end_box();
	}

	if ($web) {
		print ob_get_clean();
	} else {
		$output = ob_get_clean();
		$output = explode("\n", $output);
		if (sizeof($output)) {
			foreach($output as $line) {
				$line = trim(str_replace('&nbsp;', '', strip_tags($line)));

				if ($line != '') {
					print $line . "\n";
				}
			}
		}
	}
}

function xml_to_array($data) {
    if (is_object($data)) {
        $data = get_object_vars($data);
    }
    return (is_array($data)) ? array_map(__FUNCTION__,$data) : $data;
}

