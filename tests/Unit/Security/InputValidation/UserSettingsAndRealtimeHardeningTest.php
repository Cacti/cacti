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
require_once $root . '/lib/html_graph.php';

/**
 * Extract top-level key-value metadata for every field declared in $settings_user
 * from include/global_settings.php using PHP's tokenizer without executing the file.
 *
 * @param string $source Raw PHP source of include/global_settings.php.
 *
 * @return array<string, array<string, mixed>> Map of field name to scalar schema properties.
 */
function extract_settings_user_field_schemas(string $source) : array {
	$tokens = token_get_all($source);
	$count  = count($tokens);
	$index  = 0;

	// Locate `$settings_user = array(`
	while ($index < $count) {
		if (is_array($tokens[$index]) && $tokens[$index][0] === T_VARIABLE && $tokens[$index][1] === '$settings_user') {
			break;
		}
		$index++;
	}

	while ($index < $count && $tokens[$index] !== '(') {
		$index++;
	}

	$index++;
	$depth   = 1;
	$schemas = [];

	$current_field = null;
	$pending_key   = null;
	$expect_value  = false;
	$negate_next   = false;

	for (; $index < $count && $depth > 0; $index++) {
		$tok = $tokens[$index];

		if (is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
			continue;
		}

		if ($tok === '(') {
			$depth++;
			$expect_value = false;
			$negate_next  = false;

			continue;
		}

		if ($tok === ')') {
			if ($depth === 3) {
				$current_field = null;
			}
			$depth--;
			$expect_value = false;
			$negate_next  = false;

			continue;
		}

		if ($depth === 2 && is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
			$current_field           = substr($tok[1], 1, -1);
			$schemas[$current_field] = [];

			continue;
		}

		if ($depth !== 3 || $current_field === null) {
			continue;
		}

		if (is_array($tok) && $tok[0] === T_DOUBLE_ARROW) {
			$expect_value = true;
			$negate_next  = false;

			continue;
		}

		if ($tok === ',') {
			$pending_key  = null;
			$expect_value = false;
			$negate_next  = false;

			continue;
		}

		if (!$expect_value && is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
			$pending_key = substr($tok[1], 1, -1);

			continue;
		}

		if ($expect_value && $pending_key !== null) {
			if ($tok === '-') {
				$negate_next = true;

				continue;
			}

			if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
				$schemas[$current_field][$pending_key] = substr($tok[1], 1, -1);
			} elseif (is_array($tok) && $tok[0] === T_LNUMBER) {
				$val                                   = (int) $tok[1];
				$schemas[$current_field][$pending_key] = $negate_next ? -$val : $val;
			} elseif (is_array($tok) && $tok[0] === T_DNUMBER) {
				$val                                   = (float) $tok[1];
				$schemas[$current_field][$pending_key] = $negate_next ? -$val : $val;
			}

			$pending_key  = null;
			$expect_value = false;
			$negate_next  = false;
		}
	}

	return $schemas;
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
	expect(cacti_validate_user_setting_value('show_graph_title', true, $checkbox))->toBe('on');
	expect(cacti_validate_user_setting_value('show_graph_title', '', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'false', $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', false, $checkbox))->toBe('');
	expect(cacti_validate_user_setting_value('show_graph_title', 'invalid', $checkbox))->toBeFalse();

	$dropSql = ['method' => 'drop_sql', 'default' => '0'];
	expect(cacti_validate_user_setting_value('default_tree_id', '0', $dropSql))->toBe('0');
	expect(cacti_validate_user_setting_value('default_tree_id', '4', $dropSql))->toBe('4');
	expect(cacti_validate_user_setting_value('default_tree_id', '-1', $dropSql))->toBeFalse();
	expect(cacti_validate_user_setting_value('default_tree_id', '4 extra', $dropSql))->toBeFalse();

	$fontField = ['method' => 'font', 'max_length' => '100'];
	expect(cacti_validate_user_setting_value('title_font', 'DejaVuSans', $fontField))->toBe('DejaVuSans');
	expect(cacti_validate_user_setting_value('title_font', 'DejaVu;bad', $fontField))->toBeFalse();
	expect(cacti_validate_user_setting_value('title_font', "DejaVu\nLine2", $fontField))->toBeFalse();

	expect(cacti_validate_user_setting_value('', 'value', ['method' => 'textbox']))->toBeFalse();
	expect(cacti_validate_user_setting_value('bad_method', 'value', ['method' => 'unknown']))->toBeFalse();
	expect(cacti_validate_user_setting_value('array_val', ['x'], ['method' => 'textbox']))->toBeFalse();
	expect(cacti_validate_user_setting_value('null_val', null, ['method' => 'textbox']))->toBeFalse();
});

