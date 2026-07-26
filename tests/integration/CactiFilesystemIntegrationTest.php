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

require_once dirname(__DIR__, 2) . '/lib/CactiFilesystem.php';

it('drives the static filesystem API against a real temp tree', function () {
	$dir     = sys_get_temp_dir() . '/cacti-fs-int-' . bin2hex(random_bytes(4));
	$file    = CactiFilesystem::join($dir, 'nested', 'config.txt');
	$copy    = CactiFilesystem::join($dir, 'nested', 'config.copy');
	$renamed = CactiFilesystem::join($dir, 'nested', 'config.moved');

	try {
		CactiFilesystem::mkdir(dirname($file));
		expect(CactiFilesystem::exists($dir))->toBeTrue();

		CactiFilesystem::dumpFile($file, 'alpha');
		CactiFilesystem::appendToFile($file, "\nbeta");
		expect(CactiFilesystem::readFile($file))->toBe("alpha\nbeta");

		CactiFilesystem::copy($file, $copy);
		expect(CactiFilesystem::exists([$file, $copy]))->toBeTrue();

		CactiFilesystem::rename($copy, $renamed);
		expect(CactiFilesystem::exists($copy))->toBeFalse()
			->and(CactiFilesystem::exists($renamed))->toBeTrue();

		$temp = CactiFilesystem::tempnam($dir, 'seed');
		expect(CactiFilesystem::exists($temp))->toBeTrue();

		expect(CactiFilesystem::isAbsolutePath($file))->toBeTrue()
			->and(CactiFilesystem::isAbsolutePath('nested/config.txt'))->toBeFalse();
	} finally {
		CactiFilesystem::remove($dir);
	}

	expect(CactiFilesystem::exists($dir))->toBeFalse();
});

it('normalizes redundant .. segments lexically', function () {
	$dir       = sys_get_temp_dir() . '/cacti-fs-trav-' . bin2hex(random_bytes(4));
	$file      = CactiFilesystem::join($dir, 'nested', 'settings.txt');
	$traversal = CactiFilesystem::join($dir, 'nested', 'sub', '..', '..', 'nested', 'settings.txt');

	try {
		CactiFilesystem::mkdir(dirname($file));
		CactiFilesystem::dumpFile($file, 'guarded');

		// canonicalize() normalizes the .. segments lexically. It is not a
		// containment boundary: a path with leading ../.. would resolve outside
		// the tree, so callers must not rely on it to sandbox paths.
		expect(CactiFilesystem::canonicalize($traversal))->toBe($file)
			->and(CactiFilesystem::readFile(CactiFilesystem::canonicalize($traversal)))->toBe('guarded');
	} finally {
		CactiFilesystem::remove($dir);
	}
});
