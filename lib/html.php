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

/* html_start_box - draws the start of an HTML box with an optional title
   @arg $title - the title of this box ("" for no title)
   @arg $width - the width of the box in pixels or percent
   @arg $background_color - the color of the box border and title row background
     color
   @arg $cell_padding - the amount of cell padding to use inside of the box
   @arg $align - the HTML alignment to use for the box (center, left, or right)
   @arg $add_text - the url to use when the user clicks 'Add' in the upper-right
     corner of the box ("" for no 'Add' link) */
function html_start_box($title, $width, $background_color, $cell_padding, $align, $add_text) {
	global $colors; ?>
	<table align="<?php print $align;?>" width="<?php print $width;?>" cellpadding=0 cellspacing=0 border=0 class="cactiTable" bgcolor="#<?php print $background_color;?>">
		<tr>
			<td>
				<table cellpadding=<?php print $cell_padding;?> cellspacing=0 border=0 bgcolor="#<?php print $colors["form_background_dark"];?>" width="100%">
					<?php if ($title != "") {?><tr>
						<td bgcolor="#<?php print $background_color;?>" style="padding: 3px;" colspan="100">
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td bgcolor="#<?php print $background_color;?>" class="textHeaderDark"><?php print $title;?></td>
										<?php if ($add_text != "") {?><td class="textHeaderDark" align="right" bgcolor="#<?php print $colors["header"];?>"><strong><a class="linkOverDark" href="<?php print htmlspecialchars($add_text);?>">Add</a>&nbsp;</strong></td><?php }?>
								</tr>
							</table>
						</td>
					</tr><?php }?>

<?php }

/* html_end_box - draws the end of an HTML box
   @arg $trailing_br (bool) - whether to draw a trailing <br> tag after ending
     the box */
function html_end_box($trailing_br = true) { ?>
				</table>
			</td>
		</tr>
	</table>
	<?php if ($trailing_br == true) { print "<br>"; } ?>
<?php }

/* html_graph_start_box - draws the start of an HTML graph view box
   @arg $cellpadding - the table cell padding for the box
   @arg $leading_br (bool) - whether to draw a leader <br> tag before the start of the table */
function html_graph_start_box($cellpadding = 3, $leading_br = true) {
	if ($leading_br == true) {
		print "<br>\n";
	}

	print "<table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='$cellpadding'>\n";
}

/* html_graph_end_box - draws the end of an HTML graph view box */
function html_graph_end_box() {
	print "</table>";
}

/* html_graph_area - draws an area the contains full sized graphs
   @arg $graph_array - the array to contains graph information. for each graph in the
     array, the following two keys must exist
     $arr[0]["local_graph_id"] // graph id
     $arr[0]["title_cache"] // graph title
   @arg $no_graphs_message - display this message if no graphs are found in $graph_array
   @arg $extra_url_args - extra arguments to append to the url
   @arg $header - html to use as a header */
