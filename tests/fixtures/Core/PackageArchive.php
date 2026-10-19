<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

$root                              = dirname(__DIR__, 3);
$GLOBALS['cacti_fixture_contract'] = [
	'name'      => 'package-archive', 'complete' => false, 'assertions' => 0,
	'producers' => ['tests/fixtures/Core/PackageArchive.php', 'tests/Helpers/RequestCoverage.php'],
];
require $root . '/tests/Helpers/RequestCoverage.php';
$temporary = sys_get_temp_dir() . '/cacti-package-' . bin2hex(random_bytes(8));

if (!mkdir($temporary, 0700)) {
	throw new RuntimeException('Cannot create disposable packaging directory');
}
define('CACTI_PATH_BASE', $root);
define('CACTI_PATH_PKI', $temporary);
define('CACTI_SERVER_OS', 'unix');
define('CACTI_WEB', false);
require $root . '/include/global_constants.php';
$config = [OPTIONS_CLI => ['log_validation' => '']];
require $root . '/lib/functions.php';
require $root . '/lib/html_utility.php';
require $root . '/lib/package.php';

$package_file = '';
$name         = basename($temporary);
$xmlPath      = '/tmp/' . clean_up_name($name) . '.xml';
$archivePath  = $xmlPath . '.gz';

try {
	$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

	if ($key === false || !openssl_pkey_export_to_file($key, $temporary . '/package.key')) {
		throw new RuntimeException('Cannot create disposable signing key');
	}
	$public = openssl_pkey_get_details($key)['key'];

	if (file_put_contents($temporary . '/package.pem', $public) !== strlen($public)) {
		throw new RuntimeException('Cannot store public key');
	}

	foreach ([[], ['export_type' => 'graph_template', 'export_item_id' => 999999]] as $request) {
		$_REQUEST = $_GET = $_POST = $GLOBALS['_CACTI_REQUEST'] = $request;
		$template = '<cacti><fixture>Original template bytes</fixture></cacti>';
		$info     = ['name' => $name, 'author' => 'Disposable fixture'];
		$files    = [];
		$debug    = '';

		if (!package_template($template, $info, $files, $debug) || basename($package_file) !== basename($archivePath) || !is_file($archivePath)) {
			throw new RuntimeException('Archive filename must come from the supplied package metadata');
		}
		$xml      = gzdecode(file_get_contents($package_file));
		$document = simplexml_load_string($xml);

		if ($document === false || (string) $document->info->name !== $name || count($document->files->file) !== 1) {
			throw new RuntimeException('Archive must contain the named template');
		}
		$file = $document->files->file[0];
		$data = base64_decode((string) $file->data, true);

		if ($data !== $template || openssl_verify($data, base64_decode((string) $file->filesignature, true), $public, OPENSSL_ALGO_SHA256) !== 1) {
			throw new RuntimeException('Archive must preserve and sign the original template bytes');
		}
		unlink($package_file);
		$GLOBALS['cacti_fixture_contract']['assertions']++;
	}
	$GLOBALS['cacti_fixture_contract']['complete'] = true;
	print "PASS: package archive without request fields and with unrelated request fields\n";
} finally {
	foreach ([$temporary . '/package.key', $temporary . '/package.pem', $xmlPath, $archivePath] as $path) {
		if (is_file($path)) {
			unlink($path);
		}
	}
	rmdir($temporary);
}
