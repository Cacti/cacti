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

namespace CactiQueryHostPartitionsFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';

const SNMP_POLLER = 0;

$GLOBALS['query_host_partitions_sau']   = $argv[2];
$GLOBALS['query_host_partitions_value'] = $argv[3];

function db_fetch_cell_prepared($sql, $params = array()) {
	return $GLOBALS['query_host_partitions_sau'];
}

function cacti_snmp_get($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port, $timeout, $retries, $environ) {
	return $GLOBALS['query_host_partitions_value'];
}

$source = dirname(__DIR__, 3) . '/scripts/query_host_partitions.php';
$code   = file_get_contents($source);

// Keep original line numbers intact so RequestCoverage maps Xdebug hits back to scripts/query_host_partitions.php.
$code = preg_replace('/^<\?php\b/', '<?php namespace ' . __NAMESPACE__ . ';', $code, 1);
$code = preg_replace('/^include\(dirname\(__FILE__\)\s*\.\s*\'\/\.\.\/(?:include\/cli_check|lib\/snmp)\.php\'\);$/m', '', $code);

$temporary = tempnam(sys_get_temp_dir(), 'cacti-qhp-');
file_put_contents($temporary, $code);
$resolved = realpath($temporary);
$GLOBALS['cacti_fixture_source_map'][$resolved] = realpath($source);

$_SERVER['argv'] = array('query_host_partitions.php', '127.0.0.1', '1', '2:161:500:1:10:public', 'get', $argv[1], '1');

ob_start();
try {
	require $resolved;
} finally {
	$output = ob_get_clean();
	unlink($temporary);
}

print json_encode(array('output' => $output), JSON_THROW_ON_ERROR);
