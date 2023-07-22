<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/import.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');
include_once('./lib/xml.php');
include_once('./include/vendor/phpdiff/Diff.php');
include_once('./include/vendor/phpdiff/Renderer/Html/Inline.php');

/* set default action */
set_default_action();

check_tmp_dir();

switch(get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'details':
		package_get_details();

		break;
	case 'diff':
		package_diff_file();

		break;
	default:
		top_header();
		package_import();
		bottom_footer();

		break;
}

function check_tmp_dir() {
	if (is_tmp_writable()) {
		return true;
	} else {
		?>
		<script type='text/javascript'>
		var mixedReasonTitle = '<?php print __('Key Generation Required to Use Plugin');?>';
		var mixedOnPage      = '<?php print __esc('Package Key Information Not Found');?>';
		sessionMessage   = {
			message: '<?php print __('In order to use this Plugin, you must first run the <b><i class="deviceUp">genkey.php</i></b> script in the plugin directory.  Once that is complete, you will have a public and private key used to sign your packages.');?>',
			level: MESSAGE_LEVEL_MIXED
		};

		$(function() {
			displayMessages();
		});
		</script>
		<?php

		exit;
	}
}

function form_save() {
	global $config, $preview_only;

	validate_request_vars();

	if (isset_request_var('save_component_import')) {
		if (isset($_FILES['import_file']['tmp_name']) &&
			($_FILES['import_file']['tmp_name'] != 'none') &&
			($_FILES['import_file']['tmp_name'] != '')) {
			/* file upload */
			$xmlfile = $_FILES['import_file']['tmp_name'];

			$_SESSION['sess_import_package'] = file_get_contents($xmlfile);
		} elseif (isset($_SESSION['sess_import_package'])) {
			$xmlfile = sys_get_temp_dir() . '/package_import_' . rand();

			file_put_contents($xmlfile, $_SESSION['sess_import_package']);
		} else {
			header('Location: package_import.php');
			exit;
		}

		if (isset_request_var('trust_signer') && get_request_var('trust_signer') == 'on') {
			import_validate_public_key($xmlfile, true);
		} elseif (!package_validate_signature($xmlfile)) {
			raise_message('verify_warning', __('You have not Trusted this Package Author.  If you wish to import, check the Automatically Trust Author checkbox'), MESSAGE_LEVEL_ERROR);
			header('Location: package_import?package_location=0');
			exit;
		}

		if (get_filter_request_var('data_source_profile') == '0') {
			$import_as_new = true;
			$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
		} else {
			$import_as_new = false;
			$profile_id = get_request_var('data_source_profile');
		}

		if (get_nfilter_request_var('preview_only') == 'on') {
			$preview_only = true;
		} else {
			$preview_only = false;
		}

		if (isset_request_var('remove_orphans') && get_nfilter_request_var('remove_orphans') == 'on') {
			$remove_orphans = true;
		} else {
			$remove_orphans = false;
		}

		if (isset_request_var('replace_svalues') && get_nfilter_request_var('replace_svalues') == 'on') {
			$replace_svalues = true;
		} else {
			$replace_svalues = false;
		}

		$hashes = array();
		$files  = array();

		/* loop through each of the graphs selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (strpos($var, 'chk_file_') !== false) {
				$id = base64_decode(str_replace('chk_file_', '', $var));
				$id = json_decode($id, true);

				if (strpos($id['pfile'], '/') !== false) {
					$parts = explode('/', $id['pfile']);
				} elseif (strpos($id['pfile'], '\\') !== false) {
					$parts = explode('\\', $id['pfile']);
				} else {
					$parts = array($id['pfile']);
				}

				foreach($parts as $index => $p) {
					if ($p == 'scripts') {
						break;
					} elseif ($p == 'resource') {
						break;
					} else {
						unset($parts[$index]);
					}
				}

				$id['pfile'] = implode('/', $parts);

				$files[] = $id['pfile'];
			}

			if (strpos($var, 'chk_import_') !== false) {
				$id = base64_decode(str_replace('chk_import_', '', $var));
				$id = json_decode($id, true);

				$hashes[] = $id['hash'];
			}
		}

		if (cacti_sizeof($files) && !cacti_sizeof($hashes)) {
			$hashes[] = "dont import";
		} elseif (cacti_sizeof($hashes) && !cacti_sizeof($files)) {
			$files[]  = "dont import";
		}

		$package_name = import_package_get_name($xmlfile);

		$details = import_package_get_details($xmlfile);
		if (isset($details['class'])) {
			$class = $details['class'];
		} else {
			$class = '';
		}

		/* obtain debug information if it's set */
		$data = import_package($xmlfile, $profile_id, $remove_orphans, $replace_svalues, $preview_only, false, false, $hashes, $files, $class);

		if ($preview_only) {
			package_prepare_import_array($templates, $files, $package_name, $xmlfile, $data);

			import_display_package_data($templates, $files, $package_name, $xmlfile, $data);

			unlink($xmlfile);
		} else {
			if ($data !== false) {
				raise_message('import_success', __('The Package %s Imported Successfully', $package_name), MESSAGE_LEVEL_INFO);
			} else {
				raise_message('import_fail', __('The Package %s Import Failed', $package_name), MESSAGE_LEVEL_ERROR);
			}

			unlink($xmlfile);

			unset($_SESSION['sess_import_package']);

			header('Location: package_import.php');
			exit;
		}
	}
}

