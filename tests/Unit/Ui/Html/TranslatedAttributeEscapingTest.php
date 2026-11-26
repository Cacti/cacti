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
 * A raw __() printed into a single-quoted HTML attribute ends the attribute at
 * the first apostrophe in the translation. The shipped fr-FR catalog renders
 * data-defaultLabel='Intervalle d'actualisation' on utilities.php and it-IT
 * renders title='Aggiungi il nome dell'origine ...' on data_queries.php, so the
 * label is cut short and the remainder becomes stray attributes. Translations
 * printed into attributes go through __esc().
 */

$root = dirname(__DIR__, 4);

test('no single-quoted attribute prints a raw translation', function () use ($root) {
	$files = array_merge(
		glob($root . '/*.php'),
		glob($root . '/lib/*.php'),
		glob($root . '/include/*.php')
	);

	$offenders = [];

	foreach ($files as $file) {
		foreach (file($file) as $number => $line) {
			if (preg_match('/[a-zA-Z-]=\'(?:<\?php (?:print|echo) __\(|" \. __\()/', $line)) {
				$offenders[] = basename($file) . ':' . ($number + 1);
			}
		}
	}

	expect($offenders)->toBe([]);
});

test('__esc keeps an apostrophe inside a single-quoted attribute', function () {
	$escaped = __esc("Intervalle d'actualisation");

	expect($escaped)->not->toContain("'")
		->and(html_entity_decode($escaped, ENT_QUOTES))->toBe("Intervalle d'actualisation");
});

test('the data query graph template delete link is a closed anchor', function () use ($root) {
	$source = file_get_contents($root . '/data_queries.php');

	expect($source)->toContain("title='\" . __esc('Delete') . \"' href='\" . \$url . \"'></a>\"")
		->and($source)->not->toContain("\$url . \"'</a>\"");
});
