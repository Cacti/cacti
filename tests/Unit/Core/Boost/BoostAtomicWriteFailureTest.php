<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

function boostAtomicFailureReset(array $overrides = array()) {
	$GLOBALS['boost_atomic_failure_state'] = array_merge(array(
		'flush'   => true,
		'chmod'   => true,
		'rename'  => true,
		'unlinks' => array(),
		'renames' => array(),
		'logs'    => array(),
	), $overrides);
}

function boostAtomicFailureTempnam($directory, $prefix) {
	return $directory . '/' . $prefix . 'test';
}

function boostAtomicFailureFopen($path, $mode) {
	return new stdClass();
}

function boostAtomicFailureFwrite($stream, $data) {
	return strlen($data);
}

function boostAtomicFailureFflush($stream) {
	return $GLOBALS['boost_atomic_failure_state']['flush'];
}

function boostAtomicFailureFclose($stream) {
	return true;
}

function boostAtomicFailureChmod($path, $mode) {
	return $GLOBALS['boost_atomic_failure_state']['chmod'];
}

function boostAtomicFailureRename($source, $destination) {
	$GLOBALS['boost_atomic_failure_state']['renames'][] = array($source, $destination);

	return $GLOBALS['boost_atomic_failure_state']['rename'];
}

function boostAtomicFailureUnlink($path) {
	$GLOBALS['boost_atomic_failure_state']['unlinks'][] = $path;

	return true;
}

function boostAtomicFailureLog($message, $output = false, $facility = '') {
	$GLOBALS['boost_atomic_failure_state']['logs'][] = array($message, $facility);
}

function boostAtomicFailureLoad($root) {
	if (function_exists('boostAtomicFailureWrite')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_atomic_write_cache(');
	$end    = strpos($source, "\nfunction ", $start + 1);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_atomic_write_cache',
		'tempnam',
		'fopen',
		'fwrite',
		'fflush',
		'fclose',
		'chmod',
		'rename',
		'unlink',
		'cacti_log',
	), array(
		'boostAtomicFailureWrite',
		'boostAtomicFailureTempnam',
		'boostAtomicFailureFopen',
		'boostAtomicFailureFwrite',
		'boostAtomicFailureFflush',
		'boostAtomicFailureFclose',
		'boostAtomicFailureChmod',
		'boostAtomicFailureRename',
		'boostAtomicFailureUnlink',
		'boostAtomicFailureLog',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boostAtomicFailureLoad($root);
	boostAtomicFailureReset();
});

test('a cache flush failure removes the temporary file without publishing it', function () {
	boostAtomicFailureReset(array('flush' => false));

	expect(boostAtomicFailureWrite('/cache/final.png', 'payload'))->toBeFalse()
		->and($GLOBALS['boost_atomic_failure_state']['unlinks'])->toBe(array('/cache/.boost-test'))
		->and($GLOBALS['boost_atomic_failure_state']['renames'])->toBe(array())
		->and($GLOBALS['boost_atomic_failure_state']['logs'][0][0])->toContain('could not flush');
});

test('a cache permission failure logs a warning and publishes the stricter temporary mode', function () {
	boostAtomicFailureReset(array('chmod' => false));

	expect(boostAtomicFailureWrite('/cache/final.png', 'payload'))->toBeTrue()
		->and($GLOBALS['boost_atomic_failure_state']['unlinks'])->toBe(array())
		->and($GLOBALS['boost_atomic_failure_state']['renames'])->toBe(array(array('/cache/.boost-test', '/cache/final.png')))
		->and($GLOBALS['boost_atomic_failure_state']['logs'][0][0])->toContain('existing stricter mode');
});

test('a cache publication failure removes the temporary file and logs the error', function () {
	boostAtomicFailureReset(array('rename' => false));

	expect(boostAtomicFailureWrite('/cache/final.png', 'payload'))->toBeFalse()
		->and($GLOBALS['boost_atomic_failure_state']['unlinks'])->toBe(array('/cache/.boost-test'))
		->and($GLOBALS['boost_atomic_failure_state']['logs'][0][0])->toContain('could not publish');
});
