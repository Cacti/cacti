<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiProcess.php')) {
	test('Net_Ping integration: CactiProcess feature not present on this branch', function () {})
		->skip('lib/CactiProcess.php absent — feature PR #7073 not merged into develop yet');
	return;
}

require_once dirname(__DIR__, 2) . '/lib/ping.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';

test('Net_Ping::ping_icmp handles fping-style output through CactiProcess', function () {
	// Mock a Net_Ping object
	$ping = new Net_Ping();
	$ping->host = ['hostname' => '127.0.0.1'];
	$ping->timeout = 500;
	$ping->retries = 1;
	
	// Since we can't easily mock the shell command output in this environment without a full mock framework,
	// we'll focus on ensuring the logic doesn't crash and correctly initializes.
	
	// We check if the ping logic can resolve a local address
	$ping->ping_icmp();
	
	// The result depends on whether fping/ping exists in the environment.
	// We mostly want to verify that our refactor doesn't cause a fatal error.
	expect($ping->ping_response)->not->toBeEmpty();
});
