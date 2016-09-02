<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

/* html_start_box - draws the start of an HTML box with an optional title
   @arg $title - the title of this box ("" for no title)
   @arg $width - the width of the box in pixels or percent
   @arg $background_color - deprecated
   @arg $cell_padding - the amount of cell padding to use inside of the box
   @arg $align - the HTML alignment to use for the box (center, left, or right)
   @arg $add_text - the url to use when the user clicks 'Add' in the upper-right
     corner of the box ("" for no 'Add' link) */
function html_start_box($title, $width, $background_color, $cell_padding, $align, $add_text, $add_label = 'Add') {
	static $table_suffix = 1;

	$table_prefix = basename($_SERVER['PHP_SELF'], '.php');;
	if (!isempty_request_var('report')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('report'));
	} elseif (!isempty_request_var('tab')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('tab'));
	} elseif (!isempty_request_var('action')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('action'));
	}
	$table_id = $table_prefix . $table_suffix;

	if ($title != '') {
	?>
	<div id='<?php print $table_id;?>' class='cactiTable' style='width:<?php print $width;?>;text-align:<?php print $align;?>;'>
		<div>
			<div class='cactiTableTitle'><span style='padding:3px;'><?php if ($title != "") { print $title; }?></span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><?php if ($add_text != "") {?><a style='padding-right:5px;' class="linkOverDark" href="<?php print htmlspecialchars($add_text);?>"><?php print $add_label;?></a><?php }?></span></div>
		</div>
		<table id='<?php print $table_id . '_child';?>' class='cactiTable' style='padding:<?php print $cell_padding;?>px;'>
	<?php
	}else{
	?>
	<div id='<?php print $table_id;?>' class='cactiTable' style='width:<?php print $width;?>;text-align:<?php print $align;?>;'>
		<table id='<?php print $table_id . '_child';?>' class='cactiTable' style='padding:<?php print $cell_padding;?>px;'>
	<?php
	}

	$table_suffix++;
}

/* html_end_box - draws the end of an HTML box
   @arg $trailing_br (bool) - whether to draw a trailing <br> tag after ending
     the box */
function html_end_box($trailing_br = true) { ?>
		</table>
	</div>
	<?php if ($trailing_br == true) { print "<div class='break'></div>"; } ?>
<?php }

/* html_graph_area - draws an area the contains full sized graphs
   @arg $graph_array - the array to contains graph information. for each graph in the
     array, the following two keys must exist
     $arr[0]["local_graph_id"] // graph id
     $arr[0]["title_cache"] // graph title
   @arg $no_graphs_message - display this message if no graphs are found in $graph_array
   @arg $extra_url_args - extra arguments to append to the url
   @arg $header - html to use as a header
   @arg $columns - the number of columns to present */
function html_graph_area(&$graph_array, $no_graphs_message = "", $extra_url_args = "", $header = "", $columns = 0) {
	global $config;
	$i = 0; $k = 0; $j = 0;

	$num_graphs = sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds=<?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	</script>
	<?php

	if ($num_graphs > 0) {
		if ($header != "") {
			print $header;
		}

		$start = true;
		foreach ($graph_array as $graph) {
			if (isset($graph["graph_template_name"])) {
				if (isset($prev_graph_template_name)) {
					if ($prev_graph_template_name != $graph["graph_template_name"]) {
						$print  = true;
						$prev_graph_template_name = $graph["graph_template_name"];
					}else{
						$print = false;
					}
				}else{
					$print  = true;
					$prev_graph_template_name = $graph["graph_template_name"];
				}

				if ($print) {
					print "<tr class='templateHeader'>
						<td colspan='3' class='textHeaderDark'>
							" . __('Graph Template:') . ' ' . htmlspecialchars($graph["graph_template_name"]) . "
						</td>
					</tr>";
				}
			}elseif (isset($graph["data_query_name"])) {
				if (isset($prev_data_query_name)) {
					if ($prev_data_query_name != $graph["data_query_name"]) {
						$print  = true;
						$prev_data_query_name = $graph["data_query_name"];
					}else{
						$print = false;
					}
				}else{
					$print  = true;
					$prev_data_query_name = $graph["data_query_name"];
				}

				if ($print) {
					if (!$start) {
						while(($i % $columns) != 0) {
							print "<td style='text-align:center;width:" . ceil(100 / $columns) . "%;'></td>";
							$i++;
						}

						print "</tr>";
					}

					print "<tr class='tableHeader'>
							<td colspan='$columns' class='graphSubHeaderColumn textHeaderDark'>" . __('Data Query:') . ' ' . $graph["data_query_name"] . "</td>
						</tr>";
					$i = 0;
				}

				if (!isset($prev_sort_field_value) || $prev_sort_field_value != $graph["sort_field_value"]){
					$prev_sort_field_value = $graph["sort_field_value"];
					print "<tr class='templateHeader'>
						<td colspan='$columns' class='textHeaderDark'>
							" . $graph["sort_field_value"] . "
						</td>
					</tr>";
					$i = 0;
					$j = 0;
				}
			}

			if ($i == 0) {
				form_alternate_row();
				$start = false;
			}

			?>
			<td style='width:<?php print ceil(100 / $columns);?>%;'>
				<div>
				<table style='text-align:center;margin:auto;'>
					<tr>
						<td>
							<div class='graphWrapper' style='width:100%;' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print ((read_user_setting("custom_fonts") == "on") ? read_user_setting("title_size") : read_config_option("title_size"));?>'></div>
							<?php print (read_user_setting("show_graph_title") == "on" ? "<span align='center'>" . htmlspecialchars($graph["title_cache"]) . "</span>" : "");?>
						</td>
						<td id='dd<?php print $graph['local_graph_id'];?>' class='noprint graphDrillDown'>
							<?php graph_drilldown_icons($graph['local_graph_id']);?>
						</td>
					</tr>
				</table>
				<div>
			</td>
			<?php

			$i++;
			$k++;

			if (($i % $columns) == 0 && ($k < $num_graphs)) {
				$i=0;
				$j++;
				print "</tr>";
				$start = true;
			}
		}

		if (!$start) {
			while(($i % $columns) != 0) {
				print "<td style='text-align:center;width:" . ceil(100 / $columns) . "%;'></td>";
				$i++;
			}

			print "</tr>";
		}
	}else{
		if ($no_graphs_message != "") {
			print "<td><em>$no_graphs_message</em></td>";
		}
	}
}

/* html_graph_thumbnail_area - draws an area the contains thumbnail sized graphs
   @arg $graph_array - the array to contains graph information. for each graph in the
     array, the following two keys must exist
     $arr[0]["local_graph_id"] // graph id
     $arr[0]["title_cache"] // graph title
   @arg $no_graphs_message - display this message if no graphs are found in $graph_array
   @arg $extra_url_args - extra arguments to append to the url
   @arg $header - html to use as a header
   @arg $columns - the number of columns to present */