function package_file_get_contents($filename) {
	if (isset($_SESSION['sess_import_package'])) {
		$xmlfile = sys_get_temp_dir() . '/package_import_' . rand();

		file_put_contents($xmlfile, $_SESSION['sess_import_package']);

		$data = import_read_package_data($xmlfile, $binary_signature);

		if (isset($data['publickey'])) {
			$public_key = base64_decode($data['publickey']);
		} else {
			$public_key = get_public_key();
		}

		$fdata = false;

		foreach ($data['files']['file'] as $file) {
			if ($file['name'] == $filename) {
				$binary_signature = base64_decode($file['filesignature']);

				$fdata = base64_decode($file['data']);

				if (strlen($public_key) < 200) {
					$ok = openssl_verify($fdata, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
				} else {
					$ok = openssl_verify($fdata, $binary_signature, $public_key, OPENSSL_ALGO_SHA256);
				}

				if ($ok != 1) {
					$fdata = false;
				}

				break;
			}
		}

		unlink($xmlfile);

		return $fdata;
	}

	return false;
}

function package_diff_file() {
	global $config;

	$package_location = get_filter_request_var('package_location');
	$package_file     = get_nfilter_request_var('package_file');
	$filename         = get_nfilter_request_var('filename');

	$options = array(
		'ignoreWhitespace' => true,
		'ignoreCase' => false
	);

	$newfile = package_file_get_contents($filename);

	if ($newfile !== false) {
		$newfile = str_replace("\n\r", "\n", $newfile);
		$newfile = explode("\n", $newfile);
	}

	$oldfile = file_get_contents($config['base_path'] . '/' . $filename);

	if ($oldfile !== false) {
		$oldfile = str_replace("\n\r", "\n", $oldfile);
		$oldfile = explode("\n", $oldfile);
	}

	if (cacti_sizeof($oldfile)) {
		if (cacti_sizeof($newfile)) {
			$diff = new Diff($oldfile, $newfile, $options);

			$renderer = new Diff_Renderer_Html_Inline;

			print $diff->render($renderer);
		} else {
			print "New file does not exist";
		}
	} else {
		print "Old file does not exist";
	}
}

function package_get_details() {
	$package_ids      = get_filter_request_var('package_ids', FILTER_VALIDATE_IS_NUMERIC_LIST);
	$package_location = get_filter_request_var('package_location');
	$profile_id       = get_filter_request_var('data_source_profile');
	$remove_orphans   = isset_request_var('remove_orphans') ? true:false;
	$replace_svalues  = isset_request_var('replace_svalues') ? true:false;
	$preview          = true;

	$repo = json_decode(get_repo_manifest_file($package_location), true);

	$manifest = $repo['manifest'];

	if ($package_ids != '') {
		$package_ids = explode(',', $package_ids);

		$templates = array();
		$files     = array();

		foreach($package_ids as $package_id) {
			$filename     = $manifest[$package_id]['filename'];
			$package_name = $manifest[$package_id]['name'];

			$data = get_repo_file($package_location, $filename, false);

			if ($data !== false) {
				$tmp_dir = sys_get_temp_dir() . '/package' . $_SESSION['sess_user_id'];

				if (!is_dir($tmp_dir)) {
					mkdir($tmp_dir);
				}

				$xmlfile = $tmp_dir . '/' . $filename;

				file_put_contents($xmlfile, $data);

				$validated = import_validate_public_key($xmlfile, false);

				if ($validated === false) {
					$public_key = get_public_key();
				} else {
					$public_key = $validated;
				}

				$data = import_package($xmlfile, $profile_id, $remove_orphans, $replace_svalues, $preview);

				package_prepare_import_array($templates, $files, $package_name, $filename, $data);

				unlink($xmlfile);
			} else {
				raise_message_javascript(__('Error in Package'), __('The package "%s" download or validation failed', $package_name), __('See the cacti.log for more information.  It could be that you had either an API Key error or the package was tamered with, or the location is not available.'));
			}
		}

		import_display_package_data($templates, $files, $package_name, $filename, $data);
	} else {
		raise_message_javascript(__('Error in Package'), __('The package download or validation failed'), __('See the cacti.log for more information.  It could be that you had either an API Key error or the package was tamered with, or the location is not available'));
	}
}

function import_validate_public_key($xmlfile, $accept = false) {
	$public_key = get_public_key();

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
				$package_publickey = base64_decode($xml['publickey']);
			}

			if ($package_publickey != '') {
				return $package_publickey;
			} else {
				return get_public_key();
			}
		} else {
			raise_message_javascript(__('Error in Package'), __('Package XML File Damaged.'), __('The XML files appears to be invalid.  Please contact the package author'));
		}
	} else {
		raise_message_javascript(__('Error in Package'), __('The XML files for the package does not exist'), __('Check the package repository file for files that should exist and find the one that is missing'));
	}

	return false;
}

