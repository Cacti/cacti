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

include("./include/auth.php");
include_once("./lib/utility.php");
include_once("./lib/snmp.php");
include_once("./lib/data_query.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'gt_remove':
		host_remove_gt();

		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'query_remove':
		host_remove_query();

		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'query_reload':
		host_reload_query();

		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'query_verbose':
		host_reload_query();

		header("Location: host.php?action=edit&id=" . $_GET["host_id"] . "&display_dq_details=true");
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
	if ((!empty($_POST["add_dq_y"])) && (!empty($_POST["snmp_query_id"]))) {
		db_execute("replace into host_snmp_query (host_id,snmp_query_id) values (" . $_POST["id"] . "," . $_POST["snmp_query_id"] . ")");

		/* recache snmp data */
		run_data_query($_POST["id"], $_POST["snmp_query_id"]);

		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}

	if ((!empty($_POST["add_gt_y"])) && (!empty($_POST["graph_template_id"]))) {
		db_execute("replace into host_graph (host_id,graph_template_id) values (" . $_POST["id"] . "," . $_POST["graph_template_id"] . ")");

		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}

	if ((isset($_POST["save_component_host"])) && (empty($_POST["add_dq_y"]))) {
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

				/* the host substitution cache is now stale; purge it */
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
					run_data_query($host_id, $snmp_query["snmp_query_id"]);
				}
				}

				$graph_templates = db_fetch_assoc("select graph_template_id from host_template_graph where host_template_id=" . $_POST["host_template_id"]);

				if (sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $graph_template) {
					db_execute("replace into host_graph (host_id,graph_template_id) values ($host_id," . $graph_template["graph_template_id"] . ")");
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

/* -------------------
    Data Query Functions
   ------------------- */

function host_reload_query() {
	run_data_query($_GET["host_id"], $_GET["id"]);
}

function host_remove_query() {
	db_execute("delete from host_snmp_cache where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
	db_execute("delete from host_snmp_query where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
}

function host_remove_gt() {
	db_execute("delete from host_graph where graph_template_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
}

/* ---------------------
    Host Functions
   --------------------- */

function host_remove() {
	global $config;

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select description from host where id=" . $_GET["id"]) . "'</strong>?", "host.php", "host.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from host where id=" . $_GET["id"]);
		db_execute("delete from host_graph where host_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_query where host_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_cache where host_id=" . $_GET["id"]);
		db_execute("delete from data_input_data_cache where host_id=" . $_GET["id"]);
		db_execute("delete from graph_tree_items where host_id=" . $_GET["id"]);

		db_execute("update data_local set host_id=0 where host_id=" . $_GET["id"]);
		db_execute("update graph_local set host_id=0 where host_id=" . $_GET["id"]);
	}
}

function host_edit() {
	global $colors, $fields_host_edit;

	display_output_messages();

	if (!empty($_GET["id"])) {
		$host = db_fetch_row("select * from host where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host["description"] . "]";
	}else{
		$header_label = "[new]";
	}

	if (!empty($host["id"])) {
		?>
		<table width="98%" align="center">
			<tr>
				<td class="textInfo" colspan="2">
					<?php print $host["description"];?> (<?php print $host["hostname"];?>)
				</td>
			</tr>
			<tr>
				<td class="textHeader">
					SNMP Information<br>

					<span style="font-size: 10px; font-weight: normal; font-family: monospace;">
					<?php
					if (($host["snmp_community"] == "") && ($host["snmp_username"] == "")) {
						print "<span style='color: #ab3f1e; font-weight: bold;'>SNMP not in use</span>\n";
					}else{
						$snmp_system = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.1.0", $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);

						if ($snmp_system == "") {
							print "<span style='color: #ff0000; font-weight: bold;'>SNMP error</span>\n";
						}else{
							$snmp_uptime = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.3.0", $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);
							$snmp_hostname = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.5.0", $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);

							print "<strong>System:</strong> $snmp_system<br>\n";
							print "<strong>Uptime:</strong> $snmp_uptime<br>\n";
							print "<strong>Hostname:</strong> $snmp_hostname<br>\n";
						}
					}
					?>
					</span>
				</td>
				<td class="textInfo" valign="top">
					<span style="color: #c16921;">*</span><a href="graphs_new.php?host_id=<?php print $host["id"];?>">Create Graphs for this Host</a>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

	html_start_box("<strong>Devices</strong> $header_label", "98%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_host_edit, (isset($host) ? $host : array()))
		));

	html_end_box();

	if ((isset($_GET["display_dq_details"])) && (isset($_SESSION["debug_log"]["data_query"]))) {
		html_start_box("<strong>Data Query Debug Information</strong>", "98%", $colors["header"], "3", "center", "");

		print "<tr><td><span style='font-family: monospace;'>" . debug_log_return("data_query") . "</span></td></tr>";

		html_end_box();
	}

	if (!empty($host["id"])) {
		html_start_box("<strong>Associated Data Queries</strong>", "98%", $colors["header"], "3", "center", "");

		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Data Query Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Debugging",$colors["header_text"],1);
			DrawMatrixHeaderItem("Status",$colors["header_text"],1);
			DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
		print "</tr>";

		$selected_data_queries = db_fetch_assoc("select
			snmp_query.id,
			snmp_query.name
			from snmp_query,host_snmp_query
			where snmp_query.id=host_snmp_query.snmp_query_id
			and host_snmp_query.host_id=" . $_GET["id"] . "
			order by snmp_query.name");

		$i = 0;
		if (sizeof($selected_data_queries) > 0) {
		foreach ($selected_data_queries as $item) {
			$i++;

			/* get status information for this data query */
			$num_dq_items = sizeof(db_fetch_assoc("select snmp_index from host_snmp_cache where host_id=" . $_GET["id"] . " and snmp_query_id=" . $item["id"]));
			$num_dq_rows = sizeof(db_fetch_assoc("select snmp_index from host_snmp_cache where host_id=" . $_GET["id"] . " and snmp_query_id=" . $item["id"] . " group by snmp_index"));

			$status = "success";

			?>
			<tr>
				<td style="padding: 4px;">
					<strong><?php print $i;?>)</strong> <?php print $item["name"];?>
				</td>
				<td>
					(<a href="host.php?action=query_verbose&id=<?php print $item["id"];?>&host_id=<?php print $_GET["id"];?>">Verbose Query</a>)
				</td>
				<td>
					<?php print (($status == "success") ? "<span style='color: green;'>Success</span>" : "<span style='color: green;'>Fail</span>");?> [<?php print $num_dq_items;?> Item<?php print ($num_dq_items == 1 ? "" : "s");?>, <?php print $num_dq_rows;?> Row<?php print ($num_dq_rows == 1 ? "" : "s");?>]
				</td>
				<td align='right' nowrap>
					<a href='host.php?action=query_reload&id=<?php print $item["id"];?>&host_id=<?php print $_GET["id"];?>'><img src='images/reload_icon_small.gif' alt='Reload Data Query' border='0' align='absmiddle'></a>&nbsp;
					<a href='host.php?action=query_remove&id=<?php print $item["id"];?>&host_id=<?php print $_GET["id"];?>'><img src='images/delete_icon_large.gif' alt='Delete Data Query Association' border='0' align='absmiddle'></a>
				</td>
			</tr>
			<?php
		}
		}else{ print "<tr><td><em>No associated data queries.</em></td></tr>"; }

		?>
		<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
			<td colspan="4">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Data Query:&nbsp;
						<?php form_dropdown("snmp_query_id",db_fetch_assoc("select
							snmp_query.id,
							snmp_query.name
							from snmp_query left join host_snmp_query
							on (snmp_query.id=host_snmp_query.snmp_query_id and host_snmp_query.host_id=" . $_GET["id"] . ")
							where host_snmp_query.host_id is null
							order by snmp_query.name"),"name","id","","","");?>
					</td>
					<td align="right">
						&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_dq" align="absmiddle">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box("<strong>Associated Graph Templates</strong>", "98%", $colors["header"], "3", "center", "");

		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Graph Template Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Status",$colors["header_text"],1);
			DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
		print "</tr>";

		$selected_graph_templates = db_fetch_assoc("select
			graph_templates.id,
			graph_templates.name
			from graph_templates,host_graph
			where graph_templates.id=host_graph.graph_template_id
			and host_graph.host_id=" . $_GET["id"] . "
			order by graph_templates.name");

		$i = 0;
		if (sizeof($selected_graph_templates) > 0) {
		foreach ($selected_graph_templates as $item) {
			$i++;

			/* get status information for this graph template */
			$is_being_graphed = (sizeof(db_fetch_assoc("select id from graph_local where graph_template_id=" . $item["id"] . " and host_id=" . $_GET["id"])) > 0) ? true : false;

			?>
			<tr>
				<td style="padding: 4px;">
					<strong><?php print $i;?>)</strong> <?php print $item["name"];?>
				</td>
				<td>
					<?php print (($is_being_graphed == true) ? "<span style='color: green;'>Is Being Graphed</span> (<a href='graphs.php?action=graph_edit&id=" . db_fetch_cell("select id from graph_local where graph_template_id=" . $item["id"] . " and host_id=" . $_GET["id"] . " limit 0,1") . "'>Edit</a>)" : "<span style='color: #484848;'>Not Being Graphed</span>");?>
				</td>
				<td align='right' nowrap>
					<a href='host.php?action=gt_remove&id=<?php print $item["id"];?>&host_id=<?php print $_GET["id"];?>'><img src='images/delete_icon_large.gif' alt='Delete Graph Template Association' border='0' align='absmiddle'></a>
				</td>
			</tr>
			<?php
		}
		}else{ print "<tr><td><em>No associated graph templates.</em></td></tr>"; }

		?>
		<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
			<td colspan="4">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Graph Template:&nbsp;
						<?php form_dropdown("graph_template_id",db_fetch_assoc("select
							graph_templates.id,
							graph_templates.name
							from graph_templates left join host_graph
							on (graph_templates.id=host_graph.graph_template_id and host_graph.host_id=" . $_GET["id"] . ")
							where host_graph.host_id is null
							order by graph_templates.name"),"name","id","","","");?>
					</td>
					<td align="right">
						&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_gt" align="absmiddle">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();
	}

	form_save_button("host.php");
}

function host() {
	global $colors;

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_device_current_page", "1");
	load_current_session_value("filter", "sess_device_filter", "");
	load_current_session_value("host_id", "sess_graph_host_id", "-1");

	html_start_box("<strong>Devices</strong>", "98%", $colors["header"], "3", "center", "host.php?action=edit");

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
			<td align="right">
				<a href="host.php?action=remove&id=<?php print $host["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	}
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}
	html_end_box();
}

?>
