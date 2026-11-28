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
 * preview/tree pagination validation, and realtime graph access hardening.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/user_setting_validation.php';

if (!function_exists('_test_html_graph_validate_columns')) {
	$htmlGraphSource = file_get_contents($root . '/lib/html_graph.php');
	preg_match('/^function html_graph_validate_columns\([^)]*\)\s*\{.*?^\}/sm', $htmlGraphSource, $colMatch);
	preg_match('/^function html_graph_validate_page_size\([^)]*\)\s*\{.*?^\}/sm', $htmlGraphSource, $pageMatch);
	$colSrc  = preg_replace('/^function html_graph_validate_columns\(/m', 'function _test_html_graph_validate_columns(', $colMatch[0]);
	$pageSrc = preg_replace('/^function html_graph_validate_page_size\(/m', 'function _test_html_graph_validate_page_size(', $pageMatch[0]);
	eval($colSrc);  // nosemgrep: php.lang.security.eval-use.eval-use
	eval($pageSrc); // nosemgrep: php.lang.security.eval-use.eval-use
}

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
	$dropArray = array(
		'method' => 'drop_array',
		'array'  => array(
			10 => '10 Graphs',
			20 => '20 Graphs',
			50 => '50 Graphs',
		),
		'default' => 20,
	);

	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '20', $dropArray))->toBe('20');
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', 50, $dropArray))->toBe('50');
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '999', $dropArray))->toBeFalse();
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', '20, 10', $dropArray))->toBeFalse();
	expect(cacti_validate_user_setting_value('preview_graphs_per_page', 'not-a-number', $dropArray))->toBeFalse();

	$checkbox = array('method' => 'checkbox', 'default' => 'on');
	expect(cacti_validate_user_setting_value('show_graph_title', 'on', $checkbox))->toBe('on');
	expect(cacti_validate_user_setting_value('show_graph_title', 'true', $checkbox))->toBe('on');
	expect(cacti_validate_user_setting_value('show_graph_title', '', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'false', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'invalid', $checkbox))->toBeFalse();

	$numericTextbox = array('method' => 'textbox', 'default' => '100', 'max_length' => '5');
	expect(cacti_validate_user_setting_value('min_tree_width', '200', $numericTextbox))->toBe('200');
	expect(cacti_validate_user_setting_value('min_tree_width', '-5', $numericTextbox))->toBeFalse();
	expect(cacti_validate_user_setting_value('min_tree_width', '200; extra', $numericTextbox))->toBeFalse();

	$timeTextbox = array('method' => 'textbox', 'default' => '07:00', 'max_length' => '5');
	expect(cacti_validate_user_setting_value('day_shift_start', '08:30', $timeTextbox))->toBe('08:30');
	expect(cacti_validate_user_setting_value('day_shift_start', '25:99', $timeTextbox))->toBeFalse();

	$dropSql = array('method' => 'drop_sql', 'default' => '0');
	expect(cacti_validate_user_setting_value('default_tree_id', '4', $dropSql))->toBe('4');
	expect(cacti_validate_user_setting_value('default_tree_id', '4 extra', $dropSql))->toBeFalse();

	$fontField = array('method' => 'font', 'max_length' => '100');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVuSans', $fontField))->toBe('DejaVuSans');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVu;bad', $fontField))->toBeFalse();
	expect(cacti_validate_user_setting_value('title_font', "DejaVu\nLine2", $fontField))->toBeFalse();
});

