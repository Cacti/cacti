<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Filesystem\Path;

/**
 * cacti_path_within - resolve an untrusted candidate against a trusted base
 * directory and confirm it stays inside that base (CWE-22 containment).
 *
 * This is the modern, Symfony-backed containment primitive for new path
 * handling on the develop branch. It is intended to replace the open-coded
 * "realpath($base . '/' . $name) then str_starts_with()/strpos()" idiom that
 * is repeated across the codebase and that is easy to get subtly wrong.
 *
 * Containment is enforced in two independent stages:
 *
 *   1. Lexical containment via Symfony's Filesystem\Path component. The base is
 *      canonicalised, the candidate is resolved against it with
 *      Path::makeAbsolute(), and Path::isBasePath() rejects any result that
 *      climbs out with '../' segments or that is an unrelated absolute path.
 *      Symfony Path operates purely on strings and never touches the
 *      filesystem, so this stage is cheap and deterministic.
 *
 *   2. A realpath()-based symlink guard. Because stage 1 is purely lexical, a
 *      symlinked segment under the base could still redirect the physical
 *      location outside it. Each segment beneath the base is checked with
 *      is_link() (so a symlink whose target does not exist yet is still
 *      rejected), then the deepest existing ancestor is resolved with
 *      realpath() and confirmed to remain inside the real base. A not-yet
 *      created leaf (or nested leaves) is permitted, so the helper is safe to
 *      use for a path that is about to be written.
 *
 * When the Symfony component is not autoloaded - for example a git checkout
 * whose include/vendor tree has not been populated by "composer install" -
 * stage 1 degrades gracefully to an equivalent lexical canonicalisation and
 * prefix check. Stage 2 is unchanged, so the security contract holds either
 * way; only the implementation of the lexical step differs.
 *
 * Note the deliberate argument order and return type, which differ from the
 * boolean cacti_path_is_within($candidate, $base): this helper takes the base
 * first and returns the resolved path (ready to hand to is_file()/require())
 * or false, so a caller can validate and consume in one step.
 *
 * @param string $base      The trusted base directory the result must stay in
 * @param string $candidate An untrusted path, relative to $base or absolute
 *
 * @return string|false The canonical contained path, or false when the
 *                      candidate escapes the base or is otherwise invalid
 */
function cacti_path_within(string $base, string $candidate): string|false {
	// Reject empty input and null bytes up front: a null byte truncates the
	// path for the C library behind realpath()/require(), and PHP 8 throws a
	// ValueError on one rather than failing closed.
	if ($base === '' || $candidate === '') {
		return false;
	}

	if (strpos($base, "\0") !== false || strpos($candidate, "\0") !== false) {
		return false;
	}

	// Stage 1: lexical containment. Both results are '/'-separated absolute
	// paths with '.' and '..' resolved away.
	if (class_exists(Path::class)) {
		$abs_base = str_replace('\\', '/', Path::canonicalize($base));
		$abs_cand = str_replace('\\', '/', Path::makeAbsolute($candidate, $abs_base));

		if (!Path::isBasePath($abs_base, $abs_cand)) {
			return false;
		}
	} else {
		$resolved = cacti_path_within_lexical_fallback($base, $candidate);

		if ($resolved === false) {
			return false;
		}

		[$abs_base, $abs_cand] = $resolved;
	}

	// Stage 2: symlink guard. Walk each segment beneath the base so a symlink
	// is rejected even when it points at a target that does not exist yet.
	$real_base = realpath($abs_base);

	if ($real_base === false) {
		// A base directory that cannot be resolved cannot contain anything;
		// fail closed rather than trusting the lexical result alone.
		return false;
	}

	$real_base = str_replace('\\', '/', $real_base);

	// The portion of the candidate below the base. Empty means the candidate
	// is the base directory itself, which is contained by definition.
	$relative = ltrim(substr($abs_cand, strlen($abs_base)), '/');

	if ($relative !== '') {
		$walk             = $abs_base;
		$deepest_existing = $abs_base;

		foreach (explode('/', $relative) as $segment) {
			$walk .= '/' . $segment;

			// is_link() must be tested before file_exists(): file_exists()
			// follows the link and is false for a dangling symlink, so a
			// symlink segment whose target does not exist yet would otherwise
			// be skipped and a lexically contained path accepted that resolves
			// outside the base once the target is created.
			if (is_link($walk)) {
				return false;
			}

			if (file_exists($walk)) {
				$deepest_existing = $walk;
			}
		}

		$real_target = realpath($deepest_existing);

		if ($real_target === false) {
			return false;
		}

		$real_target = str_replace('\\', '/', $real_target);

		if ($real_target !== $real_base
			&& strncmp($real_target . '/', $real_base . '/', strlen($real_base) + 1) !== 0) {
			return false;
		}
	}

	return $abs_cand;
}

