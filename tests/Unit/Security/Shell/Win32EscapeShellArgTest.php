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
 * On Windows, cacti_escapeshellarg() removes embedded double quotes before it
 * wraps the argument, because cmd.exe toggles quoting on every double quote,
 * and doubles trailing backslashes so they do not escape the closing quote.
 * Percent signs are kept, as before. CACTI_SERVER_OS is a constant, so the
 * Windows branch runs in a child process that defines it as win32.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$win32_escape_probe = dirname(__DIR__, 3) . '/fixtures/Core/CollectorCleanupGates.php';

test('the Windows escaper keeps a value with embedded quotes as one argument', function () use ($win32_escape_probe) {
	$result = cacti_test_isolated_probe($win32_escape_probe, ['escape']);

	expect($result['words'])->toBe('"two words"')
		->and($result['quotes'])->toBe('"a&bc"')
		->and($result['percent'])->toBe('"%5.2lf %%"')
		->and($result['empty'])->toBe('""');
});

test('the Windows escaper doubles trailing backslashes before the closing quote', function () use ($win32_escape_probe) {
	$result = cacti_test_isolated_probe($win32_escape_probe, ['escape']);
	$slash  = chr(92);

	expect($result['trailing'])->toBe('"C:' . $slash . 'data' . $slash . $slash . '"')
		->and($result['trailing-two'])->toBe('"share' . str_repeat($slash, 4) . '"')
		->and($result['inner'])->toBe('"C:' . $slash . 'data' . $slash . 'file"')
		->and($result['unquoted'])->toBe('plain' . $slash);
});

test('the cmd helper keeps the same quoting as the Windows escaper', function () use ($win32_escape_probe) {
	$result = cacti_test_isolated_probe($win32_escape_probe, ['escape']);
	$slash  = chr(92);

	expect($result['cmd-quotes'])->toBe('"abc"')
		->and($result['cmd-trailing'])->toBe('"host' . $slash . $slash . '"')
		->and($result['cmd-unquoted'])->toBe('host' . $slash);
});

test('the Windows branch no longer backslash-escapes embedded quotes', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
	$body   = substr($source, strpos($source, 'function cacti_escapeshellarg('));
	$body   = substr($body, 0, strpos($body, "\n}\n"));

	expect($body)->toContain("str_replace(CACTI_ESCAPE_CHARACTER, '', \$string)")
		->and($body)->not->toContain("'\\\\' . CACTI_ESCAPE_CHARACTER");
});
