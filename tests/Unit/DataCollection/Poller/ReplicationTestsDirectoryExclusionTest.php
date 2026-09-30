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

test('the plugin resource scan excludes tests directories', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function update_resource_cache(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

	expect($body)->toContain("'.gitattributes', 'tests'");
});
