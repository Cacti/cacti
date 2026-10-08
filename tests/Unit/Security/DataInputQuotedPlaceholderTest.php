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
 * GHSA-5v3j-wcrr-jxjg: a Data Input template that wraps a <field> placeholder in
 * a matched pair of quotes ("<arg1>") neutralizes the value's own
 * cacti_escapeshellarg() single-quoting, so $(...)/backticks in a low-privilege
 * field value execute on the next poll. substitute_script_path() must shed one
 * matched pair of surrounding quotes when a token resolves.
 */

$functionsSource = file_get_contents(dirname(__DIR__, 3) . '/lib/functions.php');

test('GHSA-5v3j: substitute_script_path consumes matched surrounding quotes', function () use ($functionsSource) {
	expect($functionsSource)->toContain('>(?(1)')
		->and($functionsSource)->toContain('array_key_exists($matches[2], $escaped_values)')
		->and($functionsSource)->not->toContain('array_key_exists($matches[1], $escaped_values)');
});

if (!function_exists('substitute_script_path')) {
	preg_match('/\nfunction substitute_script_path\b.*?\n\}/s', "\n" . $functionsSource, $sspMatch);

	if (!empty($sspMatch)) {
		eval($sspMatch[0]);
	}
}

test('GHSA-5v3j: a double-quoted placeholder sheds the quotes so the value cannot break out', function () {
	expect(function_exists('substitute_script_path'))->toBeTrue();

	$esc = "'" . '$(id)' . "'";

	expect(substitute_script_path('"<arg1>"', ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path("'<arg1>'", ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path('<arg1>', ['arg1' => $esc]))->toBe($esc);
});

test('GHSA-5v3j: an unknown token keeps its literal form and quotes', function () {
	expect(substitute_script_path('"<nope>"', ['arg1' => 'x']))->toBe('"<nope>"');
});

test('GHSA-5v3j: single-pass fq9x behaviour is retained', function () {
	expect(substitute_script_path('<f>', ['f' => '<g>', 'g' => 'PWN']))->toBe('<g>');
});
