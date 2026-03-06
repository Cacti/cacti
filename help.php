<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;

require('./include/auth.php');

if (isrv('error')) {
	$page  = basename(gnrv('page'));
	$error = gfrv('error');

	if (isset($_SESSION['sess_user_id'])) {
		$username = get_username($_SESSION['sess_user_id']);
	} else {
		$username = 'unknown';
	}

	$message = sprintf('WARNING: Cacti Page:%s for User:%s Generated a Fatal Error:%d', $page, $username, $error);

	cacti_log($message, false);

	if (debounce_run_notification('page_error_' . $page)) {
		admin_email(__('Cacti System Warning'), __('WARNING: Cacti Page:%s for User:%s Generated a Fatal Error %d!', $page, $username, $error));
	}
} elseif (isrv('page')) {
	gfrv('page', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);

	$page = basename(str_replace('.html', '.md', grv('page')));

	$fgc_contextoption = [
		'ssl' => [
			'verify_peer'       => true,
			'verify_peer_name'  => true,
			'allow_self_signed' => false,
			'timeout'           => 2,
			'ignore_errors'     => true
		],
		'http' => [
			'follow_location' => 0
		]
	];

	if (read_config_option('local_documentation') != 'on') {
		$fgc_context   = stream_context_create($fgc_contextoption);
		$contents      = @file_get_contents('https://docs.cacti.net/' . $page, false, $fgc_context);
		$response_code = http_response_code();
	} else {
		$contents      = '';
		$response_code = 200;
	}

	if ($response_code != 200) {
		print json_encode(
			[
				'status'  => 'Not Reachable',
				'message' => __('The Document page \'%s\' count not be reached.  The Cacti Documentation site is not reachable.  The http error was \'%s\'.  Consider downloading an official release to obtain the latest documentation and hosting the documentation locally.', $page, $response_code)
			]
		);
	} elseif ($contents != '' && !preg_match('/does not appear to exist/i', $contents)) {
		print json_encode(
			[
				'status'   => 'Success',
				'location' => 'https://docs.cacti.net/' . $page
			]
		);
	} elseif ($contents != '' && preg_match('/does not appear to exist/i', $contents)) {
		print json_encode(
			[
				'status'   => 'Not Found',
				'location' => __esc('The Help File %s was not located on the Cacti Documentation Website.', $page) . '<br><br>' . __esc('Open a ticket at ') . '<a target="_blank" href="https://github.com/cacti/cacti/issues">' . __esc('Cacti GitHub Site') . '</a>.'
			]
		);
	} elseif (file_exists(CACTI_PATH_DOCS . '/' . $page)) {
		print json_encode(
			[
				'status'   => 'Success',
				'location' => CACTI_PATH_URL . 'docs/' . $page
			]
		);
	} else {
		print json_encode(
			[
				'status'  => 'Not Reachable',
				'message' => __('The Document page \'%s\' count not be reached locally.', $page, $response_code)
			]
		);
	}
}
