<?php
declare(strict_types = 1);
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

require_once dirname(__DIR__, 2) . '/lib/plugins.php';

/**
 * Regression test for GHSA-pjmv-fxjm-29cx.
 *
 * The plugin repository URL was an unvalidated free text setting, and archive
 * entries were written under plugins/<name>/ with no check that the entry name
 * stayed inside that directory.  Together they let an administrator point
 * plugin distribution at attacker infrastructure and have it write a PHP file
 * anywhere the web server can reach.
 *
 * @group regression
 */

$pluginsPath = dirname(__DIR__, 2) . '/lib/plugins.php';

test('the repository URL is validated before it builds a request', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	expect($source)->not->toMatch("/\\\$repo = trim\(read_config_option\('github_repository'\)/");
	expect($source)->toMatch("/\\\$repo = plugin_validate_repository_url\(read_config_option\('github_repository'\)\)/");
});

test('archive entries are contained before they are written', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	expect($source)->toMatch('/validate_relative_path_within\(\$rfile, \$restore_path\) === false/');

	$guard = strpos($source, 'validate_relative_path_within($rfile, $restore_path)');
	$write = strpos($source, 'file_put_contents($restore_path');

	expect($guard)->toBeLessThan($write);
});

test('the containment helpers live in lib/functions.php', function () {
	expect(file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php'))
		->toContain('function validate_relative_path_within')
		->toContain('function cacti_path_is_within');
});

test('repository url accepts http and https with a host', function () : void {
	expect(plugin_validate_repository_url('https://api.github.com/'))->toBe('https://api.github.com');
	expect(plugin_validate_repository_url('http://repo.internal:8080/api'))->toBe('http://repo.internal:8080/api');
});

test('repository url rejects anything else', function () : void {
	// GHSA-pjmv-fxjm-29cx: an arbitrary URL redirects every plugin fetch
	expect(plugin_validate_repository_url('file:///etc/passwd'))->toBe('');
	expect(plugin_validate_repository_url('javascript:alert(1)'))->toBe('');
	expect(plugin_validate_repository_url('ftp://example.com/'))->toBe('');
	expect(plugin_validate_repository_url('not a url'))->toBe('');
	expect(plugin_validate_repository_url(''))->toBe('');
	expect(plugin_validate_repository_url(null))->toBe('');
	expect(plugin_validate_repository_url('http://'))->toBe('');
});

test('repository url rejects userinfo, query strings and fragments', function () : void {
	// the value is concatenated with a path, so these produce malformed requests
	expect(plugin_validate_repository_url('https://user:pw@api.github.com'))->toBe('');
	expect(plugin_validate_repository_url('https://user@api.github.com'))->toBe('');
	expect(plugin_validate_repository_url('https://api.github.com/?x=1'))->toBe('');
	expect(plugin_validate_repository_url('https://api.github.com/#frag'))->toBe('');
});
