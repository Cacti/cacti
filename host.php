<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include("./include/auth.php");
include_once("./lib/utility.php");
include_once("./lib/api_data_source.php");
include_once("./lib/api_tree.php");
include_once("./lib/html_tree.php");
include_once("./lib/api_graph.php");
include_once("./lib/snmp.php");
include_once("./lib/ping.php");
include_once("./lib/data_query.php");
include_once("./lib/api_device.php");

define("MAX_DISPLAY_PAGES", 21);

$device_actions = array(
	1 => "Delete",
	2 => "Enable",
	3 => "Disable",
	4 => "Change SNMP Options",
	5 => "Clear Statistics",
	6 => "Change Availability Options"
	);

$device_actions = api_plugin_hook_function('device_action_array', $device_actions);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

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
    Global Form Functions
   -------------------------- */

function add_tree_names_to_actions_array() {
	global $device_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc("select id,name from graph_tree order by name");

	if (sizeof($trees) > 0) {
		foreach ($trees as $tree) {
			$device_actions{"tr_" . $tree["id"]} = "Place on a Tree (" . $tree["name"] . ")";
		}
	}
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((!empty($_POST["add_dq_x"])) && (!empty($_POST["snmp_query_id"]))) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("snmp_query_id"));
		input_validate_input_number(get_request_var_post("reindex_method"));
		/* ==================================================== */

		db_execute("replace into host_snmp_query (host_id,snmp_query_id,reindex_method) values (" . $_POST["id"] . "," . $_POST["snmp_query_id"] . "," . $_POST["reindex_method"] . ")");

		/* recache snmp data */
		run_data_query($_POST["id"], $_POST["snmp_query_id"]);

		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}

	if ((!empty($_POST["add_gt_x"])) && (!empty($_POST["graph_template_id"]))) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("graph_template_id"));
		/* ==================================================== */

		db_execute("replace into host_graph (host_id,graph_template_id) values (" . $_POST["id"] . "," . $_POST["graph_template_id"] . ")");
		api_plugin_hook_function('add_graph_template_to_host', array("host_id" => $_POST["id"], "graph_template_id" => $_POST["graph_template_id"]));

		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}

	if ((isset($_POST["save_component_host"])) && (empty($_POST["add_dq_x"]))) {
		if ($_POST["snmp_version"] == 3 && ($_POST["snmp_password"] != $_POST["snmp_password_confirm"])) {
			raise_message(4);
		}else{
			input_validate_input_number(get_request_var_post("id"));
			input_validate_input_number(get_request_var_post("host_template_id"));

			$host_id = api_device_save($_POST["id"], $_POST["host_template_id"], $_POST["description"],
				trim($_POST["hostname"]), $_POST["snmp_community"], $_POST["snmp_version"],
				$_POST["snmp_username"], $_POST["snmp_password"],
				$_POST["snmp_port"], $_POST["snmp_timeout"],
				(isset($_POST["disabled"]) ? $_POST["disabled"] : ""),
				$_POST["availability_method"], $_POST["ping_method"],
				$_POST["ping_port"], $_POST["ping_timeout"],
				$_POST["ping_retries"], $_POST["notes"],
				$_POST["snmp_auth_protocol"], $_POST["snmp_priv_passphrase"],
				$_POST["snmp_priv_protocol"], $_POST["snmp_context"], $_POST["max_oids"], $_POST["device_threads"]);
		}

		header("Location: host.php?action=edit&id=" . (empty($host_id) ? $_POST["id"] : $host_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $device_actions, $fields_host_edit;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "2") { /* Enable Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("update host set disabled='' where id='" . $selected_items[$i] . "'");

				/* update poller cache */
				$data_sources = db_fetch_assoc("select id from data_local where host_id='" . $selected_items[$i] . "'");
				$poller_items = $local_data_ids = array();

				if (sizeof($data_sources) > 0) {
					foreach ($data_sources as $data_source) {
						$local_data_ids[] = $data_source["id"];
						$poller_items     = array_merge($poller_items, update_poller_cache($data_source["id"]));
					}
				}

				if (sizeof($local_data_ids)) {
					poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
				}
			}
		}elseif ($_POST["drp_action"] == "3") { /* Disable Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("update host set disabled='on' where id='" . $selected_items[$i] . "'");

				/* update poller cache */
				db_execute("delete from poller_item where host_id='" . $selected_items[$i] . "'");
				db_execute("delete from poller_reindex where host_id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "4") { /* change snmp options */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				reset($fields_host_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset($_POST["t_$field_name"])) {
						db_execute("update host set $field_name = '" . $_POST[$field_name] . "' where id='" . $selected_items[$i] . "'");
					}
				}

				push_out_host($selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "5") { /* Clear Statisitics for Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("update host set min_time = '9.99999', max_time = '0', cur_time = '0',	avg_time = '0',
						total_polls = '0', failed_polls = '0',	availability = '100.00'
						where id = '" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "6") { /* change availability options */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				reset($fields_host_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset($_POST["t_$field_name"])) {
						db_execute("update host set $field_name = '" . $_POST[$field_name] . "' where id='" . $selected_items[$i] . "'");
					}
				}

				push_out_host($selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "1") { /* delete */
			if (!isset($_POST["delete_type"])) { $_POST["delete_type"] = 2; }

			$data_sources_to_act_on = array();
			$graphs_to_act_on       = array();
			$devices_to_act_on      = array();

			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$data_sources = db_fetch_assoc("select
					data_local.id as local_data_id
					from data_local
					where " . array_to_sql_or($selected_items, "data_local.host_id"));

				if (sizeof($data_sources) > 0) {
				foreach ($data_sources as $data_source) {
					$data_sources_to_act_on[] = $data_source["local_data_id"];
				}
				}

				if ($_POST["delete_type"] == 2) {
					$graphs = db_fetch_assoc("select
						graph_local.id as local_graph_id
						from graph_local
						where " . array_to_sql_or($selected_items, "graph_local.host_id"));

					if (sizeof($graphs) > 0) {
					foreach ($graphs as $graph) {
						$graphs_to_act_on[] = $graph["local_graph_id"];
					}
					}
				}

				$devices_to_act_on[] = $selected_items[$i];
			}

			switch ($_POST["delete_type"]) {
				case '1': /* leave graphs and data_sources in place, but disable the data sources */
					api_data_source_disable_multi($data_sources_to_act_on);

					api_plugin_hook_function('data_source_remove', $data_sources_to_act_on);

					break;
				case '2': /* delete graphs/data sources tied to this device */
					api_data_source_remove_multi($data_sources_to_act_on);

					api_graph_remove_multi($graphs_to_act_on);

					api_plugin_hook_function('graphs_remove', $graphs_to_act_on);

					break;
			}

			api_device_remove_multi($devices_to_act_on);

			api_plugin_hook_function('device_remove', $devices_to_act_on);
		}elseif (preg_match("/^tr_([0-9]+)$/", $_POST["drp_action"], $matches)) { /* place on tree */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				input_validate_input_number(get_request_var_post("tree_id"));
				input_validate_input_number(get_request_var_post("tree_item_id"));
				/* ==================================================== */

				api_tree_item_save(0, $_POST["tree_id"], TREE_ITEM_TYPE_HOST, $_POST["tree_item_id"], "", 0, read_graph_config_option("default_rra_id"), $selected_items[$i], 1, 1, false);
			}
		} else {
			api_plugin_hook_function('device_action_execute', $_POST['drp_action']);
		}

		header("Location: host.php");
		exit;
	}

	/* setup some variables */
	$host_list = ""; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= "<li>" . htmlspecialchars(db_fetch_cell("select description from host where id=" . $matches[1])) . "<br>";
			$host_array[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	html_start_box("<strong>" . $device_actions[get_request_var_post("drp_action")] . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='host.php' autocomplete='off' method='post'>\n";

	if (isset($host_array) && sizeof($host_array)) {
		if ($_POST["drp_action"] == "2") { /* Enable Devices */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>To enable the following Device(s), click \"Continue\".</p>
						<p><ul>" . $host_list . "</ul></p>
					</td>
					</tr>";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Enable Device(s)'>";
		}elseif ($_POST["drp_action"] == "3") { /* Disable Devices */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>To disable the following Device(s), click \"Continue\".</p>
						<p><ul>" . $host_list . "</ul></p>
					</td>
					</tr>";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disable Device(s)'>";
		}elseif ($_POST["drp_action"] == "4") { /* change snmp options */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>To change SNMP parameters for the following Device(s), check the box next to the fields
						you want to update, fill in the new value, and click \"Continue\".</p>
						<p><ul>" . $host_list . "</ul></p>
					</td>
					</tr>";
					$form_array = array();
					while (list($field_name, $field_array) = each($fields_host_edit)) {
						if ((preg_match("/^snmp_/", $field_name)) ||
							($field_name == "max_oids")) {
							$form_array += array($field_name => $fields_host_edit[$field_name]);

							$form_array[$field_name]["value"] = "";
							$form_array[$field_name]["description"] = "";
							$form_array[$field_name]["form_id"] = 0;
							$form_array[$field_name]["sub_checkbox"] = array(
								"name" => "t_" . $field_name,
								"friendly_name" => "Update this Field",
								"value" => ""
								);
						}
					}

					draw_edit_form(
						array(
							"config" => array("no_form_tag" => true),
							"fields" => $form_array
							)
						);
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Change Device(s) SNMP Options'>";
		}elseif ($_POST["drp_action"] == "6") { /* change availability options */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>To change Availability parameters for the following Device(s), check the box next to the fields
						you want to update, fill in the new value, and click \"Continue\".</p>
						<p><ul>" . $host_list . "</ul></p>
					</td>
					</tr>";
					$form_array = array();
					while (list($field_name, $field_array) = each($fields_host_edit)) {
						if (preg_match("/(availability_method|ping_method|ping_port)/", $field_name)) {
							$form_array += array($field_name => $fields_host_edit[$field_name]);

							$form_array[$field_name]["value"] = "";
							$form_array[$field_name]["description"] = "";
							$form_array[$field_name]["form_id"] = 0;
							$form_array[$field_name]["sub_checkbox"] = array(
								"name" => "t_" . $field_name,
								"friendly_name" => "Update this Field",
								"value" => ""
								);
						}
					}

					draw_edit_form(
						array(
							"config" => array("no_form_tag" => true),
							"fields" => $form_array
							)
						);
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Change Device(s) Availability Options'>";
		}elseif ($_POST["drp_action"] == "5") { /* Clear Statisitics for Selected Devices */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>To clear the counters for the following Device(s), press the \"Continue\" button below.</p>
						<p><ul>" . $host_list . "</ul></p>
					</td>
					</tr>";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Clear Statistics on Device(s)'>";
		}elseif ($_POST["drp_action"] == "1") { /* delete */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\" the following Device(s) will be deleted.</p>
						<p><ul>" . $host_list . "</ul></p>";
						form_radio_button("delete_type", "2", "1", "Leave all Graph(s) and Data Source(s) untouched.  Data Source(s) will be disabled however.", "1"); print "<br>";
						form_radio_button("delete_type", "2", "2", "Delete all associated <strong>Graph(s)</strong> and <strong>Data Source(s)</strong>.", "1"); print "<br>";
						print "</td></tr>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Device(s)'>";
		}elseif (preg_match("/^tr_([0-9]+)$/", $_POST["drp_action"], $matches)) { /* place on tree */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Device(s) will be placed under the branch selected
						below.</p>
						<p><ul>" . $host_list . "</ul></p>
						<p><strong>Destination Branch:</strong><br>"; grow_dropdown_tree($matches[1], "tree_item_id", "0"); print "</p>
					</td>
				</tr>\n
				<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Place Device(s) on Tree'>";
		} else {
			$save['drp_action'] = $_POST['drp_action'];
			$save['host_list'] = $host_list;
			$save['host_array'] = (isset($host_array)? $host_array : array());
			api_plugin_hook_function('device_action_prepare', $save);
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one device.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* -------------------
    Data Query Functions
   ------------------- */

function host_reload_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_id"));
	/* ==================================================== */

	run_data_query($_GET["host_id"], $_GET["id"]);
}

function host_remove_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_id"));
	/* ==================================================== */

	api_device_dq_remove($_GET["host_id"], $_GET["id"]);
}

function host_remove_gt() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_id"));
	/* ==================================================== */

	api_device_gt_remove($_GET["host_id"], $_GET["id"]);
}

/* ---------------------
    Host Functions
   --------------------- */

function host_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . htmlspecialchars(db_fetch_cell("select description from host where id=" . $_GET["id"])) . "'</strong>?", htmlspecialchars("host.php"), htmlspecialchars("host.php?action=remove&id=" . $_GET["id"]));
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		api_device_remove($_GET["id"]);
	}
}

