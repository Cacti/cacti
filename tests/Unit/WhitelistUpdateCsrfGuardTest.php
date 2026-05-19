<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Coverage backfill for PR #7149. csrf-magic only validates the
 * magic token on POST. The whitelist_update case in data_input.php
 * accepts shell_exec arguments and therefore must reject non-POST
 * before reaching the shell call, otherwise a GET-triggered CSRF
 * gadget on an authenticated admin can fire the rebuild push.
 */

$source = file_get_contents(__DIR__ . '/../../data_input.php');

test('whitelist_update body contains a REQUEST_METHOD POST guard', function () use ($source) {
	expect($source)->toContain("\$_SERVER['REQUEST_METHOD'] !== 'POST'");
});

test('REQUEST_METHOD guard precedes the shell_exec call in the whitelist_update case', function () use ($source) {
	/* Slice the case body from its marker to the next case label
	 * or the documented fall-through comment. The shell_exec call
	 * must appear AFTER the REQUEST_METHOD rejection so a non-POST
	 * request can never reach the shell. */
	$start = strpos($source, "case 'whitelist_update':");
	expect($start)->not->toBeFalse();

	$endA = strpos($source, "\tcase '", $start + 1);
	$endB = strpos($source, '/* fall through */', $start);
	$end  = ($endA !== false && ($endB === false || $endA < $endB)) ? $endA : $endB;
	expect($end)->not->toBeFalse();

	$body = substr($source, $start, $end - $start);
	$methodPos = strpos($body, "REQUEST_METHOD");
	$shellPos  = strpos($body, 'shell_exec(');

	expect($methodPos)->not->toBeFalse();
	expect($shellPos)->not->toBeFalse();
	expect($methodPos < $shellPos)->toBeTrue();
});
