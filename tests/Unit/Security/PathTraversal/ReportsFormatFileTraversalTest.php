<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-5x27-wqpm-m24v: reports.php's format_file value was written verbatim
 * into reports.format_file and later concatenated onto CACTI_PATH_FORMATS in
 * reports_load_format_file() without a directory component check, letting a
 * traversal sequence (e.g. ../../../../etc/passwd) escape the formats
 * directory and read arbitrary files the web user could access. 1.2.x
 * already fixed the same sink under GHSA-g37j-39f4-6r4j/GHSA-mjvw-mhj5-9jcj;
 * develop had lost the guard. The fix confines the value with basename() at
 * both the save site (lib/html_reports.php) and the load site
 * (lib/reports.php), rejecting a bare '.'/'..'/empty result at the load site.
 */

$reportsSource     = file_get_contents(__DIR__ . '/../../../../lib/reports.php');
$htmlReportsSource = file_get_contents(__DIR__ . '/../../../../lib/html_reports.php');

test('GHSA-5x27: reports_load_format_file confines the value to a bare filename', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	expect($start)->not->toBeFalse();

	$end  = strpos($reportsSource, "\n}\n", $start);
	$body = substr($reportsSource, $start, $end - $start);

	expect($body)->toContain('$format_file = basename($format_file);');
});

test('GHSA-5x27: reports_load_format_file rejects a traversal-only result before any file IO', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	$basenamePos = strpos($body, '$format_file = basename($format_file);');
	$rejectPos   = strpos($body, "\$format_file === '' || \$format_file === '.' || \$format_file === '..'");
	$existsPos   = strpos($body, 'file_exists($format_file)');

	expect($basenamePos)->not->toBeFalse();
	expect($rejectPos)->not->toBeFalse();
	expect($existsPos)->not->toBeFalse();
	expect($basenamePos)->toBeLessThan($rejectPos);
	expect($rejectPos)->toBeLessThan($existsPos);
});

test('GHSA-5x27: the save path confines format_file to a bare filename before it reaches the database', function () use ($htmlReportsSource) {
	expect($htmlReportsSource)->toContain("\$save['format_file']   = basename((string) (\$post['format_file'] ?? ''));");
});

test('GHSA-5x27: a traversal payload resolves to a harmless bare filename', function () {
	$payload = '../../../../../../etc/passwd';

	expect(basename($payload))->toBe('passwd');
});
