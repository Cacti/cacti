<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or any later version.                                   |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

use Composer\InstalledVersions;
use phpseclib4\Crypt\Rijndael;
use phpseclib4\Crypt\RSA;

beforeEach(function () {
	global $config, $database_default, $database_hostname, $database_port, $database_sessions, $encryption;

	$this->phpseclibGlobals = [
		'config'            => $config,
		'database_default'  => $database_default,
		'database_hostname' => $database_hostname,
		'database_port'     => $database_port,
		'database_sessions' => $database_sessions,
		'encryption'        => $encryption ?? false,
	];
});

afterEach(function () {
	global $config, $database_default, $database_hostname, $database_port, $database_sessions, $encryption;

	$config            = $this->phpseclibGlobals['config'];
	$database_default  = $this->phpseclibGlobals['database_default'];
	$database_hostname = $this->phpseclibGlobals['database_hostname'];
	$database_port     = $this->phpseclibGlobals['database_port'];
	$database_sessions = $this->phpseclibGlobals['database_sessions'];
	$encryption        = $this->phpseclibGlobals['encryption'];
});

test('Composer loads phpseclib 4 without the retired version 3 namespace', function () {
	expect(InstalledVersions::getPrettyVersion('phpseclib/phpseclib'))->toStartWith('4.')
		->and(class_exists(RSA::class))->toBeTrue()
		->and(class_exists(Rijndael::class))->toBeTrue()
		->and(class_exists('phpseclib3\\Crypt\\RSA'))->toBeFalse();
});

test('Cacti generates and persists a phpseclib 4 RSA key pair', function () {
	global $config, $database_default, $database_hostname, $database_port, $database_sessions;

	$database_hostname = 'phpseclib4-integration';
	$database_port     = 0;
	$database_default  = 'cacti';
	$connection        = new PDO('sqlite::memory:');
	$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
	$database_sessions["$database_hostname:$database_port:$database_default"] = $connection;
	unset($config[OPTIONS_CLI]['rsa_public_key']);

	rsa_check_keypair();

	$settings = $connection->query("SELECT name, value FROM settings WHERE name LIKE 'rsa_%'")
		->fetchAll(PDO::FETCH_KEY_PAIR);

	expect($settings)->toHaveKeys(['rsa_public_key', 'rsa_private_key', 'rsa_fingerprint']);

	$private = RSA::loadPrivateKey($settings['rsa_private_key']);
	$public  = RSA::loadPublicKey($settings['rsa_public_key']);

	expect($private->getPublicKey()->getFingerprint())->toBe($settings['rsa_fingerprint'])
		->and($public->getFingerprint())->toBe($settings['rsa_fingerprint']);
});

test('Cacti encrypted proxy packets round trip through phpseclib 4 keys', function () {
	global $encryption;

	$privateKey = RSA::createKey(2048);
	$publicKey  = $privateKey->getPublicKey();
	$encryption = true;
	$plaintext  = random_bytes(512);
	$packet     = encrypt($plaintext, (string) $publicKey);

	expect($packet)->not->toBe($plaintext)
		->and(rrdtool_proxy_decrypt($packet, (string) $privateKey))->toBe($plaintext);

	if (extension_loaded('openssl')) {
		expect(openssl_pkey_get_private((string) $privateKey))->not->toBeFalse()
			->and(openssl_pkey_get_public((string) $publicKey))->not->toBeFalse();
	}
});

test('Cacti accepts the legacy oversized symmetric-key packet contract', function () {
	$privateKey = RSA::createKey(4096);
	$legacyKey  = random_bytes(192);
	$plaintext  = random_bytes(256);
	$aes        = new Rijndael('cbc');
	$aes->setKey(substr($legacyKey, 0, 32));
	$aes->setIV(str_repeat("\0", 16));

	$encryptedKey = base64_encode($privateKey->getPublicKey()->encrypt($legacyKey));
	$packet       = str_pad(dechex(strlen($encryptedKey)), 3, '0', STR_PAD_LEFT)
		. $encryptedKey
		. base64_encode($aes->encrypt($plaintext));

	expect(rrdtool_proxy_decrypt($packet, (string) $privateKey))->toBe($plaintext);
});

test('Cacti fails closed for invalid and unrelated phpseclib 4 private keys', function () {
	global $encryption;

	$privateKey = RSA::createKey(2048);
	$otherKey   = RSA::createKey(2048);
	$encryption = true;
	$packet     = encrypt('protected payload', (string) $privateKey->getPublicKey());

	expect(rrdtool_proxy_decrypt($packet, (string) $otherKey))->toBeFalse()
		->and(rrdtool_proxy_decrypt($packet, 'not a private key'))->toBeFalse();
});
