<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Logs in to the installer endpoint through PHP's built-in web server and asks for the wizard
// state while naming the command line runtime. The reply must still be the browser's, with the page
// markup. Usage: StepJsonProbe.php PHP_COMMAND_FLAGS_FILE
//   The flags file holds the php options (one per line) the server runs with, so it publishes
//   request coverage.
$root  = dirname(__DIR__, 3);
$flags = file($argv[1] ?? '/dev/null', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$port  = 18000 + random_int(0, 999);

$server = proc_open(
	array_merge([PHP_BINARY], $flags, ['-S', "127.0.0.1:$port", '-t', $root]),
	[0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
	$pipes,
	$root
);

for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
	usleep(100000);
}

$cookie = '';

function probe_request(string $path, ?array $post, string &$cookie) : string {
	global $port;

	$context = stream_context_create(['http' => [
		'method'        => $post === null ? 'GET' : 'POST',
		'header'        => "Cookie: $cookie\r\nContent-Type: application/x-www-form-urlencoded\r\n",
		'content'       => $post === null ? '' : http_build_query($post),
		'ignore_errors' => true,
		'follow_location' => 0,
	]]);
	$body = (string) file_get_contents("http://127.0.0.1:$port$path", false, $context);

	foreach ($http_response_header ?? [] as $header) {
		if (stripos($header, 'Set-Cookie:') === 0) {
			$cookie = trim(explode(';', substr($header, 11))[0]);
		}
	}

	return $body;
}

function probe_token(string $page) : string {
	return preg_match('/csrfMagicToken\s*=\s*["\']([^"\']+)/', $page, $match) ? $match[1] : '';
}

$token = probe_token(probe_request('/install/install.php', null, $cookie));
probe_request('/index.php', ['action' => 'login', 'login_username' => 'admin', 'login_password' => 'admin', '__csrf_magic' => $token], $cookie);
$token = probe_token(probe_request('/install/install.php', null, $cookie));
$reply = json_decode(probe_request('/install/step_json.php', ['data' => ['Step' => 1, 'Runtime' => 'Cli'], '__csrf_magic' => $token], $cookie), true);

proc_terminate($server);
proc_close($server);

print 'step=' . ($reply['Step'] ?? 'none') . ' html=' . (isset($reply['Html']) ? 'yes' : 'no') . PHP_EOL;
