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
		'unlinks' => array(),
		'renames' => array(),
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

	return true;
}

function boostAtomicFailureUnlink($path) {
	$GLOBALS['boost_atomic_failure_state']['unlinks'][] = $path;

	return true;
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
		->and($GLOBALS['boost_atomic_failure_state']['renames'])->toBe(array());
});

test('a cache permission failure removes the temporary file without publishing it', function () {
	boostAtomicFailureReset(array('chmod' => false));

	expect(boostAtomicFailureWrite('/cache/final.png', 'payload'))->toBeFalse()
		->and($GLOBALS['boost_atomic_failure_state']['unlinks'])->toBe(array('/cache/.boost-test'))
		->and($GLOBALS['boost_atomic_failure_state']['renames'])->toBe(array());
});
