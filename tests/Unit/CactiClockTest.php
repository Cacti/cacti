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

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiClock.php';

test('CactiClock exposes a DI-friendly Symfony clock wrapper', function () {
	$clock = new CactiClock();

	expect($clock->now())->toBeInstanceOf(DateTimeImmutable::class);
	expect($clock->time())->toBeInt();
	expect(CactiClock::currentTime())->toBeInstanceOf(DateTimeImmutable::class);
	expect(CactiClock::unixTime())->toBeInt();
});

test('CactiClock uses an injected clock instead of the default', function () {
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

test('CactiClock delegates sleep to the wrapped clock', function () {
	$recorder = new class implements Symfony\Component\Clock\ClockInterface {
		public array $slept = [];

		public function now(): DateTimeImmutable {
			return new DateTimeImmutable('@0');
		}

		public function sleep(float|int $seconds): void {
			$this->slept[] = $seconds;
		}

		public function withTimeZone(DateTimeZone|string $timezone): static {
			return $this;
		}
	};

	$clock = new CactiClock($recorder);
	$clock->sleep(1.5);

	expect($recorder->slept)->toBe([1.5]);
});

test('CactiClock static sleepFor uses the default clock', function () {
	// NativeClock sleep(0) returns immediately; this exercises the static path.
	CactiClock::sleepFor(0);

	expect(CactiClock::default())->toBe(CactiClock::default());
});
