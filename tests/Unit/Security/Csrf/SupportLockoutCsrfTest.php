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
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

/*
 * support_lockout() (support.php, #7352) cannot be called directly: it ends in
 * exit and redirects with header(). Extract the function source and evaluate a
 * copy with exit -> return, and the header()/cacti_log() side effects removed,
 * so each decision branch runs in-process. This mirrors the source-extraction
 * convention in Issue7070PercentileContractTest. eval() runs only Cacti's own
 * source, extracted at test time, with no external input.
 *
 * The stored lockout state is read with read_config_option(..., true), a forced
 * read that bypasses the option cache. Under the test bootstrap that returns the
 * empty default, so the "currently locked" path (unlock) cannot be reached here
 * and is left to the Docker end-to-end test.
 */
function lockout_csrf_define_probe(): void {
	if (function_exists('lockout_csrf_probe')) {
		return;
	}

	$src = file_get_contents(dirname(__DIR__, 4) . '/support.php');

	if (preg_match('/function support_lockout\(\) : void \{.*?^\}/sm', $src, $m) !== 1) {
		throw new RuntimeException('could not locate support_lockout() in support.php');
	}

	$body = $m[0];
	$body = preg_replace('/^\s*header\([^;]*\);\s*$/m', '', $body);
	$body = preg_replace('/^\s*cacti_log\([^;]*\);\s*$/m', '', $body);
	$body = preg_replace('/\bexit\s*;/', 'return;', $body);

	// The forced read of admin_user only feeds the message text via get_username().
	// A forced read re-hits the database, which the test bootstrap answers with an
	// empty value that also overwrites the seeded option cache, so drop the force
	// here and let the admin identity come from the seeded cache instead. The
	// admin comparison itself already uses an unforced read, so this does not
	// change which branch runs.
	$body = str_replace("read_config_option('admin_user', true)", "read_config_option('admin_user')", $body);

	$body = preg_replace('/^function support_lockout\(\)/m', 'function lockout_csrf_probe()', $body);

	eval($body);
}

lockout_csrf_define_probe();

beforeEach(function () {
	global $no_http_headers, $config;

	// Skip the session bootstrap inside raise_message so it writes straight to
	// $_SESSION[SESS_MESSAGES] without starting a CLI session.
	$no_http_headers = true;

	$_SESSION = [];
	$_REQUEST = [];

	$config[OPTIONS_CLI]['admin_user'] = '1';
});

test('a non-POST lockout request is dropped before any state change', function () {
	$_SERVER['REQUEST_METHOD']    = 'GET';
	$_SESSION[SESS_USER_ID]       = '1';
	$_REQUEST['expected']         = 'unlocked';

	lockout_csrf_probe();

	// The guard returns before reaching the admin check, so no lockout message
	// is raised at all.
	expect($_SESSION[SESS_MESSAGES] ?? [])->toBe([]);
});

test('only the primary administrator may toggle the lockout', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SESSION[SESS_USER_ID]    = '2';
	$_REQUEST['expected']      = 'unlocked';

	lockout_csrf_probe();

	expect($_SESSION[SESS_MESSAGES])->toHaveKey('lockout_user')
		->and($_SESSION[SESS_MESSAGES]['lockout_user']['level'])->toBe(MESSAGE_LEVEL_ERROR);
});

test('a POST without a valid expected state is rejected', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SESSION[SESS_USER_ID]    = '1';
	$_REQUEST['expected']      = 'garbage';

	lockout_csrf_probe();

	expect($_SESSION[SESS_MESSAGES])->toHaveKey('lockout')
		->and($_SESSION[SESS_MESSAGES]['lockout']['level'])->toBe(MESSAGE_LEVEL_INFO);
});

test('a stale expected state does not flip the lockout', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SESSION[SESS_USER_ID]    = '1';

	// The stored state is unlocked (empty default) but the page thought it was
	// locked, so the compare-and-set must refuse to change anything.
	$_REQUEST['expected'] = 'locked';

	lockout_csrf_probe();

	expect($_SESSION[SESS_MESSAGES])->toHaveKey('lockout')
		->and($_SESSION[SESS_MESSAGES]['lockout']['level'])->toBe(MESSAGE_LEVEL_INFO);
});

test('a matching expected state locks Cacti', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SESSION[SESS_USER_ID]    = '1';

	// Stored state is unlocked and the page agrees, so the lock is applied.
	$_REQUEST['expected'] = 'unlocked';

	lockout_csrf_probe();

	expect($_SESSION[SESS_MESSAGES])->toHaveKey('lockout')
		->and($_SESSION[SESS_MESSAGES]['lockout']['level'])->toBe(MESSAGE_LEVEL_WARN);
});

test('the process-list page offset is clamped against negative LIMIT offsets', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/support.php');

	// Both process tables must clamp the page number to at least 1 so a page=0
	// or negative page cannot produce a negative SQL LIMIT offset. Matched with
	// tolerant whitespace so reformatting doesn't break this on exact spacing.
	expect(preg_match_all('/max\(\s*1\s*,\s*\(int\)\s*grv\(\s*\'page\'\s*\)\s*\)/', $src))->toBe(2);
});
