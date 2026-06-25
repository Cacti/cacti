<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function import_xml_data(string &$xml_data, bool $import_as_new, int $profile_id,
	bool $remove_orphans = false, bool $replace_svalues = false, array $import_hashes = []) : mixed {
	global $hash_type_codes, $cacti_version_codes, $ignorable_hashes, $preview_only;
	global $import_debug_info, $import_messages, $legacy_template;

	include_once(CACTI_PATH_LIBRARY . '/xml.php');

	$info_array       = [];
	$files            = [];
	$ignorable_hashes = [];
	$dep_hash_cache   = [];

	$xml_array = xml2array($xml_data);

	if (cacti_sizeof($xml_array) == 0) {
		$import_messages[] = 7; // xml parse error

		return $info_array;
	}

	$host_template_data = [];

	foreach ($xml_array as $hash => $hash_array) {
		// parse information from the hash
		$parsed_hash = parse_xml_hash($hash);

		if ($parsed_hash['type'] == 'graph_template') {
			$host_template_data['graph_template'][$parsed_hash['hash']] = $hash_array['name'];
		} elseif ($parsed_hash['type'] == 'data_query') {
			$host_template_data['data_query'][$parsed_hash['hash']] = $hash_array['name'];
		}

		// invalid/wrong hash
		if ($parsed_hash == false) {
			return $info_array;
		}

		if (!cacti_sizeof($import_hashes) || in_array($parsed_hash['hash'], $import_hashes, true)) {
			if (isset($dep_hash_cache[$parsed_hash['type']])) {
				array_push($dep_hash_cache[$parsed_hash['type']], $parsed_hash);
			} else {
				$dep_hash_cache[$parsed_hash['type']] = [$parsed_hash];
			}
		}
	}

	// Populate the hash cache with preexisting objects.
	$hash_cache = [];

	$hash_types_to_db_info = [
		'graph_template' => [
			'table' => 'graph_templates',
		],
		'graph_template_item' => [
			'table' => 'graph_templates_item',
		],
		'graph_template_input' => [
			'table' => 'graph_template_input',
		],
		'data_template' => [
			'table' => 'data_template',
		],
		'data_template_item' => [
			'table' => 'data_template_rrd',
		],
		'host_template' => [
			'table' => 'host_template',
		],
		'data_input_method' => [
			'table' => 'data_input',
		],
		'data_input_field' => [
			'table' => 'data_input_fields',
		],
		'data_query' => [
			'table' => 'snmp_query',
		],
		'data_query_graph' => [
			'table' => 'snmp_query_graph',
		],
		'data_query_sv_graph' => [
			'table' => 'snmp_query_graph_sv',
		],
		'data_query_sv_data_source' => [
			'table' => 'snmp_query_graph_rrd_sv',
		],
		'gprint_preset' => [
			'table' => 'graph_templates_gprint',
		],
		'cdef' => [
			'table' => 'cdef',
		],
		'cdef_item' => [
			'table' => 'cdef_items',
		],
		'vdef' => [
			'table' => 'vdef',
		],
		'vdef_item' => [
			'table' => 'vdef_items',
		],
		'data_source_profiles' => [
			'table' => 'data_source_profiles',
		],
		'data_source_profile_rra' => [
			'table'      => 'data_source_profiles_rra',
			'hash_field' => 'name',
		],
	];

	$hash_cache_sql_union_selects = [];

	foreach ($hash_types_to_db_info as $hash_type => $db_info) {
		$db_hash_field = (
			$db_info['hash_field'] ?? 'hash'
		);

		$db_table = $db_info['table'];

		$hash_cache_sql_union_selects[] = "SELECT '$hash_type' AS type, id, $db_hash_field AS hash
			FROM `$db_table`
			WHERE $db_hash_field != ''";
	}

	$hash_cache_sql     = implode(' UNION ALL ', $hash_cache_sql_union_selects);
	$hash_cache_results = db_fetch_assoc($hash_cache_sql);

	if (cacti_sizeof($hash_cache_results)) {
		foreach ($hash_cache_results as $hash_cache_row) {
			$hash_cache[$hash_cache_row['type']][$hash_cache_row['hash']] = $hash_cache_row['id'];
		}
	}

	$repair = 0;

	/**
	 * We will make two passes through the template array.
	 *
	 * In the first pass, we traverse the entire array
	 * and see what Data Input Methods are present and setup
	 * and ignore array for problems that have accumulated in
	 * Templates throughout the years, and in the second pass,
	 * we will process them.
	 */
	foreach ($hash_type_codes as $type => $code) {
		// do we have any matches for this type?
		if (isset($dep_hash_cache[$type])) {
			// yes we do. loop through each match for this type
			for ($i = 0; $i < cacti_count($dep_hash_cache[$type]); $i++) {
				if (!isset($cacti_version_codes[$dep_hash_cache[$type][$i]['version']])) {
					return false;
				}

				if (isset($xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $cacti_version_codes[$dep_hash_cache[$type][$i]['version']] . $dep_hash_cache[$type][$i]['hash']])) {
					$hash_array = $xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $cacti_version_codes[$dep_hash_cache[$type][$i]['version']] . $dep_hash_cache[$type][$i]['hash']];
				} elseif (isset($xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $dep_hash_cache[$type][$i]['hash']])) {
					$hash_array = $xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $dep_hash_cache[$type][$i]['hash']];
				} else {
					$import_messages[] = 7; // xml parse error

					return false;
				}

				if ($type == 'data_input_method') {
					if (xml_detect_ignorable_hash_cache($dep_hash_cache[$type][$i]['hash'], $hash_array)) {
						$repair++;
					}
				}
			}
		}
	}

	/**
	 * Second pass, we will actually perform the import of the entirety of the Template.
	 *
	 * The order of the $hash_type_codes array is ordered such that the items
	 * with the most dependencies are last and the items with no dependencies are first.
	 * this means dependencies will just magically work themselves out :)
	 */
	foreach ($hash_type_codes as $type => $code) {
		// do we have any matches for this type?
		if (isset($dep_hash_cache[$type])) {
			// yes we do. loop through each match for this type
			for ($i = 0; $i < cacti_count($dep_hash_cache[$type]); $i++) {
				$import_debug_info = [];

				if (!isset($cacti_version_codes[$dep_hash_cache[$type][$i]['version']])) {
					return false;
				}

				cacti_log('$dep_hash_cache[$type][$i][\'type\']: ' . $dep_hash_cache[$type][$i]['type'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$dep_hash_cache[$type][$i][\'version\']: ' . $dep_hash_cache[$type][$i]['version'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$cacti_version_codes[$dep_hash_cache[$type][$i][\'version\']]: ' . $cacti_version_codes[$dep_hash_cache[$type][$i]['version']], false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				cacti_log('$dep_hash_cache[$type][$i][\'hash\']: ' . $dep_hash_cache[$type][$i]['hash'], false, 'IMPORT', POLLER_VERBOSITY_HIGH);

				if (isset($xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $cacti_version_codes[$dep_hash_cache[$type][$i]['version']] . $dep_hash_cache[$type][$i]['hash']])) {
					$hash_array = $xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $cacti_version_codes[$dep_hash_cache[$type][$i]['version']] . $dep_hash_cache[$type][$i]['hash']];
				} elseif (isset($xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $dep_hash_cache[$type][$i]['hash']])) {
					$hash_array = $xml_array['hash_' . $hash_type_codes[$dep_hash_cache[$type][$i]['type']] . $dep_hash_cache[$type][$i]['hash']];
				} else {
					$import_messages[] = 7; // xml parse error

					return false;
				}

				switch($type) {
					case 'graph_template':
						$hash_cache += xml_to_graph_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $dep_hash_cache[$type][$i]['version'], $remove_orphans);

						break;
					case 'data_template':
						$hash_cache += xml_to_data_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $import_as_new, $profile_id);
						$repair++;

						break;
					case 'host_template':
						$hash_cache += xml_to_host_template($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $host_template_data);

						break;
					case 'data_input_method':
						$hash_cache += xml_to_data_input_method($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);
						$repair++;

						break;
					case 'data_query':
						$hash_cache += xml_to_data_query($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache, $files, $replace_svalues);

						break;
					case 'gprint_preset':
						$hash_cache += xml_to_gprint_preset($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);

						break;
					case 'cdef':
						$return = xml_to_cdef($dep_hash_cache[$type][$i]['hash'], $hash_array, $hash_cache);

						if ($return !== false) {
							$hash_cache += $return;
						}

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
					default:
						$param               = [];
						$param['hash']       = $dep_hash_cache[$type][$i]['hash'];
						$param['xml_array']  = $hash_array;
						$param['hash_cache'] = $hash_cache;
						$param['type']       = $type;
						$param['version']    = $dep_hash_cache[$type][$i]['version'];
						$param               = api_plugin_hook_function('import_action', $param);
						$hash_cache += $param['hash_cache'];

						break;
				}

				if (cacti_sizeof($import_debug_info)) {
					if (isset($info_array[$type])) {
						$info_array[$type][cacti_count($info_array[$type])] = $import_debug_info;
					} else {
						$info_array[$type][0] = $import_debug_info;
					}
				}
			}
		}
	}

	if (cacti_sizeof($files)) {
		$info_array['files'] = $files;
	}

	if ($repair) {
		repair_system_data_input_methods();
	}

	return $info_array;
}

function is_cacti_public_key(string $public_key) : bool {
	$public_key = trim($public_key);
	$keys[]     = get_public_key_sha1();
	$keys[]     = get_public_key_sha256();

	foreach ($keys as $key) {
		if ($public_key === $key) {
			return true;
		}
	}

	return false;
}

function get_public_key_sha1() : string {
	return get_public_key();
}

function get_public_key_sha256() : string {
	$public_key = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApH0rQ6cEYMCeHh5b7zCw\n5Mxzrj5N6PNW4NJE6YvjpzR40SE/B+vGnwpQZB+bmAVPJcn7TgUf5+ZnPoLL7BNn\nfFhDOREzQYhcTGTxTFQ/AD/DdgzyALdWsV14mwkaxKchnY3XZY1Jg/tm+AFOBrEX\n3Oa4pkOf7+V2HXVhbMhWrsoW5/tI8AQBQtzadqxXDGMpwlwKb6QNlUPk1slQFn3e\nk9rpWgq/84OxsJs2MVFyo/Nh6ehu8cE7OYHOJ/1qQ+8w99ro+zllwLqStY3/Z3Bl\nQmGcllo3/LfnWc10aqdtpFOxWcJwzkQ1vvjzAuWYPmW/fNbft3+pRuS7sa2jj/oN\nvQIDAQAB\n-----END PUBLIC KEY-----";

	return $public_key;
}

function get_public_key() : string {
	$public_key = "-----BEGIN PUBLIC KEY-----\nMFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAMbPpuQfwmg93oOGjdLKrAqwEPwvvNjC\nbk2YZiDglh8lQJxNQI9glG1Z/ptvqprFO3iSx9rTP4vzZ0Ek2+EMYTMCAwEAAQ==\n-----END PUBLIC KEY-----";

	return $public_key;
}

