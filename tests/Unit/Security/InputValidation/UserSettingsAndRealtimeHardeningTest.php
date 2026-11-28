<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for user-setting validation, Guest account write restrictions,
 * tree pagination validation, and realtime graph access hardening.
 */

$root = dirname(__DIR__, 4);

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once $root . '/include/global.php';
require_once $root . '/lib/user_setting_validation.php';

test('cacti_user_is_guest_account identifies active guest sessions safely', function () {
	expect(cacti_user_is_guest_account(3, 3))->toBeTrue();
	expect(cacti_user_is_guest_account('3', '3'))->toBeTrue();
	expect(cacti_user_is_guest_account(1, 3))->toBeFalse();
	expect(cacti_user_is_guest_account(0, 0))->toBeFalse();
	expect(cacti_user_is_guest_account(3, 0))->toBeFalse();
	expect(cacti_user_is_guest_account('', '3'))->toBeFalse();
	expect(cacti_user_is_guest_account(-1, -1))->toBeFalse();
});

test('cacti_validate_user_setting_value enforces schema types across field methods', function () {
	$dropArray = [
		'method' => 'drop_array',
		'array'  => [
			10 => '10 Graphs',
			20 => '20 Graphs',
			50 => '50 Graphs',
		],
		'default' => 20,
	];

	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '20', $dropArray))->toBe('20');
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '020', $dropArray))->toBe('20');
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', 50, $dropArray))->toBe('50');
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '999', $dropArray))->toBeFalse();
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '20, 10', $dropArray))->toBeFalse();
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', 'not-a-number', $dropArray))->toBeFalse();

	$checkbox = ['method' => 'checkbox', 'default' => 'on'];
	expect(cacti_validate_user_setting_value('show_graph_title', 'on', $checkbox))->toBe('on');
	expect(cacti_validate_user_setting_value('show_graph_title', 'true', $checkbox))->toBe('on');
	expect(cacti_validate_user_setting_value('show_graph_title', '', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'false', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'invalid', $checkbox))->toBeFalse();

	$numericTextbox = ['method' => 'textbox', 'value_type' => 'positive_int', 'min' => 1, 'max' => 10000, 'default' => '100', 'max_length' => '5'];
	expect(cacti_validate_user_setting_value('min_tree_width', '200', $numericTextbox))->toBe('200');
	expect(cacti_validate_user_setting_value('min_tree_width', '0', $numericTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('min_tree_width', '-5', $numericTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('min_tree_width', '10001', $numericTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('min_tree_width', '200; extra', $numericTextbox))->toBeFalse();

	$timeTextbox = ['method' => 'textbox', 'value_type' => 'time', 'default' => '07:00', 'max_length' => '5'];
	expect(cacti_validate_user_setting_value('day_shift_start', '08:30', $timeTextbox))->toBe('08:30');
	expect(cacti_validate_user_setting_value('day_shift_start', '00:00', $timeTextbox))->toBe('00:00');
	expect(cacti_validate_user_setting_value('day_shift_start', '23:59', $timeTextbox))->toBe('23:59');
	expect(cacti_validate_user_setting_value('day_shift_start', '24:00', $timeTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('day_shift_start', '25:99', $timeTextbox))->toBeFalse();

	$dropSql = ['method' => 'drop_sql', 'default' => '0'];
	expect(cacti_validate_user_setting_value('default_tree_id', '4', $dropSql))->toBe('4');
	expect(cacti_validate_user_setting_value('default_tree_id', '0', $dropSql))->toBe('0');
	expect(cacti_validate_user_setting_value('default_tree_id', '-1', $dropSql))->toBeFalse();
	expect(cacti_validate_user_setting_value('default_tree_id', '4 extra', $dropSql))->toBeFalse();

	$fontField = ['method' => 'font', 'max_length' => '100'];
	expect(cacti_validate_user_setting_value('title_font', 'DejaVuSans', $fontField))->toBe('DejaVuSans');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVuSans', ['method' => 'font']))->toBe('DejaVuSans');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVu;bad', $fontField))->toBeFalse();
	expect(cacti_validate_user_setting_value('title_font', "DejaVu\nLine2", $fontField))->toBeFalse();
});

