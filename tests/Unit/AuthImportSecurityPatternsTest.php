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
 * Source-scan tests for auth and import security patterns.
 *
 * These tests verify that security-relevant patterns exist in lib/auth.php,
 * lib/import.php, and lib/package.php by scanning the source text. They do
 * not execute the functions (which require a DB), but confirm that guards,
 * allowlists, and validation logic remain in place across refactors.
 */

$authSource    = file_get_contents(__DIR__ . '/../../lib/auth.php');
$importSource  = file_get_contents(__DIR__ . '/../../lib/import.php');
$packageSource = file_get_contents(__DIR__ . '/../../lib/package.php');
$xmlSource     = file_get_contents(__DIR__ . '/../../lib/xml.php');

// =====================================================================
// lib/auth.php -- session validation functions exist
// =====================================================================

test('auth.php defines is_realm_allowed function', function () use ($authSource) {
	expect($authSource)->toContain('function is_realm_allowed(');
});

test('auth.php defines is_view_allowed function', function () use ($authSource) {
	expect($authSource)->toContain('function is_view_allowed(');
});

test('auth.php defines is_graph_allowed function', function () use ($authSource) {
	expect($authSource)->toContain('function is_graph_allowed(');
});

test('auth.php defines is_tree_allowed function', function () use ($authSource) {
	expect($authSource)->toContain('function is_tree_allowed(');
});

test('auth.php defines is_device_allowed function', function () use ($authSource) {
	expect($authSource)->toContain('function is_device_allowed(');
});

test('auth.php defines auth_valid_user function', function () use ($authSource) {
	expect($authSource)->toContain('function auth_valid_user(');
});

test('auth.php defines is_user_perms_valid function', function () use ($authSource) {
	expect($authSource)->toContain('function is_user_perms_valid(');
});

// =====================================================================
// lib/auth.php -- is_view_allowed column allowlist
// =====================================================================

test('is_view_allowed uses an allowed_views array', function () use ($authSource) {
	expect($authSource)->toContain('$allowed_views');
});

test('is_view_allowed checks view against allowed_views with in_array', function () use ($authSource) {
	expect($authSource)->toContain("in_array(\$view, \$allowed_views, true)");
});

test('is_view_allowed allowlist contains show_tree', function () use ($authSource) {
	expect($authSource)->toContain("'show_tree'");
});

test('is_view_allowed allowlist contains show_list', function () use ($authSource) {
	expect($authSource)->toContain("'show_list'");
});

test('is_view_allowed allowlist contains show_preview', function () use ($authSource) {
	expect($authSource)->toContain("'show_preview'");
});

test('is_view_allowed allowlist contains graph_settings', function () use ($authSource) {
	expect($authSource)->toContain("'graph_settings'");
});

// =====================================================================
// lib/auth.php -- session constants used instead of raw strings
// =====================================================================

test('auth.php uses SESS_USER_ID constant for session access', function () use ($authSource) {
	expect($authSource)->toContain('$_SESSION[SESS_USER_ID]');
});

test('auth.php uses SESS_USER_REALMS constant for realm caching', function () use ($authSource) {
	expect($authSource)->toContain('$_SESSION[SESS_USER_REALMS]');
});

test('auth.php uses SESS_AUTH_NAMES constant', function () use ($authSource) {
	expect($authSource)->toContain('$_SESSION[SESS_AUTH_NAMES]');
});

test('auth.php uses kill_session_var for session cleanup', function () use ($authSource) {
	expect($authSource)->toContain('kill_session_var(SESS_USER_ID)');
});

// =====================================================================
// lib/auth.php -- prepared statements for all DB queries
// =====================================================================

test('auth.php uses db_fetch_cell_prepared for user lookups', function () use ($authSource) {
	expect($authSource)->toContain('db_fetch_cell_prepared(');
});

test('auth.php uses db_execute_prepared for user mutations', function () use ($authSource) {
	expect($authSource)->toContain('db_execute_prepared(');
});

