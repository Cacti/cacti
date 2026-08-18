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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

require_once(CACTI_PATH_LIBRARY . '/CactiMd5FileFinder.php');

beforeEach(function () {
	$this->filesystem = new Filesystem();
	$this->directory  = sys_get_temp_dir() . '/cacti-finder-' . bin2hex(random_bytes(8));
	$this->outside    = $this->directory . '-sibling';
	$this->filesystem->mkdir([
		$this->directory . '/nested',
		$this->directory . '/nested/deeper',
		$this->directory . '/cache',
		$this->directory . '/nested/plugins',
		$this->outside,
	]);
	$this->filesystem->dumpFile($this->directory . '/visible.php', 'visible');
	$this->filesystem->dumpFile($this->directory . '/.env', 'hidden');
	$this->filesystem->dumpFile($this->directory . '/ignored.log', 'log');
	$this->filesystem->dumpFile($this->directory . '/nested/file.php', 'nested');
	$this->filesystem->dumpFile($this->directory . '/nested/deeper/file.php', 'deeper');
	$this->filesystem->dumpFile($this->directory . '/cache/cache.php', 'cache');
	$this->filesystem->dumpFile($this->directory . '/nested/plugins/plugin.php', 'plugin');
	$this->filesystem->dumpFile($this->outside . '/outside.php', 'outside');
});

afterEach(function () {
	$this->filesystem->remove([$this->directory, $this->outside]);
});

function cactiFinderIgnoreRegex() : string {
	return '~((\.log$)|(/\.htaccess$))~';
}

test('findHashes returns stable relative paths and hashes', function () {
	$finder = new CactiMd5FileFinder();
	$hashes = $finder->findHashes($this->directory, cactiFinderIgnoreRegex(), ['cache', 'plugins']);

	expect(array_keys($hashes))->toBe(['.env', 'visible.php', 'nested/file.php', 'nested/deeper/file.php'])
		->and($hashes['visible.php'])->toBe(md5('visible'))
		->and($hashes['nested/file.php'])->toBe(md5('nested'))
		->and($hashes['nested/deeper/file.php'])->toBe(md5('deeper'));
});

test('findHashes preserves hidden files when they are not ignored', function () {
	$finder = new CactiMd5FileFinder();
	$hashes = $finder->findHashes($this->directory, cactiFinderIgnoreRegex(), ['cache', 'plugins']);

	expect($hashes)->toHaveKey('.env');
});

test('findHashes reports filtered files through the debug callback', function () {
	$messages = [];
	$finder   = new CactiMd5FileFinder();
	$finder->findHashes(
		$this->directory,
		cactiFinderIgnoreRegex(),
		['cache', 'plugins'],
		static function (string $message) use (&$messages) : void {
			$messages[] = $message;
		}
	);

	expect($messages)->toContain('[                         Ignored] ignored.log')
		->and(implode("\n", $messages))->toContain(md5('visible'));
});

test('findHashes does not follow a sibling-prefix symlink target', function () {
	if (DIRECTORY_SEPARATOR === '\\') {
		$this->markTestSkipped('Symlink creation is not consistently available on Windows.');
	}

	if (!@symlink($this->outside, $this->directory . '/outside-link')) {
		$this->markTestSkipped('Symlink creation is not permitted in this environment.');
	}

	$finder = new CactiMd5FileFinder();
	$hashes = $finder->findHashes($this->directory, cactiFinderIgnoreRegex(), ['cache', 'plugins']);

	expect($hashes)->not->toHaveKey('outside-link/outside.php');
});

test('findHashes rejects a missing root directory', function () {
	$finder = new CactiMd5FileFinder();

	expect(fn () => $finder->findHashes($this->directory . '/missing', cactiFinderIgnoreRegex()))
		->toThrow(DirectoryNotFoundException::class);
});

test('findHashes rejects an unreadable root directory', function () {
	if (DIRECTORY_SEPARATOR === '\\') {
		$this->markTestSkipped('POSIX directory permissions are not available on Windows.');
	}

	chmod($this->directory, 0000);
	clearstatcache(true, $this->directory);

	if (is_readable($this->directory)) {
		chmod($this->directory, 0700);
		$this->markTestSkipped('The test process can bypass directory permissions.');
	}

	try {
		$finder = new CactiMd5FileFinder();

		expect(fn () => $finder->findHashes($this->directory, cactiFinderIgnoreRegex()))
			->toThrow(DirectoryNotFoundException::class);
	} finally {
		chmod($this->directory, 0700);
	}
});

test('findHashes rejects an ignore pattern that is not a valid regex', function () {
	$finder = new CactiMd5FileFinder();

	/* an unchecked preg_match() would return false here and ignore every file */
	expect(fn () => $finder->findHashes($this->directory, '/unterminated'))
		->toThrow(InvalidArgumentException::class);
});
