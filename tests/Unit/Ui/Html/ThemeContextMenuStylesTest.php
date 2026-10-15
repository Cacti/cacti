<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 4) . '/lib/html.php';

test('the common header loads context menu font overrides for the selected theme', function ($theme, $path) {
	$saved = [];

	foreach (['config', '_SESSION', 'settings_user'] as $key) {
		$saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}
	$GLOBALS['settings_user'] ??= [];
	ob_start();

	try {
		html_common_header('Theme contract', $theme);
		$header = ob_get_contents();
	} finally {
		ob_end_clean();

		foreach ($saved as $key => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
	}
	expect($header)->toContain($path);
})->with([
	['modern', 'include/css/context_menu_overrides.css'],
	['midwinter', 'include/themes/midwinter/context_menu_overrides.css'],
]);
