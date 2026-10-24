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
 * Regression coverage for the Package signing key floor (#8460).
 *
 * 1.2.x refuses a Package whose key is below 2048 bits before it verifies the
 * signature (#7194, GHSA-8w9r-xmpr-5q3v).  develop verified against a key of
 * any size, trusted the legacy 512-bit Cacti key, and stored an accepted key
 * of any size in package_public_keys.  Every verify and every trust decision
 * now goes through import_public_key_meets_minimum().
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';
require_once dirname(__DIR__, 4) . '/lib/xml.php';

function keyFloorRsaKey(int $bits): OpenSSLAsymmetricKey {
	static $keys = [];

	// RSA generation dominates the run time, so each size is made once.
	if (!isset($keys[$bits])) {
		$keys[$bits] = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
	}

	return $keys[$bits];
}

function keyFloorPublicPem(OpenSSLAsymmetricKey $key): string {
	return openssl_pkey_get_details($key)['key'];
}

/*
 * Build a Package the way lib/package.php does, carrying one scripts/ file,
 * and signed with $key.  Returns the .xml.gz path.
 */
function keyFloorPackage(OpenSSLAsymmetricKey $key, string $script): string {
	openssl_pkey_export($key, $private_key);

	$payload  = "<?php\n// key floor fixture\n";
	$file_sig = '';
	$base_sig = '';

	$xml  = "<xml>\n";
	$xml .= "   <info>\n";
	$xml .= "     <name>Key Floor Package</name>\n";
	$xml .= "     <author>Key Floor</author>\n";
	$xml .= "     <homepage>http://example.invalid</homepage>\n";
	$xml .= "     <email>keyfloor@example.invalid</email>\n";
	$xml .= "   </info>\n";
	$xml .= "   <directories>\n";
	$xml .= "       <directory>scripts</directory>\n";
	$xml .= "   </directories>\n";
	$xml .= "   <files>\n";
	$xml .= "       <file>\n";
	$xml .= "           <name>$script</name>\n";

	openssl_sign($payload, $file_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '           <data>' . base64_encode($payload) . "</data>\n";
	$xml .= '           <filesignature>' . base64_encode($file_sig) . "</filesignature>\n";
	$xml .= "       </file>\n";
	$xml .= "   </files>\n";
	$xml .= "   <publickeyname>Key Floor</publickeyname>\n";
	$xml .= '   <publickey>' . base64_encode(keyFloorPublicPem($key)) . "</publickey>\n";

	openssl_sign($xml . "   <signature></signature>\n</xml>", $base_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '   <signature>' . base64_encode($base_sig) . "</signature>\n</xml>";

	$path = sys_get_temp_dir() . '/cacti_pkg_floor_' . bin2hex(random_bytes(4)) . '.xml.gz';
	$f    = fopen("compress.zlib://$path", 'wb');

	fwrite($f, $xml, strlen($xml));
	fclose($f);

	return $path;
}

function keyFloorSource(string $name): string {
	$src = file_get_contents(dirname(__DIR__, 4) . "/$name");
	expect($src)->not->toBeFalse("Failed to read $name");

	return $src;
}

/*
 * Run $body with package_public_keys holding $trusted, restoring the prior
 * database session afterwards.
 */
function keyFloorWithTrustedKeys(array $trusted, callable $body): void {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;
	$conn    = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE package_public_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, public_key TEXT)');

	$insert = $conn->prepare('INSERT INTO package_public_keys (public_key) VALUES (?)');

	foreach ($trusted as $key) {
		$insert->execute([$key]);
	}

	$database_sessions[$session] = $conn;

	try {
		$body();
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}
}

beforeEach(function () {
	$this->script = 'scripts/key_floor_' . bin2hex(random_bytes(4)) . '.php';
	$this->target = CACTI_PATH_BASE . '/' . $this->script;
	$this->files  = [];
});

afterEach(function () {
	foreach ($this->files as $file) {
		if (file_exists($file)) {
			unlink($file);
		}
	}

	if (file_exists($this->target)) {
		unlink($this->target);
	}
});

// --- the built in keys ---

test('the legacy 512-bit Cacti key is not trusted', function () {
	expect(is_cacti_public_key(get_public_key()))->toBeFalse();
	expect(is_cacti_public_key(get_public_key_sha1()))->toBeFalse();
});

test('the 2048-bit Cacti key is trusted with or without a trailing newline', function () {
	expect(is_cacti_public_key(get_public_key_sha256()))->toBeTrue();
	expect(is_cacti_public_key(get_public_key_sha256() . "\n"))->toBeTrue();
});

test('the legacy Cacti key fails the floor and the current one passes', function () {
	expect(import_public_key_meets_minimum(get_public_key()))->toBeFalse();
	expect(import_public_key_meets_minimum(get_public_key_sha256()))->toBeTrue();
});

// --- the helper ---

test('the floor rejects RSA keys below 2048 bits', function (int $bits) {
	expect(import_public_key_meets_minimum(keyFloorPublicPem(keyFloorRsaKey($bits))))->toBeFalse();
})->with([512, 1024]);

test('the floor accepts RSA keys of 2048 bits and more', function (int $bits) {
	expect(import_public_key_meets_minimum(keyFloorPublicPem(keyFloorRsaKey($bits))))->toBeTrue();
})->with([2048, 4096]);

test('the floor rejects a key that is not RSA', function () {
	$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

	expect(import_public_key_meets_minimum(keyFloorPublicPem($key)))->toBeFalse();
});

test('the floor rejects input that is not a public key', function (string $input) {
	expect(import_public_key_meets_minimum($input))->toBeFalse();
})->with([
	'empty'   => '',
	'garbage' => 'not a key',
	'header'  => "-----BEGIN PUBLIC KEY-----\nAAAA\n-----END PUBLIC KEY-----",
]);

test('the floor is one constant shared with 1.2.x', function () {
	expect(IMPORT_PACKAGE_KEY_MIN_BITS)->toBe(2048);
});

// --- the import path ---

test('a package signed with a trusted 1024-bit key is refused and writes no files', function () {
	$key           = keyFloorRsaKey(1024);
	$package       = keyFloorPackage($key, $this->script);
	$this->files[] = $package;

	keyFloorWithTrustedKeys([keyFloorPublicPem($key)], function () use ($package) {
		$public_key = '';

		expect(import_validate_signature($package))->toBeFalse();
		expect(import_read_package_data($package, $public_key, false))->toBeFalse();
		expect(import_package($package, 1, false, false, false, false, false))->toBeFalse();
	});

	expect(file_exists($this->target))->toBeFalse();
});

test('a preview of a package signed with a 1024-bit key is refused', function () {
	// A preview skips the trust gate, so the floor must hold on its own.
	$package       = keyFloorPackage(keyFloorRsaKey(1024), $this->script);
	$this->files[] = $package;
	$public_key    = '';

	expect(import_read_package_data($package, $public_key, true))->toBeFalse();
	expect(import_package($package, 1, false, false, true, false, false))->toBeFalse();
	expect(file_exists($this->target))->toBeFalse();
});

test('a package signed with a trusted 2048-bit key still imports', function () {
	$key           = keyFloorRsaKey(2048);
	$package       = keyFloorPackage($key, $this->script);
	$this->files[] = $package;

	keyFloorWithTrustedKeys([keyFloorPublicPem($key)], function () use ($package) {
		expect(import_validate_signature($package))->toBeTrue();
		expect(import_package($package, 1, false, false, false, false, false))->toBeArray();
	});

	expect(file_exists($this->target))->toBeTrue();
});

test('the shipped device packages still verify', function () {
	$packages = glob(dirname(__DIR__, 4) . '/install/templates/*.xml.gz');

	expect($packages)->not->toBeEmpty();

	foreach ($packages as $package) {
		expect(import_package($package, 1, false, false, false, true, false))
			->toBeArray(basename($package) . ' no longer verifies');
	}
});

// --- every verify and every stored key goes through the floor ---

test('import_validate_public_key checks the floor before it trusts or stores a key', function () {
	$src   = keyFloorSource('package_import.php');
	$start = strpos($src, 'function import_validate_public_key(');

	expect($start)->not->toBeFalse();

	$end    = strpos($src, "\nfunction ", $start + 1);
	$body   = substr($src, $start, $end - $start);
	$floor  = strpos($body, 'import_public_key_meets_minimum($info[\'pubkey\'])');
	$lookup = strpos($body, 'FROM package_public_keys');
	$legacy = strpos($body, '$public_key1 == $info[\'pubkey\']');
	$insert = strpos($body, 'INSERT IGNORE INTO package_public_keys');

	// The legacy key comparison is only safe because the floor runs first.
	expect($floor)->not->toBeFalse();
	expect($floor)->toBeLessThan($lookup);
	expect($floor)->toBeLessThan($legacy);
	expect($floor)->toBeLessThan($insert);
});

test('every openssl_verify of a package is guarded by the floor', function (string $file) {
	$src     = keyFloorSource($file);
	$verify  = preg_match_all('/openssl_verify\(/', $src);
	$guarded = preg_match_all('/import_public_key_meets_minimum\(\$public_key\)/', $src);

	expect($verify)->toBeGreaterThan(0);
	expect($guarded)->toBeGreaterThanOrEqual($verify);
})->with(['lib/import.php', 'package_import.php']);
