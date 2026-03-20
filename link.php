<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

require_once('./include/global.php');

$page = db_fetch_row_prepared('SELECT
	id, title, style, contentfile, enabled, refresh
	FROM external_links AS el
	WHERE id = ?',
	[gfrv('id')]);

// Prevent redirect loops
if (isset($_SERVER['HTTP_REFERER'])) {
	if (!str_contains($_SERVER['HTTP_REFERER'], 'link.php')) {
		/* Reject non-http(s) schemes (javascript:, data:, etc.) before any
		 * host comparison; parse_url returns null for host on those schemes,
		 * which would otherwise pass the === null branch unchanged. Compare
		 * against SERVER_NAME (web-server config) rather than HTTP_HOST
		 * (attacker-supplied request header) to prevent host-spoofing.
		 * parse_url() returns false on a completely malformed URL; false fails
		 * all === comparisons below, so a malformed referer falls back to
		 * 'index.php'. null means the component is absent (e.g. relative path),
		 * which is allowed through as a same-host local referer. */
		$raw     = $_SERVER['HTTP_REFERER'];
		$scheme  = parse_url($raw, PHP_URL_SCHEME);
		$host    = parse_url($raw, PHP_URL_HOST);
		$referer = (
			($scheme === null || $scheme === 'http' || $scheme === 'https') &&
			($host === null || $host === ($_SERVER['SERVER_NAME'] ?? ''))
		) ? sanitize_uri($raw) : 'index.php';
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
	cacti_redirect($referer);
} else {
	global $link_nav;

	if (is_realm_allowed($page['id'] + 10000)) {
		unset($refresh);

		if (!empty($page['refresh'])) {
			$refresh['seconds'] = $page['refresh'];
			$refresh['page']    = CACTI_PATH_URL . 'link.php?id=' . grv('id');
		}

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
			if (filter_var($page['contentfile'], FILTER_VALIDATE_URL)) {
				print '<iframe id="content" src="' . htmle($page['contentfile']) . '" sandbox="allow-scripts allow-popups allow-forms" frameborder="0"></iframe>';
			} else {
				$message = __esc("External Link ID '%s' with Title '%s' attempted to inject an invalid URL and was blocked!", $page['id'], $page['title']);
				cacti_log($message, false, 'SECURITY');
				raise_message('invalid_url', $message, MESSAGE_LEVEL_ERROR);
			}
		} else {
			print '<div id="content">';

			$basepath = CACTI_PATH_INCLUDE . '/content';
			$file     = realpath($basepath . '/' . $page['contentfile']);

			if ($file !== false && str_starts_with($file, $basepath)) {
				require_once($file);
			} else {
				print '<h1>The file \'' . htmle($page['contentfile']) . '\' does not exist!!</h1>';
			}

			print '</div>';
		}

		bottom_footer();
	} else {
		raise_message('permission_denied');
		cacti_redirect($referer);
	}
}
