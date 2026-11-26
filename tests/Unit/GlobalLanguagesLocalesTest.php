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

test('get_list_of_locales registers the 15 African locales in alphabetical language order', function () {
	$locales = get_list_of_locales();

	$expected = [
		'af-ZA' => ['language' => 'Afrikaans',        'direction' => 'ltr', 'country' => 'za', 'filename' => 'afrikaans_south_africa'],
		'am-ET' => ['language' => 'Amharic',          'direction' => 'ltr', 'country' => 'et', 'filename' => 'amharic_ethiopia'],
		'ar-EG' => ['language' => 'Arabic (Egypt)',   'direction' => 'rtl', 'country' => 'eg', 'filename' => 'arabic_egypt'],
		'ar-MA' => ['language' => 'Arabic (Morocco)', 'direction' => 'rtl', 'country' => 'ma', 'filename' => 'arabic_morocco'],
		'fr-MA' => ['language' => 'French (Morocco)', 'direction' => 'ltr', 'country' => 'ma', 'filename' => 'french_morocco'],
		'fr-SN' => ['language' => 'French (Senegal)', 'direction' => 'ltr', 'country' => 'sn', 'filename' => 'french_senegal'],
		'ha-NG' => ['language' => 'Hausa',            'direction' => 'ltr', 'country' => 'ng', 'filename' => 'hausa_nigeria'],
		'ig-NG' => ['language' => 'Igbo',             'direction' => 'ltr', 'country' => 'ng', 'filename' => 'igbo_nigeria'],
		'rw-RW' => ['language' => 'Kinyarwanda',      'direction' => 'ltr', 'country' => 'rw', 'filename' => 'kinyarwanda_rwanda'],
		'mg-MG' => ['language' => 'Malagasy',         'direction' => 'ltr', 'country' => 'mg', 'filename' => 'malagasy_madagascar'],
		'so-SO' => ['language' => 'Somali',           'direction' => 'ltr', 'country' => 'so', 'filename' => 'somali_somalia'],
		'sw-KE' => ['language' => 'Swahili',          'direction' => 'ltr', 'country' => 'ke', 'filename' => 'swahili_kenya'],
		'xh-ZA' => ['language' => 'Xhosa',            'direction' => 'ltr', 'country' => 'za', 'filename' => 'xhosa_south_africa'],
		'yo-NG' => ['language' => 'Yoruba',           'direction' => 'ltr', 'country' => 'ng', 'filename' => 'yoruba_nigeria'],
		'zu-ZA' => ['language' => 'Zulu',             'direction' => 'ltr', 'country' => 'za', 'filename' => 'zulu_south_africa'],
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

test('repair_locale preserves default Arabic and French fallbacks and resolves regional locales', function () {
	global $lang2locale;
	$lang2locale = get_list_of_locales();

	expect(repair_locale('ar'))->toBe('ar-SA');
	expect(repair_locale('fr'))->toBe('fr-FR');
	expect(repair_locale('ar-EG'))->toBe('ar-EG');
	expect(repair_locale('ar-MA'))->toBe('ar-MA');
	expect(repair_locale('fr-MA'))->toBe('fr-MA');
	expect(repair_locale('fr-SN'))->toBe('fr-SN');
	expect(repair_locale('af_ZA'))->toBe('af-ZA');
	expect(repair_locale('sw'))->toBe('sw-KE');
});
