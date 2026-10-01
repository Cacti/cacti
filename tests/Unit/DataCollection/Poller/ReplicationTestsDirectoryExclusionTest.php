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

/*
 * The file-based resource replication that feeds remote data collectors must
 * never carry 'tests' directories - neither Cacti's own tests/ nor any
 * plugin's tests/ - nor anything beneath them, in either direction.
 *
 * should_ignore_from_replication() is the gate consulted while hashing a
 * directory (md5sum_path), caching files into poller_resource_cache
 * (update_db_from_path) and pushing that cache out to a remote collector
 * (resource_cache_out). The plugin scan in update_resource_cache()
 * additionally excludes 'tests' from the directories it walks so a plugin's
 * tests/ tree is never cached in the first place.
 */

require_once dirname(__DIR__, 4) . '/include/global_constants.php';
require_once dirname(__DIR__, 4) . '/lib/poller.php';

if (!isset($GLOBALS['config']['base_path'])) {
	// Simulate an installation whose root itself contains a 'tests' ancestor
	// (e.g. /srv/tests/cacti) so the absolute-path handling below is exercised
	// even when no other test in the process has populated $config['base_path'].
	$GLOBALS['config']['base_path'] = '/srv/tests/cacti';
}

test('a bare tests directory entry is ignored', function () {
	expect(should_ignore_from_replication('tests'))->toBeTrue();
});

test('paths beneath a tests directory are ignored in either direction', function () {
	expect(should_ignore_from_replication('tests/Unit/Foo.php'))->toBeTrue()
		->and(should_ignore_from_replication('plugins/thold/tests'))->toBeTrue()
		->and(should_ignore_from_replication('plugins/thold/tests/Bar.php'))->toBeTrue();
});

test('the existing sentinel entries stay ignored', function () {
	expect(should_ignore_from_replication('.'))->toBeTrue()
		->and(should_ignore_from_replication('..'))->toBeTrue()
		->and(should_ignore_from_replication('.git'))->toBeTrue()
		->and(should_ignore_from_replication(''))->toBeTrue();
});

test('real replicated files are not mistaken for tests', function () {
	// only an exact 'tests' path segment is excluded, never a substring
	expect(should_ignore_from_replication('lib/poller.php'))->toBeFalse()
		->and(should_ignore_from_replication('scripts/ss_host_disk.php'))->toBeFalse()
		->and(should_ignore_from_replication('plugins/mytests/contents.php'))->toBeFalse()
		->and(should_ignore_from_replication('resource/script_server/contest.php'))->toBeFalse();
});

test('absolute file paths are matched relative to the installation root', function () {
	// update_db_from_path() hands this helper absolute file paths. The
	// installation root ($config['base_path']) is stripped first, so a 'tests'
	// ancestor of the Cacti tree must never cause ordinary files to be
	// excluded, while a real tests/ directory within the tree still is.
	$base = rtrim(str_replace('\\', '/', $GLOBALS['config']['base_path']), '/');

	expect(should_ignore_from_replication($base . '/lib/poller.php'))->toBeFalse()
		->and(should_ignore_from_replication($base . '/scripts/ss_host_disk.php'))->toBeFalse()
		->and(should_ignore_from_replication($base . '/tests/Unit/Foo.php'))->toBeTrue()
		->and(should_ignore_from_replication($base . '/plugins/thold/tests/Bar.php'))->toBeTrue();
});

test('the plugin resource scan excludes tests directories', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function update_resource_cache(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

	expect($body)->toContain("'.gitattributes', 'tests'");
});

test('cache-in purges pre-existing rows that are now excluded', function () {
	// The main-server purge must drop rows whose source file still exists but
	// is now excluded (e.g. a plugin tests/ tree cached before the upgrade),
	// not only rows whose file has disappeared.
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	$start = strpos($source, 'function update_resource_cache(');
	$end   = strpos($source, "\nfunction ", $start + 1);
	$body  = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

	expect($body)->toContain('!file_exists($item[\'path\']) || should_ignore_from_replication($item[\'path\'])');
});

test('cache-out skips excluded rows before building collector directories', function () {
	// A remote collector builds plugin directories from cached rows before
	// resource_cache_out() runs, so excluded paths must be filtered out first
	// or it would recreate plugins/foo/tests/ from stale rows.
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	$start = strpos($source, 'WHERE `path` LIKE "plugins/%"');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, 'foreach($paths as $type => $path)', $start);
	$body = substr($source, $start, $end - $start);

	expect($body)->toContain('should_ignore_from_replication($path[\'path\'])');
});

test('cache-in change detection skips ignored paths before logging or updating', function () {
	// Regression guard for the per-poll-cycle log spam fix. cache_in_path()
	// runs change detection *before* update_db_from_path(), so it must consult
	// should_ignore_from_replication() up front and return. Without that early
	// return an ignored dotfile (.gitignore, .mdlrc, ...) is logged and
	// re-processed every cycle: its md5 is never stored, so it is forever
	// re-detected as "changed". Pin the guard ahead of the first
	// change-detection log line so the regression cannot silently return.
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function cache_in_path(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

	$guard = strpos($body, 'should_ignore_from_replication($path)');
	$log   = strpos($body, 'NOTE: Detecting Resource Change');

	expect($guard)->not->toBeFalse()
		->and($log)->not->toBeFalse()
		->and($guard)->toBeLessThan($log);
});