test('auth.php uses db_fetch_row_prepared for row fetches', function () use ($authSource) {
	expect($authSource)->toContain('db_fetch_row_prepared(');
});

// =====================================================================
// lib/auth.php -- cookie token uses cryptographic randomness
// =====================================================================

test('set_auth_cookie uses random_bytes for token generation', function () use ($authSource) {
	expect($authSource)->toContain('random_bytes(');
});

test('auth cookie tokens are hashed with sha512', function () use ($authSource) {
	expect($authSource)->toContain("hash('sha512'");
});

// =====================================================================
// lib/auth.php -- password security functions
// =====================================================================

test('auth.php defines secpass_check_pass for password validation', function () use ($authSource) {
	expect($authSource)->toContain('function secpass_check_pass(');
});

test('auth.php defines secpass_check_history for password reuse prevention', function () use ($authSource) {
	expect($authSource)->toContain('function secpass_check_history(');
});

test('auth.php uses compat_password_verify not raw comparison', function () use ($authSource) {
	expect($authSource)->toContain('compat_password_verify(');
});

test('auth.php uses compat_password_hash for hashing', function () use ($authSource) {
	expect($authSource)->toContain('compat_password_hash(');
});

// =====================================================================
// lib/auth.php -- account lockout protection
// =====================================================================

test('auth.php defines auth_process_lockout function', function () use ($authSource) {
	expect($authSource)->toContain('function auth_process_lockout(');
});

test('auth.php defines auth_process_lockout_check function', function () use ($authSource) {
	expect($authSource)->toContain('function auth_process_lockout_check(');
});

test('auth.php defines auth_checkclear_lockout function', function () use ($authSource) {
	expect($authSource)->toContain('function auth_checkclear_lockout(');
});

// =====================================================================
// lib/auth.php -- input validation on user management functions
// =====================================================================

test('user_remove validates user_id with input_validate_input_number', function () use ($authSource) {
	// user_remove calls input_validate_input_number before any DB ops
	$start = strpos($authSource, 'function user_remove(');
	if ($start === false) {
		$this->fail('function user_remove not found in source');
	}
	$fnBody = substr($authSource, $start);
	$end = strpos($fnBody, "\nfunction ");
	if ($end === false) {
		$this->fail('no function follows user_remove in source');
	}
	$fnBody = substr($fnBody, 0, $end);

	expect($fnBody)->toContain('input_validate_input_number($user_id');
});

test('user_disable validates user_id with input_validate_input_number', function () use ($authSource) {
	$start = strpos($authSource, 'function user_disable(');
	if ($start === false) {
		$this->fail('function user_disable not found in source');
	}
	$fnBody = substr($authSource, $start);
	$end = strpos($fnBody, "\nfunction ");
	if ($end === false) {
		$this->fail('no function follows user_disable in source');
	}
	$fnBody = substr($fnBody, 0, $end);

	expect($fnBody)->toContain('input_validate_input_number($user_id');
});

test('user_enable validates user_id with input_validate_input_number', function () use ($authSource) {
	$start = strpos($authSource, 'function user_enable(');
	if ($start === false) {
		$this->fail('function user_enable not found in source');
	}
	$fnBody = substr($authSource, $start);
	$end = strpos($fnBody, "\nfunction ");
	if ($end === false) {
		$this->fail('no function follows user_enable in source');
	}
	$fnBody = substr($fnBody, 0, $end);

	expect($fnBody)->toContain('input_validate_input_number($user_id');
});

// =====================================================================
// lib/auth.php -- get_basic_auth_username sanitization
// =====================================================================

test('auth.php sanitizes basic auth username with sanitize_search_string', function () use ($authSource) {
	$start = strpos($authSource, 'function auth_get_username(');
	if ($start === false) {
		$this->fail('function auth_get_username not found in source');
	}
	$fnBody = substr($authSource, $start);
	$end = strpos($fnBody, "\nfunction ");
	if ($end === false) {
		$this->fail('no function follows auth_get_username in source');
	}
	$fnBody = substr($fnBody, 0, $end);

	expect($fnBody)->toContain('sanitize_search_string($username)');
});

