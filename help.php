<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include('./include/auth.php');

if (isset_request_var('error')) {
	$page  = basename(get_nfilter_request_var('page'));
	$error = get_filter_request_var('error');

	$message = sprintf('WARNING: Page:%s Generated a Fatal Error:%d', $page, $error);

	cacti_log($message, false);

	if (debounce_run_notification('page_error_' . $page)) {
		admin_email(__('Cacti System Warning'), __('WARNING: Cacti Page: %s Generated a Fatal Error %d!', $page, $error));
	}
} elseif (isset_request_var('page')) {
	get_filter_request_var('page', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	$page = str_replace('.html', '.md', get_request_var('page'));

	$fgc_contextoption = array(
		'ssl' => array(
			'verify_peer'       => false,
			'verify_peer_name'  => false,
			'allow_self_signed' => true,
			'timeout'           => 2,
			'ignore_errors'     => true
		)
	);

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
			array(
				'status' => 'Not Reachable',
				'message' => __('The Document page \'%s\' count not be reached.  The Cacti Documentation site is not reachable.  The http error was \'%s\'.  Consider downloading an official release to obtain the latest documentation and hosting the documentation locally.', $page, $response_code)
			)
		);
	} elseif ($contents != '' && !preg_match('/does not appear to exist/i', $contents)) {
		print json_encode(
			array(
				'status'   => 'Success',
				'location' => 'https://docs.cacti.net/' . $page
			)
		);
	} elseif ($contents != '' && preg_match('/does not appear to exist/i', $contents)) {
		print json_encode(
			array(
				'status'   => 'Not Found',
				'location' => __esc('The Help File %s was not located on the Cacti Documentation Website.', $page) . '<br><br>' . __esc('Open a ticket at ') . '<a target="_blank" href="https://github.com/cacti/cacti/issues">' . __esc('Cacti GitHub Site') . '</a>.'
			)
		);
	} elseif (file_exists($config['base_path'] . '/docs/' . $page)) {
		print json_encode(
			array(
				'status'   => 'Success',
				'location' => $config['url_path'] . 'docs/' . $page
			)
		);
	} else {
		print json_encode(
			array(
				'status' => 'Not Reachable',
				'message' => __('The Document page \'%s\' count not be reached locally.', $page, $response_code)
			)
		);
	}
}
