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

$globalSettings = file_get_contents(__DIR__ . '/../../../../include/global_settings.php');

test('remote agent timeout dropdown includes long WAN-safe values', function () use ($globalSettings) {
	expect($globalSettings)->not->toBeFalse();

	foreach (array(5, 10, 15, 20, 30, 60) as $timeout) {
		expect($globalSettings)->toContain($timeout . " => __('%d Seconds', " . $timeout . ')');
	}
});
