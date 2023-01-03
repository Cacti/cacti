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

include('./include/auth.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

$profile_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate')
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

		if (is_numeric(get_request_var('rows')) && get_request_var('rows') > 0) {
			get_filter_request_var('rows');

			$sampling_interval = db_fetch_cell_prepared('SELECT step
				FROM data_source_profiles
				WHERE id = ?',
				array(get_request_var('profile_id')));

			if (get_request_var('span') == 1) {
				print get_span(get_request_var('rows') * $sampling_interval);
			} else {
				print get_span(get_request_var('rows') * get_request_var('span'));
			}
		} else {
			print __('N/A');
		}

		break;
	case 'ajax_size':
		get_filter_request_var('id');
		get_filter_request_var('cfs');
		get_filter_request_var('rows');
		print get_size(get_request_var('id'), get_nfilter_request_var('type'), get_request_var('cfs'), get_request_var('rows'));

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

function form_save() {
	// make sure ids are numeric
	if (isset_request_var('id') && ! is_numeric(get_filter_request_var('id'))) {
		set_request_var('id', 0);
	}

	if (isset_request_var('profile_id') && ! is_numeric(get_filter_request_var('profile_id'))) {
		set_request_var('profile_id', 0);
	}

	if (get_request_var('id') > 0) {
		$prev_heartbeat = db_fetch_cell_prepared('SELECT heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			array(get_request_var('id')));
	} else {
		$prev_heartbeat = get_request_var('heartbeat');
	}

	if (isset_request_var('save_component_profile')) {
		$save['id']             = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']           = get_hash_data_source_profile(get_request_var('id'));

		$save['name']           = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);

		if (isset_request_var('step')) {
			$save['step']           = form_input_validate(get_nfilter_request_var('step'), 'step', '', false, 3);
			$save['heartbeat']      = form_input_validate(get_nfilter_request_var('heartbeat'), 'heartbeat', '', false, 3);
			$save['x_files_factor'] = form_input_validate(get_nfilter_request_var('x_files_factor'), 'x_files_factor', '', false, 3);
		}

		if (isset_request_var('default')) {
			$save['default'] = (isset_request_var('default') ? 'on':'');
			db_execute('UPDATE data_source_profiles SET `default` = ""');
		}

		if (!is_error_message()) {
			$profile_id = sql_save($save, 'data_source_profiles');

			if ($profile_id) {
				if (isset_request_var('step')) {
					// Validate consolidation functions
					$cfs = get_nfilter_request_var('consolidation_function_id');

					if (cacti_sizeof($cfs) && !empty($cfs)) {
						foreach ($cfs as $cf) {
							input_validate_input_number($cf, 'consolidation_function_id');
						}

						db_execute_prepared('DELETE FROM data_source_profiles_cf
							WHERE data_source_profile_id = ?
							AND consolidation_function_id NOT IN (' . implode(',', $cfs) . ')', array($profile_id));
					}

					// Validate consolidation functions
					$cfs = get_nfilter_request_var('consolidation_function_id');

					if (cacti_sizeof($cfs) && !empty($cfs)) {
						foreach ($cfs as $cf) {
							db_execute_prepared('REPLACE INTO data_source_profiles_cf
								(data_source_profile_id, consolidation_function_id)
								VALUES (?, ?)', array($profile_id, $cf));
						}
					}
				}

				if ($prev_heartbeat != get_request_var('heartbeat')) {
					$existing = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM data_template_data
						WHERE data_source_profile_id = ?
						AND local_data_id > 0',
						array(get_request_var('id')));

					if ($existing) {
						db_execute_prepared('UPDATE data_template_rrd AS dtr
							INNER JOIN data_template_data AS dtd
							ON dtd.local_data_id = dtr.local_data_id
							SET dtr.rrd_heartbeat = ?
							WHERE dtd.data_source_profile_id = ?',
							array(get_request_var('heartbeat'), get_request_var('id')));

						raise_message('heartbeat_change', __('Changing the Heartbeat from this page, does not change the Heartbeat for your existing Data Sources.  Use RRDtool\'s \'tune\' function to make that change to your existing RRDfiles heartbeats, or run the CLI utility update_heartbeat.php to correct.<br>'), MESSAGE_LEVEL_WARN);
					}
				}

				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: data_source_profiles.php?action=edit&id=' . (empty($profile_id) ? get_request_var('id') : $profile_id));
	} elseif (isset_request_var('save_component_rra')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('profile_id');
		/* ==================================================== */

		$sampling_interval = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			array(get_request_var('profile_id')));

		$save['id']                      = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['name']                    = form_input_validate(get_nfilter_request_var('name'), 'name', '', true, 3);
		$save['data_source_profile_id']  = form_input_validate(get_request_var('profile_id'), 'profile_id', '^[0-9]+$', false, 3);
		$save['timespan']                = form_input_validate(get_nfilter_request_var('timespan'), 'timespan', '^[0-9]+$', false, 3);

		if (isset_request_var('steps')) {
			$save['steps'] = form_input_validate(get_nfilter_request_var('steps'), 'steps', '^[0-9]+$', false, 3);

			if ($save['steps'] != '1') {
				$save['steps'] /= $sampling_interval;
			}
		}

		if (isset_request_var('rows')) {
			$save['rows'] = form_input_validate(get_nfilter_request_var('rows'), 'rows', '^[0-9]+$', false, 3);
		}

		if (!is_error_message()) {
			$profile_rra_id = sql_save($save, 'data_source_profiles_rra');

			if ($profile_rra_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: data_source_profiles.php?action=item_edit&profile_id=' . get_request_var('profile_id') . '&id=' . (empty($profile_rra_id) ? get_request_var('id') : $profile_rra_id));
		} else {
			header('Location: data_source_profiles.php?action=edit&id=' . get_request_var('profile_id'));
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
			if (get_request_var('drp_action') == '1') { // delete
				db_execute('DELETE FROM data_source_profiles WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM data_source_profiles_rra WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
				db_execute('DELETE FROM data_source_profiles_cf WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
			} elseif (get_request_var('drp_action') == '2') { // duplicate
				duplicate_data_source_profile($selected_items, get_nfilter_request_var('title_format'));
			}
		}

		header('Location: data_source_profiles.php');

		exit;
	}

	/* setup some variables */
	$profile_list = '';
	$i            = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$profile_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM data_source_profiles WHERE id = ?', array($matches[1]))) . '</li>';
			$profile_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_source_profiles.php');

	html_start_box($profile_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($profile_array) && cacti_sizeof($profile_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Data Source Profile', 'Click \'Continue\' to delete following Data Source Profiles', cacti_sizeof($profile_array)) . "</p>
					<div class='itemlist'><ul>$profile_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Data Source Profile', 'Delete Data Source Profiles', cacti_sizeof($profile_array)) . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to duplicate the following Data Source Profile. You can optionally change the title format for the new Data Source Profile', 'Click \'Continue\' to duplicate following Data Source Profiles. You can optionally change the title format for the new Data Source Profiles.', cacti_sizeof($profile_array)) . "</p>
					<div class='itemlist'><ul>$profile_list</ul></div>
					<p>" . __('Title Format:') . '<br>';
			form_text_box('title_format', '<profile_title> (1)', '', '255', '30', 'text');
			print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Duplicate Data Source Profile', 'Duplicate Date Source Profiles', cacti_sizeof($profile_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: data_source_profiles.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($profile_array) ? serialize($profile_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
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

function duplicate_data_source_profile($source_profile, $title_format) {
	if (!is_array($source_profile)) {
		$source_profile = array($source_profile);
	}

	foreach ($source_profile as $id) {
		$profile = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles
			WHERE id = ?',
			array($id));

		if (cacti_sizeof($profile)) {
			$save = array();

			$save['id']   = 0;

			foreach ($profile as $column => $value) {
				if ($column == 'id') {
					continue;
				}

				if ($column == 'hash') {
					$save['hash'] = get_hash_data_source_profile(0);
				} elseif ($column == 'name') {
					$save['name'] = str_replace('<profile_title>', $value, $title_format);
				} elseif ($column == 'default') {
					$save['default'] = '';
				} else {
					$save[$column] = $value;
				}
			}

			$newid = sql_save($save, 'data_source_profiles');

			if ($newid > 0) {
				db_execute_prepared("INSERT INTO data_source_profiles_cf
					SELECT '$newid' AS data_source_profile_id, consolidation_function_id
					FROM data_source_profiles_cf
					WHERE data_source_profile_id = ?",
					array($id));

				db_execute_prepared("INSERT INTO data_source_profiles_rra
					(`data_source_profile_id`, `name`, `steps`, `rows`, `timespan`)
					SELECT '$newid', `name`, `steps`, `rows`, `timespan`
					FROM data_source_profiles_rra
					WHERE data_source_profile_id = ?",
					array($id));

				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			raise_message('profile_error', __('Unable to duplicate Data Source Profile.  Check Cacti Log for errors.'), MESSAGE_LEVEL_ERROR);
		}
	}
}

function profile_item_remove_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('profile_id');
	/* ==================================================== */

	form_start('data_source_profiles.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$profile = db_fetch_row_prepared('SELECT *
		FROM data_source_profiles_rra
		WHERE id = ?',
		array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Source Profile RRA.');?></p>
			<p><?php print __esc('Profile Name: %s', $profile['name']);?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' title='<?php print __esc('Remove Data Source Profile RRA');?>'>
			<input type='hidden' id='rra_profile_id' value='<?php print $profile['data_source_profile_id'];?>'>
			<input type='hidden' id='rra_id' value='<?php print get_request_var('id');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').click(function(data) {
			var options = {
				url: 'data_source_profiles.php?action=item_remove',
				funcEnd: 'removeDataSourceProfilesItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				id: <?php print get_request_var('id');?>
			}

			postUrl(options, data);
		});
	});

	function removeDataSourceProfilesItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'data_source_profiles.php?action=edit&id=<?php print $profile['data_source_profile_id'];?>'})
	}

	</script>
	<?php
}

function profile_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM data_source_profiles_rra WHERE id = ?', array(get_request_var('id')));
}

