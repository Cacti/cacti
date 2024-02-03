<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

include('./include/auth.php');

$version = CACTI_VERSION_TEXT;

if (isset($_SERVER['HTTP_REFERER'])) {
	$goBack = "[<a href='" . sanitize_uri($_SERVER['HTTP_REFERER']) . "'>" . __('Return') . "</a> | <a href='" . CACTI_PATH_URL . "logout.php'>" . __('Login Again') . '</a>]';
} else {
	$goBack = "[<a href='#' onClick='window.history.back()'>" . __('Return') . "</a> | <a href='" . CACTI_PATH_URL . "logout.php'>" . __('Login Again') . '</a>]';
}

/* allow for plugin based permission denied page */
if (api_plugin_hook_function('custom_denied', OPER_MODE_NATIVE) === OPER_MODE_RESKIN) {
	exit;
}

raise_ajax_permission_denied();

html_auth_header('denied', __('Permission Denied'), __('Permission Denied'),
	__('You are not permitted to access this section of Cacti.'));
?>
	<tr><td><?php print __('If you feel that this is an error. Please contact your Cacti Administrator.'); ?></td></tr>
	<tr><td><center><?php print $goBack; ?></center></td></tr>
<?php
html_auth_footer('denied');
