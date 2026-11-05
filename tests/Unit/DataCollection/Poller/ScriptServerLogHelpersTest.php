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

require_once dirname(__DIR__, 4) . '/lib/script_server.php';

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

test('script_server_log_request redacts credential-bearing arguments', function () {
	global $config;

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_request(1234, 3, '/scripts/ss_example.php', 'ss_example', ['3:161:500:1:10:public:user:secret:SHA:private:AES:ctx']);

	$logged = file_get_contents($this->logfile);
	expect($logged)->toContain('3:161:500:1:10:<redacted>')
		->and($logged)->not->toContain('secret')
		->and($logged)->not->toContain('private');
});

test('request log identifiers cannot inject another log line', function () {
	global $config;

	$config[OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_DEBUG;
	script_server_log_request(1234, 4, "/scripts/bad\nname.php", "bad\rfunction", ['safe']);

	$logged = file_get_contents($this->logfile);
	expect($logged)->toContain("INC: 'bad?name.php'")
		->and($logged)->toContain("FUNC: 'bad?function'")
		->and(substr_count(trim($logged), "\n"))->toBe(0);
});

test('the pre-check follows remote poller and selective logging precedence', function () {
	expect(script_server_log_level_enabled(POLLER_VERBOSITY_DEBUG, POLLER_VERBOSITY_LOW, -1, POLLER_VERBOSITY_LOW))->toBeTrue()
		->and(script_server_log_level_enabled(POLLER_VERBOSITY_DEBUG, POLLER_VERBOSITY_DEBUG, -1, POLLER_VERBOSITY_LOW))->toBeFalse()
		->and(script_server_log_level_enabled(POLLER_VERBOSITY_DEBUG, -1, POLLER_VERBOSITY_DEBUG, POLLER_VERBOSITY_NONE))->toBeTrue()
		->and(script_server_log_level_enabled(POLLER_VERBOSITY_DEBUG, -1, -1, POLLER_VERBOSITY_DEBUG))->toBeTrue()
		->and(script_server_log_level_enabled(POLLER_VERBOSITY_DEBUG, -1, -1, POLLER_VERBOSITY_LOW))->toBeFalse();
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
