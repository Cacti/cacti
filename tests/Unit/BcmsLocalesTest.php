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

test('get_list_of_locales registers Bosnian, Croatian, and Serbian in alphabetical language order', function () {
	$locales = get_list_of_locales();

	$expected = [
		'bs-BA' => ['language' => 'Bosnian',  'direction' => 'ltr', 'country' => 'ba', 'filename' => 'bosnian_bosnia_herzegovina'],
		'hr-HR' => ['language' => 'Croatian', 'direction' => 'ltr', 'country' => 'hr', 'filename' => 'croatian_croatia'],
		'sr-RS' => ['language' => 'Serbian',  'direction' => 'ltr', 'country' => 'rs', 'filename' => 'serbian_serbia'],
	];

	foreach ($expected as $code => $meta) {
		expect($locales)->toHaveKey($code);
		expect($locales[$code])->toBe($meta);
		expect(file_exists(dirname(__DIR__, 2) . '/locales/po/' . $code . '.po'))->toBeTrue();
		expect(file_exists(dirname(__DIR__, 2) . '/locales/LC_MESSAGES/' . $code . '.mo'))->toBeTrue();
	}

	$languages = array_column($locales, 'language');
	$sorted    = $languages;
	sort($sorted, SORT_STRING);
	expect($languages)->toBe($sorted);
});

test('repair_locale resolves Bosnian, Croatian, and Serbian locale codes', function () {
	global $lang2locale;
	$lang2locale = get_list_of_locales();

	expect(repair_locale('bs'))->toBe('bs-BA');
	expect(repair_locale('bs_BA'))->toBe('bs-BA');
	expect(repair_locale('hr'))->toBe('hr-HR');
	expect(repair_locale('hr_HR'))->toBe('hr-HR');
	expect(repair_locale('sr'))->toBe('sr-RS');
	expect(repair_locale('sr_RS'))->toBe('sr-RS');
});
