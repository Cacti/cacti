<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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

$guest_account = true;
include("./include/auth.php");
include("./include/top_graph_header.php");

if ($_GET["rra_id"] == "all") {
	$sql_where = " where id is not null";
}else{
	$sql_where = " where id=" . $_GET["rra_id"];
}

/* make sure the graph requested exists (sanity) */
if (!(db_fetch_cell("select local_graph_id from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]))) {
	print "<strong><font size='+1' color='FF0000'>GRAPH DOES NOT EXIST</font></strong>"; exit;
}

/* take graph permissions into account here, if the user does not have permission
give an "access denied" message */
if (read_config_option("global_auth") == "on") {
	$access_denied = !(is_graph_allowed($_GET["local_graph_id"]));
	
	if ($access_denied == true) {
		print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
	}
}

$rras = get_associated_rras($_GET["local_graph_id"]);

$graph_title = get_graph_title($_GET["local_graph_id"]);

if ((isset($_GET["type"]) ? $_GET["type"] : "") == "tree") {
	print "<table width='98%' style='background-color: #ffffff; border: 1px solid #ffffff;' align='center' cellpadding='3'>";
}else{
	print "<br><table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>";
}

print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='3' class='textHeaderDark'><strong>Viewing Graph</strong> '$graph_title'</td></tr>";

$i = 0;
if (sizeof($rras) > 0) {
foreach ($rras as $rra) {
	print "<tr><td>";
	
	print "	<div align='center'><img src='graph_image.php?local_graph_id=" . $_GET["local_graph_id"] . "&rra_id=" . $rra["id"] . "' border='0' alt='$graph_title'></div>\n
		<div align='center'><strong>" . $rra["name"] . "</strong> [<a href='graph.php?local_graph_id=" . $_GET["local_graph_id"] . "&rra_id=" . $rra["id"] . "&show_source=true'>source</a>]</div><br>\n";
	
	print "</td></tr>";
	
	$i++;
}
}

print "</table>";
print "<br><br>";

include_once("./include/bottom_footer.php");

?>
