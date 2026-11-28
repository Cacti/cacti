<?php

use Cacti\Auth\FreshAuthentication;

beforeEach(function () {
	unset($_SESSION['sess_fresh_auth'], $_SESSION['sess_mfa_challenge']);
	$_SESSION[SESS_USER_ID] = 21;
});

test('remembered, expired and fresh sessions retain distinct permissions', function () {
	expect(FreshAuthentication::isFresh(21))->toBeFalse();
	FreshAuthentication::record(21, time() - FreshAuthentication::WINDOW);
	expect(FreshAuthentication::isFresh(21))->toBeFalse();
	FreshAuthentication::record(21, time());
	expect(FreshAuthentication::isFresh(21))->toBeTrue()->and(FreshAuthentication::isFresh(22))->toBeFalse();
});

test('provider challenge requires recent evidence for the original account and realm', function (int $user, int $realm, int $offset, bool $allowed) {
	$challenge = FreshAuthentication::begin(21, 1001);
	$time      = $_SESSION['sess_mfa_challenge']['start'] + $offset;
	expect(FreshAuthentication::complete($challenge, $user, $realm, $time))->toBe($allowed)
		->and(FreshAuthentication::isFresh(21))->toBe($allowed)
		->and(FreshAuthentication::complete($challenge, $user, $realm, $time))->toBeFalse();
})->with([[21, 1001, 0, true], [22, 1001, 0, false], [21, 1002, 0, false], [21, 1001, -1, false], [21, 1001, 300, false]]);

test('cancelled and superseded provider challenges cannot confer freshness', function () {
	$first = FreshAuthentication::begin(21, 1001);
	FreshAuthentication::begin(21, 1001);
	expect(FreshAuthentication::complete($first, 21, 1001, time()))->toBeFalse();
	$next = FreshAuthentication::begin(21, 1001);
	unset($_SESSION['sess_mfa_challenge']);
	expect(FreshAuthentication::complete($next, 21, 1001, time()))->toBeFalse()->and(FreshAuthentication::isFresh(21))->toBeFalse();
});
