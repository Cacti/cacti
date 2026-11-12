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

test('a template homepage that already has a scheme is left alone', function () {
	expect(Installer::homepageUrl('https://www.cacti.net'))->toBe('https://www.cacti.net')
		->and(Installer::homepageUrl('http://www.cacti.net'))->toBe('http://www.cacti.net')
		->and(Installer::homepageUrl('HTTPS://www.cacti.net'))->toBe('HTTPS://www.cacti.net');
});

test('a template homepage without a scheme gets https', function () {
	expect(Installer::homepageUrl('www.cacti.net'))->toBe('https://www.cacti.net');
});
