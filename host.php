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

include("./include/auth.php");
include_once("./lib/utility.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'query_remove':
		host_remove_query();
		
		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'query_reload':
		host_reload_query();
		
		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'new_graphs':
		include_once("./include/top_header.php");
		
		host_new_graphs();
		
		include_once("./include/bottom_footer.php");
		break;
	case 'remove':
		host_remove();
		
		header ("Location: host.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		host_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		host();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["add_y"])) && (!empty($_POST["snmp_query_id"]))) {
		db_execute("replace into host_snmp_query (host_id,snmp_query_id) values (" . $_POST["id"] . "," . $_POST["snmp_query_id"] . ")");
		
		/* recache snmp data */
		data_query($_POST["id"], $_POST["snmp_query_id"]);
		
		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}
	
	if ((isset($_POST["save_component_host"])) && (!isset($_POST["add_y"]))) {
		$save["id"] = $_POST["id"];
		$save["host_template_id"] = $_POST["host_template_id"];
		$save["description"] = form_input_validate($_POST["description"], "description", "", false, 3);
		$save["hostname"] = form_input_validate($_POST["hostname"], "hostname", "", false, 3);
		$save["snmp_community"] = form_input_validate($_POST["snmp_community"], "snmp_community", "", true, 3);
		$save["snmp_version"] = form_input_validate($_POST["snmp_version"], "snmp_version", "", true, 3);
		$save["snmp_username"] = form_input_validate($_POST["snmp_username"], "snmp_username", "", true, 3);
		$save["snmp_password"] = form_input_validate($_POST["snmp_password"], "snmp_password", "", true, 3);
		$save["snmp_port"] = form_input_validate($_POST["snmp_port"], "snmp_port", "^[0-9]+$", false, 3);
		$save["snmp_timeout"] = form_input_validate($_POST["snmp_timeout"], "snmp_timeout", "^[0-9]+$", false, 3);
		$save["disabled"] = form_input_validate((isset($_POST["disabled"]) ? $_POST["disabled"] : ""), "disabled", "", true, 3);
		
		if (!is_error_message()) {
			$host_id = sql_save($save, "host");
			
			if ($host_id) {
				raise_message(1);
				
				/* push out relavant fields to data sources using this host */
				push_out_host($host_id,0);
				
				/* the host subsitution cache is now stale; purge it */
				kill_session_var("sess_host_cache_array");
				
				/* update title cache for graph and data source */
				update_data_source_title_cache_from_host($host_id);
				update_graph_title_cache_from_host($host_id);
			}else{
				raise_message(2);
			}
			
			/* if the user changes the host template, add each snmp query associated with it */
			if (($_POST["host_template_id"] != $_POST["_host_template_id"]) && ($_POST["host_template_id"] != "0")) {
				$snmp_queries = db_fetch_assoc("select snmp_query_id from host_template_snmp_query where host_template_id=" . $_POST["host_template_id"]);
				
				if (sizeof($snmp_queries) > 0) {
				foreach ($snmp_queries as $snmp_query) {
					db_execute("replace into host_snmp_query (host_id,snmp_query_id) values ($host_id," . $snmp_query["snmp_query_id"] . ")");
					
					/* recache snmp data */
					data_query($host_id, $snmp_query["snmp_query_id"]);
				}
				}
			}
		}
		
		if ((is_error_message()) || ($_POST["host_template_id"] != $_POST["_host_template_id"])) {
			header("Location: host.php?action=edit&id=" . (empty($host_id) ? $_POST["id"] : $host_id));
		}else{
			header("Location: host.php");
		}
	}
}

/* ---------------------
    Host Functions
   --------------------- */

function host_reload_query() {
	data_query($_GET["host_id"], $_GET["id"]);
}

function host_remove_query() {
	db_execute("delete from host_snmp_cache where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
	db_execute("delete from host_snmp_query where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
}

function host_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select description from host where id=" . $_GET["id"]) . "'</strong>?", $_SERVER["HTTP_REFERER"], "host.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from host where id=" . $_GET["id"]);
		db_execute("delete from host_snmp_query where host_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_cache where host_id=" . $_GET["id"]);
		
		db_execute("update data_local set host_id=0 where host_id=" . $_GET["id"]);
		db_execute("update graph_local set host_id=0 where host_id=" . $_GET["id"]);
	}
}

function host_edit() {
	global $colors, $paths, $fields_host_edit;
	
	display_output_messages();
	
	if (!empty($_GET["id"])) {
		$host = db_fetch_row("select * from host where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host["description"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>Polling Hosts</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_host_edit, (isset($host) ? $host : array()))
		));
	
	if (!empty($host["id"])) {
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td colspan="2">
			<table cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr>
					<td width="50%">
						<font class="textEditTitle">Associated Data Query</font><br>
						If you choose to add this data query to this host, information will be queried from this
						host upon addition.
					</td>
					<td width="1">
						<?php form_dropdown("snmp_query_id",db_fetch_assoc("select id,name from snmp_query order by name"),"name","id","","None","");?>
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
	}
	
	end_box();
	
	form_save_button("host.php");
}

function host() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts</strong>", "98%", $colors["header"], "3", "center", "host.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Description",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hostname",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$hosts = db_fetch_assoc("select id,hostname,description from host order by description");
	
	$i = 0;
	if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host.php?action=edit&id=<?php print $host["id"];?>"><?php print $host["description"];?></a>
			</td>
			<td>
				<?php print $host["hostname"];?>
			</td>
			<td width="1%" align="right">
				<a href="host.php?action=remove&id=<?php print $host["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	}
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}
	end_box();
}

?>