function html_graph_area(&$graph_array, $no_graphs_message = "", $extra_url_args = "", $header = "") {
	global $config;

	$i = 0;
	if (sizeof($graph_array) > 0) {
		if ($header != "") {
			print $header;
		}

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
					print "<tr bgcolor='#a9b7cb'>
						<td colspan='3' class='textHeaderDark'>
							<strong>Graph Template:</strong> " . htmlspecialchars($graph["graph_template_name"]) . "
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
					print "<tr bgcolor='#a9b7cb'><td colspan='3' class='textHeaderDark'><strong>Data Query:</strong> " . htmlspecialchars($graph["data_query_name"]) . "</td></tr>";
				}
				print "<tr bgcolor='#a9b7cb'>
					<td colspan='3' class='textHeaderDark'>
						" . $graph["sort_field_value"]. "
					</td>
				</tr>";
			}

			?>
			<tr align='center' style='background-color: #<?php print ($i % 2 == 0 ? "f9f9f9" : "ffffff");?>;'>
				<td align='center'>
					<table align='center' cellpadding='0'>
						<tr>
							<td align='center'>
								<div style="min-height: <?php echo (1.6 * $graph["height"]) . "px"?>;"><a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=view&local_graph_id=" . $graph["local_graph_id"] . "&rra_id=all");?>'><img class='graphimage' id='graph_<?php print $graph["local_graph_id"] ?>' src='<?php print htmlspecialchars($config['url_path'] . "graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0" . (($extra_url_args == "") ? "" : "&$extra_url_args"));?>' border='0' alt='<?php print htmlspecialchars($graph["title_cache"]);?>'></a></div>
								<?php print (read_graph_config_option("show_graph_title") == "on" ? "<p style='font-size: 10;' align='center'><strong>" . htmlspecialchars($graph["title_cache"]) . "</strong></p>" : "");?>
							</td>
							<td valign='top' style='align: left; padding: 3px;' class='noprint'>
								<a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=zoom&local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&". $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_zoom.gif' border='0' alt='Zoom Graph' title='Zoom Graph' style='padding: 3px;'></a><br>
								<a href='<?php print htmlspecialchars($config['url_path'] . "graph_xport.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&" . $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_query.png' border='0' alt='CSV Export' title='CSV Export' style='padding: 3px;'></a><br>
								<a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=properties&local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&" . $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_properties.gif' border='0' alt='Graph Source/Properties' title='Graph Source/Properties' style='padding: 3px;'></a><br>
								<?php api_plugin_hook('graph_buttons', array('hook' => 'graphs_thumbnails', 'local_graph_id' => $graph['local_graph_id'], 'rra' =>  0, 'view_type' => 'view')); ?>
								<a href='#page_top'><img src='<?php print $config['url_path']; ?>images/graph_page_top.gif' border='0' alt='Page Top' title='Page Top' style='padding: 3px;'></a><br>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?php

			$i++;
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
   @arg $header - html to use as a header */
