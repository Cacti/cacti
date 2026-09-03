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

/**
 * Compares the committed include/vendor tree against composer.lock.
 *
 * Cacti commits both, so a checkout has working dependencies without running
 * Composer.  That only holds while the two agree; when they drift, a source
 * checkout runs code against package versions the lock never selected.
 */

$root = dirname(__DIR__, 2);

$lock_file      = $root . '/composer.lock';
$installed_file = $root . '/include/vendor/composer/installed.json';

foreach ([$lock_file, $installed_file] as $file) {
	if (!is_readable($file)) {
		fwrite(STDERR, sprintf("Cannot read %s\n", $file));

		exit(1);
	}
}

$lock = json_decode(file_get_contents($lock_file), true);

if (!is_array($lock) || !isset($lock['packages'])) {
	fwrite(STDERR, "composer.lock is not valid JSON or has no packages\n");

	exit(1);
}

$installed = json_decode(file_get_contents($installed_file), true);

if (!is_array($installed)) {
	fwrite(STDERR, "installed.json is not valid JSON\n");

	exit(1);
}

// Composer 2 nests the list under 'packages'; Composer 1 wrote a bare array.
$installed_packages = isset($installed['packages']) ? $installed['packages'] : $installed;

$locked = [];

foreach ($lock['packages'] as $package) {
	$locked[$package['name']] = $package['version'];
}

$present = [];

foreach ($installed_packages as $package) {
	$present[$package['name']] = $package['version'];
}

$missing    = array_diff_key($locked, $present);
$unexpected = array_diff_key($present, $locked);
$mismatched = [];

foreach (array_intersect_key($locked, $present) as $name => $version) {
	if ($present[$name] !== $version) {
		$mismatched[$name] = [$present[$name], $version];
	}
}

if (empty($missing) && empty($unexpected) && empty($mismatched)) {
	print 'include/vendor matches composer.lock (' . count($locked) . ' packages)' . PHP_EOL;

	exit(0);
}

fwrite(STDERR, "The committed include/vendor tree does not match composer.lock.\n\n");

foreach ($mismatched as $name => $versions) {
	fwrite(STDERR, sprintf("  %-40s vendor %-14s lock %s\n", $name, $versions[0], $versions[1]));
}

foreach ($missing as $name => $version) {
	fwrite(STDERR, sprintf("  %-40s absent from vendor, lock %s\n", $name, $version));
}

foreach ($unexpected as $name => $version) {
	fwrite(STDERR, sprintf("  %-40s in vendor as %s, absent from lock\n", $name, $version));
}

fwrite(STDERR, "\nRun 'composer install' and commit the resulting include/vendor tree.\n");

exit(1);