test('cacti_validate_textbox_setting_value enforces every value_type and bound', function () {
	// string
	$stringField = ['method' => 'textbox', 'value_type' => 'string', 'max_length' => '10'];
	expect(cacti_validate_textbox_setting_value('hello', $stringField))->toBe('hello');
	expect(cacti_validate_textbox_setting_value('1234567890', $stringField))->toBe('1234567890');
	expect(cacti_validate_textbox_setting_value('12345678901', $stringField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value("bad\nline", $stringField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value("bad\x00nul", $stringField))->toBeFalse();

	$defaultMaxString = ['method' => 'textbox', 'value_type' => 'string'];
	expect(cacti_validate_textbox_setting_value(str_repeat('a', 255), $defaultMaxString))->toBe(str_repeat('a', 255));
	expect(cacti_validate_textbox_setting_value(str_repeat('a', 256), $defaultMaxString))->toBeFalse();

	// int (signed, optional min/max)
	$signedInt = ['method' => 'textbox', 'value_type' => 'int', 'min' => -50, 'max' => 50];
	expect(cacti_validate_textbox_setting_value('-50', $signedInt))->toBe('-50');
	expect(cacti_validate_textbox_setting_value('0', $signedInt))->toBe('0');
	expect(cacti_validate_textbox_setting_value('50', $signedInt))->toBe('50');
	expect(cacti_validate_textbox_setting_value('-51', $signedInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('51', $signedInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('12.5', $signedInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('99999999999999999999999999999', $signedInt))->toBeFalse();

	$unboundedInt = ['method' => 'textbox', 'value_type' => 'int'];
	expect(cacti_validate_textbox_setting_value('-250000', $unboundedInt))->toBe('-250000');
	expect(cacti_validate_textbox_setting_value('250000', $unboundedInt))->toBe('250000');
	expect(cacti_validate_textbox_setting_value('99999999999999999999999999999', $unboundedInt))->toBeFalse();

	// positive_int (>= 1, default max 100000, optional min/max)
	$positiveInt = ['method' => 'textbox', 'value_type' => 'positive_int', 'min' => 1, 'max' => 100];
	expect(cacti_validate_textbox_setting_value('1', $positiveInt))->toBe('1');
	expect(cacti_validate_textbox_setting_value('100', $positiveInt))->toBe('100');
	expect(cacti_validate_textbox_setting_value('0', $positiveInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('-5', $positiveInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('101', $positiveInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('50; extra', $positiveInt))->toBeFalse();

	$positiveDefaultMax = ['method' => 'textbox', 'value_type' => 'positive_int'];
	expect(cacti_validate_textbox_setting_value('1', $positiveDefaultMax))->toBe('1');
	expect(cacti_validate_textbox_setting_value('100000', $positiveDefaultMax))->toBe('100000');
	expect(cacti_validate_textbox_setting_value('0', $positiveDefaultMax))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('100001', $positiveDefaultMax))->toBeFalse();

	// positive_int enforces floor >= 1 even if schema specifies min <= 0
	$positiveFloor = ['method' => 'textbox', 'value_type' => 'positive_int', 'min' => 0];
	expect(cacti_validate_textbox_setting_value('0', $positiveFloor))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('1', $positiveFloor))->toBe('1');

	// non_negative_int (>= 0, default max 100000, optional min/max)
	$nonNegativeInt = ['method' => 'textbox', 'value_type' => 'non_negative_int'];
	expect(cacti_validate_textbox_setting_value('0', $nonNegativeInt))->toBe('0');
	expect(cacti_validate_textbox_setting_value('100000', $nonNegativeInt))->toBe('100000');
	expect(cacti_validate_textbox_setting_value('-1', $nonNegativeInt))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('100001', $nonNegativeInt))->toBeFalse();

	$nonNegativeBounded = ['method' => 'textbox', 'value_type' => 'non_negative_int', 'min' => -5, 'max' => 25];
	expect(cacti_validate_textbox_setting_value('-1', $nonNegativeBounded))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('0', $nonNegativeBounded))->toBe('0');
	expect(cacti_validate_textbox_setting_value('25', $nonNegativeBounded))->toBe('25');
	expect(cacti_validate_textbox_setting_value('26', $nonNegativeBounded))->toBeFalse();

	// double and float (finite, optional min/max)
	$doubleField = ['method' => 'textbox', 'value_type' => 'double', 'min' => -10.5, 'max' => 99.5];
	expect(cacti_validate_textbox_setting_value('-10.5', $doubleField))->toBe('-10.5');
	expect(cacti_validate_textbox_setting_value('0', $doubleField))->toBe('0');
	expect(cacti_validate_textbox_setting_value('-0.0', $doubleField))->toBe('0');
	expect(cacti_validate_textbox_setting_value('42.25', $doubleField))->toBe('42.25');
	expect(cacti_validate_textbox_setting_value('99.5', $doubleField))->toBe('99.5');
	expect(cacti_validate_textbox_setting_value('-10.6', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('99.6', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('NAN', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('INF', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('-INF', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('1e309', $doubleField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('not-a-float', $doubleField))->toBeFalse();

	$floatField = ['method' => 'textbox', 'value_type' => 'float', 'min' => 0.0, 'max' => 1.0];
	expect(cacti_validate_textbox_setting_value('0.5', $floatField))->toBe('0.5');
	expect(cacti_validate_textbox_setting_value('1.0', $floatField))->toBe('1');
	expect(cacti_validate_textbox_setting_value('-0.01', $floatField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('1.01', $floatField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('1e309', ['method' => 'textbox', 'value_type' => 'float']))->toBeFalse();

	// time (24-hour HH:MM)
	$timeField = ['method' => 'textbox', 'value_type' => 'time', 'max_length' => '5'];
	expect(cacti_validate_textbox_setting_value('00:00', $timeField))->toBe('00:00');
	expect(cacti_validate_textbox_setting_value('08:30', $timeField))->toBe('08:30');
	expect(cacti_validate_textbox_setting_value('23:59', $timeField))->toBe('23:59');
	expect(cacti_validate_textbox_setting_value('24:00', $timeField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('25:99', $timeField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('8:30', $timeField))->toBeFalse();
	expect(cacti_validate_textbox_setting_value("08:30\n", $timeField))->toBeFalse();

	// invalid/unknown value_type or invalid min/max config fail closed
	expect(cacti_validate_textbox_setting_value('10', ['method' => 'textbox', 'value_type' => 'unknown']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('10', ['method' => 'textbox', 'value_type' => 123]))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('10', ['method' => 'textbox', 'value_type' => 'int', 'min' => 'bad']))->toBeFalse();
	expect(cacti_validate_textbox_setting_value('10', ['method' => 'textbox', 'value_type' => 'double', 'max' => 'bad']))->toBeFalse();
});

test('cacti_validate_textbox_setting_value provides backwards-compatible fallback for plugins omitting value_type', function () {
	// Numeric integer default -> non_negative_int
	$pluginInt = ['method' => 'textbox', 'default' => '100', 'max_length' => '5'];
	expect(cacti_validate_user_setting_value('plugin_timeout', '200', $pluginInt))->toBe('200');
	expect(cacti_validate_user_setting_value('plugin_timeout', '0', $pluginInt))->toBe('0');
	expect(cacti_validate_user_setting_value('plugin_timeout', '-5', $pluginInt))->toBeFalse();
	expect(cacti_validate_user_setting_value('plugin_timeout', '200; extra', $pluginInt))->toBeFalse();
	expect(cacti_validate_user_setting_value('plugin_timeout', '100001', $pluginInt))->toBeFalse();

	// Numeric decimal default -> double
	$pluginDouble = ['method' => 'textbox', 'default' => '0.75'];
	expect(cacti_validate_user_setting_value('plugin_ratio', '1.25', $pluginDouble))->toBe('1.25');
	expect(cacti_validate_user_setting_value('plugin_ratio', '-2.5', $pluginDouble))->toBe('-2.5');
	expect(cacti_validate_user_setting_value('plugin_ratio', 'invalid', $pluginDouble))->toBeFalse();
	expect(cacti_validate_user_setting_value('plugin_ratio', '1e309', $pluginDouble))->toBeFalse();

	// Non-numeric or missing default -> string
	$pluginString = ['method' => 'textbox', 'default' => 'default_label', 'max_length' => '16'];
	expect(cacti_validate_user_setting_value('plugin_label', 'custom_label', $pluginString))->toBe('custom_label');
	expect(cacti_validate_user_setting_value('plugin_label', str_repeat('x', 17), $pluginString))->toBeFalse();

	$pluginNoDefault = ['method' => 'textbox'];
	expect(cacti_validate_user_setting_value('plugin_note', 'plain text', $pluginNoDefault))->toBe('plain text');
});

test('all $settings_user textbox definitions in include/global_settings.php declare explicit value_type', function () use ($root) {
	$source  = file_get_contents($root . '/include/global_settings.php');
	$schemas = extract_settings_user_field_schemas($source);

	$textboxFields = [];

	foreach ($schemas as $fieldName => $field) {
		if (($field['method'] ?? null) === 'textbox') {
			$textboxFields[$fieldName] = $field;
		}
	}

	$expectedTypes = [
		'day_shift_start' => ['value_type' => 'time'],
		'day_shift_end'   => ['value_type' => 'time'],
		'default_height'  => ['value_type' => 'positive_int', 'min' => 1, 'max' => 10000],
		'default_width'   => ['value_type' => 'positive_int', 'min' => 1, 'max' => 10000],
		'min_tree_width'  => ['value_type' => 'positive_int', 'min' => 1, 'max' => 10000],
		'max_tree_width'  => ['value_type' => 'positive_int', 'min' => 1, 'max' => 10000],
		'title_size'      => ['value_type' => 'positive_int', 'min' => 1, 'max' => 100],
		'legend_size'     => ['value_type' => 'positive_int', 'min' => 1, 'max' => 100],
		'axis_size'       => ['value_type' => 'positive_int', 'min' => 1, 'max' => 100],
		'unit_size'       => ['value_type' => 'positive_int', 'min' => 1, 'max' => 100],
	];

	expect(array_keys($textboxFields))->toEqual(array_keys($expectedTypes));

	foreach ($textboxFields as $fieldName => $field) {
		expect($field)->toHaveKey('value_type');
		expect($field['value_type'])->toBe($expectedTypes[$fieldName]['value_type']);

		if (isset($expectedTypes[$fieldName]['min'])) {
			expect($field['min'] ?? null)->toBe($expectedTypes[$fieldName]['min']);
			expect($field['max'] ?? null)->toBe($expectedTypes[$fieldName]['max']);

			expect(cacti_validate_user_setting_value($fieldName, (string) $field['min'], $field))->toBe((string) $field['min']);
			expect(cacti_validate_user_setting_value($fieldName, (string) $field['max'], $field))->toBe((string) $field['max']);
			expect(cacti_validate_user_setting_value($fieldName, '0', $field))->toBeFalse();
			expect(cacti_validate_user_setting_value($fieldName, (string) ($field['max'] + 1), $field))->toBeFalse();
		} else {
			expect(cacti_validate_user_setting_value($fieldName, '07:00', $field))->toBe('07:00');
			expect(cacti_validate_user_setting_value($fieldName, '24:00', $field))->toBeFalse();
		}
	}

	expect(function_exists('cacti_numeric_user_setting_names'))->toBeFalse();
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

test('cacti_validate_realtime_hash accepts only bounded hexadecimal strings', function () {
	expect(cacti_validate_realtime_hash('0123456789abcdef'))->toBeTrue();
	expect(cacti_validate_realtime_hash(str_repeat('a', 64)))->toBeTrue();
	expect(cacti_validate_realtime_hash(''))->toBeFalse();
	expect(cacti_validate_realtime_hash(str_repeat('a', 65)))->toBeFalse();
	expect(cacti_validate_realtime_hash('../outside'))->toBeFalse();
	expect(cacti_validate_realtime_hash('abc def'))->toBeFalse();
});

test('html_graph_validate_columns and html_graph_validate_page_size reject non-integer settings', function () {
	expect(html_graph_validate_columns('4'))->toBe(4);
	expect(html_graph_validate_columns('0'))->toBeFalse();
	expect(html_graph_validate_columns('99'))->toBeFalse();
	expect(html_graph_validate_columns('2, invalid'))->toBeFalse();

	$GLOBALS['graphs_per_page'] = [10 => '10 Graphs', 20 => '20 Graphs', 25 => '25 Graphs'];
	$GLOBALS['item_rows']       = [10 => '10', 20 => '20'];

	expect(html_graph_validate_page_size('25'))->toBe(25);
	expect(html_graph_validate_page_size('0'))->toBeFalse();
	expect(html_graph_validate_page_size('5000'))->toBeFalse();
	expect(html_graph_validate_page_size('20, extra'))->toBeFalse();
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
