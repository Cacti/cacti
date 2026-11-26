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

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

function bcms_locales_verdict() : array {
	static $verdict;

	if ($verdict === null) {
		$verdict = cacti_test_isolated_probe(__DIR__ . '/fixtures/bcms_locales_probe.php');
	}

	return $verdict;
}

test('get_list_of_locales registers Bosnian, Croatian, and Serbian in alphabetical language order', function () {
	$verdict = bcms_locales_verdict();

	$expected = [
		'bs-BA' => ['language' => 'Bosnian',  'direction' => 'ltr', 'country' => 'ba', 'filename' => 'bosnian_bosnia_herzegovina'],
		'hr-HR' => ['language' => 'Croatian', 'direction' => 'ltr', 'country' => 'hr', 'filename' => 'croatian_croatia'],
		'sr-RS' => ['language' => 'Serbian',  'direction' => 'ltr', 'country' => 'rs', 'filename' => 'serbian_serbia'],
	];

	foreach ($expected as $code => $meta) {
		expect($verdict['locales'])->toHaveKey($code);
		expect($verdict['locales'][$code])->toBe($meta);
		expect(file_exists(dirname(__DIR__, 2) . '/locales/po/' . $code . '.po'))->toBeTrue();
		expect(file_exists(dirname(__DIR__, 2) . '/locales/LC_MESSAGES/' . $code . '.mo'))->toBeTrue();
	}

	expect($verdict['sorted'])->toBeTrue();
});

test('repair_locale resolves Bosnian, Croatian, and Serbian locale codes', function () {
	$verdict = bcms_locales_verdict();

	expect($verdict['repaired'])->toBe([
		'bs'    => 'bs-BA',
		'bs_BA' => 'bs-BA',
		'hr'    => 'hr-HR',
		'hr_HR' => 'hr-HR',
		'sr'    => 'sr-RS',
		'sr_RS' => 'sr-RS',
	]);
});
