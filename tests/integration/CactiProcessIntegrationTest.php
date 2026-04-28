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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/CactiProcess.php';

test('runStreaming delivers each line of seq 1..5 to the callback', function () {
	if (!is_executable('/usr/bin/seq')) {
		$this->markTestSkipped('/usr/bin/seq not available on this host');
	}

	$lines = [];
	$exit  = CactiProcess::runStreaming(
		['/usr/bin/seq', '1', '5'],
		['timeout' => 5],
		static function ($line) use (&$lines) {
			$lines[] = $line;
		}
	);

	expect($exit)->toBe(0)
		->and($lines)->toBe(['1', '2', '3', '4', '5']);
});
