<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

get_filter_request_var('page', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
get_filter_request_var('error', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

if (isset_request_var('page') && !isset_request_var('error')) {
	if (read_config_option('local_documentation') == 'on') {
		if (file_exists($config['base_path'] . '/docs/' . $page)) {
			print json_encode(
				array(
					'status'   => 'success',
					'location' => $config['url_path'] . '/docs/' . $page
				)
			);
		} else {
			print json_encode(
				array(
					'status' => 'not reachable',
					'message' => __('The Document page <b><i>%s</i></b> could not be reached locally.  If you have installed Cacti from GitHub directly, consider downloading the distribution release and extracting the \'docs\' folder from that release to your Cacti install.', $page)
				)
			);
		}
	}
} elseif (get_request_var('error') == 'missing') {
	$page = str_replace('.html', '.md', get_request_var('page'));

	print json_encode(
		array(
			'status'   => 'not found',
			'message' => __('The Document page <b><i>%s</i></b> could not be reached on the Official Cacti Documentation website.', $page) . '<br><br>' . __esc('Open a ticket at ') . '<a target="_blank" href="https://github.com/cacti/cacti/issues">' . __esc('Official Cacti GitHub Site') . '</a>.'
		)
	);
} elseif (get_request_var('error') == 'unreach') {
	$page = str_replace('.html', '.md', get_request_var('page'));

	print json_encode(
		array(
			'status' => 'not reachable',
			'message' => __('The Document page <b><i>%s</i></b> could not be reached.  It is possible that the Cacti Documentation site is down or not reachable by your browser.  Check your proxy settings and if that does not correct the problem, consider downloading an official release to obtain the latest page help documentation and hosting the documentation locally.', $page)
		)
	);
}

