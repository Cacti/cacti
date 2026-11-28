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
 * The Package Import, Public Key and Package Repository confirmation pages
 * print plural button titles into single-quoted title attributes. The nl-NL
 * catalog translates three of them with an apostrophe (for example
 * "Pakketrepository's verwijderen"), which ends the attribute early, so the
 * titles must go through __esc_n() rather than __n().
 */

$root = dirname(__DIR__, 4);

dataset('package_confirmation_pages', [
	'package_import.php',
	'package_keys.php',
	'package_repos.php',
]);

test('plural button titles in single-quoted attributes are escaped', function (string $file) use ($root) {
	$source = file_get_contents($root . '/' . $file);

	expect($source)->toContain("title='\" . __esc_n(")
		->and($source)->not->toContain("title='\" . __n(");
})->with('package_confirmation_pages');

test('an escaped plural title keeps its apostrophe inside the attribute', function () {
	$title = htmlspecialchars("Pakketrepository's verwijderen", ENT_QUOTES);
	$html  = "<button type='submit' title='" . $title . "'>";

	expect($title)->toBe('Pakketrepository&#039;s verwijderen')
		->and(substr_count($html, "'"))->toBe(4);
});
