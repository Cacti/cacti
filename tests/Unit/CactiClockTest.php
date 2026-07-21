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

test('CactiClock exposes a DI-friendly Symfony clock wrapper', function () {
	require_once __DIR__ . '/../../include/vendor/autoload.php';
	require_once __DIR__ . '/../../lib/CactiClock.php';

	$clock = new CactiClock();

	expect($clock->now())->toBeInstanceOf(DateTimeImmutable::class);
	expect($clock->time())->toBeInt();
	expect(CactiClock::currentTime())->toBeInstanceOf(DateTimeImmutable::class);
	expect(CactiClock::unixTime())->toBeInt();
});
