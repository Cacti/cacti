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
 * Determine whether the active user ID matches the configured Guest account.
 *
 * @param mixed $user_id          The active session or target user ID.
 * @param mixed $guest_account_id The configured Guest account ID from get_guest_account().
 *
 * @return bool True when a Guest account is active and matches the user ID.
 */
function cacti_user_is_guest_account(mixed $user_id, mixed $guest_account_id) : bool {
	$guest = filter_var($guest_account_id, FILTER_VALIDATE_INT);
	$user  = filter_var($user_id, FILTER_VALIDATE_INT);

	if ($guest === false || $guest <= 0 || $user === false || $user <= 0) {
		return false;
	}

	return $user === $guest;
}

/**
 * Validate a candidate user preference value against its $settings_user field schema.
 *
 * @param string               $name        The user setting key name.
 * @param mixed                $value       The candidate value submitted by a client.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated string value, or false when invalid.
 */
function cacti_validate_user_setting_value(string $name, mixed $value, array $field_array) : string|false {
	if ($name === '' || !isset($field_array['method']) || !is_string($field_array['method'])) {
		return false;
	}

	if (!is_scalar($value)) {
		return false;
	}

	$str = is_bool($value) ? ($value ? 'on' : '') : (string) $value;

	if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $str) === 1) {
		return false;
	}

	return match ($field_array['method']) {
		'checkbox'                              => cacti_validate_checkbox_setting_value($str),
		'drop_array', 'themes', 'drop_language' => cacti_validate_list_setting_value($str, $field_array),
		'drop_sql', 'drop_color'                => cacti_validate_int_setting_value($str, [], 0, null),
		'textbox'                               => cacti_validate_textbox_setting_value($str, $field_array),
		'font'                                  => cacti_validate_font_setting_value($str, $field_array),
		default                                 => false,
	};
}

/**
 * Validate a textbox user setting value using its declared 'value_type' schema.
 *
 * Supported value_type declarations:
 *   - 'string'           : bounded by 'max_length' (default 255)
 *   - 'int'              : signed integer with optional 'min'/'max'
 *   - 'positive_int'     : integer >= 1 (default max 100000, optional 'min'/'max')
 *   - 'non_negative_int' : integer >= 0 (default max 100000, optional 'min'/'max')
 *   - 'double', 'float'  : finite floating-point number with optional 'min'/'max'
 *   - 'time'             : 24-hour HH:MM value
 *
 * When a plugin omits 'value_type', a numeric 'default' infers 'double' if it
 * contains a decimal point (or is a float) and 'non_negative_int' otherwise;
 * non-numeric defaults fall back to 'string'.
 *
 * @param string               $str         The candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated string value, or false when invalid.
 */
function cacti_validate_textbox_setting_value(string $str, array $field_array) : string|false {
	if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $str) === 1) {
		return false;
	}

	if (isset($field_array['value_type'])) {
		if (!is_string($field_array['value_type'])) {
			return false;
		}

		$value_type = $field_array['value_type'];
	} elseif (isset($field_array['default']) && is_numeric($field_array['default'])) {
		$value_type = is_float($field_array['default']) || str_contains((string) $field_array['default'], '.')
			? 'double'
			: 'non_negative_int';
	} else {
		$value_type = 'string';
	}

	return match ($value_type) {
		'string'           => cacti_validate_string_setting_value($str, $field_array),
		'int'              => cacti_validate_int_setting_value($str, $field_array, null, null),
		'positive_int'     => cacti_validate_int_setting_value($str, $field_array, 1, 100000),
		'non_negative_int' => cacti_validate_int_setting_value($str, $field_array, 0, 100000),
		'double', 'float'  => cacti_validate_float_setting_value($str, $field_array),
		'time'             => preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D', $str) === 1 ? $str : false,
		default            => false,
	};
}

/**
 * Validate a checkbox user setting value.
 *
 * @param string $str The candidate string value.
 *
 * @return string|false Canonical 'on' or '', or false when invalid.
 */
function cacti_validate_checkbox_setting_value(string $str) : string|false {
	return match ($str) {
		'on', 'true' => 'on',
		'', 'false'  => '',
		default      => false,
	};
}

/**
 * Validate a selection setting against its allowed options map.
 *
 * @param string               $str         The candidate option key.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated option key, or false when invalid.
 */
function cacti_validate_list_setting_value(string $str, array $field_array) : string|false {
	if (!isset($field_array['array']) || !is_array($field_array['array'])) {
		return false;
	}

	$allowed = $field_array['array'];

	if (array_key_exists($str, $allowed)) {
		return $str;
	}

	if (preg_match('/^-?[0-9]+$/D', $str) === 1 && array_key_exists((int) $str, $allowed)) {
		return (string) ((int) $str);
	}

	return false;
}

/**
 * Validate a bounded string setting value.
 *
 * @param string               $str         The candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated string value, or false when exceeding max_length.
 */