function item_edit() {
	global $fields_profile_rra_edit, $aggregation_levels;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('profile_id');
	/* ==================================================== */

	$sampling_interval = db_fetch_cell_prepared('SELECT step
		FROM data_source_profiles
		WHERE id = ?',
		array(get_request_var('profile_id')));

	$readonly = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM data_template_data AS dtd
		WHERE data_source_profile_id = ?
		AND local_data_id > 0',
		array(get_request_var('profile_id')));

	if (!isempty_request_var('id')) {
		$rra = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles_rra
			WHERE id = ?',
			array(get_request_var('id')));

		if ($rra['steps'] == '1') {
			$fields_profile_rra_edit['steps']['array'] = array('1' => __('Each Insert is New Row'));
		} else {
			foreach ($aggregation_levels as $interval => $name) {
				if ($interval <= $sampling_interval) {
					unset($aggregation_levels[$interval]);
				}
			}
			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}

		$fields_profile_rra_edit['steps']['value'] = $rra['steps'] * $sampling_interval;
	} else {
		$oneguy = db_fetch_cell_prepared('SELECT id
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?
			AND steps = 1',
			array(get_request_var('profile_id')));

		if (empty($oneguy)) {
			$fields_profile_rra_edit['steps']['array'] = array('1' => __('Each Insert is New Row'));
		} else {
			$max = db_fetch_cell_prepared('SELECT MAX(steps) * ?
				FROM data_source_profiles_rra
				WHERE data_source_profile_id = ?',
				array($sampling_interval, get_request_var('profile_id')));

			foreach ($aggregation_levels as $interval => $name) {
				if ($interval <= $max) {
					unset($aggregation_levels[$interval]);
				}
			}

			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}
	}

	form_start('data_source_profiles.php', 'form_rra');

	$name = db_fetch_cell_prepared('SELECT name
		FROM data_source_profiles_rra
		WHERE id = ?',
		array(get_request_var('id')));

	html_start_box(__esc('RRA [edit: %s %s]', $name, ($readonly ? __('(Some Elements Read Only)'):'')), '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_profile_rra_edit, (isset($rra) ? $rra : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('profile_id', get_request_var('profile_id'), '');

	form_save_button('data_source_profiles.php?action=edit&id=' . get_request_var('profile_id'));

	?>
	<script type='text/javascript'>

	var profile_id=<?php print get_request_var('profile_id') != '' ? get_request_var('profile_id'):0;?>;
	var rows_to = false;
	var readonly = <?php print($readonly ? 'true':'false');?>;

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

		if (readonly) {
			$('#steps').prop('disabled', true);
			if ($('#steps').selectmenu('instance')) {
				$('#steps').selectmenu('disable');
			}

			$('#rows').prop('disabled', true);
			if ($('#rows').selectmenu('instance')) {
				$('#rows').selectmenu('disable');
			}
		}
	});

	function get_size() {
		$.get('data_source_profiles.php?action=ajax_size&type=rra&id='+profile_id+'&rows='+$('#rows').val())
			.done(function(data) {
				$('#row_size').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	}

	function get_span() {
		$.get('data_source_profiles.php?action=ajax_span&profile_id='+profile_id+'&span='+$('#steps').val()+'&rows='+$('#rows').val())
			.done(function(data) {
				$('#row_retention').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});

	}
	</script>
	<?php
}

/* ---------------------
	Profile Functions
   --------------------- */

function profile_edit() {
	global $fields_profile_edit, $timespans;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$profile = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles
			WHERE id = ?',
			array(get_request_var('id')));

		$readonly     = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_template_data AS dtd
			WHERE data_source_profile_id = ?
			AND local_data_id > 0',
			array(get_request_var('id')));

		$header_label = __esc('Data Source Profile [edit: %s]', $profile['name'] . ($readonly ? ' (Read Only)':''));
	} else {
		$header_label = __('Data Source Profile [new]');
		$readonly     = false;
	}

	form_start('data_source_profiles.php', 'profile');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_profile_edit, (isset($profile) ? $profile : array()))
		)
	);

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		if (!$readonly) {
			html_start_box(__('Data Source Profile RRAs (press save to update timespans)'), '100%', '', '3', 'center', 'data_source_profiles.php?action=item_edit&profile_id=' . $profile['id']);
		} else {
			html_start_box(__('Data Source Profile RRAs (Read Only)'), '100%', '', '3', 'center', '');
		}

		$display_text = array(
			array('display' => __('Name'),           'align' => 'left'),
			array('display' => __('Data Retention'), 'align' => 'left'),
			array('display' => __('Graph Timespan'), 'align' => 'left'),
			array('display' => __('Steps'),          'align' => 'left'),
			array('display' => __('Rows'),           'align' => 'left'),
		);

		html_header($display_text, 2);

		$profile_rras = db_fetch_assoc_prepared('SELECT *
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?
			ORDER BY steps',
			array(get_request_var('id')));

		$i = 0;

		if (cacti_sizeof($profile_rras)) {
			foreach ($profile_rras as $rra) {
				form_alternate_row('line' . $rra['id']);
				$i++;?>
				<td>
					<?php print "<a class='linkEditMain' href='" . html_escape('data_source_profiles.php?action=item_edit&id=' . $rra['id'] . '&profile_id=' . $rra['data_source_profile_id']) . "'>" . html_escape($rra['name']) . '</a>';?>
				</td>
				<td>
					<em><?php print get_span($profile['step'] * $rra['steps'] * $rra['rows']);?></em>
				</td>
				<td>
					<em><?php print isset($timespans[$rra['timespan']]) ? $timespans[$rra['timespan']]:get_span($rra['timespan']);?></em>
				</td>
				<td>
					<em><?php print $rra['steps'];?></em>
				</td>
				<td>
					<em><?php print $rra['rows'];?></em>
				</td>
				<td class='right'>
					<?php print(!$readonly ? "<a id='" . $profile['id'] . '_' . $rra['id'] . "' class='delete deleteMarker fa fa-times' title='" . __esc('Delete') . "' href='#'></a>":'');?>
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

	var profile_id=<?php print get_request_var('id') != '' ? get_request_var('id'):0;?>;

	$(function() {
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

        $('#consolidation_function_id').multiselect({
            selectedList: 4,
            noneSelectedText: '<?php print __('Select Consolidation Function(s)');?>',
            header: false,
            groupColumns: true,
            groupColumnsWidth: 90,
            height: 28,
            menuWidth: 400,
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
			request = 'data_source_profiles.php?action=item_remove_confirm&id='+id[1]+'&profile_id='+id[0];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').off('click').on('click', function(data) {
						$.post('data_source_profiles.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							id: $('#rra_id').val()
						}).done(function(data) {
							$('#cdialog').dialog('close');
							loadUrl({url:'data_source_profiles.php?action=edit&id=' + $('#rra_profile_id').val()});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __('Delete Data Source Profile Item');?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');
		<?php } else { ?>
		$('#step').prop('disabled', true);
		if ($('#step').selectmenu('instance')) {
			$('#step').selectmenu('disable')
		}

		$('#x_files_factor').prop('disabled', true);

		$('#consolidation_function_id').prop('disabled', true);
		if ($('#consolidation_function_id').multiselect('instance')) {
			$('#consolidation_function_id').multiselect('disable');
		}
		<?php } ?>
	});

	function get_size() {
		checked = $('#consolidation_function_id').multiselect('getChecked').length;
		$.get('data_source_profiles.php?action=ajax_size&type=profile&id='+profile_id+'&cfs='+checked)
			.done(function(data) {
				$('#row_size').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	}

	</script>
	<?php
}

function get_size($id, $type, $cfs = '', $rows = 1) {
	// On x86_64 platform, here is the equation
	// file_size = $header + (# data sources * 300) + (# cfs * #rows in all RRAs)
	$header   = 284;
	$dsheader = 300;
	$row      = 8;

	if ($type == 'profile') {
		if (empty($cfs)) {
			$cfs  = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM data_source_profiles_cf
				WHERE data_source_profile_id = ?',
				array($id));
		}

		$rows = db_fetch_cell_prepared('SELECT SUM(`rows`)
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?',
			array($id));

		return __('%s KBytes per Data Sources and %s Bytes for the Header', number_format_i18n(($rows * $row * $cfs + $dsheader) / 1000), $header);
	}

	if ($rows > 0) {
		$cfs  = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_source_profiles_cf
			WHERE data_source_profile_id = ?',
			array($id));

		return __('%s KBytes per Data Source', number_format_i18n(($rows * $row * $cfs) / 1000));
	} else {
		return __('Enter a valid number of Rows to obtain the RRA size.');
	}
}

function get_span($duration) {
	$years  = '';
	$months = '';
	$weeks  = '';
	$days   = '';
	$output = '';

	if ($duration > 31536000) {
		if (floor($duration / 31536000) > 0) {
			$years     = floor($duration / 31536000);
			$years	    = ($years == 1) ? __('1 Year') : __('%d Years', $years);
			$duration %= 31536000;
			$output    = $years;
		}
	}

	if ($duration > 2592000) {
		if (floor($duration / 2592000)) {
			$months    = floor($duration / 2592000);
			$months    = ($months == 1) ? __('%d Month', 1) : __('%d Months', $months);
			$duration %= 2592000;
			$output .= ($output != '' ? ', ' : '') . $months;
		}
	}

	if ($duration > 604800) {
		if (floor($duration / 604800) > 0) {
			$weeks     = floor($duration / 604800);
			$weeks     = ($weeks == 1) ? __('%d Week', 1) : __('%d Weeks', $weeks);
			$duration %= 604800;
			$output .= ($output != '' ? ', ' : '') . $weeks;
		}
	}

	if ($duration > 86400) {
		if (floor($duration / 86400) > 0) {
			$days      = floor($duration / 86400);
			$days      = ($days == 1) ? __('%d Day', 1) : __('%d Days', $days);
			$duration %= 86400;
			$output .= ($output != '' ? ', ' : '') . $days;
		}
	}

	if (floor($duration / 3600) > 0) {
		$hours   = floor($duration / 3600);
		$hours   = ($hours == 1) ? __('1 Hour') : __('%d Hours', $hours);
		$output .= ($output != '' ? ', ' : '') . $hours;
	}

	return $output;
}

function profile() {
	global $profile_actions, $item_rows, $sampling_intervals, $heartbeats, $config;

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
			'default' => 'step',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_data' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_dsp');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Data Source Profiles'), '100%', '', '3', 'center', 'data_source_profiles.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_dsp' action='data_source_profiles.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Profiles');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . "</option>\n";
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_data' <?php print(get_request_var('has_data') == 'true' ? 'checked':'');?>>
							<label for='has_data'><?php print __('Has Data Sources');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'data_source_profiles.php';
				strURL += '?filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				strURL += '&has_data='+$('#has_data').is(':checked');
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = 'data_source_profiles.php?clear=1';
				loadUrl({url:strURL})
			}

			$(function() {
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
		$sql_where = 'WHERE (dsp.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('has_data') == 'true') {
		$sql_having = 'HAVING data_sources > 0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(`rows`)
		FROM (
			SELECT dsp.id AS `rows`,
			SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_source_profiles AS dsp
			LEFT JOIN data_template_data AS dtd
			ON dsp.id=dtd.data_source_profile_id
			$sql_where
			GROUP BY dsp.id
			$sql_having
		) AS rs");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$profile_list = db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM (
			SELECT dsp.*, dtd.local_data_id
			FROM data_source_profiles AS dsp
			LEFT JOIN data_template_data AS dtd
			ON dsp.id=dtd.data_source_profile_id
			$sql_where
			GROUP BY dsp.id, dtd.data_template_id, dtd.local_data_id
		) AS rs
		GROUP BY rs.id
		$sql_having
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Data Source Profile Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this CDEF.')
		),
		'nosort00' => array(
			'display' => __('Default'),
			'align'   => 'right',
			'tip'     => __('Is this the default Profile for all new Data Templates?')
		),
		'nosort01' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Profiles that are in use cannot be Deleted. In use is defined as being referenced by a Data Source or a Data Template.')
		),
		'nosort02' => array(
			'display' => __('Read Only'),
			'align'   => 'right',
			'tip'     => __('Profiles that are in use by Data Sources become read only for now.')
		),
		'step' => array(
			'display' => __('Poller Interval'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Polling Frequency for the Profile')
		),
		'heartbeat' => array(
			'display' => __('Heartbeat'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Amount of Time, in seconds, without good data before Data is stored as Unknown')
		),
		'data_sources' => array(
			'display' => __('Data Sources Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Sources using this Profile.')
		),
		'templates' => array(
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Templates using this Profile.')
		)
	);

	$nav = html_nav_bar('data_source_profiles.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Profiles'), 'page', 'main');

	form_start('data_source_profiles.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($profile_list)) {
		foreach ($profile_list as $profile) {
			if ($profile['data_sources'] == 0 && $profile['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			if ($profile['data_sources']) {
				$readonly = true;
			} else {
				$readonly = false;
			}

			if ($profile['data_sources'] > 0) {
				$ds = '<a class="linkEditMain" href="' . CACTI_PATH_URL . 'data_sources.php?reset=true&profile=' . $profile['id'] . '">' . number_format_i18n($profile['data_sources'], '-1') . '</a>';
			} else {
				$ds = number_format_i18n($profile['data_sources'], '-1');
			}

			if ($profile['templates'] > 0) {
				$dt = '<a class="linkEditMain" href="' . CACTI_PATH_URL . 'data_templates.php?reset=true&profile=' . $profile['id'] . '">' . number_format_i18n($profile['templates'], '-1') . '</a>';
			} else {
				$dt = number_format_i18n($profile['templates'], '-1');
			}

			form_alternate_row('line' . $profile['id'], false, $disabled);
			form_selectable_cell(filter_value($profile['name'], get_request_var('filter'), 'data_source_profiles.php?action=edit&id=' . $profile['id']), $profile['id']);
			form_selectable_cell($profile['default'] == 'on' ? __('Yes'):'', $profile['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $profile['id'], '', 'right');
			form_selectable_cell($readonly ? __('Yes'):__('No'), $profile['id'], '', 'right');
			form_selectable_cell($sampling_intervals[$profile['step']], $profile['id'], '', 'right');
			form_selectable_cell($heartbeats[$profile['heartbeat']], $profile['id'], '', 'right');
			form_selectable_cell($ds, $profile['id'], '', 'right');
			form_selectable_cell($dt, $profile['id'], '', 'right');
			form_checkbox_cell($profile['name'], $profile['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Source Profiles Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($profile_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($profile_actions);

	form_end();
}
