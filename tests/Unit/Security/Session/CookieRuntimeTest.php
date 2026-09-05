<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('remember-me cookie runtime records session intent and logout clears browser state', function () : void {
	global $config;

	$old_config  = $config;
	$old_cookie  = $_COOKIE;
	$old_session = $_SESSION;

	try {
		$config[COOKIE_OPTIONS]     = [COOKIE_OPTIONS_DOMAIN => 'example.test'];
		$config[CACTI_SESSION_NAME] = 'Cacti';
		$otp_cookie                 = (string) session_name() . '_otp';
		$_SESSION                   = [];
		$_COOKIE                    = [
			'Cacti'           => 'session-id',
			$otp_cookie       => 'otp-token',
			'cacti_remembers' => '42,-1,secret',
		];

		cacti_cookie_set('arbitrary', 'value', time() + 60);
		cacti_cookie_session_set('42', -1, 'secret');

		expect($_SESSION['cacti_remembers'])->toBeTrue();

		cacti_cookie_session_logout();
		cacti_cookie_logout();

		expect($_COOKIE)->not->toHaveKey('Cacti')
			->not->toHaveKey($otp_cookie)
			->not->toHaveKey('cacti_remembers');
	} finally {
		$config   = $old_config;
		$_COOKIE  = $old_cookie;
		$_SESSION = $old_session;
	}
});
