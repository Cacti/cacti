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

function isrv(string $name) : bool {
	return isset($GLOBALS['fixture']['request'][$name]);
}

function gfrv(string $name, mixed ...$filter) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? '';
}

function grv(string $name) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? '';
}

function gnrv(string $name) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? '';
}

function db_fetch_cell(string $sql) : mixed {
	return 1;
}

function header(string $header) : void {
	$GLOBALS['fixture']['headers'][] = $header;
}

function raise_message(string $id, string $message = '', int $level = 0) : void {
	$GLOBALS['fixture']['messages'][] = [$id, $message, $level];
}

function raise_message_javascript(string $title, string $header, string $message) : void {
	$GLOBALS['fixture']['messages'][] = ['javascript', $header, $message];
}

function import_xml_data(string &$xml_data, bool $import_as_new, int $profile_id, bool $remove_orphans = false, bool $replace_svalues = false, array $import_hashes = []) : mixed {
	$GLOBALS['fixture']['calls'][] = ['import_xml_data', $xml_data];

	return $GLOBALS['fixture']['import_result'];
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
	case 'template-import-result':
		\cacti_fixture_source_function($root . '/templates_import.php', 'form_save', __NAMESPACE__);

		$upload = tempnam(sys_get_temp_dir(), 'cacti-template-');
		file_put_contents($upload, '<cacti/>');

		$_FILES                        = ['import_file' => ['tmp_name' => $upload, 'name' => 'template.xml']];
		$GLOBALS['fixture']['request'] = ['save_component_import' => '1', 'import_data_source_profile' => '1', 'preview_only' => 'false'];

		$GLOBALS['fixture']['import_result'] = false;
		form_save();

		check($GLOBALS['fixture']['messages'][0][0] === 'javascript', 'A failed import is reported as failed');
		check($GLOBALS['fixture']['headers'] === [], 'A failed import does not redirect as a success');

		$GLOBALS['fixture']['messages']      = [];
		$GLOBALS['fixture']['import_result'] = ['vdef' => []];
		form_save();

		check($GLOBALS['fixture']['messages'] === [['import_success', 'The Template Import Succeeded.', MESSAGE_LEVEL_INFO]], 'A completed import is reported as succeeded');
		check($GLOBALS['fixture']['headers'] === ['Location: templates_import.php'], 'A completed import returns to the import page');

		unlink($upload);

		break;
	default:
		fwrite(STDERR, "Unknown mode\n");

		exit(2);
}

print "PASS: $mode\n";
