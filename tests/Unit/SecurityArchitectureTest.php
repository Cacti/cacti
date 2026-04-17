<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$execSource     = file_get_contents(__DIR__ . '/../../lib/cacti_exec.php');
$httpSource     = file_get_contents(__DIR__ . '/../../lib/cacti_http.php');
$ldapSource     = file_get_contents(__DIR__ . '/../../lib/ldap.php');
$requestSource  = file_get_contents(__DIR__ . '/../../lib/cacti_request.php');
$dispatchSource = file_get_contents(__DIR__ . '/../../lib/cacti_dispatch.php');
$managersSource = file_get_contents(__DIR__ . '/../../managers.php');
$htmlFormSource = file_get_contents(__DIR__ . '/../../lib/html_form.php');
$authSource     = file_get_contents(__DIR__ . '/../../lib/auth.php');
$globalSource   = file_get_contents(__DIR__ . '/../../include/global.php');
$cliCheckSource = file_get_contents(__DIR__ . '/../../include/cli_check.php');
$importSource    = file_get_contents(__DIR__ . '/../../lib/import.php');
$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');
$databaseSource  = file_get_contents(__DIR__ . '/../../lib/database.php');

/* Item 1: cacti_exec shell execution gateway */
test('cacti_exec requires array input', function () use ($execSource) {
	expect($execSource)->toContain('if (!is_array($argv))');
});

test('cacti_exec uses cacti_escapeshellcmd on binary', function () use ($execSource) {
	expect($execSource)->toContain('cacti_escapeshellcmd($arg)');
});

test('cacti_exec uses cacti_escapeshellarg on arguments', function () use ($execSource) {
	expect($execSource)->toContain('cacti_escapeshellarg($arg)');
});

test('cacti_exec redacts sensitive flags in logs', function () use ($execSource) {
	expect($execSource)->toContain('cacti_exec_redact_cmd');
	expect($execSource)->toContain('[REDACTED]');
});

test('cacti_exec supports timeout via proc_open', function () use ($execSource) {
	expect($execSource)->toContain('function cacti_exec_with_timeout');
	expect($execSource)->toContain('proc_terminate($process, 9)');
});

/* Item 2: cacti_http SSRF protection */
test('cacti_http_fetch rejects reserved IPs', function () use ($httpSource) {
	expect($httpSource)->toContain('cacti_is_reserved_ip');
	expect($httpSource)->toContain('reserved IP rejected');
});

test('cacti_is_reserved_ip uses FILTER_FLAG_NO_RES_RANGE', function () use ($httpSource) {
	expect($httpSource)->toContain('FILTER_FLAG_NO_RES_RANGE');
	expect($httpSource)->toContain('FILTER_FLAG_NO_PRIV_RANGE');
});

test('cacti_https_context enforces verify_peer by default', function () use ($httpSource) {
	expect($httpSource)->toContain("'verify_peer'       => true");
	expect($httpSource)->toContain("'verify_peer_name'  => true");
});

test('cacti_http_fetch restricts scheme to http/https', function () use ($httpSource) {
	expect($httpSource)->toContain("scheme must be http or https");
});

test('cacti_https_context disables follow_location by default', function () use ($httpSource) {
	expect($httpSource)->toContain("'follow_location' => 0");
});

/* Item 3: LDAP filter injection prevention */
test('cacti_ldap_filter uses ldap_escape', function () use ($ldapSource) {
	expect($ldapSource)->toContain('function cacti_ldap_filter');
	expect($ldapSource)->toContain('ldap_escape(');
	expect($ldapSource)->toContain('LDAP_ESCAPE_FILTER');
});

/* Item 4: Request-layer helpers */
test('get_request_sort whitelists column names', function () use ($requestSource) {
	expect($requestSource)->toContain('function get_request_sort');
	expect($requestSource)->toContain("in_array(\$col, \$allowed, true)");
});

test('get_request_sort forces ASC or DESC', function () use ($requestSource) {
	$start = strpos($requestSource, 'function get_request_sort');
	$body  = substr($requestSource, $start, 600);
	expect($body)->toContain("!== 'ASC'");
	expect($body)->toContain("!== 'DESC'");
});