test('cacti_validate_textbox_setting_value enforces every declared value_type and plugin fallback', function () {
	// string
	expect(cacti_validate_textbox_setting_value('hello', ['value_type' => 'string', 'max_length' => 10]))->toBe('hello');
	expect(cacti_validate_textbox_setting_value('12345678901', ['value_type' => 'string', 'max_length' => 10]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value(str_repeat('a', 255), ['value_type' => 'string']))->toBe(str_repeat('a', 255));
	expect(cacti_validate_textbox_setting_value(str_repeat('a', 256), ['value_type' => 'string']))->toBeFalse();

	// signed int
	expect(cacti_validate_textbox_setting_value('-42', ['value_type' => 'int']))->toBe('-42');
	expect(cacti_validate_textbox_setting_value('0', ['value_type' => 'int']))->toBe('0');
	expect(cacti_validate_textbox_setting_value('42', ['value_type' => 'int', 'min' => -10, 'max' => 50]))->toBe('42');
	expect(cacti_validate_textbox_setting_value('-11', ['value_type' => 'int', 'min' => -10, 'max' => 50]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('51', ['value_type' => 'int', 'min' => -10, 'max' => 50]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('12.5', ['value_type' => 'int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('999999999999999999999999999999', ['value_type' => 'int']))->toBeFalse();

	// positive_int (min >= 1, default max 100000)
	expect(cacti_validate_textbox_setting_value('1', ['value_type' => 'positive_int']))->toBe('1');
	expect(cacti_validate_textbox_setting_value('100000', ['value_type' => 'positive_int']))->toBe('100000');
	expect(cacti_validate_textbox_setting_value('0', ['value_type' => 'positive_int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('-1', ['value_type' => 'positive_int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('100001', ['value_type' => 'positive_int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('0', ['value_type' => 'positive_int', 'min' => 0]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('10', ['value_type' => 'POSITIVE_INT', 'min' => 5, 'max' => 20]))->toBe('10');

	// non_negative_int (min >= 0, default max 100000)
	expect(cacti_validate_textbox_setting_value('0', ['value_type' => 'non_negative_int']))->toBe('0');
	expect(cacti_validate_textbox_setting_value('100000', ['value_type' => 'non_negative_int']))->toBe('100000');
	expect(cacti_validate_textbox_setting_value('-1', ['value_type' => 'non_negative_int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('-1', ['value_type' => 'non_negative_int', 'min' => -5]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('100001', ['value_type' => 'non_negative_int']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('15', ['value_type' => 'non_negative_int', 'min' => 10, 'max' => 20]))->toBe('15');

	// double and float (finite check + optional min/max)
	expect(cacti_validate_textbox_setting_value('3.14', ['value_type' => 'double']))->toBe('3.14');
	expect(cacti_validate_textbox_setting_value('-0.5', ['value_type' => 'float']))->toBe('-0.5');
	expect(cacti_validate_textbox_setting_value('2.5', ['value_type' => 'double', 'min' => 1.0, 'max' => 5.0]))->toBe('2.5');
	expect(cacti_validate_textbox_setting_value('0.5', ['value_type' => 'double', 'min' => 1.0, 'max' => 5.0]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('5.5', ['value_type' => 'float', 'min' => 1.0, 'max' => 5.0]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('INF', ['value_type' => 'double']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('-INF', ['value_type' => 'double']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('NAN', ['value_type' => 'double']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('1e309', ['value_type' => 'float']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('not-a-float', ['value_type' => 'double']))->toBeFalse();

	// time
	expect(cacti_validate_textbox_setting_value('07:00', ['value_type' => 'time']))->toBe('07:00');
	expect(cacti_validate_textbox_setting_value('23:59', ['value_type' => 'time']))->toBe('23:59');
	expect(cacti_validate_textbox_setting_value('7:00', ['value_type' => 'time']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('24:00', ['value_type' => 'time']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('12:60', ['value_type' => 'time']))->toBeFalse();

	// unknown value_type fails closed
	expect(cacti_validate_textbox_setting_value('123', ['value_type' => 'unknown']))->toBeFalse();

	// plugin fallback when value_type is omitted
	expect(cacti_validate_textbox_setting_value('2.75', ['default' => '1.5']))->toBe('2.75');
	expect(cacti_validate_textbox_setting_value('bad', ['default' => '1.5']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('0', ['default' => '100']))->toBe('0');
	expect(cacti_validate_textbox_setting_value('-1', ['default' => '100']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('custom label', ['default' => 'label', 'max_length' => 20]))->toBe('custom label');
	expect(cacti_validate_textbox_setting_value('freeform', []))->toBe('freeform');
});

test('all $settings_user textbox entries in include/global_settings.php declare value_type', function () {
	global $settings, $settings_user, $themes, $graph_views, $dateformats, $datechar, $graphs_per_page, $graph_timespans, $graph_timeshifts, $graph_weekdays;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';
	require CACTI_PATH_INCLUDE . '/global_settings.php';

	$expected = [
		'day_shift_start' => 'time',
		'day_shift_end'   => 'time',
		'default_height'  => 'positive_int',
		'default_width'   => 'positive_int',
		'min_tree_width'  => 'positive_int',
		'max_tree_width'  => 'positive_int',
		'title_size'      => 'positive_int',
		'legend_size'     => 'positive_int',
		'axis_size'       => 'positive_int',
		'unit_size'       => 'positive_int',
	];

	$found = [];

	foreach ($settings_user as $fields) {
		foreach ($fields as $name => $field) {
			if (($field['method'] ?? '') !== 'textbox') {
				continue;
			}

			expect($field)->toHaveKey('value_type');
			expect($field)->not->toHaveKey('type');
			$found[$name] = $field['value_type'];

			if ($field['value_type'] === 'time') {
				expect(cacti_validate_user_setting_value($name, '09:30', $field))->toBe('09:30');
				expect(cacti_validate_user_setting_value($name, '25:00', $field))->toBeFalse();
			} else {
				expect($field)->toHaveKey('min');
				expect($field)->toHaveKey('max');
				expect(cacti_validate_user_setting_value($name, (string) $field['min'], $field))->toBe((string) $field['min']);
				expect(cacti_validate_user_setting_value($name, (string) $field['max'], $field))->toBe((string) $field['max']);
				expect(cacti_validate_user_setting_value($name, '0', $field))->toBeFalse();
				expect(cacti_validate_user_setting_value($name, (string) ($field['max'] + 1), $field))->toBeFalse();
			}
		}
	}

	expect($found)->toBe($expected);
});

test('cacti_sanitize_positive_int_setting and cacti_sanitize_realtime_interval enforce numeric bounds', function () {
	expect(cacti_sanitize_positive_int_setting('30', 20, 1, 1000))->toBe(30);
	expect(cacti_sanitize_positive_int_setting('invalid', 20, 1, 1000))->toBe(20);
	expect(cacti_sanitize_positive_int_setting('0', 20, 1, 1000))->toBe(20);
	expect(cacti_sanitize_positive_int_setting('5000', 20, 1, 1000))->toBe(20);

	$allowed = [5 => '5 Seconds', 10 => '10 Seconds', 30 => '30 Seconds'];
	expect(cacti_sanitize_realtime_interval('10', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('30', $allowed, 10))->toBe(30);
	expect(cacti_sanitize_realtime_interval('15', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('10 extra', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('-1', $allowed, 10))->toBe(10);
});

test('auth_profile, graph_realtime, html_tree, and functions enforce hardening guards', function () use ($root) {
	$authProfile   = file_get_contents($root . '/auth_profile.php');
	$graphRealtime = file_get_contents($root . '/graph_realtime.php');
	$htmlTree      = file_get_contents($root . '/lib/html_tree.php');
	$functions     = file_get_contents($root . '/lib/functions.php');

	expect($authProfile)->toContain("case 'save':\n\t\tauth_profile_require_post();");
	expect($authProfile)->toContain("case 'update_data':\n\t\tauth_profile_require_post();");
	expect($authProfile)->toContain('cacti_user_is_guest_account($user, get_guest_account())');
	expect($authProfile)->toContain('cacti_validate_user_setting_value($name, $value, $settings[$name])');

	expect($graphRealtime)->toContain("'/^(true|false)$/'");
	expect($graphRealtime)->toContain('!is_graph_allowed($local_graph_id)');
	expect($graphRealtime)->toContain('cacti_sanitize_realtime_interval(');

	expect($htmlTree)->toContain('$graph_rows = filter_var($raw_graph_rows, FILTER_VALIDATE_INT);');
	expect($htmlTree)->toContain('max(0, (int) read_user_setting(\'min_tree_width\'');
	expect($htmlTree)->toContain('max(0, (int) read_user_setting(\'max_tree_width\'');

	expect($functions)->toContain('cacti_user_is_guest_account($user, get_guest_account())');
	expect($functions)->toContain('cacti_validate_user_setting_value(');
});
