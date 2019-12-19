<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
   @arg $div - end with a starting div
   @arg $cell_padding - the amount of cell padding to use inside of the box
   @arg $align - the HTML alignment to use for the box (center, left, or right)
   @arg $add_text - the url to use when the user clicks 'Add' in the upper-right
        corner of the box ("" for no 'Add' link)
        This function has two method.  This first is for legacy behavior where you
        you pass in a href to the function, and an optional label as $add_label
        The new format accepts an array of hrefs to add to the start box.  The format
        of the array is as follows:

        $add_text = array(
            array(
                'id' => 'uniqueid',
                'href' => 'value',
                'title' => 'title',
                'callback' => true|false,
                'class' => 'fa fa-icon'
            ),
            ...
        );

        If the callback is true, the Cacti attribute will be added to the href
        to present only the contents and not to include both the headers.  If
        the link must go off page, simply make sure $callback is false.  There
        is a requirement to use fontawesome icon sets for this class, but it
        can include other classes.  In addition, the href can be a hash '#' if
        your page has a ready function that has it's own javascript.
   @arg $add_label - used with legacy behavior to add specific text to the link.
        This parameter is only used in the legacy behavior.
 */
function html_start_box($title, $width, $div, $cell_padding, $align, $add_text, $add_label = false) {
	static $table_suffix = 1;

	if ($add_label === false) {
		$add_label = __('Add');
	}

	if (defined('CACTI_VERSION_BETA') && $title != '') {
		$title .= ' [ ' . get_cacti_version_text(false) . ' ]';
	}

	$table_prefix = basename(get_current_page(), '.php');;
	if (!isempty_request_var('action')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('action'));
	} elseif (!isempty_request_var('report')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('report'));
	} elseif (!isempty_request_var('tab')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('tab'));
	}
	$table_id = $table_prefix . $table_suffix;

	if ($title != '') {
		print "<div id='$table_id' class='cactiTable' style='width:$width;text-align:$align;'>";
		print '<div>';
		print "<div class='cactiTableTitle'><span>" . ($title != '' ? $title:'') . '</span></div>';
		print "<div class='cactiTableButton'>";
		if ($add_text != '' && !is_array($add_text)) {
			print "<span class='cactiFilterAdd' title='$add_label'><a class='linkOverDark' href='" . html_escape($add_text) . "'><i class='fa fa-plus'></i></a></span>";
		} else {
			if (is_array($add_text)) {
				if (cacti_sizeof($add_text)) {
					foreach($add_text as $icon) {
						if (isset($icon['callback']) && $icon['callback'] === true) {
							$classo = 'linkOverDark';
						} else {
							$classo = '';
						}

						if (isset($icon['class']) && $icon['class'] !== '') {
							$classi = $icon['class'];
						} else {
							$classi = 'fa fa-plus';
						}

						if (isset($icon['href'])) {
							$href = html_escape($icon['href']);
						} else {
							$href = '#';
						}

						if (isset($icon['title'])) {
							$title = $icon['title'];
						} else {
							$title = $add_label;
						}

						print "<span class='cactiFilterAdd' title='$title'><a" . (isset($icon['id']) ? " id='" . $icon['id'] . "'":'') . " class='$classo' href='$href'><i class='$classi'></i></a></span>";
					}
				}
			} else {
				print '<span> </span>';
			}
		}
		print '</div></div>';

		if ($div === true) {
			print "<div id='$table_id" . "_child' class='cactiTable'>";
		} else {
			print "<table id='$table_id" . "_child' class='cactiTable' style='padding:" . $cell_padding . "px;'>";
		}
	} else {
		print "<div id='$table_id' class='cactiTable' style='width:$width;text-align:$align;'>";

		if ($div === true) {
			print "<div id='$table_id" . "_child' class='cactiTable'>";
		} else {
			print "<table id='$table_id" . "_child' class='cactiTable' style='padding:" . $cell_padding . "px;'>";
		}
	}

	$table_suffix++;
}

/* html_end_box - draws the end of an HTML box
   @arg $trailing_br (bool) - whether to draw a trailing <br> tag after ending
   @arg $div (bool) - whether type of box is div or table */
function html_end_box($trailing_br = true, $div = false) {
	if ($div) {
		print '</div></div>';
	}else {
		print '</table></div>';
	}

	if ($trailing_br == true) {
		print "<div class='break'></div>";
	}
}

/* html_graph_template_multiselect - consistent multiselect javascript library for cacti. */
function html_graph_template_multiselect() {
	?>
	var msWidth = 200;

	$('#graph_template_id').hide().multiselect({
		height: 300,
		menuWidth: 'auto',
		buttonWidth: 'auto',
		noneSelectedText: '<?php print __('All Graphs & Templates');?>',
		selectedText: function(numChecked, numTotal, checkedItems) {
			myReturn = numChecked + ' <?php print __('Templates Selected');?>';
			$.each(checkedItems, function(index, value) {
				if (value.value == '-1') {
					myReturn='<?php print __('All Graphs & Templates');?>';
					return false;
				} else if (value.value == '0') {
					myReturn='<?php print __('Not Templated');?>';
					return false;
				}
			});
			return myReturn;
		},
		checkAllText: '<?php print __('All');?>',
		uncheckAllText: '<?php print __('None');?>',
		uncheckall: function() {
			$(this).multiselect('widget').find(':checkbox:first').each(function() {
				$(this).prop('checked', true);
			});
		},
		close: function(event, ui) {
			applyGraphFilter();
		},
		open: function(event, ui) {
			$("input[type='search']:first").focus();
		},
		click: function(event, ui) {
			checked=$(this).multiselect('widget').find('input:checked').length;

			if (ui.value == -1 || ui.value == 0) {
				if (ui.checked == true) {
					$('#graph_template_id').multiselect('uncheckAll');
					if (ui.value == -1) {
						$(this).multiselect('widget').find(':checkbox:first').prop('checked', true);
					} else {
						$(this).multiselect('widget').find(':checkbox[value="0"]').prop('checked', true);
					}
				}
			} else if (checked == 0) {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).click();
				});
			} else if ($(this).multiselect('widget').find('input:checked:first').val() == '-1') {
				if (checked > 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
						$(this).prop('disable', true);
					});
				}
			} else {
				$(this).multiselect('widget').find(':checkbox[value="0"]').prop('checked', false);
			}
		}
	}).multiselectfilter({
		label: '<?php print __('Search');?>',
		placeholder: '<?php print __('Enter keyword');?>',
		width: msWidth
	});
	<?php
}

/* html_graph_area - draws an area the contains full sized graphs
   @arg $graph_array - the array to contains graph information. for each graph in the
        array, the following two keys must exist
        $arr[0]["local_graph_id"] // graph id
        $arr[0]["title_cache"] // graph title
   @arg $no_graphs_message - display this message if no graphs are found in $graph_array
   @arg $extra_url_args - extra arguments to append to the url
   @arg $header - html to use as a header
   @arg $columns - the number of columns to present */
