<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$commandsSource = file_get_contents(__DIR__ . '/../../../../poller_commands.php');
$pollerSource   = file_get_contents(__DIR__ . '/../../../../poller.php');

function poller_hardening_function_body($source, $name) {
	$start = strpos($source, "function $name(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end === false ? null : $end - $start);
}

test('commands_launch_child pins the host id to an integer', function () use ($commandsSource) {
	$body = poller_hardening_function_body($commandsSource, 'commands_launch_child');

	expect($body)->toContain("' --child=' . (int) \$host_id")
		->and($body)->not->toContain('--child=$host_id');
});

test('commands_launch_child escapes the script path', function () use ($commandsSource) {
	$body = poller_hardening_function_body($commandsSource, 'commands_launch_child');

	expect($body)->toContain("cacti_escapeshellarg(\$config['base_path'] . '/poller_commands.php')");
});

test('poller_replicate_check binds the sync interval', function () use ($pollerSource) {
	$body = poller_hardening_function_body($pollerSource, 'poller_replicate_check');

	expect($body)->toContain('db_fetch_assoc_prepared(')
		->and($body)->toContain('IFNULL(sync_interval, ?)')
		->and($body)->toContain('array($sync_interval)')
		->and($body)->not->toContain('IFNULL(sync_interval, $sync_interval)');
});
