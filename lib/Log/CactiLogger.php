<?php
declare(strict_types = 1);
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

namespace Cacti\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CactiLogger {
	private static ?LoggerInterface $logger = null;

	/**
	 * Set a custom PSR-3 logger instance.
	 */
	public static function setLogger(LoggerInterface $logger): void {
		self::$logger = $logger;
	}

	/**
	 * Reset the configured logger (for tests).
	 */
	public static function reset(): void {
		self::$logger = null;
	}

	/**
	 * Log a message at a specific level.
	 */
	public static function log(string $level, string|\Stringable $message, array $context = []): void {
		if (self::$logger !== null) {
			self::$logger->log($level, $message, $context);
		} else {
			// Fallback to legacy cacti_log
			$cacti_level = self::mapToCactiLevel($level);
			cacti_log((string)$message, false, $context['environ'] ?? 'CMDPHP', $cacti_level);
		}
	}

	/**
	 * Helper for INFO level.
	 */
	public static function info(string|\Stringable $message, array $context = []): void {
		self::log(LogLevel::INFO, $message, $context);
	}

	/**
	 * Helper for ERROR level.
	 */
	public static function error(string|\Stringable $message, array $context = []): void {
		self::log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * Map PSR-3 levels to Cacti POLLER_VERBOSITY constants.
	 *
	 * Constants come from include/global_constants.php; we resolve them at
	 * call time and fall back to numeric literals so the wrapper still works
	 * when global_constants.php has not been loaded (unit-test contexts).
	 */
	private static function mapToCactiLevel(string $psrLevel): int {
		$debug = defined('POLLER_VERBOSITY_DEBUG') ? POLLER_VERBOSITY_DEBUG : 5;
		$low   = defined('POLLER_VERBOSITY_LOW') ? POLLER_VERBOSITY_LOW : 2;
		$none  = defined('POLLER_VERBOSITY_NONE') ? POLLER_VERBOSITY_NONE : 1;

		return match ($psrLevel) {
			LogLevel::DEBUG                                          => $debug,
			LogLevel::INFO, LogLevel::NOTICE                         => $low,
			LogLevel::WARNING,
			LogLevel::ERROR,
			LogLevel::CRITICAL,
			LogLevel::ALERT,
			LogLevel::EMERGENCY                                      => $none,
			default                                                  => $none,
		};
	}
}
