<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Drives LocalAuthLoginProvider::authenticate() in its own process and prints
 * which hashes the login verified against and what it stored. The password
 * functions are the production ones, sliced from lib/auth.php; only the
 * database, logging, lockout and plugin boundaries are replaced. A separate
 * process is needed because the fakes share names with lib/ functions.
 *
 * run_pr_change_tests.sh runs each scenario directly for the changed-line
 * gate; LocalAuthDecoyCoverage.php runs all of them for the request-coverage
 * gate. Both collect coverage through FixtureCoverage.php.
 *
 * Scenarios: known (wrong password for an existing user), unknown (an absent
 * user with a password), unknown-empty (an absent user with no password),
 * legacy (the right password for a user with an unsalted md5 digest) and
 * current (the right password for a user with a current hash).
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require dirname(__DIR__, 3) . '/include/global_constants.php';
require dirname(__DIR__, 3) . '/include/vendor/autoload.php';

use Cacti\Auth\LocalAuthLoginProvider;

foreach (['cacti_password_algorithm', 'cacti_password_decoy_for', 'cacti_password_decoy_hash',
	'compat_password_verify', 'compat_password_hash', 'compat_password_needs_rehash'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/auth.php', $function, '');
}

require __DIR__ . '/LocalAuthDecoyRecorder.php';

$scenario = $argv[1] ?? 'unknown';
// md5('stored secret'), as a very old release stored it
$legacy   = '226f0449623934404b8e03c686b22d1b';
$current  = compat_password_hash('stored secret', PASSWORD_DEFAULT);
$stored   = ['known' => $current, 'legacy' => $legacy, 'current' => $current][$scenario] ?? '';
$verified = [];
$updates  = [];

/**
 * Fake plugin hook: no plugin takes over local login.
 *
 * @return mixed The unmodified hook data.
 */
function api_plugin_hook_function(string $name, mixed $data = false) : mixed {
	return $data;
}

/**
 * Fake gettext passthrough.
 *
 * @return string The untranslated text.
 */
function __(string $text) : string {
	return $text;
}

/**
 * Fake logger; login failures are asserted through the result instead.
 *
 * @return void
 */
function cacti_log(mixed ...$args) : void {
}

/**
 * Fake client address lookup.
 *
 * @return string A documentation-range address.
 */
function get_client_addr() : string {
	return '192.0.2.10';
}

/**
 * Same contract as lib/functions.php cacti_sizeof().
 *
 * @return int The element count, or 0 for a non-array.
 */
function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

/**
 * Fake setting lookup: no password policy or expiry is configured.
 *
 * @return string Always empty.
 */
function read_config_option(string $name) : string {
	return '';
}

/**
 * Fake lockout reset; this probe never locks an account.
 *
 * @return void
 */
function auth_checkclear_lockout(string $username, int $realm) : void {
}

/**
 * Fake lockout check: no account is locked out.
 *
 * @return bool Always false.
 */
function auth_process_lockout_check(string $username, int $realm) : bool {
	return false;
}

/**
 * Fake failed-attempt counter.
 *
 * @return void
 */
function auth_process_lockout(string $username, int $realm) : void {
}

/**
 * Fake schema probe: the lastfail column exists.
 *
 * @return bool Always true.
 */
function db_column_exists(string $table, string $column) : bool {
	return true;
}

/**
 * Fake password column check; the column is already wide enough.
 *
 * @return void
 */
function db_check_password_length() : void {
}

/**
 * Fake user_auth lookup: only the known, legacy and current scenarios find an
 * enabled account, and its password is whatever was last stored.
 *
 * @return array The user row, or [] when the username is unknown.
 */
function db_fetch_row_prepared(string $sql, array $params = []) : array {
	if ($GLOBALS['stored'] === '') {
		return [];
	}

	return ['id' => 7, 'username' => $params[0], 'lastfail' => 0, 'failed_attempts' => 0, 'locked' => '', 'enabled' => 'on', 'password' => $GLOBALS['stored']];
}

/**
 * Records each write and applies a password update to the fake row.
 *
 * @return bool Always true.
 */
function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['updates'][] = $params;

	if (str_contains($sql, 'SET password = ?')) {
		$GLOBALS['stored'] = $params[0];
	}

	return true;
}

$error     = false;
$error_msg = '';
$password  = ['known' => 'guessed secret', 'unknown' => 'guessed secret', 'unknown-empty' => ''][$scenario] ?? 'stored secret';
$username  = $scenario === 'unknown' || $scenario === 'unknown-empty' ? 'ghost' : 'admin';
$before    = $stored;
$result    = (new LocalAuthLoginProvider())->authenticate($username, $password);
$expected  = [
	'known'         => [false, [$before], 0],
	'unknown'       => [false, [cacti_password_decoy_hash()], 0],
	'unknown-empty' => [false, [], 0],
	'legacy'        => [true, [$before], 1],
	'current'       => [true, [$before], 0],
][$scenario] ?? null;

// Fail the coverage run itself, not only the Pest test reading the verdict,
// when a login has the wrong outcome, verifies against the wrong hashes or
// stores a hash it should not.
if ($expected === null || $result->success !== $expected[0] || $verified !== $expected[1] || count($updates) !== $expected[2]) {
	throw new RuntimeException('Unexpected local login behavior for scenario ' . $scenario);
}

if ($scenario === 'legacy' && (!compat_password_verify('stored secret', $stored) || compat_password_needs_rehash($stored, PASSWORD_DEFAULT))) {
	throw new RuntimeException('The legacy digest was not replaced with a current hash');
}

print json_encode([
	'success'  => $result->success,
	'error'    => $result->error,
	'verified' => $verified,
	'decoy'    => cacti_password_decoy_hash(),
	'before'   => $before,
	'stored'   => $stored,
	'updates'  => count($updates),
], JSON_THROW_ON_ERROR) . "\n";
