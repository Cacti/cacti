<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/install/functions.php';
require_once dirname(__DIR__, 2) . '/lib/installer.php';

test('installer accepts only complete browser selection payloads', function () {
	$method   = new ReflectionMethod(Installer::class, 'isCompleteSelectionPayload');
	$expected = ['chk_template_one', 'chk_template_two'];

	expect($method->invoke(null, [
		'chk_template_one' => true,
		'chk_template_two' => false,
	], $expected))->toBeTrue()
		->and($method->invoke(null, [
			'all'              => true,
			'chk_template_one' => '1',
			'chk_template_two' => '0',
		], $expected))->toBeTrue()
		->and($method->invoke(null, [
			'chk_template_one' => true,
		], $expected))->toBeFalse()
		->and($method->invoke(null, [
			'chk_template_one'   => true,
			'chk_template_two'   => false,
			'chk_template_three' => true,
		], $expected))->toBeFalse()
		->and($method->invoke(null, [
			'chk_template_one' => true,
			'chk_template_two' => 'unexpected',
		], $expected))->toBeFalse();
});

test('installer requires payload keys only for rendered table controls', function () {
	$method = new ReflectionMethod(Installer::class, 'isTableSelectable');

	expect($method->invoke(null, ['Rows' => 0]))->toBeTrue()
		->and($method->invoke(null, ['Rows' => '999999']))->toBeTrue()
		->and($method->invoke(null, ['Rows' => 1000000]))->toBeFalse()
		->and($method->invoke(null, ['Rows' => 'unknown']))->toBeFalse()
		->and($method->invoke(null, []))->toBeFalse();
});
