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
 * Regression tests for merged security PRs.
 * Source-scan tests verify that security fixes remain in place.
 *
 * PR #6971: rrdtool tune shell escaping in lib/rrd.php
 * PR #6973: graph_theme LFI prevention in lib/rrd.php
 * PR #6974: remote_agent effective_user and OID validation
 */

$rrdPath         = __DIR__ . '/../../lib/rrd.php';
$remoteAgentPath = __DIR__ . '/../../remote_agent.php';

// --- PR #6971: rrdtool_function_tune shell escaping ---

test('rrdtool_function_tune uses cacti_escapeshellcmd for rrdtool binary path', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toContain("cacti_escapeshellcmd(read_config_option('path_rrdtool'))");
});

test('rrdtool_function_tune uses cacti_escapeshellarg for data_source_path', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	// The tune command line must escape the data source path
	expect($contents)->toMatch('/cacti_escapeshellcmd\(.*path_rrdtool.*\)\s*\.\s*\'\s*tune\s*\'\s*\.\s*cacti_escapeshellarg\(\$data_source_path\)/');
});

test('rrdtool_function_tune uses cacti_escapeshellarg for --heartbeat flag', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/--heartbeat.*cacti_escapeshellarg\(\$data_source_name\s*\.\s*\':\'/');
});

test('rrdtool_function_tune uses cacti_escapeshellarg for --minimum flag', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/--minimum.*cacti_escapeshellarg\(\$data_source_name\s*\.\s*\':\'/');
});

test('rrdtool_function_tune uses cacti_escapeshellarg for --maximum flag', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/--maximum.*cacti_escapeshellarg\(\$data_source_name\s*\.\s*\':\'/');
});

test('rrdtool_function_tune uses cacti_escapeshellarg for --data-source-type flag', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/--data-source-type.*cacti_escapeshellarg\(\$data_source_name\s*\.\s*\':\'/');
});

test('rrdtool_function_tune uses cacti_escapeshellarg for --data-source-rename flag', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/--data-source-rename.*cacti_escapeshellarg\(\$data_source_name\s*\.\s*\':\'/');
});

test('rrdtool_function_tune does not pass raw data_source_path to popen', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	// Extract just the rrdtool_function_tune function body
	$start = strpos($contents, 'function rrdtool_function_tune(');
	if ($start === false) {
		$this->fail('function rrdtool_function_tune not found in source');
	}
	$nextFunc = strpos($contents, "\nfunction ", $start + 1);
	if ($nextFunc === false) {
		$this->fail('no function follows rrdtool_function_tune in source');
	}
	$tuneBody = substr($contents, $start, $nextFunc - $start);

	// popen should receive $rrd_cmd (pre-escaped), not raw $data_source_path
	expect($tuneBody)->toContain('popen($rrd_cmd,');
	expect($tuneBody)->not->toMatch('/popen\s*\([^)]*\$data_source_path/');
});

// --- PR #6973: graph_theme LFI prevention via basename() ---

test('rrd graph theme applies basename to prevent path traversal', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toContain("basename(\$graph_data_array['graph_theme'])");
});

test('rrd graph theme rejects dot-only basename values', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	// After basename(), the code must reject '.' and '..' to prevent traversal
	expect($contents)->toContain("\$theme === '.'");
	expect($contents)->toContain("\$theme === '..'");
});

// --- PR #6974: remote_agent effective_user and OID validation ---

test('remote_agent does not accept effective_user from request and uses session user', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	expect($contents)->not->toContain("gfrv('effective_user')");
	expect($contents)->not->toContain("grv('effective_user')");
	expect($contents)->toContain('$_SESSION[SESS_USER_ID] ?? 0');
});

test('remote_agent OID validation regex in get_snmp_data', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	// OID must match digits-and-dots only pattern: /^[0-9.]+$/
	expect(str_contains($contents, "preg_match('/^[0-9.]+$/', \$oid)"))->toBeTrue();
});

test('remote_agent validates OID as string before regex check', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	// Type check must precede regex to prevent type juggling
	expect($contents)->toContain('!is_string($oid)');
});

test('remote_agent OID validation exists in get_snmp_data_walk', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	// Both get_snmp_data and get_snmp_data_walk must validate OIDs
	$walkStart = strpos($contents, 'function get_snmp_data_walk()');
	if ($walkStart === false) {
		$this->fail('function get_snmp_data_walk not found in source');
	}
	$walkBody = substr($contents, $walkStart, 600);

	expect($walkBody)->toContain('!is_string($oid)');
	expect(str_contains($walkBody, "preg_match('/^[0-9.]+$/', \$oid)"))->toBeTrue();
});
