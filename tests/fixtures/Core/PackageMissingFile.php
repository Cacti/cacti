<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Builds a package with package_template() from lib/package.php using a
 * disposable signing key, once with a dependent file that exists and once
 * with one that does not.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';

$temporary = sys_get_temp_dir() . '/cacti-package-missing-' . bin2hex(random_bytes(8));

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

function check(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . "\n");

		exit(1);
	}
}

$package_file = '';
$key          = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

if ($key === false || !openssl_pkey_export_to_file($key, $temporary . '/package.key')) {
	throw new RuntimeException('Cannot create disposable signing key');
}

file_put_contents($temporary . '/package.pem', openssl_pkey_get_details($key)['key']);

$present = $temporary . '/present.php';
file_put_contents($present, "<?php\n");

foreach ([$present => true, $temporary . '/missing.php' => false] as $dependent => $expected) {
	$template = '<cacti><fixture>template</fixture></cacti>';
	$info     = ['name' => 'missing-file-' . basename($temporary), 'author' => 'Disposable fixture'];
	$files    = [['file' => $dependent]];
	$debug    = '';

	$built = package_template($template, $info, $files, $debug);

	check($built === $expected, 'Packaging ' . basename($dependent) . ' returned the expected result');

	if ($expected) {
		$archive = gzdecode((string) file_get_contents($package_file));

		check(str_contains((string) $archive, '<data>' . base64_encode("<?php\n") . '</data>'), 'A present file is packaged with its content');

		unlink($package_file);
		rmdir(dirname($package_file));
	} else {
		check(str_contains($debug, 'ERROR: Dependent file not found: ' . $dependent), 'A missing file is reported');
		check(isset($files['template']['file']) && !is_file($files['template']['file']), 'The template work file is removed');

		rmdir(dirname($files['template']['file']));
	}
}

foreach (glob($temporary . '/*') as $path) {
	if (is_dir($path)) {
		array_map('unlink', glob($path . '/*'));
		rmdir($path);
	} else {
		unlink($path);
	}
}

rmdir($temporary);

print "PASS: package-missing-file\n";