test('get_request_ids filters non-numeric values', function () use ($requestSource) {
	expect($requestSource)->toContain('function get_request_ids');
	expect($requestSource)->toContain('is_numeric($val)');
});

test('db_qstr_like escapes percent and underscore', function () use ($requestSource) {
	expect($requestSource)->toContain('function db_qstr_like');
	expect($requestSource)->toContain("'\\\\%'");
	expect($requestSource)->toContain("'\\\\_'");
});

/* Item 5: Centralized dispatch */
test('cacti_dispatch enforces HTTP method', function () use ($dispatchSource) {
	expect($dispatchSource)->toContain('function cacti_dispatch');
	expect($dispatchSource)->toContain('405 Method Not Allowed');
});

test('cacti_dispatch enforces realm permission', function () use ($dispatchSource) {
	expect($dispatchSource)->toContain('is_realm_allowed');
});

test('cacti_dispatch supports object-level ACL', function () use ($dispatchSource) {
	expect($dispatchSource)->toContain('object_acl');
	expect($dispatchSource)->toContain('is_callable($entry[\'object_acl\'])');
});

test('cacti_dispatch logs unknown actions', function () use ($dispatchSource) {
	expect($dispatchSource)->toContain('unknown action');
});

/* Item 6: XSS fixes */
test('managers.php escapes description in tooltip', function () use ($managersSource) {
	expect($managersSource)->toContain("html_escape(\$item['description'])");
});

test('html_form.php escapes description in formFieldDescription span', function () use ($htmlFormSource) {
	expect($htmlFormSource)->toContain("html_escape(\$field_array['description']) : ''");
});

test('html_form.php escapes description in tooltip display', function () use ($htmlFormSource) {
	expect($htmlFormSource)->toContain("display_tooltip(html_escape(\$field_array['description']))");
});

/* Item 7: Auth transition helper */
test('cacti_auth_transition regenerates session ID', function () use ($authSource) {
	expect($authSource)->toContain('function cacti_auth_transition');
	expect($authSource)->toContain('session_regenerate_id(true)');
});

test('cacti_auth_transition checks lockout status', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_auth_transition');
	$body  = substr($authSource, $start, 1500);
	expect($body)->toContain("locked == 'on'");
});

test('cacti_auth_transition rotates remember-me cookie', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_auth_transition');
	$body  = substr($authSource, $start, 1500);
	expect($body)->toContain('cacti_remembers');
	expect($body)->toContain('random_bytes');
});

test('cacti_auth_transition invalidates permission cache', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_auth_transition');
	$body  = substr($authSource, $start, 1500);
	expect($body)->toContain("kill_session_var('sess_user_realms')");
});

/* CSP and header hardening (from commit 2) */
test('global.php sets default-src to self', function () use ($globalSource) {
	expect($globalSource)->toContain("default-src 'self'");
});

test('global.php sets HSTS header on HTTPS', function () use ($globalSource) {
	expect($globalSource)->toContain('Strict-Transport-Security');
});

test('global.php sets COOP header', function () use ($globalSource) {
	expect($globalSource)->toContain('Cross-Origin-Opener-Policy: same-origin');
});

test('cli_check.php rejects non-CLI SAPI', function () use ($cliCheckSource) {
	expect($cliCheckSource)->toContain("php_sapi_name() !== 'cli'");
	expect($cliCheckSource)->toContain('http_response_code(404)');
});

/* XML hardening (from commit 2) */
test('import.php enforces XML size limit', function () use ($importSource) {
	expect($importSource)->toContain('$max_xml_size = 50 * 1024 * 1024');
});

test('import.php logs XML parse errors', function () use ($importSource) {
	expect($importSource)->toContain('XML parse error');
	expect($importSource)->toContain('libxml_get_errors()');
});

/* Item A: cacti_safe_write */
test('cacti_safe_write rejects absolute paths', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_safe_write.php');
	expect($source)->toContain('absolute path rejected');
});