function html_graph_thumbnail_area(&$graph_array, $no_graphs_message = "", $extra_url_args = "", $header = "", $columns = 0) {
	global $config;
	$i = 0; $k = 0; $j = 0;

	$num_graphs = sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds=<?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	</script>
	<?php

	if ($num_graphs > 0) {
		if ($header != "") {
			print $header;
		}

		$start = true;
		foreach ($graph_array as $graph) {
			if (isset($graph["graph_template_name"])) {
				if (isset($prev_graph_template_name)) {
					if ($prev_graph_template_name != $graph["graph_template_name"]) {
						$prev_graph_template_name = $graph["graph_template_name"];
					}
				}else{
					$prev_graph_template_name = $graph["graph_template_name"];
				}
			}elseif (isset($graph["data_query_name"])) {
				if (isset($prev_data_query_name)) {
					if ($prev_data_query_name != $graph["data_query_name"]) {
						$print  = true;
						$prev_data_query_name = $graph["data_query_name"];
					}else{
						$print = false;
					}
				}else{
					$print  = true;
					$prev_data_query_name = $graph["data_query_name"];
				}

				if ($print) {
					if (!$start) {
						while(($i % $columns) != 0) {
							print "<td style='text-align:center;width:" . ceil(100 / $columns) . "%;'></td>";
							$i++;
						}

						print "</tr>";
					}

					print "<tr class='tableHeader'>
							<td class='graphSubHeaderColumn textHeaderDark' colspan='$columns'>" . __('Data Query:') . ' ' . $graph["data_query_name"] . "</td>
						</tr>";
					$i = 0;
				}
			}

			if ($i == 0) {
				form_alternate_row();
				$start = false;
			}

			?>
			<td style='width:<?php print ceil(100 / $columns);?>%;'>
				<table style='text-align:center;margin:auto;'>
					<tr>
						<td>
							<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print read_user_setting("default_width");?>' graph_height='<?php print read_user_setting("default_height");?>'></div>
							<?php print (read_user_setting("show_graph_title") == "on" ? "<span align='center'>" . htmlspecialchars($graph["title_cache"]) . "</span>" : "");?>
						</td>
						<td id='dd<?php print $graph['local_graph_id'];?>' class='noprint graphDrillDown'>
							<?php print graph_drilldown_icons($graph['local_graph_id'], 'graph_buttons_thumbnails');?>
						</td>
					</tr>
				</table>
			</td>
			<?php

			$i++;
			$k++;

			if (($i % $columns) == 0 && ($k < $num_graphs)) {
				$i=0;
				$j++;
				print "</tr>";
				$start = true;
			}
		}

		if (!$start) {
			while(($i % $columns) != 0) {
				print "<td style='text-align:center;width:" . ceil(100 / $columns) . "%;'></td>";
				$i++;
			}

			print "</tr>";
		}
	}else{
		if ($no_graphs_message != "") {
			print "<td><em>$no_graphs_message</em></td>";
		}
	}
}

function graph_drilldown_icons($local_graph_id, $type = 'graph_buttons') {
	global $config;

	$aggregate_url = aggregate_build_children_url($local_graph_id);

	print "<div class='iconWrapper'>\n";
	print "<a class='utils' href='#' role='link' id='graph_" . $local_graph_id . "_util'><img class='drillDown' src='" . $config['url_path'] . "images/cog.png' alt='' title='" . __('Graph Details, Zooming and Debugging Utilities') . "'></a><br>\n";
	print "<a class='csvexport' href='#' role='link' id='graph_" . $local_graph_id . "_csv'><img class='drillDown' src='" . $config['url_path'] . "images/table_go.png' alt='' title='" . __('CSV Export of Graph Data'). "'></a><br>\n";
	print "<a class='mrgt' href='#' role='link' id='graph_" . $local_graph_id . "_mrtg'><img class='drillDown' src='" . $config['url_path'] . "images/mrtg.png' alt='' title='" . __('MRTG Graph View'). "'></a><br>\n";
	if (read_config_option('realtime_enabled') == 'on') {
		print "<a class='realtime' href='#' role='link' id='graph_" . $local_graph_id . "_realtime'><img class='drillDown' src='" . $config['url_path'] . "images/chart_curve_go.png' alt='' title='" . __('Click to view just this Graph in Realtime'). "'></a><br/>\n";
	}
	if (is_realm_allowed(1043)) {
		print "<span class='spikekill' data-graph='" . $local_graph_id . "' id='graph_" . $local_graph_id . "_sk'><img id='sk" . $local_graph_id . "' class='drillDown' src='" . $config['url_path'] . "images/spikekill.gif'></span>";
		print '<br/>';
	}

	if ($aggregate_url != '') {
		print $aggregate_url;
	}

	api_plugin_hook($type, array('hook' => 'graphs_thumbnails', 'local_graph_id' => $local_graph_id, 'rra' =>  0, 'view_type' => ''));

	print "</div>\n";
}

/* html_nav_bar - draws a navigation bar which includes previous/next links as well as current
     page information
   @arg $base_url - the base URL will all filter options except page#
   @arg $max_pages - the maximum number of pages to display
   @arg $current_page - the current page in the navigation system
   @arg $rows_per_page - the number of rows that are displayed on a single page
   @arg $total_rows - the total number of rows in the navigation system
   @arg $object - the object types that is being displayed
   @arg $page_var - the object types that is being displayed
   @arg $return_to - paint the resulting page into this dom object */
function html_nav_bar($base_url, $max_pages, $current_page, $rows_per_page, $total_rows, $colspan=30, $object = 'Rows', $page_var = "page", $return_to = "") {
	if ($total_rows > $rows_per_page) {
		if (substr_count($base_url, '?') == 0) {
			$base_url = trim($base_url) . '?';
		}else{
			$base_url = trim($base_url) . '&';
		}

		$url_page_select = get_page_list($current_page, $max_pages, $rows_per_page, $total_rows, $base_url, $page_var, $return_to);

		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationPrevious'>
				" . (($current_page > 1) ? "<a href='#' onClick='goto$page_var(" . ($current_page-1) . ")'><i class='fa fa-angle-double-left previous'></i>" . __('Previous'). "</a>":"") . "
			</div>
			<div class='navBarNavigationCenter'>
				" . __('Showing %s %d to %d of %s [ %s ]', $object, (($rows_per_page*($current_page-1))+1), (($total_rows < $rows_per_page) || ($total_rows < ($rows_per_page*$current_page)) ? $total_rows : $rows_per_page*$current_page), $total_rows, $url_page_select) . "
			</div>
			<div class='navBarNavigationNext'>
				" . (($current_page*$rows_per_page) < $total_rows ? "<a href='#' onClick='goto$page_var(" . ($current_page+1) . ")'>" . __('Next'). "<i class='fa fa-angle-double-right next'></i></a>":"") . "
			</div>
		</div>\n";
	}elseif ($total_rows > 0) {
		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationNone'>
				" . __('Showing All %d %s', $total_rows, $object) . "
			</div>
		</div>\n";
	}else{
		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationNone'>
				" . __('No %s Found', $object) . "
			</div>
		</div>\n";
	}

	return $nav;
}

