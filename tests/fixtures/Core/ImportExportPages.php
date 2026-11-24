<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs import and export page functions with their production source loaded
 * into this namespace and the request, database and output boundaries
 * replaced by the stand-ins below.  Each mode runs in its own process because
 * several of the page functions end the request.
 *
 * Usage: ImportExportPages.php MODE
 */

namespace Cacti\Test\ImportExportPages;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/html.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . "\n");

		exit(1);
	}
}

$GLOBALS['fixture'] = ['request' => [], 'columns' => [], 'logs' => [], 'headers' => [], 'messages' => [], 'calls' => []];

function __(string $format, mixed ...$arguments) : string {
	return $arguments === [] ? $format : vsprintf($format, $arguments);
}

function __esc(string $format, mixed ...$arguments) : string {
	return html_escape(__($format, ...$arguments));
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_log(string $message, bool $output = false, string $environ = '', int $level = 0) : void {
	$GLOBALS['fixture']['logs'][] = $message;
}

function db_column_exists(string $table, string $column) : bool {
	return in_array($column, $GLOBALS['fixture']['columns'], true);
}

function get_public_key_sha1() : string {
	return 'legacy';
}

function get_public_key_sha256() : string {
	return 'built-in';
}

function import_get_package_info(string $xmlfile) : mixed {
	return $GLOBALS['fixture']['package'];
}

function import_public_key_meets_minimum(string $key) : bool {
	return true;
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['fixture']['cell'] ?? false;
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['fixture']['calls'][] = $params;

	return true;
}

$mode = $argv[1] ?? '';

switch ($mode) {
	case 'profile-messages':
		foreach (['profile_import_execute', 'profile_validate_import_columns'] as $function) {
			\cacti_fixture_source_function($root . '/data_source_profiles.php', $function, __NAMESPACE__);
		}

		$GLOBALS['fixture']['columns'] = ['name', 'hash'];

		$result = profile_import_execute(['profile' => [
			['name' => '<b>empty</b>', 'cfs' => [], 'rras' => []],
			['name' => 'columns', '<i>extra</i>' => 1, 'cfs' => [1], 'rras' => [1]],
		]]);

		$errors = implode("\n", $result['errors'] ?? []);

		check(substr_count($errors, '&lt;b&gt;empty&lt;/b&gt;') === 2, 'Profile names in result lines are encoded');
		check(str_contains($errors, '&lt;i&gt;extra&lt;/i&gt;'), 'Column names in result lines are encoded');
		check(!str_contains($errors, '<'), 'No markup from the file reaches the result lines');

		break;
	case 'public-key-details':
		\cacti_fixture_source_function($root . '/package_import.php', 'import_validate_public_key', __NAMESPACE__);

		$GLOBALS['fixture']['package'] = ['pubkey' => 'built-in', 'author' => 'Someone', 'homepage' => 'https://example.invalid', 'email' => 'x@example.invalid'];

		check(import_validate_public_key('package.xml.gz', false) === 'built-in', 'The built in key is reported as trusted');
		check(import_validate_public_key('package.xml.gz', true) === 'built-in', 'Accepting the built in key reports it as trusted');
		check($GLOBALS['fixture']['calls'] === [], 'No key row is stored for the built in key');

		$GLOBALS['fixture']['package']['pubkey'] = 'other';

		check(import_validate_public_key('package.xml.gz', false) === false, 'An unknown key is not trusted from the details view');
		check(import_validate_public_key('package.xml.gz', true) === 'other', 'An accepted key is trusted');
		check(cacti_sizeof($GLOBALS['fixture']['calls']) === 1 && $GLOBALS['fixture']['calls'][0][4] === 'other', 'Only the accepted key is stored');

		break;
	default:
		fwrite(STDERR, "Unknown mode\n");

		exit(2);
}

print "PASS: $mode\n";
