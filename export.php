<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?

include("include/config.php");

exec($config["path_php_binary"]["value"] . " -q $path_cacti/export_index.php > " . $config["path_html_export"]["value"] . "/index.html");
exec($config["path_php_binary"]["value"] . " -q $path_cacti/export_images.php");

/* copy the css/images on the first time */
if (file_exists($config["path_html_export"]["value"] . "/main.css") == false) {
	copy("$path_cacti/css/main.css", $config["path_html_export"]["value"] . "/main.css");
	copy("$path_cacti/images/top_tabs_export.gif", $config["path_html_export"]["value"] . "/top_tabs_export.gif");
	copy("$path_cacti/images/top_tabs_graph_preview.gif", $config["path_html_export"]["value"] . "/top_tabs_graph_preview.gif");
	copy("$path_cacti/images/top_tabs_graph_preview_down.gif", $config["path_html_export"]["value"] . "/top_tabs_graph_preview_down.gif");
	copy("$path_cacti/images/transparent_line.gif", $config["path_html_export"]["value"] . "/transparent_line.gif");
}

?>