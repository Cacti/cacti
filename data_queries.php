<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
include_once('./lib/data_query.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

$dq_actions = array(
	1 => __('Delete')
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
	case 'item_moveup_dssv':
		data_query_item_moveup_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_movedown_dssv':
		data_query_item_movedown_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_remove_dssv':
		data_query_item_remove_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_moveup_gsv':
		data_query_item_moveup_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_movedown_gsv':
		data_query_item_movedown_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_remove_gsv':
		data_query_item_remove_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . get_filter_request_var('snmp_query_graph_id') . '&snmp_query_id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_remove_confirm':
		data_query_item_remove_confirm();

		break;
	case 'item_remove':
		data_query_item_remove();

		header('Location: data_queries.php?action=edit&id=' . get_filter_request_var('snmp_query_id'));

		break;
	case 'item_edit':
		top_header();

		data_query_item_edit();

		bottom_footer();

		break;
	case 'remove':
		data_query_remove();

		header('Location: data_queries.php');

		break;
	case 'edit':
		top_header();

		data_query_edit();

		bottom_footer();

		break;

	default:
		top_header();

		data_query();

		bottom_footer();

		break;
}

/* --------------------------
	The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_snmp_query')) {
		get_filter_request_var('id');
		get_filter_request_var('data_input_id');

		$save['id']            = get_request_var('id');
		$save['hash']          = get_hash_data_query(get_nfilter_request_var('id'));
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description']   = form_input_validate(get_nfilter_request_var('description'), 'description', '', true, 3);
		$save['xml_path']      = form_input_validate(get_nfilter_request_var('xml_path'), 'xml_path', '', false, 3);
		$save['data_input_id'] = get_request_var('data_input_id');

		// Detect changing input id
		if (!empty($save['id'])) {
			$previous_input_id = db_fetch_cell_prepared('SELECT data_input_id
				FROM snmp_query
				WHERE id = ?',
				array($save['id']));
		}

		if (!is_error_message()) {
			$snmp_query_id = sql_save($save, 'snmp_query');

			if ($snmp_query_id) {
				raise_message(1);

				if (isset($previous_input_id) && $previous_input_id > 0) {
					data_query_update_input_method($snmp_query_id, $previous_input_id, $save['data_input_id']);
				}

				update_replication_crc(0, 'poller_replicate_snmp_query_crc');
			} else {
				raise_message(2);
			}
		}

		header('Location: data_queries.php?action=edit&id=' . (empty($snmp_query_id) ? get_request_var('id') : $snmp_query_id));
	} elseif (isset_request_var('save_component_snmp_query_item') && !isset_request_var('svg_x') && !isset_request_var('svds_x')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('snmp_query_id');
		get_filter_request_var('graph_template_id');
		/* ==================================================== */

		$save['id']                = get_request_var('id');
		$save['hash']              = get_hash_data_query(get_nfilter_request_var('id'), 'data_query_graph');
		$save['snmp_query_id']     = get_request_var('snmp_query_id');
		$save['name']              = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['graph_template_id'] = get_request_var('graph_template_id');

		$errors = false;

		if (!is_error_message()) {
			if ($save['id'] > 0) {
				$errors = api_data_query_errors($save['id'], $_POST);
			}

			if ($errors === false) {
				$snmp_query_graph_id = sql_save($save, 'snmp_query_graph');

				if ($snmp_query_graph_id) {
					raise_message(1);

					/* if the user changed the graph template, go through and delete everything that
					was associated with the old graph template */
					if (get_nfilter_request_var('graph_template_id') != get_nfilter_request_var('graph_template_id_prev')) {
						db_execute_prepared('DELETE
							FROM snmp_query_graph_rrd_sv
							WHERE snmp_query_graph_id = ?',
							array($snmp_query_graph_id));

						db_execute_prepared('DELETE
							FROM snmp_query_graph_sv
							WHERE snmp_query_graph_id = ?',
							array($snmp_query_graph_id));
					}

					db_execute_prepared('DELETE
						FROM snmp_query_graph_rrd
						WHERE snmp_query_graph_id = ?',
						array($snmp_query_graph_id));

					foreach ($_POST as $var => $val) {
						if (preg_match('/^dsdt_([0-9]+)_([0-9]+)_check/i', $var)) {
							$data_template_id     = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', '\\1', $var);
							$data_template_rrd_id = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', '\\2', $var);
							/* ================= input validation ================= */
							input_validate_input_number($data_template_id, 'dsdt->data_template_id');
							input_validate_input_number($data_template_rrd_id, 'dsdt->data_template_rrd_id');
							/* ==================================================== */

							db_execute_prepared('REPLACE INTO snmp_query_graph_rrd
								(snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name)
								VALUES (?, ?, ?, ?)',
								array(
									$snmp_query_graph_id,
									$data_template_id,
									$data_template_rrd_id,
									get_nfilter_request_var('dsdt_' .
									$data_template_id . '_' .
									$data_template_rrd_id . '_snmp_field_output')
								)
							);
						}
					}
				} else {
					raise_message(2);
				}
			}
		}

		header('Location: data_queries.php?action=item_edit&id=' . (empty($snmp_query_graph_id) ? get_request_var('id') : $snmp_query_graph_id) . '&snmp_query_id=' . get_request_var('snmp_query_id'));
	} elseif (isset_request_var('save_component_svg')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('snmp_query_id');
		get_filter_request_var('graph_template_id');
		/* ==================================================== */

		if (isempty_request_var('svg_text')) {
			raise_message(39);
			header('Location: data_queries.php?action=item_edit&id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));

			return;
		}

		if (isempty_request_var('svg_field')) {
			raise_message(38);
			header('Location: data_queries.php?action=item_edit&id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));

			return;
		}

		/* suggested values -- graph templates */
		$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_sv', 'snmp_query_graph_id = ' . get_filter_request_var('id') . ' AND field_name = ' . db_qstr(get_nfilter_request_var('svg_field')));

		$hash = get_hash_data_query(0, 'data_query_sv_graph');

		db_execute_prepared('INSERT INTO snmp_query_graph_sv
			(hash, snmp_query_graph_id, sequence, field_name, text)
			VALUES (?, ?, ?, ?, ?)',
			array(
				$hash,
				get_request_var('id'),
				$sequence,
				get_nfilter_request_var('svg_field'),
				get_nfilter_request_var('svg_text')
			)
		);

		clear_messages();

		header('Location: data_queries.php?action=item_edit&id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));
	} elseif (isset_request_var('save_component_svds')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('svds_id');
		get_filter_request_var('snmp_query_id');
		get_filter_request_var('graph_template_id');
		/* ==================================================== */

		if (isset_request_var('svds_id')) {
			$svds_id = get_request_var('svds_id');

			if (isempty_request_var('svds_text')) {
				raise_message(39);
				header('Location: data_queries.php?action=item_edit&id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));

				return;
			}

			if (isempty_request_var('svds_field')) {
				raise_message(38);
				header('Location: data_queries.php?action=item_edit&id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));

				return;
			}

			$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_id = ' . get_request_var('id')  . ' AND data_template_id = ' . $svds_id . ' AND field_name = ' . db_qstr(get_nfilter_request_var('svds_field')));

			$hash = get_hash_data_query(0, 'data_query_sv_data_source');

			db_execute_prepared('INSERT INTO snmp_query_graph_rrd_sv
				(hash, snmp_query_graph_id, data_template_id, sequence, field_name, text)
				VALUES (?, ?, ?, ?, ?, ?)',
				array(
					$hash,
					get_request_var('id'),
					$svds_id,
					$sequence,
					get_nfilter_request_var('svds_field'),
					get_nfilter_request_var('svds_text')
				)
			);

			clear_messages();

			header('Location: data_queries.php?action=item_edit?id=' . get_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));
		}
	}
}

function form_actions() {
	global $dq_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					data_query_remove($selected_items[$i]);
				}
			}
		}

		header('Location: data_queries.php');

		exit;
	}

	/* setup some variables */
	$dq_list = '';
	$i       = 0;

	/* loop through each of the data queries and process them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$name = db_fetch_cell_prepared('SELECT name
				FROM snmp_query
				WHERE id = ?',
				array($matches[1]));

			$dq_list .= '<li>' . html_escape($name) . '</li>';
			$dq_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_queries.php');

	html_start_box($dq_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($dq_array) && cacti_sizeof($dq_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			$graphs = array();

			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Data Query.', 'Click \'Continue\' to delete following Data Queries.', cacti_sizeof($dq_array)) . "</p>
					<div class='itemlist'><ul>$dq_list</ul></div>
				</td>
			</tr>\n";
		}

		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Data Query', 'Delete Data Query', cacti_sizeof($dq_array)) . "'>";
	} else {
		raise_message(40);
		header('Location: data_queries.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($dq_array) ? serialize($dq_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
	Data Query Graph Functions
   ---------------------------- */

