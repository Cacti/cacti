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

if (isset_request_var('page')) {
	get_filter_request_var('page', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	$page     = str_replace('.html', '.md', get_request_var('page'));
	$contents = file_get_contents('https://docs.cacti.net/' . $page);

	if ($contents != '' && !preg_match('/does not appear to exist/i', $contents)) {
		print 'https://docs.cacti.net/' . $page;
	} elseif (file_exists($config['base_path'] . '/docs/' . get_request_var('page'))) {
		print $config['url_path'] . '/docs/' . get_request_var('page');
	} else {
		print 'Not Found';
	}
}