test('cacti_safe_write rejects traversal segments', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_safe_write.php');
	expect($source)->toContain('traversal rejected');
});

test('cacti_safe_write uses atomic rename', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_safe_write.php');
	expect($source)->toContain('rename(');
});

test('cacti_safe_write verifies realpath post-write', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_safe_write.php');
	expect($source)->toContain('post-write realpath check');
});

/* Item B: cacti_plugin_path */
test('cacti_plugin_path uses basename on plugin name', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_plugin_path.php');
	expect($source)->toContain('basename($plugin_name)');
});

test('cacti_plugin_path verifies realpath containment', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/cacti_plugin_path.php');
	expect($source)->toContain('path escapes plugins dir');
});

test('lib/plugins.php uses cacti_plugin_path for includes', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');
	expect($source)->toContain('cacti_plugin_path(');
});

/* SHA-1 removal */
test('import.php uses SHA-256 for package verification', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/import.php');
	expect($source)->toContain('OPENSSL_ALGO_SHA256');
	expect($source)->not->toMatch('/openssl_verify\([^)]*OPENSSL_ALGO_SHA1/');
});

/* Tier 1 Item 1: cacti_path_is_within */
test('cacti_path_is_within resolves both paths via realpath', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_path_is_within');
	$body  = substr($functionsSource, $start, 800);
	expect($body)->toContain('realpath($candidate)');
	expect($body)->toContain('realpath($base)');
});

test('cacti_path_is_within handles Windows path separators', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_path_is_within');
	$body  = substr($functionsSource, $start, 800);
	expect($body)->toContain("DIRECTORY_SEPARATOR === '\\\\'");
	expect($body)->toContain("str_replace('\\\\', '/'");
});

test('cacti_path_is_within allows path equal to base', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_path_is_within');
	$body  = substr($functionsSource, $start, 800);
	expect($body)->toContain('$resolved === $base_resolved');
});

/* Tier 1 Item 2: cacti_remote_url */
test('cacti_remote_url encodes action parameter', function () use ($httpSource) {
	expect($httpSource)->toContain('function cacti_remote_url');
	expect($httpSource)->toContain('rawurlencode($action)');
});

test('cacti_remote_url encodes additional params', function () use ($httpSource) {
	$start = strpos($httpSource, 'function cacti_remote_url');
	$body  = substr($httpSource, $start, 800);
	expect($body)->toContain('rawurlencode($key)');
	expect($body)->toContain('rawurlencode($value)');
});

/* Tier 1 Item 3: cacti_redirect */
test('cacti_redirect uses validate_redirect_url', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_redirect');
	$start = strpos($functionsSource, 'function cacti_redirect');
	$body  = substr($functionsSource, $start, 600);
	expect($body)->toContain('validate_redirect_url');
});

test('cacti_redirect falls back to HTTP_REFERER', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_redirect');
	$body  = substr($functionsSource, $start, 600);
	expect($body)->toContain('HTTP_REFERER');
});

/* Tier 1 Item 4: cacti_fetch_by_id */
test('cacti_fetch_by_id sanitizes table and column names', function () use ($databaseSource) {
	expect($databaseSource)->toContain('function cacti_fetch_by_id');
	$start = strpos($databaseSource, 'function cacti_fetch_by_id');
	$body  = substr($databaseSource, $start, 1200);
	expect($body)->toContain("preg_replace('/[^a-zA-Z0-9_]/', '', \$table)");
	expect($body)->toContain("preg_replace('/[^a-zA-Z0-9_]/', '', \$id_column)");
});

test('cacti_fetch_by_id rejects non-positive IDs', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function cacti_fetch_by_id');
	$body  = substr($databaseSource, $start, 600);
	expect($body)->toContain('$id <= 0');
});

test('cacti_fetch_by_id uses prepared statement', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function cacti_fetch_by_id');
	$body  = substr($databaseSource, $start, 1200);
	expect($body)->toContain('db_fetch_row_prepared');
});

