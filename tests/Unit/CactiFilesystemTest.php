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

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiFilesystem.php';

test('CactiFilesystem wraps Symfony filesystem operations', function () {
	$filesystem = new CactiFilesystem();
	$dir        = sys_get_temp_dir() . '/cacti-filesystem-' . bin2hex(random_bytes(4));
	$file       = CactiFilesystem::join($dir, 'nested', 'settings.txt');
	$copy       = CactiFilesystem::join($dir, 'nested', 'settings.copy');

	try {
		$filesystem->ensureDirectory(dirname($file));
		$filesystem->writeFile($file, 'alpha');
		$filesystem->appendFile($file, "\nbeta");
		$filesystem->copyFile($file, $copy);

		expect($filesystem->has([$file, $copy]))->toBeTrue();
		expect($filesystem->read($file))->toBe("alpha\nbeta");
		expect(CactiFilesystem::canonicalize(CactiFilesystem::join($dir, 'nested', '..', 'nested', 'settings.txt')))->toBe($file);
	} finally {
		$filesystem->delete($dir);
	}
});

test('CactiFilesystem read throws when the file is missing', function () {
	$filesystem = new CactiFilesystem();
	$missing    = sys_get_temp_dir() . '/cacti-filesystem-missing-' . bin2hex(random_bytes(4));

	expect(fn () => $filesystem->read($missing))
		->toThrow(RuntimeException::class);
});

test('CactiFilesystem read rejects a path containing a null byte', function () {
	$filesystem = new CactiFilesystem();

	expect(fn () => $filesystem->read("/tmp/foo\0bar"))
		->toThrow(RuntimeException::class, 'null byte');
});

test('CactiFilesystem accepts an injected Symfony filesystem', function () {
	$injected   = new Symfony\Component\Filesystem\Filesystem();
	$filesystem = new CactiFilesystem($injected);
	$dir        = sys_get_temp_dir() . '/cacti-fs-inject-' . bin2hex(random_bytes(4));

	try {
		$filesystem->ensureDirectory($dir);

		expect($filesystem->has($dir))->toBeTrue();
	} finally {
		$filesystem->delete($dir);
	}
});

test('CactiFilesystem instance moves, renames and reports absolute paths', function () {
	$filesystem = new CactiFilesystem();
	$dir        = sys_get_temp_dir() . '/cacti-fs-move-' . bin2hex(random_bytes(4));
	$origin     = CactiFilesystem::join($dir, 'origin.txt');
	$target     = CactiFilesystem::join($dir, 'target.txt');

	try {
		$filesystem->ensureDirectory($dir);
		$filesystem->writeFile($origin, 'payload');
		$filesystem->move($origin, $target);

		expect($filesystem->has($origin))->toBeFalse()
			->and($filesystem->read($target))->toBe('payload');

		$temp = $filesystem->temporaryName($dir, 'tmp');
		expect($filesystem->has($temp))->toBeTrue();

		expect($filesystem->isAbsolute($target))->toBeTrue()
			->and($filesystem->isAbsolute('relative/path'))->toBeFalse();
	} finally {
		$filesystem->delete($dir);
	}
});

test('CactiFilesystem is a readonly value wrapper', function () {
	$property = (new ReflectionClass(CactiFilesystem::class))->getProperty('filesystem');
	expect($property->isReadOnly())->toBeTrue();
});
