<?php

use Cacti\Auth\LoginThrottle;

if (PHP_SAPI !== 'cli' || $argc !== 5) {
	exit(1);
}

chdir(dirname(__DIR__, 3));
require 'include/global.php';

$database = getenv('CACTI_THROTTLE_TEST_DATABASE');

if (!$database || !preg_match('/^throttle_test_[a-z0-9_]+$/D', $database) || db_fetch_cell('SELECT DATABASE()') !== $database) {
	throw new RuntimeException('Use a disposable throttle_test_ database.');
}

// Override configuration in this request only. Independent subprocesses still
// acquire the same InnoDB rows through their own connections.
$config[OPTIONS_CLI]['auth_throttle_ip']     = 6;
$config[OPTIONS_CLI]['auth_throttle_pair']   = 2;
$config[OPTIONS_CLI]['auth_throttle_window'] = (int) $argv[4];
$_SERVER['REMOTE_ADDR']                      = $argv[1];
$_SERVER['HTTP_X_FORWARDED_FOR']             = $argv[2];
$config['proxy_headers']                     = ['HTTP_X_FORWARDED_FOR'];
$config['trusted_proxies']                   = ['192.0.2.1'];

if ($argv[3] === '--reservations') {
	$addr   = (string) get_client_addr();
	$first  = LoginThrottle::reserve($addr, 'alice');
	$failed = LoginThrottle::reserve($addr, 'alice');

	if (!$first || !$failed || LoginThrottle::reserve($addr, 'alice') !== null) {
		throw new RuntimeException('Two in-flight attempts must occupy the entire identity budget.');
	}
	$first->completeSuccessfulLogin();
	$first->completeSuccessfulLogin();

	if (!LoginThrottle::reserve($addr, 'alice') || LoginThrottle::reserve($addr, 'alice') !== null) {
		throw new RuntimeException('A successful attempt must release exactly its own reservation.');
	}

	// Simulate a slow verification finishing after a new window has begun.
	sleep((int) $argv[4]);
	$config[OPTIONS_CLI]['auth_throttle_window'] = 60;

	if (!LoginThrottle::reserve($addr, 'alice') || !LoginThrottle::reserve($addr, 'alice')) {
		throw new RuntimeException('Expired windows must admit new attempts.');
	}
	$failed->completeSuccessfulLogin();

	if (LoginThrottle::reserve($addr, 'alice') !== null || !LoginThrottle::reserve($addr, 'bob')) {
		throw new RuntimeException('A late completion must preserve new-window failures and the separate identity budget.');
	}

	print 'ok';

	exit;
}

print LoginThrottle::reserve((string) get_client_addr(), $argv[3]) ? 'allow' : 'deny';
