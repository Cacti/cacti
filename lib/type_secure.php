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
*/

/**
 * Type Security Utility
 *
 * Provides safe casting methods that are compatible with PHP 8.6+ strictness.
 * Use these instead of raw (int) or (string) casts when data might be null
 * or malformed.
 */
class CactiSecureType {
	/**
	 * Safely casts a value to an integer.
	 * Returns 0 if the value is null or non-numeric, preventing PHP 8.6 warnings.
	 */
	public static function toInt(mixed $value): int {
		if ($value === null || !is_numeric($value)) {
			return 0;
		}

		return (int)$value;
	}

	/**
	 * Safely casts a value to a string.
	 * Returns an empty string if the value is null, preventing PHP 8.6 fatal errors.
	 */
	public static function toString(mixed $value): string {
		if ($value === null) {
			return '';
		}

		return (string)$value;
	}

	/**
	 * Safely casts a value to a boolean.
	 */
	public static function toBool(mixed $value): bool {
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Ensures a value is an array.
	 * Returns [] if the value is false or null, preventing autovivification errors.
	 */
	public static function toArray(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}

		return $value;
	}
}
