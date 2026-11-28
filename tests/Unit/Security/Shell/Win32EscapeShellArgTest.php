<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * On Windows, cacti_escapeshellarg() removes embedded double quotes before it
 * wraps the argument, because cmd.exe toggles quoting on every double quote,
 * and doubles trailing backslashes so they do not escape the closing quote.
 * Percent signs are kept, as before.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');

test('the Windows branch removes embedded quotes instead of backslash-escaping them', function () use ($src) {
	$fn = substr($src, strpos($src, 'function cacti_escapeshellarg('));
	$fn = substr($fn, 0, strpos($fn, "\n}\n"));

	expect($fn)->toContain("str_replace(CACTI_ESCAPE_CHARACTER, '', \$string)")
		->and($fn)->not->toContain("'\\\\' . CACTI_ESCAPE_CHARACTER");
});


test('the production Windows escaper preserves one quoted argument and trailing backslashes', function () {
	global $config;
	$saved = $config;
	$config['cacti_server_os'] = 'win32';
	try {
		expect(cacti_escapeshellarg('two words'))->toBe('"two words"')
			->and(cacti_escapeshellarg('a"&b'))->toBe('"a&b"')
			->and(cacti_escapeshellarg('C:' . chr(92) . 'data' . chr(92)))->toBe('"C:' . chr(92) . 'data' . chr(92) . chr(92) . '"')
			->and(cacti_escapeshellarg('plain', false))->toBe('plain');
	} finally {
		$config = $saved;
	}
});
