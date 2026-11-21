<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\PackageImportStatus;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';

define('CACTI_PATH_BASE', $root);

function __(string $value) : string {
	return $value;
}

function cacti_log(string $message, bool $output = false, string $type = '', int $level = 0) : void {
	$GLOBALS['messages'][] = $message;
}

// Model the two filesystem responses without placing package files in the checkout.
function is_link(string $filename) : bool {
	return $filename === CACTI_PATH_BASE . '/scripts/sample.txt';
}

function mkdir(string $directory, int $permissions, bool $recursive) : bool {
	return false;
}

function import_read_package_data(string $file, mixed &$key, bool $preview) : array {
	$key = $GLOBALS['key'];

	return ['files' => ['file' => [[
		'name' => 'scripts/sample.txt', 'filesignature' => base64_encode($GLOBALS['signature']), 'data' => base64_encode('sample')
	]]]];
}

foreach (['import_public_key_meets_minimum', 'import_write_blocked_by_symlink', 'import_package'] as $function) {
	\cacti_fixture_source_function($root . '/lib/import.php', $function, __NAMESPACE__);
}
define('IMPORT_PACKAGE_KEY_MIN_BITS', 2048);
$private        = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$GLOBALS['key'] = openssl_pkey_get_details($private)['key'];

if (!openssl_sign('sample', $GLOBALS['signature'], $private, OPENSSL_ALGO_SHA256)) {
	throw new \RuntimeException('Cannot sign sample data');
}

if (import_write_blocked_by_symlink($root . '/scripts/regular.txt', 'scripts/regular.txt')) {
	throw new \RuntimeException('A regular file should pass the package destination check');
}
$result = import_package('fixture.xml', limitex: false);

if ($result[1] !== ['scripts/sample.txt' => 'rejected']) {
	throw new \RuntimeException('The import did not report the blocked file');
}

$GLOBALS['messages'] = [];
\cacti_fixture_source_function($root . '/lib/package.php', 'package_private_tmpdir', __NAMESPACE__);
$_SESSION  = [];
$directory = package_private_tmpdir();

if (is_dir($directory) || $GLOBALS['messages'] !== ['ERROR: Unable to create private package temporary directory']) {
	throw new \RuntimeException('Workspace creation failure did not reach the import log');
}

print "PASS: package import reports an unavailable destination and workspace\n";
