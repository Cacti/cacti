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
 * Source-level and contract tests for XSS and path-traversal advisories.
 *
 * GHSA-6233: Stored XSS in report tree titles (lib/reports.php)
 * GHSA-fwh3: Reflected XSS via rfilter in aggregate_graphs.php
 * GHSA-vp35: Path traversal in package import file write (lib/import.php)
 * GHSA-pr9x: Path traversal in package_import.php read
 * GHSA-mjvw: Path traversal via format_file in reports
 */

$reportsPath         = __DIR__ . '/../../lib/reports.php';
$aggregateGraphsPath = __DIR__ . '/../../aggregate_graphs.php';
$importPath          = __DIR__ . '/../../lib/import.php';
$packageImportPath   = __DIR__ . '/../../package_import.php';
$htmlReportsPath     = __DIR__ . '/../../lib/html_reports.php';

// ---------------------------------------------------------------------------
// GHSA-6233: Stored XSS in report tree titles
// ---------------------------------------------------------------------------

test('GHSA-6233: reports.php escapes all title outputs with htmle()', function () use ($reportsPath) {
	$contents = file_get_contents($reportsPath);

	// No raw <h3>$title</h3> interpolation should remain after the fix.
	$unescapedCount = substr_count($contents, '<h3>$title</h3>');
	expect($unescapedCount)->toBe(0);

	// All title outputs must use htmle()
	$escapedCount = substr_count($contents, 'htmle($title)');
	expect($escapedCount)->toBeGreaterThanOrEqual(7);
});

test('GHSA-6233: reports.php line 943 uses htmle() for report name (safe pattern exists)', function () use ($reportsPath) {
	$contents = file_get_contents($reportsPath);

	expect($contents)->toContain('<h3>" . htmle($report[\'name\']) . \'</h3>');
});

// ---------------------------------------------------------------------------
// GHSA-fwh3: Reflected XSS via rfilter in aggregate_graphs.php
// ---------------------------------------------------------------------------

test('GHSA-fwh3: aggregate_graphs.php escapes rfilter with htmlerv in value attribute', function () use ($aggregateGraphsPath) {
	$contents = file_get_contents($aggregateGraphsPath);

	// htmlerv() must be used instead of raw grv() in the value attribute.
	expect($contents)->toContain("htmlerv('rfilter')");
	expect($contents)->not->toContain("value='<?php print grv('rfilter'); ?>'");
});

test('GHSA-fwh3: contract — rfilter output in HTML attributes must use htmlerv()', function () use ($aggregateGraphsPath) {
	$contents = file_get_contents($aggregateGraphsPath);

	// htmlerv() is the Cacti convention for encoding HTML attribute values
	// retrieved from request variables. The raw grv() call must be replaced.
	$hasRaw    = str_contains($contents, "value='<?php print grv('rfilter'); ?>'");
	$hasSafe   = str_contains($contents, "value='<?php print htmlerv('rfilter'); ?>'");

	// Fails until the advisory is remediated.
	expect($hasRaw)->toBeFalse('raw grv() must be replaced with htmlerv()');
	expect($hasSafe)->toBeTrue('htmlerv() must be used for the rfilter value attribute');
});

// ---------------------------------------------------------------------------
// GHSA-vp35: Path traversal in package import file write (lib/import.php)
// ---------------------------------------------------------------------------

test('GHSA-vp35: str_contains scripts/ prefix check is bypassable with directory traversal', function () {
	// The guard in import.php line 655 accepts any $name that contains the
	// substring 'scripts/'. A crafted name satisfies the check while the
	// resolved path escapes the base directory.
	$name = 'scripts/../../../etc/passwd';

	expect(str_contains($name, 'scripts/'))->toBeTrue();
});

test('GHSA-vp35: traversal payload resolves outside CACTI_PATH_BASE', function () {
	$base     = '/var/www/cacti';
	$name     = 'scripts/../../../etc/passwd';
	$filename = $base . '/' . $name;

	// realpath() would expose the escape; the current code skips this check.
	$resolved = realpath($filename);

	// On a real filesystem the path resolves to /etc/passwd (outside $base).
	// In unit context realpath() returns false for non-existent paths, but
	// the arithmetic remains: stripping the traversal segments leaves a path
	// that does not begin with $base.
	$normalized = implode('/', array_reduce(
		explode('/', $filename),
		function (array $parts, string $seg): array {
			if ($seg === '..') {
				array_pop($parts);
			} elseif ($seg !== '.' && $seg !== '') {
				$parts[] = $seg;
			}

			return $parts;
		},
		[]
	));

	expect(str_starts_with('/' . $normalized, $base . '/'))->toBeFalse();
});

test('GHSA-vp35: contract — import file paths are validated with validate_relative_path_within', function () use ($importPath) {
	$contents = file_get_contents($importPath);

	// The fix must validate the path before writing.
	expect($contents)->toContain('validate_relative_path_within');
});

// ---------------------------------------------------------------------------
// GHSA-pr9x: Path traversal in package_import.php read
// ---------------------------------------------------------------------------

test('GHSA-pr9x: package_import.php validates filename with validate_relative_path_within', function () use ($packageImportPath) {
	$contents = file_get_contents($packageImportPath);

	// The fix validates the filename before reading.
	expect($contents)->toContain('validate_relative_path_within($filename, CACTI_PATH_BASE)');
	// The old unvalidated file_get_contents must use the validated path.
	expect($contents)->toContain('file_get_contents($validated_path)');
});

// ---------------------------------------------------------------------------
// GHSA-mjvw: Path traversal via format_file in reports
// ---------------------------------------------------------------------------

test('GHSA-mjvw: html_reports.php saves format_file with basename() validation', function () use ($htmlReportsPath) {
	$contents = file_get_contents($htmlReportsPath);

	// The fix applies basename() to strip directory traversal.
	expect($contents)->toContain("basename(\$post['format_file'])");
	// The old unvalidated assignment must not exist.
	expect($contents)->not->toContain("\$save['format_file']   = \$post['format_file']");
});

test('GHSA-mjvw: a traversal format_file resolves outside CACTI_PATH_FORMATS', function () {
	// reports_load_format_file() prepends CACTI_PATH_FORMATS without
	// sanitizing the stored value, so a stored traversal sequence escapes
	// the formats directory at read time.
	$formatsDir  = '/var/www/cacti/formats';
	$formatFile  = '../../include/config.php';
	$resolved    = $formatsDir . '/' . $formatFile;

	$normalized = implode('/', array_reduce(
		explode('/', $resolved),
		function (array $parts, string $seg): array {
			if ($seg === '..') {
				array_pop($parts);
			} elseif ($seg !== '.' && $seg !== '') {
				$parts[] = $seg;
			}

			return $parts;
		},
		[]
	));

	expect(str_starts_with('/' . $normalized, $formatsDir . '/'))->toBeFalse();
});
