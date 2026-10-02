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
 * __() (no escaping) across many pages. Those sinks now use __esc(). None of
 * them carry a URL, so escaping is safe. This guards a representative sample
 * and asserts no unescaped name-bearing form remains in those files.
 */

$root = dirname(__DIR__, 4);

$files = array(
	'/lib/api_device.php',
	'/host.php',
	'/graph_templates.php',
	'/lib/utility.php',
);

test('representative raise_message name sinks are escaped with __esc', function () use ($root) {
	$device = file_get_contents($root . '/lib/api_device.php');

	expect($device)->toContain("raise_message('poller_down_' . \$poller_id, __esc(")
		->and($device)->not->toContain("raise_message('poller_down_' . \$poller_id, __(");
});

test('no name-bearing raise_message(..., __(...$...)) remains in the swept files', function () use ($root, $files) {
	foreach ($files as $file) {
		$source = file_get_contents($root . $file);

		foreach (explode("\n", $source) as $line) {
			if (strpos($line, 'raise_message(') !== false
				&& strpos($line, ', __(') !== false
				&& strpos($line, '$') !== false
				&& strpos($line, 'html_escape') === false) {
				expect($line)->toBe('__swept__:' . $file);
			}
		}

		expect(true)->toBeTrue();
	}
});
