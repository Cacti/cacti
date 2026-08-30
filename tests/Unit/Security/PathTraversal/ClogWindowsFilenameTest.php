<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: clog_purge_logfile() protects the active log with a byte-for-byte
 * name comparison. On Windows a name with a trailing dot/space or an alternate
 * data stream (cacti.log::$DATA) passes the prefix check yet differs as a
 * string, so the purge took the unlink() branch and deleted the live log.
 * clog_validate_filename() now rejects those Windows-special forms.
 */

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/clog_webapi.php');

test('clog_validate_filename rejects an alternate-data-stream / drive colon', function () use ($source) {
	$start = strpos($source, 'function clog_validate_filename(');
	expect($start)->not->toBeFalse();
	$body = substr($source, $start, 900);
	expect($body)->toContain("strpos(\$file, ':') !== false");
});

test('clog_validate_filename rejects a trailing dot or space', function () use ($source) {
	$start = strpos($source, 'function clog_validate_filename(');
	$body  = substr($source, $start, 900);
	expect($body)->toContain("preg_match('/[\\. ]\$/', \$file)");
});

test('the reject runs before the active-log prefix match', function () use ($source) {
	$start   = strpos($source, 'function clog_validate_filename(');
	$body    = substr($source, $start, 1200);
	$reject  = strpos($body, 'return false;');
	$prefix  = strpos($body, "strpos(\$file, \$logbase) === 0");
	expect($reject)->not->toBeFalse();
	expect($prefix)->not->toBeFalse();
	expect($reject)->toBeLessThan($prefix);
});
