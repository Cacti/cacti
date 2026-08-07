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
use Symfony\Component\Filesystem\Path;

/**
 * Cacti wrapper for Symfony Filesystem and Path.
 *
 * Symfony's component gives us portable filesystem operations and path
 * manipulation without turning Cacti into a Symfony application. Static methods
 * keep procedural callers simple; the instance API lets future services and
 * commands receive a filesystem dependency in their constructors.
 */
class CactiFilesystem {
	private readonly Filesystem $filesystem;

	public function __construct(?Filesystem $filesystem = null) {
		$this->filesystem = $filesystem ?? new Filesystem();
	}

	public static function default(): self {
		return new self();
	}

	public static function exists(string|array $files): bool {
		return self::default()->has($files);
	}

	public static function mkdir(string|array $dirs, int $mode = 0777): void {
		self::default()->ensureDirectory($dirs, $mode);
	}

	public static function remove(string|array $files): void {
		self::default()->delete($files);
	}

	public static function dumpFile(string $filename, string $content): void {
		self::default()->writeFile($filename, $content);
	}

	public static function appendToFile(string $filename, string $content, bool $lock = false): void {
		self::default()->appendFile($filename, $content, $lock);
	}

	public static function readFile(string $filename): string {
		return self::default()->read($filename);
	}

	public static function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void {
		self::default()->copyFile($originFile, $targetFile, $overwriteNewerFiles);
	}

	public static function rename(string $origin, string $target, bool $overwrite = false): void {
		self::default()->move($origin, $target, $overwrite);
	}

	public static function tempnam(string $dir, string $prefix, string $suffix = ''): string {
		return self::default()->temporaryName($dir, $prefix, $suffix);
	}

	public static function join(string ...$parts): string {
		return Path::join(...$parts);
	}

	public static function canonicalize(string $path): string {
		return Path::canonicalize($path);
	}

	public static function isAbsolutePath(string $path): bool {
		return Path::isAbsolute($path);
	}

	public function has(string|array $files): bool {
		$this->rejectNullBytes($files, 'check');

		return $this->filesystem->exists($files);
	}

	public function ensureDirectory(string|array $dirs, int $mode = 0777): void {
		$this->rejectNullBytes($dirs, 'create');

		$this->filesystem->mkdir($dirs, $mode);
	}

	public function delete(string|array $files): void {
		$this->rejectNullBytes($files, 'delete');

		$this->filesystem->remove($files);
	}

	public function writeFile(string $filename, string $content): void {
		$this->rejectNullBytes($filename, 'write');

		$this->filesystem->dumpFile($filename, $content);
	}

	public function appendFile(string $filename, string $content, bool $lock = false): void {
		$this->rejectNullBytes($filename, 'append');

		$this->filesystem->appendToFile($filename, $content, $lock);
	}

	public function read(string $filename): string {
		// file_get_contents() throws a ValueError on a null byte in PHP 8+,
		// which would escape this method's RuntimeException contract.
		$this->rejectNullBytes($filename, 'read');

		error_clear_last();
		$contents = @file_get_contents($filename);

		if ($contents === false) {
			$error  = error_get_last();
			$reason = is_array($error) ? ($error['message'] ?? 'unknown error') : 'unknown error';

			throw new RuntimeException("Unable to read file $filename: $reason");
		}

		return $contents;
	}

	public function copyFile(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void {
		$this->rejectNullBytes([$originFile, $targetFile], 'copy');

		$this->filesystem->copy($originFile, $targetFile, $overwriteNewerFiles);
	}

	public function move(string $origin, string $target, bool $overwrite = false): void {
		$this->rejectNullBytes([$origin, $target], 'move');

		$this->filesystem->rename($origin, $target, $overwrite);
	}

	public function temporaryName(string $dir, string $prefix, string $suffix = ''): string {
		$this->rejectNullBytes([$dir, $prefix, $suffix], 'create temporary file');

		return $this->filesystem->tempnam($dir, $prefix, $suffix);
	}

	public function isAbsolute(string $path): bool {
		$this->rejectNullBytes($path, 'inspect');

		return $this->filesystem->isAbsolutePath($path);
	}

	// PHP 8's filesystem functions throw a ValueError on a null byte instead of
	// failing gracefully, which would escape this class's RuntimeException
	// contract. Reject null bytes up front so every entry point fails the same way.
	private function rejectNullBytes(string|array $paths, string $action): void {
		foreach ((array) $paths as $path) {
			if (str_contains($path, "\0")) {
				// Do not interpolate the raw path here: the null byte would
				// truncate or garble the message in logs.
				throw new RuntimeException("Unable to $action: path contains a null byte");
			}
		}
	}
}
