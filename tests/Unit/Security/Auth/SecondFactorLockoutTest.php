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

require_once dirname(__DIR__,3) . '/Helpers/IsolatedProbe.php';
test('second-factor checks use the actual account realm and lockout state', function ($mode,$authenticated,$checks,$locked,$attempts) {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthSecondFactorProbe.php',[$mode]);
	expect($r['authenticated'])->toBe($authenticated)->and($r['token_checks'])->toBe($checks);
	expect($r['user']['locked'])->toBe($locked)->and($r['user']['failed_attempts'])->toBe($attempts);
})->with([
	['valid', true, 1, '', 1], ['invalid', false, 1, 'on', 2], ['locked', false, 0, 'on', 1], ['expired', true, 1, '', 0],
	['external-valid', true, 1, '', 1], ['external-invalid', false, 1, 'on', 2], ['external-locked', false, 0, 'on', 1], ['external-expired', true, 1, '', 0],
]);
test('a token submitted while locked reports the lockout and leaves the unlock timer alone', function ($mode) {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthSecondFactorProbe.php',[$mode]);
	expect($r['message'])->toBe('Your account has been locked.  Please contact your Administrator.');
	expect($r['user']['failed_attempts'])->toBe(1)->and($r['user']['lastfail'])->toBe($r['user']['lastfail_before']);
})->with(['locked', 'external-locked']);
test('a wrong token reports a failed verification', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthSecondFactorProbe.php',['invalid']);
	expect($r['message'])->toBe('Failed to verify token');
});
test('a missing second-factor account cannot authenticate', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthSecondFactorProbe.php',['missing']);
	expect($r['authenticated'])->toBeFalse()->and($r['user'])->toBeFalse()->and($r['token_checks'])->toBe(0);
});

test('second-factor submissions reject non-POST and invalid-token requests before changing account state', function ($mode, $status) {
	$r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/AuthSecondFactorProbe.php', [$mode]);
	expect($r['status'])->toBe($status)->and($r['authenticated'])->toBeFalse()->and($r['token_checks'])->toBe(0);
	expect($r['user']['failed_attempts'])->toBe(1)->and($r['user']['locked'])->toBe('');
})->with([['get', 405], ['tokenless', 403]]);
