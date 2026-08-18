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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

it('returns false without throwing when MySQL is unreachable', function () {
	// db_connect_real() short-circuits and returns false when the
	// pdo_mysql extension is not loaded (PDO::MYSQL_ATTR_FOUND_ROWS is
	// undefined), which would let this test pass without exercising the
	// retry path. Skip explicitly so green output reflects real coverage.
	if (!extension_loaded('pdo_mysql')) {
		test()->markTestSkipped('pdo_mysql extension not loaded; retry path cannot be exercised.');
	}

	// 127.0.0.1:1 is reserved (tcpmux) and effectively guaranteed closed
	// on dev hosts. The function loops $retries+1 times and applies a 2s
	// PDO::ATTR_TIMEOUT internally; with retries=1 the worst-case wall
	// time is ~4s for a refused-connection failure.
	$start = microtime(true);

	$result = db_connect_real(
		'127.0.0.1',
		'invalid_user',
		'invalid_pass',
		'invalid_db',
		'mysql',
		1,
		1
	);

	$elapsed = microtime(true) - $start;

	if ($elapsed > 10) {
		test()->markTestSkipped(
			sprintf('db_connect_real took %.1fs to fail; environment may be '
				. 'rerouting 127.0.0.1:1.', $elapsed)
		);
	}

	expect($result)->toBe(false);
});
