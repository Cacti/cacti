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

/*
 * include/global_languages.php returns early to a fallback locale when
 * i18n_language_support is off. __() used to be conditionally declared after
 * that return, which left it undefined for the rest of the request.
 *
 * include/global.php defines CACTI_VERSION_BRIEF through get_cacti_version_text()
 * immediately after loading this file, so the first thing to touch the missing
 * function was Cacti's own startup: cli/install_cacti.php died with
 * "Call to undefined function __()" before printing anything.
 *
 * The probe loads the file in its own process because it declares names lib/
 * owns.
 */

function i18n_fallback_verdict() : array {
	static $verdict;

	if ($verdict === null) {
		$verdict = cacti_test_isolated_probe(__DIR__ . '/fixtures/i18n_fallback_probe.php', ['0']);
	}

	return $verdict;
}

test('the translation API survives the disabled-i18n fallback', function () {
	$verdict = i18n_fallback_verdict();

	expect($verdict['translate_exists'])->toBeTrue();
	expect($verdict['translated'])->toBe('Version 1.3.0');
});

test('the unconditionally declared translation helpers are unaffected', function () {
	$verdict = i18n_fallback_verdict();

	// These are plain top level declarations, so PHP hoists them and the early
	// return never hid them. Asserted so a future move of __() that also moves
	// these is caught.
	expect($verdict['esc_exists'])->toBeTrue();
	expect($verdict['gettext_exists'])->toBeTrue();
});

test('__() is declared before the fallback can return', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/include/global_languages.php');

	$declared = strpos($src, 'function __() : string');
	$fallback = strpos($src, 'load_fallback_procedure();');

	expect($declared)->not->toBeFalse();
	expect($fallback)->not->toBeFalse();
	expect($declared)->toBeLessThan($fallback);
});
