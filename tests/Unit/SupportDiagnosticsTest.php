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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/utility.php';

// support.php returns early under the test-bootstrap gate, so only its function
// declarations load here. That skips the auth + dispatch path a unit test cannot
// satisfy while leaving the diagnostics helpers callable.
require_once dirname(__DIR__, 2) . '/support.php';

test('show_tech_environment renders the environment sections without a database', function () {
	global $config;

	// Seed the binary paths so read_config_option() serves from the CLI option
	// cache instead of reaching the sentinel database, which throws under the
	// test bootstrap.
	$config[OPTIONS_CLI]['path_php_binary'] = PHP_BINARY;
	$config[OPTIONS_CLI]['path_rrdtool']    = '/cacti-tests/definitely-missing-rrdtool';
	$config[OPTIONS_CLI]['path_snmpget']    = PHP_BINARY;

	ob_start();
	show_tech_environment();
	$html = ob_get_clean();

	expect($html)->toContain(__('Required PHP Extensions'))
		->and($html)->toContain(__('Recommended PHP Extensions'))
		->and($html)->toContain(__('PHP Configuration'))
		->and($html)->toContain(__('Required Binaries'))
		->and($html)->toContain(__('Writable Directories'))
		->and($html)->toContain('file_uploads');
});

test('show_tech_environment flags a directory that is not writable', function () {
	global $config;

	$config[OPTIONS_CLI]['path_php_binary'] = PHP_BINARY;
	$config[OPTIONS_CLI]['path_rrdtool']    = PHP_BINARY;
	$config[OPTIONS_CLI]['path_snmpget']    = PHP_BINARY;

	// CACTI_PATH_RESOURCE is one of the directories the report inspects and is
	// not a required path, so making it read-only exercises the not-writable
	// warning branch. Restore the mode in finally{} so a failure never leaves
	// the checkout in a read-only state.
	$dir  = CACTI_PATH_RESOURCE;
	$mode = fileperms($dir) & 0777;

	expect(chmod($dir, 0555))->toBeTrue();

	try {
		clearstatcache(true, $dir);

		ob_start();
		show_tech_environment();
		$html = ob_get_clean();

		expect($html)->toContain(__('Not writable'));
	} finally {
		chmod($dir, $mode);
		clearstatcache(true, $dir);
	}
})->skip(function_exists('posix_getuid') && posix_getuid() === 0, 'root bypasses directory write permissions');

/*
 * The redacted report is assembled inside show_tech_summary(), which runs a long
 * sequence of database queries before reaching the report block. The test
 * bootstrap's sentinel connection throws on the first query, so the function
 * cannot be called in-process. Extract the assembly block (the security-relevant
 * added code) and exercise it directly with controlled inputs, matching the
 * source-extraction convention in Issue7070PercentileContractTest.
 */
function support_diag_build_report(array $vars): string {
	$path = dirname(__DIR__, 2) . '/support.php';
	$src  = file_get_contents($path);

	if ($src === false) {
		throw new RuntimeException("support_diag_build_report(): unable to read $path");
	}

	// The block runs from the first assignment to the last $report line.
	$start = strpos($src, '$snmp_line     = $snmp_installed');

	if ($start === false) {
		throw new RuntimeException('support_diag_build_report(): start marker not found in support.php; has the report block moved?');
	}

	$end = strpos($src, "\$report .= '- ' . __('RSA Fingerprint')");

	if ($end === false) {
		throw new RuntimeException('support_diag_build_report(): end marker not found in support.php; has the report block moved?');
	}

	$end = strpos($src, "\n", $end);

	if ($end === false) {
		throw new RuntimeException('support_diag_build_report(): no newline after end marker in support.php');
	}

	$block = substr($src, $start, $end - $start);

	extract($vars);

	eval($block);

	return $report;
}

test('diagnostics report masks the RSA fingerprint and hides infrastructure', function () {
	global $config;

	$config[OPTIONS_CLI]['poller_type']     = '2';
	$config[OPTIONS_CLI]['rsa_fingerprint'] = 'abcdef0123456789deadbeefcafebabe';
	$config[OPTIONS_CLI]['poller_interval'] = '60';
	$config[OPTIONS_CLI]['stats_poller']    = 'Time:1.0';

	$report = support_diag_build_report([
		'snmp_version'    => "NET-SNMP 5.9\nextra line",
		'snmp_installed'  => true,
		'redact'          => true,
		'poller_options'  => [1 => 'cactid', 2 => 'spine'],
		'spine_version'   => 'Spine 1.2.99',
		'database'        => 'MariaDB',
		'version'         => '10.11',
		'rrdtool_release' => '1.8.0',
		'total_memory'    => 16,
		'_SERVER'         => ['SERVER_SOFTWARE' => 'nginx'],
	]);

	// Only the first 8 fingerprint characters survive, tagged as masked.
	expect($report)->toContain('abcdef01')
		->and($report)->toContain(__('masked'))
		->and($report)->not->toContain('deadbeefcafebabe');

	// The spine branch replaces the poller label with the spine version.
	expect($report)->toContain('Spine 1.2.99');

	// The single SNMP line is kept; the trailing line is dropped.
	expect($report)->toContain('NET-SNMP 5.9')
		->and($report)->not->toContain('extra line');
});

test('diagnostics report reports N/A when RSA and memory are absent', function () {
	global $config;

	$config[OPTIONS_CLI]['poller_type']     = '1';
	$config[OPTIONS_CLI]['rsa_fingerprint'] = '';
	$config[OPTIONS_CLI]['poller_interval'] = '60';
	$config[OPTIONS_CLI]['stats_poller']    = '';

	$report = support_diag_build_report([
		'snmp_version'    => 'NET-SNMP 5.9',
		'snmp_installed'  => true,
		'redact'          => false,
		'poller_options'  => [1 => 'cactid'],
		'spine_version'   => 'Unknown',
		'database'        => 'MySQL',
		'version'         => '8.0',
		'rrdtool_release' => '1.8.0',
		'total_memory'    => 0,
		'_SERVER'         => [],
	]);

	// Empty fingerprint and zero memory both collapse to the N/A label.
	expect(substr_count($report, __('N/A')))->toBeGreaterThanOrEqual(2)
		->and($report)->not->toContain(__('masked'));

	// Missing SERVER_SOFTWARE falls back to Unknown, not a leaked value.
	expect($report)->toContain(__('Unknown'));
});

test('tech_env_status renders a success span with a check icon', function () {
	$html = tech_env_status(DB_STATUS_SUCCESS, 'Installed');

	expect($html)->toContain("class='deviceUp'")
		->and($html)->toContain('fa-check-circle')
		->and($html)->toContain('Installed');
});

test('tech_env_status renders an error span with a times icon', function () {
	$html = tech_env_status(DB_STATUS_ERROR, 'Missing');

	expect($html)->toContain("class='deviceDown'")
		->and($html)->toContain('fa-times-circle')
		->and($html)->toContain('Missing');
});

test('tech_env_status falls back to a warning span for any other status', function () {
	$html = tech_env_status(DB_STATUS_WARNING, 'Not installed');

	expect($html)->toContain("class='deviceRecovering'")
		->and($html)->toContain('fa-exclamation-triangle')
		->and($html)->toContain('Not installed');
});
