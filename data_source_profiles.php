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

include('./include/auth.php');
include_once('./lib/utility.php');

$profile_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_remove_confirm':
		profile_item_remove_confirm();

		break;
	case 'item_remove':
		profile_item_remove();

		break;
	case 'ajax_span':
		get_filter_request_var('profile_id');
		get_filter_request_var('span');
		get_filter_request_var('rows');

		$sampling_interval = db_fetch_cell_prepared('SELECT step FROM data_source_profiles WHERE id = ?', array(get_request_var('profile_id')));

		if (get_request_var('span') == 1) {
			print get_span(get_request_var('rows') * $sampling_interval);
		}else{
			print get_span(get_request_var('rows') * get_request_var('span'));
		}

		break;
	case 'ajax_size':
		get_filter_request_var('id');
		get_filter_request_var('cfs');
		print get_size(get_request_var('id'), get_nfilter_request_var('type'), get_request_var('cfs'));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();
		break;
	case 'edit':
		top_header();

		profile_edit();

		bottom_footer();
		break;
	default:
		top_header();

		profile();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {

	// make sure ids are numeric
	if (isset_request_var('id') && ! is_numeric(get_filter_request_var('id'))) {
		set_request_var('id', 0);
	}

	if (isset_request_var('profile_id') && ! is_numeric(get_filter_request_var('profile_id'))) {
		set_request_var('profile_id', 0);
	}

	if (isset_request_var('save_component_profile')) {
		$save['id']             = form_input_validate(get_nfilter_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']           = get_hash_data_source_profile(get_nfilter_request_var('id'));

		$save['name']           = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);

		if (isset_request_var('step')) {
			$save['step']           = form_input_validate(get_nfilter_request_var('step'), 'step', '', false, 3);
			$save['heartbeat']      = form_input_validate(get_nfilter_request_var('heartbeat'), 'heartbeat', '', false, 3);
			$save['x_files_factor'] = form_input_validate(get_nfilter_request_var('x_files_factor'), 'x_files_factor', '', false, 3);
		}

		if (isset_request_var('default')) {
			$save['default']        = (isset_request_var('default') ? 'on':'');
			db_execute('UPDATE data_source_profiles SET `default`=""');
		}

		if (!is_error_message()) {
			$profile_id = sql_save($save, 'data_source_profiles');

			if ($profile_id) {
				if (isset_request_var('step')) {
					// Validate consolidation functions
					$cfs = get_nfilter_request_var('consolidation_function_id');
					if (sizeof($cfs)) {
						foreach($cfs as $cf) {
							input_validate_input_number($cf);
						}
					}

					db_execute('DELETE FROM data_source_profiles_cf WHERE data_source_profile_id = ' . $profile_id . ' AND consolidation_function_id NOT IN (' . implode(',', get_nfilter_request_var('consolidation_function_id')) . ')');

					// Validate consolidation functions
					$cfs = get_nfilter_request_var('consolidation_function_id');
					if (sizeof($cfs)) {
						foreach($cfs as $cf) {
							db_execute('REPLACE INTO data_source_profiles_cf (data_source_profile_id, consolidation_function_id) VALUES (' . $profile_id . ',' . $cf . ')');
						}
					}
				}

				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: data_source_profiles.php?header=false&action=edit&id=' . (empty($profile_id) ? get_nfilter_request_var('id') : $profile_id));
	}elseif (isset_request_var('save_component_rra')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('profile_id');
		/* ==================================================== */

		$sampling_interval = db_fetch_cell_prepared('SELECT step FROM data_source_profiles WHERE id = ?', array(get_request_var('profile_id')));
		$save['id']                      = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['name']                    = form_input_validate(get_nfilter_request_var('name'), 'name', '', true, 3);
		$save['data_source_profile_id']  = form_input_validate(get_nfilter_request_var('profile_id'), 'profile_id', '^[0-9]+$', false, 3);
		$save['steps']                   = form_input_validate(get_nfilter_request_var('steps'), 'steps', '^[0-9]+$', false, 3);

		if ($save['steps'] != '1') {
			$save['steps'] /= $sampling_interval;
		}
		$save['rows']                    = form_input_validate(get_nfilter_request_var('rows'), 'rows', '^[0-9]+$', false, 3);

		if (!is_error_message()) {
			$profile_rra_id = sql_save($save, 'data_source_profiles_rra');

			if ($profile_rra_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: data_source_profiles.php?header=false&action=item_edit&profile_id=' . get_nfilter_request_var('profile_id') . '&id=' . (empty($profile_rra_id) ? get_nfilter_request_var('id') : $profile_rra_id));
		}else{
			header('Location: data_source_profiles.php?header=false&action=edit&id=' . get_nfilter_request_var('profile_id'));
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $profile_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */
	
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM data_source_profiles WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM data_source_profiles_rra WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
				db_execute('DELETE FROM data_source_profiles_cf WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
			}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_data_source_profile($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			}
		}

		header('Location: data_source_profiles.php?header=false');
		exit;
	}

	/* setup some variables */
	$profile_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$profile_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_source_profiles WHERE id = ?', array($matches[1]))) . '</li>';
			$profile_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_source_profiles.php');

	html_start_box($profile_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($profile_array) && sizeof($profile_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to delete the folling Data Source Pofile(s).</p>
					<p><ul>$profile_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete CDEF(s)'>";
		}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to duplicate the following Data Source Profile(s). You can
					optionally change the title format for the new Data Source Profile(s).</p>
					<p><ul>$profile_list</ul></p>
					<p>Title Format:<br>"; form_text_box('title_format', '<profile_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Duplicate CDEF(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one CDEF.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($profile_array) ? serialize($profile_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function profile_item_remove_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('profile_id');
	/* ==================================================== */

	form_start('data_source_profiles.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$profile = db_fetch_row('SELECT * FROM data_source_profiles_rra WHERE id=' . get_request_var('id'));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p>Click 'Continue' to delete the following Data Source Profile RRA.</p>
			<p>Profile Name: '<?php print $profile['name'];?>'<br>
		</td>
	</tr>
	<tr>
		<td align='right'>
			<input id='cancel' type='button' value='Cancel' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input id='continue' type='button' value='Continue' name='continue' title='Remove Data Source Profile RRA'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#cdialog').dialog();
	});
	$('#continue').click(function(data) {
		$.post('data_source_profiles.php?action=item_remove', { 
			__csrf_magic: csrfMagicToken, 
			id: <?php print get_request_var('id');?> 
		}, function(data) {
			$('#cdialog').dialog('close');
			loadPageNoHeader('data_source_profiles.php?action=edit&header=false&id=<?php print $profile['data_source_profile_id'];?>');
		});
	});
	</script>
	<?php
}
		
function profile_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute('DELETE FROM data_source_profiles_rra WHERE id=' . get_request_var('id'));
}


function item_edit() {
	global $fields_profile_rra_edit, $aggregation_levels;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('profile_id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$rra = db_fetch_row_prepared('SELECT * FROM data_source_profiles_rra WHERE id = ?', array(get_request_var('id')));
		$sampling_interval = db_fetch_cell_prepared('SELECT step FROM data_source_profiles WHERE id = ?', array(get_request_var('profile_id')));

		if ($rra['steps'] == '1') {
			$fields_profile_rra_edit['steps']['array'] = array('1' => 'Each Insert is New Row');
		}else{
			unset($aggregation_levels[1]);
			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}
		$fields_profile_rra_edit['steps']['value'] = $rra['steps'] * $sampling_interval;
	}else{
		$oneguy = db_fetch_cell_prepared('SELECT id FROM data_source_profiles_rra WHERE data_source_profile_id = ? AND steps=1', array(get_request_var('profile_id')));

		if (empty($oneguy)) {
			$fields_profile_rra_edit['steps']['array'] = array('1' => 'Each Insert is New Row');
		}else{
			unset($aggregation_levels[1]);
			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}
	}

	form_start('data_source_profiles.php', 'form_rra');

	html_start_box('RRA [edit: ' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_source_profiles_rra WHERE id = ?', array(get_request_var('cdef_id')))) . ']', '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_profile_rra_edit, (isset($rra) ? $rra : array()))
		)
	);

	html_end_box();

	form_hidden_box('profile_id', get_request_var('profile_id'), '');

	html_end_box();

	form_save_button('data_source_profiles.php?action=edit&id=' . get_request_var('profile_id'));

	?>
	<script type='text/javascript'>

	var profile_id=<?php print get_request_var('profile_id');?>;
	var rows_to = false;

	$(function() {
		get_span();
		get_size();
		
		$('#steps').change(function() {
			get_span();
			get_size();
		});

        $('#rows').keyup(function() {
            if (rows_to) { clearTimeout(rows_to); }
            rows_to = setTimeout(function () { get_span(); get_size() }, 250);
        });
	});

	function get_size() {
		$.get('data_source_profiles.php?action=ajax_size&type=rra&id='+profile_id+'&rows='+$('#rows').val(), function(data) {
			$('#size').html(data);
		});
	}

	function get_span() {
		$.get('data_source_profiles.php?action=ajax_span&profile_id='+profile_id+'&span='+$('#steps').val()+'&rows='+$('#rows').val(), function(data) {
			$('#timespan').html(data);
		});
	}
	</script>
	<?php
}

