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

test('device repository preserves reads, writes and failure contracts through the real database helpers', function () {
	ob_start();

	try {
		require dirname(__DIR__, 2) . '/fixtures/Core/DeviceRepositoryContracts.php';
		$output = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe(['passed' => 40]);
});
