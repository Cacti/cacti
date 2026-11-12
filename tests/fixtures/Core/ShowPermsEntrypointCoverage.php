<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Runs the real cli/show_perms.php compatibility shim through the real CLI
// bootstrap, with the database boundary stubbed. --version makes the Console
// application answer and exit before the command touches a database.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';

$_SERVER['argv'] = ['show_perms.php', '--version'];

require dirname(__DIR__, 3) . '/cli/show_perms.php';
