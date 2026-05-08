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
	 */
	private static function mapToCactiLevel(string $psrLevel): int {
		return match ($psrLevel) {
			LogLevel::DEBUG   => 5, // POLLER_VERBOSITY_DEVDBG
			LogLevel::INFO    => 2, // POLLER_VERBOSITY_LOW
			LogLevel::WARNING => 1, // POLLER_VERBOSITY_NONE (standard)
			LogLevel::ERROR   => 1,
			LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => 1,
			default => 1,
		};
	}
}