/* Tier 1 Item 5: cacti_redact_sensitive */
test('cacti_redact_sensitive replaces known sensitive keys', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_redact_sensitive');
	$start = strpos($functionsSource, 'function cacti_redact_sensitive');
	$body  = substr($functionsSource, $start, 1200);
	expect($body)->toContain("'password'");
	expect($body)->toContain("'snmp_community'");
	expect($body)->toContain("'[REDACTED]'");
});

test('cacti_redact_sensitive recurses into nested arrays', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_redact_sensitive');
	$body  = substr($functionsSource, $start, 1200);
	expect($body)->toContain('cacti_redact_sensitive($value)');
});

/* Tier 1 Item 6: cacti_temp_file */
test('cacti_temp_file cleans up in finally block', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_temp_file');
	$start = strpos($functionsSource, 'function cacti_temp_file');
	$body  = substr($functionsSource, $start, 600);
	expect($body)->toContain('finally');
	expect($body)->toContain('@unlink($path)');
});

test('cacti_temp_file logs on tempnam failure', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_temp_file');
	$body  = substr($functionsSource, $start, 600);
	expect($body)->toContain('tempnam failed');
});

/* Tier 2 Item 7: cacti_exec_with_redirect */
test('cacti_exec_with_redirect validates argv', function () use ($execSource) {
	expect($execSource)->toContain('function cacti_exec_with_redirect');
	$start = strpos($execSource, 'function cacti_exec_with_redirect');
	$body  = substr($execSource, $start, 400);
	expect($body)->toContain('!is_array($argv) || empty($argv)');
});

test('cacti_exec_with_redirect uses cacti_escapeshellcmd and cacti_escapeshellarg', function () use ($execSource) {
	$start = strpos($execSource, 'function cacti_exec_with_redirect');
	$body  = substr($execSource, $start, 800);
	expect($body)->toContain('cacti_escapeshellcmd($argv[0])');
	expect($body)->toContain('cacti_escapeshellarg($argv[$i])');
});

test('cacti_exec_with_redirect supports file redirection', function () use ($execSource) {
	$start = strpos($execSource, 'function cacti_exec_with_redirect');
	$body  = substr($execSource, $start, 1200);
	expect($body)->toContain("array('file', \$stdout_file, \$mode)");
	expect($body)->toContain('proc_open');
});

/* Tier 2 Item 8: cacti_csrf_rotate */
test('cacti_csrf_rotate regenerates session', function () use ($authSource) {
	expect($authSource)->toContain('function cacti_csrf_rotate');
	$start = strpos($authSource, 'function cacti_csrf_rotate');
	$body  = substr($authSource, $start, 800);
	expect($body)->toContain('cacti_session_regenerate()');
});

test('cacti_csrf_rotate refreshes remember-me cookie', function () use ($authSource) {
	$start = strpos($authSource, 'function cacti_csrf_rotate');
	$body  = substr($authSource, $start, 800);
	expect($body)->toContain('cacti_remembers');
	expect($body)->toContain('set_auth_cookie');
});

/* Tier 2 Item 9: cacti_db_in + cacti_db_like */
test('cacti_db_in casts values to int', function () use ($requestSource) {
	expect($requestSource)->toContain('function cacti_db_in');
	$start = strpos($requestSource, 'function cacti_db_in');
	$body  = substr($requestSource, $start, 400);
	expect($body)->toContain("array_map('intval'");
});

test('cacti_db_in returns (0) for empty input', function () use ($requestSource) {
	$start = strpos($requestSource, 'function cacti_db_in');
	$body  = substr($requestSource, $start, 400);
	expect($body)->toContain("return '(0)'");
});

test('cacti_db_like escapes wildcards for prepared statements', function () use ($requestSource) {
	expect($requestSource)->toContain('function cacti_db_like');
	$start = strpos($requestSource, 'function cacti_db_like');
	$body  = substr($requestSource, $start, 400);
	expect($body)->toContain("'\\\\%'");
	expect($body)->toContain("'\\\\_'");
	expect($body)->toContain("'LIKE ?'");
});
