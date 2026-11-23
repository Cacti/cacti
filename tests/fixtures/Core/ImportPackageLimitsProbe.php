<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs the unchanged Package readers from lib/import.php in their own
 * process, with the log and the key checks answered here, and prints what
 * each reader returned for the Package file named in the scenario.
 */

namespace CactiImportPackageLimitsFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

function __($text) {
	return $text;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['log'][] = $facility . ': ' . $message;
}

function get_public_key() {
	return 'cacti key';
}

function is_cacti_public_key($key) {
	$GLOBALS['keys'][] = $key;

	return $key === 'cacti key';
}

function xml_to_array($xml) {
	return json_decode(json_encode($xml), true);
}

foreach (array('import_package_max_bytes', 'import_package_within_size_limit', 'import_package_get_details', 'import_package_get_public_key', 'import_read_package_data') as $function) {
	\cacti_fixture_source_function($root . '/lib/import.php', $function, __NAMESPACE__);
}

function import_package_limits($mode, $file, $limit = null) {
	$GLOBALS['log']  = array();
	$GLOBALS['keys'] = array();

	if ($mode == 'limit') {
		$result = array('max' => import_package_max_bytes(), 'within' => import_package_within_size_limit($file, $limit));
	} elseif ($mode == 'details') {
		$result = array('details' => import_package_get_details($file), 'key' => import_package_get_public_key($file));
	} else {
		$public_key = 'unset';

		$result = array('read' => import_read_package_data($file, $public_key), 'public_key' => $public_key);
	}

	return $result + array('log' => $GLOBALS['log'], 'keys' => $GLOBALS['keys']);
}

print json_encode(import_package_limits(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
