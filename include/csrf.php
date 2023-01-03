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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

require_once(CACTI_PATH_INCLUDE .'/vendor/csrf/csrf-conf.php');

/* cross site request forgery library */
function csrf_startup() {
	global $config;

	if ($config['is_web']) {
		/* If you need to debug CSRF, uncomment the following line */
		//csrf_conf('log_file', dirname(read_config_option('path_cactilog')) . '/csrf.log');
		if (!empty($config['path_csrf_secret'])) {
			csrf_conf('path_secret', $config['path_csrf_secret']);
		}

		csrf_conf('rewrite-js', CACTI_PATH_URL . 'include/vendor/csrf/csrf-magic.js');
		csrf_conf('callback', 'csrf_error_callback');
		csrf_conf('expires', 7200);
	} else {
		csrf_conf('disable',true);
	}
}

function csrf_error_callback() {
	//Resolve session fixation for PHP 5.4
	session_regenerate_id();
	raise_message('csrf_timeout');
	ob_end_clean();
	header('Location: ' . sanitize_uri($_SERVER['REQUEST_URI']));
	csrf_log(__FUNCTION__, 'Timeout, redirecting to ' . sanitize_uri($_SERVER['REQUEST_URI']));

	exit;
}

include_once(CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-magic.php');