function cacti_validate_string_setting_value(string $str, array $field_array) : string|false {
	$max_length = filter_var($field_array['max_length'] ?? 255, FILTER_VALIDATE_INT);

	if ($max_length === false || $max_length < 1) {
		$max_length = 255;
	}

	return strlen($str) <= $max_length ? $str : false;
}

/**
 * Validate an integer setting value with optional floor and bounds.
 *
 * @param string               $str         The candidate integer string.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 * @param int|null             $floor       Minimum allowed value for the type, or null if unbounded.
 * @param int|null             $default_max Default inclusive maximum when 'max' is omitted.
 *
 * @return string|false The canonical integer string, or false when invalid or out of bounds.
 */
function cacti_validate_int_setting_value(string $str, array $field_array, ?int $floor, ?int $default_max) : string|false {
	$int = filter_var($str, FILTER_VALIDATE_INT);

	if ($int === false) {
		return false;
	}

	$min = $floor;

	if (isset($field_array['min'])) {
		$configured_min = filter_var($field_array['min'], FILTER_VALIDATE_INT);

		if ($configured_min === false) {
			return false;
		}

		$min = $floor !== null ? max($floor, $configured_min) : $configured_min;
	}

	$max = $default_max;

	if (isset($field_array['max'])) {
		$configured_max = filter_var($field_array['max'], FILTER_VALIDATE_INT);

		if ($configured_max === false) {
			return false;
		}

		$max = $configured_max;
	}

	if (($min !== null && $int < $min) || ($max !== null && $int > $max)) {
		return false;
	}

	return (string) $int;
}

/**
 * Validate a finite floating-point setting value with optional bounds.
 *
 * @param string               $str         The candidate floating-point string.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The canonical numeric string, or false when invalid, non-finite, or out of bounds.
 */
function cacti_validate_float_setting_value(string $str, array $field_array) : string|false {
	$float = filter_var($str, FILTER_VALIDATE_FLOAT);

	if ($float === false || !is_finite($float)) {
		return false;
	}

	$min = null;

	if (isset($field_array['min'])) {
		$configured_min = filter_var($field_array['min'], FILTER_VALIDATE_FLOAT);

		if ($configured_min === false || !is_finite($configured_min)) {
			return false;
		}

		$min = $configured_min;
	}

	$max = null;

	if (isset($field_array['max'])) {
		$configured_max = filter_var($field_array['max'], FILTER_VALIDATE_FLOAT);

		if ($configured_max === false || !is_finite($configured_max)) {
			return false;
		}

		$max = $configured_max;
	}

	if (($min !== null && $float < $min) || ($max !== null && $float > $max)) {
		return false;
	}

	if ($float == 0.0) {
		$float = 0.0;
	}

	return (string) $float;
}

/**
 * Validate a font path/name user setting value.
 *
 * @param string               $str         The candidate font string.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated font string, or false when invalid.
 */
function cacti_validate_font_setting_value(string $str, array $field_array) : string|false {
	$max_length = filter_var($field_array['max_length'] ?? 100, FILTER_VALIDATE_INT);

	if ($max_length === false || $max_length < 1) {
		$max_length = 100;
	}

	if (strlen($str) > $max_length || preg_match('/[;|&$`<>]/', $str) === 1) {
		return false;
	}

	return $str;
}

/**
 * Sanitize a positive integer user setting read from storage before SQL or JS output.
 *
 * @param mixed $value   The raw stored or requested value.
 * @param int   $default Fallback integer when the value is invalid or out of range.
 * @param int   $min     Inclusive minimum allowed value.
 * @param int   $max     Inclusive maximum allowed value.
 *
 * @return int A strictly bounded integer.
 */
function cacti_sanitize_positive_int_setting(mixed $value, int $default, int $min = 1, int $max = 100000) : int {
	$int = filter_var($value, FILTER_VALIDATE_INT);

	if ($int === false || $int < $min || $int > $max) {
		return $default;
	}

	return $int;
}

/**
 * Sanitize the realtime polling interval in seconds before shell execution.
 *
 * @param mixed                    $value             The raw interval from request or user settings.
 * @param array<int|string, mixed> $allowed_intervals Optional configured interval map ($realtime_refresh).
 * @param int                      $default           Fallback interval in seconds.
 *
 * @return int A strictly validated positive integer interval.
 */
function cacti_sanitize_realtime_interval(mixed $value, array $allowed_intervals = [], int $default = 10) : int {
	$int = filter_var($value, FILTER_VALIDATE_INT);

	if ($int === false || $int < 1 || $int > 3600) {
		return $default;
	}

	if ($allowed_intervals !== [] && !array_key_exists($int, $allowed_intervals) && !array_key_exists((string) $int, $allowed_intervals)) {
		return $default;
	}

	return $int;
}

/**
 * Validate a realtime poller session hash before using it in filenames or shell arguments.
 *
 * @param mixed $hash The candidate session hash.
 *
 * @return bool True when the hash is a safe 1-to-64 character hexadecimal string.
 */
function cacti_validate_realtime_hash(mixed $hash) : bool {
	return is_string($hash) && preg_match('/^[a-f0-9]{1,64}$/iD', $hash) === 1;
}
