<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('device filters retain associated labels in native and Select2 modes', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/HostFilterProbe.php';
	$output  = array();
	$status  = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture), $output, $status);
	expect($status)->toBe(0);
	$rendered = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	expect($rendered)->toHaveCount(4);

	foreach ($rendered as $mode => $html) {
		$document = new DOMDocument();
		$document->loadHTML('<html><body><table><tr>' . $html . '</tr></table></body></html>');
		$xpath = new DOMXPath($document);
		expect($xpath->query('//label[@for="host_id"]')->length)->toBe(1);
		expect(trim($xpath->query('//label[@for="host_id"]')->item(0)->textContent))->toBe('Device');
		expect($xpath->query('//select[@id="host_id"]')->length)->toBe(1);
		expect($xpath->query('//select[contains(@class,"select2-callback")]')->length)->toBe($mode === 'modern-1' ? 1 : 0);
	}
});