test('cacti_sanitize_positive_int_setting and cacti_sanitize_realtime_interval enforce numeric bounds', function () {
	expect(cacti_sanitize_positive_int_setting('30', 20, 1, 1000))->toBe(30);
	expect(cacti_sanitize_positive_int_setting('invalid', 20, 1, 1000))->toBe(20);
	expect(cacti_sanitize_positive_int_setting('0', 20, 1, 1000))->toBe(20);
	expect(cacti_sanitize_positive_int_setting('5000', 20, 1, 1000))->toBe(20);

	$allowed = array(5 => '5 Seconds', 10 => '10 Seconds', 30 => '30 Seconds');
	expect(cacti_sanitize_realtime_interval('10', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('30', $allowed, 10))->toBe(30);
	expect(cacti_sanitize_realtime_interval('15', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('10 extra', $allowed, 10))->toBe(10);
	expect(cacti_sanitize_realtime_interval('-1', $allowed, 10))->toBe(10);
});

test('cacti_validate_realtime_hash accepts only bounded hexadecimal strings', function () {
	expect(cacti_validate_realtime_hash('0123456789abcdef'))->toBeTrue();
	expect(cacti_validate_realtime_hash(str_repeat('a', 64)))->toBeTrue();
	expect(cacti_validate_realtime_hash(''))->toBeFalse();
	expect(cacti_validate_realtime_hash(str_repeat('a', 65)))->toBeFalse();
	expect(cacti_validate_realtime_hash('../outside'))->toBeFalse();
	expect(cacti_validate_realtime_hash('abc def'))->toBeFalse();
});

test('html_graph_validate_columns and html_graph_validate_page_size reject non-integer settings', function () {
	expect(_test_html_graph_validate_columns('4'))->toBe(4);
	expect(_test_html_graph_validate_columns('0'))->toBeFalse();
	expect(_test_html_graph_validate_columns('99'))->toBeFalse();
	expect(_test_html_graph_validate_columns('2, invalid'))->toBeFalse();

	$GLOBALS['graphs_per_page'] = array(10 => '10 Graphs', 20 => '20 Graphs', 25 => '25 Graphs');
	$GLOBALS['item_rows']       = array(10 => '10', 20 => '20');

	expect(_test_html_graph_validate_page_size('25'))->toBe(25);
	expect(_test_html_graph_validate_page_size('0'))->toBeFalse();
	expect(_test_html_graph_validate_page_size('5000'))->toBeFalse();
	expect(_test_html_graph_validate_page_size('20, extra'))->toBeFalse();
});

test('auth_profile, graph_realtime, graph_view, and html_tree enforce hardening guards', function () use ($root) {
	$authProfile   = file_get_contents($root . '/auth_profile.php');
	$graphRealtime = file_get_contents($root . '/graph_realtime.php');
	$graphView     = file_get_contents($root . '/graph_view.php');
	$htmlTree      = file_get_contents($root . '/lib/html_tree.php');
	$functions     = file_get_contents($root . '/lib/functions.php');

	expect($authProfile)->toContain("case 'update_data':\n\t\tcsrf_require_post();");
	expect($authProfile)->toContain('cacti_user_is_guest_account($user, get_guest_account())');
	expect($authProfile)->toContain('cacti_validate_user_setting_value($name, $value, $settings[$name])');

	expect($graphRealtime)->toContain("'/^(true|false)$/'");
	expect($graphRealtime)->toContain('!is_graph_allowed($local_graph_id)');
	expect($graphRealtime)->toContain('cacti_sanitize_realtime_interval(');
	expect($graphRealtime)->toContain('cacti_escapeshellarg($hash)');
	expect($graphRealtime)->toContain('cacti_validate_realtime_hash(');

	expect($graphView)->toContain('$graphs_per_page_limit = filter_var(get_request_var(\'graphs\'), FILTER_VALIDATE_INT);');
	expect($graphView)->toContain('max(0, (int) read_user_setting(\'min_tree_width\'');
	expect($graphView)->toContain('max(0, (int) read_user_setting(\'max_tree_width\'');

	expect($htmlTree)->toContain('$default_tree_graphs = filter_var(read_user_setting(\'treeview_graphs_per_page\'');
	expect($functions)->toContain('cacti_user_is_guest_account($user, get_guest_account())');
	expect($functions)->toContain('cacti_validate_user_setting_value(');
});
