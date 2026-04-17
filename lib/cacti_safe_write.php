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

/**
 * Safe file-write gateway for package imports and template installs.
 *
 * Eliminates the path-traversal vulnerability class by enforcing that
 * every written file resolves to one of the allowed base directories
 * after symlink resolution. Replaces direct fopen/fwrite on
 * user-influenced paths throughout the import subsystem.
 * @param mixed $relative_name
 * @param mixed $data
 * @param mixed $allowed_bases
 * @param mixed $mode
 */

/**
 * Write data to a file, enforcing that the resolved path is inside
 * one of the allowed directories.
 *
 * @param string $relative_name Relative filename (e.g., 'scripts/ss_net_snmp_disk_io.php')
 * @param string $data          File contents to write
 * @param array  $allowed_bases Absolute paths that the file must resolve under
 *                              (e.g., [CACTI_PATH_BASE . '/scripts', CACTI_PATH_BASE . '/resource'])
 * @param int    $mode          File permissions (default 0644)
 *
 * @return string|false The resolved path on success, false on rejection
 */
function cacti_safe_write($relative_name, $data, $allowed_bases, $mode = 0644) {
	// Reject empty or whitespace-only names
	if (empty(trim($relative_name))) {
		cacti_log('ERROR: cacti_safe_write: empty filename rejected', false, 'IMPORT');

		return false;
	}

	// Reject absolute paths
	if ($relative_name[0] === '/' || $relative_name[0] === '\\' || preg_match('/^[A-Za-z]:/', $relative_name)) {
		cacti_log("ERROR: cacti_safe_write: absolute path rejected: $relative_name", false, 'IMPORT');

		return false;
	}

	// Normalize separators
	$normalized = str_replace('\\', '/', $relative_name);

	// Reject path-traversal segments
	if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
		cacti_log("ERROR: cacti_safe_write: traversal rejected: $relative_name", false, 'IMPORT');

		return false;
	}

	$matched_base = false;

	foreach ($allowed_bases as $base) {
		$real_base = realpath($base);

		if ($real_base === false) {
			continue;
		}

		// Build the candidate path
		$candidate     = $real_base . '/' . basename($relative_name);
		$candidate_dir = dirname($candidate);

		// Ensure the target directory exists or can be created
		if (!is_dir($candidate_dir)) {
			@mkdir($candidate_dir, 0755, true);
		}

		$real_dir = realpath($candidate_dir);

		if ($real_dir === false) {
			continue;
		}

		// Verify resolved directory is under the allowed base
		$sep        = DIRECTORY_SEPARATOR;
		$check_base = $real_base . $sep;

		if (DIRECTORY_SEPARATOR === '\\') {
			$real_dir   = strtolower(str_replace('\\', '/', $real_dir));
			$check_base = strtolower(str_replace('\\', '/', $check_base));
		}

		if (strpos($real_dir . '/', $check_base) === 0 || $real_dir === $real_base) {
			$matched_base = $real_base;

			break;
		}
	}

	if ($matched_base === false) {
		cacti_log("ERROR: cacti_safe_write: path not inside any allowed base: $relative_name", false, 'IMPORT');

		return false;
	}

	// Atomic write: write to temp, rename into place
	$final_path = $matched_base . '/' . basename($relative_name);
	$tmp_path   = $final_path . '.cacti_tmp_' . getmypid();

	$fp = @fopen($tmp_path, 'wb');

	if ($fp === false) {
		cacti_log("ERROR: cacti_safe_write: failed to open temp file: $tmp_path", false, 'IMPORT');

		return false;
	}

	$written = fwrite($fp, $data);
	fclose($fp);

	if ($written === false || $written !== strlen($data)) {
		@unlink($tmp_path);
		cacti_log("ERROR: cacti_safe_write: incomplete write to $tmp_path", false, 'IMPORT');

		return false;
	}

	@chmod($tmp_path, $mode);

	if (!@rename($tmp_path, $final_path)) {
		@unlink($tmp_path);
		cacti_log("ERROR: cacti_safe_write: rename failed: $tmp_path -> $final_path", false, 'IMPORT');

		return false;
	}

	// Final paranoid check: verify the written file is where we expect
	$real_final = realpath($final_path);

	if ($real_final === false || strpos($real_final, realpath($matched_base) . DIRECTORY_SEPARATOR) !== 0) {
		@unlink($final_path);
		cacti_log("ERROR: cacti_safe_write: post-write realpath check failed: $final_path", false, 'IMPORT');

		return false;
	}

	return $real_final;
}
