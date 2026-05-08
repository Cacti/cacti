<?php
declare(strict_types = 1);
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

namespace Cacti\Filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CactiFilesystem {
	private static ?Filesystem $filesystem = null;

	private static function getFilesystem(): Filesystem {
		if (self::$filesystem === null) {
			self::$filesystem = new Filesystem();
		}
		return self::$filesystem;
	}

	/**
	 * Remove a file or directory.
	 */
	public static function remove(string|iterable $files): void {
		try {
			self::getFilesystem()->remove($files);
		} catch (IOExceptionInterface $exception) {
			cacti_log("ERROR: Filesystem remove failed: " . $exception->getMessage(), false, 'FILESYSTEM');
		}
	}

	/**
	 * Check if a file or directory exists.
	 */
	public static function exists(string|iterable $files): bool {
		return self::getFilesystem()->exists($files);
	}

	/**
	 * Create a directory.
	 */
	public static function mkdir(string|iterable $dirs, int $mode = 0777): void {
		try {
			self::getFilesystem()->mkdir($dirs, $mode);
		} catch (IOExceptionInterface $exception) {
			cacti_log("ERROR: Filesystem mkdir failed: " . $exception->getMessage(), false, 'FILESYSTEM');
		}
	}

	/**
	 * Write content to a file.
	 */
	public static function dumpFile(string $filename, string $content): void {
		try {
			self::getFilesystem()->dumpFile($filename, $content);
		} catch (IOExceptionInterface $exception) {
			cacti_log("ERROR: Filesystem dumpFile failed: " . $exception->getMessage(), false, 'FILESYSTEM');
		}
	}
}
