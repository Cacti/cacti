<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$GLOBALS['rrd_crypto_options'] = array();

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return $GLOBALS['rrd_crypto_options'][$name] ?? '';
	}
}

require_once dirname(__DIR__, 3) . '/lib/rrd.php';

beforeAll(function () {
	$private = phpseclib3\Crypt\RSA::createKey(2048);
	$GLOBALS['rrd_crypto_private'] = $private->toString('PKCS8');
	$GLOBALS['rrd_crypto_public']  = $private->getPublicKey()->toString('PKCS8');
});

beforeEach(function () {
	global $encryption;

	$encryption = true;
	$GLOBALS['rrd_crypto_options']['rsa_private_key'] = $GLOBALS['rrd_crypto_private'];
});

test('RRDproxy payloads round trip through phpseclib 3', function () {
	$plaintext = "update metric.rrd N:1\n";
	$packet = encrypt($plaintext, $GLOBALS['rrd_crypto_public']);

	expect($packet)->toBeString()->not->toBeEmpty()
		->and(decrypt($packet))->toBe($plaintext);
});

test('decrypt accepts the oversized session keys emitted by phpseclib 2 clients', function () {
	$session_key = random_bytes(192);
	$aes = new phpseclib3\Crypt\Rijndael('cbc');
	$aes->setKey(substr($session_key, 0, 32));
	$aes->setIV(str_repeat("\0", 16));

	$rsa = phpseclib3\Crypt\PublicKeyLoader::loadPublicKey($GLOBALS['rrd_crypto_public'])
		->withPadding(phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
		->withHash('sha1')
		->withMGFHash('sha1');

	$encrypted_key = base64_encode($rsa->encrypt($session_key));
	$packet = str_pad(dechex(strlen($encrypted_key)), 3, '0', STR_PAD_LEFT) .
		$encrypted_key . base64_encode($aes->encrypt('legacy packet'));

	expect(decrypt($packet))->toBe('legacy packet');
});

test('malformed RRDproxy packets fail closed', function ($packet) {
	expect(decrypt($packet))->toBeFalse();
})->with(array('', 'xyzpayload', 'fffshort', '001!', '010not-base64!'));

test('RRDproxy no longer references or ships phpseclib 2', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/rrd.php');

	expect($source)->toContain('phpseclib3\\Crypt')
		->and($source)->not->toContain('phpseclib\\phpseclib\\phpseclib')
		->and(is_dir(dirname(__DIR__, 3) . '/include/vendor/phpseclib/Crypt'))->toBeFalse();
});
