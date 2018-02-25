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

global $menu, $config;
$using_guest_account = false;
$show_console_tab = true;

$oper_mode = api_plugin_hook_function('top_graph_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

/* ================= input validation ================= */
get_filter_request_var('local_graph_id');
get_filter_request_var('graph_start');
get_filter_request_var('graph_end');
/* ==================================================== */

if (read_config_option('auth_method') != 0) {
	/* at this point this user is good to go... so get some setting about this
	user and put them into variables to save excess SQL in the future */
	$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	/* find out if we are logged in as a 'guest user' or not */
	if (db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ?', array(read_config_option('guest_user'))) == $_SESSION['sess_user_id']) {
		$using_guest_account = true;
	}

	/* find out if we should show the "console" tab or not, based on this user's permissions */
	if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE realm_id = 8 AND user_id = ?', array($_SESSION['sess_user_id']))) == 0) {
		$show_console_tab = false;
	}
} else {
	$current_user = 0;
}

/* need to correct $_SESSION["sess_nav_level_cache"] in zoom view */
if (isset_request_var('action') && get_nfilter_request_var('action') == 'zoom') {
	$_SESSION['sess_nav_level_cache'][2]['url'] = 'graph.php?local_graph_id=' . get_filter_request_var('local_graph_id') . '&rra_id=0';
}

$page_title = api_plugin_hook_function('page_title', draw_navigation_text('title'));

global $graph_views;
load_current_session_value('action', 'sess_cacti_graph_action', $graph_views['2']);

?>
<!DOCTYPE html>
<html>
<head>
	<?php html_common_header($page_title);?>
</head>
<body>
<div id='cactiPageHead' class='cactiPageHead' role='banner'>
	<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<div id='tabs'><?php html_show_tabs_left();?></div>
	<div class='cactiGraphHeaderBackground'><div id='gtabs'><?php print html_graph_tabs_right($current_user);?></div></div>
</div>
<div id='breadCrumbBar' class='breadCrumbBar'>
	<div id='navBar' class='navBar'><?php echo draw_navigation_text();?></div>
	<div class='scrollBar'></div>
	<?php if (read_config_option('auth_method') != 0) {?><div class='infoBar'><?php echo draw_login_status($using_guest_account);?></div><?php }?>
</div>
<div class='cactiShadow'></div>
<div id='cactiContent' class='cactiContent'>
	<?php if (get_current_page() == 'graph_view.php' && (get_nfilter_request_var('action') == 'tree' || (isset_request_var('view_type') && get_nfilter_request_var('view_type') == 'tree'))) { ?>
	<div id='navigation' class='cactiTreeNavigationArea'><?php grow_dhtml_trees();?></div>
	<div id='navigation_right' class='cactiGraphContentArea'>
	<?php } else { ?>
	<div id='navigation_right' class='cactiGraphContentAreaPreview'>
	<?php } ?>
		<div style='position:static;' id='main' role='main'>
	<?php } ?>
