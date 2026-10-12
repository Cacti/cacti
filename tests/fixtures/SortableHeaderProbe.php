<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

$root = dirname(__DIR__, 2);
require_once $root . '/lib/functions.php';
require_once $root . '/lib/html_utility.php';
require_once $root . '/lib/html.php';
$scenario               = $argv[1] ?? '';
$arguments              = json_decode($argv[2] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
$config                 = ['is_web' => false, 'config_options_array' => ['log_validation' => '', 'i18n_language_support' => '0']];
$_SERVER['SCRIPT_NAME'] = '/headers.php';
set_request_var('action', 'edit');
set_request_var('tab', 'details');
set_request_var('sort_column', 'name');
set_request_var('sort_direction', 'ASC');
$_SESSION = [];

if ($scenario === 'column') {
	$result = html_header_sort_column(...$arguments);
} elseif ($scenario === 'history') {
	$_SESSION['sort_data']['2_headers_edit_details'] = ['other' => 'DESC', 'name' => 'ASC'];
	$result                                          = [html_header_sort_order(2), html_header_sort_order(3)];
} elseif ($scenario === 'renderers') {
	require_once $root . '/include/global_constants.php';
	require_once $root . '/include/global_languages.php';
	$headers = [
		'name'        => ['display' => 'Name', 'tip' => 'Quotes " &', 'nohide' => false],
		'count'       => ['Count', 'DESC'],
		'nosort_note' => ['display' => 'Note']
	];
	ob_start();
	html_header_sort($headers, 'name', 'ASC', 2, '/headers.php');
	$plain = ob_get_clean();
	ob_start();
	html_header_sort_checkbox($headers, 'name', 'ASC', true, '/headers.php', '', 'rows');
	$checkbox                                        = ob_get_clean();
	$_SESSION['sort_data']['1_headers_edit_details'] = ['count' => 'DESC', 'name' => 'ASC'];
	ob_start();
	html_header_sort($headers, 'count', 'DESC', 2, '/headers.php');
	$second = ob_get_clean();
	$result = [$plain, $checkbox, $second, $_SESSION['valid_sort_columns']];
} else {
	throw new InvalidArgumentException('Unknown sortable header fixture');
}
print json_encode($result, JSON_THROW_ON_ERROR);
