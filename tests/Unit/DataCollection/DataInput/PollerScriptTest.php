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
 * Process access shared by the scripts/ss_* ports of the Perl and Python
 * data input scripts, and the cacti_http() fetch the HTTP ports use.
 */

require_once dirname(__DIR__, 4) . '/lib/poller_script.php';
require_once dirname(__DIR__, 3) . '/Helpers/DataInputHttpServer.php';

test('find resolves a bare program name to an absolute path', function () {
	$path = poller_script_find('sh');

	expect($path)->toBeString()->toStartWith('/')->toEndWith('/sh');
});

test('find refuses paths and names that are not bare program names', function () {
	expect(poller_script_find('/bin/sh'))->toBeNull()
		->and(poller_script_find('../bin/sh'))->toBeNull()
		->and(poller_script_find(''))->toBeNull()
		->and(poller_script_find('-sh'))->toBeNull();
});

test('find only searches the directories it is given', function () {
	expect(poller_script_find('sh', [sys_get_temp_dir()]))->toBeNull();
});

test('exec returns null for a program that is not installed', function () {
	expect(poller_script_exec('cacti-no-such-program'))->toBeNull();
});

test('exec collects stdout, stderr and the child pid', function () {
	$output = poller_script_exec('sh', ['-c', 'printf out; printf err >&2']);

	expect($output)->not->toBeNull()
		->and($output['stdout'])->toBe('out')
		->and($output['stderr'])->toBe('err')
		->and($output['pid'])->toBeGreaterThan(0)
		->and($output['timed_out'])->toBeFalse();
});

test('exec gives the child the C locale and the fixed search path', function () {
	$output = poller_script_exec('sh', ['-c', 'printf "%s|%s" "$LC_ALL" "$PATH"']);

	expect($output['stdout'])->toBe('C|' . implode(':', poller_script_dirs()));
});

test('exec kills a child that outlives the timeout', function () {
	$start  = hrtime(true);
	$output = poller_script_exec('sleep', ['5'], 1);

	expect($output['timed_out'])->toBeTrue()
		->and((hrtime(true) - $start) / 1e9)->toBeLessThan(4);
});

test('exec reads output larger than a pipe buffer', function () {
	$output = poller_script_exec('sh', ['-c', 'i=0; while [ $i -lt 20000 ]; do echo 0123456789; i=$((i+1)); done']);

	expect(strlen($output['stdout']))->toBe(220000);
});

test('cacti_http reaches a device on a private address and refuses non-2xx responses', function () {
	$server = data_input_http_server_start();

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server could not start');
	}

	try {
		$base = 'http://' . $server['address']; // NOSONAR local plain-HTTP fixture server, as the modems serve HTTP only

		expect(cacti_http($base . '/plain', 10))->toBe("line one\nline two\n")
			->and(cacti_http($base . '/missing', 10))->toBeFalse()
			->and(cacti_http($base . '/redirect', 10))->toBeFalse();
	} finally {
		data_input_http_server_stop($server);
	}
});
