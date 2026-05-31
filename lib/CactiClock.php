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

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * DI-friendly clock wrapper for code that needs testable time access.
 */
class CactiClock {
	private ClockInterface $clock;

	public function __construct(?ClockInterface $clock = null) {
		$this->clock = $clock ?? new NativeClock();
	}

	public static function default(): self {
		return new self();
	}

	public static function currentTime(): \DateTimeImmutable {
		return self::default()->now();
	}

	public static function unixTime(): int {
		return self::default()->time();
	}

	public static function sleepFor(float|int $seconds): void {
		self::default()->sleep($seconds);
	}

	public function now(): \DateTimeImmutable {
		return $this->clock->now();
	}

	public function time(): int {
		return $this->now()->getTimestamp();
	}

	public function sleep(float|int $seconds): void {
		$this->clock->sleep($seconds);
	}
}