function html_graph_area(&$graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0) {
	global $config;
	$i = 0; $k = 0; $j = 0;

	$num_graphs = cacti_sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds = <?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start     = <?php print get_current_graph_start();?>;
	var graph_end       = <?php print get_current_graph_end();?>;
	</script>
	<?php

	if ($num_graphs > 0) {
		if ($header != '') {
			print $header;
		}

		foreach ($graph_array as $graph) {
			if ($i == 0) {
				print "<tr class='tableRowGraph'>";
			}

			?>
			<td style='width:<?php print round(100 / $columns, 2);?>%;'>
				<div>
				<table style='text-align:center;margin:auto;'>
					<tr>
						<td>
							<div class='graphWrapper' style='width:100%;' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print ((read_user_setting('custom_fonts') == 'on') ? read_user_setting('title_size') : read_config_option('title_size'));?>'></div>
							<?php print (read_user_setting('show_graph_title') == 'on' ? "<span class='center'>" . html_escape($graph['title_cache']) . '</span>' : '');?>
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

			if (($i % $columns) == 0) {
				$i = 0;
				print '</tr>';
			}
		}

		while(($i % $columns) != 0) {
			print "<td style='text-align:center;width:" . round(100 / $columns, 2) . "%;'></td>";
			$i++;
		}

		print '</tr>';
	} else {
		if ($no_graphs_message != '') {
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
function html_graph_thumbnail_area(&$graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0) {
	global $config;
	$i = 0; $k = 0; $j = 0;

	$num_graphs = cacti_sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds = <?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start     = <?php print get_current_graph_start();?>;
	var graph_end       = <?php print get_current_graph_end();?>;
	</script>
	<?php

	if ($num_graphs > 0) {
		if ($header != '') {
			print $header;
		}

		$start = true;
		foreach ($graph_array as $graph) {
			if (isset($graph['graph_template_name'])) {
				if (isset($prev_graph_template_name)) {
					if ($prev_graph_template_name != $graph['graph_template_name']) {
						$prev_graph_template_name = $graph['graph_template_name'];
					}
				} else {
					$prev_graph_template_name = $graph['graph_template_name'];
				}
			} elseif (isset($graph['data_query_name'])) {
				if (isset($prev_data_query_name)) {
					if ($prev_data_query_name != $graph['data_query_name']) {
						$print  = true;
						$prev_data_query_name = $graph['data_query_name'];
					} else {
						$print = false;
					}
				} else {
					$print  = true;
					$prev_data_query_name = $graph['data_query_name'];
				}

				if ($print) {
					if (!$start) {
						while(($i % $columns) != 0) {
							print "<td style='text-align:center;width:" . round(100 / $columns, 3) . "%;'></td>";
							$i++;
						}

						print '</tr>';
					}

					print "<tr class='tableHeader'>
							<td class='graphSubHeaderColumn textHeaderDark' colspan='$columns'>" . __('Data Query:') . ' ' . $graph['data_query_name'] . '</td>
						</tr>';
					$i = 0;
				}
			}

			if ($i == 0) {
				print "<tr class='tableRowGraph'>";
				$start = false;
			}

			?>
			<td style='width:<?php print round(100 / $columns, 2);?>%;'>
				<table style='text-align:center;margin:auto;'>
					<tr>
						<td>
							<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print read_user_setting('default_width');?>' graph_height='<?php print read_user_setting('default_height');?>'></div>
							<?php print (read_user_setting('show_graph_title') == 'on' ? "<span class='center'>" . html_escape($graph['title_cache']) . '</span>' : '');?>
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
				print '</tr>';
				$start = true;
			}
		}

		if (!$start) {
			while(($i % $columns) != 0) {
				print "<td style='text-align:center;width:" . round(100 / $columns, 2) . "%;'></td>";
				$i++;
			}

			print '</tr>';
		}
	} else {
		if ($no_graphs_message != '') {
			print "<td><em>$no_graphs_message</em></td>";
		}
	}
}

function graph_drilldown_icons($local_graph_id, $type = 'graph_buttons') {
	global $config;
	static $rand = 0;

	$aggregate_url = aggregate_build_children_url($local_graph_id);

	print "<div class='iconWrapper'>";
	print "<a class='iconLink utils' href='#' role='link' id='graph_" . $local_graph_id . "_util'><img class='drillDown' src='" . $config['url_path'] . "images/cog.png' alt='' title='" . __esc('Graph Details, Zooming and Debugging Utilities') . "'></a><br>";
	print "<a class='iconLink csvexport' href='#' role='link' id='graph_" . $local_graph_id . "_csv'><img class='drillDown' src='" . $config['url_path'] . "images/table_go.png' alt='' title='" . __esc('CSV Export of Graph Data'). "'></a><br>";
	print "<a class='iconLink mrgt' href='#' role='link' id='graph_" . $local_graph_id . "_mrtg'><img class='drillDown' src='" . $config['url_path'] . "images/timeview.png' alt='' title='" . __esc('Time Graph View'). "'></a><br>";

	if (is_realm_allowed(3)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			array($local_graph_id));

		if ($host_id > 0) {
			print "<a class='iconLink' href='" . html_escape($config['url_path'] . "host.php?action=edit&id=$host_id") . "' data-graph='" . $local_graph_id . "' id='graph_" . $local_graph_id . "_de'><img id='de" . $host_id . '_' . $rand . "' class='drillDown' src='" . $config['url_path'] . "images/server_edit.png' title='" . __esc('Edit Device') . "'></a>";
			print '<br/>';
			$rand++;
		}
	}

	if (read_config_option('realtime_enabled') == 'on' && is_realm_allowed(25)) {
		if (read_user_setting('realtime_mode') == '' || read_user_setting('realtime_mode') == '1') {
			print "<a class='iconLink realtime' href='#' role='link' id='graph_" . $local_graph_id . "_realtime'><img class='drillDown' src='" . $config['url_path'] . "images/chart_curve_go.png' alt='' title='" . __esc('Click to view just this Graph in Real-time'). "'></a><br/>";
		} else {
			print "<a class='iconLink' href='#' onclick=\"window.open('" . $config['url_path'] . 'graph_realtime.php?top=0&left=0&local_graph_id=' . $local_graph_id . "', 'popup_" . $local_graph_id . "', 'directories=no,titlebar=no,toolbar=no,location=no,status=no,menubar=no,scrollbars=no,resizable=yes,width=650,height=300');return false\"><img src='" . $config['url_path'] . "images/chart_curve_go.png' alt='' title='" . __esc('Click to view just this Graph in Real-time') . "'></a><br/>";
		}
	}

	if (is_realm_allowed(1043)) {
		print "<span class='iconLink spikekill' data-graph='" . $local_graph_id . "' id='graph_" . $local_graph_id . "_sk'><img id='sk" . $local_graph_id . "' class='drillDown' src='" . $config['url_path'] . "images/spikekill.gif' title='" . __esc('Kill Spikes in Graphs') . "'></span>";
		print '<br/>';
	}

	if ($aggregate_url != '') {
		print $aggregate_url;
	}

	api_plugin_hook($type, array('hook' => 'graph_buttons_thumbnails', 'local_graph_id' => $local_graph_id, 'rra' =>  0, 'view_type' => ''));

	print '</div>';
}

/* html_nav_bar - draws a navigation bar which includes previous/next links as well as current
	page information
   @arg $base_url - the base URL will all filter options except page (should include url_path)
   @arg $max_pages - the maximum number of pages to display
   @arg $current_page - the current page in the navigation system
   @arg $rows_per_page - the number of rows that are displayed on a single page
   @arg $total_rows - the total number of rows in the navigation system
   @arg $object - the object types that is being displayed
   @arg $page_var - the object types that is being displayed
   @arg $return_to - paint the resulting page into this dom object */
function html_nav_bar($base_url, $max_pages, $current_page, $rows_per_page, $total_rows, $colspan=30, $object = '', $page_var = 'page', $return_to = '') {
	if ($object == '') $object = __('Rows');

	if ($total_rows > $rows_per_page) {
		if (substr_count($base_url, '?') == 0) {
			$base_url = trim($base_url) . '?';
		} else {
			$base_url = trim($base_url) . '&';
		}

		$url_page_select = get_page_list($current_page, $max_pages, $rows_per_page, $total_rows, $base_url, $page_var, $return_to);

		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationPrevious'>
				" . (($current_page > 1) ? "<a href='#' onClick='goto$page_var(" . ($current_page-1) . ");return false;'><i class='fa fa-angle-double-left previous'></i>" . __('Previous'). '</a>':'') . "
			</div>
			<div class='navBarNavigationCenter'>
				" . __('%d to %d of %s [ %s ]', (($rows_per_page*($current_page-1))+1), (($total_rows < $rows_per_page) || ($total_rows < ($rows_per_page*$current_page)) ? $total_rows : $rows_per_page*$current_page), $total_rows, $url_page_select) . "
			</div>
			<div class='navBarNavigationNext'>
				" . (($current_page*$rows_per_page) < $total_rows ? "<a href='#' onClick='goto$page_var(" . ($current_page+1) . ");return false;'>" . __('Next'). "<i class='fa fa-angle-double-right next'></i></a>":'') . "
			</div>
		</div>";
	} elseif ($total_rows > 0) {
		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationNone'>
				" . __('All %d %s', $total_rows, $object) . '
			</div>
		</div>';
	} else {
		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationNone'>
				" . __('No %s Found', $object) . '
			</div>
		</div>';
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
   @arg $url - a base url to redirect sort actions to
   @arg $return_to - the id of the object to inject output into as a result of the sort action */
function html_header_sort($header_items, $sort_column, $sort_direction, $last_item_colspan = 1, $url = '', $return_to = '') {
	/* reverse the sort direction */
	if ($sort_direction == 'ASC') {
		$new_sort_direction = 'DESC';
	} else {
		$new_sort_direction = 'ASC';
	}

	$page = str_replace('.php', '', basename($_SERVER['SCRIPT_NAME']));
	if (isset_request_var('action')) {
		$page .= '_' . get_request_var('action');
	}

	if (isset_request_var('tab')) {
		$page .= '_' . get_request_var('tab');
	}

	if (isset($_SESSION['sort_data'][$page])) {
		$order_data = $_SESSION['sort_data'][$page];
	} else {
		$order_data = array(get_request_var('sort_column') => get_request_var('sort_direction'));
	}

	foreach($order_data as $key => $direction) {
		$primarySort = $key;
		break;
	}

	print "<tr class='tableHeader'>";

	$i = 1;
	foreach ($header_items as $db_column => $display_array) {
		$isSort = '';
		if (isset($display_array['nohide'])) {
			$nohide = 'nohide';
		} else {
			$nohide = '';
		}

		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];
			if ($sort_column == $db_column) {
				$icon      = $sort_direction;
				$direction = $new_sort_direction;

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];
					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';
					if (isset($display_array['sort'])) {
						$direction = $display_array['sort'];
					} else {
						$direction = 'ASC';
					}
				}
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			} else {
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			} else {
				$tip = '';
			}
		} else {
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon         = $sort_direction;
				$direction    = $new_sort_direction;
				$display_text = $display_array[0];

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];
					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';
					$direction = $display_array[1];
				}

				$display_text = $display_array[0];
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'fa fa-sort-up';
		} elseif (strtolower($icon) == 'desc') {
			$icon = 'fa fa-sort-down';
		} else {
			$icon = 'fa fa-sort';
		}

		if (($db_column == '') || (substr_count($db_column, 'nosort'))) {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='$nohide $align' " . ((($i+1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . $display_text . '</th>';
		} else {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='sortable $align $nohide $isSort'>";
			print "<div class='sortinfo' sort-return='" . ($return_to == '' ? 'main':$return_to) . "' sort-page='" . ($url == '' ? html_escape(get_current_page(false)):$url) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>";
		}

		$i++;
	}

	print '</tr>';
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
   @arg $form_action - the url to post the 'select all' form to
   @arg $return_to - the id of the object to inject output into as a result of the sort action */
