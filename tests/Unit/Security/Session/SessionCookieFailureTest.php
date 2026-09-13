<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

$csrfSource      = file_get_contents(dirname(__DIR__, 4) . '/include/csrf.php');
$functionsSource = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
$globalSource    = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');
$configSource    = file_get_contents(dirname(__DIR__, 4) . '/include/config.php.dist');

test('a missing session cookie stops the CSRF redirect loop', function () use ($csrfSource) {
	$start = strpos($csrfSource, 'function csrf_error_callback()');
	$body  = substr($csrfSource, $start, 1800);

	expect($start)->not->toBeFalse()
		->and($body)->toContain("!isset(\$_COOKIE[\$session_name])")
		->and($body)->not->toContain("!empty(\$_COOKIE) && !isset(\$_COOKIE[\$session_name])")
		->and($body)->toContain('cacti_session_cookie_failure(!empty($_COOKIE));')
		->and(strpos($body, 'cacti_session_cookie_failure('))->toBeLessThan(strpos($body, "header('Location: '"));
});

test('cookie-less clients receive the response without unbounded logging', function () use ($csrfSource) {
	$loginSource = file_get_contents(dirname(__DIR__, 4) . '/auth_login.php');

	expect($loginSource)->toContain("!isset(\$_COOKIE[\$session_name])")
		->and($loginSource)->toContain('cacti_session_cookie_failure(!empty($_COOKIE));')
		->and($csrfSource)->toContain('if ($write_log) {')
		->and($csrfSource)->toContain('http_response_code(403);');
});

test('the missing-cookie response gives actionable configuration guidance', function () use ($csrfSource) {
	expect($csrfSource)->toContain('Ensure cookies are enabled and verify the configured URL path and cookie domain.')
		->and($csrfSource)->toContain('verify url_path and cacti_cookie_domain.');
});

test('configured cookie scope remains unchanged', function () use ($globalSource) {
	expect($globalSource)->not->toContain("ini_set('session.cookie_domain', '');")
		->and($globalSource)->toContain("ini_set('session.cookie_domain', \$cacti_cookie_domain);")
		->and($globalSource)->toContain("\$options['cookie_domain'] = \$cacti_cookie_domain;");
});

test('cookie-domain documentation identifies the isolation settings', function () use ($configSource) {
	expect($configSource)->toContain('Leave it unset for a host-only cookie.')
		->and($configSource)->toContain('Use $url_path and')
		->and($configSource)->toContain('$cacti_session_name, not the cookie domain');
});

test('session regeneration only runs for an active session', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_session_regenerate()');
	$body  = substr($functionsSource, $start, 400);

	expect($start)->not->toBeFalse()
		->and($body)->toContain('if (session_status() === PHP_SESSION_ACTIVE) {')
		->and(strpos($body, 'session_regenerate_id(true);'))->toBeGreaterThan(strpos($body, 'PHP_SESSION_ACTIVE'));
});
