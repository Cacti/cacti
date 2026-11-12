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

require_once __DIR__ . '/../../../../lib/functions.php';
require_once __DIR__ . '/../../../../lib/auth.php';

/*
 * compat_password_hash(), compat_password_verify() and
 * compat_password_needs_rehash() are the choke points every local password
 * path uses: login, password change and reset, user administration and
 * copy_user.php.  New hashes use bcrypt at cost 12, every format
 * Cacti ever stored still verifies, and anything that is not current is
 * reported so a successful login replaces it.
 */

// md5('legacy secret'), as a very old release stored it
const COMPAT_PASSWORD_LEGACY_MD5 = 'c5af7d393bbc916cec4e8a7bd4d82050';

test('the default algorithm is bcrypt at the pinned cost 12', function () {
	expect(cacti_password_algorithm())->toBe([PASSWORD_BCRYPT, ['cost' => 12]]);
	expect(password_get_info(compat_password_hash('cost check', PASSWORD_DEFAULT))['options']['cost'])->toBe(12);
});

test('a new default hash uses the current algorithm, verifies and is current', function () {
	[$algo, $options] = cacti_password_algorithm();
	$hash             = compat_password_hash('modern secret', PASSWORD_DEFAULT);

	expect(password_get_info($hash)['algo'])->toBe($algo);
	expect(password_get_info($hash)['options'])->toBe($options);
	expect(compat_password_verify('modern secret', $hash))->toBeTrue();
	expect(compat_password_verify('other secret', $hash))->toBeFalse();
	expect(compat_password_verify('', $hash))->toBeFalse();
	expect(compat_password_needs_rehash($hash, PASSWORD_DEFAULT))->toBeFalse();
});

test('explicit options keep the requested algorithm and cost', function () {
	$hash = compat_password_hash('routing secret', PASSWORD_BCRYPT, ['cost' => 4]);

	expect(password_get_info($hash)['options']['cost'])->toBe(4);
	expect(compat_password_verify('routing secret', $hash))->toBeTrue();
	expect(compat_password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 4]))->toBeFalse();
	expect(compat_password_needs_rehash($hash, PASSWORD_DEFAULT))->toBeTrue();
});

test('bcrypt hashes below cost 12 verify and are upgraded, cost 12 is current', function () {
	foreach ([10 => true, 11 => true, 12 => false] as $cost => $upgrade) {
		$old = password_hash('stored secret', PASSWORD_BCRYPT, ['cost' => $cost]);

		expect(compat_password_verify('stored secret', $old))->toBeTrue();
		expect(compat_password_verify('Stored secret', $old))->toBeFalse();
		expect(compat_password_needs_rehash($old, PASSWORD_DEFAULT))->toBe($upgrade);
	}
});

test('an Argon2id hash still verifies and is reported for rehash to bcrypt', function () {
	if (!defined('PASSWORD_ARGON2ID')) {
		$this->markTestSkipped('this PHP build has no Argon2id');
	}

	$hash = password_hash('argon secret', PASSWORD_ARGON2ID);

	expect(compat_password_verify('argon secret', $hash))->toBeTrue();
	expect(compat_password_verify('other secret', $hash))->toBeFalse();
	expect(compat_password_needs_rehash($hash, PASSWORD_DEFAULT))->toBeTrue();
});

test('legacy unsalted md5 and sha256 digests verify and are upgraded', function () {
	$sha256 = hash('sha256', 'legacy secret');

	expect(compat_password_verify('legacy secret', COMPAT_PASSWORD_LEGACY_MD5))->toBeTrue();
	expect(compat_password_verify('legacy secret', $sha256))->toBeTrue();
	expect(compat_password_verify('wrong secret', COMPAT_PASSWORD_LEGACY_MD5))->toBeFalse();
	expect(compat_password_verify('wrong secret', $sha256))->toBeFalse();
	expect(compat_password_needs_rehash(COMPAT_PASSWORD_LEGACY_MD5, PASSWORD_DEFAULT))->toBeTrue();
	expect(compat_password_needs_rehash($sha256, PASSWORD_DEFAULT))->toBeTrue();

	$upgraded = compat_password_hash('legacy secret', PASSWORD_DEFAULT);

	expect(compat_password_verify('legacy secret', $upgraded))->toBeTrue();
	expect(compat_password_needs_rehash($upgraded, PASSWORD_DEFAULT))->toBeFalse();
});