function package_validate_signature($xmlfile) {
	global $config;

	// Cacti public key first
	$cacti_key = get_public_key();

	$package_key = import_package_get_public_key($xmlfile);

	// Other trusted keys next
	$keys = array_rekey(
		db_fetch_assoc('SELECT public_key FROM package_public_keys'),
		'public_key', 'public_key'
	);

	$keys[$cacti_key] = $cacti_key;

	if (in_array($package_key, $keys, true)) {
		return true;
	} else {
		return false;
	}
}

function import_display_package_data($templates, $files, $package_name, $xmlfile, $data) {
	global $config;

	$details = import_package_get_details($xmlfile);

	html_start_box(__('Packages Details'), '100%', '', '1', 'center', '');

	$display_text = array(
		array(
			'display' => __('Author'),
		),
		array(
			'display' => __('Homepage'),
		),
		array(
			'display' => __('Email')
		),
		array(
			'display' => __('Version')
		),
		array(
			'display' => __('Copyright')
		)
	);

	html_header($display_text);

	$id = 99;

	form_alternate_row('line_' . $id);
	form_selectable_cell($details['author'], $id);
	form_selectable_cell($details['homepage'], $id);
	form_selectable_cell($details['email'], $id);
	form_selectable_cell($details['version'], $id);
	form_selectable_cell($details['copyright'], $id);
	form_end_row();

	html_end_box();

	// Show the filename status'
	if (cacti_sizeof($files)) {
		html_start_box(__('Import Package Filenames [ None selected imports all, Check to import selectively ]'), '100%', '', '1', 'center', '');

		$display_text = array(
			array(
				'display' => __('Package'),
			),
			array(
				'display' => __('Filename'),
			),
			array(
				'display' => __('Status')
			)
		);

		html_header_checkbox($display_text, false, '', true, 'file');

		foreach($files as $pdata => $pfiles) {
			$pdata_parts = explode('|', $pdata);

			$file_package_file = $pdata_parts[0];

			if (isset($pdata_parts[1])) {
				$file_package_name = $pdata_parts[1];
			} else {
				$file_package_name = 'Not Set';
			}

			if (cacti_sizeof($pfiles)) {
				foreach($pfiles as $pfile => $status) {
					$id = 'file_' . base64_encode(
						json_encode(
							array(
								'package'  => $file_package_name,
								'filename' => $file_package_file,
								'pfile'    => $pfile,
							)
						)
					);

					form_alternate_row('line_' . $id);
					form_selectable_cell($file_package_name, $id);
					form_selectable_cell($pfile, $id);

					$status  = explode(',', $status);
					$nstatus = '';

					foreach($status as $s) {
						$s = trim($s);

						if ($s == 'differences') {
							$url = 'package_import.php' .
								'?action=diff' .
								'&package_location=0' .
								'&package_file=' . $file_package_file .
								'&package_name=' . $file_package_name .
								'&filename=' . str_replace($config['base_path'] . '/', '', $pfile);

							$nstatus .= ($nstatus != '' ? ', ':'') .
								"<a class='diffme linkEditMain' href='" . html_escape($url) . "'>" . __('Differences') . '</a>';
						} elseif ($s == 'identical') {
							$nstatus .= ($nstatus != '' ? ', ':'') . __('Unchanged');
						} elseif ($s == 'not writable') {
							$nstatus .= ($nstatus != '' ? ', ':'') . __('Not Writable');
						} elseif ($s == 'writable') {
							$nstatus .= ($nstatus != '' ? ', ':'') . __('Writable');
						} elseif ($s == 'new') {
							$nstatus .= ($nstatus != '' ? ', ':'') . __('New');
						} else {
							$nstatus .= ($nstatus != '' ? ', ':'') . __('Unknown');
						}
					}

					form_selectable_cell($nstatus, $id);

					form_checkbox_cell($pfile, $id);

					form_end_row();
				}
			}
		}

		html_end_box();
	}

	if (cacti_sizeof($templates)) {
		html_start_box(__('Import Package Templates [ None selected imports all, Check to import selectively ]'), '100%', '', '1', 'center', '');

		$display_text = array(
			array(
				'display' => __('Template Type')
			),
			array(
				'display' => __('Template Name')
			),
			array(
				'display' => __('Status')
			),
			array(
				'display' => __('Changes/Diffferences')
			)
		);

		html_header_checkbox($display_text, false, '', true, 'import');

		$templates = array_reverse($templates);

		foreach($templates as $hash => $detail) {
			$files = explode('<br>', $detail['package_file']);

			$id = 'import_' . base64_encode(
				json_encode(
					array(
						'package' => $detail['package'],
						'hash'    => $hash,
						'type'    => $detail['type_name'],
						'name'    => $detail['name'],
						'status'  => $detail['status'],
						'files'   => $files
					)
				)
			);

			if ($detail['status'] == 'updated') {
				$status = "<span class='updateObject'>" . __('Updated') . '</span>';
			} elseif ($detail['status'] == 'new') {
				$status = "<span class='newObject'>" . __('New') . '</span>';
			} else {
				$status = "<span class='deviceUp'>" . __('Unchanged') . '</span>';
			}

			form_alternate_row('line_import_' . $detail['status'] . '_' . $id);

			form_selectable_cell($detail['type_name'], $id);
			form_selectable_cell($detail['name'], $id);
			form_selectable_cell($status, $id);

			if (isset($detail['vals'])) {
				$diff_details = '';
				$diff_array   = array();
				$orphan_array = array();

				foreach($detail['vals'] as $package => $diffs) {
					if (isset($diffs['differences'])) {
						foreach($diffs['differences'] as $item) {
							$diff_array[$item] = $item;
						}
					}

					if (isset($diffs['orphans'])) {
						foreach($diffs['orphans'] as $item) {
							$orphan_array[$item] = $item;
						}
					}
				}

				if (cacti_sizeof($diff_array)) {
					$diff_details .= __('Differences') . '<br>' . implode('<br>', $diff_array);
				}

				if (cacti_sizeof($orphan_array)) {
					$diff_details .= ($diff_details != '' ? '<br>':'') . __('Orphans') . '<br>' . implode('<br>', $orphan_array);
				}

				form_selectable_cell($diff_details, $id, '', 'white-space:pre-wrap');
			} else {
				form_selectable_cell(__('None'), $id);
			}

			form_checkbox_cell($detail['name'], $id);

			form_end_row();
		}

		html_end_box();
	}

	?>
	<script type='text/javascript'>

	function getURLVariable(url, varname) {
		var urlparts = url.slice(url.indexOf('?') + 1).split('&');

		for (var i = 0; i < urlparts.length; i++) {
			var urlvar = urlparts[i].split('=');

			if (urlvar[0] == varname) {
				return urlvar[1];
			}
		}

		return null;
	}

	$(function() {
		if ($('#package_import_save2_child').length) {
			applySelectorVisibilityAndActions();

			$('#package_import_save2_child').find('tr[id^="line_import_new_"]').each(function(event) {
				selectUpdateRow(event, $(this));
			});
		}

		$('.diffme').click(function(event) {
			event.preventDefault();

			var url = $(this).attr('href');

			$.get(url, function(data) {
				$('#dialog').html(data);
				var package_name = getURLVariable(url, 'package_name');
				var filename     = getURLVariable(url, 'filename');

				$('#dialog').dialog({
					title: '<?php print __('File Differences for: ');?>' + filename,
					width: '60%',
					maxWidth: '90%',
					maxHeight: 600
				});
			});
		});
	});
	<?php
}

