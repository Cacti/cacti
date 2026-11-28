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

	if ($method === 'checkbox') {
		if ($str === 'on' || $str === '' || $str === 'true' || $str === 'false') {
			return ($str === 'on' || $str === 'true') ? 'on' : '';
		}

		return false;
	}

	if ($method === 'drop_array' || $method === 'themes' || $method === 'drop_language') {
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

	if ($method === 'drop_sql' || $method === 'drop_color') {
		$int = filter_var($str, FILTER_VALIDATE_INT);

		if ($int === false || $int < 0) {
			return false;
		}

		return (string) $int;
	}

	if ($method === 'textbox') {
		if ($name === 'day_shift_start' || $name === 'day_shift_end') {
			return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $str) === 1 ? $str : false;
		}

		$numeric_keys = [
			'default_height',
			'default_width',
			'min_tree_width',
			'max_tree_width',
			'title_size',
			'legend_size',
			'axis_size',
			'unit_size',
			'preview_graphs_per_page',
			'treeview_graphs_per_page',
			'page_refresh',
			'num_columns',
			'num_columns_tree',
			'realtime_interval',
			'realtime_gwindow',
			'realtime_size',
		];

		$is_numeric_field = in_array($name, $numeric_keys, true) ||
			(isset($field_array['default']) && is_numeric($field_array['default']));

		if ($is_numeric_field) {
			$int = filter_var($str, FILTER_VALIDATE_INT);

			if ($int === false || $int < 0 || $int > 100000) {
				return false;
			}

			return (string) $int;
		}

		$max_length = isset($field_array['max_length']) && is_numeric($field_array['max_length'])
			? (int) $field_array['max_length']
			: 255;

		if (strlen($str) > $max_length) {
			return false;
		}

		return $str;
	}

	if ($method === 'font') {
		$max_length = isset($field_array['max_length']) && is_numeric($field_array['max_length'])
			? (int) $field_array['max_length']
			: 100;

		if (strlen($str) > $max_length || preg_match('/[;|&$`<>]/', $str) === 1) {
			return false;
		}

		return $str;
	}

	return false;
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
