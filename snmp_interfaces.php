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
$section = "Add/Edit Data Sources"; 
include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/functions.php');
include_once ('include/utility_functions.php');
include_once ('include/form.php');

print "colors = '".sizeof($colors)."'<BR>\n";

switch ($action) {
 case 'save':
    $graph_parameters["snmp_interface_id"] = $id;
    $graph_parameters["data_source_name"] = $dsname;
    $graph_parameters["unparsed_graph_title"] = $title;
    
    CreateGraphDataFromSNMPData($graph_parameters);
    
    header ("Location: snmp_interfaces.php?id=$gid");
    break;
 case 'saveall':
    $int_list = db_fetch_assoc("select * from snmp_hosts_interfaces where hostid=$gid");
    if (sizof($int_list) > 0) {
	foreach ($int_list as $int) {
	    if ($int[ID] != "") {
		$graph_parameters["snmp_interface_id"] = $int[ID];
		$graph_parameters["data_source_name"] = ${$int[ID]};
		$graph_parameters["unparsed_graph_title"] = $title;
		
		CreateGraphDataFromSNMPData($graph_parameters);
	    }
	    
	    $i++;
	}
    }
    header ("Location: snmp_interfaces.php?id=$gid");
    break;
 case 'makegraph':
    include_once ('include/top_header.php');
    
    /* get a nice default name */
    $int = db_fetch_row("select * from snmp_hosts_interfaces where id=$id");
    
    if ($int[IPAddress] == "") {
	$snmp_description = substr($int[Description],0,10) . "_$int[InterfaceNumber]";
    } else {
	$snmp_description = "$int[IPAddress]_$int[InterfaceNumber]";
    }
    
    /* this "name cleanup" is only for the default name in the textbox; the user
     can enter something different if they so choose */
    $snmp_description = CleanUpName($snmp_description);
    $snmp_description = strtolower($snmp_description);
    $snmp_description = "traf_$snmp_description";
    
    DrawFormHeader("Create Graph from SNMP Data Interface","You must specify some additional
							     information to create a graph, please supply it here.",false);
    
    DrawFormItem("Graph Title","The title format used for the name of this graph. Valid
				 variable names are: &lt;data_source_name&gt;, &lt;snmp_description&gt;, &lt;snmp_interface_number&gt;,
				 &lt;snmp_interface_speed&gt;, &lt;snmp_hardware_address&gt;, and &lt;snmp_ip_address&gt;");
    DrawFormItemTextBox("title","Traffic Analysis for <data_source_name>","","");
    
    DrawFormItem("Data Source Name","Please enter a name for the data source, this name 
				      cannot contain spaces or other strange charcters.");
    DrawFormItemTextBox("dsname",$snmp_description,"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenIDField("GID",$gid);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 case 'makeall':
    include_once ('include/top_header.php');
    
    /* get a nice default name */
    $int_list = db_fetch_assoc("select * from snmp_hosts_interfaces where hostid=$gid");
    
    DrawFormHeader("Create Graph from SNMP Data Interface","You must specify some additional
							     information to create a graph, please supply it here.",false);
    
    DrawFormItem("Graph Title","The title format used for the name of this graph. Valid
				 variable names are: &lt;data_source_name&gt;, &lt;snmp_description&gt;, &lt;snmp_interface_number&gt;,
				 &lt;snmp_interface_speed&gt;, &lt;snmp_hardware_address&gt;, and &lt;snmp_ip_address&gt;");
    DrawFormItemTextBox("title","Traffic Analysis for <data_source_name>","","");
    
    DrawFormItem("Data Source Name(s)","Please enter a name for the data source, this name 
					 cannot contain spaces or other strange charcters.");
    
    if (sizeof($int_list) > 0) {
	foreach ($int_list as $int) {
	    if ($int[IPAddress] == "") {
		$snmp_description = substr($int[Description],0,10) . "_$int[InterfaceNumber]";
	    }else{
		$snmp_description = "$int[IPAddress]_$int[InterfaceNumber]";
	    }
	
	    $snmp_description = CleanUpName($snmp_description);
	    $snmp_description = strtolower($snmp_description);
	    $snmp_description = "traf_$snmp_description";
	    
	    DrawFormItem("","$int[Description]/$int[IPAddress]");
	    DrawFormItemTextBox($int[ID],$snmp_description,"","");
	}
    }
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("GID",$gid);
    print "<input type=\"hidden\" name=\"action\" value=\"saveall\"></form>";
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current SNMP Interfaces",$colors[dark_bar],"","5");
    DrawMatrixLoopItemAction("Make All Graphs",$colors[dark_bar],"",true,"snmp_interfaces.php?action=makeall&gid=$id");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Interface Number",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Description",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Speed",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Hardware Address",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("IP Address",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $int_list = db_fetch_assoc("select * from snmp_hosts_interfaces where hostid=$id order by interfacenumber");
    if (sizeof($int_list) > 0) {
	foreach ($int_list as $int) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem($int[InterfaceNumber],false,"");
	    DrawMatrixLoopItem($int[Description],false,"");
	    DrawMatrixLoopItem(($int[Speed]/1000000) . " mbit","",false,"");
	    DrawMatrixLoopItem($int[HardwareAddress],false,"");
	    DrawMatrixLoopItem($int[IPAddress],false,"");
	    DrawMatrixLoopItemAction("Make Graph",$colors[panel],"",false,"snmp_interfaces.php?action=makegraph&gid=$id&id=$int[ID]");
	    DrawMatrixRowEnd();
	    
	}
    }
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
