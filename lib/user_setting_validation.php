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
 * Validate a checkbox user setting value.
 *
 * @param string $str The normalized candidate string value.
 *
 * @return string|false Returns 'on', '', or false when invalid.
 */
function cacti_validate_checkbox_setting_value(string $str) : string|false {
	if ($str === 'on' || $str === '' || $str === 'true' || $str === 'false') {
		return ($str === 'on' || $str === 'true') ? 'on' : '';
	}

	return false;
}

/**
 * Validate a dropdown array, theme, or language user setting value.
 *
 * @param string               $str         The normalized candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated option key, or false when invalid.
 */
function cacti_validate_drop_array_setting_value(string $str, array $field_array) : string|false {
	if (!isset($field_array['array']) || !is_array($field_array['array'])) {
		return false;
	}

	$allowed = $field_array['array'];

	if (array_key_exists($str, $allowed)) {
		return $str;
	}

	if (preg_match('/^-?[0-9]+$/', $str) === 1 && array_key_exists((int) $str, $allowed)) {
		return (string) ((int) $str);
	}

	return false;
}

/**
 * Validate a font user setting value.
 *
 * @param string               $str         The normalized candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated font string, or false when invalid.
 */
function cacti_validate_font_setting_value(string $str, array $field_array) : string|false {
	$max_length = isset($field_array['max_length']) && is_numeric($field_array['max_length'])
		? (int) $field_array['max_length']
		: 100;

	if (strlen($str) > $max_length || preg_match('/[;|&$`<>]/', $str) === 1) {
		return false;
	}

	return $str;
}

/**
 * Validate an integer textbox setting value against optional bounds.
 *
 * @param string               $str         The normalized candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 * @param int|null             $default_min Default inclusive minimum, or null when unbounded.
 * @param int|null             $default_max Default inclusive maximum, or null when unbounded.
 * @param int|null             $floor       Hard lower bound that field-level min cannot relax.
 *
 * @return string|false The canonical integer string, or false when invalid.
 */
function cacti_validate_textbox_int_value(string $str, array $field_array, ?int $default_min = null, ?int $default_max = null, ?int $floor = null) : string|false {
	$int = filter_var($str, FILTER_VALIDATE_INT);

	if ($int === false) {
		return false;
	}

	$min = isset($field_array['min']) && is_numeric($field_array['min'])
		? (int) $field_array['min']
		: $default_min;

	if ($floor !== null && ($min === null || $min < $floor)) {
		$min = $floor;
	}

	$max = isset($field_array['max']) && is_numeric($field_array['max'])
		? (int) $field_array['max']
		: $default_max;

	if (($min !== null && $int < $min) || ($max !== null && $int > $max)) {
		return false;
	}

	return (string) $int;
}

/**
 * Validate a floating-point textbox setting value against optional bounds.
 *
 * @param string               $str         The normalized candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The canonical float string, or false when invalid.
 */
function cacti_validate_textbox_float_value(string $str, array $field_array) : string|false {
	$float = filter_var($str, FILTER_VALIDATE_FLOAT);

	if ($float === false || !is_finite($float)) {
		return false;
	}

	$min = isset($field_array['min']) && is_numeric($field_array['min'])
		? (float) $field_array['min']
		: null;
	$max = isset($field_array['max']) && is_numeric($field_array['max'])
		? (float) $field_array['max']
		: null;

	if (($min !== null && $float < $min) || ($max !== null && $float > $max)) {
		return false;
	}

	return (string) $float;
}

/**
 * Validate a textbox user setting value according to its declared value_type.
 *
 * Supported value types are string, int, positive_int, non_negative_int,
 * double/float, and time (24-hour HH:MM format). When a field definition
 * (for example from a plugin) omits value_type, numeric defaults infer
 * double (when containing a decimal point) or non_negative_int; all other
 * fields default to string.
 *
 * @param string               $str         The normalized candidate string value.
 * @param array<string, mixed> $field_array The matching $settings_user field definition.
 *
 * @return string|false The validated string value, or false when invalid.
 */
function cacti_validate_textbox_setting_value(string $str, array $field_array) : string|false {
	if (isset($field_array['value_type']) && is_string($field_array['value_type'])) {
		$value_type = strtolower($field_array['value_type']);
	} elseif (isset($field_array['default']) && is_numeric($field_array['default'])) {
		$value_type = str_contains((string) $field_array['default'], '.') ? 'double' : 'non_negative_int';
	} else {
		$value_type = 'string';
	}

	$max_length = isset($field_array['max_length']) && is_numeric($field_array['max_length'])
		? (int) $field_array['max_length']
		: 255;

	return match ($value_type) {
		'string'           => strlen($str) <= $max_length ? $str : false,
		'int'              => cacti_validate_textbox_int_value($str, $field_array),
		'positive_int'     => cacti_validate_textbox_int_value($str, $field_array, 1, 100000, 1),
		'non_negative_int' => cacti_validate_textbox_int_value($str, $field_array, 0, 100000, 0),
		'double', 'float'  => cacti_validate_textbox_float_value($str, $field_array),
		'time'             => preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $str) === 1 ? $str : false,
		default            => false,
	};
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
	if (!isset($field_array['method']) || !is_string($field_array['method'])) {
		return false;
	}

	if (!is_scalar($value) && $value !== '') {
		return false;
	}

	$str = is_bool($value) ? ($value ? 'on' : '') : (string) $value;

	if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $str) === 1 || str_contains($str, "\r") || str_contains($str, "\n")) {
		return false;
	}

	$method = $field_array['method'];

	return match ($method) {
		'checkbox'                              => cacti_validate_checkbox_setting_value($str),
		'drop_array', 'themes', 'drop_language' => cacti_validate_drop_array_setting_value($str, $field_array),
		'drop_sql', 'drop_color'                => cacti_validate_textbox_int_value($str, [], 0, null, 0),
		'textbox'                               => cacti_validate_textbox_setting_value($str, $field_array),
		'font'                                  => cacti_validate_font_setting_value($str, $field_array),
		default                                 => false,
	};
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

	if (!empty($allowed_intervals)) {
		if (!array_key_exists($int, $allowed_intervals) && !array_key_exists((string) $int, $allowed_intervals)) {
			return $default;
		}
	}

	return $int;
}
