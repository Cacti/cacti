<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	require_once dirname(__DIR__, 4) . '/lib/html.php';
});

test('password-change username renders as text', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/auth_changepassword.php');
	preg_match('/[^\n]*id=\'current\'[^\n]*/', $source, $line);
	preg_match('/<\?php print (.+?);\s*\?>/', $line[0], $expression);
	$user   = ['username' => '<img src=x onerror=alert(1)>'];
	$output = eval('return ' . $expression[1] . ';');
	expect($output)->toBe(html_escape($user['username']));
});