function data_query_item_movedown_gsv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_graph_id');
	/* ==================================================== */

	move_item_down('snmp_query_graph_sv', get_request_var('id'), 'snmp_query_graph_id=' . get_request_var('snmp_query_graph_id') . ' AND field_name = ' . db_qstr(get_nfilter_request_var('field_name')));
}

function data_query_item_moveup_gsv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_graph_id');
	/* ==================================================== */

	move_item_up('snmp_query_graph_sv', get_request_var('id'), 'snmp_query_graph_id=' . get_request_var('snmp_query_graph_id') . ' AND field_name = ' . db_qstr(get_nfilter_request_var('field_name')));
}

function data_query_item_remove_gsv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM snmp_query_graph_sv
		WHERE id = ?',
		array(get_request_var('id')));
}

function data_query_item_movedown_dssv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_template_id');
	get_filter_request_var('snmp_query_graph_id');
	/* ==================================================== */

	move_item_down('snmp_query_graph_rrd_sv', get_request_var('id'), 'data_template_id=' . get_request_var('data_template_id') . ' AND snmp_query_graph_id=' . get_request_var('snmp_query_graph_id') . ' AND field_name = ' . db_qstr(get_nfilter_request_var('field_name')));
}

function data_query_item_moveup_dssv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_template_id');
	get_filter_request_var('snmp_query_graph_id');
	/* ==================================================== */

	move_item_up('snmp_query_graph_rrd_sv', get_request_var('id'), 'data_template_id=' . get_request_var('data_template_id') . ' AND snmp_query_graph_id=' . get_request_var('snmp_query_graph_id') . ' AND field_name = ' . db_qstr(get_nfilter_request_var('field_name')));
}

