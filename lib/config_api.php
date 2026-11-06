<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Typed Settings API.
 *
 * read_config_option() returns the raw stored value of a Cacti setting, which
 * is always an untyped string (or the setting's string default, or null when
 * neither exists). Callers that need a number or a boolean have historically
 * coerced inline with intval(), (bool) casts, or string comparisons such as
 * == 'on'. Those inline coercions disagree on edge cases: intval('') is 0,
 * '' == 'on' is false, and a non-numeric string silently becomes 0. When such
 * a value feeds a security or control-flow decision the result is a classic
 * type-confusion bug (CWE-704) -- the same class as a non-numeric realm value
 * comparing unexpectedly in an authorization check.
 *
 * These accessors centralise the coercion so every call site agrees on the
 * rules, and so an invalid or missing value yields the caller-supplied typed
 * default instead of a silent zero or false. They build directly on
 * read_config_option(), so caching, remote-poller behaviour, and the optional
 * $force re-read are identical to reading the option by hand.
 */

/**
 * Reads a setting coerced to an integer.
 *
 * A numeric stored value is returned as its integer value (matching the long
 * standing intval(read_config_option(...)) idiom for every numeric string).
 * An empty, null, or non-numeric value returns $default rather than silently
 * collapsing to 0.
 *
 * @param string $name    Setting name.
 * @param int    $default Value returned when the setting is missing or not numeric.
 * @param bool   $force   Re-read from the database, bypassing the request cache.
 *
 * @return int The coerced integer value.
 */
function cacti_setting_int(string $name, int $default = 0, bool $force = false) : int {
	$value = read_config_option($name, $force);

	if (is_int($value)) {
		return $value;
	}

	if (is_string($value) && is_numeric(trim($value))) {
		return intval(trim($value));
	}

	return $default;
}

/**
 * Reads a setting coerced to a boolean.
 *
 * Cacti stores booleans in several textual forms. The truthy forms recognised
 * here are 'on', '1', and 'true'; the falsy forms are '', '0', 'false', and
 * 'off'. Comparison is case-insensitive and whitespace-trimmed. Any other
 * value returns $default. This keeps the common read_config_option(...) == 'on'
 * idiom exact ('on' is true, everything else that is a recognised falsy form is
 * false) while refusing to guess at unrecognised values.
 *
 * @param string $name    Setting name.
 * @param bool   $default Value returned when the setting is missing or unrecognised.
 * @param bool   $force   Re-read from the database, bypassing the request cache.
 *
 * @return bool The coerced boolean value.
 */
function cacti_setting_bool(string $name, bool $default = false, bool $force = false) : bool {
	$value = read_config_option($name, $force);

	if (is_bool($value)) {
		return $value;
	}

	if ($value === null || !is_scalar($value)) {
		return $default;
	}

	$normalized = strtolower(trim((string) $value));

	if (in_array($normalized, ['on', '1', 'true'], true)) {
		return true;
	}

	if (in_array($normalized, ['', '0', 'false', 'off'], true)) {
		return false;
	}

	return $default;
}

/**
 * Reads a setting coerced to a string.
 *
 * A present value is returned verbatim as a string, including the empty string,
 * which is a legitimate configured value for many textbox settings. Only a null
 * (truly absent and with no default) or a non-scalar value returns $default.
 *
 * @param string $name    Setting name.
 * @param string $default Value returned when the setting is absent or non-scalar.
 * @param bool   $force   Re-read from the database, bypassing the request cache.
 *
 * @return string The string value.
 */
function cacti_setting_string(string $name, string $default = '', bool $force = false) : string {
	$value = read_config_option($name, $force);

	if ($value === null || !is_scalar($value)) {
		return $default;
	}

	return (string) $value;
}

/**
 * Reads a setting constrained to a fixed set of allowed string values.
 *
 * The stored value is compared (as a string, case-sensitively) against
 * $allowed. A match is returned unchanged; anything else -- including a missing
 * value or one no longer offered by the setting -- returns $default. This is
 * the typed accessor for drop_array settings, where the only reachable values
 * are the menu entries, so the result is identical for every value a user can
 * select while rejecting a stale or tampered value instead of propagating it.
 *
 * @param string   $name    Setting name.
 * @param string[] $allowed The permitted values.
 * @param string   $default Value returned when the stored value is not in $allowed.
 * @param bool     $force   Re-read from the database, bypassing the request cache.
 *
 * @return string One of $allowed, or $default.
 */
function cacti_setting_enum(string $name, array $allowed, string $default, bool $force = false) : string {
	$value = read_config_option($name, $force);

	if ($value !== null && is_scalar($value) && in_array((string) $value, $allowed, true)) {
		return (string) $value;
	}

	return $default;
}