/* html_header_sort - draws a header row suitable for display inside of a box element.  When
     a user selects a column header, the collback function "filename" will be called to handle
     the sort the column and display the altered results.
   @arg $header_items - an array containing a list of column items to display.  The
        format is similar to the html_header, with the exception that it has three
        dimensions associated with each element (db_column => display_text, default_sort_order)
		alternatively (db_column => array('display' = 'blah', 'align' = 'blah', 'sort' = 'blah'))
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row
   @arg $url - a base url to redirect sort actions to */
function html_header_sort($header_items, $sort_column, $sort_direction, $last_item_colspan = 1, $url = '') {
	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	print "<tr class='tableHeader'>\n";

	$i = 1;
	foreach ($header_items as $db_column => $display_array) {
		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];
			if ($sort_column == $db_column) {
				$icon = $sort_direction;
				$direction = $new_sort_direction;
				$isSort = true;
			}else{
				$icon = '';
				if (isset($display_array['sort'])) {
					$direction = $display_array['sort'];
				}else{
					$direction = 'ASC';
				}
				$isSort = false;
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			}else{
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			}else{
				$tip = '';
			}
		}else{
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon = $sort_direction;
				$direction = $new_sort_direction;
				$display_text = $display_array[0];
				$isSort = true;
			}else{
				$icon = '';
				$display_text = $display_array[0];
				$direction = $display_array[1];
				$isSort = false;
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'fa fa-sort-asc';
		}elseif (strtolower($icon) == 'desc') {
			$icon = 'fa fa-sort-desc';
		}else{
			$icon = 'fa fa-unsorted';
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<th " . ($tip != '' ? "title='" . htmlspecialchars($tip) . "'":"") . " style='padding:4px;text-align:$align;' " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . ">" . $display_text . "</th>\n";
		}else{
			print "<th " . ($tip != '' ? "title='" . htmlspecialchars($tip) . "'":"") . " class='sortable" . ($isSort ? ' primarySort':'') . "' style='padding:4px;text-align:$align;'>";
			print "<div class='sortinfo' sort-page='" . ($url == '' ? htmlspecialchars($_SERVER['PHP_SELF']):$url) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>\n";
		}

		$i++;
	}

	print "</tr>\n";
}

/* html_header_sort_checkbox - draws a header row with a 'select all' checkbox in the last cell
     suitable for display inside of a box element.  When a user selects a column header,
     the collback function "filename" will be called to handle the sort the column and display
     the altered results.
   @arg $header_items - an array containing a list of column items to display.  The
        format is similar to the html_header, with the exception that it has three
        dimensions associated with each element (db_column => display_text, default_sort_order)
		alternatively (db_column => array('display' = 'blah', 'align' = 'blah', 'sort' = 'blah'))
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $form_action - the url to post the 'select all' form to */
function html_header_sort_checkbox($header_items, $sort_column, $sort_direction, $include_form = true, $form_action = "") {
	static $page = 0;

	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	/* default to the 'current' file */
	if ($form_action == "") { $form_action = basename($_SERVER["PHP_SELF"]); }

	print "<tr class='tableHeader'>\n";

	foreach($header_items as $db_column => $display_array) {
		$icon = '';
		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];
			if ($sort_column == $db_column) {
				$icon = $sort_direction;
				$direction = $new_sort_direction;
				$isSort = true;
			}else{
				$icon = '';

				if (isset($display_array['sort'])) {
					$direction = $display_array['sort'];
				}else{
					$direction = 'ASC';
				}
				$isSort = false;
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			}else{
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			}else{
				$tip = '';
			}
		}else{
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon = $sort_direction;
				$direction = $new_sort_direction;
				$display_text = $display_array[0];
				$isSort = true;
			}else{
				$icon = '';
				$display_text = $display_array[0];
				$direction = $display_array[1];
				$isSort = false;
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'fa fa-sort-asc';
		}elseif (strtolower($icon) == 'desc') {
			$icon = 'fa fa-sort-desc';
		}else{
			$icon = 'fa fa-unsorted';
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<th " . ($tip != '' ? "title='" . htmlspecialchars($tip) . "'":"") . " style='text-align:$align;'>" . $display_text . "</th>\n";
		}else{
			print "<th " . ($tip != '' ? "title='" . htmlspecialchars($tip) . "'":"") . " class='sortable" . ($isSort ? ' primarySort':'') . "' style='text-align:$align;'>";
			print "<div class='sortinfo' sort-page='" . htmlspecialchars($form_action) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>\n";
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __('Select All Rows'). "' onClick='SelectAll(\"chk_\",this.checked)'></th>" . ($include_form ? "<th style='display:none;'><form id='chk' name='chk' method='post' action='$form_action'></th>\n":"");
	print "</tr>\n";

	$page++;
}

/* html_header - draws a header row suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
		alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header($header_items, $last_item_colspan = 1) {
	print "<tr class='tableHeader " . (!$last_item_colspan > 1 ? 'tableFixed':'') . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		if (is_array($header_items[$i])) {
			print "<th style='text-align:" . $header_items[$i]['align'] . ";'" . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . ">" . $header_items[$i]['display'] . "</th>\n";
		}else{
			print "<th style='text-align:left;' " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . ">" . $header_items[$i] . "</th>\n";
		}
	}

	print "</tr>\n";
}

/* html_header_checkbox - draws a header row with a 'select all' checkbox in the last cell
     suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
		alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
   @arg $form_action - the url to post the 'select all' form to */
