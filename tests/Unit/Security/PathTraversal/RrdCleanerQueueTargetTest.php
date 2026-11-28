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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('proxy maintenance only passes on plain relative names', function () {
	$proxy = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RrdCleanerProxyQueue.php', []);

	expect($proxy['commands'])->toBe(['unlink plain.rrd', 'archive sub/with space.rrd'])
		->and($proxy['skipped'])->toBe(5)
		->and($proxy['dequeued'])->toBe(7)
		->and($proxy['removed'])->toBe([1, 2]);

	foreach ($proxy['logs'] as $line) {
		expect(preg_match('/[\x00-\x1f]/', $line))->toBe(0);
	}
});

test('local maintenance accepts only paths inside the configured RRA directory', function () {
	$allowed = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RrdCleanerLocalSource.php', []);

	expect($allowed)->toBe([true, true, false, false, false, false]);
});
