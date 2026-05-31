<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