/**
 * cacti_path_within_lexical_fallback - lexical containment without Symfony.
 *
 * Mirrors the stage-1 behaviour of cacti_path_within() using only core PHP, for
 * installations whose include/vendor tree has not been populated. Returns the
 * canonicalised [base, candidate] pair on success, or false when the candidate
 * is not lexically contained by the base.
 *
 * @param string $base      The trusted base directory
 * @param string $candidate An untrusted path, relative to $base or absolute
 *
 * @return array{0: string, 1: string}|false
 */
function cacti_path_within_lexical_fallback(string $base, string $candidate): array|false {
	$norm_base      = str_replace('\\', '/', $base);
	$norm_candidate = str_replace('\\', '/', $candidate);

	// Resolve the candidate against the base unless it is already absolute
	// (leading '/' on POSIX, or a drive letter on Windows), matching
	// Symfony Path::makeAbsolute().
	if ($norm_candidate[0] === '/' || preg_match('#^[a-zA-Z]:/#', $norm_candidate)) {
		$combined = $norm_candidate;
	} else {
		$combined = rtrim($norm_base, '/') . '/' . $norm_candidate;
	}

	$abs_base = cacti_path_canonicalize_lexical($norm_base);
	$abs_cand = cacti_path_canonicalize_lexical($combined);

	if ($abs_base === '' || $abs_cand === '') {
		return false;
	}

	$is_base_itself = ($abs_cand === $abs_base);
	$is_under_base  = (strncmp($abs_cand . '/', $abs_base . '/', strlen($abs_base) + 1) === 0);

	if ($is_base_itself || $is_under_base) {
		return [$abs_base, $abs_cand];
	}

	return false;
}

/**
 * cacti_path_canonicalize_lexical - resolve '.' and '..' segments in a string.
 *
 * A purely lexical canonicaliser (no filesystem access) used by the Symfony
 * degradation path. A leading '..' that would climb above the root is dropped
 * rather than retained, so it can never escape; the containment check in
 * cacti_path_within_lexical_fallback() still rejects such a candidate.
 *
 * @param string $path A '/'-separated path
 *
 * @return string The canonicalised path (without a trailing slash, except root)
 */
function cacti_path_canonicalize_lexical(string $path): string {
	$is_absolute = $path !== '' && $path[0] === '/';
	$prefix      = '';

	// Preserve a Windows drive prefix (e.g. "C:") across canonicalisation.
	if (preg_match('#^([a-zA-Z]:)/#', $path, $matches)) {
		$prefix      = $matches[1];
		$path        = substr($path, strlen($prefix));
		$is_absolute = true;
	}

	$parts = [];

	foreach (explode('/', $path) as $segment) {
		if ($segment === '' || $segment === '.') {
			continue;
		}

		if ($segment === '..') {
			if (!empty($parts) && end($parts) !== '..') {
				array_pop($parts);
			} elseif (!$is_absolute) {
				$parts[] = '..';
			}

			continue;
		}

		$parts[] = $segment;
	}

	$canonical = implode('/', $parts);

	if ($is_absolute) {
		return $prefix . '/' . $canonical;
	}

	return $prefix . $canonical;
}
