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
	<table align="<?php print $align;?>" width="<?php print $width;?>" cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $background_color;?>">
		<tr>
			<td>
				<table cellpadding=<?php print $cell_padding;?> cellspacing=0 border=0 bgcolor="#<?php print $colors["form_background_dark"];?>" width="100%">
					<?php if ($title != "") {?><tr>
						<td bgcolor="#<?php print $background_color;?>" style="padding: 3px;" colspan="10">
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td bgcolor="#<?php print $background_color;?>" class="textHeaderDark"><?php print $title;?></td>
										<?php if ($add_text != "") {?><td class="textHeaderDark" align="right" bgcolor="#<?php print $colors["header"];?>"><strong><a class="linkOverDark" href="<?php print $add_text;?>">Add</a>&nbsp;</strong></td><?php }?>
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

/* html_header - draws a header row suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header($header_items, $last_item_colspan = 1) {
	global $colors;

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		print "<td " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . "class='textSubHeaderDark'>" . $header_items[$i] . "</td>\n";
	}

	print "</tr>\n";
}

/* html_header_checkbox - draws a header row with a 'select all' checkbox in the last cell
     suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
   @arg $form_action - the url to post the 'select all' form to */
function html_header_checkbox($header_items, $form_action = "") {
	global $colors;

	/* default to the 'current' file */
	if ($form_action == "") { $form_action = basename($_SERVER["PHP_SELF"]); }

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	for ($i=0; $i<count($header_items); $i++) {
		print "<td class='textSubHeaderDark'>" . $header_items[$i] . "</td>\n";
	}

	print "<td width='1%' align='right' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"chk_\",this.checked)'></td>\n<form name='chk' method='post' action='$form_action'>\n";
	print "</tr>\n";
}

/* create_list - draws the items for an html dropdown given an array of data
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
			print '<option value="' . $id . '"';

			if ($form_previous_value == $id) {
			print " selected";
			}

			print ">" . title_trim(null_out_substitutions($form_data[$id]), 75) . "</option>\n";
		}
	}else{
		if (sizeof($form_data) > 0) {
			foreach ($form_data as $row) {
				print "<option value='$row[$column_id]'";

				if ($form_previous_value == $row[$column_id]) {
					print " selected";
				}

				if (isset($row["host_id"])) {
					print ">" . title_trim($row[$column_display], 75) . "</option>\n";
				}else{
					print ">" . title_trim(null_out_substitutions($row[$column_display]), 75) . "</option>\n";
				}
			}
		}
	}
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

	include($config["include_path"] . "/config_arrays.php");

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
		if ($disable_controls == false) { print "<a href='$filename?action=item_edit&id=" . $item["id"] . "&$url_data'>"; }
		print "<strong>Item # " . ($i+1) . "</strong>";
		if ($disable_controls == false) { print "</a>"; }
		print "</td>\n";

		if (empty($item["data_source_name"])) { $item["data_source_name"] = "No Task"; }

		switch (true) {
		case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case ereg("(HRULE|VRULE)", $_graph_type_name):
			$matrix_title = "HRULE: " . $item["value"];
			break;
		case ereg("(COMMENT)", $_graph_type_name):
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
			print "<td><a href='$filename?action=item_movedown&id=" . $item["id"] . "&$url_data'><img src='images/move_down.gif' border='0' alt='Move Down'></a>
					<a href='$filename?action=item_moveup&id=" . $item["id"] . "&$url_data'><img src='images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
			print "<td align='right'><a href='$filename?action=item_remove&id=" . $item["id"] . "&$url_data'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a></td>\n";
		}

		print "</tr>";

		$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}
}

/* draw_menu - draws the cacti menu for display in the console */
function draw_menu() {
	global $colors, $config, $user_auth_realms, $user_auth_realm_filenames;

	include($config["include_path"] . "/config_arrays.php");

	/* list all realms that this user has access to */
	if (read_config_option("global_auth") == "on") {
		$user_realms = db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . $_SESSION["sess_user_id"]);
		$user_realms = array_rekey($user_realms, "realm_id", "realm_id");
	}else{
		$user_realms = $user_auth_realms;
	}

	print "<tr><td width='100%'><table cellpadding='3' cellspacing='0' border='0' width='100%'>\n";

	/* loop through each header */
	while (list($header_name, $header_array) = each($menu)) {
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

				if ((isset($user_realms[$current_realm_id])) || (!isset($user_auth_realm_filenames{basename($item_url)}))) {
					/* if the current page exists in the sub-items array, draw each sub-item */
					if (array_key_exists(basename($_SERVER["PHP_SELF"]), $item_title) == true) {
						$draw_sub_items = true;
					}else{
						$draw_sub_items = false;
					}

					while (list($item_sub_url, $item_sub_title) = each($item_title)) {
						/* indent sub-items */
						if ($i > 0) {
							$prepend_string = "---&nbsp;";
						}else{
							$prepend_string = "";
						}

						/* do not put a line between each sub-item */
						if (($i == 0) || ($draw_sub_items == false)) {
							$background = "images/menu_line.gif";
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
								print "<tr><td class='$td_class' background='$background'>$prepend_string<strong><a href='$item_sub_url'>$item_sub_title</a></strong></td></tr>\n";
							}else{
								print "<tr><td class='$td_class' background='$background'>$prepend_string<a href='$item_sub_url'>$item_sub_title</a></td></tr>\n";
							}
						}

						$i++;
					}
				}
			}else{
				if ((isset($user_realms[$current_realm_id])) || (!isset($user_auth_realm_filenames{basename($item_url)}))) {
					/* draw normal (non sub-item) menu item */
					if (basename($_SERVER["PHP_SELF"]) == basename($item_url)) {
						print "<tr><td class='textMenuItemSelected' background='images/menu_line.gif'><strong><a href='$item_url'>$item_title</a></strong></td></tr>\n";
					}else{
						print "<tr><td class='textMenuItem' background='images/menu_line.gif'><a href='$item_url'>$item_title</a></td></tr>\n";
					}
				}
			}
		}
	}

	print "<tr><td class='textMenuItem' background='images/menu_line.gif'></td></tr>\n";

	print '</table></td></tr>';
}

/* draw_actions_dropdown - draws a table the allows the user to select an action to perform
     on one or more data elements
   @arg $actions_array - an array that contains a list of possible actions. this array should
     be compatible with the form_dropdown() function */
function draw_actions_dropdown($actions_array) {
	?>
	<table align='center' width='98%'>
		<tr>
			<td width='1' valign='top'>
				<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='image' src='images/button_go.gif' alt='Go'>
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
		<td height="1" colspan="<?php print $column_span;?>">
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