function html_header_sort_checkbox($header_items, $sort_column, $sort_direction, $include_form = true, $form_action = '', $return_to = '') {
	static $page = 0;

	/* reverse the sort direction */
	if ($sort_direction == 'ASC') {
		$new_sort_direction = 'DESC';
	} else {
		$new_sort_direction = 'ASC';
	}

	$page = str_replace('.php', '', basename($_SERVER['SCRIPT_NAME']));
	if (isset_request_var('action')) {
		$page .= '_' . get_request_var('action');
	}

	if (isset_request_var('tab')) {
		$page .= '_' . get_request_var('tab');
	}

	if (isset($_SESSION['sort_data'][$page])) {
		$order_data = $_SESSION['sort_data'][$page];
	} else {
		$order_data = array(get_request_var('sort_column') => get_request_var('sort_direction'));
	}

	foreach($order_data as $key => $direction) {
		$primarySort = $key;
		break;
	}

	/* default to the 'current' file */
	if ($form_action == '') { $form_action = get_current_page(); }

	print "<tr class='tableHeader'>";

	foreach($header_items as $db_column => $display_array) {
		$isSort = '';
		if (isset($display_array['nohide'])) {
			$nohide = 'nohide';
		} else {
			$nohide = '';
		}

		$icon   = '';
		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];
			if ($sort_column == $db_column) {
				$icon      = $sort_direction;
				$direction = $new_sort_direction;

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];
					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';
					if (isset($display_array['sort'])) {
						$direction = $display_array['sort'];
					} else {
						$direction = 'ASC';
					}
				}
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			} else {
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			} else {
				$tip = '';
			}
		} else {
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon         = $sort_direction;
				$direction    = $new_sort_direction;
				$display_text = $display_array[0];

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];
					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';
					$direction = $display_array[1];
				}

				$display_text = $display_array[0];
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'fa fa-sort-up';
		} elseif (strtolower($icon) == 'desc') {
			$icon = 'fa fa-sort-down';
		} else {
			$icon = 'fa fa-sort';
		}

		if (($db_column == '') || (substr_count($db_column, 'nosort'))) {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='$align $nohide'>" . $display_text . '</th>';
		} else {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='sortable $align $nohide $isSort'>";
			print "<div class='sortinfo' sort-return='" . ($return_to == '' ? 'main':$return_to) . "' sort-page='" . html_escape($form_action) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>";
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __esc('Select All Rows'). "' onClick='selectAll(\"chk\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows') . "' for='selectall'></label></th>" . ($include_form ? "<th style='display:none;'><form id='chk' name='chk' method='post' action='$form_action'></th>":'');
	print '</tr>';

	$page++;
}

/* html_header - draws a header row suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
        alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header($header_items, $last_item_colspan = 1) {
	print "<tr class='tableHeader " . (!$last_item_colspan > 1 ? 'tableFixed':'') . "'>";

	$i = 0;
	foreach($header_items as $item) {
		if (is_array($item)) {
			if (isset($item['nohide'])) {
				$nohide = 'nohide';
			} else {
				$nohide = '';
			}

			if (isset($item['align'])) {
				$align = $item['align'];
			} else {
				$align = 'left';
			}

			if (isset($item['tip'])) {
				$tip = $item['tip'];
			} else {
				$tip = '';
			}

			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "' ":'') . "class='$nohide $align' " . ((($i+1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . html_escape($item['display']) . '</th>';
		} else {
			print '<th ' . ((($i+1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . html_escape($item) . '</th>';
		}

		$i++;
	}

	print '</tr>';
}

/* html_section_header - draws a header row suitable for display inside of a box element
         but for display as a secton title and not as a series of table header columns
   @arg $header_name - an array of the display name of the header for the section and
        optional alignment.
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_section_header($header_item, $last_item_colspan = 1) {
	print "<tr class='tableHeader " . (!$last_item_colspan > 1 ? 'tableFixed':'') . "'>";

	if (is_array($header_item) && isset($header_item['display'])) {
		print "<th " . (isset($header_item['align']) ? "style='text-align:" . $header_item['align'] . ";'":"") . " colspan='$last_item_colspan'>" . $header_item['display'] . '</th>';
	} else {
		print "<th colspan='$last_item_colspan'>" . $header_item . '</th>';
	}

	print '</tr>';
}

/* html_header_checkbox - draws a header row with a 'select all' checkbox in the last cell
        suitable for display inside of a box element
   @arg $header_items - an array containing a list of items to be included in the header
        alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
   @arg $form_action - the url to post the 'select all' form to */