function html_graph_thumbnail_area(&$graph_array, $no_graphs_message = "", $extra_url_args = "", $header = "") {
	global $config;
	$i = 0; $k = 0; $j = 0;

	$num_graphs = sizeof($graph_array);

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
					if (!$start) {
						while($i % read_graph_config_option("num_columns") != 0) {
							print "<td align='center' width='" . ceil(100 / read_graph_config_option("num_columns")) . "%'></td>";
							$i++;
						}
						print "</tr>";
					}

					print "<tr style='background-color:#a9b7cb;'>
						<td style='background-color:#a9b7cb;' colspan='" . read_graph_config_option("num_columns") . "' class='textHeaderDark'>
							<strong>Graph Template:</strong> " . $graph["graph_template_name"] . "
						</td>
					</tr>";
					$i = 0;
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
						while($i % read_graph_config_option("num_columns") != 0) {
							print "<td align='center' width='" . ceil(100 / read_graph_config_option("num_columns")) . "%'></td>";
							$i++;
						}

						print "</tr>";
					}

					print "<tr style='background-color:#a9b7cb;'>
							<td style='background-color:#a9b7cb;' colspan='" . read_graph_config_option("num_columns") . "' class='textHeaderDark'><strong>Data Query:</strong> " . $graph["data_query_name"] . "</td>
						</tr>";
					$i = 0;
				}

				if (!isset($prev_sort_field_value) || $prev_sort_field_value != $graph["sort_field_value"]){
					$prev_sort_field_value = $graph["sort_field_value"];
					print "<tr style='background-color:#a9b7cb;'>
						<td style='background-color:#a9b7cb;' colspan='" . read_graph_config_option("num_columns") . "' class='textHeaderDark'>
							" . $graph["sort_field_value"] . "
						</td>
					</tr>";
					$i = 0;
					$j = 0;
				}
			}

			if ($i == 0) {
				print "<tr style='background-color: #" . ($j % 2 == 0 ? "F2F2F2" : "FFFFFF") . ";'>";
				$start = false;
			}

			?>
			<td align='center' width='<?php print ceil(100 / read_graph_config_option("num_columns"));?>%'>
				<table align='center' cellpadding='0'>
					<tr>
						<td align='center'>
							<div style="min-height: <?php echo (1.6 * read_graph_config_option("default_height")) . "px"?>;"><a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=view&rra_id=all&local_graph_id=" . $graph["local_graph_id"]);?>'><img class='graphimage' id='graph_<?php print $graph["local_graph_id"] ?>' src='<?php print htmlspecialchars($config["url_path"] . "graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&graph_height=" . read_graph_config_option("default_height") . "&graph_width=" . read_graph_config_option("default_width") . "&graph_nolegend=true" . (($extra_url_args == "") ? "" : "&$extra_url_args"));?>' border='0' alt='<?php print htmlspecialchars($graph["title_cache"]);?>'></a></div>
							<?php print (read_graph_config_option("show_graph_title") == "on" ? "<p style='font-size: 10;' align='center'><strong>" . htmlspecialchars($graph["title_cache"]) . "</strong></p>" : "");?>
						</td>
						<td valign='top' style='align: left; padding: 3px;'>
							<a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=zoom&local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&" . $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_zoom.gif' border='0' alt='Zoom Graph' title='Zoom Graph' style='padding: 3px;'></a><br>
							<a href='<?php print htmlspecialchars($config['url_path'] . "graph_xport.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&" . $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_query.png' border='0' alt='CSV Export' title='CSV Export' style='padding: 3px;'></a><br>
							<a href='<?php print htmlspecialchars($config['url_path'] . "graph.php?action=properties&local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0&" . $extra_url_args);?>'><img src='<?php print $config['url_path'];?>images/graph_properties.gif' border='0' alt='Graph Source/Properties' title='Graph Source/Properties' style='padding: 3px;'></a><br>
							<?php api_plugin_hook('graph_buttons_thumbnails', array('hook' => 'graphs_thumbnails', 'local_graph_id' => $graph['local_graph_id'], 'rra' =>  0, 'view_type' => '')); ?>
							<a href='#page_top'><img src='<?php print $config['url_path'] . "images/graph_page_top.gif";?>' border='0' alt='Page Top' title='Page Top' style='padding: 3px;'></a><br>
						</td>
					</tr>
				</table>
			</td>
			<?php

			$i++;
			$k++;

			if (($i % read_graph_config_option("num_columns") == 0) && ($k < $num_graphs)) {
				$i=0;
				$j++;
				print "</tr>";
				$start = true;
			}
		}

		if (!$start) {
			while($i % read_graph_config_option("num_columns") != 0) {
				print "<td align='center' width='" . ceil(100 / read_graph_config_option("num_columns")) . "%'></td>";
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

/* html_nav_bar - draws a navigation bar which includes previous/next links as well as current
     page information
   @arg $background_color - the background color of this navigation bar row
   @arg $colspan - the colspan for the entire row
   @arg $current_page - the current page in the navigation system
   @arg $rows_per_page - the number of rows that are displayed on a single page
   @arg $total_rows - the total number of rows in the navigation system
   @arg $nav_url - the url to use when presenting users with previous/next links. the variable
     <PAGE> will be substituted with the correct page number if included */
function html_nav_bar($background_color, $colspan, $current_page, $rows_per_page, $total_rows, $nav_url) {
	?>
	<tr bgcolor='#<?php print $background_color;?>' class='noprint'>
		<td colspan='<?php print $colspan;?>'>
			<table width='100%' cellspacing='0' cellpadding='3' border='0'>
				<tr>
					<td align='left' class='textHeaderDark' width='15%'>
						<?php if ($current_page > 1) {
							print "<strong><a class='linkOverDark' href='" . htmlspecialchars(str_replace("<PAGE>", ($current_page-1), $nav_url)) . "'> &lt;&lt; Previous</a></strong>";
						} ?>
					</td>
					<td align='center' class='textHeaderDark' width='70%'>
						Showing Rows <?php print (($rows_per_page*($current_page-1))+1);?> to <?php print ((($total_rows < $rows_per_page) || ($total_rows < ($rows_per_page*$current_page))) ? $total_rows : ($rows_per_page*$current_page));?> of <?php print $total_rows;?>
					</td>
					<td align='right' class='textHeaderDark' width='15%'>
						<?php if (($current_page * $rows_per_page) < $total_rows) {
							print "<strong><a class='linkOverDark' href='" . htmlspecialchars(str_replace("<PAGE>", ($current_page+1), $nav_url)) . "'> Next &gt;&gt; </a></strong>";
						} ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
}

/* html_header_sort - draws a header row suitable for display inside of a box element.  When
     a user selects a column header, the collback function "filename" will be called to handle
     the sort the column and display the altered results.
   @arg $header_items - an array containing a list of column items to display.  The
        format is similar to the html_header, with the exception that it has three
        dimensions associated with each element (db_column => display_text, default_sort_order)
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header_sort($header_items, $sort_column, $sort_direction, $last_item_colspan = 1) {
	global $colors;

	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	$i = 1;
	foreach ($header_items as $db_column => $display_array) {
		/* by default, you will always sort ascending, with the exception of an already sorted column */
		if ($sort_column == $db_column) {
			$direction = $new_sort_direction;
			$display_text = $display_array[0] . "**";
		}else{
			$display_text = $display_array[0];
			$direction = $display_array[1];
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<td " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . "class='tableSubHeaderColumn'><span class='textSubHeaderDark'>" . $display_text . "</span></td>\n";
		}else{
			print "<td class='tableSubHeaderColumn' " . ((($i) == count($header_items)) ? "colspan='$last_item_colspan'>" : ">");
			print "<a class='textSubHeaderDark' href='" . htmlspecialchars(basename($_SERVER["PHP_SELF"]) . "?sort_column=" . $db_column . "&sort_direction=" . $direction) . "'>" . $display_text . "</a>";
			print "</td>\n";
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
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $form_action - the url to post the 'select all' form to */
function html_header_sort_checkbox($header_items, $sort_column, $sort_direction, $include_form = true, $form_action = "") {
	global $colors;

	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	/* default to the 'current' file */
	if ($form_action == "") { $form_action = basename($_SERVER["PHP_SELF"]); }

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	foreach($header_items as $db_column => $display_array) {
		/* by default, you will always sort ascending, with the exception of an already sorted column */
		if ($sort_column == $db_column) {
			$direction = $new_sort_direction;
			$display_text = $display_array[0] . "**";
		}else{
			$display_text = $display_array[0];
			$direction = $display_array[1];
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<td class='tableSubHeaderColumn'>" . $display_text . "</td>\n";
		}else{
			print "<td class='tableSubHeaderColumn'>";
			print "<a class='textSubHeaderDark' href='" . htmlspecialchars(basename($_SERVER["PHP_SELF"]) . "?sort_column=" . $db_column . "&sort_direction=" . $direction) . "'>" . $display_text . "</a>";
			print "</td>\n";
		}
	}

	print "<td width='1%' class='tableSubHeaderColumn tdSelectAll' align='right' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"chk_\",this.checked)'></td>\n" . ($include_form ? "<td style='display:none;'><form name='chk' method='post' action='$form_action'></td>\n":"");
	print "</tr>\n";
}

/* html_header - draws a header row suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header($header_items, $last_item_colspan = 1) {
	global $colors;

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		print "<td " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . "class='tableSubHeaderColumn'>" . $header_items[$i] . "</td>\n";
	}

	print "</tr>\n";
}

/* html_header_checkbox - draws a header row with a 'select all' checkbox in the last cell
     suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
   @arg $form_action - the url to post the 'select all' form to */
function html_header_checkbox($header_items, $include_form = true, $form_action = "") {
	global $colors;

	/* default to the 'current' file */
	if ($form_action == "") { $form_action = basename($_SERVER["PHP_SELF"]); }

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		print "<td class='tableSubHeaderColumn'>" . $header_items[$i] . "</td>\n";
	}

	print "<td width='1%' class='tableSubHeaderColumn tdSelectAll' align='right' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"chk_\",this.checked)'></td>\n" . ($include_form ? "<td style='display:none;'><form name='chk' method='post' action='$form_action'></td>\n":"");
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
			print '<option value="' . htmlspecialchars($id) . '"';

			if ($form_previous_value == $id) {
			print " selected";
			}

			print ">" . title_trim(null_out_substitutions(htmlspecialchars($form_data[$id])), 75) . "</option>\n";
		}
	}else{
		if (sizeof($form_data) > 0) {
			foreach ($form_data as $row) {
				print "<option value='" . htmlspecialchars($row[$column_id]) . "'";

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
	global $colors, $config;

	include($config["include_path"] . "/global_arrays.php");

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
		DrawMatrixHeaderItem("Data Source",$colors["header_text"],1);
		DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("Item Color",$colors["header_text"],4);
	print "</tr>";

	$group_counter = 0; $_graph_type_name = ""; $i = 0;
	$alternate_color_1 = $colors["alternate"]; $alternate_color_2 = $colors["alternate"];

	if (sizeof($item_list) > 0) {
	foreach ($item_list as $item) {
		/* graph grouping display logic */
		$this_row_style = ""; $use_custom_row_color = false; $hard_return = "";

		if ($graph_item_types{$item["graph_type_id"]} != "GPRINT") {
			$this_row_style = "font-weight: bold;"; $use_custom_row_color = true;

			if ($group_counter % 2 == 0) {
				$alternate_color_1 = "EEEEEE";
				$alternate_color_2 = "EEEEEE";
				$custom_row_color = "D5D5D5";
			}else{
				$alternate_color_1 = $colors["alternate"];
				$alternate_color_2 = $colors["alternate"];
				$custom_row_color = "D2D6E7";
			}

			$group_counter++;
		}

		$_graph_type_name = $graph_item_types{$item["graph_type_id"]};

		/* alternating row color */
		if ($use_custom_row_color == false) {
			form_alternate_row_color($alternate_color_1,$alternate_color_2,$i);
		}else{
			print "<tr bgcolor='#$custom_row_color'>";
		}

		print "<td>";
		if ($disable_controls == false) { print "<a href='" . htmlspecialchars("$filename?action=item_edit&id=" . $item["id"] . "&$url_data") . "'>"; }
		print "<strong>Item # " . ($i+1) . "</strong>";
		if ($disable_controls == false) { print "</a>"; }
		print "</td>\n";

		if (empty($item["data_source_name"])) { $item["data_source_name"] = "No Task"; }

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
		print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";

		if ($disable_controls == false) {
			print "<td><a href='" . htmlspecialchars("$filename?action=item_movedown&id=" . $item["id"] . "&$url_data") . "'><img src='" . $config['url_path'] . "images/move_down.gif' border='0' alt='Move Down'></a>
					<a href='" . htmlspecialchars("$filename?action=item_moveup&id=" . $item["id"] . "&$url_data") . "'><img src='" . $config['url_path'] . "images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
			print "<td align='right'><a href='" . htmlspecialchars("$filename?action=item_remove&id=" . $item["id"] . "&$url_data") . "'><img src='" . $config['url_path'] . "images/delete_icon.gif' style='height:10px;width:10px;' border='0' alt='Delete'></a></td>\n";
		}

		print "</tr>";

		$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}
}

/* draw_menu - draws the cacti menu for display in the console */
function draw_menu($user_menu = "") {
	global $colors, $config, $user_auth_realms, $user_auth_realm_filenames, $menu;

	if (strlen($user_menu == 0)) {
		$user_menu = $menu;
	}

	/* list all realms that this user has access to */
	if (read_config_option("auth_method") != 0) {
		$user_realms = db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . $_SESSION["sess_user_id"]);
		$user_realms = array_rekey($user_realms, "realm_id", "realm_id");
	}else{
		$user_realms = $user_auth_realms;
	}

	print "<tr><td width='100%'><table cellpadding='3' cellspacing='0' border='0' width='100%'>\n";

	/* loop through each header */
	while (list($header_name, $header_array) = each($user_menu)) {
		/* pass 1: see if we are allowed to view any children */
		$show_header_items = false;
		while (list($item_url, $item_title) = each($header_array)) {
			$current_realm_id = (isset($user_auth_realm_filenames{basename($item_url)}) ? $user_auth_realm_filenames{basename($item_url)} : 0);

			if ((isset($user_realms[$current_realm_id])) || (!isset($user_auth_realm_filenames{basename($item_url)}))) {
				$show_header_items = true;
			}
		}

		reset($header_array);

		if ($show_header_items == true) {
			print "<tr><td class='textMenuHeader'>$header_name</td></tr>\n";
		}

		/* pass 2: loop through each top level item and render it */
		while (list($item_url, $item_title) = each($header_array)) {
			$current_realm_id = (isset($user_auth_realm_filenames{basename($item_url)}) ? $user_auth_realm_filenames{basename($item_url)} : 0);

			/* if this item is an array, then it contains sub-items. if not, is just
			the title string and needs to be displayed */
			if (is_array($item_title)) {
				$i = 0;

				if ($current_realm_id == -1 || (isset($user_realms[$current_realm_id])) || (!isset($user_auth_realm_filenames{basename($item_url)}))) {
					/* if the current page exists in the sub-items array, draw each sub-item */
					if (array_key_exists(basename($_SERVER["PHP_SELF"]), $item_title) == true) {
						$draw_sub_items = true;
					}else{
						$draw_sub_items = false;
					}

					while (list($item_sub_url, $item_sub_title) = each($item_title)) {
						$item_sub_url = $config['url_path'] . $item_sub_url;

						/* indent sub-items */
						if ($i > 0) {
							$prepend_string = "---&nbsp;";
						}else{
							$prepend_string = "";
						}

						/* do not put a line between each sub-item */
						if (($i == 0) || ($draw_sub_items == false)) {
							$background = $config['url_path'] . "images/menu_line.gif";
						}else{
							$background = "";
						}

						/* draw all of the sub-items as selected for ui grouping reasons. we can use the 'bold'
						or 'not bold' to distinguish which sub-item is actually selected */
						if ((basename($_SERVER["PHP_SELF"]) == basename($item_sub_url)) || ($draw_sub_items)) {
							$td_class = "textMenuItemSelected";
						}else{
							$td_class = "textMenuItem";
						}

						/* always draw the first item (parent), only draw the children if we are viewing a page
						that is contained in the sub-items array */
						if (($i == 0) || ($draw_sub_items)) {
							if (basename($_SERVER["PHP_SELF"]) == basename($item_sub_url)) {
								print "<tr><td class='$td_class' style='background-image:url(\"$background\");'>$prepend_string<strong><a href='" . htmlspecialchars($item_sub_url) . "'>$item_sub_title</a></strong></td></tr>\n";
							}else{
								print "<tr><td class='$td_class' style='background-image:url(\"$background\");'>$prepend_string<a href='" . htmlspecialchars($item_sub_url) . "'>$item_sub_title</a></td></tr>\n";
							}
						}

						$i++;
					}
				}
			}else{
				if ($current_realm_id == -1 || (isset($user_realms[$current_realm_id])) || (!isset($user_auth_realm_filenames{basename($item_url)}))) {
					/* draw normal (non sub-item) menu item */
					$item_url = $config['url_path'] . $item_url;
					if (basename($_SERVER["PHP_SELF"]) == basename($item_url)) {
						print "<tr><td class='textMenuItemSelected' style='background-image:url(\"" . $config['url_path'] . "images/menu_line.gif\");'><strong><a href='" . htmlspecialchars($item_url) . "'>$item_title</a></strong></td></tr>\n";
					}else{
						print "<tr><td class='textMenuItem' style='background-image:url(\"" . $config['url_path'] . "images/menu_line.gif\");'><a href='" . htmlspecialchars($item_url) . "'>$item_title</a></td></tr>\n";
					}
				}
			}
		}
	}

	print "<tr><td class='textMenuItem' style='background-image:url(\"" . $config['url_path'] . "images/menu_line.gif\");'></td></tr>\n";

	print "</table></td></tr>";
}

/* draw_actions_dropdown - draws a table the allows the user to select an action to perform
     on one or more data elements
   @arg $actions_array - an array that contains a list of possible actions. this array should
     be compatible with the form_dropdown() function */
function draw_actions_dropdown($actions_array) {
	global $config;
	?>
	<table align='center' width='100%'>
		<tr>
			<td width='1' valign='top'>
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='middle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='submit' value='Go' title='Execute Action'>
			</td>
		</tr>
	</table>

	<input type='hidden' name='action' value='actions'>
	<?php
}

/*
 * Deprecated functions
 */

function DrawMatrixHeaderItem($matrix_name, $matrix_text_color, $column_span = 1) { ?>
		<td class="tableSubHeaderColumn" style="height:1px;" colspan="<?php print $column_span;?>">
			<strong><font color="#<?php print $matrix_text_color;?>"><?php print $matrix_name;?></font></strong>
		</td>
<?php }

function form_area($text) { ?>
	<tr>
		<td bgcolor="#E1E1E1" class="textArea">
			<?php print $text;?>
		</td>
	</tr>
<?php }

?>
