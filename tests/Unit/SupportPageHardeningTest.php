<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Coverage for the support.php technical support page (PR #7174).
 *
 * support.php dumps database globals, the process list, connection
 * settings and host fingerprints, so it must be an admin page guarded
 * by the Settings/Utilities realm (15), its lockout toggle must reject
 * tokenless GET requests, and every information_schema value it echoes
 * must be escaped.
 */

$supportPath = __DIR__ . '/../../support.php';
$arraysPath  = __DIR__ . '/../../include/global_arrays.php';
$htmlPath    = __DIR__ . '/../../lib/html.php';

$support = file_get_contents($supportPath);
$arrays  = file_get_contents($arraysPath);

// ---------------------------------------------------------------------------
// Realm registration: without this, include/auth.php resolves realm 0 and
// denies every user, admin included.
// ---------------------------------------------------------------------------

test('support.php is registered in $user_auth_realm_filenames against realm 15', function () use ($arrays) {
	$start = strpos($arrays, '$user_auth_realm_filenames = array(');
	expect($start)->not->toBeFalse();

	$end = strpos($arrays, ');', $start);
	expect($end)->not->toBeFalse();

	$block = substr($arrays, $start, $end - $start);

	// Realm 15 is 'Settings/Utilities', the same realm guarding
	// utilities.php, settings.php and data_debug.php.
	expect($block)->toMatch("/'support\\.php'\\s*=>\\s*15\\b/");
});

test('support.php appears in the admin menu', function () use ($arrays) {
	expect($arrays)->toMatch("/'support\\.php'\\s*=>\\s*__\\(/");
});

// ---------------------------------------------------------------------------
// CSRF: csrf-magic only validates the token on POST, so the lockout toggle
// must reject anything but POST.
// ---------------------------------------------------------------------------

test('support_lockout rejects non-POST before toggling state', function () use ($support) {
	$start = strpos($support, 'function support_lockout()');
	expect($start)->not->toBeFalse();

	$end = strpos($support, "\nfunction ", $start + 1);
	$body = substr($support, $start, $end - $start);

	$guardPos = strpos($body, "REQUEST_METHOD");
	$writePos = strpos($body, 'set_config_option(');

	expect($guardPos)->not->toBeFalse();
	expect($writePos)->not->toBeFalse();
	expect($guardPos < $writePos)->toBeTrue();
});

test('lockout button posts the csrf magic token instead of a bare GET', function () use ($support) {
	expect($support)->not->toContain("loadUrl({ url: 'support.php?action=lockout' })");
	expect($support)->toContain('__csrf_magic: csrfMagicToken');
	expect($support)->toContain("loadPageUsingPost('support.php'");
});

// ---------------------------------------------------------------------------
// Stored XSS: information_schema.tables values are attacker-influenceable
// (a table comment can carry markup) and must be escaped.
// ---------------------------------------------------------------------------

test('show_database_tables escapes every information_schema value', function () use ($support) {
	$start = strpos($support, 'function show_database_tables()');
	expect($start)->not->toBeFalse();

	$end  = strpos($support, "\nfunction ", $start + 1);
	$body = substr($support, $start, $end - $start);

	foreach (['TABLE_NAME', 'ENGINE', 'TABLE_COLLATION', 'ROW_FORMAT', 'TABLE_COMMENT'] as $col) {
		expect($body)->toContain("html_escape(\$table['$col'])");
		expect($body)->not->toContain("'<td>' . \$table['$col'] . '</td>'");
	}
});

test('support.php does not call the undefined htmle() helper', function () use ($support) {
	// htmle() exists only on the 1.3.x/develop branch; on 1.2.x the escaping
	// helper is html_escape(). A stray htmle() call is a fatal error.
	expect($support)->not->toMatch('/\\bhtmle\\(/');
});

test('php_uname, snmp banner and rsa fingerprint are escaped', function () use ($support) {
	expect($support)->toContain('html_escape(php_uname())');
	expect($support)->toContain('html_escape(implode("\n", $out))');
	expect($support)->toContain("html_escape(read_config_option('rsa_fingerprint'))");
});

// ---------------------------------------------------------------------------
// Runtime check of the escaping path support.php now relies on.
// ---------------------------------------------------------------------------

test('html_escape neutralises a script payload in a table comment', function () use ($htmlPath) {
	if (!function_exists('html_escape')) {
		require_once $htmlPath;
	}

	$payload = "<script>alert('x')</script>`";
	$escaped = html_escape($payload);

	expect($escaped)->not->toContain('<script>');
	expect($escaped)->toContain('&lt;script&gt;');
	expect($escaped)->not->toContain('`');
});

// ---------------------------------------------------------------------------
// Bug 4: the background-process UNION must alias every branch to last_update
// so the displayed column matches the $display_text key.
// ---------------------------------------------------------------------------

test('background process UNION aliases all branches to last_update', function () use ($support) {
	$start = strpos($support, 'function show_cacti_processes()');
	expect($start)->not->toBeFalse();

	$end  = strpos($support, "\nfunction ", $start + 1);
	$body = substr($support, $start, $end - $start);

	// The stray 'AS last_updated' alias must be gone; the display map keys on
	// last_update.
	expect($body)->not->toContain("'N/A' AS last_updated");
	expect($body)->toContain("'last_update' => [");
});
