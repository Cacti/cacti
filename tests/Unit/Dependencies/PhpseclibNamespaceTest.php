<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * phpseclib puts its major version in its namespace, so a major bump renames
 * every class Cacti references. Composer accepts the upgrade, nothing fails at
 * install time, and the first fatal arrives when someone creates an RSA
 * keypair or talks to an RRDtool proxy.
 *
 * These tests fail at the point the constraint changes rather than in
 * production.
 */

test('the classes the source references actually exist', function () {
	require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';

	expect(class_exists('phpseclib3\Crypt\RSA'))->toBeTrue()
		->and(class_exists('phpseclib3\Crypt\Rijndael'))->toBeTrue()
		->and(class_exists('phpseclib3\Crypt\Random'))->toBeTrue();
});

test('the composer constraint matches the namespace in the source', function () {
	$root       = dirname(__DIR__, 3);
	$composer   = json_decode(file_get_contents($root . '/composer.json'), true);
	$constraint = $composer['require']['phpseclib/phpseclib'] ?? '';

	/* the sources use phpseclib3\, which only the 3.x line provides */
	expect($constraint)->toStartWith('^3.')
		->and(file_get_contents($root . '/lib/auth.php'))->toContain('use phpseclib3\Crypt\RSA;');
});
