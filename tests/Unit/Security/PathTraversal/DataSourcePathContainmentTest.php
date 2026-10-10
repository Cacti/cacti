<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for GHSA-9qff-xccr-rrc5.
 *
 * data_source_path is stored without path validation and used verbatim as the
 * RRD filename. data_source_path_within_rra() is the containment applied where
 * every consumer resolves the path, keeping the write inside the RRA directory,
 * including against a symlink pivot below an otherwise-contained ancestor.
 *
 * @group regression
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';

if (!defined('CACTI_PATH_RRA')) {
	define('CACTI_PATH_RRA', sys_get_temp_dir() . '/cacti_rra_' . bin2hex(random_bytes(6)));
}

// realpath()-based checks below require CACTI_PATH_RRA to be a real, existing
// directory. Another test file loaded earlier in the same process (e.g.
// RrdProxyProtocolTest.php) may already have defined the constant to a purely
// illustrative, non-existent path, so make sure it actually exists on disk
// here regardless of who defined it, and skip the fixture-dependent
// assertions below if this process cannot create it (e.g. no permission).
$rra_ready = is_dir(CACTI_PATH_RRA . '/1') || @mkdir(CACTI_PATH_RRA . '/1', 0755, true);

if ($rra_ready) {
	file_put_contents(CACTI_PATH_RRA . '/host_ds.rrd', 'x');
	file_put_contents(CACTI_PATH_RRA . '/1/host_ds.rrd', 'x');
	$rra_ready = realpath(CACTI_PATH_RRA) !== false;
}

function data_source_path_test_base() : string {
	return (string) realpath(CACTI_PATH_RRA);
}

test('a file directly under the RRA directory is contained', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	expect(data_source_path_within_rra(data_source_path_test_base() . '/host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/1/host_ds.rrd'))->toBeTrue();
});

test('a not-yet-created file under the RRA directory is still contained', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	expect(data_source_path_within_rra(data_source_path_test_base() . '/1/new_ds.rrd'))->toBeTrue();
});

test('redundant slashes from a trailing-slash path_rra are contained, not an escape', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	// A path_rra saved with a trailing slash makes the '<path_rra>/' -> rra . '/'
	// expansion produce a doubled slash (rra//0/x.rrd); the file is still inside
	// the RRA directory and must not be reported as escaping it.
	expect(data_source_path_within_rra(data_source_path_test_base() . '//host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra(data_source_path_test_base() . '//1/host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/1//new_ds.rrd'))->toBeTrue();
});

test('a CACTI_PATH_RRA defined with a trailing slash still contains its files', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	if (!function_exists('shell_exec') || !defined('PHP_BINARY') || PHP_BINARY === '') {
		$this->markTestSkipped('shell_exec()/PHP_BINARY unavailable, cannot exercise an isolated process');
	}

	// CACTI_PATH_RRA is an immutable constant, already defined in this process
	// without a trailing slash (and realpath() would strip one anyway), so the
	// trailing-slash form - the actual trigger, where the base needs rtrim() and
	// not just the target - can only be exercised in a fresh process that
	// defines the constant that way.
	$rra       = data_source_path_test_base();
	$functions = dirname(__DIR__, 4) . '/lib/functions.php';
	$autoload  = dirname(__DIR__, 4) . '/vendor/autoload.php';

	$code = sprintf(
		'<?php $a = %s; if (is_file($a)) { require $a; } define(%s, %s); require %s; ' .
		'echo (data_source_path_within_rra(%s) === true ' .
		'&& data_source_path_within_rra(%s) === true ' .
		'&& data_source_path_within_rra(%s) === false) ? "PASS" : "FAIL";',
		var_export($autoload, true),
		var_export('CACTI_PATH_RRA', true),
		var_export($rra . '/', true),
		var_export($functions, true),
		var_export($rra . '/1/host_ds.rrd', true),
		var_export($rra . '//1/host_ds.rrd', true),
		var_export($rra . '/../resource/x.rrd', true)
	);

	$script = tempnam(sys_get_temp_dir(), 'cacti_rra_trailing_');
	file_put_contents($script, $code);

	try {
		$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
	} finally {
		@unlink($script);
	}

	expect(trim((string) $output))->toBe('PASS');
});

test('an absolute path elsewhere is rejected', function () : void {
	expect(data_source_path_within_rra('/var/www/html/cacti/resource/x.php'))->toBeFalse()
		->and(data_source_path_within_rra('/tmp/x.rrd'))->toBeFalse();
});

test('traversal out of the RRA directory is rejected', function () : void {
	expect(data_source_path_within_rra(data_source_path_test_base() . '/../resource/x.rrd'))->toBeFalse()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/a/../../etc/x'))->toBeFalse();
});

test('traversal is still rejected when wrapped in redundant slashes', function () : void {
	expect(data_source_path_within_rra(data_source_path_test_base() . '//..//resource/x.rrd'))->toBeFalse()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/a//..//../etc/x'))->toBeFalse();
});

test('a relative path or a lookalike prefix is rejected', function () : void {
	expect(data_source_path_within_rra('1/host_ds.rrd'))->toBeFalse()
		->and(data_source_path_within_rra(data_source_path_test_base() . '_evil/x.rrd'))->toBeFalse();
});

test('empty and null-byte paths are rejected', function () : void {
	expect(data_source_path_within_rra(''))->toBeFalse()
		->and(data_source_path_within_rra("/tmp/x\0.rrd"))->toBeFalse();
});

test('a symlink pivot below the RRA directory is rejected even for a not-yet-created file', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	$outside = sys_get_temp_dir() . '/cacti_rra_outside_' . bin2hex(random_bytes(6));
	mkdir($outside, 0755, true);

	$link = data_source_path_test_base() . '/linked';

	if (!@symlink($outside, $link)) {
		$this->markTestSkipped('symlink() is not available in this environment');
	}

	try {
		expect(data_source_path_within_rra($link . '/escaped.rrd'))->toBeFalse();
	} finally {
		unlink($link);
		rmdir($outside);
	}
});
