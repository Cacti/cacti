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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/xml.php';

$rrdtool = trim(shell_exec('which rrdtool 2>/dev/null') ?? '');

// --- Integration: real rrdtool xport with a test RRD file ---

test('rrdtool xport produces valid parseable output', function () use ($rrdtool) {
	$rrd_base = tempnam(sys_get_temp_dir(), 'cacti_test_');
	$rrd      = $rrd_base . '.rrd';
	unlink($rrd_base);
	$now = (int) (time() / 300) * 300; // align to 5-min boundary

	$safe_rrdtool = escapeshellarg($rrdtool);
	$safe_rrd     = escapeshellarg($rrd);

	// create a simple RRD with traffic_in and traffic_out
	shell_exec("$safe_rrdtool create $safe_rrd --step 300 " .
		"DS:traffic_in:GAUGE:600:0:U " .
		"DS:traffic_out:GAUGE:600:0:U " .
		"RRA:AVERAGE:0.5:1:288 2>&1");

	// insert a few data points
	for ($i = 3; $i >= 0; $i--) {
		$ts = $now - ($i * 300);
		shell_exec("$safe_rrdtool update $safe_rrd $ts:" . ($i * 1000 + 100) . ':' . ($i * 500 + 50) . ' 2>&1');
	}

	// run xport and parse
	$start = $now - 1200;
	$end   = $now;
	$cmd   = "$safe_rrdtool xport --start $start --end $end " .
		"DEF:a=$safe_rrd:traffic_in:AVERAGE " .
		"DEF:b=$safe_rrd:traffic_out:AVERAGE " .
		"XPORT:a:\"traffic_in\" " .
		"XPORT:b:\"traffic_out\" 2>&1";

	$output = shell_exec($cmd);
	$result = rrdxport2array($output);

	expect($result)->toBeArray()
		->and(isset($result['meta']))->toBeTrue()
		->and($result['meta']['columns'])->toBe('2')
		->and($result['meta']['legend']['col1'])->toBe('traffic_in')
		->and($result['meta']['legend']['col2'])->toBe('traffic_out')
		->and(isset($result['data']))->toBeTrue()
		->and(count($result['data']))->toBeGreaterThan(0);

	unlink($rrd);
})->skip(empty($rrdtool), 'rrdtool not installed');

// --- Integration: xport on nonexistent RRD produces error XML ---

test('rrdtool xport on missing RRD produces error parseable by rrdxport2array', function () use ($rrdtool) {
	$fake         = '/tmp/cacti_test_nonexistent_' . uniqid() . '.rrd';
	$safe_rrdtool = escapeshellarg($rrdtool);
	$safe_fake    = escapeshellarg($fake);
	$cmd          = "$safe_rrdtool xport --start 1700000000 --end 1700003600 " .
		"DEF:a=$safe_fake:ds0:AVERAGE XPORT:a:\"ds0\" 2>&1";

	$output = shell_exec($cmd);
	$result = rrdxport2array($output ?? '');

	// error output should NOT produce a valid meta structure
	expect(isset($result['meta']['start']))->toBeFalse();
})->skip(empty($rrdtool), 'rrdtool not installed');

// --- Integration: xport with invalid DS name produces error ---

test('rrdtool xport with invalid DS name produces no valid meta', function () use ($rrdtool) {
	$rrd_base = tempnam(sys_get_temp_dir(), 'cacti_test_');
	$rrd      = $rrd_base . '.rrd';
	unlink($rrd_base);
	$now = (int) (time() / 300) * 300;

	$safe_rrdtool = escapeshellarg($rrdtool);
	$safe_rrd     = escapeshellarg($rrd);

	shell_exec("$safe_rrdtool create $safe_rrd --step 300 " .
		"DS:traffic_in:GAUGE:600:0:U " .
		"RRA:AVERAGE:0.5:1:288 2>&1");

	shell_exec("$safe_rrdtool update $safe_rrd $now:100 2>&1");

	// reference a DS that doesn't exist
	$cmd = "$safe_rrdtool xport --start " . ($now - 300) . " --end $now " .
		"DEF:a=$safe_rrd:bogus_ds:AVERAGE XPORT:a:\"bogus\" 2>&1";

	$output = shell_exec($cmd);
	$result = rrdxport2array($output ?? '');

	expect(isset($result['meta']['start']))->toBeFalse();

	unlink($rrd);
})->skip(empty($rrdtool), 'rrdtool not installed');

// --- Integration: full pipeline — valid xport passes guard, error fails guard ---

test('full pipeline: valid xport passes export guard', function () use ($rrdtool) {
	$rrd_base = tempnam(sys_get_temp_dir(), 'cacti_test_');
	$rrd      = $rrd_base . '.rrd';
	unlink($rrd_base);
	$now = (int) (time() / 300) * 300;

	$safe_rrdtool = escapeshellarg($rrdtool);
	$safe_rrd     = escapeshellarg($rrd);

	shell_exec("$safe_rrdtool create $safe_rrd --step 300 " .
		"DS:traffic_in:GAUGE:600:0:U " .
		"RRA:AVERAGE:0.5:1:288 2>&1");

	shell_exec("$safe_rrdtool update $safe_rrd $now:42 2>&1");

	$cmd = "$safe_rrdtool xport --start " . ($now - 300) . " --end $now " .
		"DEF:a=$safe_rrd:traffic_in:AVERAGE XPORT:a:\"traffic_in\" 2>&1";

	$output      = shell_exec($cmd);
	$xport_array = rrdxport2array($output);

	// guard check mirrors graph_xport.php early-exit condition
	$should_fail = !is_array($xport_array) || !isset($xport_array['meta']['start']);

	expect($should_fail)->toBeFalse();

	unlink($rrd);
})->skip(empty($rrdtool), 'rrdtool not installed');

test('full pipeline: error xport triggers export guard', function () use ($rrdtool) {
	$safe_rrdtool = escapeshellarg($rrdtool);
	$safe_fake    = escapeshellarg('/tmp/nonexistent.rrd');
	$cmd          = "$safe_rrdtool xport --start 1700000000 --end 1700003600 " .
		"DEF:a=$safe_fake:ds0:AVERAGE XPORT:a:\"ds0\" 2>&1";

	$output      = shell_exec($cmd);
	$xport_array = rrdxport2array($output ?? '');

	$should_fail = !is_array($xport_array) || !isset($xport_array['meta']['start']);

	expect($should_fail)->toBeTrue();
})->skip(empty($rrdtool), 'rrdtool not installed');
