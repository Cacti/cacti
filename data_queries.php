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
<? 	$section = "Add/Edit Data Sources"; include ('include/auth.php');
	header("Cache-control: no-cache");
#	include ('include/database.php');
#	include ('include/config.php');
	include_once ('include/form.php');

switch ($action) {
	case 'remove':
		if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
			include_once ('include/top_header.php');
			DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this SNMP host?", $current_script_name, "?action=remove&id=$id");
			exit;
		}
		
		if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
			db_execute("delete from snmp_hosts where id=$id");
			db_execute("delete from snmp_hosts_interfaces where hostid=$id");
		}
		
		header ("Location: snmp.php");
		break;
	case 'save':
		include_once ("include/snmp_functions.php");
		
		$sql_id = db_execute("replace into snmp_hosts (id,hostname,community) values 
			($id,\"$hostname\",\"$community\")");
		
		$hostid = db_fetch_cell"select LAST_INSERT_ID()");
		
		get_snmp_interfaces($hostname,$community,$hostid);
		
		header ("Location: snmp.php");
		break;
	case 'refresh':
		include_once ("include/snmp_functions.php");
		
		$host = db_fetch_row("select * from snmp_hosts where id=$id");
		get_snmp_interfaces($host[Hostname],$host[Community],$id);
		
		header ("Location: snmp.php");
		break;
	case 'edit':
		include_once ('include/top_header.php');
		
		DrawFormHeader("SNMP Host Configuration","",false);
		
		DrawFormItem("Hostname","The hostname of the system you want to gather SNMP data from.");
		DrawFormItemTextBox("hostname",$sql_id,"","");
		
		DrawFormItem("Community","The community to use when gathering data (default is public).");
		DrawFormItemTextBox("community",$sql_id,"","");
		
		DrawFormSaveButton();
		DrawFormItemHiddenIDField("id",$id);
		DrawFormFooter();
		
		include_once ("include/bottom_footer.php");
		
		break;
	default:
		include_once ('include/top_header.php');
		
		DrawMatrixTableBegin("97%");
		DrawMatrixRowBegin();
			DrawMatrixHeaderTop("Current SNMP Hosts",$colors[dark_bar],"","4");
			DrawMatrixHeaderAdd($colors[dark_bar],"","snmp.php?action=edit");
		DrawMatrixRowEnd();
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Hostname",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Community",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Interfaces",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Refresh SNMP Data",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
		DrawMatrixRowEnd();
		
		$hosts = db_fetch_assoc("select * from snmp_hosts order by hostname");
		$rows = sizeof($hosts);
    $i = 0;
		while ($i < $rows) { 
		    $host = $hosts[$i];
		    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
		    DrawMatrixLoopItem($host[Hostname],false,"");
		    DrawMatrixLoopItem($host[Community],false,"");
		    DrawMatrixLoopItem("View Gathered Interfaces",false,"snmp_interfaces.php?id=$host[ID]");
		    DrawMatrixLoopItem("Refresh Interface","","",false,"snmp.php?action=refresh&id=$host[ID]");
		    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"snmp.php?action=remove&id=$host[ID]");
		    DrawMatrixRowEnd();
		    $i++;
		}
		
		DrawMatrixTableEnd();
		include_once ("include/bottom_footer.php");
		
		break;
} ?>
