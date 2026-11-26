<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/package_key_writer.php';

test('package_key_ensure_directory creates missing directories with 0700 permissions and rejects symlinks', function () : void {
	$base = sys_get_temp_dir() . '/cacti_pki_test_' . bin2hex(random_bytes(6));
	mkdir($base, 0755, true);
	$nested  = $base . '/pki';
	$symlink = $base . '/pki_link';
	symlink($base, $symlink);

	try {
		expect(package_key_ensure_directory(''))->toBeFalse();
		expect(package_key_ensure_directory($symlink))->toBeFalse();
		expect(package_key_ensure_directory($nested))->toBeTrue();
		expect(is_dir($nested))->toBeTrue();
		expect(fileperms($nested) & 0777)->toBe(0700);
		expect(package_key_ensure_directory($nested))->toBeTrue();
	} finally {
		@unlink($symlink);
		@rmdir($nested);
		@rmdir($base);
	}
});

test('package_key_write_private writes both OpenSSLAsymmetricKey objects and PEM strings with 0600 permissions', function () : void {
	$dir = sys_get_temp_dir() . '/cacti_key_write_' . bin2hex(random_bytes(6));
	mkdir($dir, 0700, true);

	$key_obj = openssl_pkey_new([
		'digest_alg'       => 'sha256',
		'private_key_bits' => 2048,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	]);
	expect($key_obj)->not->toBeFalse();

	$pem = '';
	expect(openssl_pkey_export($key_obj, $pem))->toBeTrue();

	$from_object = $dir . '/object.key';
	$from_string = $dir . '/string.key';
	$link_target = $dir . '/target.key';
	$symlink     = $dir . '/symlink.key';
	file_put_contents($link_target, 'untouched');
	symlink($link_target, $symlink);

	try {
		// OpenSSLAsymmetricKey path (--privkey)
		expect(package_key_write_private($key_obj, $from_object))->toBeTrue();
		expect(is_file($from_object))->toBeTrue();
		expect(fileperms($from_object) & 0777)->toBe(0600);
		expect(file_get_contents($from_object))->toContain('PRIVATE KEY');

		// PEM string path (--generate)
		expect(package_key_write_private($pem, $from_string))->toBeTrue();
		expect(is_file($from_string))->toBeTrue();
		expect(fileperms($from_string) & 0777)->toBe(0600);
		expect(file_get_contents($from_string))->toContain('PRIVATE KEY');

		// Reject symlink destinations, invalid inputs, and empty paths
		expect(package_key_write_private($key_obj, $symlink))->toBeFalse();
		expect(file_get_contents($link_target))->toBe('untouched');
		expect(package_key_write_private($key_obj, ''))->toBeFalse();
		expect(package_key_write_private(12345, $dir . '/bad.key'))->toBeFalse();
		expect(package_key_write_private('not-a-private-key', $dir . '/bad.key'))->toBeFalse();
	} finally {
		@unlink($from_object);
		@unlink($from_string);
		@unlink($symlink);
		@unlink($link_target);
		@rmdir($dir);
	}
});

test('package_key_paths_match and package_key_tighten_permissions handle canonical paths and enforce 0600', function () : void {
	$dir = sys_get_temp_dir() . '/cacti_key_perm_' . bin2hex(random_bytes(6));
	mkdir($dir, 0700, true);
	$key_file   = $dir . '/package.key';
	$other_file = $dir . '/other.key';
	$symlink    = $dir . '/link.key';
	file_put_contents($key_file, 'secret');
	chmod($key_file, 0644);
	file_put_contents($other_file, 'other');
	symlink($key_file, $symlink);

	try {
		expect(package_key_paths_match($dir . '/./package.key', $key_file))->toBeTrue();
		expect(package_key_paths_match($other_file, $key_file))->toBeFalse();
		expect(package_key_paths_match('/nonexistent/a.key', '/nonexistent/a.key'))->toBeTrue();
		expect(package_key_paths_match('/nonexistent/a.key', '/nonexistent/b.key'))->toBeFalse();

		expect(package_key_tighten_permissions($key_file))->toBeTrue();
		expect(fileperms($key_file) & 0777)->toBe(0600);
		expect(package_key_tighten_permissions($symlink))->toBeFalse();
		expect(package_key_tighten_permissions(''))->toBeFalse();
		expect(package_key_tighten_permissions($dir . '/missing.key'))->toBeFalse();
	} finally {
		@unlink($symlink);
		@unlink($key_file);
		@unlink($other_file);
		@rmdir($dir);
	}
});

test('cli/genkey.php uses package_key_writer helpers instead of raw file_put_contents on private keys', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/cli/genkey.php');
	expect($source)->not->toBeFalse();
	expect($source)->toContain("require_once(CACTI_PATH_LIBRARY . '/package_key_writer.php');");
	expect($source)->toContain('package_key_ensure_directory($keypair_path)');
	expect($source)->toContain('package_key_write_private($privKey, $key_file)');
	expect($source)->not->toContain('file_put_contents("$keypair_path/package.key"');
});
