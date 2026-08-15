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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($arg, $quote = true) {
		return escapeshellarg($arg);
	}
}

/*
 * Mirrors the snmptrap argument escaping in lib/snmpagent.php. snmptrap takes
 * fixed positional arguments where some positions are intentionally empty
 * (agent address and uptime). Joining the raw escaped tokens with a space
 * dropped those empty positions, shifting every later argument. Emitting ''
 * for empty tokens keeps the argument count intact.
 */
function snmptrap_escape_args(array $args): string {
	return implode(' ', array_map(static function ($arg) {
		return $arg === '' ? "''" : cacti_escapeshellarg($arg);
	}, $args));
}

// Count the arguments a POSIX shell would parse from a command line built out
// of single-quoted tokens. Quoted '' counts as one argument; runs of bare
// whitespace collapse, so an unquoted empty token disappears entirely.
function shell_argc(string $command_line): int {
	$argc     = 0;
	$len      = strlen($command_line);
	$in_word  = false;
	$in_quote = false;

	for ($i = 0; $i < $len; $i++) {
		$ch = $command_line[$i];

		if ($ch === "'") {
			if (!$in_word) {
				$argc++;
				$in_word = true;
			}

			$in_quote = !$in_quote;
		} elseif ($ch === ' ' && !$in_quote) {
			$in_word = false;
		} else {
			if (!$in_word) {
				$argc++;
				$in_word = true;
			}
		}
	}

	return $argc;
}

test('snmptrap escaping preserves empty positional tokens', function () {
	$args         = ['-v', '1', 'enterprise', '', '6', 'trap', ''];
	$command_line = snmptrap_escape_args($args);

	expect($command_line)->toBe("'-v' '1' 'enterprise' '' '6' 'trap' ''");
});

test('built v1 trap command line keeps the correct argument count', function () {
	// -v 1 -c comm host:port enterprise "" 6 trap "" oid type value
	$args = ['-v', '1', '-c', 'public', 'h:162', 'ENT', '', '6', '99', '', 'oid', 's', 'val'];

	expect(shell_argc(snmptrap_escape_args($args)))->toBe(count($args));
});

test('built v2c trap command line keeps the correct argument count', function () {
	// -v 2c -c comm host:port "" enterprise oid type value
	$args = ['-v', '2c', '-c', 'public', 'h:162', '', 'ENT', 'oid', 's', 'val'];

	expect(shell_argc(snmptrap_escape_args($args)))->toBe(count($args));
});

test('the old space-join would drop the empty positions', function () {
	$args = ['-v', '1', 'enterprise', '', '6', 'trap', ''];

	// reproduce the pre-fix behaviour: empty tokens escape to '' (empty string)
	$broken = implode(' ', array_map(static function ($arg) {
		return $arg === '' ? '' : "'" . $arg . "'";
	}, $args));

	expect(shell_argc($broken))->toBeLessThan(count($args));
});
