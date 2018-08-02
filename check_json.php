<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* since we'll have additional headers, tell php when to flush them */
ob_start();

$guest_account = true;
$auth_json     = true;

include('include/global.php');

/* set the json variable for request validation handling */
set_request_var('json', true);

$debug = false;
switch (get_request_var('action')) {
	case 'checkpass':
		$error = secpass_check_pass(get_nfilter_request_var('password'));

		if ($error == '') {
			print $error;
		} else {
			print 'ok';
		}

		exit;

		break;

	case 'checksess':
		include('./include/auth.php');
		$json = json_encode(
			array(
				'status' => '200',
				'statusText' => __('Logged In'),
				'responseText' => __('Logged in with access to Cacti.')
			)
		);
		break;

	default:
		include('./include/auth.php');
		$json = json_encode(
			array(
				'status' => '404',
				'statusText' => __('Action Not Found'),
				'responseText' => __('Requested action not found on %s', 'check_json.php')
			)
		);
		break;
}

header('Content-Type: application/json');
header('Content-Length: ' . strlen($json));
print $json;
