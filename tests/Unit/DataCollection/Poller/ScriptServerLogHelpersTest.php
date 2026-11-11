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

/*
 * Covers the Script Server logging helpers that assemble debug strings only
 * when debug logging is enabled, keeping the Script Server hot path cheap on a
 * normal production installation.
 */

beforeEach(function () {
	global $config;

	// Redirect cacti_log() to a temp file and serve every option it reads from
	// the CLI option cache so nothing reaches the (absent) test database.
	$this->logfile = tempnam(sys_get_temp_dir(), 'ss-log-');

	$config[OPTIONS_CLI]['path_cactilog']          = $this->logfile;
	$config[OPTIONS_CLI]['log_destination']        = 1;
	$config[OPTIONS_CLI]['selective_debug']        = '';
	$config[OPTIONS_CLI]['selective_plugin_debug'] = '';
	$config[OPTIONS_CLI]['log_verbosity']          = POLLER_VERBOSITY_NONE;
});

afterEach(function () {
	if (!empty($this->logfile) && file_exists($this->logfile)) {
		unlink($this->logfile);
	}
});

test('cacti_effective_log_level falls back to log_verbosity without a selective override', function () {
	global $config;

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_MEDIUM;
	expect(cacti_effective_log_level())->toBe(POLLER_VERBOSITY_MEDIUM);

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	expect(cacti_effective_log_level())->toBe(POLLER_VERBOSITY_DEBUG);
});

test('script_server_log_request logs only at debug verbosity', function () {
	global $config;

	// Debug disabled: the helper returns without touching the log.
	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_NONE;
	script_server_log_request(1234, 1, '/scripts/ss_example.php', 'ss_example', ['one', 'two']);
	expect(file_get_contents($this->logfile))->toBe('');

	// Debug enabled: the helper assembles and logs the request line.
	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_request(1234, 2, '/scripts/ss_example.php', 'ss_example', ['one', 'two']);

	$logged = file_get_contents($this->logfile);
	expect($logged)->toContain("PID[1234] CTR[2] INC: 'ss_example.php'")
		->and($logged)->toContain("FUNC: 'ss_example'")
		->and($logged)->toContain("PARMS: 'one', 'two'");
});

test('script_server_log_response logs only at debug verbosity', function () {
	global $config;

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_NONE;
	script_server_log_response(1234, 1, 'result-value');
	expect(file_get_contents($this->logfile))->toBe('');

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_response(1234, 2, 'result-value');

	expect(file_get_contents($this->logfile))->toContain("PID[1234] CTR[2] RESPONSE:'result-value'");
});

test('script_server_log_response accepts a null script result', function () {
	global $config;

	// a script function without a return statement yields null; debug
	// logging off must not even inspect it
	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_NONE;
	script_server_log_response(1234, 1, null);
	expect(file_get_contents($this->logfile))->toBe('');

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_response(1234, 2, null);

	expect(file_get_contents($this->logfile))->toContain("PID[1234] CTR[2] RESPONSE:''");
});

test('script_server_log_response accepts an array script result', function () {
	global $config;

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_NONE;
	script_server_log_response(1234, 1, ['in' => 1, 'out' => 2]);
	expect(file_get_contents($this->logfile))->toBe('');

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_response(1234, 2, ['in' => 1, 'out' => 2]);

	expect(file_get_contents($this->logfile))->toContain('PID[1234] CTR[2] RESPONSE:\'{"in":1,"out":2}\'');
});