function data_query_sv_check_sequences($type, $snmp_query_graph_id, $field_name) {
	if ($type == 'ds' || $type == 'gr') {
		if ($type == 'ds') {
			$table = 'snmp_query_graph_rrd_sv';
		} else {
			$table = 'snmp_query_graph_sv';
		}
	} else {
		return false;
	}

	$bad_seq = db_fetch_cell_prepared("SELECT COUNT(sequence)
		FROM $table
		WHERE sequence <= 0
		AND field_name = ?
		AND snmp_query_graph_id = ?",
		array($field_name, $snmp_query_graph_id));

	$dup_seq = db_fetch_cell_prepared("SELECT SUM(count)
		FROM (
			SELECT sequence, COUNT(sequence) AS count
			FROM $table
			WHERE field_name = ?
			AND snmp_query_graph_id = ?
			GROUP BY sequence
		) AS t
		WHERE t.count > 1",
		array($field_name, $snmp_query_graph_id));

	// report any bad or duplicate sequences to the log for reporting purposes
	if ($bad_seq > 0) {
		cacti_log('WARN: Found ' . $bad_seq . " Bad Sequences in $table Table", false, 'WEBUI', POLLER_VERBOSITY_HIGH);
	}

	if ($dup_seq > 0) {
		cacti_log('WARN: Found ' . $dup_seq . " Duplicated Sequences in $table Table", false, 'WEBUI', POLLER_VERBOSITY_HIGH);
	}

	if ($bad_seq > 0 || $dup_seq > 0) {
		// resequence the list so it has no gaps, and 0 values will appear at the top
		// since thats where they would have been displayed
		db_execute_prepared("SET @seq = 0;
			UPDATE $table
			SET sequence = (@seq:=@seq+1)
			WHERE field_name = ?
			AND snmp_query_graph_id = ?
			ORDER BY sequence, id;",
			array($field_name, $snmp_query_graph_id));
	}
}

function data_query_item_remove_dssv() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd_sv
		WHERE id = ?',
		array(get_request_var('id')));
}

function data_query_item_remove_confirm() {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	form_start('data_queries.php?action=edit&id' . get_request_var('snmp_query_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$graph_template = db_fetch_row_prepared('SELECT *
		FROM snmp_query_graph
		WHERE id = ?',
		array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Query Graph Association.');?></p>
			<p><?php print __esc('Graph Name: %s', $graph_template['name']);?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Data Query Graph Template');?>'>
			<input type='hidden' id='snmp_query_graph_id' value='<?php print get_request_var('id');?>'>
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
				url: 'data_queries.php?action=item_remove',
				funcEnd: 'removeDataQueryItemFinalize';
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				snmp_query_id: <?php print get_request_var('snmp_query_id');?>,
				id: <?php print get_request_var('id');?>
			}

			postUrl(options, data);

		});
	});

	function removeDataQueryItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'data_queries.php?action=edit&id=<?php print get_request_var('snmp_query_id');?>'})
	}
	</script>
	<?php
}

function data_query_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE
		FROM snmp_query_graph
		WHERE id = ?',
		array(get_request_var('id')));

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd
		WHERE snmp_query_graph_id = ?',
		array(get_request_var('id')));

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd_sv
		WHERE snmp_query_graph_id = ?',
		array(get_request_var('id')));

	db_execute_prepared('DELETE
		FROM snmp_query_graph_sv
		WHERE snmp_query_graph_id = ?',
		array(get_request_var('id')));
}

