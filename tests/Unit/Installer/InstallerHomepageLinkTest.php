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

test('a template homepage is escaped in the link and in its text', function () {
	$link = Installer::homepageLink('example.org/" onmouseover="alert(1)"><script>x</script>');

	expect($link)->not->toContain('" onmouseover=')
		->and($link)->not->toContain('<script>')
		->and($link)->toStartWith('<a href="https://example.org/')
		->and($link)->toContain('rel="noopener noreferrer"');
});

test('a template homepage without a web scheme is not left as a script URL', function () {
	expect(Installer::homepageLink('javascript:alert(1)'))->toContain('href="https://javascript:alert(1)"');
});

test('an ordinary homepage is linked as it is written', function () {
	expect(Installer::homepageLink('https://www.cacti.net'))
		->toBe('<a href="https://www.cacti.net" target="_new" rel="noopener noreferrer">https://www.cacti.net</a>');
});
