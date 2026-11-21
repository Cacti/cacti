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

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

/*
 * An unknown username must cost one password verification, like a known one,
 * and that verification must use the decoy so its algorithm and cost track
 * real hashes. An empty password skips verification on both paths, so neither
 * path can be told apart by timing. A successful login replaces a stored hash
 * that is not current, and leaves a current one alone.
 */

/**
 * Runs one LocalAuthDecoyProbe scenario in a child process.
 *
 * @param string $scenario known, unknown, unknown-empty, legacy or current.
 *
 * @return array The probe verdict.
 */
function local_auth_decoy_probe(string $scenario) : array {
	return cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/Core/LocalAuthDecoyProbe.php', [$scenario]);
}

test('a wrong password for a known user verifies once against the stored hash', function () {
	$r = local_auth_decoy_probe('known');

	expect($r['success'])->toBeFalse();
	expect($r['verified'])->toBe([$r['before']]);
	expect($r['updates'])->toBe(0);
});

test('an unknown user verifies once against the decoy', function () {
	$r = local_auth_decoy_probe('unknown');

	expect($r['success'])->toBeFalse();
	expect($r['error'])->toBe('Access Denied!  Login Failed.');
	expect($r['verified'])->toBe([$r['decoy']]);
	expect(password_get_info($r['decoy'])['algo'])->toBe(password_get_info(local_auth_decoy_probe('current')['stored'])['algo']);
});

test('an unknown user with an empty password skips the decoy verification', function () {
	$r = local_auth_decoy_probe('unknown-empty');

	expect($r['success'])->toBeFalse();
	expect($r['verified'])->toBe([]);
});

test('a correct login replaces a legacy md5 digest with a current hash', function () {
	$r = local_auth_decoy_probe('legacy');

	expect($r['success'])->toBeTrue();
	expect($r['verified'])->toBe([$r['before']]);
	expect($r['updates'])->toBe(1);
	expect($r['stored'])->not->toBe($r['before']);
	expect(password_get_info($r['stored'])['algo'])->toBe(password_get_info($r['decoy'])['algo']);
	expect(password_verify('stored secret', $r['stored']))->toBeTrue();
});

test('a correct login keeps a current hash', function () {
	$r = local_auth_decoy_probe('current');

	expect($r['success'])->toBeTrue();
	expect($r['updates'])->toBe(0);
	expect($r['stored'])->toBe($r['before']);
});
