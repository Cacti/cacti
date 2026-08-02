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
 * Regression coverage for the Package signature gate on the import path.
 *
 * import_validate_signature() used to return the Package info array for both a
 * trusted and an untrusted key, recording the real verdict in $info['valid'].
 * The gate in import_read_package_data() tested the array itself, which is
 * always truthy, so a Package self-signed with its author's own key was read
 * and its scripts/ files written to disk.  The helper now returns a strict
 * bool and the gate reads that.
 *
 * The 'Automatically Trust Signer' control was the other half of the hole:
 * trust_signer=on inserted the Package's own key into package_public_keys
 * before the gate ran, so even a corrected gate would have passed.  The
 * control is gone; a key becomes trusted only through the accept action.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/import.php';

/*
 * Build a Package the way lib/package.php does, but signed with a throwaway
 * key rather than the Cacti key.  Returns the .xml.gz path; $public_key
 * receives the PEM that the Package carries.
 */
function selfSignedPackage(string &$public_key): string {
	$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
	openssl_pkey_export($key, $private_key);
	$public_key = openssl_pkey_get_details($key)['key'];

	$payload   = "<?php\n// planted by the package\n";
	$file_sig  = '';
	$base_sig  = '';

	$xml  = "<xml>\n";
	$xml .= "   <info>\n";
	$xml .= "     <name>Untrusted Package</name>\n";
	$xml .= "     <author>Mallory</author>\n";
	$xml .= "     <homepage>http://example.invalid</homepage>\n";
	$xml .= "     <email>mallory@example.invalid</email>\n";
	$xml .= "   </info>\n";
	$xml .= "   <directories>\n";
	$xml .= "       <directory>scripts</directory>\n";
	$xml .= "   </directories>\n";
	$xml .= "   <files>\n";
	$xml .= "       <file>\n";
	$xml .= "           <name>scripts/planted.php</name>\n";

	openssl_sign($payload, $file_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '           <data>' . base64_encode($payload) . "</data>\n";
	$xml .= '           <filesignature>' . base64_encode($file_sig) . "</filesignature>\n";
	$xml .= "       </file>\n";
	$xml .= "   </files>\n";
	$xml .= "   <publickeyname>Mallory</publickeyname>\n";
	$xml .= '   <publickey>' . base64_encode($public_key) . "</publickey>\n";

	openssl_sign($xml . "   <signature></signature>\n</xml>", $base_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '   <signature>' . base64_encode($base_sig) . "</signature>\n</xml>";

	$path = sys_get_temp_dir() . '/cacti_pkg_trust_' . bin2hex(random_bytes(4)) . '.xml.gz';
	$f    = fopen("compress.zlib://$path", 'wb');

	fwrite($f, $xml, strlen($xml));
	fclose($f);

	return $path;
}

function importSourceFile(string $name): string {
	$src = file_get_contents(dirname(__DIR__, 2) . "/$name");
	expect($src)->not->toBeFalse("Failed to read $name");

	return $src;
}

beforeEach(function () {
	$this->package_key = '';
	$this->package     = selfSignedPackage($this->package_key);
});

afterEach(function () {
	if (file_exists($this->package)) {
		unlink($this->package);
	}
});

// --- the gate ---

test('import_validate_signature returns a strict bool', function () {
	// The array return was the defect: any populated result read as a pass.
	expect((string) (new ReflectionFunction('import_validate_signature'))->getReturnType())->toBe('bool');
});

test('a package signed with its own key is not trusted', function () {
	expect(import_validate_signature($this->package))->toBeFalse();
});

test('a real import of a self-signed package is refused', function () {
	$public_key = '';

	expect(import_read_package_data($this->package, $public_key, false))->toBeFalse();
});

test('a preview of an untrusted package is still readable', function () {
	// A preview writes neither files nor rows, so it stays available to let an
	// operator inspect a Package before deciding to trust its signer.
	$public_key = '';
	$data       = import_read_package_data($this->package, $public_key, true);

	expect($data)->toBeArray();
	expect($data['files']['file']['name'])->toBe('scripts/planted.php');
});

test('the gate no longer short circuits before it reads the verdict', function () {
	expect(importSourceFile('lib/import.php'))
		->not->toContain('!import_validate_signature($xmlfile) && !$preview');
});

// --- accepted keys, and why auto-accepting them was the second hole ---

test('a key in package_public_keys makes the same package importable', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;
	$conn    = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE package_public_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, public_key TEXT)');

	$insert = $conn->prepare('INSERT INTO package_public_keys (public_key) VALUES (?)');
	$insert->execute([$this->package_key]);

	$database_sessions[$session] = $conn;

	try {
		$public_key = '';

		expect(import_validate_signature($this->package))->toBeTrue();
		expect(import_read_package_data($this->package, $public_key, false))->toBeArray();
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}
});

test('trust_signer no longer reaches the import', function () {
	expect(importSourceFile('package_import.php'))->not->toContain('trust_signer');
	expect(importSourceFile('include/global_settings.php'))->not->toContain('trust_signer');
});

test('a key is force trusted only by the accept action', function () {
	$src    = importSourceFile('package_import.php');
	$accept = strpos($src, 'function package_accept_key(');

	expect($accept)->not->toBeFalse();

	$offset = 0;
	$found  = 0;

	while (($pos = strpos($src, 'import_validate_public_key($xmlfile, true)', $offset)) !== false) {
		expect($pos)->toBeGreaterThan($accept);

		$found++;
		$offset = $pos + 1;
	}

	expect($found)->toBeGreaterThan(0);
});
