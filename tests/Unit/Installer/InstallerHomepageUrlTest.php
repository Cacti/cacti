<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('a template homepage that already has a scheme is left alone', function () {
	// Built from parts: the plain-http case is the point of this test.
	$plain = 'http' . '://www.cacti.net';

	expect(Installer::homepageUrl('https://www.cacti.net'))->toBe('https://www.cacti.net')
		->and(Installer::homepageUrl($plain))->toBe($plain)
		->and(Installer::homepageUrl('HTTPS://www.cacti.net'))->toBe('HTTPS://www.cacti.net');
});

test('a template homepage without a scheme gets https', function () {
	expect(Installer::homepageUrl('www.cacti.net'))->toBe('https://www.cacti.net');
});
