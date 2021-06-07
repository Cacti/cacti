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
	'locale'        => CACTI_LOCALE,
	'is_ajax'       => $is_request_ajax,
	'is_guest'      => $using_guest_account,
];

$twig_view = [
	'is_classic' => get_selected_theme() == 'classic',
	'is_graph'   => (get_current_page() == 'graph_view.php' && (get_nfilter_request_var('action') == 'tree' || (isset_request_var('view_type') && get_nfilter_request_var('view_type') == 'tree'))),
	'is_main'    => empty($config['hide_main']),
	'is_menu'    => empty($config['hide_console']),
	'tree'       => function_exists('twig_dhtml_trees') ? twig_dhtml_trees() : '',
	'tree_path'  => function_exists('twig_tree_path') ? twig_tree_path() : '[]',
];

if (empty($_SESSION['sess_user_id'])) {
	$user = false;
} else {
	$user = db_fetch_row_prepared('SELECT
		username, password_change, realm
		FROM user_auth WHERE id = ?',
		array($_SESSION['sess_user_id']));
}

$twig_auth = [
	'method' => read_config_option('auth_method'),
];

$twig_menu       = twig_menu();
$twig_header     = twig_common_header($page_title);
$twig_tabs_left  = twig_tabs_left();
$twig_tabs_right = twig_graph_tabs_right();
$twig_nav        = twig_navigation_text();

$twig_hook = [
	'nav_login_before'          => '',
	'nav_login_after'           => '',
	'top_graph_jquery_function' => '',
];

foreach ($twig_hook as $hook => &$value) {
	$value = twig_hook_buffer($hook);
}

$output = $twig->render('common/header.html.twig',
	array_merge($twig_vars,
		array(
			'common'     => $twig_common,
			'auth'       => $twig_auth,
			'menu'       => $twig_menu,
			'view'       => $twig_view,
			'header'     => $twig_header,
			'nav_items'  => $twig_nav,
			'tabs_left'  => $twig_tabs_left,
			'tabs_right' => $twig_tabs_right,
			'page_title' => $page_title,
			'user'       => $user,
		)
	)
);

if ($GLOBALS['csrf']['rewrite']) {
	$output = csrf_ob_handler($output, false);
}

echo $output;
