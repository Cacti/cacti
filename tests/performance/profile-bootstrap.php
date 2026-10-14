<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Check before loading Cacti, connecting to a database or touching any RRD.
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('CLI only.');
}

if (PHP_SAPI !== 'cli' || getenv('CACTI_PROFILE_DISPOSABLE') !== '1' || !is_file('/tmp/cacti-profile-disposable')) {
	fwrite(STDERR, "Use the disposable profiling runner.\n");
	exit(1);
}

chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../include/global.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once CACTI_PATH_LIBRARY . '/boost.php';

$_SESSION[SESS_USER_ID]     = 1;
$_SESSION['selected_theme'] = 'modern';

function profile_check(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
