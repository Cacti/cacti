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

/*
 * The XHR shim is Cacti's own code and has been since 2020.  It lives under
 * include/js with the rest of Cacti's JavaScript, not under include/vendor
 * where composer manages the contents.
 */

$root = dirname(__DIR__, 4);

test('the CSRF script ships from include/js', function () use ($root) {
	expect(is_file($root . '/include/js/csrf.js'))->toBeTrue();
});

test('the shim still defines the CsrfMagic object plugins call', function () use ($root) {
	$src = file_get_contents($root . '/include/js/csrf.js');

	expect($src)->toContain('CsrfMagic');
	expect($src)->toContain('XMLHttpRequest');
});

test('the script carries a Cacti copyright header', function () use ($root) {
	$src = file_get_contents($root . '/include/js/csrf.js');

	expect($src)->toContain('The Cacti Group');
});
