<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include_once('./include/auth.php');
include_once(CACTI_PATH_LIBRARY . '/functions.php');

$rra_path = CACTI_PATH_RRA . '/';

top_header();

set_default_action();

if (read_config_option('rrdcheck_enable') != 'on') {
	html_start_box(__('RRD check'), '100%', '', '3', 'center', '');
	print __('RRD check is disabled, please enable in Configuration -> Settings -> Data');
	html_end_box();
}

switch(get_request_var('action')) {
	case 'purge':
		rrdcheck_purge();

	default:
		rrdcheck_display_problems();
}

bottom_footer();

function rrdcheck_purge() {
	db_execute('TRUNCATE TABLE rrdcheck');
}

/*
 * Display all rrdcheck entries
 */
function rrdcheck_display_problems() {
	global $config, $item_rows;

	/* suppress warnings */
	error_reporting(0);

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'test_date',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'age' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '0'
			)
	);

	validate_store_request_vars($filters, 'sess_rrdc');

	/* ================= input validation and session storage ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('RRDfile Checker'), '100%', '', '3', 'center', '');
	filter();
	html_end_box();

	$sql_where = 'WHERE ';
	/* form the 'where' clause for our main sql query */

	$secsback = get_request_var('age');

	if (get_request_var('age') == 0) {
		$sql_where .= " test_date>='" . date('Y-m-d H:i:s', time() - (7200)) . "'";
	} else {
		$sql_where .= " test_date<='" . date('Y-m-d H:i:s', (time() - $secsback)) . "'";
	}

	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			message LIKE '		  . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(local_data_id)
		FROM rrdcheck
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$problems = db_fetch_assoc("SELECT h.description, dtd.name_cache, rc.local_data_id, rc.test_date, rc.message
		FROM rrdcheck AS rc
		LEFT JOIN data_local AS dl
		ON rc.local_data_id = dl.id
		LEFT JOIN data_template_data AS dtd
		ON rc.local_data_id = dtd.local_data_id
		LEFT JOIN host AS h
		ON dl.host_id = h.id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar(CACTI_PATH_URL . 'rrdcheck.php?filter'. get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('RRDcheck Problems'), 'page', 'main');

	form_start('rrdcheck.php');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description' => array(
			'display' => __('Host Description'),
			'sort'    => 'ASC'
		),
		'name_cache' => array(
			'display' => __('Data Source'),
			'sort'    => 'ASC'
		),
		'local_data_id' => array(
			'display' => __('Local Data ID'),
			'align'   => 'center',
			'sort'    => 'ASC'
		),
		'message' => array(
			'display' => __('Message'),
			'sort'    => 'ASC'
		),
		'test_date' => array(
			'display' => __('Date'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($problems)) {
		foreach ($problems as $problem) {
			form_alternate_row('line' . $problem['local_data_id'], true);

			if ($problem['description'] == '') {
				$problem['description'] = __('Deleted');
			}

			if ($problem['name_cache'] == '') {
				$problem['name_cache'] = __('Deleted');
			}

			form_selectable_cell(filter_value($problem['description'], get_request_var('filter')), $problem['local_data_id']);
			form_selectable_cell(filter_value($problem['name_cache'], get_request_var('filter')), $problem['local_data_id']);
			form_selectable_cell(filter_value($problem['local_data_id'], get_request_var('filter')), $problem['local_data_id'], '', 'center');
			form_selectable_cell(filter_value($problem['message'], get_request_var('filter')), $problem['local_data_id']);
			form_selectable_cell($problem['test_date'], $file['local_data_id'], '', 'right');

			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No RRDcheck Problems Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($file_list)) {
		print $nav;
	}

	form_end();

	/* restore original error handler */
	restore_error_handler();
}

function filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='form_rrdcheck' method='get' action='rrdcheck.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Age');?>
					</td>
					<td>
						<select id='age' onChange='refreshForm()'>
							<option value='0'   <?php print(get_request_var('age') == '0'   ? ' selected':'');?>>&lt; <?php print __('%d hours', 2);?></option>
							<option value='14400'   <?php print(get_request_var('age') == '14400'   ? ' selected':'');?>>&gt; <?php print __('%d hours', 4);?></option>
							<option value='43200'  <?php print(get_request_var('age') == '43200'  ? ' selected':'');?>>&gt;  <?php print __('%d hours',12);?></option>
							<option value='86400'  <?php print(get_request_var('age') == '86400'  ? ' selected':'');?>>&gt;  <?php print __('%d day', 1);?></option>
							<option value='259200'  <?php print(get_request_var('age') == '259200'  ? ' selected':'');?>>&gt; <?php print __('%d days', 3);?></option>
							<option value='604800'  <?php print(get_request_var('age') == '604800'  ? ' selected':'');?>>&gt; <?php print __('%d days', 5);?></option>
						</select>
					</td>
					<td>
						<?php print __('Messages');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"';

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . $value . "</option>\n";
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset', 'Clear');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __x('filter: purge', 'Purge');?>'>
						</span>
					</td>
					<td id='text'></td>
				</tr>
			</table>
			</form>
			<script type="text/javascript">
			function refreshForm() {
				strURL = 'rrdcheck.php?'+
					'&filter='+$('#filter').val()+
					'&age='+$('#age').val()+
					'&rows='+$('#rows').val();
				loadUrl({url:strURL});
			}

			$(function() {
				$('#form_rrdcheck').submit(function() {
					refreshForm();
					return false;
				});

				$('#rows').change(function() {
					refreshForm();
				});

				$('#clear').click(function() {
					strURL = 'rrdcheck.php?&clear=1';
					loadUrl({url:strURL});
				});

				$('#purge').click(function() {
					strURL = 'rrdcheck.php?action=purge';
					loadUrl({url:strURL});
				});
			});
			</script>
		</td>
	</tr>
	<?php
}
