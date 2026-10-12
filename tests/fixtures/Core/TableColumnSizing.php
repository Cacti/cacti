<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
namespace Cacti\Test\TableSizing;
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/lib/html_utility.php';
require_once dirname(__DIR__, 3) . '/lib/html.php';
function html_help_page($page) { return false; }
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html.php', 'html_start_box', __NAMESPACE__);
$config = ['poller_id' => 1];
$_SERVER['SCRIPT_NAME'] = '/monitor.php';
$_CACTI_REQUEST = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$_REQUEST = $_CACTI_REQUEST;
ob_start();
html_start_box($argv[3] ?? '', '100%', ($argv[2] ?? '') === 'div', 0, 'left', '', 'Add');
$html = ob_get_clean();
print json_encode(['html' => $html], JSON_THROW_ON_ERROR) . "\n";
