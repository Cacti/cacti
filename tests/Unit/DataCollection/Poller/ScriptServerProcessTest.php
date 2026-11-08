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

require_once dirname(__DIR__, 4) . '/lib/poller.php';

/**
 * Builds the command line that starts script_server.php under a test bootstrap.
 *
 * @param array<int,string> $arguments Script Server arguments
 * @param string            $bootstrap Fixture prepended to the worker
 *
 * @return array<int,string>
 */
function ss_process_test_command(array $arguments, string $bootstrap = 'script_server_bootstrap.php') : array {
	return array_merge([
		PHP_BINARY,
		'-d',
		'auto_prepend_file=' . dirname(__DIR__, 3) . '/fixtures/' . $bootstrap,
		dirname(__DIR__, 4) . '/script_server.php'
	], $arguments);
}

function ss_process_test_php_wrapper() : string {
	$wrapper   = sys_get_temp_dir() . '/script-server-php-' . bin2hex(random_bytes(8));
	$bootstrap = dirname(__DIR__, 3) . '/fixtures/script_server_bootstrap.php';
	$source    = "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('auto_prepend_file=' . $bootstrap) . " \"\$@\"\n";

	if (file_put_contents($wrapper, $source) === false || !chmod($wrapper, 0700)) {
		@unlink($wrapper);

		throw new RuntimeException('Unable to create the Script Server PHP wrapper');
	}

	return $wrapper;
}

/**
 * Starts a live Script Server and reads its startup banner.
 *
 * @param array<int,string> $arguments Script Server arguments, or empty for a cmd worker
 * @param string            $bootstrap Fixture prepended to the worker
 *
 * @return array{0: resource, 1: array<int,resource>, 2: string} Process, pipes, and banner
 */
