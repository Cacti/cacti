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

$section = "View Graphs"; $guest_account = true; include ('include/auth.php');
include_once ("include/rrd_functions.php");

/* check to see if there are user specified vars */
if ($graph_start != "") {
	$graph_data_array["use"] = true;
	$graph_data_array["graph_start"] = $_GET["graph_start"];
	$graph_data_array["graph_height"] = $_GET["graph_height"];
	$graph_data_array["graph_width"] = $_GET["graph_width"];
}

/* treat the legend separatly */
if ($graph_nolegend != "") {
	$graph_data_array["graph_nolegend"] = $_GET["graph_nolegend"];
}

$graph_data_array["print_source"] = $_GET["showsource"];

print rrdtool_function_graph($_GET["local_graph_id"], $_GET["rra_id"], $graph_data_array);

?>
