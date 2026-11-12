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

test('discovery excludes IPs and CIDRs while advancing and preserving bounded batches', function () {
	ob_start();

	try {
		require dirname(__DIR__, 3) . '/fixtures/Core/IpRangeDiscovery.php';
		$output = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe(['membership' => 113, 'discovery' => 9]);
});
