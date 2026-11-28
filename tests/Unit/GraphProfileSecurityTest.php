<?php

require_once dirname(__DIR__, 2) . '/lib/functions.php';

test('profile dropdowns accept only declared choices', function () {
	$setting = array('method' => 'drop_array', 'array' => array(10 => '10', 20 => '20'));

	expect(cacti_user_setting_value_allowed($setting, '20'))->toBeTrue();
	foreach (array('17', 'old-setting', '', array('20'), false) as $value) {
		expect(cacti_user_setting_value_allowed($setting, $value))->toBeFalse();
	}
	$setting['none_value'] = 'None';
	expect(cacti_user_setting_value_allowed($setting, '0'))->toBeTrue();
});

test('personal layout preferences accept supported checkbox and locale values', function () {
	$toggle = array('method' => 'checkbox');
	$locale = array('method' => 'drop_language', 'array' => array('en-US' => 'English'));

	expect(cacti_user_setting_value_allowed($toggle, 'on'))->toBeTrue();
	expect(cacti_user_setting_value_allowed($toggle, ''))->toBeTrue();
	expect(cacti_user_setting_value_allowed($toggle, 'yes'))->toBeFalse();
	expect(cacti_user_setting_value_allowed($locale, 'en-US'))->toBeTrue();
	expect(cacti_user_setting_value_allowed($locale, 'old-setting'))->toBeFalse();
});

test('graph pagination produces positive integer bounds for saved and restored values', function () {
	expect(cacti_graph_pagination('3', '20', 10))->toBe(array(3, 20));
	foreach (array('old-setting', '', 0, -1, array(20)) as $rows) {
		expect(cacti_graph_pagination(1, $rows, 10))->toBe(array(1, 10));
	}
	expect(cacti_graph_pagination(-1, 20, 10))->toBe(array(1, 20));
	expect(cacti_graph_pagination(PHP_INT_MAX, 20, 10))->toBe(array(1, 20));
});