test('get_basic_auth_username handles array-typed SERVER vars', function () use ($authSource) {
	// Protection against header injection via array-typed superglobals
	expect($authSource)->toContain('is_array($_SERVER[');
});

// =====================================================================
// lib/auth.php -- no extract() calls on untrusted data
// =====================================================================

test('auth.php does not use extract()', function () use ($authSource) {
	expect($authSource)->not->toMatch('/\bextract\s*\(/');
});

// =====================================================================
// lib/import.php -- signature validation exists
// =====================================================================

test('import.php defines import_validate_signature function', function () use ($importSource) {
	expect($importSource)->toContain('function import_validate_signature(');
});

test('import.php uses openssl_verify for package signature verification', function () use ($importSource) {
	expect($importSource)->toContain('openssl_verify(');
});

test('import.php supports SHA256 signatures via OPENSSL_ALGO_SHA256', function () use ($importSource) {
	expect($importSource)->toContain('OPENSSL_ALGO_SHA256');
});

test('import.php rejects tampered packages with log message', function () use ($importSource) {
	expect($importSource)->toContain('File has been Tampered with');
});

// =====================================================================
// lib/import.php -- per-file signature verification
// =====================================================================

test('import.php verifies each file signature individually', function () use ($importSource) {
	expect($importSource)->toContain('Verifying each files signature');
});

test('import.php decodes file signatures with base64_decode', function () use ($importSource) {
	expect($importSource)->toContain("base64_decode(\$f['filesignature']");
});

test('import.php rejects files with invalid signatures', function () use ($importSource) {
	expect($importSource)->toContain('Could not Verify Signature for file');
});

// =====================================================================
// lib/import.php -- file write path restriction
// =====================================================================

test('import.php restricts file writes to scripts/ and resource/ paths', function () use ($importSource) {
	// The file write block is gated by checking the name contains scripts/ or resource/
	expect($importSource)->toContain("str_contains(\$name, 'scripts/')");
	expect($importSource)->toContain("str_contains(\$name, 'resource/')");
});

test('import.php constructs write paths relative to CACTI_PATH_BASE', function () use ($importSource) {
	expect($importSource)->toContain('CACTI_PATH_BASE . "/$name"');
});

test('import.php checks directory writability before file creation', function () use ($importSource) {
	expect($importSource)->toContain('is_writable(dirname($filename))');
});

// =====================================================================
// lib/import.php -- public key trust validation
// =====================================================================

test('import.php checks package public key against trusted keys', function () use ($importSource) {
	expect($importSource)->toContain('package_public_keys');
});

test('import.php uses strict comparison for key matching', function () use ($importSource) {
	expect($importSource)->toContain("in_array(\$public_key, \$keys, true)");
});

test('import.php retrieves Cacti official public keys', function () use ($importSource) {
	expect($importSource)->toContain('get_public_key_sha1()');
	expect($importSource)->toContain('get_public_key_sha256()');
});

// =====================================================================
// lib/import.php -- no extract() or eval() on untrusted data
// =====================================================================

test('import.php does not use extract()', function () use ($importSource) {
	expect($importSource)->not->toMatch('/\bextract\s*\(/');
});

test('import.php does not use eval()', function () use ($importSource) {
	expect($importSource)->not->toMatch('/\beval\s*\(/');
});

// =====================================================================
// lib/import.php -- XML parsing uses xml_parser_create (not LIBXML_NOENT)
// =====================================================================

test('xml.php uses xml_parser_create not simplexml with entity loading', function () use ($xmlSource) {
	// Cacti xml2array uses the expat-based xml_parser_create, which does not
	// resolve external entities. This is safer than simplexml with LIBXML_NOENT.
	expect($xmlSource)->toContain('xml_parser_create(');
});

test('xml.php does not enable external entity loading', function () use ($xmlSource) {
	expect($xmlSource)->not->toContain('LIBXML_NOENT');
});