function html_header_checkbox($header_items, $include_form = true, $form_action = "", $resizable = true) {
	/* default to the 'current' file */
	if ($form_action == "") { $form_action = basename($_SERVER["PHP_SELF"]); }

	print "<tr class='tableHeader " . (!$resizable ? 'tableFixed':'') . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		if (is_array($header_items[$i])) {
			print "<th style='padding:4px;text-align:" . $header_items[$i]['align'] . ";'>" . $header_items[$i]['display'] . "</td>";
		}else{
			print "<th style='padding:4px;text-align:left;'>" . $header_items[$i] . "</th>\n";
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __('Select All Rows'). "' onClick='SelectAll(\"chk_\",this.checked)'></th>\n" . ($include_form ? "<th style='display:none;'><form id='chk' name='chk' method='post' action='$form_action'></th>\n":"");
	print "</tr>\n";
}

/* html_create_list - draws the items for an html dropdown given an array of data
   @arg $form_data - an array containing data for this dropdown. it can be formatted
     in one of two ways:
     $array["id"] = "value";
     -- or --
     $array[0]["id"] = 43;
     $array[0]["name"] = "Red";
   @arg $column_display - used to indentify the key to be used for display data. this
     is only applicable if the array is formatted using the second method above
   @arg $column_id - used to indentify the key to be used for id data. this
     is only applicable if the array is formatted using the second method above
   @arg $form_previous_value - the current value of this form element */
function html_create_list($form_data, $column_display, $column_id, $form_previous_value) {
	if (empty($column_display)) {
		foreach (array_keys($form_data) as $id) {
			print '<option value="' . htmlspecialchars($id, ENT_QUOTES) . '"';

			if ($form_previous_value == $id) {
			print " selected";
			}

			print ">" . title_trim(null_out_substitutions(htmlspecialchars($form_data[$id])), 75) . "</option>\n";
		}
	}else{
		if (sizeof($form_data) > 0) {
			foreach ($form_data as $row) {
				print "<option value='" . htmlspecialchars($row[$column_id], ENT_QUOTES) . "'";

				if ($form_previous_value == $row[$column_id]) {
					print " selected";
				}

				if (isset($row["host_id"])) {
					print ">" . title_trim(htmlspecialchars($row[$column_display]), 75) . "</option>\n";
				}else{
					print ">" . title_trim(null_out_substitutions(htmlspecialchars($row[$column_display])), 75) . "</option>\n";
				}
			}
		}
	}
}

/* html_split_string - takes a string and breaks it into a number of <br> separated segments
   @arg $string - string to be modified and returned
   @arg $length - the maximal string length to split to
   @arg $forgiveness - the maximum number of characters to walk back from to determine
         the correct break location.
   @returns $new_string - the modified string to be returned. */
function html_split_string($string, $length = 70, $forgiveness = 10) {
	$new_string = "";
	$j    = 0;
	$done = false;

	while (!$done) {
		if (strlen($string) > $length) {
			for($i = 0; $i < $forgiveness; $i++) {
				if (substr($string, $length-$i, 1) == " ") {
					$new_string .= substr($string, 0, $length-$i) . "<br>";

					break;
				}
			}

			$string = substr($string, $length-$i);
		}else{
			$new_string .= $string;
			$done        = true;
		}

		$j++;
		if ($j > 4) break;
	}

	return $new_string;
}

/* draw_graph_items_list - draws a nicely formatted list of graph items for display
     on an edit form
   @arg $item_list - an array representing the list of graph items. this array should
     come directly from the output of db_fetch_assoc()
   @arg $filename - the filename to use when referencing any external url
   @arg $url_data - any extra GET url information to pass on when referencing any
     external url
   @arg $disable_controls - whether to hide all edit/delete functionality on this form */
function draw_graph_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $config;

	include($config["include_path"] . "/global_arrays.php");

	print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Graph Item'),'',1);
		DrawMatrixHeaderItem(__('Data Source'),'',1);
		DrawMatrixHeaderItem(__('Graph Item Type'),'',1);
		DrawMatrixHeaderItem(__('CF Type'),'',1);
		DrawMatrixHeaderItem(__('Item Color'),'',4);
	print "</tr>";

	$group_counter = 0; $_graph_type_name = ""; $i = 0;

	if (sizeof($item_list)) {
		foreach ($item_list as $item) {
			/* graph grouping display logic */
			$this_row_style = ""; $use_custom_class = false; $hard_return = "";

			if ($graph_item_types{$item["graph_type_id"]} != "GPRINT") {
				$this_row_style = "font-weight: bold;"; $use_custom_class = true;

				if ($group_counter % 2 == 0) {
					$customClass = "graphItem";
				}else{
					$customClass = "graphItemAlternate";
				}

				$group_counter++;
			}

			$_graph_type_name = $graph_item_types{$item["graph_type_id"]};

			/* alternating row color */
			if ($use_custom_class == false) {
				form_alternate_row();
			}else{
				print "<tr class='$customClass'>";
			}

			print "<td>";
			if ($disable_controls == false) { print "<a class='linkEditMain' href='" . htmlspecialchars("$filename?action=item_edit&id=" . $item["id"] . "&$url_data") . "'>"; }
			print __('Item # %d', ($i+1));
			if ($disable_controls == false) { print "</a>"; }
			print "</td>\n";

			if (empty($item["data_source_name"])) { $item["data_source_name"] = __('No Task'); }

			switch (true) {
			case preg_match("/(AREA|STACK|GPRINT|LINE[123])/", $_graph_type_name):
				$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
				break;
			case preg_match("/(HRULE)/", $_graph_type_name):
				$matrix_title = "HRULE: " . $item["value"];
				break;
			case preg_match("/(VRULE)/", $_graph_type_name):
				$matrix_title = "VRULE: " . $item["value"];
				break;
			case preg_match("/(COMMENT)/", $_graph_type_name):
				$matrix_title = "COMMENT: " . $item["text_format"];
				break;
			}

			if ($item["hard_return"] == "on") {
				$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
			}

			print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
			print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";
			print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";
			print "<td style='width:1%;" . ((!empty($item["hex"])) ? "background-color:#" . $item["hex"] . ";'" : "'") . "></td>\n";
			print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";

			if ($disable_controls == false) {
				print "<td style='text-align:right;padding-right:10px;'>\n";
				if ($i != sizeof($item_list)-1) {
					print "<a class='moveArrow fa fa-caret-down' title='" . __('Move Down'). "' href='" . htmlspecialchars("$filename?action=item_movedown&id=" . $item["id"] . "&$url_data") . "'></a>\n";
				}else{
					print "<span class='moveArrowNone'></span>\n";
				}
				if ($i > 0) {
					print "<a class='moveArrow fa fa-caret-up' title='" . __('Move Up') . "' href='" . htmlspecialchars("$filename?action=item_moveup&id=" . $item["id"] . "&$url_data") . "'></a>\n";
				}else{
					print "<span class='moveArrowNone'></span>\n";
				}
				print "</td>\n";

				print "<td style='text-align:right;'><a class='deleteMarker fa fa-remove' title='" . __('Delete') . "' href='" . htmlspecialchars("$filename?action=item_remove&id=" . $item["id"] . "&$url_data") . "'></a></td>\n";
			}

			print "</tr>";

			$i++;
		}
	}else{
		print "<tr class='tableRow'><td colspan='7'><em>" . __('No Items') . "</em></td></tr>";
	}
}