function html_header_checkbox($header_items, $include_form = true, $form_action = '', $resizable = true) {
	/* default to the 'current' file */
	if ($form_action == '') { $form_action = get_current_page(); }

	print "<tr class='tableHeader " . (!$resizable ? 'tableFixed':'') . "'>";

	foreach($header_items as $item) {
		if (is_array($item)) {
			if (isset($item['nohide'])) {
				$nohide = 'nohide';
			} else {
				$nohide = '';
			}

			if (isset($item['align'])) {
				$align = $item['align'];
			} else {
				$align = 'left';
			}

			if (isset($item['tip'])) {
				$tip = $item['tip'];
			} else {
				$tip = '';
			}

			print '<th ' . ($tip != '' ? " title='" . html_escape($tip) . "' ":'') . "class='$align $nohide'>" . html_escape($item['display']) . '</th>';
		} else {
			print "<th class='left'>" . html_escape($item) . '</th>';
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __esc('Select All Rows'). "' onClick='selectAll(\"chk\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='selectall'></label></th>" . ($include_form ? "<th style='display:none;'><form id='chk' name='chk' method='post' action='$form_action'></th>":'');
	print '</tr>';
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
		if (cacti_sizeof($form_data)) {
			foreach (array_keys($form_data) as $id) {
				print '<option value="' . html_escape($id) . '"';

				if ($form_previous_value == $id) {
					print ' selected';
				}

				print '>' . title_trim(null_out_substitutions(html_escape($form_data[$id])), 75) . '</option>';
			}
		}
	} else {
		if (cacti_sizeof($form_data)) {
			foreach ($form_data as $row) {
				print "<option value='" . html_escape($row[$column_id]) . "'";

				if ($form_previous_value == $row[$column_id]) {
					print ' selected';
				}

				if (isset($row['host_id'])) {
					print '>' . title_trim(html_escape($row[$column_display]), 75) . '</option>';
				} else {
					print '>' . title_trim(null_out_substitutions(html_escape($row[$column_display])), 75) . '</option>';
				}
			}
		}
	}
}

/* html_escape_request_var - sanitizes a request variable for display
   @arg $string - string the request variable to escape
   @returns $new_string - the escaped request variable to be returned. */
function html_escape_request_var($string) {
	return html_escape(get_request_var($string));
}

/* html_escape - sanitizes a string for display
   @arg $string - string the string to escape
   @returns $new_string - the escaped string to be returned. */
function html_escape($string) {
	static $charset;

	if ($charset == '') {
		$charset = ini_get('default_charset');
	}

	if ($charset == '') {
		$charset = 'UTF-8';
	}

	return htmlspecialchars($string, ENT_QUOTES, $charset, false);
}

/* html_split_string - takes a string and breaks it into a number of <br> separated segments
   @arg $string - string to be modified and returned
   @arg $length - the maximal string length to split to
   @arg $forgiveness - the maximum number of characters to walk back from to determine
        the correct break location.
   @returns $new_string - the modified string to be returned. */
function html_split_string($string, $length = 90, $forgiveness = 10) {
	$new_string = '';
	$j    = 0;
	$done = false;

	while (!$done) {
		if (mb_strlen($string, 'UTF-8') > $length) {
			for($i = 0; $i < $forgiveness; $i++) {
				if (substr($string, $length-$i, 1) == ' ') {
					$new_string .= mb_substr($string, 0, $length-$i, 'UTF-8') . '<br>';

					break;
				}
			}

			$string = mb_substr($string, $length-$i, NULL, 'UTF-8');
		} else {
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

	include($config['include_path'] . '/global_arrays.php');

	print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Graph Item'),'',1);
		DrawMatrixHeaderItem(__('Data Source'),'',1);
		DrawMatrixHeaderItem(__('Graph Item Type'),'',1);
		DrawMatrixHeaderItem(__('CF Type'),'',1);
		DrawMatrixHeaderItem(__('Alpha %'),'',1);
		DrawMatrixHeaderItem(__('Item Color'),'',4);
	print '</tr>';

	$group_counter = 0; $_graph_type_name = ''; $i = 0;

	if (cacti_sizeof($item_list)) {
		foreach ($item_list as $item) {
			/* graph grouping display logic */
			$this_row_style   = '';
			$use_custom_class = false;
			$hard_return      = '';

			if (!preg_match('/(GPRINT|TEXTALIGN|HRULE|VRULE|TICK)/', $graph_item_types[$item['graph_type_id']])) {
				$this_row_style = 'font-weight: bold;';
				$use_custom_class = true;

				if ($group_counter % 2 == 0) {
					$customClass = 'graphItem';
				} else {
					$customClass = 'graphItemAlternate';
				}

				$group_counter++;
			}

			$_graph_type_name = $graph_item_types[$item['graph_type_id']];

			/* alternating row color */
			if ($use_custom_class == false) {
				print "<tr class='tableRowGraph'>";
			} else {
				print "<tr class='tableRowGraph $customClass'>";
			}

			print '<td>';
			if ($disable_controls == false) { print "<a class='linkEditMain' href='" . html_escape("$filename?action=item_edit&id=" . $item['id'] . "&$url_data") . "'>"; }
			print __('Item # %d', ($i+1));
			if ($disable_controls == false) { print '</a>'; }
			print '</td>';

			if (empty($item['data_source_name'])) { $item['data_source_name'] = __('No Task'); }

			switch (true) {
			case preg_match('/(TEXTALIGN)/', $_graph_type_name):
				$matrix_title = 'TEXTALIGN: ' . ucfirst($item['textalign']);
				break;
			case preg_match('/(TICK)/', $_graph_type_name):
				$matrix_title = '(' . $item['data_source_name'] . '): ' . $item['text_format'];
				break;
			case preg_match('/(AREA|STACK|GPRINT|LINE[123])/', $_graph_type_name):
				$matrix_title = '(' . $item['data_source_name'] . '): ' . $item['text_format'];
				break;
			case preg_match('/(HRULE)/', $_graph_type_name):
				$matrix_title = 'HRULE: ' . $item['value'];
				break;
			case preg_match('/(VRULE)/', $_graph_type_name):
				$matrix_title = 'VRULE: ' . $item['value'];
				break;
			case preg_match('/(COMMENT)/', $_graph_type_name):
				$matrix_title = 'COMMENT: ' . $item['text_format'];
				break;
			}

			if (preg_match('/(TEXTALIGN)/', $_graph_type_name)) {
				$hard_return = '';
			} elseif ($item['hard_return'] == 'on') {
				$hard_return = "<span style='font-weight:bold;color:#FF0000;'>&lt;HR&gt;</span>";
			}

			/* data source */
			print "<td style='$this_row_style'>" . html_escape($matrix_title) . $hard_return . '</td>';

			/* graph item type */
			print "<td style='$this_row_style'>" . $graph_item_types[$item['graph_type_id']] . '</td>';
			if (!preg_match('/(TICK|TEXTALIGN|HRULE|VRULE)/', $_graph_type_name)) {
				print "<td style='$this_row_style'>" . $consolidation_functions[$item['consolidation_function_id']] . '</td>';
			} else {
				print '<td>' . __('N/A') . '</td>';
			}

			/* alpha type */
			if (preg_match('/(AREA|STACK|TICK|LINE[123])/', $_graph_type_name)) {
				print "<td style='$this_row_style'>" . round((hexdec($item['alpha'])/255)*100) . '%</td>';
			} else {
				print "<td style='$this_row_style'></td>";
			}


			/* color name */
			if (!preg_match('/(TEXTALIGN)/', $_graph_type_name)) {
				print "<td style='width:1%;" . ((!empty($item['hex'])) ? 'background-color:#' . $item['hex'] . ";'" : "'") . '></td>';
				print "<td style='$this_row_style'>" . $item['hex'] . '</td>';
			} else {
				print '<td></td><td></td>';
			}

			if ($disable_controls == false) {
				print "<td style='text-align:right;padding-right:10px;'>";
				if ($i != cacti_sizeof($item_list)-1) {
					print "<a class='moveArrow fa fa-caret-down' title='" . __esc('Move Down'). "' href='" . html_escape("$filename?action=item_movedown&id=" . $item['id'] . "&$url_data") . "'></a>";
				} else {
					print "<span class='moveArrowNone'></span>";
				}
				if ($i > 0) {
					print "<a class='moveArrow fa fa-caret-up' title='" . __esc('Move Up') . "' href='" . html_escape("$filename?action=item_moveup&id=" . $item['id'] . "&$url_data") . "'></a>";
				} else {
					print "<span class='moveArrowNone'></span>";
				}
				print '</td>';

				print "<td style='text-align:right;'><a class='deleteMarker fa fa-times' title='" . __esc('Delete') . "' href='" . html_escape("$filename?action=item_remove&id=" . $item['id'] . "&$url_data") . "'></a></td>";
			}

			print '</tr>';

			$i++;
		}
	} else {
		print "<tr class='tableRow'><td colspan='7'><em>" . __('No Items') . '</em></td></tr>';
	}
}

/* is_menu_pick_active - determines if current selection is active
   @arg $menu_url - url of current page
   @returns true if active, false if not
*/
function is_menu_pick_active($menu_url) {
	static $url_array, $url_parts;

	$menu_parts = array();

	/* special case for host.php?action=edit&create=true */
	if (strpos($_SERVER['REQUEST_URI'], 'host.php?action=edit&create=true') !== false) {
		if (strpos($menu_url, 'host.php?action=edit&create=true') !== false) {
			return true;
		} else {
			return false;
		}
	}

	/* break out the URL and variables */
	if (!is_array($url_array) || (is_array($url_array) && !cacti_sizeof($url_array))) {
		$url_array = parse_url($_SERVER['REQUEST_URI']);
		if (isset($url_array['query'])) {
			parse_str($url_array['query'], $url_parts);
		} else {
			$url_parts = array();
		}
	}

	$menu_array = parse_url($menu_url);
	if ($menu_array === false) {
		return false;
	}

	if (! array_key_exists('path', $menu_array)) {
		return false;
	}

	if (basename($url_array['path']) == basename($menu_array['path'])) {
		if (isset($menu_array['query'])) {
			parse_str($menu_array['query'], $menu_parts);
		} else {
			$menu_parts = array();
		}

		if (isset($menu_parts['id'])) {
			if (isset($url_parts['id'])) {
				if ($menu_parts['id'] == $url_parts['id']) {
					return true;
				}
			}
		} elseif (isset($menu_parts['action'])) {
			if (isset($url_parts['action'])) {
				if ($menu_parts['action'] == $url_parts['action']) {
					return true;
				}
			}
		} else {
			return true;
		}
	}

	return false;
}

/* draw_menu - draws the cacti menu for display in the console */
function draw_menu($user_menu = '') {
	global $config, $user_auth_realm_filenames, $menu, $menu_glyphs;

	if (!is_array($user_menu)) {
		$user_menu = $menu;
	}

	print "<tr><td><table><tr><td><div id='menu'><ul id='nav' role='menu'>";

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
				} else {
					$show_header_items = false;
				}
			} else {
				$current_realm_id = (isset($user_auth_realm_filenames[basename($item_url)]) ? $user_auth_realm_filenames[basename($item_url)] : 0);

				if (is_realm_allowed($current_realm_id)) {
					$show_header_items = true;
				} elseif (api_user_realm_auth(strtok($item_url, '?'))) {
					$show_header_items = true;
				}
			}
		}

		if ($show_header_items == true) {
			// Let's give our menu li's a unique id
			$id = 'menu_' . strtolower(clean_up_name($header_name));
			if (isset($headers[$id])) {
				$id .= '_' . $i++;
			}
			$headers[$id] = true;

			if (isset($menu_glyphs[$header_name])) {
				$glyph = '<i class="menu_glyph ' . $menu_glyphs[$header_name] . '"></i>';
			} else {
				$glyph = '<i class="menu_glyph fa fa-folder"></i>';
			}

			print "<li class='menuitem' role='menuitem' aria-haspopup='true' id='$id'><a class='menu_parent active' href='#'>$glyph<span>$header_name</span></a>";
			print "<ul role='menu' id='${id}_div' style='display:block;'>";

			/* pass 2: loop through each top level item and render it */
			foreach ($header_array as $item_url => $item_title) {
				$basename = explode('?', basename($item_url));
				$basename = $basename[0];
				$current_realm_id = (isset($user_auth_realm_filenames[$basename]) ? $user_auth_realm_filenames[$basename] : 0);

				/* if this item is an array, then it contains sub-items. if not, is just
				the title string and needs to be displayed */
				if (is_array($item_title)) {
					$i = 0;

					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames[$basename])) {
						/* if the current page exists in the sub-items array, draw each sub-item */
						if (array_key_exists(get_current_page(), $item_title) == true) {
							$draw_sub_items = true;
						} else {
							$draw_sub_items = false;
						}

						foreach ($item_title as $item_sub_url => $item_sub_title) {
							if (substr($item_sub_url, 0, 10) == 'EXTERNAL::') {
								$item_sub_external = true;
								$item_sub_url = substr($item_sub_url, 10);
							} else {
								$item_sub_external = false;
								$item_sub_url = $config['url_path'] . $item_sub_url;
							}

							/* always draw the first item (parent), only draw the children if we are viewing a page
							that is contained in the sub-items array */
							if (($i == 0) || ($draw_sub_items)) {
								if (is_menu_pick_active($item_sub_url)) {
									print "<li><a role='menuitem' class='pic selected' href='";
									print html_escape($item_sub_url) . "'";
									if ($item_sub_external) {
										print " target='_blank'";
									}
									print ">$item_sub_title</a></li>";
								} else {
									print "<li><a role='menuitem' class='pic' href='";
									print html_escape($item_sub_url) . "'";
									if ($item_sub_external) {
										print " target='_blank'";
									}
									print ">$item_sub_title</a></li>";
								}
							}

							$i++;
						}
					}
				} else {
					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames[$basename])) {
						/* draw normal (non sub-item) menu item */
						if (substr($item_url, 0, 10) == 'EXTERNAL::') {
							$item_external = true;
							$item_url = substr($item_url, 10);
						} else {
							$item_external = false;
							$item_url = $config['url_path'] . $item_url;
						}
						if (is_menu_pick_active($item_url)) {
							print "<li><a role='menuitem' class='pic selected' href='";
							print html_escape($item_url) . "'";
							if ($item_external) {
								print " target='_blank'";
							}
							print ">$item_title</a></li>";
						} else {
							print "<li><a role='menuitem' class='pic' href='";
							print html_escape($item_url) . "'";
							if ($item_external) {
								print " target='_blank'";
							}
							print ">$item_title</a></li>";
						}
					}
				}
			}

			print '</ul></li>';
		}
	}

	print '</ul></div></td></tr></table></td></tr>';
}

