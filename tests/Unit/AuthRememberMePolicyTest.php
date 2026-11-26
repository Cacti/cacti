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

use Cacti\Auth\RememberMePolicy;

test('remember-me token is allowed for an eligible local account', function () : void {
	$user = [
		'id'                   => 42,
		'must_change_password' => '',
	];

	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, false, true, true, true))->toBeTrue();
});

test('remember-me token is denied when must_change_password is enabled', function () : void {
	$user = [
		'id'                   => 42,
		'must_change_password' => 'on',
	];

	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, false, true, true, true))->toBeFalse();
});

test('remember-me token is denied for basic auth, guest accounts, and invalid ids', function () : void {
	$normal = ['id' => 42, 'must_change_password' => ''];
	$guest  = ['id' => '3', 'must_change_password' => ''];
	$zero   = ['id' => 0, 'must_change_password' => ''];

	expect(RememberMePolicy::canIssueToken($normal, AUTH_METHOD_BASIC, 3, false, true, true, true))->toBeFalse();
	expect(RememberMePolicy::canIssueToken($guest, AUTH_METHOD_CACTI, 3, false, true, true, true))->toBeFalse();
	expect(RememberMePolicy::canIssueToken($zero, AUTH_METHOD_CACTI, 3, false, true, true, true))->toBeFalse();
});

test('remember-me token is denied when login preconditions fail', function () : void {
	$user = ['id' => 42, 'must_change_password' => ''];

	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, true, true, true, true))->toBeFalse();
	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, false, false, true, true))->toBeFalse();
	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, false, true, false, true))->toBeFalse();
	expect(RememberMePolicy::canIssueToken($user, AUTH_METHOD_CACTI, 3, false, true, true, false))->toBeFalse();
});

test('auth_login delegates remember-me issuance to RememberMePolicy', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/auth_login.php');
	expect($source)->not->toBeFalse();
	expect($source)->toContain('use Cacti\Auth\RememberMePolicy;');
	expect($source)->toContain('if (RememberMePolicy::canIssueToken(');
});
