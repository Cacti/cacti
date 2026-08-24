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

use Symfony\Component\Filesystem\Path;

final class CactiPath {
	public static function resolveWithinBase(string $basePath, string $candidatePath, bool $allowMissingLeaf = false) : string|false {
		// Reject null bytes before any filesystem call. PHP 8.0+ throws ValueError
		// when passing a string containing \0 to realpath(), so a traversal attempt
		// would become a fatal instead of a refusal.
		if (str_contains($basePath, "\0") || str_contains($candidatePath, "\0")) {
			return false;
		}

		$resolvedBase      = realpath($basePath);
		$resolvedCandidate = realpath($candidatePath);

		if ($resolvedCandidate === false && $allowMissingLeaf) {
			$parent = realpath(dirname($candidatePath));
			$leaf   = basename($candidatePath);

			if ($parent !== false && $leaf !== '' && $leaf !== '.' && $leaf !== '..') {
				$resolvedCandidate = Path::join($parent, $leaf);
			}
		}

		if ($resolvedBase === false || $resolvedCandidate === false ||
			!Path::isBasePath($resolvedBase, $resolvedCandidate)) {
			return false;
		}

		return $resolvedCandidate;
	}

	public static function makeRelativeIfWithinBase(string $path, string $basePath) : string {
		if (!Path::isBasePath($basePath, $path)) {
			return $path;
		}

		return Path::makeRelative($path, $basePath);
	}
}
