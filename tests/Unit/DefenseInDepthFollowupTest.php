<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$funcSource  = file_get_contents(__DIR__ . '/../../lib/functions.php');
$dbSource    = file_get_contents(__DIR__ . '/../../lib/database.php');
$boostSource = file_get_contents(__DIR__ . '/../../poller_boost.php');
$importSource = file_get_contents(__DIR__ . '/../../package_import.php');
$indexSource = file_get_contents(__DIR__ . '/../../index.php');
$ssSource    = file_get_contents(__DIR__ . '/../../script_server.php');

// --- cacti_csv_safe ---

test('cacti_csv_safe strips leading whitespace before checking dangerous chars', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_csv_safe(');
	$body = substr($funcSource, $start, 600);
	expect($body)->toContain('ltrim(');
});

test('cacti_csv_safe includes tab in dangerous list', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_csv_safe(');
	$body = substr($funcSource, $start, 600);
	expect($body)->toContain('"\t"');
});

test('cacti_csv_safe includes carriage return in dangerous list', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_csv_safe(');
	$body = substr($funcSource, $start, 600);
	expect($body)->toContain('"\r"');
});

test('cacti_csv_safe guards non-string non-numeric input', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_csv_safe(');
	$body = substr($funcSource, $start, 600);
	expect($body)->toContain('!is_string($value) && !is_numeric($value)');
});

// --- cacti_path_is_within ---

test('cacti_path_is_within helper exists in functions.php', function () use ($funcSource) {
	expect($funcSource)->toContain('function cacti_path_is_within(');
});

test('cacti_path_is_within uses realpath for both candidate and base', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_path_is_within(');
	$body = substr($funcSource, $start, 800);
	expect($body)->toContain('realpath($candidate)');
	expect($body)->toContain('realpath($base)');
});

test('cacti_path_is_within handles Windows case-insensitive comparison', function () use ($funcSource) {
	$start = strpos($funcSource, 'function cacti_path_is_within(');
	$body = substr($funcSource, $start, 800);
	expect($body)->toContain("DIRECTORY_SEPARATOR === '\\\\'");
	expect($body)->toContain('strtolower(');
});

// --- index.php uses cacti_path_is_within ---

test('index.php uses cacti_path_is_within for include path validation', function () use ($indexSource) {
	expect($indexSource)->toContain('cacti_path_is_within(');
});

// --- script_server.php uses cacti_path_is_within ---

test('script_server.php uses cacti_path_is_within for include path validation', function () use ($ssSource) {
	expect($ssSource)->toContain('cacti_path_is_within(');
});

// --- db_replace redaction ---

test('db_replace redacts snmp_auth_passphrase', function () use ($dbSource) {
	expect($dbSource)->toContain("'snmp_auth_passphrase'");
});

test('db_replace redacts rsa_private_key', function () use ($dbSource) {
	expect($dbSource)->toContain("'rsa_private_key'");
});

test('db_replace redacts secret', function () use ($dbSource) {
	expect($dbSource)->toContain("'secret'");
});

test('db_replace redacts auth_key', function () use ($dbSource) {
	expect($dbSource)->toContain("'auth_key'");
});

test('db_replace redacts priv_key', function () use ($dbSource) {
	expect($dbSource)->toContain("'priv_key'");
});

// --- boost path confinement ---

test('poller_boost rejects cache_directory equal to base_path', function () use ($boostSource) {
	expect($boostSource)->toMatch('/\$normalized_cache\s*===\s*\$normalized_base/');
});

test('poller_boost requires strict subdirectory check', function () use ($boostSource) {
	$start = strpos($boostSource, 'Reject if cache dir IS the base path');
	$block = substr($boostSource, $start, 300);
	// Must have separate equality check AND prefix check
	expect($block)->toContain('$normalized_cache === $normalized_base');
	expect($block)->toContain("\$normalized_base . '/'");
});

// --- package_import tempfile cleanup ---

test('package_import cleans up session tempfile on signature failure', function () use ($importSource) {
	$start = strpos($importSource, 'session_tmpfile');
	expect($start)->not->toBeFalse();

	// The unlink must appear before the exit on signature failure path
	$sig_fail = strpos($importSource, 'package_validate_signature');
	$block = substr($importSource, $sig_fail, 400);
	expect($block)->toContain('@unlink($xmlfile)');
});
