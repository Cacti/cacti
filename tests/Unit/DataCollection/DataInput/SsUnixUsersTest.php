<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ss_unix_users.php, the PHP port of unix_users.pl.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_unix_users.php';

const SS_UNIX_USERS_WHO = "alice    pts/0        2026-10-22 09:14 (10.0.0.5)\n"
	. "bob.smith pts/1       2026-10-22 10:02 (10.0.0.9)\n"
	. "alice    pts/2        2026-10-22 11:40 (10.0.0.5)\n"
	. "reboot   system boot\n";

test('counts every session line when no filter is given', function () {
	expect(ss_unix_users_count(SS_UNIX_USERS_WHO, ''))->toBe(3);
});

test('a filter counts only matching sessions', function () {
	expect(ss_unix_users_count(SS_UNIX_USERS_WHO, 'alice'))->toBe(2)
		->and(ss_unix_users_count(SS_UNIX_USERS_WHO, 'bob.smith'))->toBe(1)
		->and(ss_unix_users_count(SS_UNIX_USERS_WHO, 'carol'))->toBe(0);
});

test('a filter matches literally, so a dot is not a wildcard', function () {
	expect(ss_unix_users_count(SS_UNIX_USERS_WHO, 'bobxsmith'))->toBe(0)
		->and(ss_unix_users_count("bobxsmith pts/1 2026-10-22 10:02\n", 'bob.smith'))->toBe(0);
});

test('rejected filters return U instead of an unfiltered count', function (string $filter) {
	expect(ss_unix_users_collect($filter))->toBe(['U', 1])
		->and(ss_unix_users($filter))->toBe('U');
})->with(['a b', 'alice;id', '$(id)', 'a*', "alice\nbob"]);

test('dots and dashes are accepted in user names', function () {
	[$count, $status] = ss_unix_users_collect('first.last-x');

	expect($status)->toBe(0)->and($count)->toMatch('/^\d+$/');
});

test('the running host is counted with who', function () {
	[$count, $status] = ss_unix_users_collect();

	expect($status)->toBe(0)->and($count)->toMatch('/^\d+$/');
});
