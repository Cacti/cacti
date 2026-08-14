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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

final class CactiMd5FileFinder {
	public function findHashes(string $basePath, string $ignoreRegex, array $excludedDirectories = [], ?Closure $debug = null) : array {
		if (!is_dir($basePath) || !is_readable($basePath)) {
			throw new DirectoryNotFoundException("The directory '$basePath' does not exist or is not readable.");
		}

		$finder = (new Finder())
			->files()
			->ignoreDotFiles(false)
			->ignoreVCS(false)
			->ignoreUnreadableDirs()
			->exclude($excludedDirectories)
			->in($basePath);

		$hashes = [];

		foreach ($finder as $file) {
			$relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

			if (preg_match($ignoreRegex, '/' . $relativePath) !== 0) {
				if ($debug !== null) {
					$debug('[                         Ignored] ' . $file->getFilename());
				}

				continue;
			}

			$md5 = @md5_file($file->getPathname());

			if ($debug !== null) {
				$debug("[$md5] " . $file->getFilename());
			}

			$hashes[$relativePath] = $md5;
		}

		uksort($hashes, self::compareManifestPaths(...));

		return $hashes;
	}

	private static function compareManifestPaths(string $left, string $right) : int {
		$leftParts  = explode('/', $left);
		$rightParts = explode('/', $right);
		$partCount  = min(count($leftParts), count($rightParts));

		for ($index = 0; $index < $partCount; $index++) {
			$leftIsFile  = $index === count($leftParts) - 1;
			$rightIsFile = $index === count($rightParts) - 1;

			if ($leftIsFile !== $rightIsFile) {
				return $leftIsFile ? -1 : 1;
			}

			$comparison = strcmp($leftParts[$index], $rightParts[$index]);

			if ($comparison !== 0) {
				return $comparison;
			}
		}

		return count($leftParts) <=> count($rightParts);
	}
}
