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

/*
 * Tests for the initializeSpikekill() user/default assignment logic.
 *
 * Issue #5266: the six if/else branches in initializeSpikekill() were
 * inverted, assigning the system default when a user setting existed
 * and vice versa. This caused operator-configured spikekill parameters
 * to be silently discarded.
 *
 * These tests use inline stubs that mirror the production assignment
 * pattern without requiring a live database or the full spikekill class.
 *
 * See: https://github.com/Cacti/cacti/issues/5266
 */

/**
 * Mirrors the fixed initializeSpikekill() assignment logic for one field.
 * $current: the value passed to the constructor (empty string if unset).
 * $user_setting: the value from read_user_setting() (empty string if unset).
 * $default: the system default from read_config_option().
 *
 * Returns the resolved value that would be assigned to the field.
 */
function resolve_spikekill_field(string $current, string $user_setting, string $default): string {
	if ($current == '') {
		if (!empty($user_setting)) {
			return $user_setting;
		} else {
			return $default;
		}
	}

	return $current;
}

/**
 * Mirrors the BUGGY (pre-fix) assignment logic where user/default were swapped.
 */
function resolve_spikekill_field_buggy(string $current, string $user_setting, string $default): string {
	if ($current == '') {
		if (!empty($user_setting)) {
			return $default;
		} else {
			return $user_setting;
		}
	}

	return $current;
}

// The six spikekill fields and their system defaults (from spikekill class properties)
define('SPIKEKILL_FIELDS', [
	'avgnan'   => 'last',
	'method'   => '1',
	'numspike' => '10',
	'stddev'   => '10',
	'dsfilter' => '',
	'absmax'   => '1000000000',
]);

// --- User settings take priority over defaults ---

test('user setting is used when constructor value is empty', function () {
	foreach (SPIKEKILL_FIELDS as $field => $default) {
		$user_value = "user_{$field}_custom";

		$result = resolve_spikekill_field('', $user_value, $default);

		expect($result)->toBe($user_value, "Field '{$field}' should use user setting '{$user_value}', got '{$result}'");
	}
});

// --- Default values are used when user settings are empty ---

test('default is used when both constructor and user setting are empty', function () {
	foreach (SPIKEKILL_FIELDS as $field => $default) {
		$result = resolve_spikekill_field('', '', $default);

		expect($result)->toBe($default, "Field '{$field}' should fall back to default '{$default}', got '{$result}'");
	}
});

// --- Constructor value is preserved when non-empty ---

test('constructor value is preserved when already set', function () {
	foreach (SPIKEKILL_FIELDS as $field => $default) {
		$constructor_value = "explicit_{$field}";
		$user_value        = "user_{$field}";

		$result = resolve_spikekill_field($constructor_value, $user_value, $default);

		expect($result)->toBe($constructor_value, "Field '{$field}' should keep constructor value '{$constructor_value}', got '{$result}'");
	}
});

// --- Buggy logic produces wrong results (regression guard) ---

test('buggy logic assigns default when user setting exists', function () {
	foreach (SPIKEKILL_FIELDS as $field => $default) {
		$user_value = "user_{$field}_custom";

		$buggy_result = resolve_spikekill_field_buggy('', $user_value, $default);
		$fixed_result = resolve_spikekill_field('', $user_value, $default);

		/* The bug: when a user setting exists, the buggy code returns the default */
		expect($buggy_result)->toBe($default, "Buggy logic for '{$field}' should return default")
			->and($fixed_result)->toBe($user_value, "Fixed logic for '{$field}' should return user setting")
			->and($buggy_result)->not->toBe($fixed_result, "Buggy and fixed results for '{$field}' should differ when user setting is non-empty");
	}
});

test('buggy logic assigns empty string when user setting is absent', function () {
	foreach (SPIKEKILL_FIELDS as $field => $default) {
		if ($default === '') {
			continue; /* dsfilter default is empty, so buggy and fixed produce the same result */
		}

		$buggy_result = resolve_spikekill_field_buggy('', '', $default);
		$fixed_result = resolve_spikekill_field('', '', $default);

		/* The bug: when no user setting exists, the buggy code returns '' instead of the default */
		expect($buggy_result)->toBe('', "Buggy logic for '{$field}' should return empty string")
			->and($fixed_result)->toBe($default, "Fixed logic for '{$field}' should return default '{$default}'");
	}
});

// --- All six fields are individually verified ---

test('avgnan resolves user setting over default', function () {
	$result = resolve_spikekill_field('', 'nan', 'last');

	expect($result)->toBe('nan');
});

test('method resolves user setting over default', function () {
	$result = resolve_spikekill_field('', '2', '1');

	expect($result)->toBe('2');
});

test('numspike resolves user setting over default', function () {
	$result = resolve_spikekill_field('', '5', '10');

	expect($result)->toBe('5');
});

test('stddev resolves user setting over default', function () {
	$result = resolve_spikekill_field('', '3', '10');

	expect($result)->toBe('3');
});

test('dsfilter resolves user setting over default', function () {
	$result = resolve_spikekill_field('', 'traffic_in', '');

	expect($result)->toBe('traffic_in');
});

test('absmax resolves user setting over default', function () {
	$result = resolve_spikekill_field('', '500000', '1000000000');

	expect($result)->toBe('500000');
});
