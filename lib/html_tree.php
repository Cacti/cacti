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

function grow_graph_tree($tree_id, $start_branch, $user_id, $options) {
	global $colors, $current_user, $config, $graph_timeshifts;

	include($config["include_path"] . "/global_arrays.php");
	include_once($config["library_path"] . "/tree.php");

	$search_key = "";
	$already_open = false;
	$hide_until_tier = false;
	$graph_ct = 0;
	$sql_where = "";
	$sql_join  = "";

	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($start_branch != "") && ($start_branch != "0")) {
		$order_key = db_fetch_cell("select order_key from graph_tree_items where id=$start_branch");

		$search_key = substr($order_key, 0, (tree_tier($order_key) * CHARS_PER_TIER));
	}

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
		$sql_where = (empty($sql_where) ? "" : "and (" . $sql_where . " OR graph_tree_items.local_graph_id=0)");
		$sql_join = "left join graph_local on (graph_templates_graph.local_graph_id=graph_local.id)
			left join graph_templates on (graph_templates.id=graph_local.graph_template_id)
			left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))";
	}

	/* include time span selector */
	html_start_box("<strong>Graph Filters</strong>", "100%", $colors["header"], "3", "center", "");

	if (read_graph_config_option("timespan_sel") == "on") {
		?>
		<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
			<td class="noprint">
			<form style="margin:0px;padding:0px;" name="form_timespan_selector" method="post" action="graph_view.php">
				<table width="100%" cellpadding="0" cellspacing="0">
					<tr>
						<td nowrap style='white-space: nowrap;' width='55'>
							&nbsp;<strong>Presets:</strong>&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;' width='130'>
							<select name='predefined_timespan' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
								<?php
								if ($_SESSION["custom"]) {
									$graph_timespans[GT_CUSTOM] = "Custom";
									$start_val = 0;
									$end_val = sizeof($graph_timespans);
								} else {
									if (isset($graph_timespans[GT_CUSTOM])) {
										asort($graph_timespans);
										array_shift($graph_timespans);
									}
									$start_val = 1;
									$end_val = sizeof($graph_timespans)+1;
								}

								if (sizeof($graph_timespans) > 0) {
									for ($value=$start_val; $value < $end_val; $value++) {
										print "<option value='$value'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap;' width='30'>
							&nbsp;<strong>From:</strong>&nbsp;
						</td>
						<td width='150' nowrap style='white-space: nowrap;'>
							<input type='text' name='date1' id='date1' title='Graph Begin Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date1"]) ? $_SESSION["sess_current_date1"] : "");?>'>
							&nbsp;<input style='padding-bottom:8px;' type='image' src='images/calendar.gif' align='middle' alt='Start date selector' title='Start date selector' onclick="return showCalendar('date1');">&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;' width='20'>
							&nbsp;<strong>To:</strong>&nbsp;
						</td>
						<td width='150' nowrap style='white-space: nowrap;'>
							<input type='text' name='date2' id='date2' title='Graph End Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date2"]) ? $_SESSION["sess_current_date2"] : "");?>'>
							&nbsp;<input style='padding-bottom:8px;' type='image' src='images/calendar.gif' align='middle' alt='End date selector' title='End date selector' onclick="return showCalendar('date2');">
						</td>
						<td width='130' nowrap style='white-space: nowrap;'>
							&nbsp;&nbsp;<input style='padding-bottom:8px;' type='image' name='move_left' src='images/move_left.gif' align='middle' alt='Left' title='Shift Left'>
							<select name='predefined_timeshift' title='Define Shifting Interval' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION["sess_current_timeshift"] == $shift_value) { print " selected"; } print ">" . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
							<input style='padding-bottom:8px;' type='image' name='move_right' src='images/move_right.gif' align='middle' alt='Right' title='Shift Right'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;&nbsp;<input type='submit' name='button_refresh_x' value='Refresh' title='Refresh selected time span'>
							<input type='submit' name='button_clear_x' value='Clear' title='Return to the default time span'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php

		html_end_box();
	}

	$hier_sql = "select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.rra_id,
		graph_tree_items.host_id,
		graph_tree_items.order_key,
		graph_templates_graph.title_cache as graph_title,
		CONCAT_WS('',host.description,' (',host.hostname,')') as hostname,
		settings_tree.status
		from graph_tree_items
		left join graph_templates_graph on (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id and graph_tree_items.local_graph_id>0)
		left join settings_tree on (graph_tree_items.id=settings_tree.graph_tree_item_id and settings_tree.user_id=$user_id)
		left join host on (graph_tree_items.host_id=host.id)
		$sql_join
		where graph_tree_items.graph_tree_id=$tree_id
		and graph_tree_items.order_key like '$search_key%'
		$sql_where
		order by graph_tree_items.order_key";

	$hierarchy = db_fetch_assoc($hier_sql);

	print "<!-- <P>Building Hierarchy w/ " . sizeof($hierarchy) . " leaves</P>  -->\n";

	/* include graph view filter selector */
	html_start_box("", "100%", $colors["header"], "1", "center", "");

	print "<tr bgcolor='#" . $colors["header"] . "'>
		<td colspan='30'>
			<table cellspacing='0' cellpadding='3' width='100%'>
				<tr>
					<td class='textHeaderDark'>
						<strong><a class='linkOverDark' href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"]) . "'>[root]</a> - " . db_fetch_cell("select name from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) . "</strong>
					</td>
				</tr>
			</table>
		</td>
	</tr>";

	$i = 0;

	/* loop through each tree item */
	if (sizeof($hierarchy) > 0) {
	foreach ($hierarchy as $leaf) {
		/* find out how 'deep' this item is */
		$tier = tree_tier($leaf["order_key"]);

		/* find the type of the current branch */
		if ($leaf["title"] != "") { $current_leaf_type = "heading"; }elseif (!empty($leaf["local_graph_id"])) { $current_leaf_type = "graph"; }else{ $current_leaf_type = "host"; }

		/* find the type of the next branch. make sure the next item exists first */
		if (isset($hierarchy{$i+1})) {
			if ($hierarchy{$i+1}["title"] != "") { $next_leaf_type = "heading"; }elseif (!empty($hierarchy{$i+1}["local_graph_id"])) { $next_leaf_type = "graph"; }else{ $next_leaf_type = "host"; }
		}else{
			$next_leaf_type = "";
		}

		if ((($current_leaf_type == 'heading') || ($current_leaf_type == 'host')) && (($tier <= $hide_until_tier) || ($hide_until_tier == false))) {
			$current_title = (($current_leaf_type == "heading") ? $leaf["title"] : $leaf["hostname"]);

			/* draw heading */
			draw_tree_header_row($tree_id, $leaf["id"], $tier, $current_title, true, $leaf["status"], true);

			/* this is an open host, lets expand a bit */
			if (($current_leaf_type == "host") && (empty($leaf["status"]))) {
				/* get a list of all graph templates in use by this host */
				$graph_templates = db_fetch_assoc("select
					graph_templates.id,
					graph_templates.name
					from (graph_local,graph_templates,graph_templates_graph)
					where graph_local.id=graph_templates_graph.local_graph_id
					and graph_templates_graph.graph_template_id=graph_templates.id
					and graph_local.host_id=" . $leaf["host_id"] . "
					group by graph_templates.id
					order by graph_templates.name");

				if (sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $graph_template) {
					draw_tree_header_row($tree_id, $leaf["id"], ($tier+1), $graph_template["name"], false, $leaf["status"], false);

					/* get a list of each graph using this graph template for this particular host */
					$graphs = db_fetch_assoc("select
						graph_templates_graph.title_cache,
						graph_templates_graph.local_graph_id
						from (graph_local,graph_templates,graph_templates_graph)
						where graph_local.id=graph_templates_graph.local_graph_id
						and graph_templates_graph.graph_template_id=graph_templates.id
						and graph_local.graph_template_id=" . $graph_template["id"] . "
						and graph_local.host_id=" . $leaf["host_id"] . "
						order by graph_templates_graph.title_cache");

					$graph_ct = 0;
					if (sizeof($graphs) > 0) {
					foreach ($graphs as $graph) {
						/* incriment graph counter so we know when to start a new row or not */
						$graph_ct++;

						if (!isset($graphs[$graph_ct])) { $next_leaf_type = "heading"; }else{ $next_leaf_type = "graph"; }

						/* draw graph */
						$already_open = draw_tree_graph_row($already_open, $graph_ct, $next_leaf_type, ($tier+2), $graph["local_graph_id"], 1, $graph["title_cache"]);
					}
					}
				}
				}
			}

			$graph_ct = 0;
		}elseif (($current_leaf_type == 'graph') && (($tier <= $hide_until_tier) || ($hide_until_tier == false))) {
			/* incriment graph counter so we know when to start a new row or not */
			$graph_ct++;

			/* draw graph */
			$already_open = draw_tree_graph_row($already_open, $graph_ct, $next_leaf_type, $tier, $leaf["local_graph_id"], $leaf["rra_id"], $leaf["graph_title"]);
		}

		/* if we have come back to the tier that was origionally flagged, then take away the flag */
		if (($tier <= $hide_until_tier) && ($hide_until_tier != false)) {
			$hide_until_tier = false;
		}

		/* if we are supposed to hide this branch, flag it */
		if (($leaf["status"] == "1") && ($hide_until_tier == false)) {
			$hide_until_tier = $tier;
		}

		$i++;
	}
	}

	print "</tr></table></td></tr>";

	html_end_box();
}

function grow_edit_graph_tree($tree_id, $user_id, $options) {
	global $config, $colors;

	include_once($config["library_path"] . "/tree.php");

	$tree_sorting_type = db_fetch_cell("select sort_type from graph_tree where id='$tree_id'");

	$tree = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.graph_tree_id,
		graph_tree_items.local_graph_id,
		graph_tree_items.host_id,
		graph_tree_items.order_key,
		graph_tree_items.sort_children_type,
		graph_templates_graph.title_cache as graph_title,
		CONCAT_WS('',description,' (',hostname,')') as hostname
		from graph_tree_items
		left join graph_templates_graph on (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id and graph_tree_items.local_graph_id>0)
		left join host on (host.id=graph_tree_items.host_id)
		where graph_tree_items.graph_tree_id=$tree_id
		order by graph_tree_id, graph_tree_items.order_key");

	print "<!-- <P>Building Hierarchy w/ " . sizeof($tree) . " leaves</P>  -->\n";

	##  Here we go.  Starting the main tree drawing loop.

	/* change the visibility session variable if applicable */
	set_tree_visibility_status();

	$i = 0;
	if (sizeof($tree) > 0) {
	foreach ($tree as $leaf) {
		$tier = tree_tier($leaf["order_key"]);
		$transparent_indent = "<img src='images/transparent_line.gif' style='padding-right:" . (($tier-1) * 20) . "px;' style='height:1px;' align='middle' alt=''>&nbsp;";
		$sort_cache[$tier] = $leaf["sort_children_type"];

		if ($i % 2 == 0) { $row_color = $colors["form_alternate1"]; }else{ $row_color = $colors["form_alternate2"]; } $i++;

		$visible = get_visibility($leaf);

		if ($leaf["local_graph_id"] > 0) {
			if ($visible) {
				print "<td bgcolor='#$row_color'>$transparent_indent<a href='" . htmlspecialchars("tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&id=" . $leaf["id"]) . "'>" . $leaf["graph_title"] . "</a></td>\n";
				print "<td bgcolor='#$row_color'>Graph</td>";
			}
		}elseif ($leaf["title"] != "") {
			$icon = get_icon($leaf["graph_tree_id"], $leaf["order_key"]);
			if ($visible) {
				print "<td bgcolor='#$row_color'>$transparent_indent<a href='" . htmlspecialchars("tree.php?action=edit&id=" . $_GET["id"] . "&leaf_id=" . $leaf["id"] . "&subaction=change") . "'><img src='" . $icon . "' border='0'></a><a href='" . htmlspecialchars("tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&id=" . $leaf["id"]) . "'>&nbsp;<strong>" . htmlspecialchars($leaf["title"]) . "</strong></a> (<a href='" . htmlspecialchars("tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&parent_id=" . $leaf["id"]) . "'>Add</a>)</td>\n";
				print "<td bgcolor='#$row_color'>Heading</td>";
			}
		}elseif ($leaf["host_id"] > 0) {
			if ($visible) {
				print "<td bgcolor='#$row_color'>$transparent_indent<a href='" . htmlspecialchars("tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&id=" . $leaf["id"]) . "'><strong>Host:</strong> " . htmlspecialchars($leaf["hostname"]) . "</a>&nbsp;<a href='" . htmlspecialchars("host.php?action=edit&id=" . $leaf["host_id"]) . "'>(Edit host)</a></td>\n";
				print "<td bgcolor='#$row_color'>Host</td>";
			}
		}

		if ($visible) {
			if ( ((isset($sort_cache{$tier-1})) && ($sort_cache{$tier-1} != TREE_ORDERING_NONE)) || ($tree_sorting_type != TREE_ORDERING_NONE) )  {
				print "<td bgcolor='#$row_color' width='80'></td>\n";
			}else{
				print "<td bgcolor='#$row_color' width='80' align='center'>\n
					<a href='" . htmlspecialchars("tree.php?action=item_movedown&id=" . $leaf["id"] . "&tree_id=" . $_GET["id"]) . "'><img src='images/move_down.gif' border='0' alt='Move Down'></a>\n
					<a href='" . htmlspecialchars("tree.php?action=item_moveup&id=" . $leaf["id"] . "&tree_id=" . $_GET["id"]) . "'><img src='images/move_up.gif' border='0' alt='Move Up'></a>\n
					</td>\n";
			}

			print 	"<td bgcolor='#$row_color' align='right'>\n
				<a href='" . htmlspecialchars("tree.php?action=item_remove&id=" . $leaf["id"] . "&tree_id=$tree_id") . "'><img src='images/delete_icon.gif' style='height:10px;width:10px;' border='0' alt='Delete'></a>\n
				</td></tr>\n";
		}
	}
	}else{
		print "<tr><td><em>No Graph Tree Items</em></td></tr>";
	}
}

function set_tree_visibility_status() {
	if (!isset($_REQUEST["subaction"])) {
		$headers = db_fetch_assoc("SELECT graph_tree_id, order_key FROM graph_tree_items WHERE host_id='0' AND local_graph_id='0' AND graph_tree_id='" . get_request_var_request("id") . "'");

		foreach ($headers as $header) {
			$variable = "sess_tree_leaf_expand_" . $header["graph_tree_id"] . "_" . tree_tier_string($header["order_key"]);

			if (!isset($_SESSION[$variable])) {
				$_SESSION[$variable] = true;
			}
		}
	}else if ((get_request_var_request("subaction") == "expand_all") ||
		(get_request_var_request("subaction") == "collapse_all")) {

		$headers = db_fetch_assoc("SELECT graph_tree_id, order_key FROM graph_tree_items WHERE host_id='0' AND local_graph_id='0' AND graph_tree_id='" . get_request_var_request("id") . "'");

		foreach ($headers as $header) {
			$variable = "sess_tree_leaf_expand_" . $header["graph_tree_id"] . "_" . tree_tier_string($header["order_key"]);

			if (get_request_var_request("subaction") == "expand_all") {
				$_SESSION[$variable] = true;
			}else{
				$_SESSION[$variable] = false;
			}
		}
	}else{
		$order_key = db_fetch_cell("SELECT order_key FROM graph_tree_items WHERE id=" . get_request_var_request("leaf_id"));
		$variable = "sess_tree_leaf_expand_" . get_request_var_request("id") . "_" . tree_tier_string($order_key);

		if (isset($_SESSION[$variable])) {
			if ($_SESSION[$variable]) {
				$_SESSION[$variable] = false;
			}else{
				$_SESSION[$variable] = true;
			}
		}else{
			$_SESSION[$variable] = true;
		}
	}
}

function get_visibility($leaf) {
	$tier = tree_tier($leaf["order_key"]);

	$tier_string = tree_tier_string($leaf["order_key"]);

	$variable = "sess_tree_leaf_expand_" . $leaf["graph_tree_id"] . "_" . $tier_string;

	/* you must always show the base tier */
	if ($tier <= 1) {
		return true;
	}

	/* get the default status */
	$default = true;
	if (isset($_SESSION[$variable])) {
		$default = $_SESSION[$variable];
	}

	/* now work backwards to get the current visibility stauts */
	$i = $tier;
	$effective = $default;
	while ($i > 1) {
		$i--;

		$parent_tier = tree_tier_string(substr($tier_string, 0, $i * CHARS_PER_TIER));
		$parent_variable = "sess_tree_leaf_expand_" . $leaf["graph_tree_id"] . "_" . $parent_tier;

		$effective = @$_SESSION[$parent_variable];

		if (!$effective) {
			return $effective;
		}
	}

	return $effective;
}

function get_icon($graph_tree_id, $order_key) {
	$variable = "sess_tree_leaf_expand_" . $graph_tree_id . "_" . tree_tier_string($order_key);

	if (isset($_SESSION[$variable])) {
		if ($_SESSION[$variable]) {
			$icon = "images/hide.gif";
		}else{
			$icon = "images/show.gif";
		}
	}else{
		$icon = "images/hide.gif";
	}

	return $icon;
}

/* tree_tier_string - returns the tier key information to be used to determine
   visibility status of the tree item.
   @arg $order_key - the order key of the branch to fetch the depth for
   @arg $chars_per_tier - the number of characters dedicated to each branch
     depth (tier). this is typically '3' in cacti.
   @returns - the string representing the leaf position
*/
function tree_tier_string($order_key, $chars_per_tier = CHARS_PER_TIER) {
	$new_string = preg_replace("/0+$/",'',$order_key);

	return $new_string;
}

function grow_dropdown_tree($tree_id, $form_name, $selected_tree_item_id) {
	global $colors, $config;

	include_once($config["library_path"] . "/tree.php");

	$tree = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.order_key
		from graph_tree_items
		where graph_tree_items.graph_tree_id=$tree_id
		and graph_tree_items.title != ''
		order by graph_tree_items.order_key");

	print "<select name='$form_name'>\n";
	print "<option value='0'>[root]</option>\n";

	if (sizeof($tree) > 0) {
	foreach ($tree as $leaf) {
		$tier = tree_tier($leaf["order_key"]);
		$indent = str_repeat("---", ($tier));

		if ($selected_tree_item_id == $leaf["id"]) {
			$html_selected = " selected";
		}else{
			$html_selected = "";
		}

		print "<option value='" . $leaf["id"] . "'$html_selected>$indent " . $leaf["title"] . "</option>\n";
	}
	}

	print "</select>\n";
}

function grow_dhtml_trees() {
	global $colors, $config;

	include_once($config["library_path"] . "/tree.php");
	include_once($config["library_path"] . "/data_query.php");

	?>
	<script type="text/javascript">
	<!--
	USETEXTLINKS = 1
	STARTALLOPEN = 0
	USEFRAMES = 0
	USEICONS = 0
	WRAPTEXT = 1
	PERSERVESTATE = 1
	HIGHLIGHT = 1
	<?php
	/* get current time */
	list($micro,$seconds) = explode(" ", microtime());
	$current_time = $seconds + $micro;
	$expand_hosts = read_graph_config_option("expand_hosts");

	if (!isset($_SESSION['dhtml_tree'])) {
		$dhtml_tree = create_dhtml_tree();
		$_SESSION['dhtml_tree'] = $dhtml_tree;
	}else{
		$dhtml_tree = $_SESSION['dhtml_tree'];
		if (($dhtml_tree[0] + read_graph_config_option("page_refresh") < $current_time) || ($expand_hosts != $dhtml_tree[1])) {
			$dhtml_tree = create_dhtml_tree();
			$_SESSION['dhtml_tree'] = $dhtml_tree;
		}else{
			$dhtml_tree = $_SESSION['dhtml_tree'];
		}
	}

	$total_tree_items = sizeof($dhtml_tree) - 1;

	for ($i = 2; $i <= $total_tree_items; $i++) {
		print $dhtml_tree[$i];
	}
	?>
	//-->
	</script>
	<?php
}

function create_dhtml_tree() {
	/* Record Start Time */
	list($micro,$seconds) = explode(" ", microtime());
	$start = $seconds + $micro;

	$dhtml_tree = array();

	$dhtml_tree[0] = $start;
	$dhtml_tree[1] = read_graph_config_option("expand_hosts");
	$dhtml_tree[2] = "foldersTree = gFld(\"\", \"\")\n";
	$dhtml_tree[3] = "foldersTree.xID = \"root\"\n";
	$i = 3;

	$tree_list = get_graph_tree_array();

	/* auth check for hosts on the trees */
	if (read_config_option("auth_method") != 0) {
		$current_user = db_fetch_row("select policy_hosts from user_auth where id=" . $_SESSION["sess_user_id"]);

		$sql_join = "left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")";

		if ($current_user["policy_hosts"] == "1") {
			$sql_where = "and !(user_auth_perms.user_id is not null and graph_tree_items.host_id > 0)";
		}elseif ($current_user["policy_hosts"] == "2") {
			$sql_where = "and !(user_auth_perms.user_id is null and graph_tree_items.host_id > 0)";
		}
	}else{
		$sql_join  = "";
		$sql_where = "";
	}

	if (sizeof($tree_list) > 0) {
		foreach ($tree_list as $tree) {
			$i++;
			$hierarchy = db_fetch_assoc("select
				graph_tree_items.id,
				graph_tree_items.title,
				graph_tree_items.order_key,
				graph_tree_items.host_id,
				graph_tree_items.host_grouping_type,
				host.description as hostname
				from graph_tree_items
				left join host on (host.id=graph_tree_items.host_id)
				$sql_join
				where graph_tree_items.graph_tree_id=" . $tree["id"] . "
				$sql_where
				and graph_tree_items.local_graph_id = 0
				order by graph_tree_items.order_key");

			$dhtml_tree[$i] = "ou0 = insFld(foldersTree, gFld(\"" . htmlspecialchars($tree["name"]) . "\", \"" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree["id"]) . "\"))\n";
			$i++;
			$dhtml_tree[$i] = "ou0.xID = \"tree_" . $tree["id"] . "\"\n";

			if (sizeof($hierarchy) > 0) {
				foreach ($hierarchy as $leaf) {
					$i++;
					$tier = tree_tier($leaf["order_key"]);

					if ($leaf["host_id"] > 0) {
						$dhtml_tree[$i] = "ou" . ($tier) . " = insFld(ou" . abs(($tier-1)) . ", gFld(\"" . "Host: " . htmlspecialchars($leaf["hostname"]) . "\", \"" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree["id"] . "&leaf_id=" . $leaf["id"]) . "\"))\n";
						$i++;
						$dhtml_tree[$i] = "ou" . ($tier) . ".xID = \"tree_" . $tree["id"] . "_leaf_" . $leaf["id"] . "\"\n";

						if (read_graph_config_option("expand_hosts") == "on") {
							if ($leaf["host_grouping_type"] == HOST_GROUPING_GRAPH_TEMPLATE) {
								$graph_templates = db_fetch_assoc("select
									graph_templates.id,
									graph_templates.name
									from (graph_local,graph_templates,graph_templates_graph)
									where graph_local.id=graph_templates_graph.local_graph_id
									and graph_templates_graph.graph_template_id=graph_templates.id
									and graph_local.host_id=" . $leaf["host_id"] . "
									group by graph_templates.id
									order by graph_templates.name");

								if (sizeof($graph_templates) > 0) {
									foreach ($graph_templates as $graph_template) {
										$i++;
										$dhtml_tree[$i] = "ou" . ($tier+1) . " = insFld(ou" . ($tier) . ", gFld(\" " . htmlspecialchars($graph_template["name"]) . "\", \"graph_view.php?action=tree&tree_id=" . $tree["id"] . "&leaf_id=" . $leaf["id"] . "&host_group_data=graph_template:" . $graph_template["id"] . "\"))\n";
										$i++;
										$dhtml_tree[$i] = "ou" . ($tier+1) . ".xID = \"tree_" . $tree["id"] . "_leaf_" . $leaf["id"] . "_hgd_gt_" . $graph_template["id"] . "\"\n";
									}
								}
							}else if ($leaf["host_grouping_type"] == HOST_GROUPING_DATA_QUERY_INDEX) {
								$data_queries = db_fetch_assoc("select
									snmp_query.id,
									snmp_query.name
									from (graph_local,snmp_query)
									where graph_local.snmp_query_id=snmp_query.id
									and graph_local.host_id=" . $leaf["host_id"] . "
									group by snmp_query.id
									order by snmp_query.name");

								array_push($data_queries, array(
									"id" => "0",
									"name" => "Non Query Based"
								));

								if (sizeof($data_queries) > 0) {
									foreach ($data_queries as $data_query) {
										/* fetch a list of field names that are sorted by the preferred sort field */
										$sort_field_data = get_formatted_data_query_indexes($leaf["host_id"], $data_query["id"]);
										if ($data_query["id"] == 0) {
											$non_template_graphs = db_fetch_cell("SELECT COUNT(*) FROM graph_local WHERE host_id='" . $leaf["host_id"] . "' AND snmp_query_id='0'");
										}else{
											$non_template_graphs = 0;
										}

										if ((($data_query["id"] == 0) && ($non_template_graphs > 0)) ||
											(($data_query["id"] > 0) && (sizeof($sort_field_data) > 0))) {
											$i++;
											$dhtml_tree[$i] = "ou" . ($tier+1) . " = insFld(ou" . ($tier) . ", gFld(\" " . htmlspecialchars($data_query["name"]) . "\", \"" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree["id"] . "&leaf_id=" . $leaf["id"] . "&host_group_data=data_query:" . $data_query["id"]) . "\"))\n";
											$i++;
											$dhtml_tree[$i] = "ou" . ($tier+1) . ".xID = \"tree_" . $tree["id"] . "_leaf_" . $leaf["id"] . "_hgd_dq_" . $data_query["id"] . "\"\n";

											if ($data_query["id"] > 0) {
												while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
													$i++;
													$dhtml_tree[$i] = "ou" . ($tier+2) . " = insFld(ou" . ($tier+1) . ", gFld(\" " . htmlspecialchars($sort_field_value) . "\", \"" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree["id"] . "&leaf_id=" . $leaf["id"] . "&host_group_data=data_query_index:" . $data_query["id"] . ":" . urlencode($snmp_index)) . "\"))\n";
													$i++;
													$dhtml_tree[$i] = "ou" . ($tier+2) . ".xID = \"tree_" . $tree["id"] . "_leaf_" . $leaf["id"] . "_hgd_dqi" . $data_query["id"] . "_" . urlencode($snmp_index) . "\"\n";
												}
											}
										}
									}
								}
							}
						}
					}else{
						$dhtml_tree[$i] = "ou" . ($tier) . " = insFld(ou" . abs(($tier-1)) . ", gFld(\"" . htmlspecialchars($leaf["title"]) . "\", \"" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree["id"] . "&leaf_id=" . $leaf["id"]) . "\"))\n";
						$i++;
						$dhtml_tree[$i] = "ou" . ($tier) . ".xID = \"tree_" . $tree["id"] . "_leaf_" . $leaf["id"] . "\"\n";
					}
				}
			}
		}
	}

	return $dhtml_tree;
}

