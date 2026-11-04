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

function boostBackendCircuitLoadFunctions($root) {
	if (function_exists('boostBackendCircuitIsIsolated')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$functions = array(
		'boost_is_isolated_rrd_failure' => 'boostBackendCircuitIsIsolated',
		'boost_backend_failure_window_open' => 'boostBackendCircuitIsOpen',
	);

	foreach($functions as $original => $replacement) {
		$start = strpos($source, 'function ' . $original . '(');
		$end   = strpos($source, "\nfunction ", $start + 1);

		expect($start)->not->toBeFalse()
			->and($end)->not->toBeFalse();

		$function = substr($source, $start, $end - $start);
		$function = str_replace($original, $replacement, $function);
		eval($function);
	}
}

beforeEach(function () use ($root) {
	boostBackendCircuitLoadFunctions($root);
});

test('only destination-specific diagnostics are isolated from backend pressure', function () {
	expect(boostBackendCircuitIsIsolated('ERROR: not an RRD file'))->toBeTrue()
		->and(boostBackendCircuitIsIsolated('ERROR: illegal attempt to update'))->toBeTrue()
		->and(boostBackendCircuitIsIsolated('ERROR: unknown DS name value'))->toBeTrue()
		->and(boostBackendCircuitIsIsolated('ERROR: permission denied'))->toBeFalse()
		->and(boostBackendCircuitIsIsolated('ERROR: no such file or directory'))->toBeFalse();
});

test('the backend circuit bounds startup and post-progress failure windows', function () {
	expect(boostBackendCircuitIsOpen(0, 7, 7))->toBeFalse()
		->and(boostBackendCircuitIsOpen(0, 8, 8))->toBeTrue()
		->and(boostBackendCircuitIsOpen(1, 100, 31))->toBeFalse()
		->and(boostBackendCircuitIsOpen(1, 100, 32))->toBeTrue();
});