function validate_request_vars() {
	$default_profile = get_default_profile();

	/* ================= input validation and session storage ================= */
	$filters = array(
		'preview_only' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(on|true|false)')),
			'default' => 'on'
		),
		'replace_svalues' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(on|true|false)')),
			'default' => 'on'
		),
		'remove_orphans' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(on|true|false)')),
			'default' => 'on'
		),
		'trust_signer' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(on|true|false)')),
			'default' => 'on'
		),
		'data_source_profile' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => $default_profile
		),
		'image_format' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_image_format')
		),
		'graph_width' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_graph_width')
		),
		'graph_height' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_graph_height')
		),
	);

	validate_store_request_vars($filters, 'sess_pimport');
	/* ================= input validation ================= */
}

function get_import_form($default_profile) {
	global $image_types;

	validate_request_vars();

	if (isset_request_var('preview_only') && get_nfilter_request_var('preview_only') == 'on') {
		$preview_only = 'on';
	} else {
		$preview_only = '';
	}

	if (isset_request_var('replace_svalues') && get_nfilter_request_var('replace_svalues') == 'on') {
		$replace_svalues = 'on';
	} else {
		$replace_svalues = '';
	}

	if (isset_request_var('remove_orphans') && get_nfilter_request_var('remove_orphans') == 'on') {
		$remove_orphans = 'on';
	} else {
		$remove_orphans = '';
	}

	if (isset_request_var('trust_signer') && get_nfilter_request_var('trust_signer') == 'on') {
		$trust_signer = 'on';
	} else {
		$trust_signer = '';
	}

	if (isset_request_var('image_format')) {
		$image_format = get_filter_request_var('image_format');
	} else {
		$image_format = read_config_option('default_image_format');
	}

	if (isset_request_var('graph_width')) {
		$graph_width = get_filter_request_var('graph_width');
	} else {
		$graph_width = read_config_option('default_graph_width');
	}

	if (isset_request_var('graph_height')) {
		$graph_height = get_filter_request_var('graph_height');
	} else {
		$graph_height = read_config_option('default_graph_height');
	}

	$form = array(
		'import_file' => array(
			'friendly_name' => __('Local Package Import File'),
			'description' => __('The *.xml.gz file located on your Local machine to Upload and Import.'),
			'accept' => '.xml.gz',
			'method' => 'file'
		),
		'trust_signer' => array(
			'friendly_name' => __('Automatically Trust Signer'),
			'method' => 'hidden',
			'description' => __('If checked, Cacti will automatically Trust the Signer for this and any future Packages by that author.'),
			'value' => 'on',
			'default' => ''
		),
	);

	$form2 = array(
		'data_header' => array(
			'friendly_name' => __('Data Source Overrides'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'data_source_profile' => array(
			'friendly_name' => __('Data Source Profile'),
			'method' => 'drop_sql',
			'description' => __('Select the Data Source Profile.  The Data Source Profile controls polling interval, the data aggregation, and retention policy for the resulting Data Sources.'),
			'sql' => "SELECT id, name FROM data_source_profiles ORDER BY name",
			'none_value' => __('Create New from Template'),
			'value' => isset_request_var('data_source_profile') ? get_filter_request_var('data_source_profile'):'',
			'default' => $default_profile
		),
		'graph_header' => array(
			'friendly_name' => __('Graph/Data Template Overrides'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'remove_orphans' => array(
			'friendly_name' => __('Remove Orphaned Graph Items'),
			'method' => 'checkbox',
			'description' => __('If checked, Cacti will delete any Graph Items from both the Graph Template and associated Graphs that are not included in the imported Graph Template.'),
			'value' => $remove_orphans,
			'default' => ''
		),
		'replace_svalues' => array(
			'friendly_name' => __('Replace Data Query Suggested Value Patterns'),
			'method' => 'checkbox',
			'description' => __('Replace Data Source and Graph Template Suggested Value Records.  Graphs and Data Sources will take on new names after either a Data Query Reindex or by using the forced Replace Suggested Values process.'),
			'value' => $replace_svalues,
			'default' => ''
		),
		'image_format' => array(
			'friendly_name' => __('Graph Template Image Format'),
			'description' => __('The Image Format to be used when importing or updating Graph Templates.'),
			'method' => 'drop_array',
			'default' => read_config_option('default_image_format'),
			'value' => $image_format,
			'array' => $image_types,
		),
		'graph_height' => array(
			'friendly_name' => __('Graph Template Height', 'pagkage'),
			'description' => __('The Height to be used when importing or updating Graph Templates.'),
			'method' => 'textbox',
			'default' => read_config_option('default_graph_height'),
			'size' => '5',
			'value' => $graph_height,
			'max_length' => '5'
		),
		'graph_width' => array(
			'friendly_name' => __('Graph Template Width'),
			'description' => __('The Width to be used when importing or updating Graph Templates.'),
			'method' => 'textbox',
			'default' => read_config_option('default_graph_width'),
			'size' => '5',
			'value' => $graph_width,
			'max_length' => '5'
		)
	);

	return array_merge($form, $form2);
}

function get_default_profile() {
	$default_profile = db_fetch_cell('SELECT id
		FROM data_source_profiles
		WHERE `default`="on"');

	if (empty($default_profile)) {
		$default_profile = db_fetch_cell('SELECT id
			FROM data_source_profiles
			ORDER BY id
			LIMIT 1');
	}

	return $default_profile;
}

function package_import() {
	global $actions, $hash_type_names;

	if (!isset_request_var('package_location')) {
		set_request_var('package_location', '0');
	}

	set_request_var('package_class', '0');

	if (get_request_var('package_location') == 0) {
		form_start('package_import.php', 'import', true);
	} else {
		form_start('package_import.php', 'import');
	}

	$pform = array();

	$default_profile = get_default_profile();

	$form = get_import_form(get_filter_request_var('package_location'), $default_profile);

	html_start_box(__('Package Import'), '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form
		)
	);

	html_end_box(true, true);

	print '<div id="contents"></div>';
	print "<div id='dialog'></div>";

	form_hidden_box('save_component_import', '1', '');
	form_hidden_box('preview_only', 'on', '');

	form_save_button('', 'import', 'import', false);

	?>
	<script type='text/javascript'>

	$(function() {
		$('#import_file').change(function() {
			$('#preview_only').val('on');

			var form = $('#import')[0];
			var data = new FormData(form);
			var formExtra = '?package_location=0&preview_only=on';

			if ($('#remove_orphans').is(':checked')) {
				formExtra += '&remove_orphans=on';
			}

			if ($('#replace_svalues').is(':checked')) {
				formExtra += '&replace_svalues=on';
			}

			if ($('#trust_signer').is(':checked')) {
				formExtra += '&trust_signer=on';
			}

			Pace.start();

			$.ajax({
				type: 'POST',
				enctype: 'multipart/form-data',
				url: urlPath + 'package_import.php' + formExtra,
				data: data,
				processData: false,
				contentType: false,
				cache: false,
				timeout: 10000,
				success: function (data) {
					if ($('#contents').length == 0) {
						$('#main').append('<div id="contents"></div>');
					} else {
						$('#contents').empty();
					}

					$('#contents').html(data);

					$('#preview_only').val('');

					Pace.stop();
				},
				error: function (e) {
					if ($('#contents').length == 0) {
						$('#main').append('<div id="contents"></div>');
					} else {
						$('#contents').empty();
					}

					$('#contents').html(data);

					Pace.stop();
				}
			});
		});
	});
	</script>
	<?php

	form_end();
}

function form_dialog_box() {
	print '<div style="display:none">
		<div id="import_dialog" title="">
			<div id="import_message"></div>
		</div>
	</div>';
}

function get_repo_file($repo_id, $filename = 'package.manifest', $javascript = false) {
	return false;
}

function get_repo_manifest_file($repo_id) {
	return get_repo_file($repo_id, 'package.manifest');
}

function is_tmp_writable() {
	$tmp_dir  = sys_get_temp_dir();
	$tmp_len  = strlen($tmp_dir);
	$tmp_dir .= ($tmp_len !== 0 && substr($tmp_dir, -$tmp_len) === '/') ? '': '/';
	$is_tmp   = is_resource_writable($tmp_dir);

	return $is_tmp;
}

function package_prepare_import_array(&$templates, &$files, $package_name, $package_filename, $import_info) {
	global $hash_type_names;

	/**
	 * This function will create an array of item types and their status
	 * the user will have an option to import select items based upon
	 * these values.
	 *
	 * $templates['template_hash'] = array(
	 *    'package'      => 'some_package_name',
	 *    'package_file' => 'some_package_filename',
	 *    'type'         => 'some_type',
	 *    'type_name'    => 'some_type_name',
	 *    'name'         => 'some_name',
	 *    'status'       => 'some_status'
	 * );
	 *
	 * $files[$package_filename|$package_name] = array(
	 *    'filename' => 'somefilename'
	 * );
	 */

	if (cacti_sizeof($import_info)) {
		if (isset($import_info[1]) && cacti_sizeof($import_info[1])) {
			foreach($import_info[1] as $filename => $status) {
				$files["$package_filename|$package_name"][$filename] = $status;
			}
		}

		if (isset($import_info[0]) && cacti_sizeof($import_info[0])) {
			foreach ($import_info[0] as $type => $type_array) {
				if ($type == 'files') {
					continue;
				}

				foreach ($type_array as $index => $vals) {
					$hash = $vals['hash'];

					$templates[$hash]['package']      = $package_name;
					$templates[$hash]['package_file'] = $package_filename;
					$templates[$hash]['status']       = $vals['type'];
					$templates[$hash]['type']         = $type;
					$templates[$hash]['type_name']    = $hash_type_names[$type];
					$templates[$hash]['name']         = $vals['title'];

					unset($vals['title']);
					unset($vals['result']);
					unset($vals['hash']);
					unset($vals['type']);

					if (isset($vals['dep'])) {
						unset($vals['dep']);
					}

					if (cacti_sizeof($vals)) {
						$templates[$hash]['vals'][$package_name] = $vals;
					}
				}
			}
		}
	}
}

