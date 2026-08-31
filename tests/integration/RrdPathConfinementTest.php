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
 * End-to-end coverage for rrd_check_path() containment against a real RRA
 * directory on disk. realpath() resolves symlinks, so an escape through a
 * symlinked subdirectory is caught the same as a literal '..'.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

beforeEach(function () {
	$this->root    = sys_get_temp_dir() . '/rrdconf_' . getmypid() . '_' . uniqid();
	$this->base    = $this->root . '/rra';
	$this->outside = $this->root . '/outside';
	mkdir($this->base, 0777, true);
	mkdir($this->outside, 0777, true);
	file_put_contents($this->base . '/x.rrd', '');
	file_put_contents($this->outside . '/secret.rrd', '');
});

afterEach(function () {
	foreach (array($this->base . '/link', $this->base . '/x.rrd', $this->outside . '/secret.rrd') as $f) {
		if (is_link($f) || file_exists($f)) {
			unlink($f);
		}
	}

	foreach (array($this->base, $this->outside, $this->root) as $d) {
		if (is_dir($d)) {
			rmdir($d);
		}
	}
});

test('allows a file inside the RRA base', function () {
	expect(rrd_check_path($this->base . '/x.rrd', $this->base))->toBeTrue();
});

test('allows a not-yet-created file inside the RRA base', function () {
	expect(rrd_check_path($this->base . '/new/../new.rrd', $this->base))->toBeFalse(); // traversal still rejected
	expect(rrd_check_path($this->base . '/new.rrd', $this->base))->toBeTrue();
});

test('rejects a file outside the RRA base', function () {
	expect(rrd_check_path($this->outside . '/secret.rrd', $this->base))->toBeFalse();
});

test('rejects an escape through a symlinked subdirectory', function () {
	symlink($this->outside, $this->base . '/link');

	expect(rrd_check_path($this->base . '/link/secret.rrd', $this->base))->toBeFalse();
});

test('allows a not-yet-created file in a not-yet-created subdirectory of the base', function () {
	// the create flow mkdir()s missing parents, so this legitimate case must pass
	expect(rrd_check_path($this->base . '/newsite/deep/traffic.rrd', $this->base))->toBeTrue();
});

test('rejects a target whose parent directory does not exist and is outside the base', function () {
	// the earlier single-level realpath(dirname()) fallback returned true here,
	// which the create flow could then mkdir into existence outside the RRA tree
	expect(rrd_check_path($this->root . '/evil/nested/x.rrd', $this->base))->toBeFalse();
	expect(rrd_check_path('/nonexistent/evil/x.rrd', $this->base))->toBeFalse();
});
