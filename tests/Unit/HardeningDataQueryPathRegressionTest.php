<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$dqSource = file_get_contents(__DIR__ . '/../../lib/data_query.php');

test('GHSA-gx62-3v55-846j: get_data_query_array calls cacti_path_is_within before file read', function () use ($dqSource) {
	$start = strpos($dqSource, 'function get_data_query_array(');
	expect($start)->not->toBeFalse();

	$end  = strpos($dqSource, "\nfunction ", $start + 1);
	$body = substr($dqSource, $start, $end - $start);

	expect($body)->toContain('cacti_path_is_within(');

	// Both must appear; ordering is tested separately.
	$hasFile = strpos($body, 'file(') !== false || strpos($body, 'file_get_contents(') !== false;
	expect($hasFile)->toBeTrue();
});

test('GHSA-gx62-3v55-846j: allowed base includes /resource directory', function () use ($dqSource) {
	$start = strpos($dqSource, 'function get_data_query_array(');
	$end   = strpos($dqSource, "\nfunction ", $start + 1);
	$body  = substr($dqSource, $start, $end - $start);

	// The guard must anchor paths to the Cacti resource subtree.
	$guardPos = strpos($body, 'cacti_path_is_within(');
	expect($guardPos)->not->toBeFalse();

	// Look for '/resource' within a reasonable window around the guard.
	$window = substr($body, max(0, $guardPos - 200), 400);
	expect($window)->toContain('/resource');
});

test('GHSA-gx62-3v55-846j: traversal is rejected before file() call', function () use ($dqSource) {
	$start = strpos($dqSource, 'function get_data_query_array(');
	$end   = strpos($dqSource, "\nfunction ", $start + 1);
	$body  = substr($dqSource, $start, $end - $start);

	$guardPos = strpos($body, 'cacti_path_is_within(');
	expect($guardPos)->not->toBeFalse();

	// file() is the actual read call; it must appear after the guard.
	$filePos = strpos($body, 'file(', $guardPos);
	expect($filePos)->not->toBeFalse();
	expect($guardPos)->toBeLessThan($filePos);
});

test('GHSA-gx62-3v55-846j: rejected path logs a security warning', function () use ($dqSource) {
	$start = strpos($dqSource, 'function get_data_query_array(');
	$end   = strpos($dqSource, "\nfunction ", $start + 1);
	$body  = substr($dqSource, $start, $end - $start);

	// Find the rejection branch — the block that runs when the path check fails.
	$guardPos = strpos($body, 'cacti_path_is_within(');
	$window   = substr($body, $guardPos, 300);

	expect($window)->toContain('cacti_log(');
	expect($window)->toContain('SECURITY');
});
