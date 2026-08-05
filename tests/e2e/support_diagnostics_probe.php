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
*/

/*
 * Runs inside the Cacti web container against a real database. Verifies the
 * Copy Diagnostics report (support.php, #7349) is safe to paste into a public
 * issue: the RSA fingerprint is truncated and the live database host and
 * password never appear. The report block is extracted from support.php and
 * evaluated with the container's real settings, so a regression that widens the
 * report to include an infrastructure value fails here.
 */

chdir(dirname(__DIR__, 2));

require_once __DIR__ . '/../../include/global.php';
require_once CACTI_PATH_LIBRARY . '/functions.php';

function support_diag_probe_assert(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}

function support_diag_probe_report(): string {
	global $snmp_version, $poller_options, $spine_version, $database, $version,
		$rrdtool_release, $total_memory;

	$snmp_version    = shell_exec('snmpget -V 2>&1') ?: 'NET-SNMP Unknown';
	$poller_options  = [1 => 'cactid', 2 => 'spine'];
	$spine_version   = 'Unknown';
	$database        = db_fetch_cell('SELECT VERSION()') ?: 'Unknown';
	$version         = '';
	$rrdtool_release = get_installed_rrdtool_version() ?: 'Unknown';
	$total_memory    = 0;

	// $snmp_version above is raw snmpget output, and the shareable report is the
	// redacted one, so drive the two branches the block reads.
	$snmp_installed  = true;
	$redact          = true;

	$path = dirname(__DIR__, 2) . '/support.php';
	$src  = file_get_contents($path);

	support_diag_probe_assert($src !== false, "unable to read $path");

	// support.php runs an authenticated dispatch on include, so extract the
	// helper the redacted branch calls. eval() runs only Cacti's own source.
	support_diag_probe_assert(
		preg_match('/^function support_redact\(.*?^\}/sm', $src, $m) === 1,
		'support_redact() not found in support.php'
	);

	eval($m[0]);

	// Anchor on the assignment target rather than its right-hand side: the SNMP
	// branch has already changed shape once, and a literal marker stops matching
	// without saying so.
	support_diag_probe_assert(
		preg_match('/^\s*\$snmp_line\s*=/m', $src, $m, PREG_OFFSET_CAPTURE) === 1,
		'start marker not found in support.php; has the report block moved?'
	);

	$start = $m[0][1];

	support_diag_probe_assert(
		preg_match("/\\\$report\s*\.=\s*'- '\s*\.\s*__\('RSA Fingerprint'\)/", $src, $m, PREG_OFFSET_CAPTURE) === 1,
		'end marker not found in support.php; has the report block moved?'
	);

	$end = strpos($src, "\n", $m[0][1]);

	support_diag_probe_assert($end !== false, 'no newline after end marker in support.php');

	$block = substr($src, $start, $end - $start);

	eval($block);

	return $report;
}

$report = support_diag_probe_report();

support_diag_probe_assert($report !== '', 'diagnostics report must not be empty');

$rsa = read_config_option('rsa_fingerprint');

if ($rsa != '') {
	support_diag_probe_assert(
		strpos($report, $rsa) === false && strpos($report, substr($rsa, 0, 8)) !== false,
		'RSA fingerprint must be truncated, not printed in full'
	);
}

global $database_hostname, $database_password;

if (!empty($database_hostname) && $database_hostname != 'localhost' && $database_hostname != '127.0.0.1') {
	support_diag_probe_assert(
		strpos($report, $database_hostname) === false,
		'diagnostics report must not contain the database hostname'
	);
}

if (!empty($database_password)) {
	support_diag_probe_assert(
		strpos($report, $database_password) === false,
		'diagnostics report must not contain the database password'
	);
}

print "PASS support diagnostics docker probe\n";