function import_package_get_public_key(string $xmlfile) : string {
	$data = import_package_get_details($xmlfile);

	if (isset($data['public_key'])) {
		return $data['public_key'];
	} else {
		return get_public_key();
	}
}

function import_package_get_name(string $xmlfile) : string {
	$data = import_package_get_details($xmlfile);

	if (isset($data['name'])) {
		return $data['name'];
	} else {
		return __('Unknown');
	}
}

function import_package_get_details(string $xmlfile) : array {
	$filename = "compress.zlib://$xmlfile";

	$return = [];
	$data   = file_get_contents($filename);
	$xmlget = simplexml_load_string($data);
	$pkgarr = xml_to_array($xmlget);
	$return = $pkgarr['info'];

	if (isset($pkgarr['publickey'])) {
		$return['public_key'] = base64_decode($pkgarr['publickey'], true);
	} else {
		$return['public_key'] = get_public_key();
	}

	if (isset($pkgarr['publickeyname'])) {
		$return['public_key_name'] = base64_decode($pkgarr['publickeyname'], true);
	}

	if (!isset($return['name']) || is_array($return['name'])) {
		$return['name'] = 'Unknown';
	}

	if (!isset($return['tags']) || is_array($return['tags'])) {
		$return['tags'] = '';
	}

	if (!isset($return['installation']) || is_array($return['installation'])) {
		$return['installation'] = '';
	}

	if (!isset($return['version']) || is_array($return['version'])) {
		$return['version'] = 'Prior to 1.2.23';
	}

	return $return;
}

function import_validate_signature(string $xmlfile) : mixed {
	// Cacti public key first
	$cacti_key1   = get_public_key_sha1();
	$cacti_key2   = get_public_key_sha256();
	$public_key   = import_package_get_public_key($xmlfile);

	$info = import_get_package_info($xmlfile);

	if ($info === false) {
		return false;
	} else {
		if (!isset($info['pubkey'])) {
			return false;
		}

		// Other trusted keys next
		$keys = array_rekey(
			db_fetch_assoc('SELECT public_key FROM package_public_keys'),
			'public_key', 'public_key'
		);

		$keys[$cacti_key1] = $cacti_key1;
		$keys[$cacti_key2] = $cacti_key2;

		if (in_array($public_key, $keys, true)) {
			$info['valid'] = true;
		} else {
			$info['valid'] = false;
		}

		return $info;
	}
}

function import_get_package_info(string $xmlfile) : mixed {
	if (!file_exists($xmlfile)) {
		return false;
	}

	$filename = "compress.zlib://$xmlfile";

	$data = file_get_contents($filename);

	if ($data != '') {
		$name              = '';
		$author            = '';
		$homepage          = '';
		$email             = '';
		$package_keyname   = '';
		$package_publickey = '';

		$xml = xml2array($data);

		if (cacti_sizeof($xml)) {
			if (isset($xml['info']['name'])) {
				$name = $xml['info']['name'];
			}

			if (isset($xml['info']['author'])) {
				$author = $xml['info']['author'];
			}

			if (isset($xml['info']['homepage'])) {
				$homepage = $xml['info']['homepage'];
			}

			if (isset($xml['info']['email'])) {
				$email = $xml['info']['email'];
			}

			if (isset($xml['publickeyname'])) {
				$package_keyname = $xml['publickeyname'];
			}

			if (isset($xml['publickey'])) {
				$package_publickey = base64_decode($xml['publickey'], true);
			}

			return [
				'name'     => $name,
				'author'   => $author,
				'homepage' => $homepage,
				'email'    => $email,
				'pubkey'   => $package_publickey
			];
		}
	}

	return false;
}

