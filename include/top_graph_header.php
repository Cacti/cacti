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

global $menu, $config, $is_request_ajax;
$using_guest_account = false;

$page_title = api_plugin_hook_function('page_title', draw_navigation_text('title'));

if (!$is_request_ajax) {?>
<!DOCTYPE html>
<html lang='<?php print CACTI_LOCALE;?>'>
<head>
	<?php html_common_header($page_title);?>
</head>
<body>
	<a class='skip-link' href='#main' style='display:none'>Skip to main</a>
	<div id='cactiPageHead' class='cactiPageHead' role='banner'>
		<div id='tabs'><?php html_show_tabs_left();?></div>
		<div class='cactiGraphHeaderBackground'><div id='gtabs'><?php print html_graph_tabs_right();?></div></div>
		<div class='cactiConsolePageHeadBackdrop' style='display:none;'></div>
	</div>
	<div id='breadCrumbBar' class='breadCrumbBar'>
		<div id='navBar' class='navBar'><?php print draw_navigation_text();?></div>
		<div class='scrollBar'></div>
		<?php if (read_config_option('auth_method') != AUTH_METHOD_NONE) {?><div class='infoBar'><?php print draw_login_status($using_guest_account);?></div><?php }?>
	</div>
	<div class='cactiShadow'></div>
	<?php } else { ?>
	<div id='navBar' class='navBar'><?php print draw_navigation_text();?></div>
	<title><?php print $page_title;?></title>
	<?php } ?>
	<div id='cactiContent' class='cactiContent'>
		<?php if (get_current_page() == 'graph_view.php' && (get_nfilter_request_var('action') == 'tree' || (isset_request_var('view_type') && get_nfilter_request_var('view_type') == 'tree'))) { ?>
		<div style='display:none;' id='navigation' class='cactiTreeNavigationArea'><?php grow_dhtml_trees();?></div>
		<?php } ?>
		<div id='navigation_right' class='cactiGraphContentArea'>
			<main style='position:relative;display:none;' id='main'>