function grow_right_pane_tree($tree_id, $leaf_id, $host_group_data) {
	global $current_user, $colors, $config, $graphs_per_page, $graph_timeshifts;

	include($config["include_path"] . "/global_arrays.php");
	include_once($config["library_path"] . "/data_query.php");
	include_once($config["library_path"] . "/tree.php");
	include_once($config["library_path"] . "/html_utility.php");

	define("MAX_DISPLAY_PAGES", 21);

	if (empty($tree_id)) { return; }

	$sql_where       = "";
	$sql_join        = "";
	$title           = "";
	$title_delimeter = "";
	$search_key      = "";

	$leaf      = db_fetch_row("SELECT order_key, title, host_id, host_grouping_type
					FROM graph_tree_items
					WHERE id=$leaf_id");

	$leaf_type = get_tree_item_type($leaf_id);

	/* get the "starting leaf" if the user clicked on a specific branch */
	if (!empty($leaf_id)) {
		$search_key = substr($leaf["order_key"], 0, (tree_tier($leaf["order_key"]) * CHARS_PER_TIER));
	}

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
		$sql_where = (empty($sql_where) ? "" : "AND $sql_where");
		$sql_join = "
			LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))";
	}

	/* get information for the headers */
	if (!empty($tree_id)) { $tree_name = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id"); }
	if (!empty($leaf_id)) { $leaf_name = $leaf["title"]; }
	if (!empty($leaf_id)) { $host_name = db_fetch_cell("SELECT host.description FROM (graph_tree_items,host) WHERE graph_tree_items.host_id=host.id AND graph_tree_items.id=$leaf_id"); }

	$host_group_data_array = explode(":", $host_group_data);

	if ($host_group_data_array[0] == "graph_template") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . db_fetch_cell("select name from graph_templates where id=" . $host_group_data_array[1]);
		$graph_template_id = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == "data_query") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . (empty($host_group_data_array[1]) ? "Non Query Based" : db_fetch_cell("select name from snmp_query where id=" . $host_group_data_array[1]));
		$data_query_id = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == "data_query_index") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . (empty($host_group_data_array[1]) ? "Non Query Based" : db_fetch_cell("select name from snmp_query where id=" . $host_group_data_array[1])) . "-> " . (empty($host_group_data_array[2]) ? "Template Based" : get_formatted_data_query_index($leaf["host_id"], $host_group_data_array[1], $host_group_data_array[2]));
		$data_query_id = $host_group_data_array[1];
		$data_query_index = $host_group_data_array[2];
	}

	if (!empty($tree_name)) { $title .= $title_delimeter . "<strong>Tree:</strong>" . htmlspecialchars($tree_name); $title_delimeter = "-> "; }
	if (!empty($leaf_name)) { $title .= $title_delimeter . "<strong>Leaf:</strong>" . htmlspecialchars($leaf_name); $title_delimeter = "-> "; }
	if (!empty($host_name)) { $title .= $title_delimeter . "<strong>Host:</strong>" . htmlspecialchars($host_name); $title_delimeter = "-> "; }
	if (!empty($host_group_data_name)) { $title .= $title_delimeter . " $host_group_data_name"; $title_delimeter = "-> "; }
	if (isset($_REQUEST["tree_id"])) {
		$nodeid = "tree_" . get_request_var_request("tree_id");
	}

	if (isset($_REQUEST["leaf_id"])) {
		$nodeid .= "_leaf_" . get_request_var_request("leaf_id");
	}

	if (isset($_REQUEST["host_group_data"])) {
		$type_id = explode(":", get_request_var_request("host_group_data"));

		if ($type_id[0] == "graph_template") {
			$nodeid .= "_hgd_gt_" . $type_id[1];
		}elseif ($type_id[0] == "data_query") {
			$nodeid .= "_hgd_dq_" . $type_id[1];
		}else{
			$nodeid .= "_hgd_dqi" . $type_id[1] . "_" . $type_id[2];
		}
	}

	print "<script type=\"text/javascript\">\n";
	print "<!--\n";
	print "myNode = findObj(\"$nodeid\")\n";
	print "myNode.forceOpeningOfAncestorFolders();\n";
	print "highlightObjLink(myNode)\n";
	print "//-->\n";
	print "</script>";

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post("graphs"));
	input_validate_input_number(get_request_var_post("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_post("filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["thumbnails"])) {
		$_REQUEST["thumbnails"] = sanitize_search_string(get_request_var_post("thumbnails"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_POST["clear_x"])) {
		kill_session_var("sess_graph_view_graphs");
		kill_session_var("sess_graph_view_filter");
		kill_session_var("sess_graph_view_thumbnails");
		kill_session_var("sess_graph_view_page");

		unset($_POST["graphs"]);
		unset($_REQUEST["graphs"]);
		unset($_POST["filter"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["page"]);
		unset($_POST["thumbnails"]);
		unset($_REQUEST["thumbnails"]);

		$changed = true;
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += check_changed("graphs", "sess_graph_view_graphs");
		$changed += check_changed("filter", "sess_graph_view_filter");
		$changed += check_changed("action", "sess_graph_view_action");
	}

	if (isset($_SESSION["sess_graph_view_tree_id"])) {
		if ($_SESSION["sess_graph_view_tree_id"] != $tree_id) {
			$changed += 1;
		}
	}
	$_SESSION["sess_graph_view_tree_id"] = $tree_id;

	if (isset($_SESSION["sess_graph_view_leaf_id"])) {
		if ($_SESSION["sess_graph_view_leaf_id"] != $leaf_id) {
			$changed += 1;
		}
	}
	$_SESSION["sess_graph_view_leaf_id"] = $leaf_id;

	if (isset($_SESSION["sess_graph_view_host_group_data"])) {
		if ($_SESSION["sess_graph_view_host_group_data"] != $host_group_data) {
			$changed += 1;
		}
	}
	$_SESSION["sess_graph_view_host_group_data"] = $host_group_data;

	if ($changed) {
		$_REQUEST["page"] = 1;
	}

	load_current_session_value("page",   "sess_graph_view_page",   "1");
	load_current_session_value("graphs", "sess_graph_view_graphs", read_graph_config_option("treeview_graphs_per_page"));
	load_current_session_value("filter", "sess_graph_view_filter", "");

	if (isset($_SESSION["sess_graph_view_thumbnails"])) {
		if ($_SESSION["sess_graph_view_thumbnails"] == "on") {
			if (isset($_POST["filter"])) {
				if (!isset($_POST["thumbnails"])) {
					$_SESSION["sess_graph_view_thumbnails"] = 'off';
				}
			}
		}else{
			if (isset($_POST["thumbnails"])) {
				$_SESSION["sess_graph_view_thumbnails"] = 'on';
			}
		}
	}else{
		$_SESSION["sess_graph_view_thumbnails"] = read_graph_config_option("thumbnail_section_tree_2");
		if ($_SESSION["sess_graph_view_thumbnails"] == '') {
			$_SESSION["sess_graph_view_thumbnails"] = 'off';
		}else{
			$_SESSION["sess_graph_view_thumbnails"] = 'on';
		}
	}

	html_start_box("<strong>Graph Filters</strong>", "100%", $colors["header"], "3", "center", "");
	/* include time span selector */
	if (read_graph_config_option("timespan_sel") == "on") {
		?>
		<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
			<td class="noprint">
			<form style="margin:0px;padding:0px;" name="form_timespan_selector" method="post" action="graph_view.php">
				<table cellpadding="0" cellspacing="0">
					<tr>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<strong>Presets:</strong>&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;'>
							<select name='predefined_timespan' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
								<?php
								if (isset($_SESSION["custom"])) {
									$graph_timespans[GT_CUSTOM] = "Custom";
									$start_val = 0;
									$end_val = sizeof($graph_timespans);
								} else {
									if (isset($graph_timespans[GT_CUSTOM])) {
										asort($graph_timespans);
										array_shift($graph_timespans);
									}
									$start_val = 1;
									$end_val = sizeof($graph_timespans)+1;
								}

								if (sizeof($graph_timespans) > 0) {
									for ($value=$start_val; $value < $end_val; $value++) {
										print "<option value='$value'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<strong>From:</strong>&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;'>
							<input type='text' name='date1' id='date1' title='Graph Begin Timestamp' size='15' value='<?php print (isset($_SESSION["sess_current_date1"]) ? $_SESSION["sess_current_date1"] : "");?>'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type='image' src='images/calendar.gif' align='middle' alt='Start date selector' title='Start date selector' onclick="return showCalendar('date1');">
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<strong>To:</strong>&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;'>
							<input type='text' name='date2' id='date2' title='Graph End Timestamp' size='15' value='<?php print (isset($_SESSION["sess_current_date2"]) ? $_SESSION["sess_current_date2"] : "");?>'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type='image' src='images/calendar.gif' align='middle' alt='End date selector' title='End date selector' onclick="return showCalendar('date2');">
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type='image' name='move_left' src='images/move_left.gif' align='middle' alt='Left' title='Shift Left'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<select name='predefined_timeshift' title='Define Shifting Interval' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION["sess_current_timeshift"] == $shift_value) { print " selected"; } print ">" . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type='image' name='move_right' src='images/move_right.gif' align='middle' alt='Right' title='Shift Right'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type='submit' name='button_refresh_x' value='Refresh' title='Refresh selected time span'>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<input type='submit' name='button_clear_x' value='Clear' title='Return to the default time span'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php
	}
	?>
	<tr class="noprint" bgcolor="#e5e5e5">
		<td class="noprint">
		<form style="margin:0px;padding:0px;" name="form_graph_view" method="post">
			<table cellspacing="0" cellpadding="0">
				<tr>
					<td width="55" nowrap="" style="white-space: nowrap;">
						<strong>&nbsp;Search:</strong>&nbsp;
					</td>
					<td width="130" nowrap="" style="white-space: nowrap;">
						<input size='30' name='filter' value='<?php print htmlspecialchars(get_request_var_request("filter"));?>'>
					</td>
					<td nowrap style='white-space:nowrap;' width="110">
						&nbsp;<strong>Graphs per Page:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="graphs" id="graphs" onChange="submit()">
							<?php
							if (sizeof($graphs_per_page) > 0) {
							foreach ($graphs_per_page as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("graphs") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="40">
						<label for="thumbnails"><strong>&nbsp;Thumbnails:&nbsp;</strong></label>
					</td>
					<td>
						<input type="checkbox" name="thumbnails" onClick="submit()" <?php print (($_SESSION['sess_graph_view_thumbnails'] == "on") ? "checked":"");?>>
					</td>
					<td style='white-space:nowrap;' nowrap>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filter">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php
	html_end_box();

	api_plugin_hook_function('graph_tree_page_buttons',
		array(
			'treeid' => $tree_id,
			'leafid' => $leaf_id,
			'mode' => 'tree',
			'timespan' => $_SESSION["sess_current_timespan"],
			'starttime' => get_current_graph_start(),
			'endtime' => get_current_graph_end())
	);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$graph_list = array();

	if (($leaf_type == "header") || (empty($leaf_id))) {
		if (strlen(get_request_var_request("filter"))) {
			$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%' OR graph_templates_graph.title LIKE '%" . get_request_var_request("filter") . "%')");
		}

		$graph_list = db_fetch_assoc("SELECT
			graph_tree_items.id,
			graph_tree_items.title,
			graph_tree_items.local_graph_id,
			graph_tree_items.rra_id,
			graph_tree_items.order_key,
			graph_templates_graph.height,
			graph_templates_graph.title_cache as title_cache
			FROM (graph_tree_items,graph_local)
			LEFT JOIN graph_templates_graph ON (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id AND graph_tree_items.local_graph_id>0)
			$sql_join
			WHERE graph_tree_items.graph_tree_id=$tree_id
			AND graph_local.id=graph_templates_graph.local_graph_id
			AND graph_tree_items.order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'
			AND graph_tree_items.local_graph_id>0
			$sql_where
			GROUP BY graph_tree_items.id
			ORDER BY graph_tree_items.order_key");
	}elseif ($leaf_type == "host") {
		/* graph template grouping */
		if ($leaf["host_grouping_type"] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$graph_templates = db_fetch_assoc("SELECT
				graph_templates.id,
				graph_templates.name
				FROM (graph_local,graph_templates,graph_templates_graph)
				WHERE graph_local.id=graph_templates_graph.local_graph_id
				AND graph_templates_graph.graph_template_id=graph_templates.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (empty($graph_template_id) ? "" : "AND graph_templates.id=$graph_template_id") . "
				GROUP BY graph_templates.id
				ORDER BY graph_templates.name");

			/* for graphs without a template */
			array_push($graph_templates, array(
				"id" => "0",
				"name" => "(No Graph Template)"
				));

			if (sizeof($graph_templates) > 0) {
			foreach ($graph_templates as $graph_template) {
				if (strlen(get_request_var_request("filter"))) {
					$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%')");
				}

				$graphs = db_fetch_assoc("SELECT
					graph_templates_graph.title_cache,
					graph_templates_graph.local_graph_id,
					graph_templates_graph.height
					FROM (graph_local,graph_templates_graph)
					$sql_join
					WHERE graph_local.id=graph_templates_graph.local_graph_id
					AND graph_local.graph_template_id=" . $graph_template["id"] . "
					AND graph_local.host_id=" . $leaf["host_id"] . "
					$sql_where
					ORDER BY graph_templates_graph.title_cache");

				/* let's sort the graphs naturally */
				usort($graphs, 'naturally_sort_graphs');

				if (sizeof($graphs)) {
				foreach ($graphs as $graph) {
					$graph["graph_template_name"] = $graph_template["name"];
					array_push($graph_list, $graph);
				}
				}
			}
			}
		/* data query index grouping */
		}elseif ($leaf["host_grouping_type"] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$data_queries = db_fetch_assoc("SELECT
				snmp_query.id,
				snmp_query.name
				FROM (graph_local,snmp_query)
				WHERE graph_local.snmp_query_id=snmp_query.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (!isset($data_query_id) ? "" : "and snmp_query.id=$data_query_id") . "
				GROUP BY snmp_query.id
				ORDER BY snmp_query.name");

			/* for graphs without a data query */
			if (empty($data_query_id)) {
				array_push($data_queries, array(
					"id" => "0",
					"name" => "Non Query Based"
					));
			}

			if (sizeof($data_queries) > 0) {
			foreach ($data_queries as $data_query) {
				/* fetch a list of field names that are sorted by the preferred sort field */
				$sort_field_data = get_formatted_data_query_indexes($leaf["host_id"], $data_query["id"]);

				if (strlen(get_request_var_request("filter"))) {
					$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%')");
				}

				/* grab a list of all graphs for this host/data query combination */
				$graphs = db_fetch_assoc("SELECT
					graph_templates_graph.title_cache,
					graph_templates_graph.local_graph_id,
					graph_templates_graph.height,
					graph_local.snmp_index
					FROM (graph_local, graph_templates_graph)
					$sql_join
					WHERE graph_local.id=graph_templates_graph.local_graph_id
					AND graph_local.snmp_query_id=" . $data_query["id"] . "
					AND graph_local.host_id=" . $leaf["host_id"] . "
					" . (empty($data_query_index) ? "" : "and graph_local.snmp_index='$data_query_index'") . "
					$sql_where
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache");

				/* re-key the results on data query index */
				$snmp_index_to_graph = array();
				if (sizeof($graphs) > 0) {
					/* let's sort the graphs naturally */
					usort($graphs, 'naturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph{$graph["snmp_index"]}{$graph["local_graph_id"]} = $graph["title_cache"];
						$graphs_height[$graph["local_graph_id"]] = $graph["height"];
					}
				}

				/* using the sorted data as they key; grab each snmp index from the master list */
				while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
					/* render each graph for the current data query index */
					if (isset($snmp_index_to_graph[$snmp_index])) {
						while (list($local_graph_id, $graph_title) = each($snmp_index_to_graph[$snmp_index])) {
							/* reformat the array so it's compatable with the html_graph* area functions */
							array_push($graph_list, array("data_query_name" => $data_query["name"], "sort_field_value" => $sort_field_value, "local_graph_id" => $local_graph_id, "title_cache" => $graph_title, "height" => $graphs_height[$graph["local_graph_id"]]));
						}
					}
				}
			}
			}
		}
	}

	$total_rows = sizeof($graph_list);

	/* generate page list */
	if ($total_rows > get_request_var_request("graphs")) {
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("graphs"), $total_rows, "graph_view.php?action=tree&tree_id=" . $tree_id . "&leaf_id=" . $leaf_id . (isset($_REQUEST["host_group_data"]) ? "&host_group_data=" . get_request_var_request("host_group_data") : ""));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree_id . "&leaf_id=" . $leaf_id  . (isset($_REQUEST["host_group_data"]) ? "&host_group_data=" . get_request_var_request("host_group_data") : "") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Graphs " . ((get_request_var_request("graphs")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_graph_config_option("treeview_graphs_per_page")) || ($total_rows < (get_request_var_request("graphs")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("graphs")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if ((get_request_var_request("page") * get_request_var_request("graphs")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $tree_id . "&leaf_id=" . $leaf_id  . (isset($_REQUEST["host_group_data"]) ? "&host_group_data=" . get_request_var_request("host_group_data") : "") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("graphs")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								Showing All Graphs" . (strlen(get_request_var_request("filter")) ? " [ Filter '" . htmlspecialchars(get_request_var_request("filter")) . "' Applied ]" : "") . "
							</td>
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;

	/* start graph display */
	print "<tr bgcolor='#" . $colors["header_panel"] . "'><td width='390' colspan='11' class='textHeaderDark'>$title</td></tr>";

	$i = get_request_var_request("graphs") * (get_request_var_request("page") - 1);
	$last_graph = $i + get_request_var_request("graphs");

	$new_graph_list = array();
	while ($i < $total_rows && $i < $last_graph) {
		$new_graph_list[] = $graph_list[$i];
		$i++;
	}

	if ($_SESSION["sess_graph_view_thumbnails"] == "on") {
		html_graph_thumbnail_area($new_graph_list, "", "view_type=tree&graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}else{
		html_graph_area($new_graph_list, "", "view_type=tree&graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}

	if (!empty($leaf_id)) {
		api_plugin_hook_function('tree_after',$host_name.','.get_request_var("leaf_id"));
	}

	api_plugin_hook_function('tree_view_page_end');

	print $nav;

	html_end_box();
}

function find_first_folder_url() {
	$default_tree_id = read_graph_config_option("default_tree_id");

	/* see if the user selected a default graph tree */
	$use_tree_id = 0;
	if (empty($default_tree_id)) {
		$tree_list = get_graph_tree_array();

		if (sizeof($tree_list) > 0) {
			$use_tree_id = $tree_list[0]["id"];
		}
	}else{
		$use_tree_id = $default_tree_id;
	}

	if (!empty($use_tree_id)) {
		/* find the first clickable item in the tree */
		$hierarchy = db_fetch_assoc("select
			graph_tree_items.id,
			graph_tree_items.host_id
			from graph_tree_items
			where graph_tree_items.graph_tree_id=$use_tree_id
			and graph_tree_items.local_graph_id = 0
			order by graph_tree_items.order_key");

		if (sizeof($hierarchy) > 0) {
			return "graph_view.php?action=tree&tree_id=$use_tree_id&leaf_id=" . $hierarchy[0]["id"] . "&select_first=true";
		}else{
			return "graph_view.php?action=tree&tree_id=$use_tree_id&select_first=true";
		}
	}

	return;
}

function draw_tree_header_row($tree_id, $tree_item_id, $current_tier, $current_title, $use_expand_contract, $expand_contract_status, $show_url) {
	global $colors;

	/* start the nested table for the heading */
	print "<tr><td colspan='2'><table width='100%' cellpadding='2' cellspacing='1' border='0'><tr>\n";

	/* draw one vbar for each tier */
	for ($j=0;($j<($current_tier-1));$j++) {
		print "<td width='10' bgcolor='#" . $colors["panel"] . "'></td>\n";
	}

	/* draw the '+' or '-' icons if configured to do so */
	if (($use_expand_contract) && (!empty($current_title))) {
		if ($expand_contract_status == "1") {
			$other_status = '0';
			$ec_icon = 'show';
		}else{
			$other_status = '1';
			$ec_icon =  'hide';
		}

		print "<td bgcolor='#" . $colors["panel"] . "' align='center' width='1%'><a
			href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=$tree_id&hide=$other_status&branch_id=$tree_item_id") . "'>
			<img src='images/$ec_icon.gif' border='0'></a></td>\n";
	}elseif (!($use_expand_contract) && (!empty($current_title))) {
		print "<td bgcolor='#" . $colors["panel"] . "' width='10'></td>\n";
	}

	/* draw the actual cell containing the header */
	if (!empty($current_title)) {
		print "<td bgcolor='#" . $colors["panel"] . "' NOWRAP><strong>
			" . (($show_url == true) ? "<a href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=$tree_id&start_branch=$tree_item_id") . "'>" : "") . $current_title . (($show_url == true) ? "</a>" : "") . "&nbsp;</strong></td>\n";
	}

	/* end the nested table for the heading */
	print "</tr></table></td></tr>\n";
}

function draw_tree_graph_row($already_open, $graph_counter, $next_leaf_type, $current_tier, $local_graph_id, $rra_id, $graph_title) {
	global $colors;

	/* start the nested table for the graph group */
	if ($already_open == false) {
		print "<tr><td><table width='100%' cellpadding='2' cellspacing='1'><tr>\n";

		/* draw one vbar for each tier */
		for ($j=0;($j<($current_tier-1));$j++) {
			print "<td width='10' bgcolor='#" . $colors["panel"] . "'></td>\n";
		}

		print "<td><table width='100%' cellspacing='0' cellpadding='2'><tr>\n";

		$already_open = true;
	}

	/* print out the actual graph html */
	if (read_graph_config_option("thumbnail_section_tree_1") == "on") {
		if (read_graph_config_option("timespan_sel") == "on") {
			print "<td><a href='" . htmlspecialchars("graph.php?local_graph_id=$local_graph_id&rra_id=all") . "'><img align='middle' alt='" . htmlspecialchars($graph_title) . "' class='graphimage' id='graph_$local_graph_id'
				src='" . htmlspecialchars("graph_image.php?action=view&local_graph_id=$local_graph_id&rra_id=0&graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end() . '&graph_height=' .
				read_graph_config_option("default_height") . '&graph_width=' . read_graph_config_option("default_width") . "&graph_nolegend=true") . "' border='0'></a></td>\n";

			/* if we are at the end of a row, start a new one */
			if ($graph_counter % read_graph_config_option("num_columns") == 0) {
				print "</tr><tr>\n";
			}
		}else{
			print "<td><a href='" . htmlspecialchars("graph.php?local_graph_id=$local_graph_id&rra_id=all") . "'><img align='middle' alt='" . htmlspecialchars($graph_title) . "' class='graphimage' id='graph_$local_graph_id'
				src='" . htmlspecialchars("graph_image.php?action=view&local_graph_id=$local_graph_id&rra_id=$rra_id&graph_start=" . -(db_fetch_cell("select timespan from rra where id=$rra_id")) . '&graph_height=' .
				read_graph_config_option("default_height") . '&graph_width=' . read_graph_config_option("default_width") . "&graph_nolegend=true") . "' border='0'></a></td>\n";

			/* if we are at the end of a row, start a new one */
			if ($graph_counter % read_graph_config_option("num_columns") == 0) {
				print "</tr><tr>\n";
			}
		}
	}else{
		if (read_graph_config_option("timespan_sel") == "on") {
			print "<td><a href='" . htmlspecialchars("graph.php?local_graph_id=$local_graph_id&rra_id=all") . "'><img class='graphimage' id='graph_$local_graph_id' src='graph_image.php?action=view&local_graph_id=$local_graph_id&rra_id=0&graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end() . "' border='0' alt='" . htmlspecialchars($graph_title) . "'></a></td>";
			print "</tr><tr>\n";
		}else{
			print "<td><a href='" . htmlspecialchars("graph.php?local_graph_id=$local_graph_id&rra_id=all") . "'><img class='graphimage' id='graph_$local_graph_id' src='graph_image.php?action=view&local_graph_id=$local_graph_id&rra_id=$rra_id' border='0' alt='" . htmlspecialchars($graph_title) . "'></a></td>";
			print "</tr><tr>\n";
		}
	}

	/* if we are at the end of the graph group, end the nested table */
	if ($next_leaf_type != "graph") {
		print "</tr></table></td>";
		print "</tr></table></td></tr>\n";

		$already_open = false;
	}

	return $already_open;
}

function draw_tree_dropdown($current_tree_id) {
	global $colors;

	$html = "";

	$tree_list = get_graph_tree_array();

	if (isset($_GET["tree_id"])) {
		$_SESSION["sess_view_tree_id"] = $current_tree_id;
	}

	/* if there is a current tree, make sure it still exists before going on */
	if ((!empty($_SESSION["sess_view_tree_id"])) && (db_fetch_cell("select id from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) == "")) {
		$_SESSION["sess_view_tree_id"] = 0;
	}

	/* set a default tree if none is already selected */
	if (empty($_SESSION["sess_view_tree_id"])) {
		if (db_fetch_cell("select id from graph_tree where id=" . read_graph_config_option("default_tree_id")) > 0) {
			$_SESSION["sess_view_tree_id"] = read_graph_config_option("default_tree_id");
		}else{
			if (sizeof($tree_list) > 0) {
				$_SESSION["sess_view_tree_id"] = $tree_list[0]["id"];
			}
		}
	}

	/* make the dropdown list of trees */
	if (sizeof($tree_list) > 1) {
		$html ="<form name='form_tree_id' id='form_tree_id' action='graph_view.php'>
			<td valign='middle' style='height:30px;' bgcolor='#" . $colors["panel"] . "'>\n
				<table width='100%' cellspacing='0' cellpadding='0'>\n
					<tr>\n
						<td width='200' class='textHeader'>\n
							&nbsp;&nbsp;Select a Graph Hierarchy:&nbsp;\n
						</td>\n
						<td bgcolor='#" . $colors["panel"] . "'>\n
							<select name='cbo_tree_id' onChange='window.location=document.form_tree_id.cbo_tree_id.options[document.form_tree_id.cbo_tree_id.selectedIndex].value'>\n";

		foreach ($tree_list as $tree) {
			$html .= "<option value='graph_view.php?action=tree&tree_id=" . $tree["id"] . "'";
				if ($_SESSION["sess_view_tree_id"] == $tree["id"]) { $html .= " selected"; }
				$html .= ">" . $tree["name"] . "</option>\n";
			}

		$html .= "</select>\n";
		$html .= "</td></tr></table></td></form>\n";
	}elseif (sizeof($tree_list) == 1) {
		/* there is only one tree; use it */
		//print "	<td valign='middle' height='5' colspan='3' bgcolor='#" . $colors["panel"] . "'>";
	}

	return $html;
}

function naturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}

?>
