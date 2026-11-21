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
 * Output and filter hardening: the log purge message, the select list data
 * attributes and the plugin README renderer escape what they print, and the
 * list filters in sites.php and pollers.php match their search text literally.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 4) . '/lib/database.php';
require_once dirname(__DIR__, 4) . '/lib/html.php';

test('html_create_list escapes the data-class and data-style attributes', function () {
	$rows = [
		1 => ['name' => 'one', 'class' => "x'><script>alert(1)</script>", 'style' => "color:red' onmouseover='alert(1)"],
	];

	ob_start();
	html_create_list($rows, 'name', '', '');
	$html = (string) ob_get_clean();

	expect($html)->not->toContain('<script>')
		->and($html)->not->toContain("' onmouseover='")
		->and($html)->toContain('data-class=')
		->and($html)->toContain('data-style=');
});

test('html_create_list keeps ordinary class and style values readable', function () {
	ob_start();
	html_create_list([1 => ['name' => 'one', 'class' => 'ok-class', 'style' => 'color:red;']], 'name', '', '');
	$html = (string) ob_get_clean();

	expect($html)->toContain("data-class='ok-class'")
		->and($html)->toContain("data-style='color:red;'");
});

test('the plugin readme renderer does not pass raw HTML through', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	require_once dirname(__DIR__, 4) . '/lib/plugins.php';

	$key      = "$database_hostname:$database_port:$database_default";
	$previous = $database_sessions[$key] ?? null;

	$conn = new FakeMySQLPDO();
	$conn->exec('CREATE TABLE plugin_available (plugin TEXT, tag_name TEXT, readme TEXT)');
	$conn->exec('INSERT INTO plugin_available (plugin, tag_name, readme) VALUES (' . $conn->quote('demo') . ', ' . $conn->quote('v1') . ', ' . $conn->quote(base64_encode("# Title\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))\n")) . ')');

	$database_sessions[$key] = $conn;

	try {
		ob_start();
		$found = api_plugin_get_available_file_contents('demo', 'v1', 'readme');
		$html  = (string) ob_get_clean();
	} finally {
		if ($previous === null) {
			unset($database_sessions[$key]);
		} else {
			$database_sessions[$key] = $previous;
		}
	}

	expect($found)->toBeTrue()
		->and($html)->toContain('<h1>Title</h1>')
		->and($html)->not->toContain('<script>')
		->and($html)->not->toContain('href="javascript:');
});

test('the log purge messages are escaped like the rest of the function', function () {
	$src  = file_get_contents(dirname(__DIR__, 4) . '/lib/clog_webapi.php');
	$body = substr($src, strpos($src, 'function clog_purge_logfile('), 4000);

	expect($body)->toContain("__esc('The Cacti Log File \\'%s\\' was Removed by user \\'%s\\''")
		->and($body)->toContain("__esc('The Cacti Log File \\'%s\\' was Rotated by user \\'%s\\''")
		->and($body)->not->toMatch("/\\\$imessage\s*=\s*__\(/");
});

test('the site and poller filters escape LIKE wildcards', function (string $file) {
	$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

	expect($src)->toContain("db_qstr('%' . db_like_escape(grv('filter')) . '%')")
		->and($src)->not->toContain("db_qstr('%' . grv('filter') . '%')");
})->with(['sites.php', 'pollers.php']);