/* draw_actions_dropdown - draws a table the allows the user to select an action to perform
        on one or more data elements
   @arg $actions_array - an array that contains a list of possible actions. this array should
        be compatible with the form_dropdown() function
   @arg $delete_action - if there is a delete action that should surpress removal of rows
        specify it here.  If you don't want any delete actions, set to 0.*/
function draw_actions_dropdown($actions_array, $delete_action = 1) {
	global $config;

	if ($actions_array === NULL || cacti_sizeof($actions_array) == 0) {
		return;
	}

	if (!isset($actions_array[0])) {
		$my_actions[0]  = __('Choose an action');
		$my_actions    += $actions_array;
		$actions_array  = $my_actions;
	}

	?>
	<div class='actionsDropdown'>
		<div>
			<span class='actionsDropdownArrow'><img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt=''></span>
			<?php form_dropdown('drp_action', $actions_array, '', '', '0', '', '');?>
			<span class='actionsDropdownButton'><input type='submit' class='ui-button ui-corner-all ui-widget' id='submit' value='<?php print __esc('Go');?>' title='<?php print __esc('Execute Action');?>'></span>
		</div>
	</div>
	<input type='hidden' id='action' name='action' value='actions'>
	<script type='text/javascript'>

	function setDisabled() {
		$('tr[id^="line"]').addClass('selectable').prop('disabled', false).removeClass('disabled_row').unbind('click').prop('disabled', false);

		if ($('#drp_action').val() == <?php print $delete_action;?>) {
			$(':checkbox.disabled').each(function(data) {
				$(this).closest('tr').addClass('disabled_row');
				if ($(this).is(':checked')) {
					$(this).prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
					$(this).closest('tr').removeClass('selected');
				}
				$(this).prop('disabled', true).closest('tr').removeClass('selected');
			});

			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('enable');
				} else {
					$(this).prop('disabled', false);
				}
			});
		} else if ($('#drp_action').val() == 0) {
			$(':checkbox.disabled').each(function(data) {
				$(this).prop('disabled', false);
			});

			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('disable');
				} else {
					$(this).prop('disabled', true);
				}
			});
		} else if (<?php print $delete_action;?> != 0) {
			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('enable');
				} else {
					$(this).prop('disabled', false);
				}
			});
		}

		$('tr[id^="line"]').filter(':not(.disabled_row)').off('click').on('click', function(event) {
			selectUpdateRow(event, $(this));
		});
	}

	$(function() {
		setDisabled();

		$('#drp_action').change(function() {
			setDisabled();
		});

		$('.tableSubHeaderCheckbox').find(':checkbox').off('click').on('click', function(data) {
			if ($(this).is(':checked')) {
				$('input[id^="chk_"]').not(':disabled').prop('checked', true).attr('data-prev-check', 'true').attr('aria-checked', 'true');
				$('tr.selectable').addClass('selected');
				disableSelection();
			} else {
				$('input[id^="chk_"]').not(':disabled').prop('checked', false).removeAttr('data-prev-check').removeAttr('aria-checked');
				$('tr.selectable').removeClass('selected');
				enableSelection();
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
		<td class='textArea'>
			<?php print $text;?>
		</td>
	</tr>
<?php }

/* is_console_page - determinese if current passed url is considered to be
          a console page
   @arg url - url to be checked
   @returns true if console page, false if not
*/
function is_console_page($url) {
	global $menu;

	$basename = basename($url);

	if ($basename == 'index.php') {
		return true;
	}

	if ($basename == 'rrdcleaner.php') {
		return true;
	}

	if (api_plugin_hook_function('is_console_page', $url) != $url) {
		return true;
	}

	if (cacti_sizeof($menu)) {
		foreach($menu as $section => $children) {
			if (cacti_sizeof($children)) {
				foreach($children as $page => $name) {
					if (basename($page) == $basename) {
						return true;
					}
				}
			}
		}
	}

	return false;
}

function html_show_tabs_left() {
	global $config, $tabs_left;

	$realm_allowed     = array();
	$realm_allowed[7]  = is_realm_allowed(7);
	$realm_allowed[8]  = is_realm_allowed(8);
	$realm_allowed[18] = is_realm_allowed(18);
	$realm_allowed[19] = is_realm_allowed(19);
	$realm_allowed[21] = is_realm_allowed(21);
	$realm_allowed[22] = is_realm_allowed(22);

	if ($realm_allowed[8]) {
		$show_console_tab = true;
	} else {
		$show_console_tab = false;
	}

	if (get_selected_theme() == 'classic') {
		if ($show_console_tab == true) {
			?><a id='tab-console' <?php print (is_console_page(get_current_page()) ? " class='selected'":'');?> href='<?php echo $config['url_path']; ?>index.php'><img src='<?php echo $config['url_path']; ?>images/tab_console<?php print (is_console_page(get_current_page()) ? '_down':'');?>.gif' alt='<?php print __('Console');?>'></a><?php
		}

		if ($realm_allowed[7]) {
			if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
				// Don't show graphs tab when offline
			} else {
				$file = get_current_page();
				if ($file == 'graph_view.php' || $file == 'graph.php') {
					print "<a id='tab-graphs' class='selected' href='" . html_escape($config['url_path'] . 'graph_view.php') . "'><img src='" . $config['url_path'] . "images/tab_graphs_down.gif' alt='" . __('Graphs') . "'></a>";
				} else {
					print "<a id='tab-graphs' href='" . html_escape($config['url_path'] . 'graph_view.php') . "'><img src='" . $config['url_path'] . "images/tab_graphs.gif' alt='" . __('Graphs') . "'></a>";
				}
			}
		}

		if ($realm_allowed[21] || $realm_allowed[22]) {
			if ($config['poller_id'] > 1) {
				// Don't show reports tabe if not poller 1
			} else {
				if (substr_count($_SERVER['REQUEST_URI'], 'reports_')) {
					print '<a id="tab-reports" href="' . $config['url_path'] . ($realm_allowed[22] ? 'reports_admin.php':'reports_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_nectar_down.gif" alt="' . __('Reporting') . '"></a>';
				} else {
					print '<a id="tab-reports" href="' . $config['url_path'] . ($realm_allowed[22] ? 'reports_admin.php':'reports_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_nectar.gif" alt="' . __('Reporting') . '"></a>';
				}
			}
		}

		if ($realm_allowed[18] || $realm_allowed[19]) {
			if (substr_count($_SERVER['REQUEST_URI'], 'clog')) {
				print '<a id="tab-logs" href="' . $config['url_path'] . ($realm_allowed[18] ? 'clog.php':'clog_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_clog_down.png" alt="' . __('Logs'). '"></a>';
			} else {
				print '<a id="tab-logs" href="' . $config['url_path'] . ($realm_allowed[18] ? 'clog.php':'clog_user.php') . '"><img src="' . $config['url_path'] . 'images/tab_clog.png" alt="' . __('Logs') . '"></a>';
			}
		}

		api_plugin_hook('top_graph_header_tabs');

		if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
			// Only show external links when online
		} else {
			$external_links = db_fetch_assoc('SELECT id, title
				FROM external_links
				WHERE style="TAB"
				AND enabled="on"
				ORDER BY sortorder');

			if (cacti_sizeof($external_links)) {
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

						print '<a id="tab-link' . $tab['id'] . '" href="' . $config['url_path'] . 'link.php?id=' . $tab['id'] . '"><img src="' . get_classic_tabimage($tab['title'], $down) . '" alt="' . $tab['title'] . '"></a>';
					}
				}
			}
		}
	} else {
		if ($show_console_tab) {
			$tabs_left[] =
			array(
				'title' => __('Console'),
				'id'	=> 'tab-console',
				'url'   => $config['url_path'] . 'index.php',
			);
		}

		if ($realm_allowed[7]) {
			if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
				// Don't show the graphs tab when offline
			} else {
				$tabs_left[] =
					array(
						'title' => __('Graphs'),
						'id'	=> 'tab-graphs',
						'url'   => $config['url_path'] . 'graph_view.php',
					);
			}
		}

		if ($realm_allowed[21] || $realm_allowed[22]) {
			if ($config['poller_id'] > 1) {
				// Don't show the reports tab on other pollers
			} else {
				$tabs_left[] =
					array(
						'title' => __('Reporting'),
						'id'	=> 'tab-reports',
						'url'   => $config['url_path'] . ($realm_allowed[22] ? 'reports_admin.php':'reports_user.php'),
					);
			}
		}

		if ($realm_allowed[18] || $realm_allowed[19]) {
			$tabs_left[] =
				array(
					'title' => __('Logs'),
					'id'	=> 'tab-logs',
					'url'   => $config['url_path'] . ($realm_allowed[18] ? 'clog.php':'clog_user.php'),
				);
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
		$count    = 0;

		foreach($elements as $p) {
			$p = trim($p);

			if ($p == '') {
				continue;
			}

			$altpos  = strpos($p, 'alt=');
			$hrefpos = strpos($p, 'href=');
			$idpos   = strpos($p, 'id=');

			if ($altpos !== false) {
				$alt = substr($p, $altpos+4);
				$parts = explode("'", $alt);
				if ($parts[0] == '') {
					$alt = $parts[1];
				} else {
					$alt = $parts[0];
				}
			} else {
				$alt = __('Title');
			}

			if ($hrefpos !== false) {
				$href = substr($p, $hrefpos+5);
				$parts = explode("'", $href);
				if ($parts[0] == '') {
					$href = $parts[1];
				} else {
					$href = $parts[0];
				}
			} else {
				$href = 'unknown';
			}

			if ($idpos !== false) {
				$id = substr($p, $idpos+3);
				$parts = explode("'", $id);
				if ($parts[0] == '') {
					$id = $parts[1];
				} else {
					$id = $parts[0];
				}
			} else {
				$id = 'unknown' . $count;
				$count++;
			}

			$tabs_left[] = array('title' => ucwords($alt), 'id' => 'tab-' . $id, 'url' => $href);
		}

		if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
			// Only show external links when online
		} else {
			$external_links = db_fetch_assoc('SELECT id, title
				FROM external_links
				WHERE style="TAB"
				AND enabled="on"
				ORDER BY sortorder');

			if (cacti_sizeof($external_links)) {
				foreach($external_links as $tab) {
					if (is_realm_allowed($tab['id']+10000)) {
						$tabs_left[] =
							array(
								'title' => $tab['title'],
								'id'    => 'tab-link' . $tab['id'],
								'url'   => $config['url_path'] . 'link.php?id=' . $tab['id']
							);
					}
				}
			}
		}

		$i = 0;
		$me_base = get_current_page();
		foreach($tabs_left as $tab) {
			$tab_base = basename($tab['url']);

			if ($tab_base == 'graph_view.php' && ($me_base == 'graph_view.php' || $me_base == 'graph.php')) {
				$tabs_left[$i]['selected'] = true;
			} elseif (isset_request_var('id') && ($tab_base == 'link.php?id=' . get_nfilter_request_var('id')) && $me_base == 'link.php') {
				$tabs_left[$i]['selected'] = true;
			} elseif ($tab_base == 'index.php' && is_console_page($me_base)) {
				$tabs_left[$i]['selected'] = true;
			} elseif ($tab_base == $me_base) {
				$tabs_left[$i]['selected'] = true;
			}

			$i++;
		}

		$i = 0;

		print "<div class='maintabs'><nav><ul role='tablist'>";

		foreach($tabs_left as $tab) {
			if (isset($tab['id'])) {
				$id = $tab['id'];
			} else {
				$id = 'anchor' . $i;
				$i++;
			}

			print "<li><a id='$id' class='lefttab" . (isset($tab['selected']) ? ' selected':'') . "' href='" . html_escape($tab['url']) . "'><span class='fa glyph_$id'></span><span class='text_$id'>" . html_escape($tab['title']) . "</span></a><a id='menu-$id' class='maintabs-submenu' href='#'><i class='fa fa-angle-down'></i></a></li>";
		}

		print "<li class='ellipsis maintabs-submenu-ellipsis'><a id='menu-ellipsis' class='submenu-ellipsis' href='#'><i class='fa fa-angle-down'></i></a></li>";

		print '</ul></nav></div>';
	}
}

function html_graph_tabs_right() {
	global $config, $tabs_right;

	$theme = get_selected_theme();

	if ($theme == 'classic') {
		if (is_view_allowed('show_tree')) {
			?><a class='righttab' id='treeview' href='<?php print html_escape($config['url_path'] . 'graph_view.php?action=tree');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_tree<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'tree') {
				print '_down';
			}?>.gif' title='<?php print __esc('Tree View');?>' alt=''></a><?php
		}?><?php

		if (is_view_allowed('show_list')) {
			?><a class='righttab' id='listview' href='<?php print html_escape($config['url_path'] . 'graph_view.php?action=list');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_list<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'list') {
				print '_down';
			}?>.gif' title='<?php print __esc('List View');?>' alt=''></a><?php
		}?><?php

		if (is_view_allowed('show_preview')) {
			?><a class='righttab' id='preview' href='<?php print html_escape($config['url_path'] . 'graph_view.php?action=preview');?>'><img src='<?php echo $config['url_path']; ?>images/tab_mode_preview<?php
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'preview') {
				print '_down';
			}?>.gif' title='<?php print __esc('Preview View');?>' alt=''></a><?php
		}?>&nbsp;<br>
		<?php
	} else {
		$tabs_right = array();

		if (is_view_allowed('show_tree')) {
			$tabs_right[] = array(
				'title' => __('Tree View'),
				'image' => 'include/themes/' . $theme . '/images/tab_tree.gif',
				'id'    => 'tree',
				'url'   => 'graph_view.php?action=tree',
			);
		}

		if (is_view_allowed('show_list')) {
			$tabs_right[] = array(
				'title' => __('List View'),
				'image' => 'include/themes/' . $theme . '/images/tab_list.gif',
				'id'    => 'list',
				'url'   => 'graph_view.php?action=list',
			);
		}

		if (is_view_allowed('show_preview')) {
			$tabs_right[] = array(
				'title' => __('Preview'),
				'image' => 'include/themes/' . $theme . '/images/tab_preview.gif',
				'id'    => 'preview',
				'url'   => 'graph_view.php?action=preview',
			);
		}

		$i = 0;
		foreach($tabs_right as $tab) {
			if ($tab['id'] == 'tree') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'tree') {
					$tabs_right[$i]['selected'] = true;
				}
			} elseif ($tab['id'] == 'list') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'list') {
					$tabs_right[$i]['selected'] = true;
				}
			} elseif ($tab['id'] == 'preview') {
				if (isset_request_var('action') && get_nfilter_request_var('action') == 'preview') {
					$tabs_right[$i]['selected'] = true;
				}
			} elseif (strstr(get_current_page(false), $tab['url'])) {
				$tabs_right[$i]['selected'] = true;
			}

			$i++;
		}

		print "<div class='tabs' style='float:right;'><nav><ul role='tablist'>";
		foreach($tabs_right as $tab) {
			switch($tab['id']) {
			case 'tree':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li role='tab'><a id='treeview' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li role='tab'><a title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}
				break;
			case 'list':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li role='tab'><a id='listview' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li role='tab'><a title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}

				break;
			case 'preview':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li role='tab'><a id='preview' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'><img src='" . $config['url_path'] . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li role='tab'><a title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? 'selected':'') . "' href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}

				break;
			}
		}
		print '</ul></nav></div>';
	}
}

