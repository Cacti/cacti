<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs import_package() from lib/import.php over a Package with two template
 * files.  The Package reader and the template importer are replaced by the
 * stand-ins below; the signature checks run on a throwaway key.
 */

namespace Cacti\Test\PackageTemplateResults;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';

define('CACTI_PATH_BASE', $root);

function check(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . "\n");

		exit(1);
	}
}

function cacti_log(string $message, bool $output = false, string $environ = '', int $level = 0) : void {
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function import_public_key_meets_minimum(string $key) : bool {
	return true;
}

function import_read_package_data(string $file, mixed &$key, bool $preview) : array {
	$key = $GLOBALS['key'];

	return ['files' => ['file' => $GLOBALS['package_files']]];
}

function import_xml_data(string &$xml_data, bool $import_as_new, int $profile_id, bool $remove_orphans = false, bool $replace_svalues = false, array $import_hashes = []) : mixed {
	return array_shift($GLOBALS['import_results']);
}

\cacti_fixture_source_function($root . '/lib/import.php', 'import_package', __NAMESPACE__);

$private        = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$GLOBALS['key'] = openssl_pkey_get_details($private)['key'];

foreach (['first.xml', 'second.xml'] as $name) {
	openssl_sign($name, $signature, $private, OPENSSL_ALGO_SHA256);

	$GLOBALS['package_files'][] = ['name' => $name, 'data' => base64_encode($name), 'filesignature' => base64_encode($signature)];
}

$GLOBALS['import_results'] = [
	['graph_template' => [['hash' => 'one', 'title' => 'First']]],
	['graph_template' => [['hash' => 'two', 'title' => 'Second']], 'vdef' => [['hash' => 'three', 'title' => 'Third']]],
];

$result = import_package('package.xml.gz', 1, false, false, true, false, false);

check(is_array($result) && array_column($result[0]['graph_template'], 'title') === ['First', 'Second'], 'Each template file of a package is reported');
check(array_column($result[0]['vdef'] ?? [], 'title') === ['Third'], 'Every object type of a later file is reported');

$GLOBALS['import_results'] = [['vdef' => []], false];

check(import_package('package.xml.gz', 1, false, false, true, false, false) === false, 'A rejected template file fails the package');

print "PASS: package-results\n";