/* draw_menu - draws the cacti menu for display in the console */
function draw_menu($user_menu = "") {
	global $config, $user_auth_realm_filenames, $menu;

	if (strlen($user_menu == 0)) {
		$user_menu = $menu;
	}

	//print "<pre>";print_r($_SERVER);print "</pre>";
	//print "<pre>";print_r($user_menu);print "</pre>";exit;

	print "<tr><td><table><tr><td><div id='menu'><ul id='nav' role='menu' tabindex='0'>\n";

	/* loop through each header */
	$i = 0;
	$headers = array();
	foreach ($user_menu as $header_name => $header_array) {
		/* pass 1: see if we are allowed to view any children */
		$show_header_items = false;
		foreach ($header_array as $item_url => $item_title) {
			if (preg_match('#link.php\?id=(\d+)#', $item_url, $matches)) {
				if (is_realm_allowed($matches[1]+10000)) {
					$show_header_items = true;
				}else{
					$show_header_items = false;
				}
			}else{
				$current_realm_id = (isset($user_auth_realm_filenames{basename($item_url)}) ? $user_auth_realm_filenames{basename($item_url)} : 0);

				if (is_realm_allowed($current_realm_id)) {
					$show_header_items = true;
				}elseif (api_user_realm_auth(strtok($item_url, '?'))) {
					$show_header_items = true;
				}
			}
		}

		if ($show_header_items == true) {
			// Let's give our menu li's a unique id
			$id = 'menu_' . strtolower(clean_up_name($header_name));
			if (isset($headers[$id])) {
				$id .= '_' . $i;
				$i++;
			}
			$headers[$id] = true;

			print "<li role='menuitem' aria-haspopup='true' id='$id'><a class='menu_parent active' href='#'>$header_name</a>\n";
			print "<ul role='menu' id='${id}_div' style='display:block;'>\n";

			/* pass 2: loop through each top level item and render it */
			foreach ($header_array as $item_url => $item_title) {
				$current_realm_id = (isset($user_auth_realm_filenames{basename($item_url)}) ? $user_auth_realm_filenames{basename($item_url)} : 0);

				/* if this item is an array, then it contains sub-items. if not, is just
				the title string and needs to be displayed */
				if (is_array($item_title)) {
					$i = 0;

					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames{basename($item_url)})) {
						/* if the current page exists in the sub-items array, draw each sub-item */
						if (array_key_exists(basename($_SERVER["PHP_SELF"]), $item_title) == true) {
							$draw_sub_items = true;
						}else{
							$draw_sub_items = false;
						}

						while (list($item_sub_url, $item_sub_title) = each($item_title)) {
							$item_sub_url = $config['url_path'] . $item_sub_url;

							/* always draw the first item (parent), only draw the children if we are viewing a page
							that is contained in the sub-items array */
							if (($i == 0) || ($draw_sub_items)) {
								if (basename($_SERVER["PHP_SELF"]) == basename($item_sub_url)) {
									print "<li><a role='menuitem' tabindex='-1' class='pic selected' href='" . htmlspecialchars($item_sub_url) . "'>$item_sub_title</a></li>\n";
								}else{
									print "<li><a role='menuitem' tabindex='-1' class='pic' href='" . htmlspecialchars($item_sub_url) . "'>$item_sub_title</a></li>\n";
								}
							}

							$i++;
						}
					}
				}else{
					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames{basename($item_url)})) {
						/* draw normal (non sub-item) menu item */
						$item_url = $config['url_path'] . $item_url;
						if (basename($_SERVER["PHP_SELF"]) == basename($item_url)) {
							print "<li><a role='menuitem' tabindex='-1' class='pic selected' href='" . htmlspecialchars($item_url) . "'>$item_title</a></li>\n";
						}else{
							print "<li><a role='menuitem' tabindex='-1' class='pic' href='" . htmlspecialchars($item_url) . "'>$item_title</a></li>\n";
						}
					}
				}
			}

			print "</ul></li>\n";
		}
	}

	print "</ul></div></td></tr></table></td></tr>\n";
}

/* draw_actions_dropdown - draws a table the allows the user to select an action to perform
     on one or more data elements
   @arg $actions_array - an array that contains a list of possible actions. this array should
     be compatible with the form_dropdown() function 
   @arg $delete_action - if there is a delete action that should surpress removal of rows
     specify it here.  If you don't want any delete actions, set to 0.*/
function draw_actions_dropdown($actions_array, $delete_action = 1) {
	global $config;

	if (!isset($actions_array[0])) {
		$my_actions[0]  = 'Choose an action';
		$my_actions    += $actions_array;
		$actions_array  = $my_actions;
	}

	?>
	<div class='actionsDropdown'>
		<div>
			<span class='actionsDropdownArrow'><img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt=''></span>
			<?php form_dropdown('drp_action', $actions_array, '', '', '0', '', '');?>
			<span class='actionsDropdownButton'><input id='submit' type='submit' value='Go' title='<?php print __('Execute Action');?>'></span>
		</div>
	</div>
	<input type='hidden' id='action' name='action' value='actions'>
	<script type='text/javascript'>
	function setDisabled() {
		if ($('#drp_action').val() == <?php print $delete_action;?>) {
			$('tr.selectable').find('td').not('.checkbox').each(function(data) {
				$(this).unbind();
			});

			$(':checkbox.disabled').each(function(data) {
				if ($(this).is(':checked')) {
					$(this).prop('checked', false);
					$(this).parent().toggleClass('selected');
				}
				$(this).unbind();
				$(this).prop('disabled', true).change().closest('tr').removeClass('selectable').removeClass('selected');
			});

			$('#submit').prop('disabled', false).removeClass('ui-button-disabled');
		}else if ($('#drp_action').val() == 0) {
			$('#submit').prop('disabled', true).addClass('ui-button-disabled');
		}else if (<?php print $delete_action;?> != 0) {
			$(':checkbox.disabled').prop('disabled', false).change().closest('tr').addClass('selectable').change();

			$('#submit').prop('disabled', false).removeClass('ui-button-disabled');
		}

		$('tr.selectable').find('td').not('.checkbox').each(function(data) {
			$(this).unbind();
			$(this).click(function(data) {
				$(this).parent().toggleClass('selected');
				var $checkbox = $(this).parent().find(':checkbox');
				$checkbox.prop('checked', !$checkbox.is(':checked'));
			});
		});

		$('tr.selectable').find('.checkbox').each(function(data) {
			$(this).unbind();
			$(this).click(function(data) {
				if (!$(this).is(':disabled')) {
					$(this).parent().toggleClass('selected');
					var checked = $(this).is(':checkbox');
					$(this).prop('checked', !checked);
				}
			});
		});
	}

	$(function() {
		setDisabled();
		$('#drp_action').unbind().change(function() {
			setDisabled();
		});

		$('.tableSubHeaderCheckbox').find(':checkbox').click(function(data) {
			if ($(this).is(':checked')) {
				$('input[id^=chk_]').not(':disabled').prop('checked',true);
				$('tr.selectable').addClass('selected');
			}else{
				$('input[id^=chk_]').not(':disabled').prop('checked',false);
				$('tr.selectable').removeClass('selected');
			}
		});
	});
	</script>
	<?php
}

/*
 * Deprecated functions
 */

