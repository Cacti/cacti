<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Changed-line coverage entry for the graph item label render probe. The
 * probe keeps its own assertions; this wrapper only adds the coverage writer
 * that run_pr_change_tests.sh reads, and passes the render mode through argv.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Unit/fixtures/aggregate_label_render_probe.php';
