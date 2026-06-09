<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$pkgSource = file_get_contents(__DIR__ . '/../../package_import.php');

test('GHSA-wqqv-4rrg-mrrc: template name column uses form_selectable_ecell', function () use ($pkgSource) {
	// form_selectable_ecell HTML-encodes before output; the plain variant does not.
	// Locate the detail-row rendering block and confirm $detail['name'] goes
	// through the escaping variant.
	$pos = strpos($pkgSource, "form_selectable_ecell(\$detail['name']");
	expect($pos)->not->toBeFalse();
});

test('GHSA-wqqv-4rrg-mrrc: type_name column uses form_selectable_ecell', function () use ($pkgSource) {
	$pos = strpos($pkgSource, "form_selectable_ecell(\$detail['type_name']");
	expect($pos)->not->toBeFalse();
});

test('GHSA-wqqv-4rrg-mrrc: package name column uses form_selectable_ecell', function () use ($pkgSource) {
	$pos = strpos($pkgSource, 'form_selectable_ecell($file_package_name,');
	expect($pos)->not->toBeFalse();
});

test('GHSA-wqqv-4rrg-mrrc: diff_array is not double-escaped', function () use ($pkgSource) {
	// Entries in $diff_array are pre-escaped at source in lib/import.php.
	// Wrapping them again with array_map('html_escape') would double-encode
	// the color spans used to highlight differences, breaking the UI.
	$doubleEscape = strpos($pkgSource, "array_map('html_escape', \$diff_array)");
	expect($doubleEscape)->toBeFalse();
});

test('GHSA-wqqv-4rrg-mrrc: orphan_array is escaped at sink', function () use ($pkgSource) {
	// $orphan_array entries are NOT pre-escaped, so they must be escaped at
	// the output site to prevent stored XSS from attacker-controlled names.
	$pos = strpos($pkgSource, "array_map('html_escape', \$orphan_array)");
	expect($pos)->not->toBeFalse();
});

test('GHSA-wqqv-4rrg-mrrc: pre-escape comment present for diff_array', function () use ($pkgSource) {
	// A code comment must explain why double-escaping is intentionally omitted
	// near the implode($diff_array) call, so future reviewers do not "fix" it.

	// Find the implode call that renders diff_array.
	$implodePos = strpos($pkgSource, 'implode(\'<br>\', $diff_array)');
	if ($implodePos === false) {
		$implodePos = strpos($pkgSource, 'implode("<br>", $diff_array)');
	}
	expect($implodePos)->not->toBeFalse();

	// The explanatory comment must appear within 300 bytes before the call.
	$window = substr($pkgSource, max(0, $implodePos - 300), 350);
	$hasComment = strpos($window, 'pre-escaped') !== false
		|| strpos($window, 'pre_escaped') !== false
		|| strpos($window, 'double') !== false;
	expect($hasComment)->toBeTrue();
});
