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

require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once CACTI_PATH_LIBRARY . '/api_scheduler.php';
require_once CACTI_PATH_LIBRARY . '/package.php';

test('__() formats positional specifiers including reordered arguments', function () {
	expect(__('Version %1$s | %2$s', '1.3.0', '2026'))->toBe('Version 1.3.0 | 2026')
		->and(__('Page %2$s returned error %1$d (%3$.2f%%)', 42, 'graphs.php', 12.5))
		->toBe('Page graphs.php returned error 42 (12.50%)');
});

test('__() preserves %% until after sprintf() and avoids PHP 8 ValueError', function () {
	global $i18n;

	$origI18n = $i18n;

	try {
		$i18n['testdomain'] = new class {
			public function translate(string $text) : string {
				return $text;
			}

			public function gettext(string $text) : string {
				return $text;
			}
		};

		expect(__('> %d %% of Devices Globally', 50, 'testdomain'))
			->toBe('> 50 % of Devices Globally')
			->and(__('%% Dev [TIP:%s]', 'MAX', 'testdomain'))
			->toBe('% Dev [TIP:MAX]')
			->and(__('Alert Hi for %s (%s %%)', 'Router1', '95', 'testdomain'))
			->toBe('Alert Hi for Router1 (95 %)')
			->and(__('80% of total memory', 'testdomain'))
			->toBe('80% of total memory')
			->and(__('Literal %%s token', 'testdomain'))
			->toBe('Literal %s token')
			->and(__gettext('100%% ready', 'cacti', true))
			->toBe('100% ready')
			->and(__gettext('100%% ready', 'cacti', false))
			->toBe('100%% ready');
	} finally {
		$i18n = $origI18n;
	}
});

test('__x() handles literal percent, positional specifiers, and %% without crashing', function () {
	expect(__x('context', '80% of total memory'))->toBe('80% of total memory')
		->and(__x('context', 'Alert %1$s (%2$s %%)', 'HostA', '90'))
		->toBe('Alert HostA (90 %)')
		->and(__x('context', 'Value %2$d for %1$s', 'cpu', 7))
		->toBe('Value 7 for cpu')
		->and(__x('context', '100%% done', 'ignored'))
		->toBe('100% done');
});

test('number_format_i18n() normalizes ICU locale keys, applies baseu scaling, and preserves LC_NUMERIC', function () {
	global $cacti_locale, $cacti_country;

	$origLocale  = $cacti_locale ?? null;
	$origCountry = $cacti_country ?? null;
	$beforeNum   = setlocale(LC_NUMERIC, '0');

	try {
		$cacti_locale  = 'de-DE';
		$cacti_country = 'de';

		expect(number_format_i18n(1234.56, 2, 0))->toBe('1.234,56')
			->and(number_format_i18n(2048, 2, 1024))->toBe('2 K')
			->and(number_format_i18n(2097152, 2, 1024))->toBe('2 M')
			->and(number_format_i18n(2147483648, 2, 1024))->toBe('2 G')
			->and(number_format_i18n(2199023255552, 2, 1024))->toBe('2 T')
			->and(number_format_i18n(2048, -1, 1024))->toBe('2.048')
			->and(number_format_i18n(2048, null, 1024))->toBe('2.048')
			->and(number_format_i18n(2048, 0, 1024))->toBe('2.048');

		$cacti_locale  = 'en';
		$cacti_country = 'us';

		expect(number_format_i18n(1234.56, 2, 0))->toBe('1,234.56');
		expect(setlocale(LC_NUMERIC, '0'))->toBe($beforeNum);
	} finally {
		$cacti_locale  = $origLocale;
		$cacti_country = $origCountry;
	}
});

test('i18n_text_debug() checks writability of i18n_text_log rather than i18n_log', function () {
	global $config;

	$origTextLog = $config['i18n_text_log'] ?? null;
	$origLog     = $config['i18n_log'] ?? null;
	$tmpFile     = tempnam(sys_get_temp_dir(), 'cacti-i18n-text-log-');

	try {
		$config['i18n_log']      = '';
		$config['i18n_text_log'] = $tmpFile;

		i18n_text_debug('debug-line');

		expect(file_get_contents($tmpFile))->toContain('debug-line');
	} finally {
		if ($origTextLog === null) {
			unset($config['i18n_text_log']);
		} else {
			$config['i18n_text_log'] = $origTextLog;
		}

		if ($origLog === null) {
			unset($config['i18n_log']);
		} else {
			$config['i18n_log'] = $origLog;
		}

		if ($tmpFile !== false && file_exists($tmpFile)) {
			unlink($tmpFile);
		}
	}
});

test('get_locale_direction() returns rtl for Arabic/Hebrew and ltr otherwise', function () {
	expect(get_locale_direction('ar-SA'))->toBe('rtl')
		->and(get_locale_direction('he-IL'))->toBe('rtl')
		->and(get_locale_direction('en-US'))->toBe('ltr')
		->and(get_locale_direction('unknown-XX'))->toBe('ltr')
		->and(get_locale_direction())->toBe('ltr');
});

test('rrdtool_set_language() uses C.UTF-8 with LC_NUMERIC=C and rrdtool_reset_language() restores unset env', function () {
	$origLang = getenv('LANG');
	$origNum  = getenv('LC_NUMERIC');

	try {
		putenv('LANG');
		putenv('LC_NUMERIC');

		rrdtool_set_language(true);
		expect(getenv('LANG'))->toBe('C.UTF-8')
			->and(getenv('LC_NUMERIC'))->toBe('C');

		rrdtool_reset_language();
		expect(getenv('LANG'))->toBeFalse()
			->and(getenv('LC_NUMERIC'))->toBeFalse();

		putenv('LANG=de_DE.UTF-8');
		putenv('LC_NUMERIC=de_DE.UTF-8');

		rrdtool_set_language(false);
		expect(getenv('LANG'))->toBe(str_replace('-', '_', CACTI_LOCALE) . '.UTF-8');

		rrdtool_reset_language();
		expect(getenv('LANG'))->toBe('de_DE.UTF-8')
			->and(getenv('LC_NUMERIC'))->toBe('de_DE.UTF-8');
	} finally {
		if ($origLang === false) {
			putenv('LANG');
		} else {
			putenv('LANG=' . $origLang);
		}

		if ($origNum === false) {
			putenv('LC_NUMERIC');
		} else {
			putenv('LC_NUMERIC=' . $origNum);
		}
	}
});

test('api_scheduler_javascript() emits JSON-encoded translated JS strings', function () {
	ob_start();
	api_scheduler_javascript();
	$output = (string) ob_get_clean();

	expect($output)->toContain('noneSelectedText: "Select the days(s) of the week"')
		->and($output)->toContain('html.replace("every X Weeks", "every X Days")')
		->and($output)->toContain('html.replace("Rerun Every", "Rerun Every X Weeks")');
});

test('check_get_author_info() emits JSON-encoded key prompts when package.info is absent', function () {
	if (file_exists(CACTI_PATH_PKI . '/package.info')) {
		expect(check_get_author_info())->toBeArray();

		return;
	}

	ob_start();
	$result = check_get_author_info();
	$output = (string) ob_get_clean();

	expect($result)->toBeFalse()
		->and($output)->toContain('var mixedReasonTitle = "Key Generation Required to Use Tool";')
		->and($output)->toContain('var mixedOnPage      = "Packaging Key Information Not Found";');
});