/* ---------------------
    CDEF Functions
   --------------------- */

function profile_edit() {
	global $fields_profile_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$profile = db_fetch_row_prepared('SELECT * FROM data_source_profiles WHERE id = ?', array(get_request_var('id')));
		$readonly     = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_template_data AS dtd
			WHERE data_source_profile_id = ? AND local_data_id > 0', array(get_request_var('id')));

		$header_label = '[edit: ' . htmlspecialchars($profile['name']) . ']' . ($readonly ? ' (Read Only)':'');
	}else{
		$header_label = '[new]';
		$readonly     = false;
	}

	form_start('data_source_profiles.php', 'profile');

	html_start_box("Data Source Profile $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_profile_edit, (isset($profile) ? $profile : array()))
		)
	);

	html_end_box();

	if (!isempty_request_var('id')) {
		if (!$readonly) {
			html_start_box('Data Source Profile RRAs (press save to update timespans)', '100%', '', '3', 'center', 'data_source_profiles.php?action=item_edit&profile_id=' . $profile['id']);
		}else{
			html_start_box('Data Source Profile RRAs (Read Only)', '100%', '', '3', 'center', '');
		}

		$display_text = array(
			array('display' => 'Name',               'align' => 'left'), 
			array('display' => 'Effective Timespan', 'align' => 'left'),
			array('display' => 'Steps',              'align' => 'left'),
			array('display' => 'Rows',               'align' => 'left'),
		); 

		html_header($display_text, 2);

		$profile_rras = db_fetch_assoc_prepared('SELECT * FROM data_source_profiles_rra WHERE data_source_profile_id = ? ORDER BY steps', array(get_request_var('id')));

		$i = 0;
		if (sizeof($profile_rras)) {
			foreach ($profile_rras as $rra) {
				form_alternate_row('line' . $rra['id']);$i++;?>
				<td>
					<?php print (!$readonly ? "<a class='linkEditMain' href='" . htmlspecialchars('data_source_profiles.php?action=item_edit&id=' . $rra['id'] . '&profile_id=' . $rra['data_source_profile_id']) . "'>":"") . htmlspecialchars($rra['name']) . (!$readonly ? "</a>":"");?>
				</td>
				<td style='text-align:left'>
					<em><?php print get_span($profile['step'] * $rra['steps'] * $rra['rows']);?></em>
				</td>
				<td style='text-align:left'>
					<em><?php print $rra['steps'];?></em>
				</td>
				<td style='text-align:left'>
					<em><?php print $rra['rows'];?></em>
				</td>
				<td class='right'>
					<?php print (!$readonly ? "<a id='" . $profile['id'] . '_' . $rra['id'] . "' class='delete deleteMarker fa fa-remove' title='Delete' href='#'></a>":"");?>
				</td>
				<?php
				form_end_row();
			}
		}

		html_end_box();
	}

	form_save_button('data_source_profiles.php', 'return');

	?>
	<script type='text/javascript'>

	var profile_id=<?php print get_request_var('id');?>;

	$(function() {
		$('body').append("<div id='cdialog'></div>");

        $('#consolidation_function_id').multiselect({
            selectedList: 1,
            noneSelectedText: 'Select Consolidation Function(s)',
            header: false,
            multipleRow: true,
            multipleRowWidth: 90,
            height: 28,
            minWidth: 400,
			click: function(event, ui){
				get_size();
			}
        });

		get_size();
		$('consolidation_function_id').change(function() {
			get_size();
		});

		<?php if (!$readonly) {?>
		$('.delete').click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'data_source_profiles.php?action=item_remove_confirm&id='+id[1]+'&profle_id='+id[0];
			$.get(request, function(data) {
				$('#cdialog').html(data);
				applySkin();
				$('#cdialog').dialog({ 
					title: 'Delete Data Source Profile Item', 
					close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
					minHeight: 80, 
					minWidth: 500 
				});
			});
		}).css('cursor', 'pointer');
		<?php }else{ ?>
		$('#step').prop('disabled', true);
		$('#x_files_factor').prop('disabled', true);
		$('#heartbeat').prop('disabled', true);
		$('#consolidation_function_id').prop('disabled', true);
		$('#consolidation_function_id').multiselect('disable');
		<?php } ?>
	});

	function get_size() {
		checked = $('#consolidation_function_id').multiselect('getChecked').length;
		$.get('data_source_profiles.php?action=ajax_size&type=profile&id='+profile_id+'&cfs='+checked, function(data) {
			$('#size').html(data);
		});
	}

	</script>
	<?php
}

