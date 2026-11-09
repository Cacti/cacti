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

/*
 * The packaging tool depends on keys that cli/genkey.php writes into
 * CACTI_PATH_PKI. The unit environment never runs genkey.php, so the key
 * directory is absent and both entry points must refuse to continue.
 */

require_once dirname(__DIR__, 4) . '/lib/package.php';

beforeEach(function () {
	global $no_http_headers;

	$this->packageState = [$_SESSION ?? [], $no_http_headers ?? null];
	// raise_message() would otherwise try to open a web session from the CLI.
	$no_http_headers = true;
	$_SESSION        = [];
});

afterEach(function () {
	global $no_http_headers;

	[$_SESSION, $no_http_headers] = $this->packageState;
});

test('the packaging tool explains how to create keys when package.info is missing', function () {
	expect(file_exists(CACTI_PATH_PKI . '/package.info'))->toBeFalse();

	ob_start();
	$info   = check_get_author_info();
	$script = ob_get_clean();

	expect($info)->toBeFalse()
		->and($script)->toContain('genkey.php')
		->and($script)->toContain('MESSAGE_LEVEL_MIXED');
});

test('the package metadata database is not created in an unwritable key directory', function () {
	expect(is_writable(CACTI_PATH_PKI))->toBeFalse();

	expect(open_packager_metadata_table())->toBeFalse()
		->and($_SESSION[SESS_MESSAGES]['package_nowrite']['level'])->toBe(MESSAGE_LEVEL_ERROR)
		->and($_SESSION[SESS_MESSAGES]['package_nowrite']['message'])->toContain(CACTI_PATH_PKI)
		->and(file_exists(CACTI_PATH_PKI . '/package.db'))->toBeFalse();
});
