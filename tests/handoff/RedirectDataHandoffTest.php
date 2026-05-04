<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/html_utility.php';

test('Data Handoff: referer is validated before redirection', function () {
	// Simulate the logic in lib/auth.php or auth_changepassword.php
	$mock_server_referer = 'http://attacker.com/evil';
	
	// The hand-off logic
	$target = validate_redirect_url($mock_server_referer);
	
	expect($target)->toBe('index.php');
	expect($target)->not->toContain('attacker.com');
});

test('Data Handoff: local path referer is preserved', function () {
	$mock_server_referer = '/cacti/graph_view.php?id=50';
	$target = validate_redirect_url($mock_server_referer);
	
	expect($target)->toBe('/cacti/graph_view.php?id=50');
});
