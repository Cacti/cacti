<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

include("include/config.php");

exec(read_config_option("path_php_binary") . " -q $path_cacti/export_index.php > " . read_config_option("path_html_export") . "/index.html");
exec(read_config_option("path_php_binary") . " -q $path_cacti/export_images.php");

/* copy the css/images on the first time */
if (file_exists(read_config_option("path_html_export") . "/main.css") == false) {
	copy("$path_cacti/css/main.css", read_config_option("path_html_export") . "/main.css");
	copy("$path_cacti/images/top_tabs_export.gif", read_config_option("path_html_export") . "/top_tabs_export.gif");
	copy("$path_cacti/images/top_tabs_graph_preview.gif", read_config_option("path_html_export") . "/top_tabs_graph_preview.gif");
	copy("$path_cacti/images/top_tabs_graph_preview_down.gif", read_config_option("path_html_export") . "/top_tabs_graph_preview_down.gif");
	copy("$path_cacti/images/transparent_line.gif", read_config_option("path_html_export") . "/transparent_line.gif");
}

?>