function html_host_filter($host_id = '-1', $call_back = 'applyFilter', $sql_where = '', $noany = false, $nonone = false) {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	if ($host_id == '-1' && isset_request_var('host_id')) {
		$host_id = get_filter_request_var('host_id');
	}

	if ($theme == 'classic' || !read_config_option('autocomplete_enabled')) {
		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<select id='host_id' name='host_id' onChange='<?php print $call_back;?>'>
				<?php if (!$noany) {?><option value='-1'<?php if ($host_id == '-1') {?> selected<?php }?>><?php print __('Any');?></option><?php }?>
				<?php if (!$nonone) {?><option value='0'<?php if ($host_id == '0') {?> selected<?php }?>><?php print __('None');?></option><?php }?>
				<?php

				$devices = get_allowed_devices($sql_where);

				if (cacti_sizeof($devices)) {
					foreach ($devices as $device) {
						print "<option value='" . $device['id'] . "'"; if ($host_id == $device['id']) { print ' selected'; } print '>' . title_trim(html_escape(strip_domain($device['description'])), 40) . '</option>';
					}
				}
				?>
			</select>
		</td>
		<?php
	} else {
		if ($host_id > 0) {
			$hostname = db_fetch_cell_prepared('SELECT description
				FROM host WHERE id = ?',
				array($host_id));
		} elseif ($host_id == 0) {
			$hostname = __('None');
		} else {
			$hostname = __('Any');
		}

		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<span id='host_wrapper' style='width:200px;' class='ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-corner-all ui-button ui-widget'>
				<span id='host_click' class='ui-selectmenu-icon ui-icon ui-icon-triangle-1-s'></span>
				<span class='ui-select-text'>
					<input type='text' size='28' id='host' value='<?php print html_escape($hostname);?>'>
				</span>
			</span>
			<input type='hidden' id='host_id' name='host_id' value='<?php print $host_id;?>'>
			<input type='hidden' id='call_back' value='<?php print $call_back;?>'>
		</td>
	<?php
	}
}

