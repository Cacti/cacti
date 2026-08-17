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
 * Tests for the unresolved substitution variable warning (#3744).
 *
 * get_full_script_path() and get_full_test_script_path() null out
 * <field> tokens that no data input field resolved.  That keeps the
 * script runnable but silently shifts positional arguments.  The fix
 * logs a warning naming the unresolved tokens before stripping them.
 *
 * SYNC WARNING: detect_unresolved_tokens() mirrors the guard in
 * lib/functions.php.  Production functions require DB state, so the
 * logic is replicated here for behavioral coverage.
 */

function detect_unresolved_tokens(string $full_path): array {
	preg_match_all('/<[A-Za-z0-9_]+>/', $full_path, $matches);

	return array_values(array_unique($matches[0]));
}

function strip_unresolved_tokens(string $full_path): string {
	return preg_replace('/(<[A-Za-z0-9_]+>)+/', '', $full_path) ?? '';
}

test('fully substituted command produces no tokens and no warning', function () {
	$cmd = "/usr/bin/php -q script.php '10.0.0.1' 'public'";

	expect(detect_unresolved_tokens($cmd))->toBe([])
		->and(strip_unresolved_tokens($cmd))->toBe($cmd);
});

test('unresolved field token is detected and named', function () {
	$cmd = "/usr/bin/php -q script.php <hostname> 'public'";

	expect(detect_unresolved_tokens($cmd))->toBe(['<hostname>']);
});

test('duplicate unresolved tokens are reported once', function () {
	$cmd = 'script.sh <hostname> <hostname> <index>';

	expect(detect_unresolved_tokens($cmd))->toBe(['<hostname>', '<index>']);
});

test('stripping preserves the argument-offset behaviour being warned about', function () {
	$cmd = 'script.sh <hostname> arg2 arg3';

	// after the strip, arg2 shifts into the hostname position: the reason the warning exists
	expect(strip_unresolved_tokens($cmd))->toBe('script.sh  arg2 arg3');
});

test('path placeholders and shell redirects are not treated as tokens', function () {
	// '2>' and '<file' are not <word> tokens; resolved values are quoted strings
	$cmd = "script.sh 'a' 2>/dev/null";

	expect(detect_unresolved_tokens($cmd))->toBe([]);
});

test('consecutive tokens strip as one group, matching legacy behaviour', function () {
	$cmd = 'script.sh <a><b> tail';

	expect(detect_unresolved_tokens($cmd))->toBe(['<a>', '<b>'])
		->and(strip_unresolved_tokens($cmd))->toBe('script.sh  tail');
});
