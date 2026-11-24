<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * The import and export pages cannot be included in the test process, so each
 * scenario runs tests/fixtures/Core/ImportExportPages.php in a child process,
 * which loads the page functions from the checked-in source.
 */

test('import and export page scenario passes', function (string $mode) {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/ImportExportPages.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect($errors)->toBe('')
		->and(proc_close($process))->toBe(0)
		->and($output)->toContain("PASS: $mode");
})->with([
	'data source profile import result lines' => ['profile-messages'],
	'package key details before verification' => ['public-key-details'],
	'template import result message'          => ['template-import-result'],
	'template preview dependencies'           => ['template-dependencies'],
	'unreadable uploaded package preview'     => ['package-preview-failure'],
	'unreadable repository package details'   => ['package-details-failure'],
	'repository import of part of a package'  => ['repository-import-selection'],
]);

test('the results of every template file of a package are kept', function () {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/PackageTemplateResults.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect($errors)->toBe('')
		->and(proc_close($process))->toBe(0)
		->and($output)->toContain('PASS: package-results');
});
