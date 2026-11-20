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
 * scripts/ss_unix_processes.php, the PHP port of unix_processes.pl.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_unix_processes.php';

const SS_UNIX_PROCESSES_PS = "  PID TTY      STAT   TIME COMMAND\n"
	. "    1 ?        Ss     0:03 /sbin/init\n"
	. "    2 ?        S      0:00 [kthreadd]\n"
	. "  812 pts/0    Ss     0:00 -bash\n"
	. "  900 pts/0    S+     0:00 php ss_unix_processes.php\n"
	. "  901 pts/0    R+     0:00 ps ax\n";

test('ps parsing counts process lines and skips the header', function () {
	expect(ss_unix_processes_count_ps(SS_UNIX_PROCESSES_PS, []))->toBe(5);
});

test('ps parsing leaves out the counting process and its ps child', function () {
	expect(ss_unix_processes_count_ps(SS_UNIX_PROCESSES_PS, [900, 901]))->toBe(3);
});

test('a /proc listing counts numeric entries other than the counting process', function () {
	$entries = ['.', '..', '1', '2', '812', '900', 'self', 'thread-self', 'meminfo', 'sys'];

	expect(ss_unix_processes_count_proc($entries, 900))->toBe(3)
		->and(ss_unix_processes_count_proc($entries, 77))->toBe(4);
});

test('the running host reports at least one other process', function () {
	[$count, $status] = ss_unix_processes_collect();

	expect($status)->toBe(0)
		->and($count)->toMatch('/^\d+$/')
		->and((int) $count)->toBeGreaterThan(0)
		->and(ss_unix_processes())->toMatch('/^\d+$/');
});
