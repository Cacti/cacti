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

test('bulk walk parsing does not fall through to the version action', function () {
	$source = file_get_contents(__DIR__ . '/../../../../cli/change_device.php');

	expect($source)->toBeString();
	expect(preg_match(
		"/case '--bulk_walk':.*?\\bbreak;\\s*case '--version':/s",
		$source
	))->toBe(1);
});
