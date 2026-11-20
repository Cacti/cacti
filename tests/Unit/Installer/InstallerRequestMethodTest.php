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

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('installer rejects non-POST methods before bootstrap', function (string $method) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Installer/MethodProbe.php', [$method]);

	expect($result['status'])->toBe(405)
		->and($result['body'])->toBe(['error' => 'Method not allowed'])
		->and($result['bootstrapped'])->toBeFalse();
})->with(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
