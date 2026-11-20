<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('force_session_data does nothing outside a web request', function () {
	/*
	 * tail_file() calls force_session_data(), and the installer log is tailed at
	 * the complete step. On the command line the session settings do not exist,
	 * so starting a session was a fatal TypeError in session_start(null).
	 */
	global $config;

	$saved = isset($config['is_web']) ? $config['is_web'] : null;
	$config['is_web'] = false;

	try {
		expect(force_session_data())->toBeFalse()
			->and(session_status())->toBe(PHP_SESSION_NONE);
	} finally {
		$config['is_web'] = $saved;
	}
});
