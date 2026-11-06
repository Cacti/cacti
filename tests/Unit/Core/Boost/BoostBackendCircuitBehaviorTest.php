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

require_once dirname(__DIR__, 4) . '/lib/boost.php';

test('only destination-specific diagnostics are isolated from backend pressure', function () {
	expect(boost_is_isolated_rrd_failure('ERROR: not an RRD file'))->toBeTrue()
		->and(boost_is_isolated_rrd_failure('ERROR: illegal attempt to update'))->toBeTrue()
		->and(boost_is_isolated_rrd_failure('ERROR: unknown DS name value'))->toBeTrue()
		->and(boost_is_isolated_rrd_failure('ERROR: permission denied'))->toBeFalse()
		->and(boost_is_isolated_rrd_failure('ERROR: no such file or directory'))->toBeFalse();
});

test('the backend circuit bounds startup and post-progress failure windows', function () {
	expect(boost_backend_failure_window_open(0, 7, 7))->toBeFalse()
		->and(boost_backend_failure_window_open(0, 8, 8))->toBeTrue()
		->and(boost_backend_failure_window_open(1, 100, 31))->toBeFalse()
		->and(boost_backend_failure_window_open(1, 100, 32))->toBeTrue();
});
