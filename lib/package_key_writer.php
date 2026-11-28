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
 * Ensure the package PKI directory exists with owner-only permissions when created.
 *
 * @param string $directory Target PKI directory path
 *
 * @return bool
 */
function package_key_ensure_directory(string $directory) : bool {
	if ($directory === '' || is_link($directory)) {
		return false;
	}

	if (is_dir($directory)) {
		return true;
	}

	$previous_umask = umask(0077);

	try {
		if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
			return false;
		}

		return @chmod($directory, 0700);
	} finally {
		umask($previous_umask);
	}
}

/**
 * Determine whether two filesystem paths refer to the same key file.
 *
 * @param string $source_path      Provided source key path
 * @param string $destination_path Target package.key path
 *
 * @return bool
 */
function package_key_paths_match(string $source_path, string $destination_path) : bool {
	$real_source = realpath($source_path);
	$real_dest   = realpath($destination_path);

	if ($real_source !== false && $real_dest !== false) {
		return $real_source === $real_dest;
	}

	return $source_path === $destination_path;
}

/**
 * Enforce owner-only (0600) permissions on an existing regular private key file.
 *
 * @param string $path Path to the private key file
 *
 * @return bool
 */
function package_key_tighten_permissions(string $path) : bool {
	if ($path === '' || is_link($path) || !is_file($path)) {
		return false;
	}

	return @chmod($path, 0600);
}

/**
 * Export and atomically write a package-signing private key with 0600 permissions.
 *
 * Accepts either an OpenSSLAsymmetricKey instance or a PEM private key string.
 * Key material is written to a private temporary file in the destination
 * directory before renaming into place so the key is never created with
 * permissive default permissions.
 *
 * @param mixed  $private_key      OpenSSLAsymmetricKey or PEM private key string
 * @param string $destination_path Destination file path for package.key
 *
 * @return bool
 */
function package_key_write_private(mixed $private_key, string $destination_path) : bool {
	if ($destination_path === '' || is_link($destination_path)) {
		return false;
	}

	if (!$private_key instanceof OpenSSLAsymmetricKey && !is_string($private_key)) {
		return false;
	}

	$pem = '';

	if (!@openssl_pkey_export($private_key, $pem) || !is_string($pem) || $pem === '') {
		return false;
	}

	$directory = dirname($destination_path);

	if (!package_key_ensure_directory($directory)) {
		return false;
	}

	$previous_umask = umask(0077);
	$temporary      = false;

	try {
		$temporary = $directory . '/.package.key.' . bin2hex(random_bytes(8));

		if (@file_put_contents($temporary, $pem) === false || !@chmod($temporary, 0600) || !@rename($temporary, $destination_path)) {
			return false;
		}

		$temporary = false;

		return @chmod($destination_path, 0600);
	} finally {
		if (is_string($temporary) && is_file($temporary)) {
			@unlink($temporary);
		}

		umask($previous_umask);
	}
}
