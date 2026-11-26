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
 * Create a private 0700 per-run working directory for RRD float operations.
 *
 * @param string|null $base_dir Optional parent directory (defaults to sys_get_temp_dir())
 *
 * @return string|false Path to the created directory, or false on failure
 */
function float_rrd_create_private_temp_dir(?string $base_dir = null) : string|false {
	$root = ($base_dir !== null && $base_dir !== '') ? $base_dir : sys_get_temp_dir();

	if (!is_dir($root) || is_link($root)) {
		return false;
	}

	$previous_umask = umask(0077);

	try {
		$dir = @tempnam($root, 'cacti_float_');

		if ($dir === false || !@unlink($dir) || !@mkdir($dir, 0700)) {
			return false;
		}

		if (!@chmod($dir, 0700)) {
			@rmdir($dir);

			return false;
		}

		return $dir;
	} finally {
		umask($previous_umask);
	}
}

/**
 * Build an unpredictable XML dump file path inside the private working directory.
 *
 * @param string $temp_dir      Private per-run working directory
 * @param int    $local_data_id Local data source ID
 *
 * @return string
 */
function float_rrd_build_xml_path(string $temp_dir, int $local_data_id) : string {
	return rtrim($temp_dir, '/\\') . '/' . $local_data_id . '_' . bin2hex(random_bytes(8)) . '.xml';
}

/**
 * Open a regular file inside the private working directory with 0600 permissions.
 *
 * Refuses to follow symlinks and fails closed if permissions cannot be restricted.
 *
 * @param string $path Target file path
 * @param string $mode File open mode (for example 'wb' or 'ab')
 *
 * @return resource|false
 */
function float_rrd_open_private_file(string $path, string $mode) {
	if ($path === '' || is_link($path)) {
		return false;
	}

	$previous_umask = umask(0077);

	try {
		$handle = @fopen($path, $mode);

		if (!is_resource($handle)) {
			return false;
		}

		if (!@chmod($path, 0600)) {
			fclose($handle);
			@unlink($path);

			return false;
		}

		return $handle;
	} finally {
		umask($previous_umask);
	}
}

/**
 * Remove temporary XML files and optionally remove the private working directory.
 *
 * @param string $temp_dir           Private per-run working directory
 * @param bool   $preserve_debug_log Keep cacti_float_rrdfiles.log and its directory when true
 *
 * @return void
 */
function float_rrd_cleanup_temp_dir(string $temp_dir, bool $preserve_debug_log = false) : void {
	if ($temp_dir === '' || !is_dir($temp_dir) || is_link($temp_dir)) {
		return;
	}

	$iterator = new DirectoryIterator($temp_dir);

	foreach ($iterator as $entry) {
		if ($entry->isDot()) {
			continue;
		}

		if ($preserve_debug_log && $entry->getFilename() === 'cacti_float_rrdfiles.log') {
			continue;
		}

		if ($entry->isLink() || $entry->isFile()) {
			@unlink($entry->getPathname());
		}
	}

	if (!$preserve_debug_log) {
		@rmdir($temp_dir);
	}
}
