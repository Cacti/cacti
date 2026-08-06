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

// This directory doubles as the renderer's template path, so only redirect
// when the file is requested directly. When included as a template it must
// stay side-effect free and emit no headers.
//
// Canonicalize both sides so a symlinked script path compares equal to this
// file. When SCRIPT_FILENAME is unset or unresolvable, treat the access as an
// include rather than a direct request and skip the redirect.
if (!empty($_SERVER['SCRIPT_FILENAME']) && !str_contains($_SERVER['SCRIPT_FILENAME'], "\0")) {
	$requested = realpath($_SERVER['SCRIPT_FILENAME']);

	if ($requested !== false && $requested === realpath(__FILE__)) {
		header('Location:../index.php');

		exit;
	}
}
