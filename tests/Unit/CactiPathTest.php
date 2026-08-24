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

require_once(CACTI_PATH_LIBRARY . '/CactiPath.php');

beforeEach(function () {
	$this->filesystem = new Filesystem();
	$this->directory  = sys_get_temp_dir() . '/cacti-path-' . bin2hex(random_bytes(8));
	$this->base       = $this->directory . '/base';
	$this->sibling    = $this->directory . '/base-sibling';
	$this->filesystem->mkdir([$this->base . '/nested', $this->sibling]);
	$this->filesystem->touch([$this->base . '/nested/file.php', $this->sibling . '/file.php']);
});

afterEach(function () {
	$this->filesystem->remove($this->directory);
});

test('resolveWithinBase returns a resolved file inside the base', function () {
	$result = CactiPath::resolveWithinBase($this->base, $this->base . '/nested/../nested/file.php');

	expect($result)->toBe(realpath($this->base . '/nested/file.php'));
});

test('resolveWithinBase allows the base itself', function () {
	expect(CactiPath::resolveWithinBase($this->base, $this->base))->toBe(realpath($this->base));
});

test('resolveWithinBase rejects a sibling with the same prefix', function () {
	expect(CactiPath::resolveWithinBase($this->base, $this->sibling . '/file.php'))->toBeFalse();
});

test('resolveWithinBase rejects a missing candidate by default', function () {
	expect(CactiPath::resolveWithinBase($this->base, $this->base . '/nested/new.php'))->toBeFalse();
});

test('resolveWithinBase accepts a missing leaf under a resolved parent when requested', function () {
	$result = CactiPath::resolveWithinBase($this->base, $this->base . '/nested/new.php', true);

	expect($result)->toBe(realpath($this->base . '/nested') . '/new.php');
});

test('resolveWithinBase rejects a missing leaf under an outside parent', function () {
	expect(CactiPath::resolveWithinBase($this->base, $this->sibling . '/new.php', true))->toBeFalse();
});

test('resolveWithinBase rejects a missing base', function () {
	expect(CactiPath::resolveWithinBase($this->directory . '/missing', $this->base))->toBeFalse();
});

test('resolveWithinBase rejects an outside symlink target', function () {
	if (DIRECTORY_SEPARATOR === '\\') {
		$this->markTestSkipped('Symlink creation is not consistently available on Windows.');
	}

	symlink($this->sibling, $this->base . '/linked');

	expect(CactiPath::resolveWithinBase($this->base, $this->base . '/linked/file.php'))->toBeFalse();
});

test('makeRelativeIfWithinBase returns a relative path for an inside candidate', function () {
	expect(CactiPath::makeRelativeIfWithinBase($this->base . '/nested/file.php', $this->base))
		->toBe('nested/file.php');
});

test('makeRelativeIfWithinBase preserves an outside path', function () {
	expect(CactiPath::makeRelativeIfWithinBase($this->sibling . '/file.php', $this->base))
		->toBe($this->sibling . '/file.php');
});

test('resolveWithinBase refuses a null byte instead of raising a ValueError', function () {
	expect(CactiPath::resolveWithinBase($this->base, $this->base . "/file.php\0.txt"))->toBeFalse();
	expect(CactiPath::resolveWithinBase($this->base . "\0", $this->base . '/nested/file.php'))->toBeFalse();
	expect(CactiPath::resolveWithinBase($this->base, $this->base . "/missing\0", true))->toBeFalse();
});
