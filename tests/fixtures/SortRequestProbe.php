<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs one page request through the production filter, sort and header code,
 * in its own process so the per-request table counters start at zero as they
 * do in a page view. The argument is a JSON object:
 *
 *   script   the script name of the page
 *   request  the request variables
 *   session  the session left behind by the previous request
 *   tables   the tables the request draws, in page order, each with
 *            prefix   the session prefix of its filter
 *            default  its default sort column, or null when it declares none
 *            columns  the keys of its header
 *            renderer plain | checkbox
 *            raw      true when the filter passes the sort column through
 *
 * The verdict carries, per table, the sort column the page reads after
 * validation and the ORDER BY clause, and the session for the next request.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 2);
$spec = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

$config = ['is_web' => false, 'config_options_array' => ['log_validation' => '', 'i18n_language_support' => '0']];

require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';
require_once $root . '/lib/html_utility.php';
require_once $root . '/lib/html.php';
require_once $root . '/include/global_languages.php';

$_SERVER['SCRIPT_NAME'] = $spec['script'];
$_SESSION               = $spec['session'] ?? [];
$_REQUEST               = $spec['request'] ?? [];
$_GET                   = $_REQUEST;

$result = ['sort' => [], 'order' => []];

foreach ($spec['tables'] as $table) {
	$callback = empty($table['raw']) ? 'sanitize_search_string' : 'strval';
	$filters  = [
		'sort_column'    => ['filter' => FILTER_CALLBACK, 'default' => $table['default'] ?? '', 'options' => ['options' => $callback]],
		'sort_direction' => ['filter' => FILTER_CALLBACK, 'default' => 'ASC', 'options' => ['options' => $callback]],
	];

	if (isset($table['default'])) {
		validate_store_request_vars($filters, $table['prefix'] ?? '');
	} else {
		// a page without a declared default validates its sort column itself
		get_filter_request_var('sort_column', FILTER_CALLBACK, ['options' => $callback]);
		update_order_string();
	}

	$result['sort'][]  = get_request_var('sort_column');
	$result['order'][] = get_order_string();

	$header = [];

	foreach ($table['columns'] as $column) {
		$header[$column] = ['display' => $column, 'sort' => 'ASC'];
	}

	ob_start();

	if (($table['renderer'] ?? 'checkbox') === 'plain') {
		html_header_sort($header, (string) get_request_var('sort_column'), (string) get_request_var('sort_direction'));
	} else {
		html_header_sort_checkbox($header, (string) get_request_var('sort_column'), (string) get_request_var('sort_direction'), false);
	}

	ob_end_clean();
}

$result['session'] = $_SESSION;

print json_encode($result, JSON_THROW_ON_ERROR);
