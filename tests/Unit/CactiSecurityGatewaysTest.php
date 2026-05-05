<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');
$helpSource      = file_get_contents(__DIR__ . '/../../help.php');
$pluginsSource   = file_get_contents(__DIR__ . '/../../lib/plugins.php');

test('cacti_exec is defined and rejects empty binary', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_exec(');
	expect($start)->not->toBeFalse();

	/* New implementation guards with trim() and returns -1; it does not
	 * throw because callers are expected to log and move on. */
	$body = substr($functionsSource, $start, 900);
	expect($body)->toContain("trim(\$binary) === ''");
	expect($body)->not->toContain('cacti_escapeshellcmd');
});

test('cacti_exec uses proc_open with argv array to bypass the shell', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_exec(');
	$body  = substr($functionsSource, $start, 3000);

	/* argv array passed directly — no shell, no escaping needed */
	expect($body)->toContain('proc_open($argv, $descriptors, $pipes)');
	expect($body)->toContain('array_merge(array($binary), array_values($args))');
});

test('cacti_exec uses non-blocking I/O with stream_select on stdout and stderr', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_exec(');
	$body  = substr($functionsSource, $start, 3000);

	expect($body)->toContain('stream_set_blocking($pipes[1], 0)');
	expect($body)->toContain('stream_set_blocking($pipes[2], 0)');
	expect($body)->toContain('stream_select($read, $write, $except,');
});

test('cacti_exec returns empty array on empty stdout not array with empty string', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_exec(');
	$body  = substr($functionsSource, $start, 3000);

	expect($body)->toContain("\$output  = (\$stdout === '') ? array() : explode(\"\\n\", \$stdout)");
});

test('cacti_http rejects non-http(s) schemes', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_http(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 2200);
	expect($body)->toContain("\$scheme !== 'http' && \$scheme !== 'https'");
});

test('cacti_http enforces TLS peer verification on https', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_http(');
	$body  = substr($functionsSource, $start, 2200);

	expect($body)->toContain("'verify_peer'       => true");
	expect($body)->toContain("'verify_peer_name'  => true");
	expect($body)->toContain("'allow_self_signed' => false");
});

test('cacti_http disables redirect following', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_http(');
	$body  = substr($functionsSource, $start, 2200);

	expect($body)->toContain("'follow_location' => 0");
	expect($body)->toContain("'max_redirects'   => 0");
});

test('cacti_http honours host allowlist when supplied', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_http(');
	$body  = substr($functionsSource, $start, 2200);

	expect($body)->toContain('!in_array($host_lower, $allowed, true)');
});

test('cacti_plugin_path rejects unsafe plugin names', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_plugin_path(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 1200);
	expect($body)->toContain("preg_match('/^[A-Za-z0-9_-]+$/', \$plugin)");
});

test('cacti_plugin_path realpaths the plugin base', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_plugin_path(');
	$body  = substr($functionsSource, $start, 1200);

	expect($body)->toContain("realpath(\$config['base_path'] . '/plugins/' . \$plugin)");
});

test('cacti_plugin_path routes the relative path through validate_relative_path_within', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_plugin_path(');
	$body  = substr($functionsSource, $start, 1200);

	expect($body)->toContain('validate_relative_path_within((string) $relative, $plugin_base)');
});

test('help.php uses cacti_http for the docs fetch', function () use ($helpSource) {
	expect($helpSource)->toContain("cacti_http('https://docs.cacti.net/' . \$page, 2, array('docs.cacti.net'), \$response_code)");
});

test('lib/plugins.php routes plugin setup.php includes through cacti_plugin_path', function () use ($pluginsSource) {
	expect($pluginsSource)->toContain("cacti_plugin_path(\$plugin, 'setup.php')");
	expect($pluginsSource)->toContain("cacti_plugin_path(\$hdata['name'], \$hdata['file'])");
	expect($pluginsSource)->toContain("cacti_plugin_path(\$plugin_name, \$plugin_file)");
});
