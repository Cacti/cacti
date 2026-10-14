<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Execute the shipped output expressions without booting a live login or import.
test('password-change username renders as text', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/auth_changepassword.php');
	preg_match('/[^\n]*id=\'current\'[^\n]*/', $source, $line);
	preg_match('/<\?php print (.+?);\s*\?>/', $line[0], $expression);
	$user   = ['username' => '<img src=x onerror=alert(1)>'];
	$path   = tempnam(sys_get_temp_dir(), 'cacti-xss-test-');
	file_put_contents($path, '<?php return ' . $expression[1] . ';');

	try {
		$output = include $path;
	} finally {
		unlink($path);
	}
	expect($output)->toBe(htmle($user['username']));
});

test('authentication failure escapes the message and keeps the administrator HTML', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');
	preg_match('/print [^\n]*ui-state-error[^\n]*;/', $source, $statement);
	$message        = '<script>alert(1)</script>';
	$custom_message = '<b>Contact</b> <a href="help.php">support</a>';
	$path           = tempnam(sys_get_temp_dir(), 'cacti-xss-test-');
	file_put_contents($path, '<?php ' . $statement[0]);
	ob_start();

	try {
		include $path;
		$output = ob_get_contents();
	} finally {
		ob_end_clean();
		unlink($path);
	}
	expect($output)->toContain(htmle($message))->toContain($custom_message)
		->not->toContain('<script>');
});

test('package homepage uses the escaped cell renderer', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/package_import.php');
	expect($source)->toContain("form_selectable_ecell(\$details['homepage'], \$id)")
		->not->toContain("form_selectable_cell(\$details['homepage'], \$id)");
});