function get_size($id, $type, $cfs = '') {
	// On x86_64 platform, here is the equation
	// file_size = $header + (# data sources * 300) + (# cfs * #rows in all RRAs)
	$header   = 284;
	$dsheader = 300;
	$row      = 8;

	if ($type == 'profile') {
		if (empty($cfs)) {
			$cfs  = db_fetch_cell_prepared('SELECT COUNT(*) FROM data_source_profiles_cf WHERE data_source_profile_id = ?', array($id));
		}

		$rows = db_fetch_cell_prepared('SELECT SUM(rows) FROM data_source_profiles_rra WHERE data_source_profile_id = ?', array($id));

		return number_format($rows * $row * $cfs + $dsheader) . " Bytes per Data Source, and $header Bytes for the Header.";
	}else{
		$cfs  = db_fetch_cell_prepared('SELECT COUNT(*) FROM data_source_profiles_cf WHERE data_source_profile_id = ?', array($id));
		$rows = get_filter_request_var('rows');

		return number_format($rows * $row * $cfs) . " Bytes per Data Source.";
	}
}

function get_span($duration) {
	$years  = '';
	$months = '';
	$weeks  = '';
	$days   = '';
	$output = '';

	if ($duration > 31536000) {
		if (floor($duration/31536000) > 0) {
			$years     = floor($duration/31536000) . ' Years ';;
			$duration %= 31536000;
			$output    = $years;
		}
	}

	if ($duration > 2592000) {
		if (floor($duration/2592000)) {
			$months = floor($duration/2592000) . ' Months ';;
			$duration %= 2592000;
			$output   .= (strlen($output) ? ', ':'') . $months;
		}
	}

	if ($duration > 604800) {
		if (floor($duration/604800) > 0) {
			$weeks     = floor($duration/604800) . ' Weeks ';
			$duration %= 604800;
			$output   .= (strlen($output) ? ', ':'') . $weeks;
		}
	}

	if ($duration > 86400) {
		if (floor($duration/86400) > 0) {
			$days      = floor($duration/86400) . ' Days ';
			$duration %= 86400;
			$output   .= (strlen($output) ? ', ':'') . $days;
		}
	}

	if (floor($duration/3600) > 0) {
		$hours   = floor($duration/3600) . ' Hours';
		$output .= (strlen($output) ? ', ':'') . $hours;
	}

	return $output;
}

