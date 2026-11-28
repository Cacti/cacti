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

	$numericTextbox = ['method' => 'textbox', 'default' => '100', 'max_length' => '5'];
	expect(cacti_validate_user_setting_value('min_tree_width', '200', $numericTextbox))->toBe('200');
	expect(cacti_validate_user_setting_value('min_tree_width', '-5', $numericTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('min_tree_width', '200; extra', $numericTextbox))->toBeFalse();

	$timeTextbox = ['method' => 'textbox', 'default' => '07:00', 'max_length' => '5'];
	expect(cacti_validate_user_setting_value('day_shift_start', '08:30', $timeTextbox))->toBe('08:30');
	expect(cacti_validate_user_setting_value('day_shift_start', '25:99', $timeTextbox))->toBeFalse();

	$dropSql = ['method' => 'drop_sql', 'default' => '0'];
	expect(cacti_validate_user_setting_value('default_tree_id', '4', $dropSql))->toBe('4');
	expect(cacti_validate_user_setting_value('default_tree_id', '4 extra', $dropSql))->toBeFalse();

	$fontField = ['method' => 'font', 'max_length' => '100'];
	expect(cacti_validate_user_setting_value('title_font', 'DejaVuSans', $fontField))->toBe('DejaVuSans');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVu;bad', $fontField))->toBeFalse();
	expect(cacti_validate_user_setting_value('title_font', "DejaVu\nLine2", $fontField))->toBeFalse();
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
