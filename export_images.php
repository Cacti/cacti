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

include ("include/database.php");
include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ('include/config.php');
include ("export_header.php");

$graphs = db_fetch_assoc("select 
	g.ID,g.Title,g.ImageFormatID,
	t.Name 
	from rrd_graph g
	left join def_image_type t
	on g.imageformatid=t.id 
	and g.export=\"on\"
	order by g.title");

if (sizeof($graphs) > 0) {
    foreach ($graphs as $graph) {
	$filename = "graph_$graph[id].html";
	$fp = fopen(read_config_option("path_html_export") . "/$filename", "w");
	
	$exp_header = shell_exec(read_config_option("path_php_binary") . " -q $path_cacti/export_header.php");
	
	$graph_html = "";
	$rra_list = db_fetch_assoc("select id,name from rrd_rra order by steps");
	if (sizeof($rra_list) > 0) {
	    foreach ($rra_list as $rra) {
		$image_filename = "normal_" . $graph[id] . "_" . $rra[id] . "." . $graph[name];
		
		$graph_html .= "<div align=center><img src=\"$image_filename\" border=0></div>\n";
		$graph_html .= "<div align=center><strong>$row_rra[name]</strong></div><br>";
		
		$graph_data_array["export"] = true;
		$graph_data_array["export_filename"] = $image_filename;
		
		rrdtool_function_graph($graph[id],$rra[id],$graph_data_array);
	    }
	}
	
	$main_html = $exp_header . $graph_html;
	fwrite($fp, $main_html);
	fclose($fp);
    }
}

include_once ("include/bottom_footer.php");

?>
