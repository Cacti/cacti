<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Loads lib/process.php without the Composer autoloader, as a site whose vendor
// tree is not yet installed would, so cacti_process_run() must take the native
// proc_open() backend.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'cacti_sizeof', '');
require_once dirname(__DIR__, 3) . '/lib/process.php';

if (class_exists(Symfony\Component\Process\Process::class, false)) {
	throw new RuntimeException('The Symfony backend must be unavailable for this fixture.');
}

$result = cacti_process_run(['printf', '%s', 'a; $(id)']);

if ($result['via'] !== 'proc_open' || $result['stdout'] !== 'a; $(id)' || $result['exit_code'] !== 0) {
	throw new RuntimeException('The native fallback did not run the command without a shell.');
}
print json_encode(['passed' => 1], JSON_THROW_ON_ERROR) . "\n";
