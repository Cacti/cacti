<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\PackageDiffBoundary;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root                              = dirname(__DIR__, 3);
$GLOBALS['cacti_fixture_contract'] = [
	'name'      => 'package-diff-boundary', 'complete' => false, 'assertions' => 0,
	'producers' => ['tests/fixtures/Core/PackageDiffBoundary.php', 'tests/Helpers/FixtureCoverage.php', 'tests/Helpers/RequestCoverage.php'],
];
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';

define('CACTI_PATH_BASE', $root);
define('CACTI_WEB', false);

/*
 * Request accessors and collaborators package_diff_file() depends on, confined
 * to this fixture namespace so the unchanged production function can run without
 * the web entry point's auth and dispatch stack.  package_file_get_contents()
 * is stubbed to return false so the diff never needs the package repository or
 * the vendor diff renderer; the boundary decision runs entirely on real code.
 */
$GLOBALS['diff_requested_filename'] = '';

function gfrv(string $name) : string {
	return '';
}

function grv(string $name) : string {
	return $name === 'filename' ? (string) $GLOBALS['diff_requested_filename'] : '';
}

function __($text, ...$args) {
	return $text;
}

function cacti_sizeof($array) : int {
	return is_array($array) ? count($array) : 0;
}

function package_file_get_contents($package_location, $package_file, $filename) {
	return false;
}

// Load the unchanged production function into this namespace for execution.
\cacti_fixture_source_function($root . '/package_import.php', 'package_diff_file', __NAMESPACE__);

function capture_diff(string $filename) : string {
	$GLOBALS['diff_requested_filename'] = $filename;

	ob_start();
	package_diff_file();

	return (string) ob_get_clean();
}

$script = glob($root . '/scripts/*.php');

if (!is_array($script) || cacti_sizeof($script) < 1) {
	throw new \RuntimeException('A scripts/ file is required to exercise the in-subtree path');
}
$script = basename($script[0]);

// In the base tree but outside scripts/ and resource/: must be rejected.
$outside = capture_diff('include/cacti_version');

// Inside scripts/: must pass the boundary (and then report the missing new file).
$inside = capture_diff('scripts/' . $script);

if (strpos($outside, 'Invalid filename specified.') === false) {
	throw new \RuntimeException('A file outside scripts/ and resource/ must be rejected by the diff boundary');
}

if (strpos($inside, 'Invalid filename specified.') !== false) {
	throw new \RuntimeException('A script inside scripts/ must pass the diff boundary');
}

$GLOBALS['cacti_fixture_contract']['assertions'] = 2;
$GLOBALS['cacti_fixture_contract']['complete']   = true;

print "PASS: package diff file boundary confines the preview to scripts/ and resource/\n";
