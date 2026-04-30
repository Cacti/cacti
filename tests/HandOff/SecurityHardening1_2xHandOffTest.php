<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 |                                                                         |
 | HandOff tests: verify behavioral properties of the security fixes from  |
 | SecurityHardening1_2xTest without requiring a full Cacti bootstrap.     |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');
$databaseSource  = file_get_contents(__DIR__ . '/../../lib/database.php');
$authSource      = file_get_contents(__DIR__ . '/../../lib/auth.php');

// -------------------------------------------------------------------------
// sanitize_uri — double-decode prevention (M-2)
//
// Before the fix, sanitize_uri decoded percent-encoded input via urldecode()
// before stripping dangerous characters. An attacker could encode a payload
// as %3Cscript%3E, which would survive the is_urlencoded() check, get decoded
// to <script>, and then be stripped — but double-encoded inputs like
// %253Cscript%253E would decode to %3Cscript%3E and pass through intact.
//
// After the fix the function never calls urldecode(), so encoded payloads are
// passed through as literal percent-sequences and rendered harmless.
// -------------------------------------------------------------------------

test('sanitize_uri does not decode percent-encoded payloads', function () use ($functionsSource) {
	// Extract sanitize_uri and its dependency is_urlencoded, then eval with a
	// minimal stub for get_nfilter_request_var (only used in the graph_view branch).
	if (!function_exists('get_nfilter_request_var')) {
		// phpcs:ignore
		function get_nfilter_request_var($key) { return ''; }
	}

	if (!function_exists('sanitize_uri')) {
		if (preg_match('/function is_urlencoded\(.*?^\}/ms', $functionsSource, $m)) {
			eval($m[0]);
		}
		if (preg_match('/function sanitize_uri\(.*?^\}/ms', $functionsSource, $m)) {
			eval($m[0]);
		}
	}

	expect(function_exists('sanitize_uri'))->toBeTrue();

	// A double-encoded script tag: before the fix this decoded to %3Cscript%3E
	// and in a second pass could become <script>. After the fix the percent signs
	// are kept and the strip_tags call has nothing to remove.
	$input  = '%253Cscript%253Ealert(1)%253C%2Fscript%253E';
	$result = sanitize_uri($input);

	// The % character is not in the drop_char_match list, so the encoded form
	// must be preserved rather than decoded to angle brackets.
	expect($result)->not->toContain('<script>');
	expect($result)->not->toContain('<');
});

test('sanitize_uri strips literal angle brackets directly', function () {
	if (!function_exists('sanitize_uri')) {
		$this->markTestSkipped('sanitize_uri not loaded');
	}

	$result = sanitize_uri('<script>alert(1)</script>');
	expect($result)->not->toContain('<');
	expect($result)->not->toContain('>');
});

// -------------------------------------------------------------------------
// db_dump_data — unescaped credentials (H-4)
//
// Before the fix, credential values were concatenated directly into the shell
// command string. A username or database name containing shell metacharacters
// (e.g. spaces, semicolons, backticks) could break command structure.
//
// After the fix every element of $command — including $username and $database
// — is wrapped with cacti_escapeshellarg() before being appended to the
// command string. The password is passed via MYSQL_PWD so it never appears
// on the command line at all.
// -------------------------------------------------------------------------

test('db_dump_data command loop calls cacti_escapeshellarg on every argument', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	expect($start)->not->toBeFalse();

	$body = substr($databaseSource, $start, 2500);

	// The loop must call cacti_escapeshellarg on each $arg, not concatenate raw.
	expect($body)->toContain('cacti_escapeshellarg($arg)');

	// Username must reach the $command array (where it gets escaped) not be
	// interpolated directly into $cmd_string.
	$posUsername    = strpos($body, '$username');
	$posCommandArr  = strpos($body, '$command = array(');
	$posCmdString   = strpos($body, '$cmd_string');
	expect($posUsername)->toBeLessThan($posCmdString);
	expect($posCommandArr)->toBeLessThan($posCmdString);
});

