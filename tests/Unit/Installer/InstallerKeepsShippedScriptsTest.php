<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The device template packages in install/templates embed their own copies of
 * shared scripts and resource files, and those copies can be older than the
 * files shipped next to them.  The installer imports the packages on install
 * and upgrade, so it must not replace the shipped files with the embedded
 * copies.  Package imports from the console or the CLI keep their behaviour.
 */

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/import.php';
require_once dirname(__DIR__, 3) . '/lib/xml.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

/**
 * Return the 2048-bit RSA key used to sign the fixture Package.
 *
 * Generation dominates the run time, so the key is made once per run.
 *
 * @return OpenSSLAsymmetricKey The signing key
 */
function keepShippedKey(): OpenSSLAsymmetricKey {
	static $key = null;

	if ($key === null) {
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
	}

	return $key;
}

/**
 * Build a signed Package the way lib/package.php does, carrying one file.
 *
 * @param string $name    The Package file name, for example scripts/x.php
 * @param string $payload The file contents embedded in the Package
 *
 * @return string The path of the .xml.gz Package
 */
function keepShippedPackage(string $name, string $payload): string {
	$key = keepShippedKey();

	openssl_pkey_export($key, $private_key);
	openssl_sign($payload, $file_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml  = "<xml>\n";
	$xml .= "   <info>\n";
	$xml .= "     <name>Keep Shipped Package</name>\n";
	$xml .= "     <author>Keep Shipped</author>\n";
	$xml .= "     <homepage>http://example.invalid</homepage>\n";
	$xml .= "     <email>keepshipped@example.invalid</email>\n";
	$xml .= "   </info>\n";
	$xml .= "   <directories>\n";
	$xml .= "       <directory>scripts</directory>\n";
	$xml .= "   </directories>\n";
	$xml .= "   <files>\n";
	$xml .= "       <file>\n";
	$xml .= "           <name>$name</name>\n";
	$xml .= '           <data>' . base64_encode($payload) . "</data>\n";
	$xml .= '           <filesignature>' . base64_encode($file_sig) . "</filesignature>\n";
	$xml .= "       </file>\n";
	$xml .= "   </files>\n";
	$xml .= "   <publickeyname>Keep Shipped</publickeyname>\n";
	$xml .= '   <publickey>' . base64_encode(openssl_pkey_get_details($key)['key']) . "</publickey>\n";

	openssl_sign($xml . "   <signature></signature>\n</xml>", $base_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '   <signature>' . base64_encode($base_sig) . "</signature>\n</xml>";

	$path = sys_get_temp_dir() . '/cacti_pkg_keep_' . bin2hex(random_bytes(4)) . '.xml.gz';
	$file = fopen("compress.zlib://$path", 'wb');

	fwrite($file, $xml, strlen($xml));
	fclose($file);

	return $path;
}

/**
 * Import a Package with the fixture key trusted, restoring the prior database
 * session afterwards.
 *
 * @param string $package             The Package to import
 * @param bool   $keep_existing_files Passed through to import_package()
 *
 * @return mixed The import_package() result
 */
function keepShippedImport(string $package, bool $keep_existing_files): mixed {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;
	$conn    = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE package_public_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, public_key TEXT)');
	$conn->prepare('INSERT INTO package_public_keys (public_key) VALUES (?)')
		->execute([openssl_pkey_get_details(keepShippedKey())['key']]);

	$database_sessions[$session] = $conn;

	try {
		return import_package($package, 1, false, false, false, false, false, [], [], $keep_existing_files);
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}
}

/**
 * Ask the installer whether an import_package() result counts as a success.
 *
 * @param mixed $result The import_package() result
 *
 * @return bool True when the installer accepts the result
 */
function keepShippedInstallerAccepts(mixed $result): bool {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();

	return (new ReflectionMethod(Installer::class, 'packageImportSucceeded'))->invoke($installer, $result);
}

beforeEach(function () {
	$this->name    = 'scripts/keep_shipped_' . bin2hex(random_bytes(4)) . '.php';
	$this->target  = CACTI_PATH_BASE . '/' . $this->name;
	$this->package = keepShippedPackage($this->name, "<?php\n// embedded copy\n");
});

afterEach(function () {
	foreach ([$this->package, $this->target] as $file) {
		if (file_exists($file)) {
			unlink($file);
		}
	}
});

test('an import that keeps existing files leaves the shipped copy in place', function () {
	file_put_contents($this->target, "<?php\n// shipped copy\n");

	$result = keepShippedImport($this->package, true);

	expect($result)->toBeArray()
		->and($result[1][$this->target])->toBe(__('kept existing'))
		->and(file_get_contents($this->target))->toBe("<?php\n// shipped copy\n")
		->and(keepShippedInstallerAccepts($result))->toBeTrue();
});

test('an import that keeps existing files still writes files the installation lacks', function () {
	$result = keepShippedImport($this->package, true);

	expect($result)->toBeArray()
		->and($result[1][$this->target])->toBe(__('written'))
		->and(file_get_contents($this->target))->toBe("<?php\n// embedded copy\n")
		->and(keepShippedInstallerAccepts($result))->toBeTrue();
});

test('a default import still replaces an existing file', function () {
	file_put_contents($this->target, "<?php\n// shipped copy\n");

	$result = keepShippedImport($this->package, false);

	expect($result)->toBeArray()
		->and($result[1][$this->target])->toBe(__('written'))
		->and(file_get_contents($this->target))->toBe("<?php\n// embedded copy\n");
});

test('the installer still fails a package whose files were not handed off', function () {
	expect(keepShippedInstallerAccepts([[], ['/tmp/file' => __('not writable')]]))->toBeFalse()
		->and(keepShippedInstallerAccepts([[], ['/tmp/a' => __('kept existing'), '/tmp/b' => __('could not open')]]))->toBeFalse();
});

test('the installer imports packages without replacing shipped files', function () {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	expect($installer)->toContain('import_package($path . $package, $this->profile, false, false, false, false, true, [], [], true);');
});

test('console and CLI imports still replace files', function () {
	foreach (['package_import.php', 'cli/import_package.php'] as $file) {
		$src = file_get_contents(CACTI_PATH_BASE . '/' . $file);

		preg_match_all('/import_package\(([^;]*)\);/', $src, $calls);

		expect($calls[1])->not->toBeEmpty();

		foreach ($calls[1] as $args) {
			expect(substr_count($args, ','))->toBeLessThan(9);
		}
	}
});

test('the shipped MikroTik scripts bind query index values instead of interpolating them', function () {
	foreach (glob(CACTI_PATH_BASE . '/scripts/ss_mikrotik_*.php') as $script) {
		$src = file_get_contents($script);

		expect(preg_match('/IN\s*\(\s*\'\$index/', $src))->toBe(0, basename($script));
		expect(preg_match('/=\s*\'\$index\'/', $src))->toBe(0, basename($script));
	}
});
