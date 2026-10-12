<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Execute the shipped output expressions without booting a live login or import.
test('password-change username renders as text', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/auth_changepassword.php');
	preg_match('/[^\n]*id=\'current\'[^\n]*/', $source, $line);
	preg_match('/<\?php print (.+?);\s*\?>/', $line[0], $expression);
	$user   = ['username' => '<img src=x onerror=alert(1)>'];
	$output = eval('return ' . $expression[1] . ';');
	expect($output)->toBe(htmle($user['username']));
});

test('authentication failure messages render as text', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');
	preg_match('/print [^\n]*ui-state-error[^\n]*;/', $source, $statement);
	$message        = '<script>alert(1)</script>';
	$custom_message = '<img src=x onerror=alert(2)>';
	ob_start();

	try {
		eval($statement[0]);
		$output = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	expect($output)->toContain(htmle($message))->toContain(htmle($custom_message))
		->not->toContain('<script>')->not->toContain('<img');
});

test('package homepage uses the escaped cell renderer', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/package_import.php');
	expect($source)->toContain("form_selectable_ecell(\$details['homepage'], \$id)")
		->not->toContain("form_selectable_cell(\$details['homepage'], \$id)");
});