function html_site_filter($site_id = '-1', $call_back = 'applyFilter', $sql_where = '', $noany = false, $nonone = false) {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	if ($site_id == '-1' && isset_request_var('site_id')) {
		$site_id = get_filter_request_var('site_id');
	}

	?>
	<td>
		<?php print __('Site');?>
	</td>
	<td>
		<select id='site_id' onChange='<?php print $call_back;?>'>
			<?php if (!$noany) {?><option value='-1'<?php if ($site_id == '-1') {?> selected<?php }?>><?php print __('Any');?></option><?php }?>
			<?php if (!$nonone) {?><option value='0'<?php if ($site_id == '0') {?> selected<?php }?>><?php print __('None');?></option><?php }?>
			<?php

			$sites = get_allowed_sites($sql_where);

			if (cacti_sizeof($sites)) {
				foreach ($sites as $site) {
					print "<option value='" . $site['id'] . "'"; if ($site_id == $site['id']) { print ' selected'; } print '>' . html_escape($site['name']) . '</option>';
				}
			}
			?>
		</select>
	</td>
	<?php
}

function html_spikekill_actions() {
	switch(get_nfilter_request_var('action')) {
		case 'spikemenu':
			html_spikekill_menu(get_filter_request_var('local_graph_id'));

			break;
		case 'spikesave':
			switch(get_nfilter_request_var('setting')) {
				case 'ravgnan':
					$id = get_nfilter_request_var('id');
					switch($id) {
						case 'avg':
						case 'last':
						case 'nan':
							set_user_setting('spikekill_avgnan', $id);
							break;
					}

					break;
				case 'rstddev':
					set_user_setting('spikekill_deviations', get_filter_request_var('id'));
					break;
				case 'rvarout':
					set_user_setting('spikekill_outliers', get_filter_request_var('id'));
					break;
				case 'rvarpct':
					set_user_setting('spikekill_percent', get_filter_request_var('id'));
					break;
				case 'rkills':
					set_user_setting('spikekill_number', get_filter_request_var('id'));
					break;
			}

			break;
	}
}

function html_spikekill_setting($name) {
	return read_user_setting($name, read_config_option($name), true);
}

function html_spikekill_menu_item($text, $icon = '', $class = '', $id = '', $data_graph = '', $subitem = '') {
	$output = '<li ';

	if (!empty($id)) {
		$output .= "id='$id' ";
	}

	if (!empty($data_graph)) {
		$output .= "data-graph='$data_graph' ";
	}

	$output .= 'class=\'' . (empty($class)?'': " $class") . '\'>';
	$output .= '<span class=\'spikeKillMenuItem\'>';
	if (!empty($icon)) {
		$output .= "<i class='$icon'></i>";
	}

	$output .= "$text</span>";

	if (!empty($subitem)) {
		$output .= "<ul>$subitem</ul>";
	}

	$output .= '</li>';
	return $output;
}

function html_spikekill_menu($local_graph_id) {
	$ravgnan1 = html_spikekill_menu_item(__('Average'), html_spikekill_setting('spikekill_avgnan') == 'avg' ? 'fa fa-check':'fa', 'skmethod', 'method_avg');
	$ravgnan2 = html_spikekill_menu_item(__('NaN\'s'), html_spikekill_setting('spikekill_avgnan') == 'nan' ? 'fa fa-check':'fa', 'skmethod', 'method_nan');
	$ravgnan3 = html_spikekill_menu_item(__('Last Known Good'), html_spikekill_setting('spikekill_avgnan') == 'last' ? 'fa fa-check':'fa', 'skmethod', 'method_last');

	$ravgnan = html_spikekill_menu_item(__('Replacement Method'), '', '', '', '', $ravgnan1 . $ravgnan2 . $ravgnan3);

	$rstddev = '';
	for($i = 1; $i <= 10; $i++) {
		$rstddev .= html_spikekill_menu_item(__('%s Standard Deviations', $i), html_spikekill_setting('spikekill_deviations') == $i ? 'fa fa-check':'fa', 'skstddev', 'stddev_' . $i);
	}
	$rstddev  = html_spikekill_menu_item(__('Standard Deviations'), '', '', '', '', $rstddev);

	$rvarpct = '';
	for($i = 1; $i <= 10; $i++) {
		$rvarpct .= html_spikekill_menu_item(round($i * 100,0) . ' %', html_spikekill_setting('spikekill_percent') == ($i * 100) ? 'fa fa-check':'fa', 'skvarpct', 'varpct_' . ($i * 100));
	}
	$rvarpct = html_spikekill_menu_item(__('Variance Percentage'), '', '', '', '', $rvarpct);

	$rvarout  = '';
	for($i = 3; $i <= 10; $i++) {
		$rvarout .= html_spikekill_menu_item(__('%d Outliers', $i), html_spikekill_setting('spikekill_outliers') == $i ? 'fa fa-check':'fa', 'skvarout', 'varout_' . $i);
	}
	$rvarout  = html_spikekill_menu_item(__('Variance Outliers'), '', '', '', '', $rvarout);

	$rkills  = '';
	for($i = 1; $i <= 10; $i++) {
		$rkills .= html_spikekill_menu_item(__('%d Spikes', $i),html_spikekill_setting('spikekill_number') == $i ? 'fa fa-check':'fa', 'skkills', 'kills_' . $i);
	}
	$rkills  = html_spikekill_menu_item(__('Kills Per RRA'), '', '', '', '', $rkills);

	?>
	<div class='spikekillParent' style='display:none;z-index:20;position:absolute;text-align:left;white-space:nowrap;padding-right:2px;'>
	<ul class='spikekillMenu' style='font-size:1em;'>
	<?php
	print html_spikekill_menu_item(__('Remove StdDev'), 'deviceUp fa fa-life-ring', 'rstddev', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Remove Variance'), 'deviceRecovering fa fa-life-ring', 'rvariance', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Gap Fill Range'), 'deviceUnknown fa fa-life-ring', 'routlier', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Float Range'), 'deviceDown fa fa-life-ring', 'rrangefill', '',  $local_graph_id);

	print html_spikekill_menu_item(__('Dry Run StdDev'), 'deviceUp fa fa-check', 'dstddev', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Variance'), 'deviceRecovering fa fa-check', 'dvariance', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Gap Fill Range'), 'deviceUnknown fa fa-check', 'doutlier', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Float Range'), 'deviceDown fa fa-check', 'drangefill', '',  $local_graph_id);

	print html_spikekill_menu_item(__('Settings'), 'fa fa-cog', '', '', '', $ravgnan . $rstddev . $rvarpct . $rvarout . $rkills);
}

