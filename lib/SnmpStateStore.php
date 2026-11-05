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
 * Private, bounded state storage for the legacy Net-SNMP disk scripts.
 *
 * This class lives outside the Script Server's allowed roots. Its methods
 * therefore cannot be selected as request callables.
 */
final class CactiSnmpStateStore {
	private const MAX_STATE_BYTES = 1048576;

	/**
	 * Validate a real-time request identifier before using it in a state file.
	 *
	 * @param mixed $poller_id The real-time request identifier
	 *
	 * @return string|false The safe identifier, or false when invalid
	 */
	public static function realtimeId(mixed $poller_id) : string|false {
		if (!is_string($poller_id) && !is_int($poller_id)) {
			return false;
		}

		$poller_id = (string) $poller_id;

		return preg_match('/^(?:[1-9][0-9]*|[A-Fa-f0-9]{64})$/D', $poller_id) ? $poller_id : false;
	}

	/**
	 * Create or validate a state directory private to the poller user.
	 *
	 * @param string $dir The state directory
	 *
	 * @return string|false The trusted directory, or false
	 */
	public static function directory(string $dir) : string|false {
		return self::trustedDirectory($dir, true);
	}

	/**
	 * Read a prior counter state only from a bounded, owned regular file.
	 *
	 * @param string $file The state file
	 *
	 * @return array|false Decoded state, or false when unusable
	 */
	public static function read(string $file) : array|false {
		if (self::trustedDirectory(dirname($file), false) === false || is_link($file) || !is_file($file)) {
			return false;
		}

		if (!self::ownedByCurrentUser($file)) {
			return false;
		}

		$size = filesize($file);

		if ($size === false || $size > self::MAX_STATE_BYTES) {
			return false;
		}

		$data = file_get_contents($file, false, null, 0, self::MAX_STATE_BYTES + 1);

		if ($data === false || strlen($data) > self::MAX_STATE_BYTES) {
			return false;
		}

		$state = json_decode($data, true);

		return is_array($state) ? $state : false;
	}

	/**
	 * Atomically replace a counter state inside its private directory.
	 *
	 * @param string $dir  The state directory
	 * @param string $file The state file
	 * @param string $data Encoded state
	 *
	 * @return bool True when the state was replaced
	 */
	public static function write(string $dir, string $file, string $data) : bool {
		if (strlen($data) > self::MAX_STATE_BYTES) {
			return false;
		}

		$trusted_dir = self::trustedDirectory($dir, false);
		$real_dir    = realpath($dir);

		if ($trusted_dir === false || $real_dir === false || realpath(dirname($file)) !== $real_dir) {
			return false;
		}

		if (file_exists($file) && !is_link($file)) {
			if (!is_file($file) || !self::ownedByCurrentUser($file)) {
				return false;
			}
		}

		$temp = @tempnam($real_dir, 'state');

		// tempnam() falls back to the system directory when $dir is unusable.
		if ($temp === false || realpath(dirname($temp)) !== $real_dir) {
			if ($temp !== false) {
				@unlink($temp);
			}

			return false;
		}

		$written = file_put_contents($temp, $data, LOCK_EX);

		if ($written !== strlen($data) || !@chmod($temp, 0600)) {
			@unlink($temp);

			return false;
		}

		$replaced = @rename($temp, $file);

		// Windows rename() cannot replace an existing file. The directory is
		// private, so removing the validated target does not expose a symlink race.
		if (!$replaced && DIRECTORY_SEPARATOR === '\\' && (is_file($file) || is_link($file))) {
			$replaced = @unlink($file) && @rename($temp, $file);
		}

		if (!$replaced) {
			@unlink($temp);

			return false;
		}

		return true;
	}

	/**
	 * Create or validate both levels of the private state directory.
	 *
	 * @param string $dir    The leaf directory
	 * @param bool   $create Create missing directory levels
	 *
	 * @return string|false The trusted directory, or false
	 */
	private static function trustedDirectory(string $dir, bool $create) : string|false {
		foreach ([dirname($dir), $dir] as $path) {
			if ($create && !is_link($path) && !file_exists($path)) {
				@mkdir($path, 0700);
			}

			clearstatcache(true, $path);

			if (is_link($path) || !is_dir($path)) {
				cacti_log('WARNING: Script state directory is not a directory', false, 'POLLER');

				return false;
			}

			if (!self::ownedByCurrentUser($path)) {
				cacti_log('WARNING: Script state directory is owned by another user', false, 'POLLER');

				return false;
			}

			$permissions = fileperms($path);

			if ($permissions === false) {
				cacti_log('WARNING: Script state directory permissions could not be checked', false, 'POLLER');

				return false;
			}

			if (($permissions & 0077) !== 0) {
				@chmod($path, 0700);
				clearstatcache(true, $path);
				$permissions = fileperms($path);

				if ($permissions === false || ($permissions & 0077) !== 0) {
					cacti_log('WARNING: Script state directory is open to other users', false, 'POLLER');

					return false;
				}
			}
		}

		return $dir;
	}

	/**
	 * Check ownership where the platform exposes an effective user id.
	 *
	 * @param string $path The path to check
	 *
	 * @return bool True when ownership is acceptable
	 */
	private static function ownedByCurrentUser(string $path) : bool {
		if (!function_exists('posix_geteuid')) {
			return true;
		}

		$owner = fileowner($path);

		return $owner !== false && $owner === posix_geteuid();
	}
}
