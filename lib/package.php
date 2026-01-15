<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

function get_export_hash($export_type, $export_item_id) {
	switch($export_type) {
		case 'host_template':
			if (!empty($export_item_id)) {
				return db_fetch_cell_prepared('SELECT hash
					FROM host_template
					WHERE id = ?',
					[$export_item_id]);
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
					[$export_item_id]);
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
					[$export_item_id]);
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

function check_template_dependencies($export_type, $template_id) {
	/* FIX ME: This function is not used */
	//$error_message .= ($error_message != '' ? '<br>':'') . __('Script or Resource File \'%s\' does not exist.  Please repackage after locating and installing this file', $file['file']);
}

function check_get_author_info() {
	if (file_exists(CACTI_PATH_PKI . '/package.info')) {
		$info = parse_ini_file(CACTI_PATH_PKI . '/package.info', true);
		$info = $info['info'];

		return $info;
	} else {
		?>
		<script type='text/javascript'>
		var mixedReasonTitle = '<?php print __('Key Generation Required to Use Tool'); ?>';
		var mixedOnPage      = '<?php print __esc('Packaging Key Information Not Found'); ?>';

		sessionMessage = {
			message: '<?php print __('In order to use this Packaging Tool, you must first run the <b><i class="deviceUp">genkey.php</i></b> script in the cli directory.  Once that is complete, you will have a public and private key used to sign your packages.'); ?>',
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
	$db_file   = CACTI_PATH_PKI . '/package.db';
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

	if (is_writable(dirname($db_file))) {
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
		raise_message('package_nowrite', __('The Web Server must have write access to the \'%s\' directory', CACTI_PATH_PKI), MESSAGE_LEVEL_ERROR);
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

function get_package_contents($export_type, $export_item_id, $include_deps = true) {
	global $export_errors;

	$types = [
		'host_template',
		'graph_template',
		'data_query'
	];

	$graph_templates       = [];
	$queries               = [];
	$query_graph_templates = [];

	switch($export_type) {
		case 'host_template':
			$graph_templates = db_fetch_assoc_prepared('SELECT gt.id, gt.name
				FROM host_template_graph AS htg
				INNER JOIN graph_templates AS gt
				ON gt.id = htg.graph_template_id
				WHERE host_template_id = ?',
				[$export_item_id]);

			$queries = db_fetch_assoc_prepared('SELECT sq.*
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query AS sq
				ON sq.id = htsq.snmp_query_id
				WHERE host_template_id = ?',
				[$export_item_id]);

			break;
		case 'data_query':
			$queries = db_fetch_assoc_prepared('SELECT sq.*
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query AS sq
				ON sq.id = htsq.snmp_query_id
				WHERE host_template_id = ?',
				[$export_item_id]);

			$query_graph_templates = db_fetch_assoc_prepared('SELECT sqg.graph_template_id, sqg.name
				FROM host_template_snmp_query AS htsq
				INNER JOIN snmp_query_graph AS sqg
				ON htsq.snmp_query_id = sqg.snmp_query_id
				WHERE host_template_id = ?',
				[$export_item_id]);

			break;
		case 'graph_template':
			$graph_templates = db_fetch_assoc_prepared('SELECT *
				FROM graph_templates AS gt
				WHERE id = ?',
				[$export_item_id]);

			if (cacti_sizeof($graph_templates)) {
				$in = '';

				foreach ($graph_templates as $gt) {
					$in .= ($in != '' ? ',' : '') . $gt['id'];
				}

				$queries = db_fetch_assoc("SELECT sq.*
					FROM graph_templates AS gt
					INNER JOIN snmp_query_graph AS sqg
					ON gt.id = sqg.graph_template_id
					INNER JOIN snmp_query AS sq
					ON sq.id = sqg.snmp_query_id
					WHERE gt.id IN ($in)");
			}

			break;
	}

	// Determine what files are included
	$export_errors = 0;
	$xml_data      = get_item_xml($export_type, $export_item_id, $include_deps);
	$files         = [];

	if (!$export_errors) {
		$files = find_dependent_files($xml_data, true);

		/* search xml files for scripts */
		if (cacti_sizeof($files)) {
			foreach ($files as $file) {
				if (str_contains($file['file'], '.xml')) {
					$files = array_merge($files, find_dependent_files(file_get_contents($file['file']), $file));
				}
			}
		}
	} else {
		return __('Cacti Template has Errors.  Unable to parse entire template.');
	}

	/**
	 * When exporting Graph Templates, you have to check for data queries
	 * and process their XML files for additional scripts
	 */
	if ($export_type == 'graph_template' && cacti_sizeof($queries)) {
		foreach ($queries as $dq) {
			$xml_data = get_item_xml('data_query', $dq['id'], $include_deps);

			$nfiles = find_dependent_files($xml_data, true);

			/* search xml files for scripts */
			if (cacti_sizeof($nfiles)) {
				foreach ($nfiles as $file) {
					if (str_contains($file['file'], '.xml')) {
						$files = array_merge($files, find_dependent_files(file_get_contents($file['file']), $file));
					}
				}
			}
		}
	}

	$output = '<div class="flexContainer cactiTable" style="justify-content:space-around;">';

	if (cacti_sizeof($graph_templates)) {
		$output .= '<div class="flexChild" style="vetical-align:top;width:24%;padding:0px 5px;">';

		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Graph Templates') . '</div></div>';

		foreach ($graph_templates as $t) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $t['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($queries)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Data Queries') . '</div></div>';

		foreach ($queries as $q) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $q['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($query_graph_templates)) {
		$output .= '<div class="flexChild" style="50%;vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Data Query Graph Templates') . '</div></div>';

		foreach ($graph_templates as $t) {
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . $t['name'] . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($queries)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Resource Files') . '</div></div>';

		foreach ($queries as $q) {
			$file   = str_replace('<path_cacti>', CACTI_PATH_BASE, $q['xml_path']);
			$exists = file_exists($file);
			$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . html_escape(basename($file)) . ($exists ? '<i class="fa-solid fa-circle-check deviceUp"></i>' : '<i class="ti ti-cross deviceDown"></i>') . '</div></div>';
		}

		$output .= '</div>';
	}

	if (cacti_sizeof($files)) {
		$output .= '<div class="flexChild" style="vertical-align:top;width:24%;padding:0px 5px;">';
		$output .= '<div class="formHeader"><div class="formHeaderText">' . __('Script Files') . '</div></div>';

		$found = [];

		foreach ($files as $file) {
			if (array_search($file, $found, true) === false) {
				if (!str_contains($file['file'], '/resource/')) {
					$exists = file_exists($file['file']);
					$output .= '<div class="formRow"><div class="formColumnLeft nowrap">' . html_escape(basename($file['file'])) . ($exists ? '<i class="fa-solid fa-circle-check deviceUp"></i>' : '<i class="ti ti-cross deviceDown"></i>') . '</div></div>';
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
	if (file_exists(CACTI_PATH_PKI . '/package.key')) {
		return 'file://' . CACTI_PATH_PKI . '/package.key';
	} else {
		print 'FATAL: You must run genkey.php to generate your key first' . PHP_EOL;

		return false;
	}
}

function get_package_public_key() {
	if (file_exists(CACTI_PATH_PKI . '/package.pem')) {
		$key = openssl_pkey_get_public('file://' . CACTI_PATH_PKI . '/package.pem');

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

function find_dependent_files($xml_data, $raise_message = false) {
	$files = [];
	$data  = explode("\n", $xml_data);

	foreach ($data as $line) {
		if (str_contains($line, '<xml_path>')) {
			$line = str_replace('<xml_path>', '', $line);
			$line = str_replace('</xml_path>', '', $line);

			$files = process_paths($line, $files, $raise_message);
		} elseif (str_contains($line, '<script_path>')) {
			$line  = str_replace('<script_path>', '', $line);
			$line  = str_replace('</script_path>', '', $line);
			$files = process_paths($line, $files, $raise_message);
		} elseif (str_contains($line, '<input_string>')) {
			$line  = str_replace('<input_string>', '', $line);
			$line  = str_replace('</input_string>', '', $line);
			$line  = base64_decode($line, true);
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
			foreach ($paths['missing_paths'] as $p) {
				raise_message('missing_' . $p['file'], __('A Critical Template file \'%s\' is missing.  Please locate this file before packaging', $p['file']), MESSAGE_LEVEL_ERROR);
			}
		}
	}

	return $files;
}

/**
 * types include
 * xml          => location found in template xml <xml_path>
 * script       => location found in xml <input_string>
 * resource_xml => location found in resource xml file
 * @param mixed $input
 * @param mixed $type
 */
function find_paths($input, $type = 'cacti_xml') {
	$excluded_paths = [
		'/bin/',
		'/usr/bin/',
		'/usr/local/bin/'
	];

	$excluded_basenames = [
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
	];

	$paths  = [];
	$mpaths = [];

	$input = htmlspecialchars_decode($input);
	$parts = preg_split('/\s+/', $input);

	foreach ($parts as $part) {
		$opath = htmlspecialchars($part);
		$part  = str_replace('<path_cacti>', CACTI_PATH_BASE, $part);
		$part  = str_replace('|path_cacti|', CACTI_PATH_BASE, $part);
		$part  = str_replace('|path_php_binary|', '', $part);

		if (trim($part) == '') {
			continue;
		}

		$valid = true;

		if (file_exists($part)) {
			foreach ($excluded_paths as $path) {
				if (str_contains($part, $path)) {
					$valid = false;

					break;
				}
			}

			if ($valid) {
				foreach ($excluded_basenames as $binary) {
					if (str_contains($binary, basename($part))) {
						$valid = false;

						break;
					}
				}
			}

			if ($valid) {
				$paths[] = ['opath' => $opath, 'file' => $part];
			}
		} elseif (str_contains($part, '/') || str_contains($part, '\\')) {
			$mpaths[] = ['opath' => $opath, 'file' => $part];
		}
	}

	return ['paths' => $paths, 'missing_paths' => $mpaths];
}

function package_template(&$template, &$info, &$files, &$debug) {
	global $export_errors, $package_file;

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
	if (CACTI_SERVER_OS == 'unix') {
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
	$xml .= '     <name>' . $info['name'] . "</name>\n";

	if (isset($info['author'])) {
		$xml .= '     <author>' . $info['author'] . "</author>\n";
		$debug .= ' Author     : ' . $info['author'] . "\n";
	}

	if (isset($info['homepage'])) {
		$xml .= '     <homepage>' . $info['homepage'] . "</homepage>\n";
		$debug .= ' Homepage   : ' . $info['homepage'] . "\n";
	}

	if (isset($info['email'])) {
		$xml .= '     <email>' . $info['email'] . "</email>\n";
		$debug .= ' Email      : ' . $info['email'] . "\n";
	}

	if (isset($info['description'])) {
		$xml .= '     <description>' . $info['description'] . "</description>\n";
		$debug .= ' Description: ' . $info['description'] . "\n";
	}

	if (isset($info['class'])) {
		$xml .= '     <class>' . $info['class'] . "</class>\n";
		$debug .= ' Class: ' . $info['class'] . "\n";
	}

	if (isset($info['tags'])) {
		$xml .= '     <tags>' . $info['tags'] . "</tags>\n";
		$debug .= ' Tags: ' . $info['tags'] . "\n";
	}

	if (isset($info['installation'])) {
		$xml .= '     <installation>' . $info['installation'] . "</installation>\n";
		$debug .= ' Instructions: ' . $info['installation'] . "\n";
	}

	if (isset($info['version'])) {
		$xml .= '     <version>' . $info['version'] . "</version>\n";
		$debug .= ' Version    : ' . $info['version'] . "\n";
	}

	if (isset($info['copyright'])) {
		$xml .= '     <copyright>' . $info['copyright'] . "</copyright>\n";
		$debug .= ' Copyright  : ' . $info['copyright'] . "\n";
	}

	$xml .= "   </info>\n";

	$debug .= "Packaging Dependent files....\n";

	$debug .= ' Files Specified: ' . count($files) . "\n";

	/* calculate directories */
	$directories = [];

	if (cacti_sizeof($files)) {
		foreach ($files as $file) {
			$directories[dirname($file['file'])] = dirname($file['file']);
		}
	}

	$debug .= ' Directories extracted: ' . count($directories) . "\n";

	$xml .= "   <directories>\n";

	if (cacti_sizeof($directories)) {
		foreach ($directories as $dir) {
			$debug .= "   Adding Directory: $dir\n";
			$xml .= '       <directory>' . str_replace($my_base, '', $dir) . "</directory>\n";
		}
	}
	$xml .= "   </directories>\n";

	$files['template'] = ['file' => $xmlfile, 'type' => 'template'];

	$xml .= "   <files>\n";

	$dupfiles = [];

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
			$xml .= '           <name>' . basename($name) . "</name>\n";
		} else {
			$xml .= '           <name>' . str_replace($my_base, '', $name) . "</name>\n";
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
		$xml .= '           <filesignature>' . base64_encode($binary_signature) . "</filesignature>\n";
		$xml .= "       </file>\n";
	}

	$xml .= "   </files>\n";
	$xml .= '   <publickeyname>' . $info['author'] . "</publickeyname>\n";
	$xml .= '   <publickey>' . base64_encode($public_key) . "</publickey>\n";

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

	$xml .= '   <signature>' . $basesig . "</signature>\n</xml>";

	$name = get_item_name(get_request_var('export_type'), get_request_var('export_item_id'));

	$debug .= 'NOTE: Creating compressed template xml "' . clean_up_name($name) . ".xml.gz\"\n";

	$f = fopen("compress.zlib://$tmpdir/" . clean_up_name($name) . '.xml.gz','wb');
	fwrite($f, $xml, strlen($xml));
	fclose($f);

	$package_file = $tmpdir . '/' . clean_up_name($name) . '.xml.gz';

	return true;
}

function get_item_name($export_type, $export_id) {
	$name = 'Unknown';

	$name = match ($export_type) {
		'host_template' => db_fetch_cell_prepared('SELECT name
			FROM host_template
			WHERE id = ?', [$export_id]),
		'graph_template' => db_fetch_cell_prepared('SELECT name
			FROM graph_templates
			WHERE id = ?',
			[$export_id]),
		'data_query' => db_fetch_cell_prepared('SELECT name
			FROM snmp_query
			WHERE id = ?',
			[$export_id]),
		default => $name,
	};

	return $name;
}