function DrawMatrixHeaderItem($matrix_name, $matrix_text_color, $column_span = 1) { 
	?>
	<th style='height:1px;' colspan='<?php print $column_span;?>'>
		<div class='textSubHeaderDark'><?php print $matrix_name;?></div>
	</th>
	<?php 
}

function form_area($text) { ?>
	<tr>
		<td class="textArea">
			<?php print $text;?>
		</td>
	</tr>
<?php }

function is_console_page($url) {
	global $menu;

	if (basename($url) == 'index.php') {
		return true;
	}

	if (basename($url) == 'rrdcleaner.php') {
		return true;
	}

	if (api_plugin_hook_function('is_console_page', $url) != $url) {
		return true;
	}

	if (sizeof($menu)) {
	foreach($menu as $section => $children) {
		if (sizeof($children)) {
		foreach($children as $page => $name) {
			if (basename($page) == basename($url)) {
				return true;
			}
		}
		}
	}
	}

	return false;
}

function html_show_tabs_left($show_console_tab) {
	global $config, $tabs_left;

	if (get_selected_theme() == 'classic') {
		if ($show_console_tab == true) {
			?><a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console<?php print (is_console_page(basename($_SERVER['PHP_SELF'])) ? '_down':'');?>.gif" alt="<?php print __('Console');?>"></a><?php
		}

		if (is_realm_allowed(7)) {
			?><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs<?php
			$file = basename($_SERVER['PHP_SELF']);
			if ($file == "graph_view.php" || $file == "graph.php") {
				print "_down";
			} 
			print ".gif";?>" alt="Graphs"></a><?php
		}

		if (is_realm_allowed(21) || is_realm_allowed(22)) {
			if (substr_count($_SERVER["REQUEST_URI"], "reports_")) {
				print '<a href="' . $config['url_path'] . (is_realm_allowed(22) ? 'reports_admin.php':'reports_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_nectar_down.gif" alt="' . __('Reporting') . '"></a>';
			}else{
				print '<a href="' . $config['url_path'] . (is_realm_allowed(22) ? 'reports_admin.php':'reports_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_nectar.gif" alt="' . __('Reporting') . '"></a>';
			}
		}

		if (is_realm_allowed(18) || is_realm_allowed(19)) {
			if (substr_count($_SERVER["REQUEST_URI"], "clog")) {
				print '<a href="' . $config['url_path'] . (is_realm_allowed(18) ? 'clog.php':'clog_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_clog_down.png" alt="' . __('Cacti Log'). '"></a>';
			}else{
				print '<a href="' . $config['url_path'] . (is_realm_allowed(18) ? 'clog.php':'clog_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_clog.png" alt="' . __('Cacti Log') . '"></a>';
			}
		}

		$external_links = db_fetch_assoc('SELECT id, title FROM external_links WHERE style="TAB" ORDER BY sortorder');
		if (sizeof($external_links)) {
			foreach($external_links as $tab) {
				if (is_realm_allowed($tab['id']+10000)) {
					$parsed_url = parse_url($_SERVER['REQUEST_URI']);
					$down = false;

					if (basename($parsed_url['path']) == 'link.php') {
						if (isset($parsed_url['query'])) {
							$queries = explode('&', $parsed_url['query']);
							foreach($queries as $q) {
								list($var, $value) = explode('=', $q);
								if ($var == 'id') {
									if ($value == $tab['id']) {
										$down = true;
										break;
									}
								}
							}
						}
					}

					print '<a href="' . $config['url_path'] . 'link.php?id=' . $tab['id'] . '"><img src="' . get_classic_tabimage($tab['title'], $down) . '" alt="' . $tab['title'] . '"></a>';
				}
			}
		}

		api_plugin_hook('top_graph_header_tabs');
	}else{
		if ($show_console_tab) {
			$tabs_left[] =
			array(
				'title' => __('Console'),
				'id'	=> 'maintab-anchor-console',
				'image' => '',
				'url'   => $config['url_path'] . 'index.php',
			);
		}

		$tabs_left[] =
			array(
				'title' => __('Graphs'),
				'id'	=> 'maintab-anchor-graphs',
				'image' => '',
				'url'   => $config['url_path'] . 'graph_view.php',
			);

		$tabs_left[] =
			array(
				'title' => __('Reporting'),
				'id'	=> 'maintab-anchor-reports',
				'image' => '',
				'url'   => $config['url_path'] . (is_realm_allowed(22) ? 'reports_admin.php':'reports_user.php'),
			);

		$tabs_left[] =
			array(
				'title' => __('Cacti Log'),
				'id'	=> 'maintab-anchor-logs',
				'image' => '',
				'url'   => $config['url_path'] . (is_realm_allowed(18) ? 'clog.php':'clog_user.php'),
			);

		$external_links = db_fetch_assoc('SELECT id, title FROM external_links WHERE style="TAB" ORDER BY sortorder');
		if (sizeof($external_links)) {
			foreach($external_links as $tab) {
				if (is_realm_allowed($tab['id']+10000)) {
					$tabs_left[] = 
						array(
							'title' => $tab['title'],
							'id'    => 'maintab-anchor-link' . $tab['id'],
							'image' => '',
							'url'   => $config['url_path'] . 'link.php?id=' . $tab['id']
						);
				}
			}
		}

		// Get Plugin Text Out of Band
		ob_start();
		api_plugin_hook('top_graph_header_tabs');

		$tab_text = trim(ob_get_clean());
		$tab_text = str_replace('<a', '', $tab_text);
		$tab_text = str_replace('</a>', '|', $tab_text);
		$tab_text = str_replace('<img', '', $tab_text);
		$tab_text = str_replace('<', '', $tab_text);
		$tab_text = str_replace('"', "'", $tab_text);
		$tab_text = str_replace('>', '', $tab_text);
		$elements = explode('|', $tab_text);

		foreach($elements as $p) {
			$p = trim($p);

			if ($p == '') continue;

			$altpos  = strpos($p, 'alt=');
			$hrefpos = strpos($p, 'href=');

			if ($altpos >= 0) {
				$alt = substr($p, $altpos+4);
				$parts = explode("'", $alt);
				if ($parts[0] == '') {
					$alt = $parts[1];
				}else{
					$alt = $parts[0];
				}
			}else{
				$alt = 'Title';
			}

			if ($hrefpos >= 0) {
				$href = substr($p, $hrefpos+5);
				$parts = explode("'", $href);
				if ($parts[0] == '') {
					$href = $parts[1];
				}else{
					$href = $parts[0];
				}
			}else{
				$href = 'unknown';
			}

			$tabs_left[] = array('title' => ucwords($alt), 'url' => $href);
		}

		$i = 0;
		$me_base = basename($_SERVER['PHP_SELF']);
		foreach($tabs_left as $tab) {
			$tab_base = basename($tab['url']);
			if ($tab_base == 'graph_view.php' && ($me_base == 'graph_view.php' || $me_base == 'graph.php')) {
				$tabs_left[$i]['selected'] = true;
			}elseif ($tab_base == 'index.php' && is_console_page($me_base)) {
				$tabs_left[$i]['selected'] = true;
			}elseif ($tab_base == $me_base) {
				$tabs_left[$i]['selected'] = true;
			}
			$i++;
		}

		$i = 0;

		print "<div class='maintabs'><nav><ul role='tablist'>\n";
		foreach($tabs_left as $tab) {
			print "<li role='tab'><a id='" . (isset($tab['id']) ? $tab['id'] : 'maintab-anchor-' . $i) . "' class='lefttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "' tabindex='-1'>" . $tab['title'] . "</a></li>\n";

			$i++;
		}
		print "</ul></nav></div>\n";
	}
}

function html_graph_tabs_right($current_user) {
	global $config, $tabs_right;

	$theme = get_selected_theme();

	if ($theme == 'classic') {
		if (is_view_allowed('show_tree')) {
			?><a class='righttab' href='<?php print htmlspecialchars($config['url_path'] . 'graph_view.php?action=tree');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_tree<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'tree') {
				print '_down';
			}?>.gif' title='<?php print __('Tree View');?>' alt=''></a><?php
		}?><?php

		if (is_view_allowed('show_list')) {
			?><a class='righttab' href='<?php print htmlspecialchars($config['url_path'] . 'graph_view.php?action=list');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_list<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'list') {
				print '_down';
			}?>.gif' title='<?php print __('List View');?>' alt=''></a><?php
		}?><?php

		if (is_view_allowed('show_preview')) {
			?><a class='righttab' href='<?php print htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_preview<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'preview') {
				print '_down';
			}?>.gif' title='<?php print __('Preview View');?>' alt=''></a><?php
		}?>&nbsp;<br>
		<?php
	}else{
		$tabs_right = array(
			array(
				'title' => __('Tree View'),
				'image' => 'include/themes/' . $theme . '/images/tab_tree.gif',
				'id'    => 'tree',
				'url'   => 'graph_view.php?action=tree',
			),
			array(
				'title' => __('List View'),
				'image' => 'include/themes/' . $theme . '/images/tab_list.gif',
				'id'    => 'list',
				'url'   => 'graph_view.php?action=list',
			),
			array(
				'title' => __('Preview'),
				'image' => 'include/themes/' . $theme . '/images/tab_preview.gif',
				'id'    => 'preview',
				'url'   => 'graph_view.php?action=preview',
			),
		);

		$i = 0;
		foreach($tabs_right as $tab) {
			if ($tab['id'] == 'tree') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'tree') {
					$tabs_right[$i]['selected'] = true;
				}
			}elseif ($tab['id'] == 'list') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'list') {
					$tabs_right[$i]['selected'] = true;
				}
			}elseif ($tab['id'] == 'preview') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'preview') {
					$tabs_right[$i]['selected'] = true;
				}
			}elseif (strstr($_SERVER['PHP_SELF'], $tab['url'])) {
				$tabs_right[$i]['selected'] = true;
			}

			$i++;
		}

		print "<div class='tabs' style='float:right;'><nav><ul role='tablist'>\n";
		foreach($tabs_right as $tab) {
			switch($tab['id']) {
			case 'tree':
				if (is_view_allowed('show_tree')) {
					if (isset($tab['image']) && $tab['image'] != '') {
						print "<li role='tab'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>\n";
					}else{
						print "<li role='tab' tabindex='1'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . "</a></li>\n";
					}
					break;
				}

				break;
			case 'list':
				if (is_view_allowed('show_list')) {
					if (isset($tab['image']) && $tab['image'] != '') {
						print "<li role='tab'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>\n";
					}else{
						print "<li role='tab'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . "</a></li>\n";
					}
					break;
				}

				break;
			case 'preview':
				if (is_view_allowed('show_preview')) {
					if (isset($tab['image']) && $tab['image'] != '') {
						print "<li role='tab'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>\n";
					}else{
						print "<li role='tab'><a title='" . $tab['title'] . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . "</a></li>\n";
					}
					break;
				}

				break;
			}
		}
		print "</ul></nav></div>\n";
	}
}

