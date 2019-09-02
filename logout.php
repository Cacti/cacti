<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

define('CACTI_IN_INSTALL', 1);
include('./include/auth.php');

global $config;

set_default_action();

api_plugin_hook('logout_pre_session_destroy');

/* Clear session */
setcookie(session_name(), '', time() - 3600, $config['url_path']);
setcookie(session_name() . '_otp', '', time() - 3600, $config['url_path']);
session_destroy();

api_plugin_hook('logout_post_session_destroy');

/* allow for plugin based logout page */
if (api_plugin_hook_function('custom_logout_message', OPER_MODE_NATIVE) === OPER_MODE_RESKIN) {
	exit;
}

/* Check to see if we are using Web Basic Auth */
html_auth_header('logout',__('Logout of Cacti'), __('Automatic Logout'), __('You have been logged out of Cacti due to a session timeout.'), array());
?>
			<tr>
				<td>
					<?php print __('Please close your browser or %sLogin Again%s', '<a href="index.php">', '</a>') ?>
				</td>
			</tr>
<?php
html_auth_footer('logout', __('Cookies have been cleared'), '');

