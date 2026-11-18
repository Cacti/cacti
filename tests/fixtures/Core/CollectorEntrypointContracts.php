<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

$root    = dirname(__DIR__, 3);
$options = ['-d', 'auto_prepend_file=' . dirname(__DIR__) . '/script_server_bootstrap.php'];

if (getenv('CACTI_PR_XDEBUG_EXTENSION')) {
	$options = array_merge(['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')], $options);
}

if (($argv[1] ?? 'cli') === 'cgi') {
	$binary = dirname(PHP_BINARY) . '/php-cgi' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

	if (!is_file($binary)) {
		throw new RuntimeException('The native PHP CGI executable is required for the non-CLI boundary contract.');
	}
	// Exercise the actual CGI SAPI locally, without an HTTP server. Keep its
	// redirect protection enabled and let Cacti's own CLI-only guard reject it.
	putenv('REDIRECT_STATUS=1');
	$cases = [[array_merge([$binary], $options, ['-q', '-f', $root . '/script_server.php']), 1, null]];
} else {
	$prefix = array_merge([PHP_BINARY], $options);
	$cases  = [
		[array_merge($prefix, [$root . '/cmd.php', '--help']), 0, 'usage: cmd.php'],
		[array_merge($prefix, [$root . '/cmd_realtime.php', '0', '1', '1']), 255, 'Invalid poller_id specified.'],
		[array_merge($prefix, [$root . '/cmd_realtime.php', '1', '0', '1']), 255, 'Invalid graph_id specified.'],
		[array_merge($prefix, [$root . '/cmd_realtime.php', str_repeat('a', 64), '0', '1']), 255, 'Invalid graph_id specified.'],
	];
}

foreach ($cases as [$command, $expectedStatus, $expectedOutput]) {
	$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);

	if (!is_resource($process)) {
		throw new RuntimeException('Could not start the isolated collector entrypoint.');
	}
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== $expectedStatus || ($expectedOutput !== null && !str_contains($output, $expectedOutput))
		|| str_contains($output, 'Script Server has Started')) {
		throw new RuntimeException('Collector boundary contract failed: ' . $error);
	}
}

printf("PASS: %d collector entrypoint contracts\n", count($cases));
