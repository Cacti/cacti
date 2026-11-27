<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';
test('plain and encoded data-input imports validate stored command syntax (#7472)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/DataInputCommand.php');
	expect($result['import_cases'])->toBe(42);
});
test('data-input form rejects shell syntax and control characters and keeps quoted placeholders (#7472)', function (string $command, bool $allowed) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/DataInputCommand.php', ['form', base64_encode($command)]);
	expect($result['writes'])->toBe($allowed ? 1 : 0)->and($result['blocked'])->toBe(!$allowed);
})->with([
	['<path_php_binary> <path_cacti>/scripts/query.php <hostname>', true],
	['"<path_php_binary>" <path_cacti>/scripts/query.php "<hostname>"', true],
	["<path_php_binary>\t<path_cacti>/scripts/query.php <hostname>", true],
	['echo alpha; label', false],
	["echo alpha\nlabel", false],
	["echo alpha\0label", false],
	["echo alpha\x1blabel", false],
	["echo alpha\x7flabel", false],
	['echo "$HOME"', false],
	['echo (label)', false],
]);
