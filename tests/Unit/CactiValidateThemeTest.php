<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// cacti_validate_theme() reads the configured default from
// read_config_option('selected_theme'); in CLI context that resolves from the
// $config['config_options_array'] cache, so seeding it controls the default.
function set_configured_default_theme(string $theme) : void {
	$GLOBALS['config'][OPTIONS_CLI]['selected_theme'] = $theme;
}

test('valid requested theme returns itself', function () {
	set_configured_default_theme('dark');

	expect(cacti_validate_theme('modern'))->toBe('modern');
});

test('invalid requested theme returns a valid configured default', function () {
	set_configured_default_theme('dark');

	expect(cacti_validate_theme('does_not_exist'))->toBe('dark');
});

test('invalid requested theme with a poisoned default returns the safe fallback', function () {
	set_configured_default_theme('../../tmp/x');

	$result = cacti_validate_theme('also_invalid');

	expect($result)->toBe('modern')
		->and($result)->not->toContain('..')
		->and($result)->not->toContain('/');
});

test('traversal in the requested theme never escapes the theme set', function () {
	set_configured_default_theme('dark');

	$result = cacti_validate_theme('../../../etc/passwd');

	expect($result)->not->toContain('..')
		->and($result)->not->toContain('/')
		->and(is_dir(CACTI_PATH_INCLUDE . '/themes/' . $result))->toBeTrue();
});
