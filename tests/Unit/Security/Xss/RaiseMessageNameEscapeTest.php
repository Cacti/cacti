<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-9737-rqh3-h7fh: names interpolated into raise_message() went through
 * __() (no escaping) across many pages. Those sinks now use __esc(). None carry
 * a URL. This asserts no name-bearing unescaped form remains in the swept files.
 */

$root = dirname(__DIR__, 4);

$files = array('/lib/api_device.php', '/host.php', '/graphs_new.php', '/lib/utility.php');

test('no name-bearing raise_message(..., __(...$...)) remains in the swept files', function () use ($root, $files) {
	foreach ($files as $file) {
		$source = file_get_contents($root . $file);

		foreach (explode("\n", $source) as $line) {
			if (strpos($line, 'raise_message(') !== false
				&& strpos($line, ', __(') !== false
				&& strpos($line, '$') !== false
				&& strpos($line, 'html_escape') === false
				// messages that intentionally build HTML or carry a URL are
				// excluded from the sweep, exactly as the conversion was
				&& preg_match('/<[a-zA-Z\/]|&[a-z]+;|&#/', $line) === 0
				&& preg_match('/http|href|<a |url_path|Location:/', $line) === 0) {
				expect($line)->toBe('__swept__:' . $file);
			}
		}
	}

	expect(true)->toBeTrue();
});