function ss_process_test_start(array $arguments = [], string $bootstrap = 'script_server_bootstrap.php') : array {
	if (!$arguments) {
		$arguments = ['--environ=cmd', '--poller=1', '--mode=online'];
	}

	$process = proc_open(ss_process_test_command($arguments, $bootstrap), [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start Script Server fixture');
	}

	$healthy = true;
	$banner  = script_server_read_response($pipes, $process, $healthy, 3.0);

	if (!$healthy || $banner === false) {
		script_server_close($process, $pipes, false);

		throw new RuntimeException('Script Server fixture did not become ready');
	}

	return [$process, $pipes, $banner];
}

function ss_process_test_run(array $arguments) : array {
	$process = proc_open(ss_process_test_command($arguments), [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start Script Server fixture');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	return [$status, $stdout, $stderr];
}

function ss_process_test_run_php(string $script) : array {
	$process = proc_open([
		PHP_BINARY,
		'-d',
		'auto_prepend_file=' . dirname(__DIR__, 3) . '/fixtures/script_server_bootstrap.php',
		$script
	], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start PHP fixture');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	return [$status, $stdout, $stderr];
}

function ss_process_test_script() : array {
	$token  = bin2hex(random_bytes(8));
	$prefix = 'ss_runtime_' . $token;
	$file   = CACTI_PATH_SCRIPTS . '/.script-server-test-' . $token . '.php';
	$failed = CACTI_PATH_SCRIPTS . '/.script-server-failed-' . $token . '.php';
	$source = "<?php\n" .
		"print \"include-noise\\n\";\n" .
		"function {$prefix}_echo(string \$value = 'none') : string { return \$value; }\n" .
		"function {$prefix}_prints() : string { print str_repeat('x', 1048576); flush(); return 'printed-result'; }\n" .
		"function {$prefix}_buffers() : string { ob_start(); print str_repeat('x', 1048576); return 'buffered-result'; }\n" .
		"function {$prefix}_corrupts_buffer() : string { ob_start(null, 0, 0); print 'retained'; return 'unreachable'; }\n" .
		"function {$prefix}_multiline() : string { return \"first\\nsecond\"; }\n" .
		"function {$prefix}_control() : string { return \"first\\tsecond\"; }\n" .
		"function {$prefix}_long() : string { return str_repeat('x', SCRIPT_SERVER_MAX_RESPONSE_LENGTH + 1); }\n" .
		"function {$prefix}_slow() : string { usleep(250000); return 'late'; }\n" .
		"function {$prefix}_throws() : string { throw new RuntimeException('fixture failure'); }\n" .
		"function {$prefix}_guard_level() : string { return (string) script_server_start_output_guard(); }\n" .
		"function {$prefix}_guard_zero() : string { return script_server_reset_output_guard(0) ? 'bad' : 'guarded'; }\n" .
		"function {$prefix}_guard_mismatch() : string { return script_server_reset_output_guard(999) ? 'bad' : 'guarded'; }\n";

	file_put_contents($file, $source);
	file_put_contents($failed, "<?php\nfunction {$prefix}_partial() : string { return 'partial'; }\nthrow new RuntimeException('load failed');\n");

	return [$file, $failed, $prefix];
}

beforeEach(function () {
	[$this->script, $this->failed_script, $this->prefix] = ss_process_test_script();
	$this->process                                       = false;
	$this->pipes                                         = false;
});

afterEach(function () {
	if (is_resource($this->process) || is_array($this->pipes)) {
		script_server_close($this->process, $this->pipes);
	}

	if (is_file($this->script)) {
		unlink($this->script);
	}

	if (is_file($this->failed_script)) {
		unlink($this->failed_script);
	}
});

test('the live server emits one bounded response and remains aligned', function () {
	[$this->process, $this->pipes, $banner] = ss_process_test_start();

	expect($banner)->toBe('PHP Script Server has Started - Parent is cmd');

	$requests = [
		[$this->prefix . '_prints', '', 'printed-result'],
		[$this->prefix . '_buffers', '', 'buffered-result'],
		[$this->prefix . '_throws', '', 'U'],
		[$this->prefix . '_guard_level', '', '1'],
		[$this->prefix . '_guard_zero', '', 'guarded'],
		[$this->prefix . '_guard_mismatch', '', 'guarded'],
		['get_cacti_version', '', 'U'],
		[$this->prefix . '_echo', escapeshellarg('after'), 'after'],
	];

	foreach ($requests as [$function, $arguments, $expected]) {
		$healthy = true;
		$request = $this->script . ' ' . $function . ($arguments === '' ? '' : ' ' . $arguments);

		expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe($expected)
			->and($healthy)->toBeTrue();
	}

	$healthy = true;
	$request = CACTI_PATH_SCRIPTS . '/ss_net_snmp_disk_io.php CactiSnmpStateStore::write';

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
		->and($healthy)->toBeTrue();

	$request = $this->failed_script . ' ' . $this->prefix . '_partial';

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
		->and(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
		->and($healthy)->toBeTrue();
});

test('the output guard is stable and rejects invalid reset levels', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/script_server_invariant_probe.php';

	[$status, $stdout, $stderr] = ss_process_test_run_php($fixture);

	expect($status)->toBe(0)
		->and($stdout)->toBe('')
		->and($stderr)->toBe('');
});

test('complete invalid frames remain aligned and incomplete overlong frames retire the worker', function () {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;

	fwrite($this->pipes[0], str_repeat('x', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 1) . "\n");
	fflush($this->pipes[0]);

	expect(script_server_read_response($this->pipes, $this->process, $healthy, 1.0))->toBe('U')
		->and($healthy)->toBeTrue();

	fwrite($this->pipes[0], $this->script . ' ' . $this->prefix . "_echo\x01bad\n");
	fflush($this->pipes[0]);

	expect(script_server_read_response($this->pipes, $this->process, $healthy, 1.0))->toBe('U')
		->and($healthy)->toBeTrue()
		->and(exec_poll_php($this->script . ' ' . $this->prefix . '_echo aligned', true, $this->pipes, $this->process, 1.0, $healthy))->toBe('aligned');

	script_server_close($this->process, $this->pipes);
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;

	fwrite($this->pipes[0], str_repeat('x', SCRIPT_SERVER_MAX_REQUEST_LENGTH + 2));
	fflush($this->pipes[0]);

	expect(script_server_read_response($this->pipes, $this->process, $healthy, 1.0))->toBeFalse()
		->and($healthy)->toBeFalse();
});

test('canonical traversal and symlink aliases stay bound to the same script', function () {
	if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
		$this->markTestSkipped('requires symbolic links');
	}

	$alias = CACTI_PATH_SCRIPTS . '/.script-server-alias-' . bin2hex(random_bytes(8)) . '.php';

	try {
		expect(symlink($this->script, $alias))->toBeTrue();
		[$this->process, $this->pipes] = ss_process_test_start();
		$healthy                       = true;
		$lexical                       = dirname($this->script) . '/../scripts/' . basename($this->script);

		expect(exec_poll_php($lexical . ' ' . $this->prefix . '_echo lexical', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('lexical')
			->and(exec_poll_php($alias . ' ' . $this->prefix . '_echo symlink', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('symlink')
			->and($healthy)->toBeTrue();
	} finally {
		@unlink($alias);
	}
});

test('a cached callable binding fails closed when a symlink target changes', function () {
	if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
		$this->markTestSkipped('requires symbolic links');
	}

	$token                = bin2hex(random_bytes(8));
	$alias                = CACTI_PATH_SCRIPTS . '/.script-server-retarget-' . $token . '.php';
	$replacement          = CACTI_PATH_SCRIPTS . '/.script-server-replacement-' . $token . '.php';
	$marker               = sys_get_temp_dir() . '/script-server-retarget-marker-' . $token;
	$replacement_function = 'ss_replacement_' . $token;
	$source               = '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded'); function $replacement_function() : string { return 'replacement'; }";

	file_put_contents($replacement, $source);

	try {
		expect(symlink($this->script, $alias))->toBeTrue();
		[$this->process, $this->pipes] = ss_process_test_start();
		$healthy                       = true;

		expect(exec_poll_php($alias . ' ' . $this->prefix . '_echo original', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('original');

		unlink($alias);
		expect(symlink($replacement, $alias))->toBeTrue()
			->and(exec_poll_php($alias . ' ' . $this->prefix . '_echo rebound', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and(exec_poll_php($alias . ' ' . $replacement_function, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('replacement')
			->and(file_exists($marker))->toBeTrue()
			->and($healthy)->toBeTrue();
	} finally {
		@unlink($alias);
		@unlink($replacement);
		@unlink($marker);
	}
});

test('outside and escaping symlink scripts are rejected before include side effects', function () {
	if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
		$this->markTestSkipped('requires symbolic links');
	}

	$token   = bin2hex(random_bytes(8));
	$outside = sys_get_temp_dir() . '/script-server-outside-' . $token . '.php';
	$marker  = sys_get_temp_dir() . '/script-server-marker-' . $token;
	$link    = CACTI_PATH_SCRIPTS . '/.script-server-escape-' . $token . '.php';
	$source  = '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded'); function ss_outside_$token() : string { return 'outside'; }";

	file_put_contents($outside, $source);

	try {
		expect(symlink($outside, $link))->toBeTrue();
		[$this->process, $this->pipes] = ss_process_test_start();
		$healthy                       = true;

		expect(exec_poll_php($outside . ' ss_outside_' . $token, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and(exec_poll_php($link . ' ss_outside_' . $token, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and($healthy)->toBeTrue();
	} finally {
		@unlink($link);
		@unlink($outside);
		@unlink($marker);
	}
});

test('malformed and ambiguous requests are rejected before script side effects', function () {
	$token  = bin2hex(random_bytes(8));
	$script = CACTI_PATH_SCRIPTS . '/.script-server-side-effect-' . $token . '.php';
	$marker = sys_get_temp_dir() . '/script-server-marker-' . $token;
	$source = '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded'); function ss_valid_$token() : string { return 'valid'; }";

	file_put_contents($script, $source);

	try {
		[$this->process, $this->pipes] = ss_process_test_start();
		$healthy                       = true;

		expect(exec_poll_php($script . ' Class::method', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and(exec_poll_php($script . " ss_valid_$token 'unterminated", true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and(exec_poll_php($script . ' ss_valid_' . $token, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('valid')
			->and(file_exists($marker))->toBeTrue()
			->and($healthy)->toBeTrue();
	} finally {
		@unlink($script);
		@unlink($marker);
	}
});

test('a callable loaded by one script cannot be rebound to another script', function () {
	$token    = bin2hex(random_bytes(8));
	$conflict = CACTI_PATH_SCRIPTS . '/.script-server-conflict-' . $token . '.php';
	$marker   = sys_get_temp_dir() . '/script-server-marker-' . $token;
	$source   = '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded'); function {$this->prefix}_echo(string \$value = 'none') : string { return 'conflict:' . \$value; }";

	file_put_contents($conflict, $source);

	try {
		[$this->process, $this->pipes] = ss_process_test_start();
		$healthy                       = true;

		expect(exec_poll_php($this->script . ' ' . $this->prefix . '_echo first', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('first')
			->and(exec_poll_php($conflict . ' ' . $this->prefix . '_echo second', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
			->and(file_exists($marker))->toBeFalse()
			->and(exec_poll_php($this->script . ' ' . $this->prefix . '_echo third', true, $this->pipes, $this->process, 3.0, $healthy))->toBe('third')
			->and($healthy)->toBeTrue();
	} finally {
		@unlink($conflict);
		@unlink($marker);
	}
});

test('a framing or output limit violation retires the worker before a fresh worker serves requests', function (string $function) {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$request                       = $this->script . ' ' . $this->prefix . '_' . $function;

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	script_server_close($this->process, $this->pipes);
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$request                       = $this->script . ' ' . $this->prefix . '_echo ' . escapeshellarg('fresh');

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('fresh')
		->and($healthy)->toBeTrue();
})->with(['multiline', 'control', 'long']);

test('an unremovable nested output buffer corrupts and terminates the worker', function () {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$request                       = $this->script . ' ' . $this->prefix . '_corrupts_buffer';

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();
});

test('a timed out worker is discarded before a fresh worker serves requests', function () {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$request                       = $this->script . ' ' . $this->prefix . '_slow';

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 0.05, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	script_server_close($this->process, $this->pipes);
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$request                       = $this->script . ' ' . $this->prefix . '_echo ' . escapeshellarg('fresh');

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('fresh')
		->and($healthy)->toBeTrue();
});

test('output during the bootstrap does not reach stdout ahead of the banner', function () {
	[$this->process, $this->pipes, $banner] = ss_process_test_start([], 'script_server_noisy_bootstrap.php');
	$healthy                                = true;
	$request                                = $this->script . ' ' . $this->prefix . '_echo ' . escapeshellarg('aligned');

	expect($banner)->toBe('PHP Script Server has Started - Parent is cmd')
		->and(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('aligned')
		->and($healthy)->toBeTrue();
});

test('an empty request is refused without retiring a healthy worker', function (string $command) {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;
	$start                         = hrtime(true);

	expect(exec_poll_php($command, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('U')
		->and($healthy)->toBeTrue()
		->and((hrtime(true) - $start) / 1e9)->toBeLessThan(1.0);

	$request = $this->script . ' ' . $this->prefix . '_echo ' . escapeshellarg('still-aligned');

	expect(exec_poll_php($request, true, $this->pipes, $this->process, 3.0, $healthy))->toBe('still-aligned')
		->and($healthy)->toBeTrue();
})->with(['empty' => [''], 'blank' => ['   ']]);

test('the server exits promptly when its input closes', function () {
	[$this->process, $this->pipes] = ss_process_test_start();

	fclose($this->pipes[0]);
	$deadline = hrtime(true) + 1000000000;

	do {
		$status = proc_get_status($this->process);

		if (!$status['running']) {
			break;
		}

		usleep(10000);
	} while (hrtime(true) < $deadline);

	expect($status['running'])->toBeFalse();

	fclose($this->pipes[1]);
	fclose($this->pipes[2]);
	proc_close($this->process);
	$this->process = false;
	$this->pipes   = false;
});

test('the reserved shutdown request is framed and the caller retires the exited worker', function () {
	[$this->process, $this->pipes] = ss_process_test_start();
	$healthy                       = true;

	expect(exec_poll_php('quit', true, $this->pipes, $this->process, 1.0, $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	$deadline = hrtime(true) + 1000000000;

	do {
		$status = proc_get_status($this->process);

		if (!$status['running']) {
			break;
		}

		usleep(10000);
	} while (hrtime(true) < $deadline);

	expect($status['running'])->toBeFalse();
});

test('the worker lifecycle helpers open replace and close a live server', function () {
	if (DIRECTORY_SEPARATOR === '\\') {
		$this->markTestSkipped('requires an executable test wrapper');
	}

	global $config;

	$wrapper       = ss_process_test_php_wrapper();
	$had_binary    = array_key_exists('path_php_binary', $config[OPTIONS_CLI]);
	$original      = $config[OPTIONS_CLI]['path_php_binary'] ?? null;
	$process       = false;
	$pipes         = false;
	$using_process = false;

	$config[OPTIONS_CLI]['path_php_binary'] = $wrapper;

	try {
		[$process, $pipes, $using_process] = script_server_open('cmd', 1, 'online', 3.0);

		expect($using_process)->toBeTrue();

		$healthy = true;
		$request = $this->script . ' ' . $this->prefix . '_echo first';

		expect(exec_poll_php($request, true, $pipes, $process, 1.0, $healthy))->toBe('first')
			->and(script_server_replace($process, $pipes, $using_process, 'cmd', 1, 'online', 3.0))->toBeTrue();

		$healthy = true;
		$request = $this->script . ' ' . $this->prefix . '_echo replacement';

		expect(exec_poll_php($request, true, $pipes, $process, 1.0, $healthy))->toBe('replacement');

		script_server_close($process, $pipes);

		expect($process)->toBeFalse()->and($pipes)->toBeFalse();
	} finally {
		script_server_close($process, $pipes, false);
		@unlink($wrapper);

		if ($had_binary) {
			$config[OPTIONS_CLI]['path_php_binary'] = $original;
		} else {
			unset($config[OPTIONS_CLI]['path_php_binary']);
		}
	}
});

test('short and long CLI options select help version and environment', function () {
	[$status, $stdout, $stderr] = ss_process_test_run(['-h']);

	expect($status)->toBe(0)
		->and($stdout)->toContain('usage: script_server.php')
		->and($stdout)->not->toContain('Script Server has Started')
		->and($stderr)->toBe('');

	[$status, $stdout, $stderr] = ss_process_test_run(['-V']);

	expect($status)->toBe(0)
		->and($stdout)->toContain('Cacti Script Server, Version')
		->and($stderr)->toBe('');

	[$this->process, $this->pipes, $banner] = ss_process_test_start(['--environ=spine', '--poller=1', '--mode=online']);

	expect($banner)->toBe('PHP Script Server has Started - Parent is spine');
	script_server_close($this->process, $this->pipes);
	[$this->process, $this->pipes, $banner] = ss_process_test_start(['--environ', 'realtime', '--poller', str_repeat('a', 64), '--mode', 'online']);

	expect($banner)->toBe('PHP Script Server has Started - Parent is realtime');
	script_server_close($this->process, $this->pipes);
	[$this->process, $this->pipes, $banner] = ss_process_test_start(['cmd.php', '1']);

	expect($banner)->toBe('PHP Script Server has Started - Parent is cmd');
});

test('invalid CLI poller and mode values fail closed', function (array $arguments, string $message) {
	[$status, $stdout, $stderr] = ss_process_test_run($arguments);

	expect($status)->toBe(1)
		->and($stdout)->not->toContain('Script Server has Started')
		->and($stderr)->toContain($message);
})->with([
	'poller'       => [['--environ=cmd', '--poller=../bad', '--mode=online'], 'Invalid Script Server poller id'],
	'zero poller'  => [['--environ=cmd', '--poller=0', '--mode=online'], 'Invalid Script Server poller id'],
	'mode'         => [['--environ=cmd', '--poller=1', '--mode=sideways'], 'Invalid Script Server connection mode'],
	'unknown'      => [['--unknown'], 'Invalid Script Server arguments'],
	'missing value'=> [['--poller'], 'Invalid Script Server arguments'],
	'extra legacy' => [['cmd', '1', 'extra'], 'Invalid Script Server arguments'],
]);