function html_host_filter($host_id = '-1', $call_back = 'applyFilter', $sql_where = '') {
	$theme = get_selected_theme();

	if ($theme == 'classic') {
		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<select id='host_id' name='host_id' onChange='<?php print $call_back;?>()'>
				<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
				<option value='0'<?php if (get_request_var('host_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
				<?php

				if ($sql_where != '' && strpos($sql_where, 'WHERE') === false) { 
					$sql_where = 'WHERE ' . $sql_where;
				}

				$hosts = db_fetch_assoc("SELECT h.id, CONCAT_WS('',h.description,' (',h.hostname,')') AS name 
					FROM host AS h
					LEFT JOIN host_template AS ht
					ON ht.id=h.host_template_id
					$sql_where 
					ORDER BY h.description, h.hostname");

				if (sizeof($hosts) > 0) {
					foreach ($hosts as $host) {
						print "<option value='" . $host['id'] . "'"; if (get_request_var('host_id') == $host['id']) { print ' selected'; } print '>' . title_trim(htmlspecialchars($host['name']), 40) . "</option>\n";
					}
				}
				?>
			</select>
		</td>
		<?php
	}else{
		if ($host_id > 0) {
			$hostname = db_fetch_cell_prepared("SELECT description FROM host WHERE id = ?", array($host_id));
		}elseif ($host_id == 0) {
			$hostname = 'None';
		}else{
			$hostname = 'Any';
		}

		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<span id='host_wrapper' style='width:200px;' class='ui-selectmenu-button ui-widget ui-state-default ui-corner-all'>
				<span id='host_click' class='ui-icon ui-icon-triangle-1-s'></span>
				<input size='28' id='host' value='<?php print $hostname;?>'>
			</span>
			<input type='hidden' id='host_id' name='host_id' value='<?php print $host_id;?>'>
			<input type='hidden' id='call_back' value='<?php print $call_back . "()";?>'>
		</td>
	<?php
	}
}

function html_spikekill_actions() {
	switch(get_nfilter_request_var('action')) {
	case 'spikemenu':
		print html_spikekill_menu(get_filter_request_var('local_graph_id'));

		break;
	case 'spikesave':
		switch(get_nfilter_request_var('setting')) {
			case 'rmethod':
				$id = get_filter_request_var('id');
				set_user_setting('spikekill_method', $id);
				break;
			case 'rstddev':
				$id = get_filter_request_var('id');
				set_user_setting('spikekill_deviations', $id);
				break;
			case 'rvarout':
				$id = get_filter_request_var('id');
				set_user_setting('spikekill_outliers', $id);
				break;
			case 'rvarpct':
				$id = get_filter_request_var('id');
				set_user_setting('spikekill_percent', $id);
				break;
			case 'rkills':
				$id = get_filter_request_var('id');
				set_user_setting('spikekill_number', $id);
				break;
		}

		break;
	}
}

function html_spikekill_setting($name) {
	return read_user_setting($name, read_config_option($name), true);
}

function html_spikekill_menu($local_graph_id) {
	$rmethod  = '<li>' . __('Replacement Methods') . '<ul>';
	$rmethod .= '<li class="skmethod" id="method_1"><i ' . (html_spikekill_setting('spikekill_avgnan') == '1' ? 'class="fa fa-check"':'') . '></i><span></span>' . __('Average') . '</li>';
	$rmethod .= '<li class="skmethod" id="method_2"><i ' . (html_spikekill_setting('spikekill_avgnan') == '2' ? 'class="fa fa-check"':'') . '></i><span></span>' . __('Nan\'s') . '</li>';
	$rmethod .= '</ul></li>';

	$rstddev  = '<li>' . __('Standard Deviations') . '<ul>';
	for($i = 1; $i <= 10; $i++) {
		$rstddev .= '<li class="skstddev" id="stddev_' . $i . '"><i ' . (html_spikekill_setting('spikekill_deviations') == $i ? 'class="fa fa-check"':'') . '></i><span></span>' . __('%s Standard Deviations', $i) . '</li>';
	}
	$rstddev .= '</ul></li>';

	$rvarpct  = '<li>' . __('Variance Percentage') . '<ul>';
	for($i = 1; $i <= 10; $i++) {
		$rvarpct .= '<li class="skvarpct" id="varpct_' . ($i * 100) . '"><i ' . (html_spikekill_setting('spikekill_percent') == ($i * 100) ? 'class="fa fa-check"':'') . '></i><span></span>' . round($i * 100,0) . ' %</li>';
	}
	$rvarpct .= '</ul></li>';

	$rvarout  = '<li>' . __('Variance Outliers') . '<ul>';
	for($i = 3; $i <= 10; $i++) {
		$rvarout .= '<li class="skvarout" id="varout_' . $i . '"><i ' . (html_spikekill_setting('spikekill_outliers') == $i ? 'class="fa fa-check"':'') . '></i><span></span>' . __('%d Outliers', $i) . '</li>';
	}
	$rvarout .= '</ul></li>';

	$rkills  = '<li>' . __('Kills Per RRA') . '<ul>';
	for($i = 1; $i <= 10; $i++) {
		$rkills .= '<li class="skkills" id="kills_' . $i . '"><i ' . (html_spikekill_setting('spikekill_number') == $i ? 'class="fa fa-check"':'') . '></i><span></span>' . __('%d Spiikes', $i) . '</li>';
	}
	$rkills .= '</ul></li>';

	?>
	<div class='spikekillParent' style='display:none;z-index:20;position:absolute;text-align:left;white-space:nowrap;padding-right:2px;'>
	<ul class='spikekillMenu' style='font-size:1em;'>
		<li data-graph='<?php print $local_graph_id;?>' class='rstddev'><i class='deviceUp fa fa-support'></i><span></span><?php print __('Remove StdDev');?></li>
		<li data-graph='<?php print $local_graph_id;?>' class='rvariance'><i class='deviceRecovering fa fa-support'></i><span></span><?php print __('Remove Variance');?></li>
		<li data-graph='<?php print $local_graph_id;?>' class='dstddev'><i class='deviceUp fa fa-check'></i><span></span><?php print __('DryRun StdDev');?></li>
		<li data-graph='<?php print $local_graph_id;?>' class='dvariance'><i class='deviceRecovering fa fa-check'></i><span></span><?php print __('DryRun Variance');?></li>
		<li><i class='fa fa-cog'></i><span></span>Settings
			<ul>
				<?php print $rmethod;?>
				<?php print $rstddev;?>
				<?php print $rvarpct;?>
				<?php print $rvarout;?>
				<?php print $rkills;?>
			</ul>
		</li>
	</ul>
	</div>
	<?php
}

function html_spikekill_js() {
	?>
	<script type='text/javascript'>
	$(function() {
		$('.spikekill').hover(function() {
			local_graph_id = $(this).attr('data-graph');

			$.get('?action=spikemenu&local_graph_id='+local_graph_id, function(data) {
				$('#sk'+local_graph_id).after(data);	

				$('.spikekillMenu').menu({
					select: function(event, ui) {
						$(this).menu('focus', event, ui.item);
					},
					delay: 500
				});

				spikeKillActions();

				$('.spikekillParent').show();
			});
		}, function() {
			$(this).find('.spikekillParent:first').each(function() {
				$(this).find('.spikekillMenu:first').menu('collapseAll', null, true);
				$(this).fadeOut(600, function() {
					$('.spikekillMenu').menu('destroy');
					$('.spikekillParent').remove();
				});
			});
		});
	});

	function spikeKillActions() {
		$('.rstddev').click(function() {
			removeSpikesStdDev($(this).attr('data-graph'));
			$('.spikekillParent').hide();
		});

		$('.dstddev').click(function() {
			dryRunStdDev($(this).attr('data-graph'));
			$('.spikekillParent').hide();
		});

		$('.rvariance').click(function() {
			removeSpikesVariance($(this).attr('data-graph'));
			$('.spikekillParent').hide();
		});

		$('.dvariance').click(function() {
			dryRunVariance($(this).attr('data-graph'));
			$('.spikekillParent').hide();
		});


		$('.skmethod').click(function() {
			$('.skmethod').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$('.spikekillMenu').menu('collapseAll', null, true);

			strURL = '?action=spikesave&setting=rmethod&id='+$(this).attr('id').replace('method_','');
			$.get(strURL);
		});

		$('.skkills').click(function() {
			$('.skkills').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$('.spikekillMenu').menu('collapseAll', null, true);

			strURL = '?action=spikesave&setting=rkills&id='+$(this).attr('id').replace('kills_','');
			$.get(strURL);
		});

		$('.skstddev').click(function() {
			$('.skstddev').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$('.spikekillMenu').menu('collapseAll', null, true);

			strURL = '?action=spikesave&setting=rstddev&id='+$(this).attr('id').replace('stddev_','');
			$.get(strURL);
		});

		$('.skvarpct').click(function() {
			$('.skvarpct').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$('.spikekillMenu').menu('collapseAll', null, true);

			strURL = '?action=spikesave&setting=rvarpct&id='+$(this).attr('id').replace('varpct_','');
			$.get(strURL);
		});

		$('.skvarout').click(function() {
			$('.skvarout').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$('.spikekillMenu').menu('collapseAll', null, true);

			strURL = '?action=spikesave&setting=rvarout&id='+$(this).attr('id').replace('varout_','');
			$.get(strURL);
		});
	}
	</script>
	<?php
}

