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
 * scripts/ss_webhits.php, the PHP port of webhits.pl. GHSA-x5pw-cg7j-mj4r
 * moved webhits.pl off the shell; the port reads the log itself.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_webhits.php';

test('counts newlines the way wc -l does', function () {
	$log = tempnam(sys_get_temp_dir(), 'webhits');

	try {
		file_put_contents($log, "GET /\nGET /a\nGET /b");

		expect(ss_webhits_collect($log))->toBe(['2', 0])
			->and(ss_webhits($log))->toBe('2');

		file_put_contents($log, '');

		expect(ss_webhits_collect($log))->toBe(['0', 0]);
	} finally {
		unlink($log);
	}
});

test('counts across read chunks', function () {
	$stream = fopen('php://memory', 'w+b');

	fwrite($stream, str_repeat("x\n", 1500000));
	rewind($stream);

	expect(ss_webhits_count($stream))->toBe(1500000);

	fclose($stream);
});

test('a path with shell metacharacters is only ever a file name', function () {
	expect(ss_webhits_collect('/tmp/x; touch /tmp/cacti-webhits-pwned'))->toBe(['U', 1])
		->and(file_exists('/tmp/cacti-webhits-pwned'))->toBeFalse();
});

test('missing files and directories return U', function () {
	expect(ss_webhits_collect('/nonexistent/access_log'))->toBe(['U', 1])
		->and(ss_webhits_collect(sys_get_temp_dir()))->toBe(['U', 1]);
});

test('an empty path falls back to the httpd access log', function () {
	$expected = is_file('/var/log/httpd/access_log') && is_readable('/var/log/httpd/access_log') ? 0 : 1;

	expect(ss_webhits_collect('')[1])->toBe($expected);
});
