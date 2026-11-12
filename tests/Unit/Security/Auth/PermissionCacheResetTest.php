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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
*/

/*
 * Tree, graph, device and template permissions are cached in the session and
 * dropped when user_auth.reset_perms changes.  Every console path that writes
 * a user or group permission row or default policy, a group membership or
 * removes a group must
 * therefore call reset_user_perms() or reset_group_perms(), or a signed-in
 * user keeps the old access until they log out.
 */

/**
 * Split a console page into its request handlers: each isrv('...') branch and
 * each top-level function, so a write is matched with the reset in its own
 * handler.
 */
function permission_cache_handlers(string $file) : array {
	$source   = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
	$handlers = preg_split('/^(?=\tif \(+isrv\(|\t\} elseif \(isrv\(|function )/m', $source);

	return array_values(array_filter($handlers, fn ($handler) => preg_match(
		'/(INSERT|REPLACE|DELETE)\s+(IGNORE\s+)?(INTO|FROM)\s+user_auth_(perms|group_perms|group_members)\b/i', $handler) === 1
		|| preg_match('/UPDATE\s+`?user_auth(_group)?`?\s+SET\s+`?(policy_|\$p\b)/i', $handler)                           === 1));
}

dataset('permission writers', function () {
	foreach (['user_admin.php', 'user_group_admin.php'] as $file) {
		foreach (permission_cache_handlers($file) as $handler) {
			preg_match('/^.*$/m', ltrim($handler), $first);

			yield $file . ': ' . trim($first[0]) => [$handler];
		}
	}
});

test('a handler that writes permissions resets the permission cache', function (string $handler) {
	// a copy writes a new group that has no members yet
	if (str_starts_with($handler, 'function user_group_copy(')) {
		expect(true)->toBeTrue();

		return;
	}

	expect($handler)->toMatch('/reset_(user|group)_perms\(/');
})->with('permission writers');
