<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Pest and its isolated PHP subprocesses each own an Xdebug session.
if (PHP_SAPI === 'cli' && getenv('CACTI_LIBRARY_COVERAGE')) {
	$root  = dirname(__DIR__, 2);
	$files = ['api_data_source', 'boost', 'data_query', 'functions', 'html_form', 'ldap', 'plugins', 'poller', 'reports', 'utility'];
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, array_map(static fn ($file) => $root . '/lib/' . $file . '.php', $files));
	$directory = getenv('CACTI_LIBRARY_NATIVE_COVERAGE');

	if ($directory !== false && $directory !== '') {
		putenv('CACTI_COVERAGE_FILE=' . $directory . '/process-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.json');
	}
	require __DIR__ . '/FixtureCoverage.php';
}