// =====================================================================
// lib/import.php -- simplexml_load_string usage (for package info only)
// =====================================================================

test('import.php uses simplexml_load_string without LIBXML_NOENT', function () use ($importSource) {
	// simplexml_load_string is used only after signature verification
	expect($importSource)->toContain('simplexml_load_string(');
	expect($importSource)->not->toContain('LIBXML_NOENT');
});

// =====================================================================
// lib/import.php -- no dynamic include/require with user paths
// =====================================================================

test('import.php only includes via CACTI_PATH_LIBRARY constant', function () use ($importSource) {
	// Match both parenthesized form: include('path') and bare form: include 'path'
	// so the scan does not silently miss unparenthesized includes.
	preg_match_all('/(?:include|require)(?:_once)?\s*(?:\(([^)]+)\)|([\'"][^\'"]+[\'"]))/i', $importSource, $matches);

	// $matches[1] captures parenthesized paths; $matches[2] captures bare paths
	$paths = array_filter(array_merge($matches[1], $matches[2]));

	foreach ($paths as $path) {
		expect($path)->toContain('CACTI_PATH_LIBRARY');
	}
});

// =====================================================================
// lib/package.php -- openssl signature signing
// =====================================================================

test('package.php uses openssl_sign with SHA256 for package signing', function () use ($packageSource) {
	expect($packageSource)->toContain('openssl_sign(');
	expect($packageSource)->toContain('OPENSSL_ALGO_SHA256');
});

test('package.php uses openssl_verify for self-check after signing', function () use ($packageSource) {
	expect($packageSource)->toContain('openssl_verify(');
});

// =====================================================================
// lib/package.php -- file path safety
// =====================================================================

test('package.php uses basename for resource file display', function () use ($packageSource) {
	expect($packageSource)->toContain('basename(');
});

test('package.php uses htmle for output escaping', function () use ($packageSource) {
	expect($packageSource)->toContain('htmle(');
});

// =====================================================================
// lib/package.php -- no eval/extract/dynamic include
// =====================================================================

test('package.php does not use extract()', function () use ($packageSource) {
	expect($packageSource)->not->toMatch('/\bextract\s*\(/');
});

test('package.php does not use eval()', function () use ($packageSource) {
	expect($packageSource)->not->toMatch('/\beval\s*\(/');
});

test('package.php does not use dynamic include with request-derived paths', function () use ($packageSource) {
	// package.php has no include/require at all
	expect($packageSource)->not->toMatch('/\b(?:include|require)(?:_once)?\s*\(/');
});

// =====================================================================
// lib/package.php -- SQLite prepared statements
// =====================================================================

test('package.php uses SQLite prepared statements with bindValue', function () use ($packageSource) {
	expect($packageSource)->toContain('->prepare(');
	expect($packageSource)->toContain('->bindValue(');
	expect($packageSource)->toContain('SQLITE3_TEXT');
});

// =====================================================================
// Findings: patterns that do NOT exist (documented, not failing tests)
// =====================================================================

/*
 * FINDING: import.php file write path does not use realpath() or check for
 * directory traversal sequences (../) in the $name variable from package data.
 * The write is gated by str_contains checks for 'scripts/' and 'resource/',
 * and CACTI_PATH_BASE is prepended, but a crafted name like
 * 'scripts/../../etc/something' would pass the str_contains check.
 * The package signature verification mitigates this (attacker cannot forge
 * a signed package), but defense-in-depth path canonicalization is absent.
 *
 * FINDING: import.php does not check for ZipArchive/PharData extraction
 * because it does not use archive extraction. Packages are gzipped XML,
 * read via compress.zlib:// stream wrapper and parsed line by line.
 *
 * FINDING: No CSRF token validation exists in lib/auth.php itself. CSRF
 * protection is handled at the form/request layer (include/global_session.php),
 * not in the auth library functions.
 *
 * FINDING: package.php has no curl-based remote fetches. The CURLOPT_SSL
 * patterns are not relevant here; remote package fetching (if any) is
 * handled elsewhere.
 */
