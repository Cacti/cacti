<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Local login failures must look the same to the client whether or not the
 * account exists, and only an authenticated account may reach the password
 * policy checks that run after verification.
 */

$auth_src         = file_get_contents(__DIR__ . '/../../../../lib/auth.php');
$include_auth_src = file_get_contents(__DIR__ . '/../../../../include/auth.php');

function login_uniformity_function_body(string $src, string $name): string {
	$start = strpos($src, 'function ' . $name . '(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, ($end === false ? strlen($src) : $end) - $start);
}

test('secpass_login_process returns one failure message to the client', function () use ($auth_src) {
	$body = login_uniformity_function_body($auth_src, 'secpass_login_process');

	/* only the verification branches; the forced password change notice follows a successful login */
	$body = substr($body, 0, strpos($body, "read_config_option('secpass_forceold')"));

	preg_match_all("/\\\$error_msg\s*=\s*__\('([^']*)'\)/", $body, $matches);

	expect($matches[1])->not->toBeEmpty();
	expect(array_unique($matches[1]))->toBe(array('Access Denied!  Login Failed.'));
});

test('secpass_login_process stops before the password policy checks for an unknown account', function () use ($auth_src) {
	$body = login_uniformity_function_body($auth_src, 'secpass_login_process');

	$unknown = strpos($body, 'LOGIN FAILED: Invalid user');
	$policy  = strpos($body, "read_config_option('secpass_forceold')");

	expect($unknown)->not->toBeFalse();
	expect($policy)->not->toBeFalse();

	$between = substr($body, $unknown, $policy - $unknown);

	expect($between)->toContain('return array();');
});

test('local_auth_login_process only rehashes the authenticated local account', function () use ($auth_src) {
	$body = login_uniformity_function_body($auth_src, 'local_auth_login_process');

	expect($body)->toContain("WHERE id = ?\n\t\t\t\t\tAND realm = 0");
	expect($body)->not->toContain("SET password = ?\n\t\t\t\t\tWHERE username = ?");
});

test('include/auth.php uses Builtin authentication on the request that migrates the legacy method', function () use ($include_auth_src) {
	$migrate = strpos($include_auth_src, 'check_reset_no_authentication($auth_method);');
	$reread  = strpos($include_auth_src, "if (\$auth_method == 0) {\n\t\$auth_method = 1;\n}");
	$gate    = strpos($include_auth_src, 'if ($auth_method != 0) {');

	expect($migrate)->not->toBeFalse();
	expect($reread)->toBeGreaterThan($migrate);
	expect($gate)->toBeGreaterThan($reread);
});
