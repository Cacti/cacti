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
 * quotes ("<arg1>") - or embeds it in a larger quoted word such as
 * "prefix<arg1>" - could neutralise the value's own cacti_escapeshellarg()
 * single-quoting, so $(...)/backticks in a low-privilege field value executed on
 * the next poll. substitute_script_path() is now quote-aware: it sheds an exact
 * wrapping pair and, for a token embedded in a larger quoted word, closes the
 * template's quoting around the already-escaped value so it cannot be
 * re-interpreted. Values are never re-scanned (single-pass, GHSA-fq9x retained).
 */

$functionsSource = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');

test('GHSA-5v3j: substitute_script_path is quote-aware and closes template quoting around values', function () use ($functionsSource) {
	expect($functionsSource)->toContain("preg_match('/\\G<([A-Za-z0-9_]+)>/'")
		->and($functionsSource)->toContain('$out .= $quote . $value . $quote;')
		->and($functionsSource)->not->toContain('>(?(1)')
		->and($functionsSource)->not->toContain('array_key_exists($matches[2], $escaped_values)');
});

test('GHSA-5v3j: a double-quoted placeholder sheds the quotes so the value cannot break out', function () {
	expect(function_exists('substitute_script_path'))->toBeTrue();

	// cacti_escapeshellarg() wraps the value in single quotes.
	$esc = "'" . '$(id)' . "'";

	expect(substitute_script_path('"<arg1>"', ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path("'<arg1>'", ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path('<arg1>', ['arg1' => $esc]))->toBe($esc);
});

test('GHSA-5v3j: a token embedded in a larger quoted word keeps the value safely quoted', function () {
	$esc = "'" . '$(id)' . "'";

	// the template's quoting is closed around the already-escaped value, so an
	// outer double quote cannot re-enable $(...) / backticks inside it
	expect(substitute_script_path('"prefix<arg1>"', ['arg1' => $esc]))->toBe('"prefix"' . $esc . '""')
		->and(substitute_script_path('"<arg1>suffix"', ['arg1' => $esc]))->toBe('""' . $esc . '"suffix"')
		->and(substitute_script_path('"a<arg1>b"', ['arg1' => $esc]))->toBe('"a"' . $esc . '"b"');
});

test('GHSA-5v3j: an unknown token keeps its literal form and quotes', function () {
	expect(substitute_script_path('"<nope>"', ['arg1' => 'x']))->toBe('"<nope>"');
});

test('GHSA-5v3j: single-pass fq9x behaviour is retained', function () {
	expect(substitute_script_path('<f>', ['f' => '<g>', 'g' => 'PWN']))->toBe('<g>');
});

test('GHSA-5v3j: a trusted raw path token keeps the template quoting (not shell-escaped)', function () {
	// the path_* tokens resolve to raw, un-escaped config values. A template that
	// quotes one must keep those quotes so a path containing spaces survives the
	// shell, while a bare path token stays unquoted so the PHP script server can
	// resolve the included file as a filesystem path.
	expect(substitute_script_path('"<path_php_binary>"', ['path_php_binary' => '/opt/php 8/bin/php']))->toBe('"/opt/php 8/bin/php"')
		->and(substitute_script_path('<path_cacti>/scripts/ss_foo.php', ['path_cacti' => '/var/www/html']))->toBe('/var/www/html/scripts/ss_foo.php')
		->and(substitute_script_path('"<path_cacti>/scripts/ss_foo.php"', ['path_cacti' => '/var/www/html']))->toBe('"/var/www/html/scripts/ss_foo.php"');
});

test('GHSA-5v3j: a backslash before a token still substitutes the field (no over-consume)', function () {
	$esc = "'" . '$(id)' . "'";

	// a backslash must not swallow the following <token>; the field still
	// resolves while an odd backslash run is normalised so it cannot escape the
	// value's opening quote (\'x' would otherwise leave an unmatched quote)
	expect(substitute_script_path('\\<arg1>', ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path('\\\\<arg1>', ['arg1' => $esc]))->toBe('\\\\' . $esc)
		->and(substitute_script_path('"a\\"<arg1>"', ['arg1' => $esc]))->toBe('"a\\""' . $esc . '""');
});

test('GHSA-5v3j: substituted templates round-trip through /bin/sh as safe literal arguments', function () {
	// run a single already-quoted shell word through /bin/sh via `printf %s` and
	// return [exitCode, stdout]; proves the substitution is a well-formed word
	// that yields exactly the intended literal and never expands $(...)
	$sh = static function (string $argument): array {
		$spec  = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
		$pipes = [];
		$proc  = proc_open('printf %s ' . $argument, $spec, $pipes);

		if (!is_resource($proc)) {
			return [-1, ''];
		}

		$stdout = stream_get_contents($pipes[1]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($proc), $stdout];
	};

	// cacti_escapeshellarg() single-quotes the value; $(id) would execute on any
	// quoting break, so it must survive as the literal five bytes for every shape
	$payloadRaw = '$(id)';
	$payload    = "'" . $payloadRaw . "'";

	$templates = [
		'<arg1>', '"<arg1>"', "'<arg1>'",
		'"prefix<arg1>"', '"<arg1>suffix"', '"a<arg1>b"',
		'\\<arg1>', '\\\\<arg1>', '\\\\\\<arg1>', '"a\\"<arg1>"',
	];

	foreach ($templates as $tpl) {
		[$code, $out] = $sh(substitute_script_path($tpl, ['arg1' => $payload]));

		// parses cleanly (no unmatched-quote error) and $(id) stays literal
		expect($code)->toBe(0)
			->and($out)->toContain($payloadRaw)
			->and($out)->not->toContain('uid=');
	}

	// benign round-trip across odd/even backslash counts: an odd run is normalised
	// away so it cannot escape the value's quote, while an even run contributes its
	// own literal backslashes from the template
	$benignRaw = 'hello world';
	$benign    = "'" . $benignRaw . "'";

	foreach (['' => 0, '\\' => 0, '\\\\' => 1, '\\\\\\' => 1] as $prefix => $backslashes) {
		[$code, $out] = $sh(substitute_script_path($prefix . '<arg1>', ['arg1' => $benign]));

		expect($code)->toBe(0)
			->and($out)->toBe(str_repeat('\\', $backslashes) . $benignRaw);
	}
})->skip(stripos(PHP_OS, 'WIN') === 0 || !function_exists('proc_open'), 'POSIX /bin/sh round-trip required');


