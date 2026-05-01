<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$authSource = file_get_contents(__DIR__ . '/../../lib/auth.php');

test('GHSA-4494-26m2-mvhm: check_auth_cookie runs lockout check before returning', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	expect($start)->not->toBeFalse();

	// The remember-me cookie path must run the lockout check before it
	// hands back a user id, otherwise a locked-out account can still
	// re-enter via the persistent cookie.
	$end  = strpos($authSource, "\n}\n", $start);
	$body = substr($authSource, $start, $end - $start);

	expect($body)->toContain("auth_process_lockout_check(\$user_info['username'], \$user_info['realm']) === false");
	expect($body)->toContain("return \$user_info['id'];");
});

test('GHSA-4494-26m2-mvhm: lockout check precedes successful return', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	$end   = strpos($authSource, "\n}\n", $start);
	$body  = substr($authSource, $start, $end - $start);

	$lockoutPos = strpos($body, 'auth_process_lockout_check(');
	$returnPos  = strpos($body, "return \$user_info['id'];");

	expect($lockoutPos)->not->toBeFalse();
	expect($returnPos)->not->toBeFalse();
	expect($lockoutPos)->toBeLessThan($returnPos);
});

test('GHSA-4494-26m2-mvhm: lockout failure returns false', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	$end   = strpos($authSource, "\n}\n", $start);
	$body  = substr($authSource, $start, $end - $start);

	// The lockout failure branch must short-circuit to false, not fall
	// through to the success return.
	expect(preg_match('/auth_process_lockout_check\([^)]*\)\s*===\s*false\)\s*\{\s*return false;/', $body))->toBe(1);
});
