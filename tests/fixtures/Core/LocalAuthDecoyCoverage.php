<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Runs every LocalAuthDecoyProbe scenario so the request-coverage gate sees
// the provider's unknown-user decoy and rehash-on-login paths execute. The changed-line gate runs
// the scenarios one by one from run_pr_change_tests.sh instead, because each
// child there writes its own CACTI_COVERAGE_FILE.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$arguments = [];

if (getenv('CACTI_PR_XDEBUG_EXTENSION')) {
	$arguments = ['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')];
}

foreach (['known' => false, 'unknown' => false, 'unknown-empty' => false, 'legacy' => true, 'current' => true] as $scenario => $success) {
	$process = proc_open(array_merge([PHP_BINARY], $arguments, [__DIR__ . '/LocalAuthDecoyProbe.php', $scenario]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output  = stream_get_contents($pipes[1]);
	$error   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$verdict = json_decode((string) $output, true);

	if (proc_close($process) !== 0 || !is_array($verdict) || $verdict['success'] !== $success) {
		throw new RuntimeException($scenario . ': ' . $error);
	}
}
print "PASS: local login decoy scenarios\n";
