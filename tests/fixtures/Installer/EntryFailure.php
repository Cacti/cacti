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

namespace InstallerEntryFailureCoverage;

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root      = dirname(__DIR__, 3);
$mode      = $argv[1] ?? '';
$temporary = null;

function __($text) {
	return $text;
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return false;
}

// Isolate only the entry-point guard, preserving its exact source and line numbers.
// The surrounding bootstrap and authentication are covered by the HTTP integration suite.
$blocks = [
	'vendor'   => ['include/global.php', '$vendor_autoload = CACTI_PATH_INCLUDE', "\nif (\$config['poller_id']"],
	'settings' => ['install/install.php', 'if (!prime_default_settings()) {', "\n// SAFETY CHECKS"],
];
$expected       = $mode === 'vendor' ? 'FATAL: The include/vendor directory is not populated.' : 'Unable to initialize installer settings.';
$expectedStatus = 500;

if ($mode === 'cli-option') {
	$expected       = 'ERROR: Invalid SNMP option format';
	$expectedStatus = false;
	\cacti_fixture_source_function($root . '/cli/install_cacti.php', 'set_install_multioption', __NAMESPACE__);
} elseif (isset($blocks[$mode])) {
	[$file, $start, $end] = $blocks[$mode];
	$source               = file_get_contents($root . '/' . $file);
	$offset               = strpos($source, $start);
	$finish               = strpos($source, $end, $offset);

	if ($offset === false || $finish === false) {
		throw new \RuntimeException('Entry guard boundaries changed');
	}
	$line      = substr_count(substr($source, 0, $offset), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-entry-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . substr($source, $offset, $finish - $offset));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/' . $file);

	if ($mode === 'settings') {
		\cacti_fixture_source_function($root . '/install/functions.php', 'prime_default_settings', __NAMESPACE__);
		$GLOBALS['settings'] = ['general' => ['fixture' => ['default' => 'value']]];
		$_SESSION            = [];
	} else {
		define('CACTI_PATH_INCLUDE', sys_get_temp_dir() . '/missing-vendor-' . bin2hex(random_bytes(8)));
		$config = ['is_web' => true];
		$ps     = '';
		$sp     = "\n";
	}
} else {
	throw new \RuntimeException('Unknown entry-point scenario');
}

ob_start();
register_shutdown_function(static function () use ($mode, $expected, $expectedStatus, $temporary) : void {
	$output = ob_get_clean();

	if (isset($temporary)) {
		unlink($temporary);
	}

	if (!str_contains($output, $expected) || str_contains($output, 'synthetic-private-value') || http_response_code() !== $expectedStatus) {
		fwrite(STDERR, 'Unexpected entry-point failure: ' . $output . PHP_EOL);
		exit(2);
	}
	print 'PASS: ' . $mode . PHP_EOL;
});

if ($mode === 'cli-option') {
	$options = [];
	set_install_multioption($options, 'SNMP', 'SNMP', 'synthetic-private-value', '');
} else {
	require $temporary;
}

throw new \RuntimeException('Entry point continued after failure');
