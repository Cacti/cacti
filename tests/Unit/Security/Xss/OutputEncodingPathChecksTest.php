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

use Symfony\Component\Process\Process;

/*
 * Defense in depth for values that already pass validation where they are
 * stored: text placed into HTML, a quoted SQL literal or a URL is encoded for
 * that context, log paths refuse control and markup characters, the data
 * source debug check reads the contained path, and a log purge or rotate
 * confirmation is a POST that carries a token.
 *
 * The fixture runs the production code blocks with their original line numbers.
 */

test('the production code encodes, quotes and checks each value where it is used', function (string $mode, string $result) {
	$process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/OutputEncodingPaths.php', $mode]);
	$process->setTimeout(60);
	$process->run();

	expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
		->and($process->getOutput())->toContain('PASS: ' . $result);
})->with([
	'rendered values'                => ['', 'output encoding, query quoting and path checks'],
	'table filter script'            => ['filter', 'table filter values are encoded as URL components'],
	'purge by GET is refused'        => ['purge-get', 'log confirmation purge-get'],
	'purge without a valid token'    => ['purge-token', 'log confirmation purge-token'],
	'purge by POST with a token'     => ['purge-post', 'log confirmation purge-post'],
	'rotate by GET is refused'       => ['rotate-get', 'log confirmation rotate-get'],
	'rotate by POST with a token'    => ['rotate-post', 'log confirmation rotate-post'],
]);

test('orphaned graph item text is encoded once, by each renderer that prints it', function () {
	$root   = dirname(__DIR__, 4);
	$import = file_get_contents($root . '/lib/import.php');

	// The text is built raw, so no renderer encodes it a second time.
	expect($import)->toContain("\$orphan_text[] = 'Found Orphaned Graph Items, Type: ' . \$graph_item_types[\$item['graph_type_id']] . ', Text Format: ' . \$item['text_format'] . ', Value: ' . \$item['value'];")
		->and($import)->toContain("print '<li>' . htmle(\$orphan) . '</li>' . PHP_EOL;");

	foreach (['templates_import.php', 'package_import.php'] as $page) {
		$source = file_get_contents($root . '/' . $page);

		expect($source)->toContain("implode('<br>', array_map('html_escape', \$orphan_array))")
			->and($source)->toContain("implode('<br>', \$diff_array)");
	}
});

test('the log viewer has no confirmation link that changes a log by GET', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/clog_webapi.php');

	expect(substr_count($source, 'csrf_require_post();'))->toBe(2)
		->and($source)->toContain("postUrl({url: location.pathname+\" . json_encode('?' . \$action . '=1&filename=' . rawurlencode(basename(\$logfile))) . \"}, {__csrf_magic: csrfMagicToken});")
		->and($source)->not->toContain("strURL = location.pathname+'?\$action=1")
		->and($source)->not->toContain("filename=' . \$logfile\n")
		->and($source)->not->toContain("filename=' . basename(\$logfile)");
});
