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

/*
 * Removing a user or group permission row, and queueing every unknown RRDfile
 * for deletion or archiving, change stored data. Each must arrive as a POST
 * with a valid token, and the cleaner's own buttons must send that request.
 * The probe runs each page's controller block behind the real POST check.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$post_guard_probe = dirname(__DIR__, 3) . '/fixtures/Core/CollectorCleanupGates.php';

test('a GET never reaches the guarded handler', function (string $page) use ($post_guard_probe) {
	$result = cacti_test_isolated_probe($post_guard_probe, ['refused', $page]);

	expect($result['calls'])->toBe([])
		->and($result['token_checks'])->toBe(0);
})->with([
	'user permissions'  => ['user_admin.php'],
	'group permissions' => ['user_group_admin.php'],
	'rrd cleaner'       => ['rrdcleaner.php'],
]);

test('a POST without a valid token never reaches the guarded handler', function (string $page) use ($post_guard_probe) {
	$result = cacti_test_isolated_probe($post_guard_probe, ['refused', $page, 'POST']);

	expect($result['calls'])->toBe([])
		->and($result['token_checks'])->toBe(1);
})->with([
	'user permissions'  => ['user_admin.php'],
	'group permissions' => ['user_group_admin.php'],
	'rrd cleaner'       => ['rrdcleaner.php'],
]);

test('a POST with a valid token reaches the handler', function () use ($post_guard_probe) {
	$calls = cacti_test_isolated_probe($post_guard_probe, ['post'])['calls'];

	expect($calls['user_admin.php'])->toBe(['perm_remove'])
		->and($calls['user_group_admin.php'])->toBe(['perm_remove'])
		->and($calls['rrdcleaner.php'])->toBe(['top_header', 'remove_all_rrds', 'list_rrd', 'bottom_footer']);
});

test('the cleaner buttons post their action with the token', function () use ($post_guard_probe) {
	$result = cacti_test_isolated_probe($post_guard_probe, ['post']);

	foreach (['remall', 'arcall'] as $action) {
		expect($result['buttons'][$action])->toStartWith("postUrl({ url: 'rrdcleaner.php?action=" . $action . '&')
			->and($result['buttons'][$action])->toContain('__csrf_magic: csrfMagicToken');
	}

	expect($result['rescan'])->toBe('rrdcleaner.php?action=rescan');
});

test('no page links the permission removal action by GET', function () {
	$root = dirname(__DIR__, 4);

	foreach (['user_admin.php', 'user_group_admin.php', 'include/layout.js', 'lib/html_utility.php', 'lib/html.php'] as $file) {
		expect(file_get_contents($root . '/' . $file))->not->toContain('action=perm_remove');
	}
});
