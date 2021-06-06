<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

global $config, $menu, $is_request_ajax, $twig, $twig_vars, $twig_common, $twig_options;

$page_title = api_plugin_hook_function('page_title', draw_navigation_text('title'));
$using_guest_account = false;

$twig_common = [
	'locale'     => CACTI_LOCALE,
	'is_ajax'    => $is_request_ajax,
	'is_guest'   => $using_guest_account,
	'is_classic' => get_selected_theme() == 'classic',
];

$user = db_fetch_row_prepared('SELECT
	username, password_change, realm
	FROM user_auth WHERE id = ?',
	array($_SESSION['sess_user_id']));

$twig_options = [
	'auth_method' => read_config_option('auth_method'),
];

$twig_menu       = twig_menu();
$twig_header     = twig_common_header($page_title);
$twig_tabs_left  = twig_tabs_left();
$twig_tabs_right = twig_graph_tabs_right();
$twig_nav        = twig_navigation_text();

echo $twig->render('common/header.html.twig',
	array_merge($twig_vars,
		array(
			'common'     => $twig_common,
			'options'    => $twig_options,
			'menu'       => $twig_menu,
			'header'     => $twig_header,
			'nav_items'  => $twig_nav,
			'tabs_left'  => $twig_tabs_left,
			'tabs_right' => $twig_tabs_right,
			'page_title' => $page_title,
			'user'       => $user,
		)
	)
);
