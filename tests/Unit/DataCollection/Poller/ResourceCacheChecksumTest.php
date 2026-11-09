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
 * md5sum_path() fingerprints a replicated directory so remote collectors only
 * pull changed resources. An entry it cannot read must not abort the scan or
 * leak into the fingerprint, and archives are never part of it.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 4) . '/lib/poller.php';

beforeEach(function () {
	$this->directory = sys_get_temp_dir() . '/cacti-md5sum-' . bin2hex(random_bytes(6));

	mkdir($this->directory . '/nested', 0700, true);
	file_put_contents($this->directory . '/script.php', '<?php return 1;');
	file_put_contents($this->directory . '/nested/data.txt', 'nested');
});

afterEach(function () {
	foreach (['/script.php', '/nested/data.txt', '/bundle.tgz', '/dangling.php'] as $entry) {
		if (is_link($this->directory . $entry) || is_file($this->directory . $entry)) {
			unlink($this->directory . $entry);
		}
	}

	rmdir($this->directory . '/nested');
	rmdir($this->directory);
});

test('an unreadable entry is skipped without changing the fingerprint', function () {
	$before = md5sum_path($this->directory);

	// A dangling link is neither a directory nor readable.
	symlink($this->directory . '/removed-target.php', $this->directory . '/dangling.php');
	file_put_contents($this->directory . '/bundle.tgz', 'archive');

	expect(md5sum_path($this->directory))->toBe($before);

	file_put_contents($this->directory . '/nested/data.txt', 'changed');

	expect(md5sum_path($this->directory))->not->toBe($before)
		->and(md5sum_path($this->directory, false))->toMatch('/^[0-9a-f]{32}$/');
});

test('a missing directory has no fingerprint', function () {
	expect(md5sum_path($this->directory . '/absent'))->toBeFalse();
});