test('a legacy digest matches only its exact stored form', function () {
	// The digests are lower case hex; an upper case copy or a value that is
	// not a digest of the password never matches, as before.
	expect(compat_password_verify('legacy secret', strtoupper(COMPAT_PASSWORD_LEGACY_MD5)))->toBeFalse();
	expect(compat_password_verify('legacy secret', hash('sha256', 'other secret')))->toBeFalse();
	expect(compat_password_verify('legacy secret', COMPAT_PASSWORD_LEGACY_MD5 . ' '))->toBeFalse();
	expect(compat_password_verify('legacy secret', substr(COMPAT_PASSWORD_LEGACY_MD5, 0, 31)))->toBeFalse();
	expect(compat_password_verify(COMPAT_PASSWORD_LEGACY_MD5, COMPAT_PASSWORD_LEGACY_MD5))->toBeFalse();
});

test('an empty or unrecognised stored hash never verifies', function () {
	expect(compat_password_verify('anything', ''))->toBeFalse();
	expect(compat_password_verify('', ''))->toBeFalse();
	expect(compat_password_verify('anything', 'not a hash'))->toBeFalse();
	expect(compat_password_verify('anything', '*'))->toBeFalse();
});

test('long passwords hash and verify, and bcrypt reads only the first 72 bytes', function () {
	$long = str_repeat('a', 5000);
	$hash = compat_password_hash($long, PASSWORD_DEFAULT);

	expect(compat_password_verify($long, $hash))->toBeTrue();
	expect(compat_password_needs_rehash($hash, PASSWORD_DEFAULT))->toBeFalse();

	// bcrypt stops at 72 bytes, so a change after that point still verifies
	expect(compat_password_verify(str_repeat('a', 4999) . 'b', $hash))->toBeTrue();
	expect(compat_password_verify(str_repeat('a', 71) . 'b', $hash))->toBeFalse();
});

test('a bcrypt hash of a password over 72 bytes from an earlier release still verifies', function () {
	$long = str_repeat('a', 80);
	$old  = password_hash($long, PASSWORD_BCRYPT, ['cost' => 4]);

	expect(compat_password_verify($long, $old))->toBeTrue();
	expect(compat_password_verify(str_repeat('b', 80), $old))->toBeFalse();
	expect(compat_password_needs_rehash($old, PASSWORD_DEFAULT))->toBeTrue();
});

test('the login decoy has the shape and cost of a real hash and matches nothing', function () {
	[$algo, $options] = cacti_password_algorithm();
	$decoy            = cacti_password_decoy_hash();
	$real             = password_hash('correct horse', $algo, $options);

	expect(password_get_info($decoy)['algo'])->toBe(password_get_info($real)['algo']);
	expect(password_get_info($decoy)['options'])->toBe(['cost' => 12]);
	expect(strlen($decoy))->toBe(strlen($real));
	expect(password_needs_rehash($decoy, $algo, $options))->toBeFalse();
	expect(compat_password_verify('correct horse', $decoy))->toBeFalse();
	expect(compat_password_verify('', $decoy))->toBeFalse();
});

test('the login decoy is built once per process and is not a fixed literal', function () {
	expect(cacti_password_decoy_hash())->toBe(cacti_password_decoy_hash());

	[$algo, $options] = cacti_password_algorithm();

	expect(cacti_password_decoy_for($algo, $options))->not->toBe(cacti_password_decoy_for($algo, $options));
});

test('the local password matrix probe passes in its own process', function () {
	require_once __DIR__ . '/../../../Helpers/IsolatedProbe.php';

	expect(cacti_test_isolated_probe(__DIR__ . '/../../../fixtures/Core/CompatPasswordMatrix.php'))->toBe(['passed' => 22]);
});
