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

include_once('./include/global.php');

$page = db_fetch_row_prepared('SELECT
	id, title, style, contentfile, enabled, refresh
	FROM external_links AS el
	WHERE id = ?',
	array(get_filter_request_var('id')));

// Prevent redirect loops
if (isset($_SERVER['HTTP_REFERER'])) {
	if (strpos($_SERVER['HTTP_REFERER'], 'link.php') === false) {
		$referer = $_SERVER['HTTP_REFERER'];
		$_SESSION['link_referer'] = $referer;
	} elseif (isset($_SESSION['link_referer'])) {
		$referer = sanitize_uri($_SESSION['link_referer']);
	} else {
		$referer = 'index.php';
	}
} elseif (isset($_SESSION['link_referer'])) {
	$referer = sanitize_uri($_SESSION['link_referer']);
} else {
	$referer = 'index.php';
}

if (!cacti_sizeof($page)) {
	raise_message('page_not_defined');
	header('Location: ' . $referer);
	exit;
} else {
	global $link_nav;

	if (is_realm_allowed($page['id']+10000)) {
		unset ($refresh);

		if (!empty($page['refresh'])) {
			$refresh['seconds'] = $page['refresh'];
			$refresh['page']    = $config['url_path'] . 'link.php?id=' . get_request_var('id');
		}

		if ($page['style'] == 'TAB') {
			$link_nav['link.php:']['title']   = $page['title'];
			$link_nav['link.php:']['mapping'] = '';
			top_header(true);
		} else {
			$link_nav['link.php:']['title']   = $page['title'];
			$link_nav['link.php:']['mapping'] = 'index.php:';
			top_header();
		}

		if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i', $page['contentfile'])) {
			print '<iframe id="content" src="' . $page['contentfile'] . '" frameborder="0"></iframe>';
		} else {
			print '<div id="content">';

			$file = $config['base_path'] . "/include/content/" . $page['contentfile'];

			if (file_exists($file)) {
				include_once($file);
			} else {
				print '<h1>The file \'' . $page['contentfile'] . '\' does not exist!!</h1>';
			}

			print '</div>';
		}

		bottom_footer();
	} else {
		raise_message('permission_denied');
		header('Location: ' . $referer);
		exit;
	}
}