function import_read_package_data(string $xmlfile, string &$public_key, bool $preview = false) : mixed {
	$public_key = import_package_get_public_key($xmlfile);

	$filename = "compress.zlib://$xmlfile";

	if (!import_validate_signature($xmlfile) && !$preview) {
		cacti_log('FATAL: Package Public Key is not Official Cacti Public Key for Package ' . $filename, true, 'IMPORT', POLLER_VERBOSITY_LOW);

		return false;
	}

	$f   = fopen($filename, 'r');
	$xml = '';

	$binary_signature = '';

	if (is_resource($f)) {
		while (!feof($f)) {
			$x = fgets($f);

			if (strlen($x) == 0) {
				cacti_log('FATAL: Unable to read Cacti Package ' . $filename, true, 'IMPORT', POLLER_VERBOSITY_LOW);
				fclose($f);

				return false;
			}

			if (str_contains($x, '<signature>')) {
				$binary_signature =  base64_decode(trim(str_replace(['<signature>', '</signature>'], ['', ''], $x)), true);
				$x                = "   <signature></signature>\n";

				cacti_log('NOTE: Got Package Signature', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
			}

			$xml .= "$x";
		}

		fclose($f);
	} else {
		cacti_log('FATAL: Unable to open file ' . $filename, true, 'IMPORT', POLLER_VERBOSITY_LOW);

		return false;
	}

	// Verify Signature
	if (strlen($public_key) < 200) {
		$ok = openssl_verify($xml, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
	} else {
		$ok = openssl_verify($xml, $binary_signature, $public_key, OPENSSL_ALGO_SHA256);
	}

	if ($ok == 1) {
		cacti_log('NOTE: File is Signed Correctly', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
	} elseif ($ok == 0) {
		cacti_log('FATAL: File has been Tampered with.', false, 'IMPORT', POLLER_VERBOSITY_LOW);

		return false;
	} else {
		cacti_log('FATAL: Could not Verify Signature.', false, 'IMPORT', POLLER_VERBOSITY_LOW);

		return false;
	}

	cacti_log('Loading Plugin Information from package', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);

	$xmlget = simplexml_load_string($xml);
	$data   = xml_to_array($xmlget);

	if (cacti_sizeof($data)) {
		return $data;
	} else {
		return false;
	}
}

/**
 * import_package - This function will selectively import some or all of the
 * components of a Cacti, Provide a preview of the import, or provide information
 * about the Package depending upon the settings provided below.
 *
 * This function can also read from the $_REQUEST environment certain overrides for
 * Graph Size, and Image format when importing through the GUI.
 *
 * @param string $xmlfile         The XML file to process
 * @param int    $profile_id      The Data Source Profile to use for the packages
 * @param bool   $remove_orphans  Boolean true to remove Graph Template orphans after import
 * @param bool   $replace_svalues Boolean that if true, all suggested values for Graph Templates
 *                                and Data Templates will be replaced with the values in the
 *                                package.
 * @param bool   $preview         If true, only generate a preview of what will be imported
 *                                and the corresponding changes.
 * @param bool   $info_only       Return only the information about the package, not details
 * @param bool   $limitex         Limit the execution time to 50 seconds to process for larger
 *                                packages.
 * @param array  $import_hashes   The hashes to import from the package
 * @param array  $import_files    The XML resource files and script files to import from the package
 *
 * @return mixed The results of the import package
 */
function import_package(string $xmlfile, int $profile_id = 1, bool $remove_orphans = false,
	bool $replace_svalues = false, bool $preview = false, bool $info_only = false, bool $limitex = true,
	array $import_hashes = [], array $import_files = []) : mixed {
	global $preview_only;

	$preview_only = $preview;
	$public_key   = '';
	$debug_data   = [];
	$existing     = '';

	// set new timeout and memory settings
	if ($limitex) {
		ini_set('max_execution_time', '50');
		ini_set('memory_limit', '-1');
	}

	$data = import_read_package_data($xmlfile, $public_key, $preview);

	if (!$data) {
		return false;
	}

	$filestatus = [];

	if ($info_only) {
		return $data['info'];
	}

	cacti_log('Verifying each files signature', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);

	if (isset($data['files']['file']['data'])) {
		$data['files']['file'] = [$data['files']['file']];
	}

	foreach ($data['files']['file'] as $f) {
		$binary_signature = base64_decode($f['filesignature'], true);
		$fdata            = base64_decode($f['data'], true);

		if (strlen($public_key) < 200) {
			$ok = openssl_verify($fdata, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
		} else {
			$ok = openssl_verify($fdata, $binary_signature, $public_key, OPENSSL_ALGO_SHA256);
		}

		if ($ok == 1) {
			cacti_log('NOTE: File OK: ' . $f['name'], false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
		} else {
			cacti_log('FATAL: Could not Verify Signature for file: ' . $f['name'], true, 'IMPORT', POLLER_VERBOSITY_LOW);

			return false;
		}
	}

	if (!$preview) {
		cacti_log('Processing Files for Import', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
	} else {
		cacti_log('Processing Files for Preview', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
	}

	// Resolve allowed bases once; the foreach below only consults these.
	$allowed_base_scripts  = realpath(CACTI_PATH_BASE . '/scripts');
	$allowed_base_resource = realpath(CACTI_PATH_BASE . '/resource');
	$normalized_scripts    = ($allowed_base_scripts === false) ? false : rtrim(str_replace('\\', '/', $allowed_base_scripts),  '/');
	$normalized_resource   = ($allowed_base_resource === false) ? false : rtrim(str_replace('\\', '/', $allowed_base_resource), '/');

	foreach ($data['files']['file'] as $f) {
		$name            = $f['name'];
		$normalized_name = str_replace('\\', '/', $name);

		if (strpos($name, chr(0)) !== false || preg_match('#(^|/)\.\.(/|$)#', $normalized_name)) {
			cacti_log("WARNING: Skipping file with path traversal attempt: $name", false, 'IMPORT');

			continue;
		}

		// Reject absolute paths: Unix (/...), Windows (\... or C:\...).
		if (preg_match('#^([/\\\\]|[A-Za-z]:)#', $name)) {
			cacti_log("WARNING: Skipping file with absolute path: $name", false, 'IMPORT');

			continue;
		}

		$fdata = base64_decode($f['data'], true);

		if (str_contains($name, 'scripts/') || str_contains($name, 'resource/')) {
			$filename = CACTI_PATH_BASE . "/$name";

			// Allow new nested subdirectories, but ensure the first existing ancestor
			// still resolves within the intended scripts/resource boundary.
			$target_dir   = dirname($filename);
			$resolved_dir = false;

			while ($target_dir !== dirname($target_dir)) {
				$resolved_dir = realpath($target_dir);

				if ($resolved_dir !== false) {
					break;
				}

				$target_dir = dirname($target_dir);
			}

			if ($resolved_dir === false) {
				cacti_log('FATAL: Package file destination outside allowed boundaries: ' . $name, true, 'IMPORT');

				continue;
			}

			$normalized_resolved = rtrim(str_replace('\\', '/', $resolved_dir), '/');
			$in_scripts          = $normalized_scripts !== false
				&& ($normalized_resolved === $normalized_scripts || strpos($normalized_resolved, $normalized_scripts . '/') === 0);
			$in_resource         = $normalized_resource !== false
				&& ($normalized_resolved === $normalized_resource || strpos($normalized_resolved, $normalized_resource . '/') === 0);

			if (!$in_scripts && !$in_resource) {
				cacti_log('FATAL: Package file destination outside allowed boundaries: ' . $name, true, 'IMPORT');

				continue;
			}

			if (!$preview) {
				if (!cacti_sizeof($import_files) || in_array($name, $import_files, true)) {
					cacti_log('Writing file: ' . $filename, false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);

					if ((is_writable(dirname($filename)) && !file_exists($filename)) || is_writable($filename)) {
						$file = fopen($filename, 'wb');

						if (is_resource($file)) {
							fwrite($file , $fdata, strlen($fdata));
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

					cacti_log('Write Status file: ' . $filename . ', with Status ' . $filestatus[$filename], false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
				}
			} else {
				cacti_log('Previewing file: ' . $filename, false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);

				$new = md5($fdata);

				if (file_exists($filename)) {
					$existing = md5_file($filename);
				}

				if (is_writable(dirname($filename))) {
					if (file_exists($filename) && is_writable($filename)) {
						if ($new == $existing) {
							$filestatus[$filename] = 'writable, identical';
						} else {
							$filestatus[$filename] = 'writable, differences';
						}
					} elseif (file_exists($filename) && is_writable($filename)) {
						$filestatus[$filename] = 'writable, new';
					} elseif (file_exists($filename) && !is_writable($filename)) {
						if ($new == $existing) {
							$filestatus[$filename] = 'not writable, identical';
						} else {
							$filestatus[$filename] = 'not writable, differences';
						}
					} else {
						$filestatus[$filename] = 'writable, new';
					}
				} elseif (file_exists($filename)) {
					if ($new == $existing) {
						$filestatus[$filename] = 'not writable, identical';
					} else {
						$filestatus[$filename] = 'not writable, differences';
					}
				} else {
					$filestatus[$filename] = 'not writable, new';
				}
			}
		} else {
			if (!$preview) {
				cacti_log('Importing XML Data for ' . $name, false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
			} else {
				cacti_log('Previewing XML Data for ' . $name, false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
			}

			$debug_data = import_xml_data($fdata, false, $profile_id, $remove_orphans, $replace_svalues, $import_hashes);

			if ($debug_data === false) {
				return false;
			}
		}
	}

	/**
	 * update template data with the information from the package if it's not already set
	 * through import.  This is mainly for legacy templates
	 */
	if (!$preview) {
		foreach ($data['files']['file'] as $f) {
			$fdata = base64_decode($f['data'], true);
			$name  = $f['name'];

			if (isset($f['type']) && $f['type'] == 'template') {
				$template_data = xml2array($fdata);

				if (cacti_sizeof($template_data)) {
					foreach ($template_data as $hash => $tdata) {
						$parsed_hash = parse_xml_hash($hash);

						if ($parsed_hash['type'] == 'host_template') {
							/**
							 * funny thing is that xml2array returns an empty string as an array and
							 * not as a string.  We are not going to change that here as there
							 * may be unintended consequences.
							 */
							$info = $data['info'];

							$dt = db_fetch_row_prepared('SELECT *
								FROM host_template
								WHERE hash = ?',
								[$parsed_hash['hash']]);

							if (cacti_sizeof($dt)) {
								$sets   = '';
								$params = [];

								if ($dt['version'] == '' && $info['version'] != '' && !is_array($info['version'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'version = ?';
									$params[] = $info['version'];
								}

								if ($dt['class'] == '' && $info['class'] != '' && !is_array($info['class'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'class = ?';
									$params[] = $info['class'];
								}

								if ($dt['author'] == '' && $info['author'] != '' && !is_array($info['author'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'author = ?';
									$params[] = $info['author'];
								}

								if ($dt['email'] == '' && $info['email'] != '' && !is_array($info['email'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'email = ?';
									$params[] = $info['email'];
								}

								if ($dt['homepage'] == '' && $info['homepage'] != '' && !is_array($info['homepage'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'homepage = ?';
									$params[] = $info['homepage'];
								}

								if ($dt['copyright'] == '' && $info['copyright'] != '' && !is_array($info['copyright'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'copyright = ?';
									$params[] = $info['copyright'];
								}

								if ($dt['tags'] == '' && $info['tags'] != '' && !is_array($info['tags'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'tags = ?';
									$params[] = $info['tags'];
								}

								if ($dt['installation'] == '' && $info['installation'] != '' && !is_array($info['installation'])) {
									$sets .= ($sets != '' ? ', ' : '') . 'installation = ?';

									$params[] = $info['installation'];
								}

								if (cacti_sizeof($params)) {
									$params[] = $dt['id'];

									db_execute_prepared("UPDATE host_template
										SET $sets
										WHERE id = ?", $params);
								}
							}
						} elseif ($parsed_hash['type'] == 'graph_template') {
							// Reserve for potential future use
						}
					}
				}
			}
		}
	}

	if (!$preview) {
		cacti_log('File creation complete', false, 'IMPORT', POLLER_VERBOSITY_MEDIUM);
	}

	return [$debug_data, $filestatus];
}

function xml_to_graph_template(string $hash, array &$xml_array, array &$hash_cache, string $hash_version, bool $remove_orphans = false) : mixed {
	global $struct_graph, $struct_graph_item, $fields_graph_template_input_edit, $cacti_version_codes;
	global $preview_only, $graph_item_types, $import_debug_info;

	// track changes
	$status = 0;

	// import into: graph_templates
	$_graph_template_id = db_fetch_cell_prepared('SELECT id
		FROM graph_templates
		WHERE hash = ?',
		[$hash]);

	if (!empty($_graph_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM graph_templates
			WHERE id = ?',
			[$_graph_template_id]);
	} else {
		$previous_data = [];
	}

	$save['id']    = (empty($_graph_template_id) ? '0' : $_graph_template_id);
	$save['hash']  = $hash;
	$save['name']  = $xml_array['name'];

	if (isset($xml_array['class'])) {
		$save['class'] = $xml_array['class'];
	}

	if (isset($xml_array['version'])) {
		$save['version'] = $xml_array['version'];
	}

	if (isset($xml_array['multiple'])) {
		$save['multiple'] = $xml_array['multiple'];
	}

	if (isset($xml_array['test_source'])) {
		$save['test_source'] = $xml_array['test_source'];
	} else {
		$save['test_source'] = read_config_option('default_test_source');
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'graph_templates');

	if (!$preview_only) {
		$graph_template_id = sql_save($save, 'graph_templates');

		$hash_cache['graph_template'][$hash] = $graph_template_id;
	} else {
		$graph_template_id = $_graph_template_id;

		$hash_cache['graph_template'][$hash] = $graph_template_id;
	}

	// import into: graph_templates_graph
	$save = [];

	$save['id'] = (empty($_graph_template_id) ? '0' : db_fetch_cell_prepared('SELECT gtg.id
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gt.id = gtg.graph_template_id
		WHERE gt.id = ?
		AND gtg.local_graph_id = 0',
		[$graph_template_id]));

	if (!empty($_graph_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE id = ?',
			[$save['id']]);
	} else {
		$previous_data = [];
	}

	$save['graph_template_id'] = $graph_template_id;

	foreach ($struct_graph as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array['graph']['t_' . $field_name])) {
			$save['t_' . $field_name] = $xml_array['graph']['t_' . $field_name];
		}

		// make sure this field exists in the xml array first
		if (isset($xml_array['graph'][$field_name])) {
			if ($field_name == 'unit_exponent_value' && $xml_array['graph'][$field_name] == '0') {
				$save[$field_name] = '';
			} elseif ($field_name == 'graph_width') {
				if (isrv('graph_width') && !ierv('graph_width')) {
					$save[$field_name] = gfrv('graph_width');
				} else {
					$save[$field_name] = read_config_option('default_graph_width');
				}
			} elseif ($field_name == 'graph_height') {
				if (isrv('graph_height') && !ierv('graph_height')) {
					$save[$field_name] = gfrv('graph_height');
				} else {
					$save[$field_name] = read_config_option('default_graph_height');
				}
			} elseif ($field_name == 'image_format_id') {
				if (isrv('image_format') && !ierv('image_format')) {
					$save[$field_name] = gfrv('image_format');
				} else {
					$save[$field_name] = read_config_option('default_image_format');
				}
			} else {
				$save[$field_name] = xml_character_decode($xml_array['graph'][$field_name]);
			}
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'graph_templates_graph');

	if (!$preview_only) {
		$graph_template_graph_id = sql_save($save, 'graph_templates_graph');
	} else {
		$graph_template_graph_id = $save['id'];
	}

	// import into: graph_templates_item
	if (is_array($xml_array['items'])) {
		$new_items = [];

		if ($graph_template_id > 0) {
			$items = db_fetch_assoc_prepared('SELECT *
				FROM graph_templates_item
				WHERE graph_template_id = ?
				AND local_graph_id = 0',
				[$graph_template_id]);

			if (cacti_sizeof($items)) {
				foreach ($items as $item) {
					$orphaned_items[$item['hash']] = $item;
				}
			}
		} else {
			$orphaned_items = [];
		}

		foreach ($xml_array['items'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// mark present hashes
			if (isset($orphaned_items[$parsed_hash['hash']])) {
				unset($orphaned_items[$parsed_hash['hash']]);
			}

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_graph_template_item_id = db_fetch_cell_prepared('SELECT id
				FROM graph_templates_item
				WHERE hash = ?
				AND graph_template_id = ?
				AND local_graph_id = 0',
				[$parsed_hash['hash'], $graph_template_id]);

			if (!empty($_graph_template_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM graph_templates_item
					WHERE id = ?',
					[$_graph_template_item_id]);
			} else {
				$previous_data = [];
			}

			$save['id']                = (empty($_graph_template_item_id) ? '0' : $_graph_template_item_id);
			$save['hash']              = $parsed_hash['hash'];
			$save['graph_template_id'] = $graph_template_id;

			foreach ($struct_graph_item as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					// is the value of this field a hash or not?
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache, 'graph_templates_item');
					} elseif (($field_name == 'color_id') && (preg_match('/^[a-fA-F0-9]{6}$/', $item_array[$field_name])) && (get_version_index($parsed_hash['version']) >= get_version_index('0.8.5'))) { // treat the 'color' field differently
						$color_id = db_fetch_cell_prepared('SELECT id
							FROM colors
							WHERE hex = ?',
							[$item_array[$field_name]]);

						if (empty($color_id) && !$preview_only) {
							db_execute_prepared('INSERT INTO colors (
								hex) VALUES (?)',
								[$item_array[$field_name]]);

							$color_id = db_fetch_insert_id();
						}

						if (empty($color_id) && $preview_only) {
							$color_id = $item_array[$field_name];
						}

						$save[$field_name] = $color_id;
					} elseif (($field_name == 'color2_id') && (preg_match('/^[a-fA-F0-9]{6}$/', $item_array[$field_name])) && (get_version_index($parsed_hash['version']) >= get_version_index('0.8.5'))) { // treat the 'color' field differently
						$color2_id = db_fetch_cell_prepared('SELECT id
							FROM colors
							WHERE hex = ?',
							[$item_array[$field_name]]);

						if (empty($color2_id) && !$preview_only) {
							db_execute_prepared('INSERT INTO colors (
								hex) VALUES (?)',
								[$item_array[$field_name]]);

							$color2_id = db_fetch_insert_id();
						}

						if (empty($color2_id) && $preview_only) {
							$color2_id = $item_array[$field_name];
						}

						$save[$field_name] = $color2_id;
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			if (!empty($_graph_template_id)) {
				if (!cacti_sizeof($previous_data)) {
					$new_items[$parsed_hash['hash']] = $save;
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'graph_templates_item');

			if (!$preview_only) {
				$graph_template_item_id = sql_save($save, 'graph_templates_item');

				$hash_cache['graph_template_item'][$parsed_hash['hash']] = $graph_template_item_id;
			} else {
				$hash_cache['graph_template_item'][$parsed_hash['hash']] = $_graph_template_item_id;
			}
		}
	}

	// import into: graph_template_input
	if (is_array($xml_array['inputs'])) {
		foreach ($xml_array['inputs'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_graph_template_input_id = db_fetch_cell_prepared('SELECT id
				FROM graph_template_input
				WHERE hash = ?
				AND graph_template_id = ?',
				[$parsed_hash['hash'], $graph_template_id]);

			if (!empty($_graph_template_input_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM graph_template_input
					WHERE id = ?',
					[$_graph_template_input_id]);
			} else {
				$previous_data = [];
			}

			$save['id']                = (empty($_graph_template_input_id) ? '0' : $_graph_template_input_id);
			$save['hash']              = $parsed_hash['hash'];
			$save['graph_template_id'] = $graph_template_id;

			foreach ($fields_graph_template_input_edit as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'graph_template_input');

			if (!$preview_only) {
				$graph_template_input_id = sql_save($save, 'graph_template_input');

				$hash_cache['graph_template_input'][$parsed_hash['hash']] = $graph_template_input_id;

				// import into: graph_template_input_defs
				$hash_items = explode('|', $item_array['items']);

				if (!empty($hash_items[0])) {
					for ($i = 0; $i < cacti_count($hash_items); $i++) {
						// parse information from the hash
						$parsed_hash = parse_xml_hash($hash_items[$i]);

						// invalid/wrong hash
						if ($parsed_hash == false) {
							return false;
						}

						if (isset($hash_cache['graph_template_item'][$parsed_hash['hash']])) {
							db_execute_prepared('REPLACE INTO graph_template_input_defs
								(graph_template_input_id, graph_template_item_id)
								VALUES (?, ?)',
								[$graph_template_input_id, $hash_cache['graph_template_item'][$parsed_hash['hash']]]);
						}
					}
				}
			}
		}
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_graph_template_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($graph_template_id) ? 'fail' : 'success'));

	if (isset($new_items) && cacti_sizeof($new_items)) {
		$new_text = [];

		foreach ($new_items as $item) {
			$new_text[] = 'New Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];
		}

		$import_debug_info['new_items'] = $new_text;
	}

	if (isset($orphaned_items) && cacti_sizeof($orphaned_items)) {
		$orphan_text = [];

		foreach ($orphaned_items as $item) {
			if (!$preview_only && $remove_orphans) {
				$orphan_text[] = 'Removed Orphaned Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];

				db_execute_prepared('DELETE FROM graph_templates_item
					WHERE hash = ?',
					[$item['hash']]);

				db_execute_prepared('DELETE FROM graph_templates_item
					WHERE local_graph_template_item_id = ?',
					[$item['id']]);
			} else {
				$orphan_text[] = 'Found Orphaned Graph Items, Type: ' . $graph_item_types[$item['graph_type_id']] . ', Text Format: ' . $item['text_format'] . ', Value: ' . $item['value'];
			}
		}

		$import_debug_info['orphans'] = $orphan_text;

		if (!$preview_only && $remove_orphans) {
			retemplate_graphs($graph_template_id);
		}
	}

	return $hash_cache;
}

function xml_to_data_template(string $hash, array &$xml_array, array &$hash_cache, bool $import_as_new, int $profile_id) : mixed {
	global $struct_data_source, $struct_data_source_item, $import_template_id, $preview_only;
	global $ignorable_hashes, $import_debug_info, $legacy_template;

	// track changes
	$status = 0;

	// import into: data_template
	$_data_template_id = db_fetch_cell_prepared('SELECT id
		FROM data_template
		WHERE hash = ?',
		[$hash]);

	if (!empty($_data_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM data_template
			WHERE id = ?',
			[$_data_template_id]);
	} else {
		$previous_data = [];
	}

	$save['id']   = (empty($_data_template_id) ? '0' : $_data_template_id);
	$save['hash'] = $hash;
	$save['name'] = $xml_array['name'];

	// check for status changes
	$status += compare_data($save, $previous_data, 'data_template');

	if (!$preview_only) {
		$data_template_id = sql_save($save, 'data_template');

		$hash_cache['data_template'][$hash] = $data_template_id;
	} else {
		$data_template_id = $_data_template_id;

		$hash_cache['data_template'][$hash] = $_data_template_id;
	}

	// import into: data_template_data
	$save = [];

	$save['id'] = (empty($_data_template_id) ? '0' : db_fetch_cell_prepared('SELECT dtd.id
		FROM data_template AS dt
		INNER JOIN data_template_data AS dtd
		ON dt.id=dtd.data_template_id
		WHERE dt.id = ?
		AND dtd.local_data_id = 0',
		[$data_template_id]));

	if (!empty($save['id'])) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE id = ?',
			[$save['id']]);
	} else {
		$previous_data = [];
	}

	$save['data_template_id']       = $data_template_id;
	$save['data_source_profile_id'] = $profile_id;

	foreach ($struct_data_source as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array['ds']['t_' . $field_name])) {
			$save['t_' . $field_name] = $xml_array['ds']['t_' . $field_name];
		}

		// make sure this field exists in the xml array first
		if (isset($xml_array['ds'][$field_name])) {
			// is the value of this field a hash or not?
			if ($field_name == 'data_source_profile_id') {
				$save[$field_name] = $profile_id;
			} elseif (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $xml_array['ds'][$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array['ds'][$field_name], $hash_cache, 'data_template_data');
			} else {
				$save[$field_name] = xml_character_decode($xml_array['ds'][$field_name]);
			}
		}
	}

	// set the rrd_step
	if ($profile_id > 0) {
		$save['rrd_step'] = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			[$profile_id]);
	} else {
		$profile_id = db_fetch_cell('SELECT id
			FROM data_source_profiles
			ORDER BY `default`
			DESC LIMIT 1');

		$save['rrd_step'] = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			[$profile_id]);
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'data_template_data');

	if (!$preview_only) {
		$data_template_data_id = sql_save($save, 'data_template_data');

		$import_template_id = $data_template_data_id;
	}

	// import into: data_template_rrd
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_data_template_rrd_id = db_fetch_cell_prepared('SELECT id
				FROM data_template_rrd
				WHERE hash = ?
				AND data_template_id = ?
				AND local_data_id = 0',
				[$parsed_hash['hash'], $data_template_id]);

			if (!empty($_data_template_rrd_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM data_template_rrd
					WHERE id = ?',
					[$_data_template_rrd_id]);
			} else {
				$previous_data = [];
			}

			$save['id']               = (empty($_data_template_rrd_id) ? '0' : $_data_template_rrd_id);
			$save['hash']             = $parsed_hash['hash'];
			$save['data_template_id'] = $data_template_id;

			foreach ($struct_data_source_item as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array['t_' . $field_name])) {
					$save['t_' . $field_name] = $item_array['t_' . $field_name];
				}

				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					// is the value of this field a hash or not?
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache, 'data_template_rrd');
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			// setup the rrd_heartbeat based upon the profiles setting
			if ($import_as_new === false) {
				$save['rrd_heartbeat'] = db_fetch_cell_prepared('SELECT heartbeat
					FROM data_source_profiles
					WHERE id = ?',
					[$profile_id]);
			}

			if ($legacy_template) {
				if ($save['data_source_type_id'] == 1 || $save['data_source_type_id'] == 4) {
					if ($save['rrd_maximum'] == '0' && $save['rrd_minimum'] == '0') {
						$save['rrd_maximum'] = 'U';
					}
				} elseif ($save['data_source_type_id'] == 3 || $save['data_source_type_id'] == 7) {
					if ($save['rrd_maximum'] == '0' && $save['rrd_minimum'] == '0') {
						$save['rrd_maximum'] = 'U';
						$save['rrd_minimum'] = 'U';
					}
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'data_template_rrd');

			if (!$preview_only) {
				$data_template_rrd_id = sql_save($save, 'data_template_rrd');

				if ($legacy_template) {
					// Correct max values in templates and data sources: GAUGE/ABSOLUTE (1,4)
					db_execute("UPDATE data_template_rrd
						SET rrd_maximum='U'
						WHERE rrd_maximum = '0'
						AND rrd_minimum = '0'
						AND data_template_id = $data_template_id
						AND data_source_type_id IN(1,4)");

					// Correct min/max values in templates and data sources: DERIVE/DDERIVE (3,7)
					db_execute("UPDATE data_template_rrd
						SET rrd_maximum='U', rrd_minimum='U'
						WHERE (rrd_maximum = '0' OR rrd_minimum = '0')
						AND data_template_id = $data_template_id
						AND data_source_type_id IN(3,7)");
				}

				$hash_cache['data_template_item'][$parsed_hash['hash']] = $data_template_rrd_id;
			} else {
				$hash_cache['data_template_item'][$parsed_hash['hash']] = $_data_template_rrd_id;
			}
		}
	}

	// import into: data_input_data
	if (!$preview_only) {
		if (is_array($xml_array['data'])) {
			foreach ($xml_array['data'] as $item_array) {
				$data_hash = parse_xml_hash($item_array['data_input_field_id']);

				// Skip bad SNMP port hashes
				if (array_search($data_hash['hash'], $ignorable_hashes, true) !== false) {
					continue;
				}

				$save = [];

				$save['data_template_data_id'] = $data_template_data_id;
				$save['data_input_field_id']   = resolve_hash_to_id($item_array['data_input_field_id'], $hash_cache, 'data_input_data');
				$save['data_template_id']      = $data_template_id;
				$save['local_data_id']         = 0;
				$save['host_id']               = 0;

				/**
				 * fix legacy broken input fields for type_code (index_type, index_value, output_type) which
				 * should always be checked
				 */
				$type_code = db_fetch_cell_prepared('SELECT type_code
					FROM data_input_fields
					WHERE id = ?',
					[$save['data_input_field_id']]);

				if ($type_code == 'index_type' || $type_code == 'index_value' || $type_code == 'output_type') {
					$save['t_value'] = 'on';
				} else {
					$save['t_value'] = $item_array['t_value'];
				}

				$save['value'] = xml_character_decode($item_array['value']);

				if (!empty($save['data_input_field_id'])) {
					sql_save($save, 'data_input_data', ['data_template_data_id', 'data_input_field_id'], false);
				} else {
					cacti_log('Import Error: Failed to insert into data_input_data table', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				}
			}
		}

		// push out field mappings for the data collector
		db_execute_prepared('REPLACE INTO poller_data_template_field_mappings
			SELECT dtr.data_template_id, dif.data_name,
			GROUP_CONCAT(DISTINCT dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names,
			NOW() AS last_updated
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			INNER JOIN data_input_fields AS dif
			ON dtr.data_input_field_id = dif.id
			WHERE dtr.local_data_id = 0
			AND gti.local_graph_id = 0
			AND dtr.data_template_id = ?
			GROUP BY dtr.data_template_id, dif.data_name',
			[$data_template_id]);
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_data_template_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($data_template_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_data_query(string $hash, array &$xml_array, array &$hash_cache, array &$files, bool $replace_svalues = false) : mixed {
	global $fields_data_query_edit, $fields_data_query_item_edit, $preview_only, $import_debug_info;

	// track changes
	$status = 0;

	// import into: snmp_query
	$_data_query_id = db_fetch_cell_prepared('SELECT id
		FROM snmp_query
		WHERE hash = ?',
		[$hash]);

	if (!empty($_data_query_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM snmp_query
			WHERE id = ?',
			[$_data_query_id]);
	} else {
		$previous_data = [];
	}

	$save['id']     = (empty($_data_query_id) ? '0' : $_data_query_id);
	$save['hash']   = $hash;

	foreach ($fields_data_query_edit as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			// is the value of this field a hash or not?
			if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $xml_array[$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array[$field_name], $hash_cache, 'snmp_query');
			} else {
				$save[$field_name] = xml_character_decode($xml_array[$field_name]);
			}
		}
	}

	if (isset($save['xml_path'])) {
		$path = str_replace('<path_cacti>', CACTI_PATH_BASE, $save['xml_path']);

		if (!file_exists($path)) {
			$files[$path] = 'missing';
		} elseif (!is_readable($path)) {
			$files[$path] = 'notreadable';
		} else {
			$files[$path] = 'found';
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'snmp_query');

	if (!$preview_only) {
		$data_query_id = sql_save($save, 'snmp_query');

		$hash_cache['data_query'][$hash] = $data_query_id;

		update_replication_crc(0, 'poller_replicate_snmp_query_crc');
	} else {
		$data_query_id = $_data_query_id;

		$hash_cache['data_query'][$hash] = $_data_query_id;
	}

	// import into: snmp_query_graph
	if (is_array($xml_array['graphs'])) {
		foreach ($xml_array['graphs'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_data_query_graph_id = db_fetch_cell_prepared('SELECT id
				FROM snmp_query_graph
				WHERE hash = ?
				AND snmp_query_id = ?',
				[$parsed_hash['hash'], $data_query_id]);

			if (!empty($_data_query_graph_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM snmp_query_graph
					WHERE id = ?',
					[$_data_query_graph_id]);
			} else {
				$previous_data = [];
			}

			$save['id']            = (empty($_data_query_graph_id) ? '0' : $_data_query_graph_id);
			$save['hash']          = $parsed_hash['hash'];
			$save['snmp_query_id'] = $data_query_id;

			foreach ($fields_data_query_item_edit as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					// is the value of this field a hash or not?
					if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache, 'snmp_query_graph');
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'snmp_query_graph');

			if (!$preview_only) {
				$data_query_graph_id = sql_save($save, 'snmp_query_graph');

				$hash_cache['data_query_graph'][$parsed_hash['hash']] = $data_query_graph_id;

				// import into: snmp_query_graph_rrd
				if (is_array($item_array['rrd'])) {
					foreach ($item_array['rrd'] as $sub_item_array) {
						$save = [];

						$save['snmp_query_graph_id']  = $data_query_graph_id;
						$save['data_template_id']     = resolve_hash_to_id($sub_item_array['data_template_id'], $hash_cache, 'snmp_query_graph_rrd');
						$save['data_template_rrd_id'] = resolve_hash_to_id($sub_item_array['data_template_rrd_id'], $hash_cache, 'snmp_query_graph_rrd');
						$save['snmp_field_name']      = $sub_item_array['snmp_field_name'];

						if (!empty($save['data_template_id']) && !empty($save['data_template_rrd_id'])) {
							sql_save($save, 'snmp_query_graph_rrd', ['snmp_query_graph_id', 'data_template_id', 'data_template_rrd_id'], false);
						} else {
							cacti_log('Import Error: inserting into snmp_query_graph_rrd', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
						}
					}
				}
			} else {
				$data_query_graph_id = $_data_query_graph_id;

				$hash_cache['data_query_graph'][$parsed_hash['hash']] = $_data_query_graph_id;
			}

			// import into: snmp_query_graph_sv
			if (is_array($item_array['sv_graph'])) {
				// if the user choose to replace data query suggested values
				if ($data_query_graph_id > 0 && $replace_svalues && cacti_sizeof($item_array['sv_graph'])) {
					if (!$preview_only) {
						db_execute_prepared('DELETE FROM snmp_query_graph_sv
							WHERE snmp_query_graph_id = ?',
							[$data_query_graph_id]);
					}
				} elseif (!cacti_sizeof($item_array['sv_graph'])) {
					cacti_log('WARNING: Suggested Values Array for Graph Template Empty', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				}

				foreach ($item_array['sv_graph'] as $sub_item_hash => $sub_item_array) {
					// parse information from the hash
					$parsed_hash = parse_xml_hash($sub_item_hash);

					// invalid/wrong hash
					if ($parsed_hash == false) {
						return false;
					}

					$save = [];

					$_data_query_graph_sv_id = db_fetch_cell_prepared('SELECT id
						FROM snmp_query_graph_sv
						WHERE hash = ?
						AND snmp_query_graph_id = ?',
						[$parsed_hash['hash'], $data_query_graph_id]);

					if (!empty($_data_query_graph_sv_id)) {
						$previous_data = db_fetch_row_prepared('SELECT *
							FROM snmp_query_graph_sv
							WHERE id = ?',
							[$_data_query_graph_sv_id]);
					} else {
						$previous_data = [];
					}

					$save['id']                  = (empty($_data_query_graph_sv_id) ? '0' : $_data_query_graph_sv_id);
					$save['hash']                = $parsed_hash['hash'];
					$save['snmp_query_graph_id'] = $data_query_graph_id;
					$save['sequence']            = $sub_item_array['sequence'];
					$save['field_name']          = $sub_item_array['field_name'];
					$save['text']                = xml_character_decode($sub_item_array['text']);

					// check for status changes
					$status += compare_data($save, $previous_data, 'snmp_query_graph_sv');

					if (!$preview_only) {
						$data_query_graph_sv_id = sql_save($save, 'snmp_query_graph_sv');

						$hash_cache['data_query_sv_graph'][$parsed_hash['hash']] = $data_query_graph_sv_id;
					} else {
						$hash_cache['data_query_sv_graph'][$parsed_hash['hash']] = $_data_query_graph_sv_id;
					}
				}
			}

			// import into: snmp_query_graph_rrd_sv
			if (is_array($item_array['sv_data_source'])) {
				// if the user choose to replace data query suggested values
				if ($data_query_graph_id > 0 && $replace_svalues && cacti_sizeof($item_array['sv_data_source'])) {
					if (!$preview_only) {
						db_execute_prepared('DELETE FROM snmp_query_graph_rrd_sv
							WHERE snmp_query_graph_id = ?',
							[$data_query_graph_id]);
					}
				} elseif (!cacti_sizeof($item_array['sv_graph'])) {
					cacti_log('WARNING: Suggested Values Array for Data Template Empty', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
				}

				foreach ($item_array['sv_data_source'] as $sub_item_hash => $sub_item_array) {
					// parse information from the hash
					$parsed_hash = parse_xml_hash($sub_item_hash);

					// invalid/wrong hash
					if ($parsed_hash == false) {
						return false;
					}

					$save = [];

					$_data_query_graph_rrd_sv_id = db_fetch_cell_prepared('SELECT id
						FROM snmp_query_graph_rrd_sv
						WHERE hash = ?
						AND snmp_query_graph_id = ?',
						[$parsed_hash['hash'], $data_query_graph_id]);

					if (!empty($_data_query_graph_rrd_sv_id)) {
						$previous_data = db_fetch_row_prepared('SELECT *
							FROM snmp_query_graph_rrd_sv
							WHERE id = ?',
							[$_data_query_graph_rrd_sv_id]);
					} else {
						$previous_data = [];
					}

					$save['id']                  = (empty($_data_query_graph_rrd_sv_id) ? '0' : $_data_query_graph_rrd_sv_id);
					$save['hash']                = $parsed_hash['hash'];
					$save['snmp_query_graph_id'] = $data_query_graph_id;
					$save['data_template_id']    = resolve_hash_to_id($sub_item_array['data_template_id'], $hash_cache, 'snmp_query_graph_rrd_sv');
					$save['sequence']            = $sub_item_array['sequence'];
					$save['field_name']          = $sub_item_array['field_name'];
					$save['text']                = xml_character_decode($sub_item_array['text']);

					// check for status changes
					$status += compare_data($save, $previous_data, 'snmp_query_graph_rrd_sv');

					if (!$preview_only) {
						if (!empty($save['data_template_id'])) {
							$data_query_graph_rrd_sv_id = sql_save($save, 'snmp_query_graph_rrd_sv');

							$hash_cache['data_query_sv_data_source'][$parsed_hash['hash']] = $data_query_graph_rrd_sv_id;
						} else {
							cacti_log('Import Error: Error Importing into snmp_query_graph_rrd_sv table', false, 'IMPORT', POLLER_VERBOSITY_HIGH);
						}
					} else {
						$hash_cache['data_query_sv_data_source'][$parsed_hash['hash']] = $_data_query_graph_rrd_sv_id;
					}
				}
			}
		}
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_data_query_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($data_query_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_gprint_preset(string $hash, array &$xml_array, array &$hash_cache) : mixed {
	global $fields_grprint_presets_edit, $preview_only, $import_debug_info;

	// track changes
	$status = 0;

	// import into: graph_templates_gprint
	$_gprint_preset_id = db_fetch_cell_prepared('SELECT id
		FROM graph_templates_gprint
		WHERE hash = ?',
		[$hash]);

	if (!empty($_gprint_preset_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM graph_templates_gprint
			WHERE id = ?',
			[$_gprint_preset_id]);
	} else {
		$previous_data = [];
	}

	$save['id']   = (empty($_gprint_preset_id) ? '0' : $_gprint_preset_id);
	$save['hash'] = $hash;

	foreach ($fields_grprint_presets_edit as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'graph_templates_gprint');

	if (!$preview_only) {
		$gprint_preset_id = sql_save($save, 'graph_templates_gprint');

		$hash_cache['gprint_preset'][$hash] = $gprint_preset_id;
	} else {
		$hash_cache['gprint_preset'][$hash] = $_gprint_preset_id;
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_gprint_preset_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($gprint_preset_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_data_source_profile(string $hash, array &$xml_array, array &$hash_cache, bool $import_as_new, int $profile_id) : mixed {
	global $fields_profile_edit, $fields_profile_rra_edit, $import_template_id, $preview_only, $import_debug_info;

	if ($import_as_new == true) {
		$save['id']   = 0;
		$save['hash'] = get_hash_data_source_profile(0);

		foreach ($fields_profile_edit as $field_name => $field_array) {
			// make sure this field exists in the xml array first
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
					[$dsp_id, $import_template_id]);
			}

			$hash_cache['data_source_profiles'][$hash] = $dsp_id;

			// import into: data_source_profiles_cf
			$hash_items = explode('|', $xml_array['cf_items']);

			if (!empty($hash_items[0])) {
				for ($i = 0; $i < cacti_count($hash_items); $i++) {
					db_execute_prepared('REPLACE INTO data_source_profiles_cf
						(data_source_profile_id, consolidation_function_id)
						VALUES (?, ?)',
						[$dsp_id, $hash_items[$i]]);
				}
			}

			// import into: data_source_profiles_rra
			if (is_array($xml_array['items'])) {
				foreach ($xml_array['items'] as $item_name => $item_array) {
					$save       = [];
					$save['id'] = 0;

					$save['data_source_profile_id'] = $dsp_id;

					foreach ($fields_profile_rra_edit as $field_name => $field_array) {
						// make sure this field exists in the xml array first
						if (isset($item_array[$field_name])) {
							$save[$field_name] = xml_character_decode($item_array[$field_name]);
						}
					}

					$rra_id = sql_save($save, 'data_source_profiles_rra');

					$hash_cache['data_source_profile_rra'][$item_name] = $rra_id;
				}
			}
		}

		// status information that will be presented to the user
		$import_debug_info['type']   = 'new';
		$import_debug_info['hash']   = $save['hash'];
		$import_debug_info['title']  = $xml_array['name'] . ' (imported)';
		$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($dsp_id) ? 'fail' : 'success'));

		return $hash_cache;
	} else {
		return false;
	}
}

function xml_to_host_template(string $hash, array &$xml_array, array &$hash_cache, array &$host_template_data) : mixed {
	global $fields_host_template_edit, $preview_only, $import_debug_info;

	// track changes
	$status = 0;

	// import into: graph_templates_gprint
	$_host_template_id = db_fetch_cell_prepared('SELECT id
		FROM host_template
		WHERE hash = ?',
		[$hash]);

	if (!empty($_host_template_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM host_template
			WHERE id = ?',
			[$_host_template_id]);
	} else {
		$previous_data = [];
	}

	$save['id']   = (empty($_host_template_id) ? '0' : $_host_template_id);
	$save['hash'] = $hash;

	foreach ($fields_host_template_edit as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'host_template');

	if (cacti_sizeof($host_template_data)) {
		foreach ($host_template_data as $type => $data) {
			foreach ($data as $dhash => $dname) {
				if ($type == 'graph_template') {
					$exists1 = db_fetch_cell_prepared('SELECT id
						FROM graph_templates
						WHERE hash = ?',
						[$dhash]);

					if (!$exists1) {
						$import_debug_info['differences'][] = __('New Graph Template: %s', htmle($dname));
						$status++;
					} elseif ($save['id']) {
						// For existing, we have to perform two checks
						$exists2 = db_fetch_cell_prepared('SELECT COUNT(*)
							FROM host_template_graph
							WHERE host_template_id = ?
							AND graph_template_id = ?',
							[$save['id'], $exists1]);

						if (!$exists2) {
							$data_query_id = db_fetch_cell_prepared('SELECT snmp_query_id
								FROM snmp_query_graph
								WHERE graph_template_id = ?',
								[$exists1]);

							if (!$data_query_id) {
								$import_debug_info['differences'][] = __('New Graph Template: %s', htmle($dname));
								$status++;
							}
						}
					}
				} elseif ($type == 'data_query') {
					$exists = db_fetch_cell_prepared('SELECT id
						FROM snmp_query
						WHERE hash = ?',
						[$dhash]);

					if (!$exists) {
						$import_debug_info['differences'][] = __('New Data Query: %s', htmle($dname));
						$status++;
					} elseif ($save['id']) {
						$exists = db_fetch_cell_prepared('SELECT COUNT(*)
							FROM host_template_snmp_query
							WHERE host_template_id = ?
							AND snmp_query_id = ?',
							[$save['id'], $exists]);

						if (!$exists) {
							$import_debug_info['differences'][] = __('New Data Query: %s', htmle($dname));
							$status++;
						}
					}
				}
			}
		}
	}

	if (!$preview_only) {
		$host_template_id = sql_save($save, 'host_template');

		$hash_cache['host_template'][$hash] = $host_template_id;

		// import into: host_template_graph
		$hash_items = explode('|', $xml_array['graph_templates']);

		if (!empty($hash_items[0])) {
			for ($i = 0; $i < cacti_count($hash_items); $i++) {
				// parse information from the hash
				$parsed_hash = parse_xml_hash($hash_items[$i]);

				// invalid/wrong hash
				if ($parsed_hash == false) {
					return false;
				}

				if (isset($hash_cache['graph_template'][$parsed_hash['hash']])) {
					db_execute_prepared('REPLACE INTO host_template_graph
						(host_template_id, graph_template_id)
						VALUES (?, ?)',
						[$host_template_id, $hash_cache['graph_template'][$parsed_hash['hash']]]);
				}
			}
		}

		// import into: host_template_snmp_query
		$hash_items = explode('|', $xml_array['data_queries']);

		if (!empty($hash_items[0])) {
			for ($i = 0; $i < cacti_count($hash_items); $i++) {
				// parse information from the hash
				$parsed_hash = parse_xml_hash($hash_items[$i]);

				// invalid/wrong hash
				if ($parsed_hash == false) {
					return false;
				}

				if (isset($hash_cache['data_query'][$parsed_hash['hash']])) {
					db_execute_prepared('REPLACE INTO host_template_snmp_query
						(host_template_id, snmp_query_id)
						VALUES (?, ?)',
						[$host_template_id, $hash_cache['data_query'][$parsed_hash['hash']]]);
				}
			}
		}
	} else {
		$hash_cache['host_template'][$hash] = $_host_template_id;
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_host_template_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($host_template_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_to_cdef(string $hash, array &$xml_array, array &$hash_cache) : mixed {
	global $fields_cdef_edit, $preview_only, $import_debug_info;

	// track changes
	$status = 0;

	$fields_cdef_item_edit = [
		'sequence' => 'sequence',
		'type'     => 'type',
		'value'    => 'value'
	];

	// import into: cdef
	$_cdef_id = db_fetch_cell_prepared('SELECT id
		FROM cdef
		WHERE hash = ?',
		[$hash]);

	if (!empty($_cdef_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM cdef
			WHERE id = ?',
			[$_cdef_id]);
	} else {
		$previous_data = [];
	}

	$save['id']   = (empty($_cdef_id) ? '0' : $_cdef_id);
	$save['hash'] = $hash;

	foreach ($fields_cdef_edit as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'cdef');

	if (!$preview_only) {
		$cdef_id = sql_save($save, 'cdef');

		$hash_cache['cdef'][$hash] = $cdef_id;
	} else {
		$cdef_id = $_cdef_id;

		$hash_cache['cdef'][$hash] = $_cdef_id;
	}

	$damaged_items = false;

	// import into: cdef_items
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			$damaged_item = false;

			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_cdef_item_id = db_fetch_cell_prepared('SELECT id
				FROM cdef_items
				WHERE hash= ?
				AND cdef_id = ?',
				[$parsed_hash['hash'], $cdef_id]);

			if (!empty($_cdef_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM cdef_items
					WHERE id = ?',
					[$_cdef_item_id]);
			} else {
				$previous_data = [];
			}

			$save['id']      = (empty($_cdef_item_id) ? '0' : $_cdef_item_id);
			$save['hash']    = $parsed_hash['hash'];
			$save['cdef_id'] = $cdef_id;

			foreach ($fields_cdef_item_edit as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					/* check, if an inherited cdef as to be decoded (value == 5)
					 * this whole procedure relies on the sequence during template export
					 * inherited cdef's must come first, this has to be taken care of during export
					 * so we do not have any specific dependency checks here */
					if (($field_name == 'value') && ($item_array['type'] == '5')) {
						/* parse information from the hash, which in this case
						 * is stored as a value of the current item being processed */
						$parsed_item_hash = parse_xml_hash($item_array['value']);

						// invalid/wrong hash
						if ($parsed_item_hash == false) {
							$damaged_item  = true;
							$damaged_items = true;
						}

						if (!$damaged_item) {
							$_cdef_id = db_fetch_cell_prepared('SELECT id
								FROM cdef
								WHERE hash = ?',
								[$parsed_item_hash['hash']]);

							$save[$field_name] = $_cdef_id;
						}
					} else {
						$save[$field_name] = xml_character_decode($item_array[$field_name]);
					}
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'cdef_items');

			if (!$preview_only) {
				if (!$damaged_item) {
					$cdef_item_id = sql_save($save, 'cdef_items');

					$hash_cache['cdef_item'][$parsed_hash['hash']] = $cdef_item_id;
				}
			} else {
				$hash_cache['cdef_item'][$parsed_hash['hash']] = $_cdef_item_id;
			}
		}
	}

	// status information that will be presented to the user
	if (!$damaged_items) {
		$import_debug_info['type']   = (empty($_cdef_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
		$import_debug_info['hash']   = $hash;
		$import_debug_info['title']  = $xml_array['name'];
		$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($cdef_id) ? 'fail' : 'success'));
	} else {
		$import_debug_info['type']   = 'damaged';
		$import_debug_info['hash']   = $hash;
		$import_debug_info['title']  = $xml_array['name'];
		$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($cdef_id) ? 'fail' : 'success'));
	}

	return $hash_cache;
}

function xml_to_vdef(string $hash, array &$xml_array, array &$hash_cache) : mixed {
	global $preview_only, $import_debug_info;

	include_once(CACTI_PATH_LIBRARY . '/vdef.php');

	// track changes
	$status = 0;

	// import into: vdef
	$_vdef_id = db_fetch_cell_prepared('SELECT id
		FROM vdef
		WHERE hash = ?',
		[$hash]);

	if (!empty($_vdef_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM vdef
			WHERE id = ?',
			[$_vdef_id]);
	} else {
		$previous_data = [];
	}

	$save['id']   = (empty($_vdef_id) ? '0' : $_vdef_id);
	$save['hash'] = $hash;

	$fields_vdef_edit = preset_vdef_form_list();

	foreach ($fields_vdef_edit as $field_name => $field_array) {
		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = xml_character_decode($xml_array[$field_name]);
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'vdef');

	if (!$preview_only) {
		$vdef_id = sql_save($save, 'vdef');

		$hash_cache['vdef'][$hash] = $vdef_id;
	} else {
		$vdef_id = $_vdef_id;

		$hash_cache['vdef'][$hash] = $_vdef_id;
	}

	// import into: vdef_items
	if (is_array($xml_array['items'])) {
		foreach ($xml_array['items'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			$save = [];

			$_vdef_item_id = db_fetch_cell_prepared('SELECT id
				FROM vdef_items
				WHERE hash = ?
				AND vdef_id = ?',
				[$parsed_hash['hash'], $vdef_id]);

			if (!empty($_vdef_item_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM vdef_items
					WHERE id = ?',
					[$_vdef_item_id]);
			} else {
				$previous_data = [];
			}

			$save['id']      = (empty($_vdef_item_id) ? '0' : $_vdef_item_id);
			$save['hash']    = $parsed_hash['hash'];
			$save['vdef_id'] = $vdef_id;

			$fields_vdef_item_edit = preset_vdef_item_form_list();

			foreach ($fields_vdef_item_edit as $field_name => $field_array) {
				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'vdef_items');

			if (!$preview_only) {
				$vdef_item_id = sql_save($save, 'vdef_items');

				$hash_cache['vdef_item'][$parsed_hash['hash']] = $vdef_item_id;
			} else {
				$hash_cache['vdef_item'][$parsed_hash['hash']] = $_vdef_item_id;
			}
		}
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_vdef_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($vdef_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function xml_detect_ignorable_hash_cache(string $hash, array &$xml_array) : bool {
	global $ignorable_hashes;

	$found = false;

	$system_hashes = [
		'3eb92bb845b9660a7445cf9740726522', // Get SNMP Data
		'bf566c869ac6443b0c75d1c32b5a350e', // Get SNMP Data (Indexed)
		'80e9e4c4191a5da189ae26d0e237f015', // Get Script Data (Indexed)
		'332111d8b54ac8ce939af87a7eac0c06', // Get Script Server Data (Indexed)
	];

	$valid_snmp_port_hashes = [
		'c1f36ee60c3dc98945556d57f26e475b',
		'fc64b99742ec417cc424dbf8c7692d36'
	];

	// Leave system data input methods alone
	if (array_search($hash, $system_hashes, true) !== false) {
		foreach ($xml_array['fields'] as $input_hash => $field) {
			if ($field['type_code'] == 'snmp_port') {
				$parsed_hash = parse_xml_hash($input_hash);
				$hash        = $parsed_hash['hash'];

				if (array_search($hash, $valid_snmp_port_hashes, true) === false) {
					$ignorable_hashes[$input_hash] = $hash;

					$found = true;
				}
			}
		}
	}

	return $found;
}

function xml_to_data_input_method(string $hash, array &$xml_array, array &$hash_cache) : mixed {
	global $fields_data_input_edit, $fields_data_input_field_edit, $fields_data_input_field_edit_1;
	global $preview_only, $import_debug_info, $ignorable_hashes;

	// track changes
	$status = 0;

	// aggregate field arrays
	$fields_data_input_field_edit += $fields_data_input_field_edit_1;

	// import into: data_input
	$_data_input_id = db_fetch_cell_prepared('SELECT id
		FROM data_input
		WHERE hash = ?',
		[$hash]);

	if (!empty($_data_input_id)) {
		$previous_data = db_fetch_row_prepared('SELECT *
			FROM data_input
			WHERE id = ?',
			[$_data_input_id]);
	} else {
		$previous_data = [];
	}

	$save = [];

	$save['id']   = (empty($_data_input_id) ? '0' : $_data_input_id);
	$save['hash'] = $hash;

	foreach ($fields_data_input_edit as $field_name => $field_array) {
		// Work around for templates exported during
		// period where there was a bug in 1.2.13-1.2.15
		if ($field_name == 'fname') {
			$field_name = 'name';
		}

		// make sure this field exists in the xml array first
		if (isset($xml_array[$field_name])) {
			if ($field_name == 'input_string' && import_is_base64_encoded($xml_array[$field_name])) {
				$save[$field_name] = base64_decode($xml_array[$field_name], true);
			} else {
				$save[$field_name] = xml_character_decode($xml_array[$field_name]);

				// fix issue with data input method importing and white spaces
				if ($field_name == 'input_string') {
					$save[$field_name] = str_replace('><', '> <', $save[$field_name]);
					$save[$field_name] = str_replace('>""<', '>" "<', $save[$field_name]);
					$save[$field_name] = str_replace('>\'\'<', '>\' \'<', $save[$field_name]);
				}
			}
		}
	}

	// check for status changes
	$status += compare_data($save, $previous_data, 'data_input');

	if (!$preview_only) {
		$data_input_id = sql_save($save, 'data_input');

		$hash_cache['data_input_method'][$hash] = $data_input_id;

		update_replication_crc(0, 'poller_replicate_data_input_crc');
	} else {
		$data_input_id = $_data_input_id;

		$hash_cache['data_input_method'][$hash] = $_data_input_id;
	}

	// import into: data_input_fields
	if (is_array($xml_array['fields'])) {
		foreach ($xml_array['fields'] as $item_hash => $item_array) {
			// parse information from the hash
			$parsed_hash = parse_xml_hash($item_hash);

			// invalid/wrong hash
			if ($parsed_hash == false) {
				return false;
			}

			// bad snmp port hashes
			if ($parsed_hash['hash'] == '5240353b8f7f259acaf30e6229bc14e7') {
				continue;
			}

			if ($parsed_hash['hash'] == 'd94caa7cc3733bd95ee00a3917fdcbb5') {
				continue;
			}

			$save = [];

			$_data_input_field_id = db_fetch_cell_prepared('SELECT id
				FROM data_input_fields
				WHERE hash= ?
				AND data_input_id = ?',
				[$parsed_hash['hash'], $data_input_id]);

			if (!empty($_data_input_field_id)) {
				$previous_data = db_fetch_row_prepared('SELECT *
					FROM data_input_fields
					WHERE id = ?',
					[$_data_input_field_id]);
			} else {
				$previous_data = [];
			}

			$save['id']            = (empty($_data_input_field_id) ? '0' : $_data_input_field_id);
			$save['hash']          = $parsed_hash['hash'];
			$save['data_input_id'] = $data_input_id;

			foreach ($fields_data_input_field_edit as $field_name => $field_array) {
				// Work around for templates exported during
				// period where there was a bug in 1.2.13-1.2.15
				if ($field_name == 'fname') {
					$field_name = 'name';
				}

				// make sure this field exists in the xml array first
				if (isset($item_array[$field_name])) {
					// Correct a nasty spelling error
					$item_array[$field_name] = str_replace('Authentication', 'Authentication', $item_array[$field_name]);

					$save[$field_name] = xml_character_decode($item_array[$field_name]);
				}
			}

			// check for status changes
			$status += compare_data($save, $previous_data, 'data_input_fields');

			if (!$preview_only) {
				$data_input_field_id = sql_save($save, 'data_input_fields');

				$hash_cache['data_input_field'][$parsed_hash['hash']] = $data_input_field_id;

				update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
			} else {
				$hash_cache['data_input_field'][$parsed_hash['hash']] = $_data_input_field_id;
			}
		}
	}

	// update field use counter cache if possible
	if (!$preview_only) {
		if ((isset($xml_array['input_string'])) && (!empty($data_input_id))) {
			generate_data_input_field_sequences($xml_array['input_string'], $data_input_id);
		}
	}

	// status information that will be presented to the user
	$import_debug_info['type']   = (empty($_data_input_id) ? 'new' : ($status > 0 ? 'updated' : 'unchanged'));
	$import_debug_info['hash']   = $hash;
	$import_debug_info['title']  = $xml_array['name'];
	$import_debug_info['result'] = ($preview_only ? 'preview' : (empty($data_input_id) ? 'fail' : 'success'));

	return $hash_cache;
}

function compare_data(array $save, array $previous_data, string $table) : int {
	global $preview_only, $import_debug_info;

	if ($preview_only) {
		$ignores = [
			'task_item_id',
			'gprint_id',
			'cdef_id',
			'vdef_id',
			'consolidation_function_id',
			'data_input_id',
			'graph_template_id',
			'data_template_id',
			'data_input_field_id'
		];
	} else {
		$ignores = [];
	}

	if (!cacti_sizeof($previous_data)) {
		return 0;
	} else {
		$different = 0;

		foreach ($save as $column => $value) {
			if (array_search($column, $ignores, true) !== false) {
				continue;
			}

			if ($previous_data[$column] != $value) {
				$cols = db_get_table_column_types($table);

				if (str_contains($cols[$column]['type'], 'int') ||
					str_contains($cols[$column]['type'], 'float') ||
					str_contains($cols[$column]['type'], 'decimal') ||
					str_contains($cols[$column]['type'], 'double')) {
					if (empty($previous_data[$column]) && empty($value)) {
						continue;
					}

					if ($table == 'data_template_rrd' && $column == 'rrd_heartbeat') {
						continue;
					}

					if ($table == 'data_template_data' && $column == 'rrd_step') {
						continue;
					}

					if ($table == 'graph_templates_graph' && $column == 'image_format_id') {
						continue;
					}

					if ($table == 'graph_templates_graph' && $column == 'graph_width') {
						continue;
					}

					if ($table == 'graph_templates_graph' && $column == 'graph_height') {
						continue;
					}
				} elseif (empty($previous_data[$column]) && empty($value)) {
					continue;
				}

				if ($column == 'color_id') {
					$oldvalue = db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', [$previous_data[$column]]);
					$oldvalue = htmle($oldvalue);
					$oldvalue = '<span style="background-color:#' . $oldvalue . '">' . $oldvalue . '</span>';

					$newvalue = db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', [$value]);

					if (empty($newvalue) && $preview_only) {
						$newvalue = htmle($value);
					} else {
						$newvalue = htmle($newvalue);
					}

					$newvalue = '<span style="background-color:#' . $newvalue . '">' . $newvalue . '</span>';
				} elseif ($column == 'color2_id') {
					$oldvalue = db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', [$previous_data[$column]]);
					$oldvalue = htmle($oldvalue);
					$oldvalue = '<span style="background-color:#' . $oldvalue . '">' . $oldvalue . '</span>';

					$newvalue = db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', [$value]);

					if (empty($newvalue) && $preview_only) {
						$newvalue = htmle($value);
					} else {
						$newvalue = htmle($newvalue);
					}

					$newvalue = '<span style="background-color:#' . $newvalue . '">' . $newvalue . '</span>';
				} else {
					$oldvalue = htmle($previous_data[$column]);
					$newvalue = htmle($value);
				}

				$different++;

				$import_debug_info['differences'][] = 'Table: ' . $table . ', Column: ' . $column . ', New Value: ' . $newvalue . ', Old Value: ' . $oldvalue;
			}
		}

		return $different;
	}
}

function hash_to_friendly_name(string $hash, bool $display_type_name) : string {
	global $hash_type_names;

	// parse information from the hash
	$parsed_hash = parse_xml_hash($hash);

	// invalid/wrong hash
	if ($parsed_hash == false) {
		return __('Unknown Field');
	}

	if ($display_type_name == true) {
		$prepend = '(<em>' . $hash_type_names[$parsed_hash['type']] . '</em>) ';
	} else {
		$prepend = '';
	}

	switch ($parsed_hash['type']) {
		case 'graph_template':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM graph_templates
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_template':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM data_template
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_template_item':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT data_source_name
				FROM data_template_rrd
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'host_template':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM host_template
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_input_method':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM data_input
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_input_field':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM data_input_fields
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_query':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM snmp_query
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'gprint_preset':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM graph_templates_gprint
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'cdef':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM cdef
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'vdef':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM vdef
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'data_source_profile':
			return $prepend . htmle(db_fetch_cell_prepared('SELECT name
				FROM data_source_profile
				WHERE hash = ?',
				[$parsed_hash['hash']]));
		case 'round_robin_archive':
			return $prepend;
		default:
			$param            = [];
			$param['hash']    = $parsed_hash['hash'];
			$param['type']    = $parsed_hash['type'];
			$param['prepend'] = $prepend;

			api_plugin_hook_function('get_friendly_name', $param);

			return htmle($param['prepend']);
	}
}

function resolve_hash_to_id(string $hash, array &$hash_cache_array, string $table) : mixed {
	global $import_debug_info;

	// parse information from the hash
	$parsed_hash = parse_xml_hash($hash);

	// invalid/wrong hash
	if ($parsed_hash == false) {
		return false;
	}

	if (isset($hash_cache_array[$parsed_hash['type']][$parsed_hash['hash']])) {
		$import_debug_info['dep'][$hash] = 'met';

		return $hash_cache_array[$parsed_hash['type']][$parsed_hash['hash']];
	} else {
		// bad snmp port hashes
		if ($parsed_hash['hash'] == '5240353b8f7f259acaf30e6229bc14e7') {
			return 0;
		}

		if ($parsed_hash['hash'] == 'd94caa7cc3733bd95ee00a3917fdcbb5') {
			return 0;
		}

		if ($parsed_hash['hash'] == 'cbbe5c1ddfb264a6e5d509ce1c78c95f') {
			return 0;
		}

		if ($parsed_hash['hash'] == '51bde3d899e12bde28ad979166985584') {
			return 0;
		}

		cacti_log('Import Error: Import found an invalid dependency hash of :' . $parsed_hash['hash'] . " for Table $table.  Please open bug on GitHub.");

		$import_debug_info['dep'][$hash] = 'unmet';

		return 0;
	}
}

function parse_xml_hash(string $hash) : mixed {
	global $legacy_template, $import_messages;

	$parsed_hash = [];

	if (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/', $hash, $matches)) {
		$parsed_hash['type']    = check_hash_type($matches[1]);
		$parsed_hash['version'] = strval(check_hash_version($matches[2]));
		$parsed_hash['hash']    = $matches[3];

		// an error has occurred
		if (($parsed_hash['type'] === false) || ($parsed_hash['version'] === false)) {
			$import_messages[] = 7; // xml parse error

			cacti_log(__FUNCTION__ . ' ERROR type or version not found for hash: ' . $hash, false, 'IMPORT', POLLER_VERBOSITY_LOW);

			return false;
		}
	} elseif (preg_match('/hash_([a-f0-9]{2})([a-f0-9]{32})/', $hash, $matches)) {
		$parsed_hash['type']    = check_hash_type($matches[1]);
		$parsed_hash['version'] = strval(check_hash_version('0101'));
		$parsed_hash['hash']    = $matches[2];

		// an error has occurred
		if (($parsed_hash['type'] === false) || ($parsed_hash['version'] === false)) {
			$import_messages[] = 7; // xml parse error

			cacti_log(__FUNCTION__ . ' ERROR type or version not found for hash: ' . $hash, false, 'IMPORT', POLLER_VERBOSITY_LOW);

			return false;
		}
	} else {
		// bad snmp port hashes
		if ($hash == '5240353b8f7f259acaf30e6229bc14e7') {
			return false;
		}

		if ($hash == 'd94caa7cc3733bd95ee00a3917fdcbb5') {
			return false;
		}

		$import_messages[] = 7; // xml parse error

		cacti_log(__FUNCTION__ . ' ERROR wrong hash format for hash: ' . $hash, false, 'IMPORT', POLLER_VERBOSITY_LOW);

		return false;
	}

	if (cacti_version_compare($parsed_hash['version'], '1.2.3', '<')) {
		$legacy_template = true;
	}

	return $parsed_hash;
}

function check_hash_type(string $hash_type) : mixed {
	global $hash_type_codes, $import_messages;

	// lets not mess up the pointer for other people
	$local_hash_type_codes = $hash_type_codes;

	foreach ($local_hash_type_codes as $type => $code) {
		if ($code == $hash_type) {
			$current_type = $type;
		}
	}

	if (!isset($current_type)) {
		$import_messages[] = 18; // xml parse error

		return false;
	}

	return $current_type;
}

function check_hash_version(string $hash_version) : mixed {
	global $cacti_version_codes, $import_messages;

	$current_version = '';

	foreach ($cacti_version_codes as $version => $code) {
		if ($version == CACTI_VERSION) {
			$current_version_code = $code;
		}

		if ($code == $hash_version) {
			$hash_version_code = $code;
			$current_version   = $version;
		}
	}

	if (!isset($current_version_code)) {
		cacti_log("ERROR: $hash_version Current Cacti Version does not exist!", false, 'IMPORT');
		$import_messages[] = 15; // xml parse error

		return false;
	}

	if (!isset($hash_version_code)) {
		cacti_log("ERROR: $hash_version hash version does not exist!", false, 'IMPORT');
		$import_messages[] = 16; // xml parse error

		return false;
	}

	if ($hash_version_code > $current_version_code) {
		cacti_log("ERROR: $hash_version_code > $current_version_code", false, 'IMPORT');
		cacti_log("ERROR: $hash_version hash version is for a newer Cacti!", false, 'IMPORT');
		$import_messages[] = 17; // xml parse error

		return false;
	}

	return $current_version;
}

function get_version_index(string $string_version) : mixed {
	global $cacti_version_codes;

	$i = 0;

	foreach ($cacti_version_codes as $version => $code) {
		if ($string_version == $version) {
			return $i;
		}

		$i++;
	}

	// version index not found
	return -1;
}

function xml_character_decode(string $text) : string {
	if (function_exists('html_entity_decode')) {
		return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	} else {
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);

		return strtr($text, $trans_tbl);
	}
}

function import_display_results(array $import_debug_info, array $filestatus, bool $web = false, bool $preview = false) : void {
	global $hash_type_names, $ignorable_hashes;

	if (!cacti_sizeof($ignorable_hashes)) {
		$ignorable_hashes = [];
	}

	// Capture to a buffer
	ob_start();

	if (cacti_sizeof($import_debug_info)) {
		html_start_box(($preview ? __('Import Preview Results') : __('Import Results')), '100%', false, 3, 'center', '');

		if (cacti_sizeof($filestatus)) {
			if ($preview) {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti would make the following changes if the Package was imported:') . '</p>' . PHP_EOL;
			} else {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti has imported the following items for the Package:') . '</p>' . PHP_EOL;
			}

			print '<p><strong>' . __('Package Files') . '</strong></p>' . PHP_EOL;

			print '<ul>' . PHP_EOL;

			foreach ($filestatus as $filename => $status) {
				print '<li>' . ($preview ? __('[preview] ') : '') . htmle($filename) . ' [' . $status . ']</li>' . PHP_EOL;
			}

			print '</ul>' . PHP_EOL;
		} else {
			if ($preview) {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti would make the following changes if the Template was imported:') . '</p>' . PHP_EOL;
			} else {
				print "<tr class='odd'><td><p class='textArea'>" . __('Cacti has imported the following items for the Template:') . '</p>' . PHP_EOL;
			}
		}

		foreach ($import_debug_info as $type => $type_array) {
			if ($type == 'files') {
				continue;
			}

			$type_name = !empty($hash_type_names[$type]) ? $hash_type_names[$type] : "Unknown type: $type";

			print PHP_EOL . '<p><strong>' . $type_name . '</strong></p>' . PHP_EOL;

			foreach ($type_array as $vals) {
				if ($vals['result'] == 'success') {
					$result_text = "<span class='success'>" . __('[success]') . '</span>';
				} elseif ($vals['result'] == 'fail') {
					$result_text = "<span class='failed'>" . __('[fail]') . '</span>';
				} else {
					$result_text = "<span class='success'>" . __('[preview]') . '</span>';
				}

				if ($vals['type'] == 'updated') {
					$type_text = "<span class='updateObject'>" . __('[updated]') . '</span>' . PHP_EOL;
				} elseif ($vals['type'] == 'new') {
					$type_text = "<span class='newObject'>" . __('[new]') . '</span>' . PHP_EOL;
				} else {
					$type_text = "<span class='deviceUp'>" . __('[unchanged]') . '</span>' . PHP_EOL;
				}

				print "<span class='monoSpace'>$result_text " . htmle($vals['title']) . " $type_text</span><br>" . PHP_EOL;

				if (isset($vals['orphans'])) {
					print '<ul class="monoSpace">' . PHP_EOL;

					foreach ($vals['orphans'] as $orphan) {
						print '<li>' . htmle($orphan) . '</li>' . PHP_EOL;
					}

					print '</ul>' . PHP_EOL;
				}

				if (isset($vals['new_items'])) {
					print '<ul class="monoSpace">' . PHP_EOL;

					foreach ($vals['new_items'] as $item) {
						print '<li>' . htmle($item) . '</li>' . PHP_EOL;
					}

					print '</ul>' . PHP_EOL;
				}

				if (isset($vals['differences'])) {
					print '<ul class="monoSpace">' . PHP_EOL;

					foreach ($vals['differences'] as $diff) {
						print '<li>' . $diff . '</li>' . PHP_EOL;
					}

					print '</ul>' . PHP_EOL;
				}

				if (!$preview) {
					$dep_text        = '';
					$dep_errors      = false;
					$dep_status_text = '';

					if ((isset($vals['dep'])) && (cacti_sizeof($vals['dep']) > 0)) {
						foreach ($vals['dep'] as $dep_hash => $dep_status) {
							if ($dep_status == 'met') {
								$dep_status_text = "<span class='foundDependency'>" . __('Found Dependency:') . '</span>' . PHP_EOL;
							} elseif (array_search($dep_hash, $ignorable_hashes, true) === false) {
								$dep_status_text = "<span class='unmetDependency'>" . __('Unmet Dependency:') . '</span>' . PHP_EOL;
								$dep_errors      = true;
							}

							$dep_text .= "<span class='monoSpace'>&nbsp;&nbsp;&nbsp;+ $dep_status_text " . hash_to_friendly_name($dep_hash, true) . '</span><br>' . PHP_EOL;
						}
					}

					// only print out dependency details if they contain errors; otherwise it would get too long
					if ($dep_errors == true) {
						print $dep_text;
					}
				}
			}
		}

		print '</td></tr>' . PHP_EOL;

		html_end_box();
	}

	if ($web) {
		print ob_get_clean();
	} else {
		$output = ob_get_clean();
		$output = explode("\n", $output);

		if (cacti_sizeof($output)) {
			foreach ($output as $line) {
				$line = trim(str_replace('&nbsp;', '', strip_tags($line)));

				if ($line != '') {
					print $line . PHP_EOL;
				}
			}
		}
	}
}

function xml_to_array(mixed $data) : mixed {
	if (is_object($data)) {
		$data = get_object_vars($data);
	}

	return (is_array($data)) ? array_map(__FUNCTION__, $data) : $data;
}

function import_is_base64_encoded(string $string) : bool {
	if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $string)) {
		return true;
	} else {
		return false;
	}
}
