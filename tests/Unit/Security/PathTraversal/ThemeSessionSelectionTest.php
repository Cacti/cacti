<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('theme switching stores an allowlisted theme name', function ($requested) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');
	preg_match("/if \(isrv\('newtheme'\)\) \{(.*?)\n\t\}\n\n\tif \(isrv\('csrf_timeout'\)\)/s", $source, $block);
	expect($block)->not->toBeEmpty();
	$body     = str_replace("gnrv('newtheme')", '$requested', $block[1]);
	$previous = $_SESSION;
	$path     = tempnam(sys_get_temp_dir(), 'cacti-theme-test-');

	try {
		file_put_contents($path, '<?php ' . $body);
		include $path;
		expect($_SESSION['selected_theme'])->not->toContain('..')->not->toContain('/')
			->and(is_dir(CACTI_PATH_INCLUDE . '/themes/' . $_SESSION['selected_theme']))->toBeTrue();
	} finally {
		unlink($path);
		$_SESSION = $previous;
	}
})->with(['../themes/modern', '../../../tmp/modern', 'modern', [['modern']]]);
