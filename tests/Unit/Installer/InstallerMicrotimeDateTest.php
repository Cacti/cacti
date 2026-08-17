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

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('a microtime value without a fractional part is not parseable as U.u', function () {
	/*
	 * Root cause. (string) microtime(true) drops the fraction whenever the
	 * float lands on a whole second, and 'U.u' rejects the result. Calling
	 * format() on that false is what aborted the CLI install.
	 */
	expect(DateTime::createFromFormat('U.u', '1785703138'))->toBeFalse();
});

test('installer parses whole-second microtime values instead of returning false', function () {
	$date = Installer::dateFromMicrotime('1785703138');

	expect($date)->toBeInstanceOf(DateTime::class)
		->and($date->getTimestamp())->toBe(1785703138)
		->and($date->format('u'))->toBe('000000');
});

test('installer preserves microseconds when the value carries them', function () {
	$date = Installer::dateFromMicrotime('1785703138.123456');

	expect($date->getTimestamp())->toBe(1785703138)
		->and($date->format('u'))->toBe('123456');
});

test('both timestamps of the completion message survive fraction-less values', function () {
	/*
	 * beginInstall() formats a start and a finish timestamp into one message.
	 * Either can arrive without a fraction, so exercise the worst case where
	 * neither is parseable as U.u.
	 */
	$started   = Installer::dateFromMicrotime('1785703138');
	$completed = Installer::dateFromMicrotime('1785703200');

	$message = __('Installation was started at %s, completed at %s', $started->format('Y-m-d H:i:s'), $completed->format('Y-m-d H:i:s'));

	expect($started->getTimestamp())->toBe(1785703138)
		->and($completed->getTimestamp())->toBe(1785703200)
		->and($message)->toContain($started->format('Y-m-d H:i:s'))
		->and($message)->toContain($completed->format('Y-m-d H:i:s'));
});

test('installer falls back to now for values it cannot parse', function () {
	/*
	 * install_started reads as an empty string on a fresh config and as false
	 * once beginInstall() normalises it. Neither is a timestamp, but the log
	 * line still has to render.
	 */
	$before = time();

	foreach (['', false, null, 'garbage'] as $value) {
		$date = Installer::dateFromMicrotime($value);

		expect($date)->toBeInstanceOf(DateTime::class)
			->and($date->getTimestamp())->toBeGreaterThanOrEqual($before);
	}
});

test('a float landing on a whole second stringifies without a fraction', function () {
	// Confirms the failure is reachable from the producer, not just from stored values.
	expect((string) 1785703138.0)->toBe('1785703138')
		->and(Installer::dateFromMicrotime(1785703138.0)->getTimestamp())->toBe(1785703138);
});

test('no installer code path formats a raw U.u parse result', function () {
	// Every caller must route through the guard, or the fatal comes back.
	$installer = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');

	expect(substr_count($installer, "DateTime::createFromFormat('U.u'"))->toBe(1)
		->and($installer)->toContain('Installer::dateFromMicrotime(');
});
