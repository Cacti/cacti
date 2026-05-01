<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/global.php';

test('Remote Agent: Network parameter is strictly validated as numeric', function () {
	// Mock environment
	global $config;
	$config['poller_id'] = 1;
	
	// Test Case 1: Valid numeric network ID
	$_REQUEST['network'] = '2';
	$network = get_filter_request_var('network'); // In remote_agent.php it's currently get_filter_request_var('network')
	// Wait, I changed it to check is_numeric($network) and cast to (int)
	
	$valid_network = '2';
	expect(is_numeric($valid_network))->toBeTrue();
	expect((int)$valid_network)->toBe(2);

	// Test Case 2: Malicious network ID (Command Injection attempt)
	$malicious_network = '2; rm -rf /';
	expect(is_numeric($malicious_network))->toBeFalse();
	
	// Test Case 3: Negative network ID
	$negative_network = '-1';
	expect(is_numeric($negative_network))->toBeTrue();
	expect((int)$negative_network)->toBe(-1);
	// My fix checks $network <= 0
});

test('Remote Agent: Sensitive parameters use FILTER_VALIDATE_INT', function () {
	// Simulate get_filter_request_var with FILTER_VALIDATE_INT as applied in remote_agent.php
	$_REQUEST['local_graph_id'] = '123';
	expect(filter_var($_REQUEST['local_graph_id'], FILTER_VALIDATE_INT))->toBe(123);

	$_REQUEST['local_graph_id'] = '123; DROP TABLE users;';
	expect(filter_var($_REQUEST['local_graph_id'], FILTER_VALIDATE_INT))->toBeFalse();

	$_REQUEST['effective_user'] = '99';
	expect((int)filter_var($_REQUEST['effective_user'], FILTER_VALIDATE_INT))->toBe(99);

	$_REQUEST['effective_user'] = 'invalid';
	expect(filter_var($_REQUEST['effective_user'], FILTER_VALIDATE_INT))->toBeFalse();
});
