<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\PackageWorkspace;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';

define('CACTI_PATH_BASE', $root);
define('CACTI_PATH_LIBRARY', $root . '/lib');

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function __(string $text) : string {
	return $text;
}

function __esc(string $format, mixed ...$args) : string {
	return sprintf($format, ...$args);
}

function cacti_sizeof(mixed $items) : int {
	return is_array($items) ? count($items) : 0;
}

function cacti_log(string $message, bool $output = false, string $type = '', int $level = 0) : void {
	$GLOBALS['package_logs'][] = $message;
}

function gfrv(string $name, mixed ...$filters) : string {
	return ['drp_action' => '1', 'package_location' => '1', 'package_ids' => '0', 'data_source_profile' => '1'][$name] ?? '';
}

function grv(string $name) : string {
	return gfrv($name);
}

function isrv(string $name) : bool {
	return $name === 'import_state';
}

function is_realm_allowed(int $realm) : bool {
	return $realm === 29;
}

function package_import_take_state(string $token) : array {
	return [
		'package_location' => '1', 'data_source_profile' => 1, 'remove_orphans' => false, 'replace_svalues' => false,
		'selected_items'   => [], 'selected_hashes' => [], 'selected_files' => [['filename' => 'monitor.gz', 'pfile' => 'template.xml']]
	];
}

function get_repo_manifest_file(string $location) : string {
	return json_encode(['manifest' => [[
		'name' => 'Monitor', 'filename' => 'monitor.gz', 'metadata' => ['author' => '', 'homepage' => '', 'email' => '']
	]]], JSON_THROW_ON_ERROR);
}

function get_repo_file(string $location, string $name, bool $javascript = false) : string {
	return 'fixture package bytes';
}

function inspect_workspace(string $file) : void {
	check(basename($file) === 'monitor.gz', 'Unexpected package filename');
	check(file_get_contents($file) === 'fixture package bytes', 'Incomplete package data');
	check(dirname($file) === $GLOBALS['workspace'], 'Package was written outside the session workspace');
	$GLOBALS['inspections']++;
}

function import_validate_signature(string $file) : bool {
	inspect_workspace($file);

	return true;
}

function import_validate_public_key(string $file, bool $accept) : string {
	inspect_workspace($file);

	return 'fixture key';
}

function import_package_get_public_key(string $file) : string {
	inspect_workspace($file);

	return 'fixture key';
}

function import_read_package_data(string $file, mixed &$signature, bool $preview) : array {
	return ['files' => ['file' => []]];
}

function import_package(string $file, mixed ...$options) : array {
	inspect_workspace($file);

	return [['fixture']];
}

function package_prepare_import_array(array &$templates, array &$files, mixed ...$items) : void {
}

function import_display_package_data(mixed ...$items) : void {
}

function import_display_results(mixed ...$items) : void {
}

function raise_message(mixed ...$items) : void {
}

class Redirect extends \RuntimeException {
}

// The final import redirects and exits after removing the transfer file.
function header(string $header) : void {
	check($header === 'Location: package_import.php?package_location=1', 'Unexpected import redirect');

	throw new Redirect();
}

// Load the controller's library dependencies with their normal order and paths.
$controller = $root . '/package_import.php';
$source     = file_get_contents($controller);

if (!preg_match('/^require_once\(CACTI_PATH_LIBRARY[^\n]*(?:\nrequire_once\(CACTI_PATH_LIBRARY[^\n]*)*/m', $source, $match, PREG_OFFSET_CAPTURE)) {
	throw new \RuntimeException('Missing controller library imports');
}
[$dependencies, $offset] = $match[0];
$bootstrap               = tempnam(sys_get_temp_dir(), 'cacti-package-libraries-');
file_put_contents($bootstrap, '<?php ' . str_repeat("\n", substr_count(substr($source, 0, $offset), "\n")) . $dependencies);
$GLOBALS['cacti_fixture_source_map'][$bootstrap] = $controller;

try {
	require $bootstrap;
} finally {
	unlink($bootstrap);
}
check(function_exists('package_private_tmpdir'), 'Missing package workspace dependency');

foreach (['form_actions', 'package_file_get_contents', 'package_verify_key', 'package_accept_key', 'package_get_details'] as $handler) {
	\cacti_fixture_source_function($controller, $handler, __NAMESPACE__);
}
\cacti_fixture_source_function($root . '/lib/package.php', 'find_paths', __NAMESPACE__);

$_SESSION               = [];
$GLOBALS['workspace']   = \package_private_tmpdir();
$GLOBALS['inspections'] = 0;

try {
	check((fileperms($GLOBALS['workspace']) & 0777) === 0700, 'Session workspace is not private');
	ob_start();
	package_verify_key();
	$status = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
	check($status['status'] === 'success', 'Signer verification failed');
	package_accept_key();
	package_get_details();
	check(package_file_get_contents('1', 'monitor.gz', 'template.xml') === false, 'An absent package member was returned');

	try {
		form_actions();

		throw new \RuntimeException('Missing final redirect');
	} catch (Redirect) {
		check($GLOBALS['inspections'] === 6, 'An import operation skipped its workspace');
	}
	check(glob($GLOBALS['workspace'] . '/*') === [], 'A completed import left temporary files');
} finally {
	foreach (glob($GLOBALS['workspace'] . '/*') as $file) {
		unlink($file);
	}
	rmdir($GLOBALS['workspace']);
}
$script = glob($root . '/scripts/*.php')[0];
$paths  = find_paths($script . ' ' . $root . '/README.md');
check(array_column($paths['paths'], 'file') === [$script], 'Expected script missing from export');
check(array_column($paths['missing_paths'], 'file') === [$root . '/README.md'], 'An unrelated document was included in export');

print "PASS: repository imports share a private workspace and clean up each transfer\n";