function html_spikekill_js() {
	?>
	<script type='text/javascript'>
	spikeKillOpen = false;
	$(function() {
		$(document).click(function() {
			if (spikeKillOpen) {
				$(this).find('.spikekillMenu').menu('destroy').parent().remove();
				spikeKillOpen = false;
			}
		});

		$('span.spikekill').children().contextmenu(function() {
			return false;
		});

		$('span.spikekill').unbind().click(function() {
			if (spikeKillOpen == false) {
				local_graph_id = $(this).attr('data-graph');

				$.get('?action=spikemenu&local_graph_id='+local_graph_id)
					.done(function(data) {
						$('#sk'+local_graph_id).after(data);

						menuAnchor = $('#sk'+local_graph_id).offset().left;
						pageWidth  = $(document).width();

						if (pageWidth - menuAnchor < 180) {
							$('.spikekillMenu').css({ position: 'absolute', top: 0, left: -180 });
						}

						$('.spikekillMenu').menu({
							select: function(event, ui) {
								$(this).menu('focus', event, ui.item);
							},
							delay: 1000
						});

						$('.spikekillParent').show();

						spikeKillActions();

						spikeKillOpen = true;
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});

			} else {
				spikeKillOpen = false;
				$(this).find('.spikekillMenu').menu('destroy').parent().remove();
			}
		});
	});

	function spikeKillActions() {
		$('.rstddev').unbind().click(function() {
			removeSpikesStdDev($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.dstddev').unbind().click(function() {
			dryRunStdDev($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.rvariance').unbind().click(function() {
			removeSpikesVariance($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.dvariance').unbind().click(function() {
			dryRunVariance($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.routlier').unbind().click(function() {
			removeSpikesInRange($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.doutlier').unbind().click(function() {
			dryRunSpikesInRange($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.rrangefill').unbind().click(function() {
			removeRangeFill($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.drangefill').unbind().click(function() {
			dryRunRangeFill($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.skmethod').unbind().click(function() {
			$('.skmethod').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=ravgnan&id='+$(this).attr('id').replace('method_','');
			$.get(strURL)
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('.skkills').unbind().click(function() {
			$('.skkills').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rkills&id='+$(this).attr('id').replace('kills_','');
			$.get(strURL)
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('.skstddev').unbind().click(function() {
			$('.skstddev').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rstddev&id='+$(this).attr('id').replace('stddev_','');
			$.get(strURL)
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('.skvarpct').unbind().click(function() {
			$('.skvarpct').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rvarpct&id='+$(this).attr('id').replace('varpct_','');
			$.get(strURL)
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('.skvarout').unbind().click(function() {
			$('.skvarout').find('i').removeClass('fa fa-check');
			$(this).find('i:first').addClass('fa fa-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rvarout&id='+$(this).attr('id').replace('varout_','');
			$.get(strURL)
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});
	}
	</script>
	<?php
}

/* html_common_header - prints a common set of header, css and javascript links
   @arg title - the title of the page to place in the browser
   @arg selectedTheme - optionally sets a specific theme over the current one
*/
function html_common_header($title, $selectedTheme = '') {
	global $config, $path2calendar, $path2timepicker, $path2colorpicker;

	if ($selectedTheme == '') {
		$selectedTheme = get_selected_theme();
	}

	?>
	<meta http-equiv='X-UA-Compatible' content='IE=Edge,chrome=1'>
	<meta content='width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0' name='viewport'>
	<meta name='apple-mobile-web-app-capable' content='yes'>
	<meta name='mobile-web-app-capable' content='yes'>
	<meta name='robots' content='noindex,nofollow'>
	<title><?php echo $title; ?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<script type='text/javascript'>
		var theme='<?php print $selectedTheme;?>';
		var searchFilter='<?php print __esc('Enter a search term');?>';
		var searchRFilter='<?php print __esc('Enter a regular expression');?>';
		var noFileSelected='<?php print __esc('No file selected');?>';
		var timeGraphView='<?php print __esc('Time Graph View');?>';
		var filterSettingsSaved='<?php print __esc('Filter Settings Saved');?>';
		var spikeKillResuls='<?php print __esc('SpikeKill Results');?>';
		var utilityView='<?php print __esc('Utility View');?>';
		var realtimeClickOn='<?php print __esc('Click to view just this Graph in Realtime');?>';
		var realtimeClickOff='<?php print __esc('Click again to take this Graph out of Realtime');?>';
		var treeView='<?php print __esc('Tree View');?>';
		var listView='<?php print __esc('List View');?>';
		var previewView='<?php print __esc('Preview View');?>';
		var cactiHome='<?php print __esc('Cacti Home');?>';
		var cactiProjectPage='<?php print __esc('Cacti Project Page');?>';
		var cactiCommunityForum='<?php print __esc('User Community');?>';
		var cactiDocumentation='<?php print __esc('Documentation');?>';
		var reportABug='<?php print __esc('Report a bug');?>';
		var aboutCacti='<?php print __esc('About Cacti');?>';
		var spikeKillResults='<?php print __esc('SpikeKill Results');?>';
		var showHideFilter='<?php print __esc('Click to Show/Hide Filter');?>';
		var clearFilterTitle='<?php print __esc('Clear Current Filter');?>';
		var clipboard='<?php print __esc('Clipboard');?>';
		var clipboardID='<?php print __esc('Clipboard ID');?>';
		var clipboardNotAvailable='<?php print __esc('Copy operation is unavailable at this time');?>';
		var clipboardCopyFailed='<?php print __esc('Failed to find data to copy!');?>';
		var clipboardUpdated='<?php print __esc('Clipboard has been updated');?>';
		var clipboardNotUpdated='<?php print __esc('Sorry, your clipboard could not be updated at this time');?>';
		var defaultSNMPSecurityLevel='<?php print read_config_option('snmp_security_level');?>';
		var defaultSNMPAuthProtocol='<?php print read_config_option('snmp_auth_protocol');?>';
		var defaultSNMPPrivProtocol='<?php print read_config_option('snmp_priv_protocol');?>';
		var passwordPass='<?php print __esc('Passphrase length meets 8 character minimum');?>';
		var passwordTooShort='<?php print __esc('Passphrase too short');?>';
		var passwordMatchTooShort='<?php print __esc('Passphrase matches but too short');?>';
		var passwordNotMatchTooShort='<?php print __esc('Passphrase too short and not matching');?>';
		var passwordMatch='<?php print __esc('Passphrases match');?>';
		var passwordNotMatch='<?php print __esc('Passphrases do not match');?>';
		var errorOnPage='<?php print __esc('Sorry, we could not process your last action.');?>';
		var errorNumberPrefix='<?php print __esc('Error:');?>';
		var errorReasonPrefix='<?php print __esc('Reason:');?>';
		var errorReasonTitle='<?php print __esc('Action failed');?>';
		var testSuccessful='<?php print __esc('Connection Successful');?>';
		var testFailed='<?php print __esc('Connection Failed');?>';
		var errorReasonUnexpected='<?php print __esc('The response to the last action was unexpected.');?>';
		var mixedReasonTitle='<?php print __esc('Some Actions failed');?>';
		var mixedOnPage='<?php print __esc('Note, we could not process all your actions.  Details are below.');?>';
		var sessionMessageTitle='<?php print __esc('Operation successful');?>';
		var sessionMessageSave='<?php print __esc('The Operation was successful.  Details are below.');?>';
		var sessionMessageOk='<?php print __esc('Ok');?>';
		var sessionMessagePause='<?php print __esc('Pause');?>';
		var sessionMessageContinue='<?php print __esc('Continue');?>';
		var sessionMessageCancel='<?php print __esc('Cancel');?>';
		var zoom_i18n_zoom_in='<?php print __esc('Zoom In');?>';
		var zoom_i18n_zoom_out='<?php print __esc('Zoom Out');?>';
		var zoom_i18n_zoom_out_factor='<?php print __esc('Zoom Out Factor');?>';
		var zoom_i18n_timestamps='<?php print __esc('Timestamps');?>';
		var zoom_i18n_zoom_2='<?php print __esc('2x');?>';
		var zoom_i18n_zoom_4='<?php print __esc('4x');?>';
		var zoom_i18n_zoom_8='<?php print __esc('8x');?>';
		var zoom_i18n_zoom_16='<?php print __esc('16x');?>';
		var zoom_i18n_zoom_32='<?php print __esc('32x');?>';
		var zoom_i18n_zoom_out_positioning='<?php print __esc('Zoom Out Positioning');?>';
		var zoom_i18n_mode='<?php print __esc('Zoom Mode');?>';
		var zoom_i18n_graph='<?php print __esc('Graph');?>';
		var zoom_i18n_quick='<?php print __esc('Quick');?>';
		var zoom_i18n_advanced='<?php print __esc('Advanced');?>';
		var zoom_i18n_newTab='<?php print __esc('Open in new tab');?>';
		var zoom_i18n_save_graph='<?php print __esc('Save graph');?>';
		var zoom_i18n_copy_graph='<?php print __esc('Copy graph');?>';
		var zoom_i18n_copy_graph_link='<?php print __esc('Copy graph link');?>';
		var zoom_i18n_on='<?php print __esc('Always On');?>';
		var zoom_i18n_auto='<?php print __esc('Auto');?>';
		var zoom_i18n_off='<?php print __esc('Always Off');?>';
		var zoom_i18n_begin='<?php print __esc('Begin with');?>';
		var zoom_i18n_center='<?php print __esc('Center');?>';
		var zoom_i18n_end='<?php print __esc('End with');?>';
		var zoom_i18n_disabled='<?php print __esc('Disabled');?>';
		var zoom_i18n_close='<?php print __esc('Close');?>';
		var zoom_i18n_settings='<?php print __esc('Settings');?>';
		var zoom_i18n_3rd_button='<?php print __esc('3rd Mouse Button');?>';
	</script>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print $selectedTheme;?>/images/favicon.ico' rel='shortcut icon'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print $selectedTheme;?>/images/cacti_logo.gif' rel='icon' sizes='96x96'>
	<?php
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.zoom.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery-ui.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/default/style.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.multiselect.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.multiselect.filter.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.timepicker.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.colorpicker.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/c3.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/pace.css');
	print get_md5_include_css('include/fa/css/fontawesome.css');
	print get_md5_include_css('include/vendor/flag-icon-css/css/flag-icon.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/main.css');
	print get_md5_include_js('include/js/screenfull.js');
	print get_md5_include_js('include/js/jquery.js');
	print get_md5_include_js('include/js/jquery-ui.js');
	print get_md5_include_js('include/js/jquery.ui.touch.punch.js');
	print get_md5_include_js('include/js/jquery.cookie.js');
	print get_md5_include_js('include/js/js.storage.js');
	print get_md5_include_js('include/js/jstree.js');
	print get_md5_include_js('include/js/jquery.hotkeys.js');
	print get_md5_include_js('include/js/jquery.tablednd.js');
	print get_md5_include_js('include/js/jquery.zoom.js');
	print get_md5_include_js('include/js/jquery.multiselect.js');
	print get_md5_include_js('include/js/jquery.multiselect.filter.js');
	print get_md5_include_js('include/js/jquery.timepicker.js');
	print get_md5_include_js('include/js/jquery.colorpicker.js');
	print get_md5_include_js('include/js/jquery.tablesorter.js');
	print get_md5_include_js('include/js/jquery.tablesorter.widgets.js');
	print get_md5_include_js('include/js/jquery.tablesorter.pager.js');
	print get_md5_include_js('include/js/jquery.metadata.js');
	print get_md5_include_js('include/js/jquery.sparkline.js');
	print get_md5_include_js('include/js/Chart.js');
	print get_md5_include_js('include/js/dygraph-combined.js');
	print get_md5_include_js('include/js/d3.js');
	print get_md5_include_js('include/js/c3.js');
	print get_md5_include_js('include/layout.js');
	print get_md5_include_js('include/js/pace.js');
	print get_md5_include_js('include/realtime.js');
	print get_md5_include_js('include/themes/' . $selectedTheme .'/main.js');

	if (isset($path2calendar) && file_exists($path2calendar)) {
		print get_md5_include_js($path2calendar);
	}

	if (isset($path2timepicker) && file_exists($path2timepicker)) {
		print get_md5_include_js($path2timepicker);
	}

	if (isset($path2colorpicker) && file_exists($path2colorpicker)) {
		print get_md5_include_js($path2colorpicker);
	}

	if (file_exists('include/themes/custom.css')) {
		print get_md5_include_css('include/themes/custom.css');
	}
	api_plugin_hook('page_head');
}
