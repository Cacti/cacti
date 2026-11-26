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
 * Resolve a reported page parameter to a known Cacti script basename.
 *
 * Accepts either a bare script filename or a local URL containing a query
 * string, strips the path to its basename, and verifies it is either a
 * registered Cacti realm page or an existing top-level PHP script under
 * the Cacti installation directory.
 *
 * @param string               $raw_page    Raw page parameter from the request
 * @param array<string, mixed> $known_pages Map of registered Cacti script filenames
 * @param string               $base_path   Filesystem path to the Cacti installation root
 *
 * @return string|null Validated script basename, or null when unrecognized
 */
function help_resolve_error_page(string $raw_page, array $known_pages, string $base_path) : ?string {
	$raw_page = trim($raw_page);

	if ($raw_page === '' || str_contains($raw_page, "\0")) {
		return null;
	}

	$path = parse_url($raw_page, PHP_URL_PATH);

	if (!is_string($path) || $path === '') {
		return null;
	}

	$candidate = basename($path);

	if (preg_match('/^[a-zA-Z0-9_]+\.php$/', $candidate) !== 1) {
		return null;
	}

	if (array_key_exists($candidate, $known_pages)) {
		return $candidate;
	}

	$root = rtrim($base_path, '/\\');

	if ($root !== '' && is_file($root . '/' . $candidate)) {
		return $candidate;
	}

	return null;
}

/**
 * Validate that a reported HTTP error status is within the 4xx/5xx range.
 *
 * @param int $status HTTP error status code
 *
 * @return bool
 */
function help_error_status_is_valid(int $status) : bool {
	return $status >= 400 && $status <= 599;
}
