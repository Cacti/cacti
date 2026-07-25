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
	require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
	require_once dirname(__DIR__, 2) . '/lib/CactiClock.php';

	$clock = new CactiClock();

	expect($clock->now())->toBeInstanceOf(DateTimeImmutable::class);
	expect($clock->time())->toBeInt();
	expect(CactiClock::currentTime())->toBeInstanceOf(DateTimeImmutable::class);
	expect(CactiClock::unixTime())->toBeInt();
});

test('CactiClock uses an injected clock instead of the default', function () {
	require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
	require_once dirname(__DIR__, 2) . '/lib/CactiClock.php';

	$fixed = new DateTimeImmutable('2020-01-02 03:04:05', new DateTimeZone('UTC'));

	$injected = new class($fixed) implements Symfony\Component\Clock\ClockInterface {
		public function __construct(private DateTimeImmutable $now) {}

		public function now(): DateTimeImmutable {
			return $this->now;
		}

		public function sleep(float|int $seconds): void {}

		public function withTimeZone(DateTimeZone|string $timezone): static {
			return $this;
		}
	};

	$clock = new CactiClock($injected);

	expect($clock->now())->toEqual($fixed);
	expect($clock->time())->toBe($fixed->getTimestamp());
});