function data_query_item_edit() {
	global $fields_data_query_item_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$snmp_query_item = db_fetch_row_prepared('SELECT *
			FROM snmp_query_graph
			WHERE id = ?',
			array(get_request_var('id')));
	}

	$snmp_query   = db_fetch_row_prepared('SELECT name, xml_path
		FROM snmp_query
		WHERE id = ?',
		array(get_request_var('snmp_query_id')));

	if (cacti_sizeof($snmp_query)) {
		$header_label = __esc('Associated Graph/Data Templates [edit: %s]', $snmp_query['name']);
	} else {
		$header_label = __('Associated Graph/Data Templates [new]');
	}

	form_start('data_queries.php', 'data_queries');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_data_query_item_edit, (isset($snmp_query_item) ? $snmp_query_item : array()), $_REQUEST)
		)
	);

	html_end_box(true, true);

	?>
	<script type='text/javascript'>
	function assignDataQueryGraphName(init) {
		if (init == false || $('#name').val() == '') {
			$('#name').val($('#graph_template_id').children(':selected').text());
		}
	}

	$(function() {
		$('form#data_queries').find('#graph_template_id').change(function() {
			assignDataQueryGraphName(false);
		});
		assignDataQueryGraphName(true);
	});

	</script>
	<?php

	if (!empty($snmp_query_item['id'])) {
		html_start_box(__('Associated Data Templates'), '100%', '', '3', 'center', '');

		$data_templates = db_fetch_assoc_prepared('SELECT data_template.id, data_template.name
			FROM (data_template, data_template_rrd, graph_templates_item)
			WHERE graph_templates_item.task_item_id = data_template_rrd.id
			AND data_template_rrd.data_template_id = data_template.id
			AND data_template_rrd.local_data_id = 0
			AND graph_templates_item.local_graph_id = 0
			AND graph_templates_item.graph_template_id = ?
			GROUP BY data_template.id
			ORDER BY data_template.name', array($snmp_query_item['graph_template_id']));

		$i = 0;

		if (cacti_sizeof($data_templates)) {
			foreach ($data_templates as $data_template) {
				print "<tr class='tableHeader'>
					<th class='tableSubHeaderColumn'>" . __esc('Data Template - %s', $data_template['name']) . '</th>
				</tr>';

				$data_template_rrds = db_fetch_assoc_prepared('SELECT dtr.id, dtr.data_source_name,
					sqgr.snmp_field_name, sqgr.snmp_query_graph_id
					FROM data_template_rrd AS dtr
					LEFT JOIN snmp_query_graph_rrd AS sqgr
					ON sqgr.data_template_rrd_id = dtr.id
					AND sqgr.snmp_query_graph_id = ?
					AND sqgr.data_template_id = ?
					WHERE dtr.data_template_id = ?
					AND dtr.local_data_id = 0
					ORDER BY dtr.data_source_name',
					array(get_request_var('id'), $data_template['id'], $data_template['id']));

				$i = 0;

				if (cacti_sizeof($data_template_rrds)) {
					foreach ($data_template_rrds as $data_template_rrd) {
						if (empty($data_template_rrd['snmp_query_graph_id'])) {
							$old_value = '';
						} else {
							$old_value = 'on';
						}

						form_alternate_row();
						?>
						<td>
							<table>
								<tr>
									<td style='width:200px;'>
										<?php print __('Data Source');?>
									</td>
									<td style='width:200px;'>
										<?php print $data_template_rrd['data_source_name'];?>
									</td>
									<td>
										<?php
										$snmp_queries = get_data_query_array(get_request_var('snmp_query_id'));
						$xml_outputs      = array();

						if (isset($snmp_queries['fields']) && cacti_sizeof($snmp_queries['fields'])) {
							foreach ($snmp_queries['fields'] as $field_name => $field_array) {
								if ($field_array['direction'] == 'output' || $field_array['direction'] == 'input-output') {
									$xml_outputs[$field_name] = $field_name . ' (' . $field_array['name'] . ')';
								}
							}
						}

						form_dropdown('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_snmp_field_output',$xml_outputs,'','',empty($data_template_rrd['snmp_field_name'])?$data_template_rrd['data_source_name']:$data_template_rrd['snmp_field_name'],'','');?>
									</td>
									<td class='right'>
										<?php form_checkbox('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_check', $old_value, '', '', '', get_request_var('id'), '', __('If this Graph Template requires the Data Template Data Source to the left, select the correct XML output column and then to enable the mapping either check or toggle here.'));
						print '<br>';?>
									</td>
								</tr>
							</table>
						</td>
						<?php
						form_end_row();
					}
				}
			}
		}

		html_end_box();

		html_start_box(__('Suggested Values - Graphs'), '100%', '', '3', 'center', '');

		/* suggested values for graphs templates */
		$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, snmp_query_graph_id, id
			FROM snmp_query_graph_sv
			WHERE snmp_query_graph_id = ?
			ORDER BY field_name, sequence',
			array(get_request_var('id')));

		html_header(array(
			array('display' => __('Name'), 'align' => 'left'),
			array('display' => __('Order'), 'align' => 'center'),
			array('display' => __('Equation'), 'align' => 'left')
		), 2);

		$i            = 0;
		$total_values = cacti_sizeof($suggested_values);

		if ($total_values) {
			foreach ($suggested_values as $suggested_value) {
				data_query_sv_check_sequences('gr', $suggested_value['snmp_query_graph_id'], $suggested_value['field_name']);

				form_alternate_row();

				$show_up   = false;
				$show_down = false;

				// Handle up true
				if ($i != 0) {
					$show_up = true;
				}

				// Handle down true
				if ($total_values > 1 && $i < $total_values - 1) {
					$show_down = true;
				}

				?>
				<td class='left'>
					<?php print html_escape($suggested_value['field_name']);?>
				</td>
				<td class='center'>
					<?php if ($show_down) {?>
					<a class='remover fa fa-caret-down moveArrow' title='<?php print __('Move Down');?>' href='<?php print html_escape('data_queries.php?action=item_movedown_gsv&snmp_query_graph_id=' . get_request_var('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id') . '&field_name=' . $suggested_value['field_name']);?>'></a>
					<?php } else {?>
					<span class='moveArrowNone'></span>
					<?php } ?>
					<?php if ($show_up) {?>
					<a class='remover fa fa-caret-up moveArrow' title='<?php print __('Move Up');?>' href='<?php print html_escape('data_queries.php?action=item_moveup_gsv&snmp_query_graph_id=' . get_request_var('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id') . '&field_name=' . $suggested_value['field_name']);?>'></a>
					<?php } else {?>
					<span class='moveArrowNone'></span>
					<?php } ?>
				</td>
				<td class='left'>
					<?php print html_escape($suggested_value['text']);?>
				</td>
				<td class='right'>
					<a class='remover deleteMarker fa fa-times' title='<?php print html_escape(__('Delete'));?>' href='<?php print html_escape('data_queries.php?action=item_remove_gsv&snmp_query_graph_id=' . get_request_var('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id'));?>'></a>
				</td>
				<?php

				form_end_row();

				$i++;
			}
		} else {
			print "<tr><td colspan='4'><em>" . __('No Suggested Values Found') . '</em></td></tr>';
		}

		form_alternate_row();
		?>
		<td colspan='4'>
			<table>
				<tr>
					<td class='nowrap'>
						<?php print __('Field Name');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='svg_field' size='15'>
					</td>
					<td class='nowrap'>
						<?php print __('Suggested Value');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='svg_text' size='60'>
					</td>
					<td>
						<input type='button' class='ui-button ui-corner-all ui-widget' id='svg_x' name='svg_x' value='<?php print __esc('Add');?>' title='<?php print __('Add Graph Title Suggested Name');?>'>
					</td>
				</tr>
			</table>
		</td>
		<?php
		form_end_row();

		html_end_box();

		html_start_box(__('Suggested Values - Data Sources'), '100%', '', '3', 'center', '');

		/* suggested values for data templates */
		if (cacti_sizeof($data_templates)) {
			foreach ($data_templates as $data_template) {
				$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, snmp_query_graph_id, id
					FROM snmp_query_graph_rrd_sv
					WHERE snmp_query_graph_id = ?
					AND data_template_id = ?
					ORDER BY field_name, sequence', array(get_request_var('id'), $data_template['id']));

				$name = db_fetch_cell_prepared('SELECT name
					FROM data_template
					WHERE id = ?',
					array($data_template['id']));

				print "<tr class='tableHeader'><td colspan='4'>" . html_escape($name) . '</td></tr><tr>';

				html_header(array(
					array('display' => __('Name'), 'align' => 'left'),
					array('display' => __('Order'), 'align' => 'center'),
					array('display' => __('Equation'), 'align' => 'left')
				), 2);

				$i            = 0;
				$total_values = cacti_sizeof($suggested_values);

				if ($total_values) {
					$prev_name = '';

					foreach ($suggested_values as $suggested_value) {
						data_query_sv_check_sequences('ds', $suggested_value['snmp_query_graph_id'], $suggested_value['field_name']);

						form_alternate_row();

						$show_up   = false;
						$show_down = false;

						// Handle up true
						if ($i != 0) {
							$show_up = true;
						}

						// Handle down true
						if ($total_values > 1 && $i < $total_values - 1) {
							$show_down = true;
						}

						?>
						<td class='left'>
							<?php print html_escape($suggested_value['field_name']);?>
						</td>
						<td class='center'>
							<?php if ($show_down) {?>
							<a class='remover fa fa-caret-down moveArrow' title='<?php print __('Move Down');?>' href='<?php print html_escape('data_queries.php?action=item_movedown_dssv&snmp_query_graph_id=' . get_request_var('id') . '&id='. $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id') . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'></a>
							<?php } else {?>
							<span class='moveArrowNone'></span>
							<?php } ?>
							<?php if ($show_up) {?>
							<a class='remover fa fa-caret-up moveArrow' title='<?php print __('Move Up');?>' href='<?php print html_escape('data_queries.php?action=item_moveup_dssv&snmp_query_graph_id=' . get_request_var('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id') . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'></a>
							<?php } else {?>
							<span class='moveArrowNone'></span>
							<?php } ?>
						</td>
						<td class='nowrap left'>
							<?php print html_escape($suggested_value['text']);?>
						</td>
						<td class='right'>
							<a class='remover deleteMarker fa fa-times' title='<?php print __('Delete');?>' href='<?php print html_escape('data_queries.php?action=item_remove_dssv&snmp_query_graph_id=' . get_request_var('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . get_request_var('snmp_query_id') . '&data_template_id=' . $data_template['id']);?>'></a>
						</td>
						<?php

						form_end_row();

						$prev_name = $suggested_value['field_name'];
						$i++;
					}
				} else {
					print "<tr><td colspan='4'><em>" . __('No Suggested Values Found') . '</em></td></tr>';
				}

				form_alternate_row();
				?>
				<td colspan='4'>
					<table>
						<tr>
							<td class='nowrap'>
								<?php print __('Field Name');?>
							</td>
							<td>
								<input type='text' class='svds_field ui-state-default ui-corner-all' id='svds_<?php print $data_template['id'];?>_field' size='15'>
							</td>
							<td class='nowrap'>
								<?php print __('Suggested Value');?>
							</td>
							<td>
								<input type='text' class='svds_text ui-state-default ui-corner-all' id='svds_<?php print $data_template['id'];?>_text' size='60'>
							</td>
							<td>
								<input type='button' class='svds_x ui-button ui-corner-all ui-widget' id='svds_<?php print $data_template['id'];?>_x' value='<?php print __esc('Add');?>' title='<?php print __('Add Data Source Name Suggested Name');?>'>
							</td>
						</tr>
					</table>
				</td>
				<?php
				form_end_row();
			}
		}

		html_end_box();
	}

	if (isset($snmp_query_item['graph_template_id'])) {
		$item = $snmp_query_item['graph_template_id'];
	} else {
		$item = 0;
	}

	?>
	<script type='text/javascript'>
	var graph_template_id_prev=<?php print $item;?>;

	$('.remover').click(function(event) {
		event.preventDefault();
		href=$(this).attr('href');
		$.get(href)
			.done(function(data) {
				$('form[action="data_queries.php"]').unbind();
				$('#main').html(data);
				applySkin();
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	});

	$('input[id="svg_x"]').click(function() {
		var options = {
			url:'data_queries.php'
		}

		var data = {
			graph_template_id_prev:graph_template_id_prev,
			action: 'save',
			save_component_svg: '1',
			id: $('#id').val(),
			graph_template_id: $('#graph_template_id').val(),
			snmp_query_id: $('#snmp_query_id').val(),
			svg_field: $('#svg_field').val(),
			svg_text: $('#svg_text').val(),
			svg_x:'Add',
			__csrf_magic: csrfMagicToken
		}

		postUrl(options, data);
	});

	$('input.svds_x').click(function() {
		// Get the dsid value
		var id    = $(this).attr('id');
		var parts = id.split('_');
		var sid   = parts[1];

		if (sid != '') {
			var data = {
				action: 'save',
				save_component_svds: '1',
				id: $('#id').val(),
				graph_template_id: $('#graph_template_id').val(),
				snmp_query_id: $('#snmp_query_id').val(),
				'svds_field': $('#svds_'+sid+'_field').val(),
				'svds_text': $('#svds_'+sid+'_text').val(),
				'svds_id': sid,
				header: 'false',
				__csrf_magic: csrfMagicToken
			};

			postURL({url:'data_queries.php'}, data);
		}
	});
	</script>
	<?php

	form_save_button('data_queries.php?action=edit&id=' . get_request_var('snmp_query_id'), 'return');
}

/* ---------------------
	Data Query Functions
   --------------------- */

function data_query_remove($id) {
	$snmp_query_graph = db_fetch_assoc_prepared('SELECT id
		FROM snmp_query_graph
		WHERE snmp_query_id = ?',
		array($id));

	if (cacti_sizeof($snmp_query_graph)) {
		foreach ($snmp_query_graph as $item) {
			db_execute_prepared('DELETE
				FROM snmp_query_graph_rrd
				WHERE snmp_query_graph_id = ?',
				array($item['id']));
		}
	}

	db_execute_prepared('DELETE
		FROM snmp_query
		WHERE id = ?',
		array($id));

	db_execute_prepared('DELETE
		FROM snmp_query_graph
		WHERE snmp_query_id = ?',
		array($id));

	db_execute_prepared('DELETE
		FROM host_template_snmp_query
		WHERE snmp_query_id = ?',
		array($id));

	db_execute_prepared('DELETE
		FROM host_snmp_query
		WHERE snmp_query_id = ?',
		array($id));

	db_execute_prepared('DELETE
		FROM host_snmp_cache
		WHERE snmp_query_id = ?',
		array($id));

	update_replication_crc(0, 'poller_replicate_snmp_query_crc');
}

function data_query_edit() {
	global $fields_data_query_edit, $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$snmp_query   = db_fetch_row_prepared('SELECT * FROM snmp_query WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('Data Queries [edit: %s]', $snmp_query['name']);
	} else {
		$header_label = __('Data Queries [new]');
	}

	form_start('data_queries.php', 'data_queries');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_data_query_edit, (isset($snmp_query) ? $snmp_query : array()))
		)
	);

	html_end_box(false, true);

	if (!empty($snmp_query['id'])) {
		$search       = array('<path_cacti>', '<path_snmpget>', '<path_php_binary>');
		$replace      = array(CACTI_PATH_BASE, read_config_option('path_snmpget'), read_config_option('path_php_binary'));
		$xml_filename = str_replace($search, $replace, $snmp_query['xml_path']);

		if ((file_exists($xml_filename)) && (is_file($xml_filename))) {
			$text            = "<span class='deviceUp'>" . __('Successfully located XML file') . '</span>';
			$xml_file_exists = true;
		} else {
			$text            = "<span class='deviceDown'>" . __('Could not locate XML file.') . '</span>';
			$xml_file_exists = false;
		}

		html_start_box('', '100%', '', '3', 'center', '');
		print "<tr class='tableRow debug'><td>$text</td></tr>";
		html_end_box(false);

		html_start_box(__('Associated Graph Templates'), '100%', '', '3', 'center', 'data_queries.php?action=item_edit&snmp_query_id=' . $snmp_query['id']);

		print "<tr class='tableHeader'>
			<th class='tableSubHeaderColumn'>" . __('Name') . "</th>
			<th class='tableSubHeaderColumn'>" . __('Graph Template Name') . "</th>
			<th class='tableSubHeaderColumn right'>" . __('Graphs Using') . "</th>
			<th class='tableSubHeaderColumn right'>" . __('Mapping ID') . "</th>
			<th class='tableSubHeaderColumn right' style='width:60px;'>" . __('Action') . '</th>
		</tr>';

		$snmp_query_graphs = db_fetch_assoc_prepared('SELECT sqg.id,
			gt.name AS graph_template_name, sqg.name, COUNT(gl.id) AS graphs
			FROM snmp_query_graph AS sqg
			LEFT JOIN graph_templates AS gt
			ON sqg.graph_template_id = gt.id
			LEFT JOIN graph_local AS gl
			ON gl.snmp_query_graph_id = sqg.id
			AND gl.graph_template_id = sqg.graph_template_id
			WHERE sqg.snmp_query_id = ?
			GROUP BY sqg.id
			ORDER BY sqg.name',
			array($snmp_query['id']));

		if (cacti_sizeof($snmp_query_graphs)) {
			foreach ($snmp_query_graphs as $snmp_query_graph) {
				form_alternate_row();
				?>
					<td>
					<?php if ($xml_file_exists) {?>
						<a class='linkEditMain' href="<?php print html_escape('data_queries.php?action=item_edit&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id']);?>"><?php print html_escape($snmp_query_graph['name']);?></a>
					<?php } else { ?>
						<span class='noLinkEditMain' title='<?php print __esc('Association Read Only until XML file located');?>'><?php print html_escape($snmp_query_graph['name']);?></span>
					<?php } ?>
					</td>
					<td>
						<?php print html_escape($snmp_query_graph['graph_template_name']);?>
					</td>
					<td class='right'>
						<?php print number_format_i18n($snmp_query_graph['graphs'], '-1');?>
					</td>
					<td class='right'>
						<?php print $snmp_query_graph['id'];?>
					</td><?php if ($snmp_query_graph['graphs'] == 0) {?>
					<td class='right'>
						<a class='delete deleteMarker fa fa-times' title='<?php print __('Delete');?>' href='<?php print html_escape('data_queries.php?action=item_remove_confirm&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id']);?>'></a>
					</td>
					<?php } else { ?>
					<td class='right'>
						<a class='deleteMarkerDisabled fa fa-times' title='<?php print __('Mapped Graph Templates with Graphs are read only');?>' href='#'></a>
					</td>
					<?php } ?>
				</tr>
				<?php
			}
		} else {
			print "<tr class='tableRow'><td><em>" . __('No Graph Templates Defined.') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_queries.php', 'return');

	?>
	<script type='text/javascript'>

	var snmp_query_id = '<?php print isset($snmp_query['id']) ? $snmp_query['id']:'0';?>';
	var snmp_query_graph_id = '<?php print isset($snmp_query_graph['id']) ? $snmp_query_graph['id']:'0';?>';

	$(function() {
		$('.cdialog').remove();
		$('#main').append("<div id='cdialog' class='cdialog'></div>");

		$('.noLinkEditMain').tooltip();

		$('.delete').click(function (event) {
			event.preventDefault();

			request = $(this).attr('href');
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').click(function(data) {
						$.post('data_queries.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							snmp_query_id: snmp_query_id,
							id: $('#snmp_query_graph_id').val()
						}, function(data) {
							$('#cdialog').dialog('close');
							loadUrl({url:'data_queries.php?action=edit&id='+snmp_query_id});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __('Delete Associated Graph');?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');
	});

	</script>
	<?php
}

function data_query() {
	global $dq_actions, $item_rows;

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
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_dq');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Data Queries'), '100%', '', '3', 'center', 'data_queries.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_data_queries' method='get' action='data_queries.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Data Queries');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
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
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' name='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'data_queries.php';
			strURL += '?filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'data_queries.php?clear=1';
			loadUrl({url:strURL})
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_data_queries').submit(function(event) {
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
		$sql_where = 'WHERE (sq.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR di.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM snmp_query AS sq
		INNER JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$snmp_queries = db_fetch_assoc("SELECT sq.id, sq.name,
		di.name AS data_input_method,
		COUNT(DISTINCT gl.id) AS graphs,
		COUNT(DISTINCT sqg.graph_template_id) AS templates
		FROM snmp_query AS sq
		LEFT JOIN snmp_query_graph AS sqg
		ON sq.id=sqg.snmp_query_id
		LEFT JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		LEFT JOIN graph_local AS gl
		ON gl.snmp_query_id=sq.id
		$sql_where
		GROUP BY sq.id
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Data Query Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Data Query.')
		),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal ID for this Graph Template.  Useful when performing automation or debugging.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Data Queries that are in use cannot be Deleted. In use is defined as being referenced by either a Graph or a Graph Template.')
		),
		'graphs' => array(
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs using this Data Query.')
		),
		'templates' => array(
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs Templates using this Data Query.')
		),
		'data_input_method' => array(
			'display' => __('Data Input Method'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Data Input Method used to collect data for Data Sources associated with this Data Query.')
		)
	);

	$nav = html_nav_bar('data_queries.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Data Queries'), 'page', 'main');

	form_start('data_queries.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($snmp_queries)) {
		foreach ($snmp_queries as $snmp_query) {
			if ($snmp_query['graphs'] == 0 && $snmp_query['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $snmp_query['id'], true, $disabled);
			form_selectable_cell(filter_value($snmp_query['name'], get_request_var('filter'), 'data_queries.php?action=edit&id=' . $snmp_query['id']), $snmp_query['id']);
			form_selectable_cell($snmp_query['id'], $snmp_query['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $snmp_query['id'], '', 'right');
			form_selectable_cell(number_format_i18n($snmp_query['graphs'], '-1'), $snmp_query['id'], '', 'right');
			form_selectable_cell(number_format_i18n($snmp_query['templates'], '-1'), $snmp_query['id'], '', 'right');
			form_selectable_cell(filter_value($snmp_query['data_input_method'], get_request_var('filter')), $snmp_query['id'], '', 'right');
			form_checkbox_cell($snmp_query['name'], $snmp_query['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Queries Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($snmp_queries)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($dq_actions);

	form_end();
}
