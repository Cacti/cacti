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

include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
$action = "preview"; 
include ("export_header.php");

$array_settings = LoadSettingsIntoArray("","");


print "<tr><td bgcolor='#$colors[light]' width='1%' rowspan='99999'></td>\n";

$graphs = db_fetch_assoc("select 
			   g.id,g.title,g.imageformatid,
			   t.name 
			   from rrd_graph g
			   left join def_image_type t
			   on g.imageformatid=t.id 
			   and g.export=\"on\"
			   order by g.title");

if (sizeof($graphs) > 0) {
    foreach ($graphs as $graph) {
	$graph_data_array["graph_width"] = $array_settings["preview"]["width"];
	$graph_data_array["graph_height"] = $array_settings["preview"]["height"];
	$graph_data_array["use"] = true;
	$graph_data_array["graph_nolegend"] = true;
	$graph_data_array["graph_start"] = -$array_settings["preview"]["timespan"];
	
	$image_filename = "prev_".$graph[ID] .".". $graph[Name];
	
	$graph_data_array["export"] = true;
	$graph_data_array["export_filename"] = $image_filename;
	
	/* use rraid of 1 (daily) for now as default */
	rrdtool_function_graph($graph[ID],1,$graph_data_array);
	
	print "<td width='25%'><a href='graph_$graph[ID].html'><img src='$image_filename' border='0' alt='rrdtool Graph'></a></td>\n";
	$k++;	
		
	if ($k % $array_settings["preview"]["columnnumber"] == 0) {
	    print "</tr><tr height='10'><td>&nbsp;</td></tr><tr>\n";
	}	
}	
print "</tr>\n";

include_once ("include/bottom_footer.php");
