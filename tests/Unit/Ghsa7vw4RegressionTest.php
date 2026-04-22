<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$pollerSource = file_get_contents(__DIR__ . '/../../lib/poller.php');

test('GHSA-7vw4-2r73-89g2: file_exists_2gb uses PHP file_exists, not a shell', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	expect($start)->not->toBeFalse();

	$end  = strpos($pollerSource, "\n}\n", $start);
	$body = substr($pollerSource, $start, $end - $start);

	// PHP's file_exists handles >2GB files on every supported build since
	// PHP 5.0, so the shell fallback that used to be here is no longer
	// required and its $filename expansion was the actual bug.
	expect($body)->toContain('return @file_exists($filename);');
});

test('GHSA-7vw4-2r73-89g2: file_exists_2gb has no shell invocation', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	$end   = strpos($pollerSource, "\n}\n", $start);
	$body  = substr($pollerSource, $start, $end - $start);

	expect($body)->not->toContain('system(');
	expect($body)->not->toContain('shell_exec(');
	expect($body)->not->toContain('exec(');
	expect($body)->not->toContain('test -f');
});

test('GHSA-7vw4-2r73-89g2: file_exists_2gb body is a single-line delegation', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	$end   = strpos($pollerSource, "\n}\n", $start);
	$body  = substr($pollerSource, $start, $end - $start);

	// Guard against a future "helpful" refactor re-introducing argv
	// construction around $filename.
	expect($body)->not->toContain('escapeshellarg');
	expect($body)->not->toContain('escapeshellcmd');
});
