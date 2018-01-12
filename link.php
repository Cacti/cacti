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

include_once('./include/global.php');

$page = db_fetch_row_prepared('SELECT
	id, title, style, contentfile, enabled
	FROM external_links AS el
	WHERE id = ?',
	array(get_filter_request_var('id')));

if (!sizeof($page)) {
	print 'FATAL: Page is not defined.';
} else {
	global $link_nav;

	if (is_realm_allowed($page['id']+10000)) {
		unset ($refresh);

		if ($page['style'] == 'TAB') {
			$link_nav['link.php:']['title']   = $page['title'];
			$link_nav['link.php:']['mapping'] = '';
			general_header();
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
	} else {
		print 'ERROR: Page is not authorized.';
	}

	bottom_footer();
}

