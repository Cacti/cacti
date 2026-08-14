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

require_once(__DIR__ . '/../../lib/CactiFilesystem.php');

beforeEach(function () {
	$this->directory = sys_get_temp_dir() . '/cacti-filesystem-' . bin2hex(random_bytes(8));
	$this->filesystem = new Filesystem();
	$this->filesystem->mkdir($this->directory);
});

afterEach(function () {
	$this->filesystem->remove($this->directory);
});

test('writeFile creates a file with the requested contents', function () {
	$filename = $this->directory . '/nested/config.php';
	$contents = '<?php return true;' . PHP_EOL;

	$filesystem = new CactiFilesystem();
	$filesystem->writeFile($filename, $contents);

	expect(file_get_contents($filename))->toBe($contents);
});

test('writeFile replaces existing contents', function () {
	$filename = $this->directory . '/config.php';
	file_put_contents($filename, 'old');

	$filesystem = new CactiFilesystem();
	$filesystem->writeFile($filename, 'new');

	expect(file_get_contents($filename))->toBe('new');
});

test('writeFile delegates to Symfony Filesystem dumpFile', function () {
	$delegate = new class extends Filesystem {
		public array $writes = [];

		public function dumpFile(string $filename, $content) : void {
			$this->writes[] = [$filename, $content];
		}
	};

	$filesystem = new CactiFilesystem($delegate);
	$filesystem->writeFile('/tmp/cacti-config.php', 'contents');

	expect($delegate->writes)->toBe([['/tmp/cacti-config.php', 'contents']]);
});
