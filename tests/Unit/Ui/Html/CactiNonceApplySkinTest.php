<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * applySkin() used cactiNonce as a bare identifier. Plugin pages, and any
 * request whose inline session script is blocked, never define that variable.
 * The resulting ReferenceError aborts the rest of applySkin().
 */

$root = dirname(__DIR__, 4);

test('applySkin does not read cactiNonce unless it is defined', function () use ($root) {
	$src = file_get_contents($root . '/include/layout.js');

	expect($src)->toBeString()->not->toBeEmpty();

	$fn = strstr($src, 'function applySkin()');

	expect($fn)->toBeString();

	$end = strpos($fn, "\nfunction ", 1);

	expect($end)->not->toBeFalse();

	$fn    = substr($fn, 0, $end);
	$guard = strpos($fn, "typeof cactiNonce !== 'undefined'");
	$use   = strpos($fn, 'nonce: cactiNonce');

	expect($guard)->not->toBeFalse()
		->and($use)->not->toBeFalse()
		->and($guard)->toBeLessThan($use);
});
