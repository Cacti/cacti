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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

/*
 * Every page shell prints <html> with lang and dir from the active locale.
 * The direction comes from the 'direction' field of get_list_of_locales();
 * anything unknown stays left-to-right. Arabic and Hebrew get a right-to-left
 * document only under a theme that ships an `rtl` file, because the other
 * themes hide content when the page is mirrored.
 */

function document_direction_verdict() : array {
	static $verdict;

	if ($verdict === null) {
		$verdict = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/document_direction_probe.php', ['0']);
	}

	return $verdict;
}

test('right-to-left locales report rtl', function () {
	$directions = document_direction_verdict()['directions'];

	expect($directions['ar-SA'])->toBe('rtl');
	expect($directions['he-IL'])->toBe('rtl');
});

test('left-to-right and unknown locales report ltr', function () {
	$directions = document_direction_verdict()['directions'];

	expect($directions['en-US'])->toBe('ltr');
	expect($directions['de-DE'])->toBe('ltr');
	expect($directions['xx-XX'])->toBe('ltr');
	expect($directions[''])->toBe('ltr');
	expect($directions['<script>'])->toBe('ltr');
});

test('only the locales flagged rtl in the language table are rtl', function () {
	expect(document_direction_verdict()['rtl_locales'])->toBe(['ar-SA', 'he-IL']);
});

test('a right-to-left locale mirrors only a theme that supports it', function () {
	$documents = document_direction_verdict()['documents'];

	expect($documents['he-IL|modern'])->toBe('rtl');
	expect($documents['ar-SA|modern'])->toBe('rtl');
	expect($documents['he-IL|midwinter'])->toBe('ltr');
	expect($documents['ar-SA|midwinter'])->toBe('ltr');
});

test('a left-to-right locale stays ltr in every theme', function () {
	$documents = document_direction_verdict()['documents'];

	expect($documents['en-US|modern'])->toBe('ltr');
	expect($documents['en-US|midwinter'])->toBe('ltr');
});

test('an invalid or missing theme never mirrors the page', function () {
	$documents = document_direction_verdict()['documents'];

	expect($documents['he-IL|../themes/modern'])->toBe('ltr');
	expect($documents['he-IL|Modern'])->toBe('ltr');
	expect($documents['he-IL|'])->toBe('ltr');
	expect($documents['he-IL|default'])->toBe('ltr');
});

test('only themes that ship an rtl file support right-to-left', function () {
	expect(document_direction_verdict()['rtl_themes'])->toBe(['modern']);
});

test('the active locale drives the html attributes', function () {
	$verdict = document_direction_verdict();

	expect($verdict['active'])->toBe('ltr');
	expect($verdict['attributes'])->toBe(" lang='en-US' dir='ltr'");
});

test('every html emitter prints the document attributes', function () {
	$root  = dirname(__DIR__, 4);
	$files = [
		'graph_realtime.php',
		'include/auth.php',
		'include/top_general_header.php',
		'include/top_graph_header.php',
		'include/top_header.php',
		'install/install.php',
		'lib/auth.php',
		'lib/html.php',
	];

	foreach ($files as $file) {
		$src = file_get_contents($root . '/' . $file);

		// A bare <html> or <html lang=...> without dir means a page shell was missed.
		expect(preg_match_all('/<html(?:>|\\s)/i', $src))->toBe(0, $file);
		expect(str_contains($src, 'get_html_document_attributes()'))->toBeTrue($file);
	}
});

test('general and authentication error shells render document attributes', function () {
	foreach (document_direction_verdict()['shells'] as $html) {
		expect($html)->toContain("<html lang='en-US' dir='ltr'>");
	}
});
