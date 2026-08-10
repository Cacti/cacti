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
 * Path containment.
 *
 * Forward-ported from 1.2.x, where these live in lib/functions.php.  They are
 * in their own file on develop so the containment rules can be covered on their
 * own; the behaviour is unchanged from the 1.2.x originals.
 */

/**
 * cacti_normalize_windows_path - folds a Windows path into a comparable form
 *
 * @param mixed $path The path to normalize
 *
 * @return string The lower cased, forward slashed path
 */
function cacti_normalize_windows_path(mixed $path) : string {
	$lower = strtolower((string) $path);

	/**
	 * Long-path prefixes.  Strip \\?\UNC\ first so the remaining \\ is
	 * preserved for UNC share comparison; then strip bare \\?\ which only
	 * wraps drive-letter paths for filesystem APIs.
	 */
	if (strpos($lower, '\\\\?\\unc\\') === 0) {
		$lower = '\\\\' . substr($lower, 8);
	} elseif (strpos($lower, '\\\\?\\') === 0) {
		$lower = substr($lower, 4);
	}

	$lower = str_replace('\\', '/', $lower);

	// drop trailing slashes except for a lone '/', the drive-root case
	if (strlen($lower) > 1) {
		$lower = rtrim($lower, '/');
	}

	return $lower;
}

/**
 * cacti_path_is_within - checks that a resolved path sits under a base directory
 *
 * @param string    $candidate The path to test
 * @param string    $base      The directory it must stay within
 * @param bool|null $windows   Force Windows comparison rules, null to detect
 *
 * @return bool True when the candidate resolves inside the base
 */
function cacti_path_is_within(string $candidate, string $base, ?bool $windows = null) : bool {
	$resolved = realpath($candidate);

	if ($resolved === false) {
		return false;
	}

	$base_resolved = realpath($base);

	if ($base_resolved === false) {
		return false;
	}

	if ($windows ?? (DIRECTORY_SEPARATOR === '\\')) {
		$resolved      = cacti_normalize_windows_path($resolved);
		$base_resolved = cacti_normalize_windows_path($base_resolved);
	}

	return strpos($resolved, $base_resolved . '/') === 0 || $resolved === $base_resolved;
}

/**
 * validate_relative_path_within - validates an untrusted relative path
 *
 * Rejects absolute paths, drive letters, empty or dot segments, and symlinked
 * segments under the base, then confirms the result resolves inside the base.
 *
 * @param mixed  $path     The untrusted relative path
 * @param string $base_dir The base directory the path must stay within
 *
 * @return mixed The validated absolute path, or false when invalid
 */
function validate_relative_path_within(mixed $path, string $base_dir) : mixed {
	if (!is_string($path) || $path === '' || strpos($path, "\0") !== false) {
		return false;
	}

	$normalized = str_replace('\\', '/', $path);

	if ($normalized === '' || $normalized[0] === '/' || preg_match('/^[a-zA-Z]:\//', $normalized)) {
		return false;
	}

	$parts = [];

	foreach (explode('/', $normalized) as $part) {
		if ($part === '' || $part === '.' || $part === '..') {
			return false;
		}

		$parts[] = $part;
	}

	$base_real = realpath($base_dir);

	if ($base_real === false) {
		return false;
	}

	$candidate = $base_real . '/' . implode('/', $parts);

	// block symlink pivots under writable base paths
	$walk = $base_real;

	foreach ($parts as $part) {
		$walk .= '/' . $part;

		if (file_exists($walk) && is_link($walk)) {
			return false;
		}
	}

	/**
	 * An entry that does not exist yet is judged by its parent directory.
	 * cacti_path_is_within() already fails closed when realpath() cannot
	 * resolve either side, so both cases share one check.
	 */
	$anchor = file_exists($candidate) ? $candidate : dirname($candidate);

	if (!cacti_path_is_within($anchor, $base_real)) {
		return false;
	}

	return $candidate;
}

/**
 * plugin_validate_repository_url - constrains the plugin repository API base
 *
 * The repository URL drives every plugin fetch, so an arbitrary value lets an
 * administrator point plugin distribution at attacker controlled infrastructure.
 *
 * @param mixed $url The configured repository URL
 *
 * @return string The trimmed URL, or an empty string when unusable
 */
function plugin_validate_repository_url(mixed $url) : string {
	$url = trim((string) $url, "/\n\r ");

	if ($url === '') {
		return '';
	}

	$parts = parse_url($url);

	if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
		return '';
	}

	if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
		return '';
	}

	return $url;
}
