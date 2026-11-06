<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Standalone test for cacti_process_run(). Runs without the Cacti bootstrap or
 * a database: it stubs the one Cacti helper the unit references (cacti_sizeof),
 * registers the composer autoloader so Symfony Process resolves, and then loads
 * lib/process.php directly.
 *
 * Run: php tests/Unit/CactiProcessRunTest.php
 */

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a): int {
		return is_array($a) || $a instanceof Countable ? count($a) : 0;
	}
}

require_once __DIR__ . '/../../include/vendor/autoload.php';
require_once __DIR__ . '/../../lib/process.php';

$failures = 0;
$total    = 0;

function check(string $name, bool $cond, string $detail = ''): void {
	global $failures, $total;
	$total++;

	if ($cond) {
		fwrite(STDOUT, "PASS: $name\n");
	} else {
		$failures++;
		fwrite(STDOUT, "FAIL: $name" . ($detail !== '' ? " -- $detail" : '') . "\n");
	}
}

// Guard: these assertions only mean anything if the primary (Symfony) backend
// is the one under test. The reference conversion ships with Symfony Process as
// a hard dependency, so a missing class is a test-environment failure.
check('Symfony Process backend is available',
	class_exists(Symfony\Component\Process\Process::class));

// 1. Basic argv exec: stdout, exit code, and backend identity.
$r = cacti_process_run(['printf', '%s', 'hello']);
check('printf returns exact stdout', $r['stdout'] === 'hello', var_export($r['stdout'], true));
check('printf exit code is 0', $r['exit_code'] === 0, (string) $r['exit_code']);
check('ran via symfony backend', $r['via'] === 'symfony', $r['via']);
check('not timed out', $r['timed_out'] === false);

// 2. The core security property: a shell metacharacter payload passed as ONE
//    argv entry must reach the program as a literal string, never be split or
//    interpreted. If a shell were involved, 'echo' / command substitution /
//    redirection would fire and the literal would not survive.
$payload = '$(touch /tmp/cacti_pwned); `id`; a&&b || c; x|y > /dev/null; *';
$r = cacti_process_run(['printf', '%s', $payload]);
check('metachars survive verbatim (no shell interpretation)',
	$r['stdout'] === $payload, var_export($r['stdout'], true));
check('injection side effect did NOT occur',
	!file_exists('/tmp/cacti_pwned'));

// 3. Semicolon/pipe do not chain a second command: the whole string is argv[1].
$r = cacti_process_run(['printf', '%s', 'a; id']);
check('command separators are literal, not chained',
	$r['stdout'] === 'a; id', var_export($r['stdout'], true));

// 4. Multiple args stay distinct (argv, not a re-split string).
$r = cacti_process_run(['printf', '[%s]', 'one two', 'three']);
check('each argv entry is one argument',
	$r['stdout'] === '[one two][three]', var_export($r['stdout'], true));

// 5. stdin payload is delivered and read back unchanged.
$r = cacti_process_run(['cat'], "line1\nline2\n");
check('stdin payload is piped to the child',
	$r['stdout'] === "line1\nline2\n", var_export($r['stdout'], true));

// 6. Non-zero exit is reported, stderr is captured.
$r = cacti_process_run(['sh', '-c', 'echo oops >&2; exit 3']);
check('non-zero exit code is reported', $r['exit_code'] === 3, (string) $r['exit_code']);
check('stderr is captured', trim($r['stderr']) === 'oops', var_export($r['stderr'], true));

// 7. Unknown program fails cleanly rather than fataling.
$r = cacti_process_run(['this-binary-does-not-exist-cacti']);
check('missing binary yields non-zero exit', $r['exit_code'] !== 0, (string) $r['exit_code']);

// 8. Empty argv is rejected.
$r = cacti_process_run([]);
check('empty argv is rejected', $r['exit_code'] === -1 && $r['via'] === 'none');

// 9. Timeout is enforced and flagged.
$r = cacti_process_run(['sleep', '5'], null, 1);
check('timeout is flagged', $r['timed_out'] === true, var_export($r, true));

// 10. The proc_open fallback honours the SAME no-shell contract.
$r = cacti_process_run_proc_open(['printf', '%s', $payload], null, 5, null, null);
check('fallback backend reports proc_open', $r['via'] === 'proc_open', $r['via']);
check('fallback keeps metachars literal too',
	$r['stdout'] === $payload, var_export($r['stdout'], true));

fwrite(STDOUT, "\n$total checks, $failures failure(s)\n");
exit($failures === 0 ? 0 : 1);