function host_edit() {
	global $colors, $fields_host_edit, $reindex_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	api_plugin_hook('host_edit_top');

	if (!empty($_GET["id"])) {
		$host = db_fetch_row("select * from host where id=" . $_GET["id"]);
		$header_label = "[edit: " . htmlspecialchars($host["description"]) . "]";
	}else{
		$header_label = "[new]";
	}

	if (!empty($host["id"])) {
		?>
		<table width="100%" align="center">
			<tr>
				<td class="textInfo" colspan="2">
					<?php print htmlspecialchars($host["description"]);?> (<?php print htmlspecialchars($host["hostname"]);?>)
				</td>
			</tr>
			<tr>
				<td class="textHeader">
				<?php if (($host["availability_method"] == AVAIL_SNMP) ||
					($host["availability_method"] == AVAIL_SNMP_GET_NEXT) ||
					($host["availability_method"] == AVAIL_SNMP_GET_SYSDESC) ||
					($host["availability_method"] == AVAIL_SNMP_AND_PING) ||
					($host["availability_method"] == AVAIL_SNMP_OR_PING)) { ?>
					SNMP Information<br>

					<span style="font-size: 10px; font-weight: normal; font-family: monospace;">
					<?php
					if ((($host["snmp_community"] == "") && ($host["snmp_username"] == "")) ||
						($host["snmp_version"] == 0)) {
						print "<span style='color: #ab3f1e; font-weight: bold;'>SNMP not in use</span>\n";
					}else{
						$snmp_system = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.1.0", $host["snmp_version"],
							$host["snmp_username"], $host["snmp_password"],
							$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
							$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"),SNMP_WEBUI);

						/* modify for some system descriptions */
						/* 0000937: System output in host.php poor for Alcatel */
						if (substr_count($snmp_system, "00:")) {
							$snmp_system = str_replace("00:", "", $snmp_system);
							$snmp_system = str_replace(":", " ", $snmp_system);
						}

						if ($snmp_system == "") {
							print "<span style='color: #ff0000; font-weight: bold;'>SNMP error</span>\n";
						}else{
							$snmp_uptime   = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.3.0", $host["snmp_version"],
								$host["snmp_username"], $host["snmp_password"],
								$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
								$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);

							$snmp_hostname = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.5.0", $host["snmp_version"],
								$host["snmp_username"], $host["snmp_password"],
								$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
								$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);

							$snmp_location = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.6.0", $host["snmp_version"],
								$host["snmp_username"], $host["snmp_password"],
								$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
								$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);

							$snmp_contact  = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.4.0", $host["snmp_version"],
								$host["snmp_username"], $host["snmp_password"],
								$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
								$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);

							print "<strong>System:</strong>" . html_split_string($snmp_system) . "<br>\n";
							$days      = intval($snmp_uptime / (60*60*24*100));
							$remainder = $snmp_uptime % (60*60*24*100);
							$hours     = intval($remainder / (60*60*100));
							$remainder = $remainder % (60*60*100);
							$minutes   = intval($remainder / (60*100));
							print "<strong>Uptime:</strong> $snmp_uptime";
							print "&nbsp;($days days, $hours hours, $minutes minutes)<br>\n";
							print "<strong>Hostname:</strong> $snmp_hostname<br>\n";
							print "<strong>Location:</strong> $snmp_location<br>\n";
							print "<strong>Contact:</strong> $snmp_contact<br>\n";
						}
					}
					?>
					</span>
				<?php }
				if (($host["availability_method"] == AVAIL_PING) ||
					($host["availability_method"] == AVAIL_SNMP_AND_PING) ||
					($host["availability_method"] == AVAIL_SNMP_OR_PING)) {
					/* create new ping socket for host pinging */
					$ping = new Net_Ping;

					$ping->host = $host;
					$ping->port = $host["ping_port"];

					/* perform the appropriate ping check of the host */
					if ($ping->ping($host["availability_method"], $host["ping_method"],
						$host["ping_timeout"], $host["ping_retries"])) {
						$host_down = false;
						$color     = "#000000";
					}else{
						$host_down = true;
						$color     = "#ff0000";
					}

				?>
					<br>Ping Results<br>
					<span style="font-size: 10px; font-weight: normal; color: <?php print $color; ?>; font-family: monospace;">
					<?php print $ping->ping_response; ?>
					</span>
				<?php }else if ($host["availability_method"] == AVAIL_NONE) { ?>
					No Availability Check In Use<br>
				<?php } ?>
				</td>
				<td class="textInfo" valign="top">
					<span style="color: #c16921;">*</span><a href="<?php print htmlspecialchars("graphs_new.php?host_id=" . $host["id"]);?>">Create Graphs for this Host</a><br>
					<span style="color: #c16921;">*</span><a href="<?php print htmlspecialchars("data_sources.php?host_id=" . $host["id"] . "&ds_rows=30&filter=&template_id=-1&method_id=-1&page=1");?>">Data Source List</a><br>
					<span style="color: #c16921;">*</span><a href="<?php print htmlspecialchars("graphs.php?host_id=" . $host["id"] . "&graph_rows=30&filter=&template_id=-1&page=1");?>">Graph List</a>
					<?php api_plugin_hook('device_edit_top_links'); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	html_start_box("<strong>Devices</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	/* preserve the host template id if passed in via a GET variable */
	if (!empty($_GET["host_template_id"])) {
		$fields_host_edit["host_template_id"]["value"] = $_GET["host_template_id"];
	}

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_host_edit, (isset($host) ? $host : array()))
		));

	/* we have to hide this button to make a form change in the main form trigger the correct
	 * submit action */
	echo "<div style='display:none;'><input type='submit' value='Default Submit Button'></div>";

	html_end_box();

	?>
	<script type="text/javascript">
	<!--

	// default snmp information
	var snmp_community       = document.getElementById('snmp_community').value;
	var snmp_username        = document.getElementById('snmp_username').value;
	var snmp_password        = document.getElementById('snmp_password').value;
	var snmp_auth_protocol   = document.getElementById('snmp_auth_protocol').value;
	var snmp_priv_passphrase = document.getElementById('snmp_priv_passphrase').value;
	var snmp_priv_protocol   = document.getElementById('snmp_priv_protocol').value;
	var snmp_context         = document.getElementById('snmp_context').value;
	var snmp_port            = document.getElementById('snmp_port').value;
	var snmp_timeout         = document.getElementById('snmp_timeout').value;
	var max_oids             = document.getElementById('max_oids').value;

	// default ping methods
	var ping_method    = document.getElementById('ping_method').value;
	var ping_port      = document.getElementById('ping_port').value;
	var ping_timeout   = document.getElementById('ping_timeout').value;
	var ping_retries   = document.getElementById('ping_retries').value;

	var availability_methods = document.getElementById('availability_method').options;
	var num_methods          = document.getElementById('availability_method').length;
	var selectedIndex        = document.getElementById('availability_method').selectedIndex;

	var agent = navigator.userAgent;
	agent = agent.match("MSIE");

	function setPingVisibility() {
		availability_method = document.getElementById('availability_method').value;
		ping_method         = document.getElementById('ping_method').value;

		/* debugging, uncomment as required */
		//alert("The availability method is '" + availability_method + "'");
		//alert("The ping method is '" + ping_method + "'");

		switch(availability_method) {
		case "0": // none
			document.getElementById('row_ping_method').style.display  = "none";
			document.getElementById('row_ping_port').style.display    = "none";
			document.getElementById('row_ping_timeout').style.display = "none";
			document.getElementById('row_ping_retries').style.display = "none";

			break;
		case "2": // snmp
		case "5": // snmp sysDesc
		case "6": // snmp getNext
			document.getElementById('row_ping_method').style.display  = "none";
			document.getElementById('row_ping_port').style.display    = "none";
			document.getElementById('row_ping_timeout').style.display = "";
			document.getElementById('row_ping_retries').style.display = "";

			break;
		default: // ping ok
			switch(ping_method) {
			case "1": // ping icmp
				document.getElementById('row_ping_method').style.display  = "";
				document.getElementById('row_ping_port').style.display    = "none";
				document.getElementById('row_ping_timeout').style.display = "";
				document.getElementById('row_ping_retries').style.display = "";

				break;
			case "2": // ping udp
			case "3": // ping tcp
				document.getElementById('row_ping_method').style.display  = "";
				document.getElementById('row_ping_port').style.display    = "";
				document.getElementById('row_ping_timeout').style.display = "";
				document.getElementById('row_ping_retries').style.display = "";

				break;
			}

			break;
		}
	}

	function addSelectItem(item, formObj) {
		if (agent != "MSIE") {
			formObj.add(item,null); // standards compliant
		}else{
			formObj.add(item);      // IE only
		}
	}

	function setAvailability(type) {
		/* get the availability structure */
		var am=document.getElementById('availability_method');

		/* get current selectedIndex */
		selectedIndex = document.getElementById('availability_method').selectedIndex;

		/* debugging uncomment as required */
		//alert("The selectedIndex is '" + selectedIndex + "'");
		//alert("The array length is '" + am.length + "'");

		switch(type) {
		case "NoSNMP":
			/* remove snmp options */
			if (am.length == 7) {
				am.remove(1);
				am.remove(1);
				am.remove(1);
				am.remove(1);
				am.remove(1);
			}

			/* set the index to something valid, like "ping" */
			if (selectedIndex > 1) {
				am.selectedIndex=1;
			}

			break;
		case "All":
			/* restore all options */
			if (am.length == 2) {
				am.remove(0);
				am.remove(0);

				var a=document.createElement('option');
				var b=document.createElement('option');
				var c=document.createElement('option');
				var d=document.createElement('option');
				var e=document.createElement('option');
				var f=document.createElement('option');
				var g=document.createElement('option');

				a.value="0";
				a.text="None";
				addSelectItem(a,am);

				b.value="1";
				b.text="Ping and SNMP Uptime";
				addSelectItem(b,am);

				e.value="4";
				e.text="Ping or SNMP Uptime";
				addSelectItem(e,am);

				c.value="2";
				c.text="SNMP Uptime";
				addSelectItem(c,am);

				f.value="5";
				f.text="SNMP Desc";
				addSelectItem(f,am);

				g.value="6";
				g.text="SNMP getNext";
				addSelectItem(g,am);

				d.value="3";
				d.text="Ping";
				addSelectItem(d,am);

				/* restore the correct index number */
				if (selectedIndex == 0) {
					am.selectedIndex = 0;
				}else{
					am.selectedIndex = 3;
				}
			}

			break;
		}

		setAvailabilityVisibility(type, am.selectedIndex);
		setPingVisibility();
	}

	function setAvailabilityVisibility(type, selectedIndex) {
		switch(type) {
		case "NoSNMP":
			switch(selectedIndex) {
			case "0": // availability none
				document.getElementById('row_ping_method').style.display="none";
				document.getElementById('ping_method').value=0;

				break;
			case "1": // ping
				document.getElementById('row_ping_method').style.display="";
				document.getElementById('ping_method').value=ping_method;

				break;
			}
		case "All":
			switch(selectedIndex) {
			case "0": // availability none
				document.getElementById('row_ping_method').style.display="none";
				document.getElementById('ping_method').value=0;

				break;
			case "1": // ping and snmp sysUptime
			case "3": // ping
			case "4": // ping or snmp sysUptime
				if ((document.getElementById('row_ping_method').style.display == "none") ||
					(document.getElementById('row_ping_method').style.display == undefined)) {
					document.getElementById('ping_method').value=ping_method;
					document.getElementById('row_ping_method').style.display="";
				}

				break;
			case "2": // snmp sysUptime
			case "5": // snmp sysDesc
			case "6": // snmp getNext
				document.getElementById('row_ping_method').style.display="none";
				document.getElementById('ping_method').value="0";

				break;
			}
		}
	}

	function changeHostForm() {
		snmp_version        = document.getElementById('snmp_version').value;

		switch(snmp_version) {
		case "0":
			setAvailability("NoSNMP");
			setSNMP("None");

			break;
		case "1":
		case "2":
			setAvailability("All");
			setSNMP("v1v2");

			break;
		case "3":
			setAvailability("All");
			setSNMP("v3");

			break;
		}
	}

	function setSNMP(snmp_type) {
		switch(snmp_type) {
		case "None":
			document.getElementById('row_snmp_username').style.display        = "none";
			document.getElementById('row_snmp_password').style.display        = "none";
			document.getElementById('row_snmp_community').style.display       = "none";
			document.getElementById('row_snmp_auth_protocol').style.display   = "none";
			document.getElementById('row_snmp_priv_passphrase').style.display = "none";
			document.getElementById('row_snmp_priv_protocol').style.display   = "none";
			document.getElementById('row_snmp_context').style.display         = "none";
			document.getElementById('row_snmp_port').style.display            = "none";
			document.getElementById('row_snmp_timeout').style.display         = "none";
			document.getElementById('row_max_oids').style.display             = "none";

			break;
		case "v1v2":
			document.getElementById('row_snmp_username').style.display        = "none";
			document.getElementById('row_snmp_password').style.display        = "none";
			document.getElementById('row_snmp_community').style.display       = "";
			document.getElementById('row_snmp_auth_protocol').style.display   = "none";
			document.getElementById('row_snmp_priv_passphrase').style.display = "none";
			document.getElementById('row_snmp_priv_protocol').style.display   = "none";
			document.getElementById('row_snmp_context').style.display         = "none";
			document.getElementById('row_snmp_port').style.display            = "";
			document.getElementById('row_snmp_timeout').style.display         = "";
			document.getElementById('row_max_oids').style.display             = "";

			break;
		case "v3":
			document.getElementById('row_snmp_username').style.display        = "";
			document.getElementById('row_snmp_password').style.display        = "";
			document.getElementById('row_snmp_community').style.display       = "none";
			document.getElementById('row_snmp_auth_protocol').style.display   = "";
			document.getElementById('row_snmp_priv_passphrase').style.display = "";
			document.getElementById('row_snmp_priv_protocol').style.display   = "";
			document.getElementById('row_snmp_context').style.display         = "";
			document.getElementById('row_snmp_port').style.display            = "";
			document.getElementById('row_snmp_timeout').style.display         = "";
			document.getElementById('row_max_oids').style.display             = "";

			break;
		}
	}

	function addLoadEvent(func) {
		var oldonload = window.onload;
		if (typeof window.onload != 'function') {
			window.onload = func;
		} else {
			window.onload = function() {
				if (oldonload) {
					oldonload();
				}
				func();
			}
		}
	}

	addLoadEvent(changeHostForm);

	-->
	</script>
	<?php

	if ((isset($_GET["display_dq_details"])) && (isset($_SESSION["debug_log"]["data_query"]))) {
		html_start_box("<strong>Data Query Debug Information</strong>", "100%", $colors["header"], "3", "center", "");

		print "<tr><td><span style='font-family: monospace;'>" . debug_log_return("data_query") . "</span></td></tr>";

		html_end_box();
	}

	if (!empty($host["id"])) {
		html_start_box("<strong>Associated Graph Templates</strong>", "100%", $colors["header"], "3", "center", "");

		html_header(array("Graph Template Name", "Status"), 2);

		$selected_graph_templates = db_fetch_assoc("select
			graph_templates.id,
			graph_templates.name
			from (graph_templates,host_graph)
			where graph_templates.id=host_graph.graph_template_id
			and host_graph.host_id=" . $_GET["id"] . "
			order by graph_templates.name");

		$available_graph_templates = db_fetch_assoc("SELECT
			graph_templates.id, graph_templates.name
			FROM snmp_query_graph RIGHT JOIN graph_templates
			ON (snmp_query_graph.graph_template_id = graph_templates.id)
			WHERE (((snmp_query_graph.name) Is Null)) ORDER BY graph_templates.name");

		$i = 0;
		if (sizeof($selected_graph_templates) > 0) {
		foreach ($selected_graph_templates as $item) {
			$i++;

			/* get status information for this graph template */
			$is_being_graphed = (sizeof(db_fetch_assoc("select id from graph_local where graph_template_id=" . $item["id"] . " and host_id=" . $_GET["id"])) > 0) ? true : false;

			?>
			<tr>
				<td style="padding: 4px;">
					<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item["name"]);?>
				</td>
				<td>
					<?php print (($is_being_graphed == true) ? "<span style='color: green;'>Is Being Graphed</span> (<a href='" . htmlspecialchars("graphs.php?action=graph_edit&id=" . db_fetch_cell("select id from graph_local where graph_template_id=" . $item["id"] . " and host_id=" . $_GET["id"] . " limit 0,1")) . "'>Edit</a>)" : "<span style='color: #484848;'>Not Being Graphed</span>");?>
				</td>
				<td align='right' nowrap>
					<a href='<?php print htmlspecialchars("host.php?action=gt_remove&id=" . $item["id"] . "&host_id=" . $_GET["id"]);?>'><img src='images/delete_icon_large.gif' title='Delete Graph Template Association' alt='Delete Graph Template Association' border='0' align='middle'></a>
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
						<?php form_dropdown("graph_template_id",$available_graph_templates,"name","id","","","");?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_gt_x" title="Add Graph Template to Host">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box("<strong>Associated Data Queries</strong>", "100%", $colors["header"], "3", "center", "");

		html_header(array("Data Query Name", "Debugging", "Re-Index Method", "Status"), 2);

		$selected_data_queries = db_fetch_assoc("select
			snmp_query.id,
			snmp_query.name,
			host_snmp_query.reindex_method
			from (snmp_query,host_snmp_query)
			where snmp_query.id=host_snmp_query.snmp_query_id
			and host_snmp_query.host_id=" . $_GET["id"] . "
			order by snmp_query.name");

		$available_data_queries = db_fetch_assoc("select
			snmp_query.id,
			snmp_query.name
			from snmp_query
			order by snmp_query.name");

		$keeper = array();
		foreach ($available_data_queries as $item) {
			if (sizeof(db_fetch_assoc("SELECT snmp_query_id FROM host_snmp_query " .
					" WHERE ((host_id=" . $_GET["id"] . ")" .
					" and (snmp_query_id=" . $item["id"] ."))")) > 0) {
				/* do nothing */
			} else {
				array_push($keeper, $item);
			}
		}

		$available_data_queries = $keeper;

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
					<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item["name"]);?>
				</td>
				<td>
					(<a href="<?php print htmlspecialchars("host.php?action=query_verbose&id=" . $item["id"] . "&host_id=" . $_GET["id"]);?>">Verbose Query</a>)
				</td>
				<td>
					<?php print $reindex_types{$item["reindex_method"]};?>
				</td>
				<td>
					<?php print (($status == "success") ? "<span style='color: green;'>Success</span>" : "<span style='color: green;'>Fail</span>");?> [<?php print $num_dq_items;?> Item<?php print ($num_dq_items == 1 ? "" : "s");?>, <?php print $num_dq_rows;?> Row<?php print ($num_dq_rows == 1 ? "" : "s");?>]
				</td>
				<td align='right' nowrap>
					<a href='<?php print htmlspecialchars("host.php?action=query_reload&id=" . $item["id"] . "&host_id=" . $_GET["id"]);?>'><img src='images/reload_icon_small.gif' title='Reload Data Query' alt='Reload Data Query' border='0' align='middle'></a>&nbsp;
					<a href='<?php print htmlspecialchars("host.php?action=query_remove&id=" . $item["id"] . "&host_id=" . $_GET["id"]);?>'><img src='images/delete_icon_large.gif' title='Delete Data Query Association' alt='Delete Data Query Association' border='0' align='middle'></a>
				</td>
			</tr>
			<?php
		}
		}else{ print "<tr><td><em>No associated data queries.</em></td></tr>"; }

		?>
		<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
			<td colspan="5">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Data Query:&nbsp;
						<?php form_dropdown("snmp_query_id",$available_data_queries,"name","id","","","");?>
					</td>
					<td nowrap>Re-Index Method:&nbsp;
						<?php form_dropdown("reindex_method",$reindex_types,"","",read_config_option("reindex_method"),"","");?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_dq_x" title="Add Data Query to Host">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();
	}

	form_save_button("host.php", "return");

	api_plugin_hook('host_edit_bottom');
}

function host() {
	global $colors, $device_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("host_status"));
	input_validate_input_number(get_request_var_request("host_rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_device_current_page");
		kill_session_var("sess_device_filter");
		kill_session_var("sess_device_host_template_id");
		kill_session_var("sess_host_status");
		kill_session_var("sess_host_rows");
		kill_session_var("sess_host_sort_column");
		kill_session_var("sess_host_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["host_status"]);
		unset($_REQUEST["host_rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	if ((!empty($_SESSION["sess_host_status"])) && (!empty($_REQUEST["host_status"]))) {
		if ($_SESSION["sess_host_status"] != $_REQUEST["host_status"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_device_current_page", "1");
	load_current_session_value("filter", "sess_device_filter", "");
	load_current_session_value("host_template_id", "sess_device_host_template_id", "-1");
	load_current_session_value("host_status", "sess_host_status", "-1");
	load_current_session_value("host_rows", "sess_host_rows", read_config_option("num_rows_device"));
	load_current_session_value("sort_column", "sess_host_sort_column", "description");
	load_current_session_value("sort_direction", "sess_host_sort_direction", "ASC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["host_rows"] == -1) {
		$_REQUEST["host_rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '?host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&host_rows=' + objForm.host_rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Devices</strong>", "100%", $colors["header"], "3", "center", "host.php?action=edit&host_template_id=" . htmlspecialchars(get_request_var_request("host_template_id")) . "&host_status=" . htmlspecialchars(get_request_var_request("host_status")));

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_devices" action="host.php">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("host_template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template["id"] . "'"; if (get_request_var_request("host_template_id") == $host_template["id"]) { print " selected"; } print ">" . htmlspecialchars($host_template["name"]) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="host_status" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("host_status") == "-1") {?> selected<?php }?>>Any</option>
							<option value="-3"<?php if (get_request_var_request("host_status") == "-3") {?> selected<?php }?>>Enabled</option>
							<option value="-2"<?php if (get_request_var_request("host_status") == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="-4"<?php if (get_request_var_request("host_status") == "-4") {?> selected<?php }?>>Not Up</option>
							<option value="3"<?php if (get_request_var_request("host_status") == "3") {?> selected<?php }?>>Up</option>
							<option value="1"<?php if (get_request_var_request("host_status") == "1") {?> selected<?php }?>>Down</option>
							<option value="2"<?php if (get_request_var_request("host_status") == "2") {?> selected<?php }?>>Recovering</option>
							<option value="0"<?php if (get_request_var_request("host_status") == "0") {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows per Page:&nbsp;
					</td>
					<td width="1">
						<select name="host_rows" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("host_rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("host_rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "where (host.hostname like '%%" . get_request_var_request("filter") . "%%' OR host.description like '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("host_status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_status") == "-2") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled='on'" : "where host.disabled='on'");
	}elseif (get_request_var_request("host_status") == "-3") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled=''" : "where host.disabled=''");
	}elseif (get_request_var_request("host_status") == "-4") {
		$sql_where .= (strlen($sql_where) ? " and (host.status!='3' or host.disabled='on')" : "where (host.status!='3' or host.disabled='on')");
	}else {
		$sql_where .= (strlen($sql_where) ? " and (host.status=" . get_request_var_request("host_status") . " AND host.disabled = '')" : "where (host.status=" . get_request_var_request("host_status") . " AND host.disabled = '')");
	}

	if (get_request_var_request("host_template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_template_id") == "0") {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=0" : "where host.host_template_id=0");
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=" . get_request_var_request("host_template_id") : "where host.host_template_id=" . get_request_var_request("host_template_id"));
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='host.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("select
		COUNT(host.id)
		from host
		$sql_where");

	$sortby = get_request_var_request("sort_column");
	if ($sortby=="hostname") {
		$sortby = "INET_ATON(hostname)";
	}

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	$sql_query = "SELECT *
		FROM host
		$sql_where
		ORDER BY " . $sortby . " " . get_request_var_request("sort_direction") . "
		LIMIT " . (get_request_var_request("host_rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("host_rows");

	$hosts = db_fetch_assoc($sql_query);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("host_rows"), $total_rows, "host.php?filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='11'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("host.php?filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("host_rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("host_rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("host_rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("host.php?filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"description" => array("Description", "ASC"),
		"id" => array("ID", "ASC"),
		"nosort1" => array("Graphs", "ASC"),
		"nosort2" => array("Data Sources", "ASC"),
		"status" => array("Status", "ASC"),
		"status_rec_date" => array("In State", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"cur_time" => array("Current (ms)", "DESC"),
		"avg_time" => array("Average (ms)", "DESC"),
		"availability" => array("Availability", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($hosts) > 0) {
		foreach ($hosts as $host) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $host["id"]); $i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("host.php?action=edit&id=" . $host["id"]) . "'>" .
				(strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["description"])) : htmlspecialchars($host["description"])) . "</a>", $host["id"], 250);
			form_selectable_cell(round(($host["id"]), 2), $host["id"]);
			form_selectable_cell((isset($host_graphs[$host["id"]]) ? $host_graphs[$host["id"]] : 0), $host["id"]);
			form_selectable_cell((isset($host_data_sources[$host["id"]]) ? $host_data_sources[$host["id"]] : 0), $host["id"]);
			form_selectable_cell(get_colored_device_status(($host["disabled"] == "on" ? true : false), $host["status"]), $host["id"]);
			form_selectable_cell(get_timeinstate($host), $host["id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["hostname"])) : htmlspecialchars($host["hostname"])), $host["id"]);
			form_selectable_cell(round(($host["cur_time"]), 2), $host["id"]);
			form_selectable_cell(round(($host["avg_time"]), 2), $host["id"]);
			form_selectable_cell(round($host["availability"], 2), $host["id"]);
			form_checkbox_cell($host["description"], $host["id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}
	html_end_box(false);

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	print "</form>\n";
}

function get_timeinstate($host) {
        $interval = read_config_option("poller_interval");
        if ($host['status_event_count'] > 0) {
                $time = $host['status_event_count'] * $interval;
        }elseif (strtotime($host['status_rec_date']) > 943916400) {
                $time = time() - strtotime($host['status_rec_date']);
        }else{
                return "-";
        }

        if ($time > 86400) {
                $days  = floor($time/86400);
                $time %= 86400;
        }else{
                $days  = 0;
        }

        if ($time > 3600) {
                $hours = floor($time/3600);
                $time  %= 3600;
        }else{
                $hours = 0;
        }

        $minutes = floor($time/60);

        return $days . "d " . $hours . "h " . $minutes . "m";
}


?>

