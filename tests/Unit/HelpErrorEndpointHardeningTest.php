<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/help_error_guard.php';

test('help_resolve_error_page accepts registered Cacti pages and URLs with query strings', function () : void {
	$known = [
		'graphs.php' => 5,
		'host.php'   => 3,
	];

	expect(help_resolve_error_page('graphs.php', $known, ''))->toBe('graphs.php');
	expect(help_resolve_error_page('/cacti/host.php?action=edit&id=12', $known, ''))->toBe('host.php');
});

test('help_resolve_error_page accepts an existing top-level PHP file under base_path', function () : void {
	$dir = sys_get_temp_dir() . '/cacti_help_guard_' . bin2hex(random_bytes(6));
	mkdir($dir, 0700, true);
	file_put_contents($dir . '/custom_page.php', "<?php\n");

	try {
		expect(help_resolve_error_page('/cacti/custom_page.php?tab=general', [], $dir))->toBe('custom_page.php');
		expect(help_resolve_error_page('missing_page.php', [], $dir))->toBeNull();
	} finally {
		@unlink($dir . '/custom_page.php');
		@rmdir($dir);
	}
});

test('help_resolve_error_page rejects empty, unknown, markup, null-byte, and non-php values', function () : void {
	$known = ['graphs.php' => 5];

	expect(help_resolve_error_page('', $known, ''))->toBeNull();
	expect(help_resolve_error_page('   ', $known, ''))->toBeNull();
	expect(help_resolve_error_page("graphs.php\0extra", $known, ''))->toBeNull();
	expect(help_resolve_error_page('http://', $known, ''))->toBeNull();
	expect(help_resolve_error_page('bogus_random_key.php', $known, ''))->toBeNull();
	expect(help_resolve_error_page('<b>invalid</b>.php', $known, ''))->toBeNull();
	expect(help_resolve_error_page('graphs.txt', $known, ''))->toBeNull();
	expect(help_resolve_error_page('my-page.php', $known, ''))->toBeNull();
});

test('help_error_status_is_valid enforces 4xx and 5xx HTTP status bounds', function () : void {
	expect(help_error_status_is_valid(400))->toBeTrue();
	expect(help_error_status_is_valid(500))->toBeTrue();
	expect(help_error_status_is_valid(599))->toBeTrue();
	expect(help_error_status_is_valid(0))->toBeFalse();
	expect(help_error_status_is_valid(200))->toBeFalse();
	expect(help_error_status_is_valid(399))->toBeFalse();
	expect(help_error_status_is_valid(600))->toBeFalse();
});

test('help.php validates reported pages and escapes values passed to admin_email', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/help.php');
	expect($source)->not->toBeFalse();
	expect($source)->toContain("require_once(CACTI_PATH_LIBRARY . '/help_error_guard.php');");
	expect($source)->toContain('help_resolve_error_page(');
	expect($source)->toContain('help_error_status_is_valid($error)');
	expect($source)->toContain('html_escape($page), html_escape($username)');
});
