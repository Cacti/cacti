<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/export.php');
include_once('./lib/import.php');

/* set default action */
set_default_action();

if (check_get_author_info() === false) {
	exit;
}

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'get_contents':
		$export_type    = get_nfilter_request_var('export_type');
		$export_item_id = get_nfilter_request_var('export_item_id');
		$include_deps   = (get_nfilter_request_var('include_deps') == 'true' ? true:false);

		print get_package_contents($export_type, $export_item_id);

		break;
	default:
		top_header();
		export();
		bottom_footer();

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function get_export_hash($export_type, $export_item_id) {
	switch($export_type) {
		case 'host_template':
			if (!empty($export_item_id)) {
				return db_fetch_cell_prepared('SELECT hash
					FROM host_template
					WHERE id = ?',
					array($export_item_id));
			} else {
				return db_fetch_cell('SELECT hash
					FROM host_template
					ORDER BY name
					LIMIT 1');
			}

			break;
		case 'graph_template':
			if (!empty($export_item_id)) {
				return db_fetch_cell_prepared('SELECT hash
					FROM graph_templates
					WHERE id = ?',
					array($export_item_id));
			} else {
				return db_fetch_cell('SELECT hash
					FROM graph_templates
					ORDER BY name
					LIMIT 1');
			}

			break;
		case 'data_query':
			if (!empty($export_item_id)) {
				return db_fetch_cell_prepared('SELECT hash
					FROM snmp_query
					WHERE id = ?',
					array($export_item_id));
			} else {
				return db_fetch_cell('SELECT hash
					FROM snmp_query
					ORDER BY name
					LIMIT 1');
			}

			break;
		default:
			return '';
			break;
	}
}

function form_save() {
	global $export_types, $export_errors, $debug, $package_file;

    /* ================= input validation ================= */
    get_filter_request_var('export_item_id');
    /* ==================================================== */

	$export_okay = false;

	$xml_data = get_item_xml(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'), (((isset_request_var('include_deps') ? get_nfilter_request_var('include_deps') : '') == '') ? false : true));

	$info                 = array();
	$info['name']         = get_nfilter_request_var('name');
	$info['author']       = get_nfilter_request_var('author');
	$info['homepage']     = get_nfilter_request_var('homepage');
	$info['email']        = get_nfilter_request_var('email');
	$info['description']  = get_nfilter_request_var('description');
	$info['class']        = get_nfilter_request_var('class');
	$info['tags']         = get_nfilter_request_var('tags');
	$info['installation'] = get_nfilter_request_var('installation');
	$info['version']      = get_nfilter_request_var('version');
	$info['copyright']    = get_nfilter_request_var('copyright');

	// Let's store the Template information for subsequent exports
	$hash = get_export_hash(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'));

	$export_okay = save_packager_metadata($hash, $info);

	$debug = '';

	if ($export_okay) {
		$files = find_dependent_files($xml_data);

		/* search xml files for scripts */
		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				if (strpos($file['file'], '.xml') !== false) {
					$files = array_merge($files, find_dependent_files(file_get_contents($file['file'])));
				}
			}
		}

		$success = package_template($xml_data, $info, $files, $debug);
	} else {
		top_header();
		print __('WARNING: Export Errors Encountered. Refresh Browser Window for Details!') . "\n";
		print $xml_data;
		bottom_footer();
		exit;
	}

	if ($export_errors || !$success) {
		raise_message('package_error', __('There were errors packaging your Templates.  Errors Follow. ') . str_replace("\n", '<br>', $debug), MESSAGE_LEVEL_ERROR);
		header('Location: package.php');
		exit;
	} elseif ($package_file != '') {
		header('Content-Type: application/gzip');
		header('Content-Disposition: attachment; filename="' . basename($package_file) . '"');
		header('Content-Length: ' . filesize($package_file));
		header('Content-Control: no-cache');
		header('Pragma: public');
		header('Expires: 0');
		readfile($package_file);
		unlink($package_file);
		exit;
	}
}

/* ---------------------------
    Template Export Functions
   --------------------------- */

function find_dependent_files($xml_data, $raise_message = false) {
	$files = array();
	$data  = explode("\n", $xml_data);

	foreach($data as $line) {
		if (strpos($line, '<xml_path>') !== false) {
			$line = str_replace('<xml_path>', '', $line);
			$line = str_replace('</xml_path>', '', $line);

			$files = process_paths($line, $files, $raise_message);
		} elseif (strpos($line, '<script_path>')) {
			$line = str_replace('<script_path>', '', $line);
			$line = str_replace('</script_path>', '', $line);
			$files = process_paths($line, $files, $raise_message);
		} elseif (strpos($line, '<input_string>')) {
			$line  = str_replace('<input_string>', '', $line);
			$line  = str_replace('</input_string>', '', $line);
			$line  = base64_decode($line);
			$line  = xml_character_decode($line);
			$line  = str_replace('><', '> <', $line);
			$line  = str_replace('>""<', '>" "<', $line);
			$line  = str_replace('>\'\'<', '>\' \'<', $line);
			$files = process_paths($line, $files, $raise_message);
		}
	}

	return $files;
}

function process_paths($line, $files, $raise_message) {
	$paths = find_paths(trim($line));
	if (cacti_sizeof($paths['paths'])) {
		$files = array_merge($files, $paths['paths']);
	}

	if (cacti_sizeof($paths['missing_paths'])) {
		if ($raise_message) {
			foreach($paths['missing_paths'] as $p) {
				raise_message('missing_' . $p['file'], __('A Critical Template file \'%s\' is missing.  Please locate this file before packaging', $p['file']), MESSAGE_LEVEL_ERROR);
			}
		}
	}

	return $files;
}

/**
	types include
	xml          => location found in template xml <xml_path>
	script       => location found in xml <input_string>
	resource_xml => location found in resource xml file
*/
function find_paths($input, $type = 'cacti_xml') {
	global $config;

	$excluded_paths = array(
		'/bin/',
		'/usr/bin/',
		'/usr/local/bin/'
	);

	$excluded_basenames = array(
		'bash',
		'snmpwalk',
		'snmpget',
		'snmpbulkwalk',
		'csh',
		'tcsh',
		'ksh',
		'sh',
		'python',
		'perl',
		'php',
		'grep',
		'awk',
		'wc'
	);

	$paths  = array();
	$mpaths = array();

	$input = htmlspecialchars_decode($input);
	$parts = preg_split('/\s+/', $input);

	foreach($parts as $part) {
		$opath = htmlspecialchars($part);
		$part  = str_replace('<path_cacti>', CACTI_PATH_BASE, $part);
		$part  = str_replace('|path_cacti|', CACTI_PATH_BASE, $part);
		$part  = str_replace('|path_php_binary|', '', $part);

		if (trim($part) == '') continue;

		$valid = true;
		if (file_exists($part)) {
			foreach($excluded_paths as $path) {
				if (strpos($part, $path) !== false) {
					$valid = false;
					break;
				}
			}

			if ($valid) {
				foreach($excluded_basenames as $binary) {
					if (strpos($binary, basename($part)) !== false) {
						$valid = false;
						break;
					}
				}
			}

			if ($valid) {
				$paths[] = array('opath' => $opath, 'file' => $part);
			}
		} elseif (strpos($part, '/') !== false || strpos($part, "\\") !== false) {
			$mpaths[] = array('opath' => $opath, 'file' => $part);
		}
	}

	return array('paths' => $paths, 'missing_paths' => $mpaths);;
}

function package_template(&$template, &$info, &$files, &$debug) {
	global $config, $export_errors, $package_file;

	$binary_signature = '';
	$debug            = '';
	$private_key      = get_package_private_key();
	$public_key       = get_package_public_key();
	$my_base          = CACTI_PATH_BASE . '/';

	/* set new timeout and memory settings */
	ini_set('max_execution_time', '0');
	ini_set('memory_limit', '-1');
	ini_set('zlib.output_compression', '0');

	/* establish a temp directory */
	if ($config['cacti_server_os'] == 'unix') {
		$tmpdir = '/tmp/';
	} else {
		$tmpdir = getenv('TEMP');
	}

	/* write the template to disk */
	$xmlfile = $tmpdir . '/' . clean_up_name($info['name']) . '.xml';
	file_put_contents($xmlfile, $template);

	/* create the package xml file */
	$xml = "<xml>\n";
	$xml .= "   <info>\n";
	$xml .= "     <name>" . $info['name'] . "</name>\n";

	if (isset($info['author'])) {
		$xml   .= '     <author>' . $info['author'] . "</author>\n";
		$debug .= ' Author     : ' . $info['author'] . "\n";
	}

	if (isset($info['homepage'])) {
		$xml   .= '     <homepage>' . $info['homepage'] . "</homepage>\n";
		$debug .= ' Homepage   : ' . $info['homepage'] . "\n";
	}

	if (isset($info['email'])) {
		$xml   .= '     <email>' . $info['email'] . "</email>\n";
		$debug .= ' Email      : ' . $info['email'] . "\n";
	}

	if (isset($info['description'])) {
		$xml   .= '     <description>' . $info['description'] . "</description>\n";
		$debug .= ' Description: ' . $info['description'] . "\n";
	}

	if (isset($info['class'])) {
		$xml   .= '     <class>' . $info['class'] . "</class>\n";
		$debug .= ' Class: ' . $info['class'] . "\n";
	}

	if (isset($info['tags'])) {
		$xml   .= '     <tags>' . $info['tags'] . "</tags>\n";
		$debug .= ' Tags: ' . $info['tags'] . "\n";
	}

	if (isset($info['installation'])) {
		$xml   .= '     <installation>' . $info['installation'] . "</installation>\n";
		$debug .= ' Instructions: ' . $info['installation'] . "\n";
	}

	if (isset($info['version'])) {
		$xml   .= '     <version>' . $info['version'] . "</version>\n";
		$debug .= ' Version    : ' . $info['version'] . "\n";
	}

	if (isset($info['copyright'])) {
		$xml   .= '     <copyright>' . $info['copyright'] . "</copyright>\n";
		$debug .= ' Copyright  : ' . $info['copyright'] . "\n";
	}

	$xml .= "   </info>\n";

	$debug .= "Packaging Dependent files....\n";

	$debug .= ' Files Specified: ' . sizeof($files) . "\n";

	/* calculate directories */
	$directories = array();
	if (cacti_sizeof($files)) {
		foreach($files as $file) {
			$directories[dirname($file['file'])] = dirname($file['file']);
		}
	}

	$debug .= ' Directories extracted: ' . sizeof($directories) . "\n";

	$xml .= "   <directories>\n";
	if (cacti_sizeof($directories)) {
		foreach ($directories as $dir) {
			$debug .= "   Adding Directory: $dir\n";
			$xml .= "       <directory>" . str_replace($my_base, '', $dir) . "</directory>\n";
		}
	}
	$xml .= "   </directories>\n";

	$files['template'] = array('file' => $xmlfile, 'type' => 'template');

	$xml .= "   <files>\n";

	$dupfiles = array();
	foreach ($files as $file) {
		$name = $file['file'];

		// Prevent doing a file twice
		if (isset($dupfiles[$name])) {
			continue;
		}
		$dupfiles[$name] = true;

		if (isset($file['opath'])) {
			$opath = $file['opath'];
		} else {
			$opath = '';
		}

		if (isset($file['type'])) {
			$type = $file['type'];
		} else {
			$type = '';
		}

		$debug .= "   Adding File: $name\n";

		$binary_signature = '';
		$xml .= "       <file>\n";

		if ($type != '') {
			$xml .= "           <name>" . basename($name) . "</name>\n";
		} else {
			$xml .= "           <name>" . str_replace($my_base, '', $name) . "</name>\n";
		}

		if ($opath != '') {
			$xml .= "           <opath>$opath</opath>\n";
		}

		if ($type != '') {
			$xml .= "           <type>template</type>\n";
		}

		if (file_exists($name)) {
			$data = file_get_contents($name);
		} else {
			$data = 'Not Found';
		}

		openssl_sign($data, $binary_signature, $private_key, OPENSSL_ALGO_SHA256);

		if ($data) {
			$data = base64_encode($data);
		}

		$xml .= "           <data>$data</data>\n";
		$xml .= "           <filesignature>" . base64_encode($binary_signature) . "</filesignature>\n";
		$xml .= "       </file>\n";
	}

	$xml .= "   </files>\n";
	$xml .= "   <publickeyname>" . $info['author']                  . "</publickeyname>\n";
	$xml .= "   <publickey>"     . base64_encode($public_key)       . "</publickey>\n";

	/* get rid of the temp file */
	unlink($files['template']['file']);

	$debug .= "NOTE: Signing Plugin using SHA256\n";
	$binary_signature = '';
	openssl_sign($xml . "   <signature></signature>\n</xml>", $binary_signature, $private_key, OPENSSL_ALGO_SHA256);

	$ok = openssl_verify($xml . "   <signature></signature>\n</xml>", $binary_signature, $public_key, OPENSSL_ALGO_SHA256);

	$debug .= "NOTE: Base 64 Encoding Files and SHA256 Signing each file\n";

	if ($ok == 1) {
		$basesig = base64_encode($binary_signature);
		$debug .= "NOTE: Signing Complete\n";
	} elseif ($ok == 0) {
		$basesig = '';
		$debug .= "ERROR: Could not sign\n";
		return false;
	} else {
		$basesig = '';
		$debug .= "ERROR: Could not sign\n";
		return false;
	}

	$xml .= "   <signature>"     . $basesig                         . "</signature>\n</xml>";

	$name = get_item_name(get_request_var('export_type'), get_request_var('export_item_id'));

	$debug .= "NOTE: Creating compressed template xml \"" . clean_up_name($name) . ".xml.gz\"\n";

	$f = fopen("compress.zlib://$tmpdir/" . clean_up_name($name) . ".xml.gz",'wb');
	fwrite($f, $xml, strlen($xml));
	fclose($f);

	$package_file = $tmpdir . '/' . clean_up_name($name) . '.xml.gz';

	return true;
}

function get_item_name($export_type, $export_id) {
	$name = 'Unknown';

	switch($export_type) {
		case 'host_template':
			$name = db_fetch_cell_prepared('SELECT name
				FROM host_template
				WHERE id = ?', array($export_id));

			break;
		case 'graph_template':
			$name = db_fetch_cell_prepared('SELECT name
				FROM graph_templates
				WHERE id = ?',
				array($export_id));

			break;
		case 'data_query':
			$name = db_fetch_cell_prepared('SELECT name
				FROM snmp_query
				WHERE id = ?',
				array($export_id));

			break;
	}

	return $name;
}

function export() {
	global $export_types, $config, $package_classes;

	/* 'graph_template' should be the default */
	if (!isset_request_var('export_type')) {
		set_request_var('export_type', 'host_template');

		$id = db_fetch_cell('SELECT id FROM host_template ORDER BY name LIMIT 1');

		set_request_var('export_item_id', $id);
	}

	unset($export_types['data_template']);

	switch (get_nfilter_request_var('export_type')) {
		case 'host_template':
		case 'graph_template':
		case 'data_query':
			break;
		default:
			set_request_var('export_type', 'host_template');
	}

	html_start_box(__('Package Templates'), '100%', '', '3', 'center', '');

	?>
	<tr class='tableRow'>
		<td>
			<table>
				<tr>
					<td><span class='formItemName'><?php print __('What would you like to Package?');?>&nbsp;</span></td>
					<td>
						<select id='export_type'>
							<?php
							foreach($export_types as $key => $array) {
								print "<option value='$key'"; if (get_nfilter_request_var('export_type') == $key) { print ' selected'; } print '>' . html_escape($array['name']) . "</option>";
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box();

	$info = check_get_author_info();
	if ($info === false) {
		exit;
	}

	// Let's get any saved package details from the last time the template was packaged
	$hash = get_export_hash(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'));

	// Two methods, one with SQLite and one without

	$data = array();
	if (class_exists('SQLite3')) {
		$data = get_packager_metadata($hash);
	}

	// Legacy support, to be deprecated eventually
	if (!cacti_sizeof($data)) {
		$data = read_config_option('package_export_' . $hash);

		if ($data != '') {
			$data = json_decode($data, true);
		}
	}

	// If this template has not been saved before, initialize values
	if (!is_array($data)) {
		$data = array();

		switch(get_nfilter_request_var('export_type')) {
			case 'host_template':
				if (!isset_request_var('export_item_id')) {
					$detail = db_fetch_row('SELECT id, name
						FROM host_template
						ORDER BY name
						LIMIT 1');
				} else {
					$detail = db_fetch_row_prepared('SELECT id, name
						FROM host_template
						WHERE id = ?',
						array(get_filter_request_var('export_item_id')));
				}

				break;
			case 'graph_template':
				if (!isset_request_var('export_item_id')) {
					$detail = db_fetch_row('SELECT id, name
						FROM graph_templates
						ORDER BY name
						LIMIT 1');
				} else {
					$detail = db_fetch_row_prepared('SELECT id, name
						FROM graph_templates
						WHERE id = ?',
						array(get_filter_request_var('export_item_id')));
				}

				break;
			case 'data_query':
				if (!isset_request_var('export_item_id')) {
					$detail = db_fetch_row('SELECT id, name
						FROM snmp_query
						ORDER BY name
						LIMIT 1');
				} else {
					$detail = db_fetch_row_prepared('SELECT id, name
						FROM snmp_query
						WHERE id = ?',
						array(get_filter_request_var('export_item_id')));
				}

				break;
		}

		if (cacti_sizeof($detail)) {
			switch(get_nfilter_request_var('export_type')) {
				case 'host_template':
					$data['description'] = __('%s Device Package', $detail['name']);
					break;
				case 'graph_template':
					$data['description'] = __('%s Graph Template Package', $detail['name']);
					break;
				case 'data_query':
					$data['description'] = __('%s Data Query Package', $detail['name']);
					break;
			}

			$data['tags']         = '';
			$data['installation'] = '';
			$data['name'] = $detail['name'];
		}
	} else {
		$detail = $data;
	}

	if (cacti_sizeof($data)) {
		$info = array_merge($info, $data);
	}

	form_start('package.php', 'form_id');

	html_start_box(__('Available Templates [%s]', $export_types{get_nfilter_request_var('export_type')}['name']), '100%', '', '3', 'center', '');

	$package_form = array(
		'spacer0' => array(
			'method' => 'spacer',
			'friendly_name' => __('Available Templates'),
		),
		'export_item_id' => array(
			'method' => 'drop_sql',
			'friendly_name' => __('%s to Export', $export_types{get_nfilter_request_var('export_type')}['name']),
			'description' => __('Choose the exact items to export in the Package.'),
			'value' => (isset_request_var('export_item_id') ? get_filter_request_var('export_item_id'):'|arg1:export_item_id|'),
			'sql' => $export_types[get_nfilter_request_var('export_type')]['dropdown_sql']
		),
		'include_deps' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Include Dependencies'),
			'description' => __('Some templates rely on other items in Cacti to function properly. It is highly recommended that you select this box or the resulting import may fail.'),
			'value' => 'on',
			'sql' => $export_types[get_nfilter_request_var('export_type')]['dropdown_sql']
		),
		'spacer1' => array(
			'method' => 'spacer',
			'friendly_name' => __('Package Information'),
		),
		'description' => array(
			'method' => 'textbox',
			'friendly_name' => __('Package Description'),
			'description' => __('The Package Description.'),
			'value' => (isset($info['description']) ? $info['description']:read_config_option('package_description', true)),
			'max_length' => '255',
			'size' => '80'
		),
		'copyright' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Package Copyright'),
			'description' => __('The license type for this package.'),
			'value' => (isset($info['copyright']) ? $info['copyright']:'GNU General Public License'),
			'array' => array(
				'Apache License 2.0'                 => __('Apache License 2.0'),
				'Creative Commons'                   => __('Creative Commons'),
				'GNU General Public License'         => __('GNU General Public License'),
				'MIT License'                        => __('MIT License'),
				'Eclipse Public License version 2.0' => __('Eclipse Public License version 2.0'),
			),
			'default' => 'GNU General Public License'
		),
		'version' => array(
			'method' => 'textbox',
			'friendly_name' => __('Package Version'),
			'description' => __('The version number to publish for this Package.'),
			'value' => (isset($info['version']) ? $info['version']:read_config_option('package_version', true)),
			'max_length' => '10',
			'size' => '10'
		),
		'class' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Package Class'),
			'description' => __('The Classification of the Package.'),
			'value' => (isset($info['class']) ? $info['class']:read_config_option('package_class', true)),
			'array' => $package_classes,
			'default' => 'linux'
		),
		'tags' => array(
			'method' => 'textarea',
			'friendly_name' => __('Package Tags'),
			'description' => __('Assign various searchable attributes to the Package.'),
			'value' => (isset($info['tags']) ? $info['tags']:read_config_option('package_tags', true)),
			'textarea_rows' => '2',
			'textarea_cols' => '80'
		),
		'installation' => array(
			'method' => 'textarea',
			'friendly_name' => __('Installation Instructions'),
			'description' => __('Some Packages require additional changes outside of Cacti\'s scope such as setting up an SNMP Agent Extension on the Devices to be monitored.  You should add those instructions here..'),
			'value' => (isset($info['installation']) ? $info['installation']:read_config_option('package_installation', true)),
			'textarea_rows' => '5',
			'textarea_cols' => '80'
		),
		'spacer2' => array(
			'method' => 'spacer',
			'friendly_name' => __('Author Information'),
		),
		'author' => array(
			'method' => 'other',
			'friendly_name' => __('Author Name'),
			'description' => __('The Registered Authors Name.'),
			'value' => $info['author'],
			'max_length' => '40',
			'size' => '40'
		),
		'homepage' => array(
			'method' => 'other',
			'friendly_name' => __('Homepage'),
			'description' => __('The Registered Authors Home Page.'),
			'value' => $info['homepage'],
			'max_length' => '60',
			'size' => '60'
		),
		'email' => array(
			'method' => 'other',
			'friendly_name' => __('Email Address'),
			'description' => __('The Registered Authors Email Address.'),
			'value' => $info['email'],
			'max_length' => '60',
			'size' => '60'
		),
		'export_type' => array(
			'method' => 'hidden',
			'value' => get_nfilter_request_var('export_type')
		)
	);

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $package_form
		)
	);

	html_end_box();

	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<input type='hidden' id='name' name='name' value='<?php print $detail['name'];?>'>
				<input class='export' type='submit' value='<?php print __('Package');?>'>
			</td>
		</tr>
	</table>
	</form>
	<script type='text/javascript'>
	var stopTimer = null;

	$(function() {
		$('#export_type').change(function() {
			strURL  = urlPath+'package.php';
			strURL += '?header=false';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&author='+$('#author').val();
			strURL += '&homepage='+escape($('#homepage').val());
			strURL += '&email='+$('#email').val();
			strURL += '&description='+escape($('#description').val());
			strURL += '&version='+escape($('#version').val());
			loadPageNoHeader(strURL);
		});

		$('#export_item_id').change(function() {
			strURL  = urlPath+'package.php';
			strURL += '?header=false';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&export_item_id='+$('#export_item_id').val();
			strURL += '&author='+$('#author').val();
			strURL += '&homepage='+escape($('#homepage').val());
			strURL += '&email='+$('#email').val();
			strURL += '&description='+escape($('#description').val());
			strURL += '&version='+escape($('#version').val());
			loadPageNoHeader(strURL);
		});

		if ($('#details').length) {
			strURL  = urlPath+'package.php';
			strURL += '?action=get_contents';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&export_item_id='+$('#export_item_id').val();
			strURL += '&include_deps='+$('#include_deps').is(':checked');
			$.get(strURL, function(data) {
				$('#details').html(data);
				$('#name').val($('#export_item_id option:selected').text());
			});
		}

		$('form#form_id').submit(function(event) {
			stopTimer = setTimeout(function() { Pace.stop() }, 1000);
		});

		if ($('#name').val() == '') {
			$('#name').val($('#export_item_id option:selected').text());
		}
	});
	</script>
	<?php

	html_start_box(__('Package Contents Include'), '100%', '', '3', 'center', '');

	if (isset_request_var('export_type') && isset_request_var('export_item_id')) {
		print get_package_contents(get_request_var('export_type'), get_request_var('export_item_id'));
	} else {
		print '<div id="details" style="vertical-align:top">';
		print '</div>';
	}

	html_end_box();
}

function check_template_dependencies($export_type, $template_id) {
	$error_message .= ($error_message != '' ? '<br>':'') . __('Script or Resource File \'%s\' does not exist.  Please repackage after locating and installing this file', $file['file']);
}

function check_get_author_info() {
	global $config;

	if (file_exists(CACTI_PATH_BASE . '/cache/package/package.info')) {
		$info = parse_ini_file(CACTI_PATH_BASE . '/cache/package/package.info', true);
		$info = $info['info'];

		return $info;
	} else {
		?>
		<script type='text/javascript'>
		var mixedReasonTitle = '<?php print __('Key Generation Required to Use Plugin');?>';
		var mixedOnPage      = '<?php print __esc('Package Key Information Not Found');?>';
		sessionMessage = {
			message: '<?php print __('In order to use this Plugin, you must first run the <b><i class="deviceUp">genkey.php</i></b> script in the plugin directory.  Once that is complete, you will have a public and private key used to sign your packages.');?>',
			level: MESSAGE_LEVEL_MIXED
		};

		$(function() {
			displayMessages();
		});
		</script>
		<?php

		return false;
	}
}

function open_packager_metadata_table() {
	global $config;

	$db_file   = CACTI_PATH_BASE . '/cache/package/package.db';
	$db_struct = 'CREATE TABLE package (
		hash char(32) NOT NULL,
		name char(40) NOT NULL,
		author char(40) NOT NULL,
		homepage char(60) NOT NULL,
		email char(60) NOT NULL,
		description char(128) NOT NULL,
		class char(20) NOT NULL,
		tags char(128) NOT NULL,
		installation char(1024) NOT NULL,
		version char(20) NOT NULL,
		copyright char(40) NOT NULL,
		PRIMARY KEY (hash))';

	if (is_writeable(dirname($db_file))) {
		$create = true;
		if (file_exists($db_file)) {
			$create = false;
		}

		$cnn = new SQLite3($db_file);

		if (is_object($cnn)) {
			if ($create) {
				$cnn->exec($db_struct);
			}
		}

		return $cnn;
	} else {
		raise_message('package_nowrite', __('The Web Server must have write access to the \'package\' plugin directory'), MESSAGE_LEVEL_ERROR);
	}

	return false;
}

function get_packager_metadata($hash) {
	$cnn = open_packager_metadata_table();

	if (is_object($cnn)) {
		$query = $cnn->prepare('SELECT * FROM package WHERE hash = :hash');

		$query->bindValue(':hash', $hash, SQLITE3_TEXT);

		$result = $query->execute();

		if ($result !== false) {
			return $result->fetchArray();
		} else {
			return false;
		}
	} else {
		raise_message('package_connection', __('Unable to initialize SQLite3'), MESSAGE_LEVEL_ERROR);
	}

	return false;
}

function save_packager_metadata($hash, $info) {
	if (class_exists('SQLite3')) {
		$cnn = open_packager_metadata_table();

		$query = $cnn->prepare('REPLACE INTO package (hash, name, author, homepage, email, description, class, tags, installation, version, copyright)
			VALUES (:hash, :name, :author, :homepage, :email, :description, :class, :tags, :installation, :version, :copyright)');

		$query->bindValue(':hash', $hash, SQLITE3_TEXT);
		$query->bindValue(':name', $info['name'], SQLITE3_TEXT);
		$query->bindValue(':author', $info['author'], SQLITE3_TEXT);
		$query->bindValue(':homepage', $info['homepage'], SQLITE3_TEXT);
		$query->bindValue(':email', $info['email'], SQLITE3_TEXT);
		$query->bindValue(':description', $info['description'], SQLITE3_TEXT);
		$query->bindValue(':class', $info['class'], SQLITE3_TEXT);
		$query->bindValue(':tags', $info['tags'], SQLITE3_TEXT);
		$query->bindValue(':installation', $info['installation'], SQLITE3_TEXT);
		$query->bindValue(':version', $info['version'], SQLITE3_TEXT);
		$query->bindValue(':copyright', $info['copyright'], SQLITE3_TEXT);

		$result = $query->execute();

		if ($result !== false) {
			return true;
		} else {
			return false;
		}
	} else {
		set_config_option('package_export_' . $hash, json_encode($info));
		return true;
	}
}

function get_package_contents($export_type, $export_item_id, $include_deps = true) {
	global $config, $export_errors;

	$types = array(
		'host_template',
		'graph_template',
		'data_query'
	);

	// Determine what files are included
	$export_errors = 0;
	$xml_data      = get_item_xml($export_type, $export_item_id, $include_deps);
	$file          = array();

	if (!$export_errors) {
		$files = find_dependent_files($xml_data, true);

		/* search xml files for scripts */
		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				if (strpos($file['file'], '.xml') !== false) {
					$files = array_merge($files, find_dependent_files(file_get_contents($file['file']), $file));
				}
			}
		}
	} else {
		return __('Cacti Template has Errors.  Unable to parse entire template.');
	}

	$graph_templates = array();
	$queries = array();
	$query_graph_templates = array();

	switch($export_type) {
		case 'host_template':
			$graph_templates = db_fetch_assoc_prepared('SELECT gt.id, gt.name
				FROM host_template_graph AS htg
				INNER JOIN graph_templates AS gt
				ON gt.id = htg.graph_template_id
				WHERE host_template_id = ?',
				array($export_item_id));

			$queries = db_fetch_assoc_prepared('SELECT sq.*
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query AS sq
				ON sq.id = htsq.snmp_query_id
				WHERE host_template_id = ?',
				array($export_item_id));

			break;
		case 'data_query':
			$queries = db_fetch_assoc_prepared('SELECT sq.*
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query AS sq
				ON sq.id = htsq.snmp_query_id
				WHERE host_template_id = ?',
				array($export_item_id));

			$query_graph_templates = db_fetch_assoc_prepared('SELECT sqg.graph_template_id, sqg.name
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query_graph AS sqg
				ON htsq.snmp_query_id = sqg.snmp_query_id
				WHERE host_template_id = ?',
				array($export_item_id));

			break;
		case 'graph_template':
			$graph_templates = db_fetch_assoc_prepared('SELECT *
				FROM graph_templates AS gt
				WHERE id = ?',
				array($export_item_id));

			break;
	}

	$output = '<div class="flexContainer cactiTable" style="justify-content:space-around;">';

	if (cacti_sizeof($graph_templates)) {
		$output .= '<div class="flexChild" style="vetical-align:top;width:24%;padding:0px 5px;">';

		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Graph Templates') . '</div></div>';

		foreach($graph_templates as $t) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $t['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($queries)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Data Queries') . '</div></div>';

		foreach($queries as $q) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $q['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($query_graph_templates)) {
		$output .= '<div class="flexChild" style="50%;vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Data Query Graph Templates') . '</div></div>';

		foreach($graph_templates as $t) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $t['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($queries)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Resource Files') . '</div></div>';

		foreach($queries as $q) {
			$file = str_replace('<path_cacti>', CACTI_PATH_BASE, $q['xml_path']);
			$exists = file_exists($file);
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . html_escape(basename($file)) . ($exists ? '<i class="fa fa-check-circle deviceUp"></i>':'<i class="fa fa-cross deviceDown"></i>') . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($files)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Script Files') . '</div></div>';

		$found = array();

		foreach($files as $file) {
			if (array_search($file, $found) === false) {
				if (strpos($file['file'], '/resource/') === false) {
					$exists = file_exists($file['file']);
					$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . html_escape(basename($file['file'])) .  ($exists ? '<i class="fa fa-check-circle deviceUp"></i>':'<i class="fa fa-cross deviceDown"></i>') . '</div></div>';
				}

				$found[] = $file;
			}
		}

		$output .= '</div>';
	}

	$output .= '</div>';

	return $output;
}

function get_package_private_key() {
	global $config;

	if (file_exists(CACTI_PATH_BASE . '/cache/package/package.key')) {
		return 'file://' . CACTI_PATH_BASE . '/cache/package/package.key';
	} else {
		print 'FATAL: You must run genkey.php to generate your key first' . PHP_EOL;
		return false;
	}
}

function get_package_public_key() {
	global $config;

	if (file_exists(CACTI_PATH_BASE . '/cache/package/package.pem')) {
		$key = openssl_pkey_get_public('file://' . CACTI_PATH_BASE . '/cache/package/package.pem');
		if ($key === false) {
			cacti_log('FATAL: Unable to extract Public Key from Pem File.');
			return false;
		} else {
			$keyData = openssl_pkey_get_details($key);
			return $keyData['key'];
		}
	} else {
		print 'FATAL: You must run genkey.php to generate your key first' . PHP_EOL;
		return false;
	}
}

