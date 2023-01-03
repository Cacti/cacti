<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

// include global
require(__DIR__ . '/../include/global.php');

// determine if we have html documentation otherwise send to GitHub Documentation Repo
if (file_exists($config['base_path'] . "/" . CACTI_DOCUMENTATION_TOC)) {
	// slurp up TOC and output
	print file_get_contents($config['base_path'] . "/" . CACTI_DOCUMENTATION_TOC);
} else {
	// Redirect to GitHub documentation
	header("Location: https://github.com/Cacti/documentation/blob/develop/README.md");
}
