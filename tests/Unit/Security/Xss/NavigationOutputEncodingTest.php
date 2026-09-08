<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

test('external link titles are escaped by the real navigation renderer', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $navigation;

	$session       = "$database_hostname:$database_port:$database_default";
	$priorDatabase = $database_sessions[$session] ?? null;
	$priorNav      = $navigation;
	$priorServer   = $_SERVER;
	$priorRequest  = $_REQUEST;
	$conn          = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE external_links (id INTEGER PRIMARY KEY, title TEXT, style TEXT)');
	$conn->exec("INSERT INTO external_links (id, title, style) VALUES (7, '<img src=x onerror=alert(1)>', 'TAB')");

	$database_sessions[$session] = $conn;
	$navigation                  = [
		'link.php:' => [
			'mapping' => '',
			'title'   => '',
			'level'   => 0,
			'url'     => '',
		],
	];
	$_SERVER['SCRIPT_NAME']  = '/link.php';
	$_SERVER['REQUEST_URI']  = '/link.php?id=7';
	$_REQUEST                = [];

	try {
		$output = draw_navigation_text();

		expect($output)->toContain('&lt;img src=x onerror=alert(1)&gt;')
			->and($output)->not->toContain('<img src=x');
	} finally {
		$navigation = $priorNav;
		$_SERVER    = $priorServer;
		$_REQUEST   = $priorRequest;

		if ($priorDatabase === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $priorDatabase;
		}
	}
});

test('tree data query indexes are escaped at their output boundary', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/html_tree.php');

	expect($source)->toContain("htmle(get_formatted_data_query_index(\$leaf['host_id'], intval(\$host_group_data_array[1]), \$host_group_data_array[2]))");
});
