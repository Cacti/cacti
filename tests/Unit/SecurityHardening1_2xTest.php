<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$authProfileSource  = file_get_contents(__DIR__ . '/../../auth_profile.php');
$functionsSource    = file_get_contents(__DIR__ . '/../../lib/functions.php');
$htmlUtilitySource  = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
$databaseSource     = file_get_contents(__DIR__ . '/../../lib/database.php');

// M-1: JS context injection in auth_profile.php

test('auth_profile currentTheme uses json_encode not bare print', function () use ($authProfileSource) {
	expect($authProfileSource)->not->toContain("var currentTheme = '<?php print get_selected_theme()");
	expect($authProfileSource)->toContain('json_encode((string) get_selected_theme())');
});

test('auth_profile currentLang uses json_encode not bare print', function () use ($authProfileSource) {
	expect($authProfileSource)->not->toContain("var currentLang  = '<?php print read_config_option('user_language')");
	expect($authProfileSource)->toContain("json_encode((string) read_config_option('user_language'))");
});

test('auth_profile authMethod uses json_encode not bare print', function () use ($authProfileSource) {
	expect($authProfileSource)->not->toContain("var authMethod   = '<?php print read_config_option('auth_method')");
	expect($authProfileSource)->toContain("json_encode((string) read_config_option('auth_method'))");
});

// M-2: sanitize_uri double-decode removed

test('sanitize_uri does not call urldecode', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function sanitize_uri(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 600);
	expect($body)->not->toContain('urldecode(');
});

// M-3/M-4: validate_redirect_url prefers SERVER_NAME over HTTP_HOST

test('validate_redirect_url prefers SERVER_NAME over HTTP_HOST', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function validate_redirect_url(');
	expect($start)->not->toBeFalse();

	$body = substr($htmlUtilitySource, $start, 3000);
	// SERVER_NAME check must appear before HTTP_HOST fallback
	$posServerName = strpos($body, "SERVER_NAME");
	$posHttpHost   = strpos($body, "HTTP_HOST");
	expect($posServerName)->toBeLessThan($posHttpHost);
});

test('validate_redirect_url rejects protocol-relative URLs after sanitize_uri', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function validate_redirect_url(');
	$body = substr($htmlUtilitySource, $start, 3000);
	expect($body)->toContain("strpos(\$safe, '//') === 0");
});

// H-4: db_dump_data wraps credential values with cacti_escapeshellarg

test('db_dump_data escapes all command arguments with cacti_escapeshellarg', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	expect($start)->not->toBeFalse();

	$body = substr($databaseSource, $start, 2000);
	expect($body)->toContain('cacti_escapeshellarg(');
});

test('db_dump_data passes password via environment not command line', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	$body = substr($databaseSource, $start, 2000);
	// Password must appear in env array, not in the $command array
	expect($body)->toContain("'MYSQL_PWD'");
	// --password flag must not be appended to the command array
	expect($body)->not->toContain("'--password'");
	expect($body)->not->toContain('"-p"');
});

// H-5: check_auth_cookie lockout logic must deny locked, not deny unlocked
//
// auth_process_lockout_check() returns true when the account IS locked.
// The condition must be truthy (deny when locked), not === false (deny when unlocked).

$authSource = file_get_contents(__DIR__ . '/../../lib/auth.php');

test('check_auth_cookie uses truthy lockout check not inverted === false', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 3000);
	// The correct form: if (auth_process_lockout_check(...)) { return false; }
	expect($body)->toContain('if (auth_process_lockout_check(');
	// The broken form must not be present inside check_auth_cookie
	expect($body)->not->toContain('auth_process_lockout_check($user_info[\'username\'], $user_info[\'realm\']) === false');
});

// H-6: cacti_auth_transition must not touch user_auth_cache (no cookie rotation)
//
// On 1.2.x cookie rotation belongs to set_auth_cookie / check_auth_cookie.
// Putting it in cacti_auth_transition causes a double rotation: check_auth_cookie
// rotates T1->T2, then the transition reads the stale $_COOKIE (still T1),
// its UPDATE matches nothing, and it queues a third cookie T3.  The browser
// gets T3 while the DB holds T2, breaking remember-me on the next visit.

test('cacti_auth_transition does not rotate the remember-me cookie', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_auth_transition(');
	expect($start)->not->toBeFalse();

	$end   = strpos($authSource, "\nfunction ", $start + 1);
	$body  = $end !== false ? substr($authSource, $start, $end - $start) : substr($authSource, $start, 3000);

	expect($body)->not->toContain('UPDATE user_auth_cache');
	expect($body)->not->toContain('cacti_cookie_session_set(');
});

test('check_auth_cookie calls set_auth_cookie after the lockout check', function () use ($authSource) {
	$start = strpos($authSource, 'function check_auth_cookie(');
	expect($start)->not->toBeFalse();

	$end  = strpos($authSource, "\nfunction ", $start + 1);
	$body = $end !== false ? substr($authSource, $start, $end - $start) : substr($authSource, $start, 3000);

	$lockoutPos    = strpos($body, 'auth_process_lockout_check(');
	$setCookiePos  = strpos($body, 'set_auth_cookie(');

	expect($lockoutPos)->not->toBeFalse('auth_process_lockout_check not found in check_auth_cookie');
	expect($setCookiePos)->not->toBeFalse('set_auth_cookie not found in check_auth_cookie');
	expect($lockoutPos)->toBeLessThan($setCookiePos, 'lockout check must come before set_auth_cookie');
});
