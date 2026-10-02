<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-w3qw-762w-6g9v: an array-form action[] made every scalar $action == $bad
 * comparison in the include/global.php GET guard false, slipping a
 * state-changing action past it. The array guard must itself fail closed.
 */

$root = dirname(__DIR__, 4);

test('the GET action guard rejects an array action by failing closed before the denylist', function () use ($root) {
	$source = file_get_contents($root . '/include/global.php');

	$guard = strpos($source, 'if (is_array($action)) {');
	$loop  = strpos($source, 'foreach ($bad_actions as $bad) {');

	expect($guard)->not->toBeFalse()
		->and($loop)->not->toBeFalse()
		->and($guard)->toBeLessThan($loop);

	// the guard body itself must return 405 and terminate, not merely exist
	$body = substr($source, $guard, $loop - $guard);

	expect($body)->toContain('http_response_code(405)')
		->and($body)->toContain('exit;');
});
