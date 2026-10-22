<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Process and HTTP access shared by the scripts/ss_* ports of the Perl and
 * Python data input scripts.
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

test('http get refuses anything but an http URL', function () {
	expect(poller_script_http_get('file:///etc/passwd'))->toBeNull()
		->and(poller_script_http_get('https://127.0.0.1/'))->toBeNull();
});

test('http get returns null when nothing listens', function () {
	$probe = stream_socket_server('tcp://127.0.0.1:0');
	$addr  = stream_socket_get_name($probe, false);

	fclose($probe);

	expect(poller_script_http_get('http://' . $addr . '/', 2))->toBeNull(); // NOSONAR loopback port with no listener; the modems serve HTTP only
});

test('http get returns the body of a 2xx response and refuses other statuses', function () {
	$server = data_input_http_server_start();

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server could not start');
	}

	try {
		$base = 'http://' . $server['address']; // NOSONAR local plain-HTTP fixture server, as the modems serve HTTP only

		expect(poller_script_http_get($base . '/plain'))->toBe("line one\nline two\n")
			->and(poller_script_http_get($base . '/missing'))->toBeNull()
			->and(poller_script_http_get($base . '/redirect'))->toBeNull();
	} finally {
		data_input_http_server_stop($server);
	}
});