function profile() {
	global $profile_actions, $item_rows, $sampling_intervals, $heartbeats;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'name', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_data' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_dsp');
	/* ================= input validation ================= */

	html_start_box("Data Source Profile's", '100%', '', '3', 'center', 'data_source_profiles.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_dsp' action='data_source_profiles.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						Profiles
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='has_data' <?php print (get_request_var('has_data') == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_data'>Has Data Sources</label>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print get_request_var('page');?>'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL = 'data_source_profiles.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_data='+$('#has_data').is(':checked')+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'data_source_profiles.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_data').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_dsp').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var('has_data') == 'true') {
		$sql_having = 'HAVING data_sources>0';
	}else{
		$sql_having = '';
	}

	form_start('data_source_profiles.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(rows)
		FROM (
			SELECT dsp.id AS rows,
			SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_source_profiles AS dsp
			LEFT JOIN data_template_data AS dtd
			ON dsp.id=dtd.data_source_profile_id
			$sql_where
			GROUP BY dsp.id
			$sql_having
		) AS rs");

	$profile_list = db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM (
			SELECT dsp.*, dtd.local_data_id
			FROM data_source_profiles AS dsp
			LEFT JOIN data_template_data AS dtd
			ON dsp.id=dtd.data_source_profile_id
			GROUP BY dsp.id, dtd.data_template_id, dtd.local_data_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('data_source_profiles.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 9, 'Profiles', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('display' => 'Data Source Profile Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this CDEF.'),
		'nosort00' => array('display' => 'Default', 'align' => 'right', 'tip' => 'Is this the default Profile for all new Data Templates?'), 
		'nosort01' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Profiles that are in use can not be Deleted.  In use is defined as being referenced by a Data Source or a Data Template.'), 
		'nosort02' => array('display' => 'Read Only', 'align' => 'right', 'tip' => 'Profiles that are in use by Data Sources become read only for now.'), 
		'step' => array('display' => 'Poller Interval', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The Polling Frequency for the Profile'),
		'heartbeat' => array('display' => 'Heartbeat', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The Amount of Time, in seocnds, without good data before Data is stored as Unknown'),
		'data_sources' => array('display' => 'Data Sources Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Sources using this Profile.'),
		'templates' => array('display' => 'Templates Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Templates using this Profile.'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($profile_list)) {
		foreach ($profile_list as $profile) {
			if ($profile['data_sources'] == 0 && $profile['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

			if ($profile['data_sources']) {
				$readonly = true;
			}else{
				$readonly = false;
			}

			form_alternate_row('line' . $profile['id'], false, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_source_profiles.php?action=edit&id=' . $profile['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($profile['name'])) : htmlspecialchars($profile['name'])) . '</a>', $profile['id']);
			form_selectable_cell($profile['default'] == 'on' ? 'Yes':'', $profile['id'], '', 'text-align:right');
			form_selectable_cell($disabled ? 'No':'Yes', $profile['id'], '', 'text-align:right');
			form_selectable_cell($readonly ? 'Yes':'No', $profile['id'], '', 'text-align:right');
			form_selectable_cell($sampling_intervals[$profile['step']], $profile['id'], '', 'text-align:right');
			form_selectable_cell($heartbeats[$profile['heartbeat']], $profile['id'], '', 'text-align:right');
			form_selectable_cell(number_format($profile['data_sources']), $profile['id'], '', 'text-align:right');
			form_selectable_cell(number_format($profile['templates']), $profile['id'], '', 'text-align:right');
			form_checkbox_cell($profile['name'], $profile['id'], $disabled);
			form_end_row();
		}
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='4'><em>No Data Source Profiles</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($profile_actions);

	form_end();
}