test('db_dump_data never interpolates password into command string', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	$body = substr($databaseSource, $start, 2500);

	// Password must not appear in $command array or $cmd_string concatenation.
	// It is passed exclusively through MYSQL_PWD environment variable.
	expect($body)->toContain("'MYSQL_PWD' => \$password");

	// Verify the $password variable does not appear inside the $command array
	// literal. We check the slice between $command = array( and the closing );
	$cmdStart = strpos($body, '$command = array(');
	$cmdEnd   = strpos($body, ');', $cmdStart);
	$cmdBody  = substr($body, $cmdStart, $cmdEnd - $cmdStart);
	expect($cmdBody)->not->toContain('$password');
	expect($cmdBody)->not->toContain('--password');
});

// -------------------------------------------------------------------------
// check_auth_cookie — lockout ordering (H-5)
//
// Before the fix, check_auth_cookie called set_auth_cookie (rotating the
// remember-me token) before calling auth_process_lockout_check.  A locked
// account would have its token rotated and a fresh Set-Cookie header emitted
// before the function returned false.  The auth event was also written to
// user_log before the lockout was detected.
//
// After the fix auth_process_lockout_check runs first.  A locked account is
// rejected immediately; no token is rotated and no log entry is written.
// -------------------------------------------------------------------------

test('check_auth_cookie rejects locked accounts before rotating the cookie', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	expect($start)->not->toBeFalse('check_auth_cookie not found in lib/auth.php');

	$end  = strpos($authSource, "\nfunction ", $start + 1);
	$body = $end !== false ? substr($authSource, $start, $end - $start) : substr($authSource, $start, 3000);

	$lockoutPos   = strpos($body, 'auth_process_lockout_check(');
	$setCookiePos = strpos($body, 'set_auth_cookie(');

	expect($lockoutPos)->not->toBeFalse('auth_process_lockout_check not present in check_auth_cookie');
	expect($setCookiePos)->not->toBeFalse('set_auth_cookie not present in check_auth_cookie');

	// Lockout must be evaluated before the cookie is rotated.
	expect($lockoutPos)->toBeLessThan($setCookiePos,
		'auth_process_lockout_check must appear before set_auth_cookie');
});

test('check_auth_cookie lockout check uses truthy result not inverted === false', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	$end   = strpos($authSource, "\nfunction ", $start + 1);
	$body  = $end !== false ? substr($authSource, $start, $end - $start) : substr($authSource, $start, 3000);

	// The broken form returned false for locked accounts and allowed them through.
	expect($body)->not->toContain('auth_process_lockout_check($user_info[\'username\'], $user_info[\'realm\']) === false');
});

// -------------------------------------------------------------------------
// cacti_auth_transition — no cookie rotation on 1.2.x (H-6)
//
// An earlier revision added cookie rotation inside cacti_auth_transition.
// This caused a double rotation on the cookie-restore path: check_auth_cookie
// called set_auth_cookie (T1->T2), then cacti_auth_transition read the stale
// $_COOKIE (still T1), found no DB row, and emitted a third cookie T3.  The
// browser held T3 while the DB stored T2, breaking remember-me on the next
// visit.
//
// On 1.2.x the rotation was moved back to set_auth_cookie / check_auth_cookie
// exclusively.  cacti_auth_transition must not touch user_auth_cache.
// -------------------------------------------------------------------------

test('cacti_auth_transition does not update user_auth_cache', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_auth_transition(');
	expect($start)->not->toBeFalse('cacti_auth_transition not found in lib/auth.php');

	$end  = strpos($authSource, "\nfunction ", $start + 1);
	$body = $end !== false ? substr($authSource, $start, $end - $start) : substr($authSource, $start, 3000);

	expect($body)->not->toContain('UPDATE user_auth_cache',
		'cacti_auth_transition must not rotate the remember-me token on 1.2.x');
	expect($body)->not->toContain('set_auth_cookie(',
		'cacti_auth_transition must not call set_auth_cookie on 1.2.x');
});
