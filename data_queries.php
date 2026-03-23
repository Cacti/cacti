<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Duplicate')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_moveup_dssv':
		data_query_item_moveup_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_movedown_dssv':
		data_query_item_movedown_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_remove_dssv':
		data_query_item_remove_dssv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_moveup_gsv':
		data_query_item_moveup_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_movedown_gsv':
		data_query_item_movedown_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_remove_gsv':
		data_query_item_remove_gsv();

		header('Location: data_queries.php?action=item_edit&id=' . gfrv('snmp_query_graph_id') . '&snmp_query_id=' . gfrv('snmp_query_id'));

		break;
	case 'item_remove_confirm':
		data_query_item_remove_confirm();

		break;
	case 'item_remove':
		data_query_item_remove();

		header('Location: data_queries.php?action=edit&id=' . gfrv('snmp_query_id'));

		break;
	case 'item_edit':
		data_query_item_edit();

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

function form_save() : void {
	if (isrv('save_component_snmp_query')) {
		gfrv('id');
		gfrv('data_input_id');

		$save['id']            = grv('id');
		$save['hash']          = get_hash_data_query(gnrv('id'));
		$save['name']          = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['description']   = form_input_validate(gnrv('description'), 'description', '', true, 3);
		$save['xml_path']      = form_input_validate(trim(gnrv('xml_path')), 'xml_path', '', false, 3);
		$save['data_input_id'] = grv('data_input_id');

		// Detect changing input id
		if (!empty($save['id'])) {
			$previous_input_id = db_fetch_cell_prepared('SELECT data_input_id
				FROM snmp_query
				WHERE id = ?',
				[$save['id']]);
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

		header('Location: data_queries.php?action=edit&id=' . (empty($snmp_query_id) ? grv('id') : $snmp_query_id));
	} elseif (isrv('save_component_snmp_query_item') && !isrv('svg_x') && !isrv('svds_x')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('snmp_query_id');
		gfrv('graph_template_id');
		// ====================================================

		$save['id']                = grv('id');
		$save['hash']              = get_hash_data_query(gnrv('id'), 'data_query_graph');
		$save['snmp_query_id']     = grv('snmp_query_id');
		$save['name']              = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['graph_template_id'] = grv('graph_template_id');

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
					if (gnrv('graph_template_id') != gnrv('graph_template_id_prev')) {
						db_execute_prepared('DELETE
							FROM snmp_query_graph_rrd_sv
							WHERE snmp_query_graph_id = ?',
							[$snmp_query_graph_id]);

						db_execute_prepared('DELETE
							FROM snmp_query_graph_sv
							WHERE snmp_query_graph_id = ?',
							[$snmp_query_graph_id]);
					}

					db_execute_prepared('DELETE
						FROM snmp_query_graph_rrd
						WHERE snmp_query_graph_id = ?',
						[$snmp_query_graph_id]);

					foreach ($_POST as $var => $val) {
						if (preg_match('/^dsdt_([0-9]+)_([0-9]+)_check/i', $var)) {
							$data_template_id     = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', '\\1', $var);
							$data_template_rrd_id = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', '\\2', $var);
							// ================= input validation =================
							input_validate_input_number($data_template_id, 'dsdt->data_template_id');
							input_validate_input_number($data_template_rrd_id, 'dsdt->data_template_rrd_id');
							// ====================================================

							db_execute_prepared('REPLACE INTO snmp_query_graph_rrd
								(snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name)
								VALUES (?, ?, ?, ?)',
								[
									$snmp_query_graph_id,
									$data_template_id,
									$data_template_rrd_id,
									gnrv('dsdt_' .
									$data_template_id . '_' .
									$data_template_rrd_id . '_snmp_field_output')
								]
							);
						}
					}
				} else {
					raise_message(2);
				}
			}
		}

		header('Location: data_queries.php?action=item_edit&id=' . (empty($snmp_query_graph_id) ? grv('id') : $snmp_query_graph_id) . '&snmp_query_id=' . grv('snmp_query_id'));
	} elseif (isrv('save_component_svg')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('snmp_query_id');
		gfrv('graph_template_id');
		// ====================================================

		if (ierv('svg_text')) {
			raise_message(39);
			header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));

			return;
		}

		if (ierv('svg_field')) {
			raise_message(38);
			header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));

			return;
		}

		// suggested values -- graph templates
		$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_sv', 'snmp_query_graph_id = ' . gfrv('id') . ' AND field_name = ' . db_qstr(gnrv('svg_field')));

		$hash = get_hash_data_query(0, 'data_query_sv_graph');

		db_execute_prepared('INSERT INTO snmp_query_graph_sv
			(hash, snmp_query_graph_id, sequence, field_name, text)
			VALUES (?, ?, ?, ?, ?)',
			[
				$hash,
				grv('id'),
				$sequence,
				gnrv('svg_field'),
				gnrv('svg_text')
			]
		);

		db_execute_prepared('UPDATE snmp_query
			SET last_updated = NOW()
			WHERE id = ?',
			[grv('id')]);

		clear_messages();

		header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));
	} elseif (isrv('save_component_svds')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('svds_id');
		gfrv('snmp_query_id');
		gfrv('graph_template_id');
		// ====================================================

		if (isrv('svds_id')) {
			$svds_id = grv('svds_id');

			if (ierv('svds_text')) {
				raise_message(39);
				header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));

				return;
			}

			if (ierv('svds_field')) {
				raise_message(38);
				header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));

				return;
			}

			$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_id = ' . grv('id') . ' AND data_template_id = ' . $svds_id . ' AND field_name = ' . db_qstr(gnrv('svds_field')));

			$hash = get_hash_data_query(0, 'data_query_sv_data_source');

			db_execute_prepared('INSERT INTO snmp_query_graph_rrd_sv
				(hash, snmp_query_graph_id, data_template_id, sequence, field_name, text)
				VALUES (?, ?, ?, ?, ?, ?)',
				[
					$hash,
					grv('id'),
					$svds_id,
					$sequence,
					gnrv('svds_field'),
					gnrv('svds_text')
				]
			);

			db_execute_prepared('UPDATE snmp_query
				SET last_updated = NOW()
				WHERE id = ?',
				[grv('id')]);

			clear_messages();

			header('Location: data_queries.php?action=item_edit&id=' . grv('id') . '&snmp_query_id=' . grv('snmp_query_id'));
		}
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					data_query_remove($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '2') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					data_query_duplicate($selected_items[$i], gnrv('name_format'));
				}
			}
		} else {
			raise_message(40);
		}

		header('Location: data_queries.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the data queries and process them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$name = db_fetch_cell_prepared('SELECT name
					FROM snmp_query
					WHERE id = ?',
					[$matches[1]]);

				$ilist .= '<li>' . htmle($name) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'data_queries.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Data Query.'),
					'pmessage' => __('Click \'Continue\' to Delete following Data Queries.'),
					'scont'    => __('Delete Data Query'),
					'pcont'    => __('Delete Data Queries')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Data Query.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Data Queries.'),
					'scont'    => __('Duplicate Data Query'),
					'pcont'    => __('Duplicate Data Queries'),
					'extra'    => [
						'name_format' => [
							'method'  => 'textbox',
							'title'   => __('Name Format'),
							'default' => '<dataquery_name> (1)',
							'width'   => 25
						]
					]
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function data_query_item_movedown_gsv() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_graph_id');
	// ====================================================

	move_item_down('snmp_query_graph_sv', grv('id'), ['snmp_query_graph_id' => grv('snmp_query_graph_id'), 'field_name' => gnrv('field_name')]);
}

function data_query_item_moveup_gsv() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_graph_id');
	// ====================================================

	move_item_up('snmp_query_graph_sv', grv('id'), ['snmp_query_graph_id' => grv('snmp_query_graph_id'), 'field_name' => gnrv('field_name')]);
}

function data_query_item_remove_gsv() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM snmp_query_graph_sv
		WHERE id = ?',
		[grv('id')]);
}

function data_query_item_movedown_dssv() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('data_template_id');
	gfrv('snmp_query_graph_id');
	// ====================================================

	move_item_down('snmp_query_graph_rrd_sv', grv('id'), ['data_template_id' => grv('data_template_id'), 'snmp_query_graph_id' => grv('snmp_query_graph_id'), 'field_name' => gnrv('field_name')]);
}

function data_query_item_moveup_dssv() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('data_template_id');
	gfrv('snmp_query_graph_id');
	// ====================================================

	move_item_up('snmp_query_graph_rrd_sv', grv('id'), ['data_template_id' => grv('data_template_id'), 'snmp_query_graph_id' => grv('snmp_query_graph_id'), 'field_name' => gnrv('field_name')]);
}

function data_query_sv_check_sequences(string $type, int $snmp_query_graph_id, string $field_name) : bool {
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
		[$field_name, $snmp_query_graph_id]);

	$dup_seq = db_fetch_cell_prepared("SELECT SUM(count)
		FROM (
			SELECT sequence, COUNT(sequence) AS count
			FROM $table
			WHERE field_name = ?
			AND snmp_query_graph_id = ?
			GROUP BY sequence
		) AS t
		WHERE t.count > 1",
		[$field_name, $snmp_query_graph_id]);

	// report any bad or duplicate sequences to the log for reporting purposes
	if ($bad_seq > 0) {
		cacti_log('WARN: Found ' . $bad_seq . " Bad Sequences in $table Table", false, 'WEBUI', POLLER_VERBOSITY_HIGH);
	}

	if ($dup_seq > 0) {
		cacti_log('WARN: Found ' . $dup_seq . " Duplicated Sequences in $table Table", false, 'WEBUI', POLLER_VERBOSITY_HIGH);
	}

	if ($bad_seq > 0 || $dup_seq > 0) {
		// resequence the list so it has no gaps, and 0 values will appear at the top
		// since that's where they would have been displayed
		db_execute_prepared("SET @seq = 0;
			UPDATE $table
			SET sequence = (@seq:=@seq+1)
			WHERE field_name = ?
			AND snmp_query_graph_id = ?
			ORDER BY sequence, id;",
			[$field_name, $snmp_query_graph_id]);
	}

	return true;
}

function data_query_item_remove_dssv() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd_sv
		WHERE id = ?',
		[grv('id')]);
}

function data_query_item_remove_confirm() : void {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_id');
	// ====================================================

	form_start('data_queries.php?action=edit&id' . grv('snmp_query_id'));

	html_start_box('', '100%', false, 3, 'center', '');

	$graph_template = db_fetch_row_prepared('SELECT *
		FROM snmp_query_graph
		WHERE id = ?',
		[grv('id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Query Graph Association.'); ?></p>
			<p><?php print __esc('Graph Name: %s', $graph_template['name']); ?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' onClick='$("#cdialog").dialog("close");' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' name='continue' title='<?php print __esc('Remove Data Query Graph Template'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='snmp_query_graph_id' value='<?php print grv('id'); ?>'>
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
				funcEnd: 'removeDataQueryItemFinalize',
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				snmp_query_id: <?php print grv('snmp_query_id'); ?>,
				id: <?php print grv('id'); ?>
			}

			postUrl(options, data);

		});
	});

	function removeDataQueryItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'data_queries.php?action=edit&id=<?php print grv('snmp_query_id'); ?>'})
	}
	</script>
	<?php
}

function data_query_item_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE
		FROM snmp_query_graph
		WHERE id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd
		WHERE snmp_query_graph_id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE
		FROM snmp_query_graph_rrd_sv
		WHERE snmp_query_graph_id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE
		FROM snmp_query_graph_sv
		WHERE snmp_query_graph_id = ?',
		[grv('id')]);
}

function data_query_item_edit() : void {
	global $fields_data_query_item_edit;

	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_id');
	// ====================================================

	if (!ierv('id')) {
		$snmp_query_item = db_fetch_row_prepared('SELECT *
			FROM snmp_query_graph
			WHERE id = ?',
			[grv('id')]);
	}

	$snmp_query   = db_fetch_row_prepared('SELECT name, xml_path
		FROM snmp_query
		WHERE id = ?',
		[grv('snmp_query_id')]);

	if (cacti_sizeof($snmp_query)) {
		$header_label = __esc('Associated Graph/Data Templates [edit: %s]', $snmp_query['name']);
	} else {
		$header_label = __('Associated Graph/Data Templates [new]');
	}

	form_start('data_queries.php', 'data_queries');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_data_query_item_edit, (isset($snmp_query_item) ? $snmp_query_item : []), $_REQUEST)
		]
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
		html_start_box(__('Associated Data Templates'), '100%', false, 3, 'center', '');

		$data_templates = db_fetch_assoc_prepared('SELECT data_template.id, data_template.name
			FROM (data_template, data_template_rrd, graph_templates_item)
			WHERE graph_templates_item.task_item_id = data_template_rrd.id
			AND data_template_rrd.data_template_id = data_template.id
			AND data_template_rrd.local_data_id = 0
			AND graph_templates_item.local_graph_id = 0
			AND graph_templates_item.graph_template_id = ?
			GROUP BY data_template.id
			ORDER BY data_template.name', [$snmp_query_item['graph_template_id']]);

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
					[grv('id'), $data_template['id'], $data_template['id']]);

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
										<?php print __('Data Source'); ?>
									</td>
									<td style='width:200px;'>
										<?php print $data_template_rrd['data_source_name']; ?>
									</td>
									<td>
										<?php
										$snmp_queries = get_data_query_array(grv('snmp_query_id'));
						$xml_outputs      = [];

						if (isset($snmp_queries['fields']) && cacti_sizeof($snmp_queries['fields'])) {
							foreach ($snmp_queries['fields'] as $field_name => $field_array) {
								if ($field_array['direction'] == 'output' || $field_array['direction'] == 'input-output') {
									$xml_outputs[$field_name] = $field_name . ' (' . $field_array['name'] . ')';
								}
							}
						}

						form_dropdown('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_snmp_field_output',$xml_outputs,'','',empty($data_template_rrd['snmp_field_name']) ? $data_template_rrd['data_source_name'] : $data_template_rrd['snmp_field_name'],'',''); ?>
									</td>
									<td class='right'>
										<?php form_checkbox('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_check', $old_value, '', '', '', grv('id'), '', __('If this Graph Template requires the Data Template Data Source to the left, select the correct XML output column and then to enable the mapping either check or toggle here.'));
						print '<br>'; ?>
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

		html_start_box(__('Suggested Values - Graphs'), '100%', false, 3, 'center', '');

		// suggested values for graphs templates
		$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, snmp_query_graph_id, id
			FROM snmp_query_graph_sv
			WHERE snmp_query_graph_id = ?
			ORDER BY field_name, sequence',
			[grv('id')]);

		html_header([
			['display' => __('Name'), 'align' => 'left'],
			['display' => __('Order'), 'align' => 'center'],
			['display' => __('Equation'), 'align' => 'left']
		], 2);

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
					<?php print htmle($suggested_value['field_name']); ?>
				</td>
				<td class='center'>
					<?php if ($show_down) {?>
					<a class='remover ti ti-caret-down-filled moveArrow' title='<?php print __('Move Down'); ?>' href='<?php print htmle('data_queries.php?action=item_movedown_gsv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id') . '&field_name=' . $suggested_value['field_name']); ?>'></a>
					<?php } else {?>
					<span class='moveArrowNone'></span>
					<?php } ?>
					<?php if ($show_up) {?>
					<a class='remover ti ti-caret-up-filled moveArrow' title='<?php print __('Move Up'); ?>' href='<?php print htmle('data_queries.php?action=item_moveup_gsv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id') . '&field_name=' . $suggested_value['field_name']); ?>'></a>
					<?php } else {?>
					<span class='moveArrowNone'></span>
					<?php } ?>
				</td>
				<td class='left'>
					<?php print htmle($suggested_value['text']); ?>
				</td>
				<td class='right'>
					<a class='remover deleteMarker ti ti-x' title='<?php print htmle(__('Delete')); ?>' href='<?php print htmle('data_queries.php?action=item_remove_gsv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id')); ?>'></a>
				</td>
				<?php

				form_end_row();

				$i++;
			}
		} else {
			print "<tr class='tableRow odd'><td colspan='4'><em>" . __('No Suggested Values Found') . '</em></td></tr>';
		}

		form_alternate_row();
		?>
		<td colspan='4'>
			<table>
				<tr>
					<td class='nowrap'>
						<?php print __('Field Name'); ?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='svg_field' size='15'>
					</td>
					<td class='nowrap'>
						<?php print __('Suggested Value'); ?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='svg_text' size='60'>
					</td>
					<td>
						<button type='button' class='ui-button ui-corner-all ui-widget' id='svg_x' name='svg_x' value='add' title='<?php print __('Add Graph Title Suggested Name'); ?>'><?php print __esc('Add'); ?></button>
					</td>
				</tr>
			</table>
		</td>
		<?php
		form_end_row();

		html_end_box();

		html_start_box(__('Suggested Values - Data Sources'), '100%', false, 3, 'center', '');

		// suggested values for data templates
		if (cacti_sizeof($data_templates)) {
			foreach ($data_templates as $data_template) {
				$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, snmp_query_graph_id, id
					FROM snmp_query_graph_rrd_sv
					WHERE snmp_query_graph_id = ?
					AND data_template_id = ?
					ORDER BY field_name, sequence', [grv('id'), $data_template['id']]);

				$name = db_fetch_cell_prepared('SELECT name
					FROM data_template
					WHERE id = ?',
					[$data_template['id']]);

				print "<tr class='tableHeader'><td colspan='4'>" . htmle($name) . '</td></tr><tr>';

				html_header([
					['display' => __('Name'), 'align' => 'left'],
					['display' => __('Order'), 'align' => 'center'],
					['display' => __('Equation'), 'align' => 'left']
				], 2);

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
							<?php print htmle($suggested_value['field_name']); ?>
						</td>
						<td class='center'>
							<?php if ($show_down) {?>
							<a class='remover ti ti-caret-down-filled moveArrow' title='<?php print __('Move Down'); ?>' href='<?php print htmle('data_queries.php?action=item_movedown_dssv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id') . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']); ?>'></a>
							<?php } else {?>
							<span class='moveArrowNone'></span>
							<?php } ?>
							<?php if ($show_up) {?>
							<a class='remover ti ti-caret-up-filled moveArrow' title='<?php print __('Move Up'); ?>' href='<?php print htmle('data_queries.php?action=item_moveup_dssv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id') . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']); ?>'></a>
							<?php } else {?>
							<span class='moveArrowNone'></span>
							<?php } ?>
						</td>
						<td class='nowrap left'>
							<?php print htmle($suggested_value['text']); ?>
						</td>
						<td class='right'>
							<a class='remover deleteMarker ti ti-x' title='<?php print __('Delete'); ?>' href='<?php print htmle('data_queries.php?action=item_remove_dssv&snmp_query_graph_id=' . grv('id') . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . grv('snmp_query_id') . '&data_template_id=' . $data_template['id']); ?>'></a>
						</td>
						<?php

						form_end_row();

						$prev_name = $suggested_value['field_name'];
						$i++;
					}
				} else {
					print "<tr class='tableRow odd'><td colspan='4'><em>" . __('No Suggested Values Found') . '</em></td></tr>';
				}

				form_alternate_row();
				?>
				<td colspan='4'>
					<table>
						<tr>
							<td class='nowrap'>
								<?php print __('Field Name'); ?>
							</td>
							<td>
								<input type='text' class='svds_field ui-state-default ui-corner-all' id='svds_<?php print $data_template['id']; ?>_field' size='15'>
							</td>
							<td class='nowrap'>
								<?php print __('Suggested Value'); ?>
							</td>
							<td>
								<input type='text' class='svds_text ui-state-default ui-corner-all' id='svds_<?php print $data_template['id']; ?>_text' size='60'>
							</td>
							<td>
								<button type='button' class='svds_x ui-button ui-corner-all ui-widget' id='svds_<?php print $data_template['id']; ?>_x' value='add' title='<?php print __('Add Data Source Name Suggested Name'); ?>'><?php print __esc('Add'); ?></button>
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
	var graph_template_id_prev=<?php print $item; ?>;

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

	$('button[id="svg_x"]').click(function() {
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

	$('button[id^="svds_"]').click(function() {
		var options = {
			url:'data_queries.php'
		}

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
				__csrf_magic: csrfMagicToken
			};

			postUrl(options, data);
		}
	});
	</script>
	<?php

	form_save_button('data_queries.php?action=edit&id=' . grv('snmp_query_id'), 'return');
}

function data_query_remove(int $id) : void {
	$snmp_query_graph = db_fetch_assoc_prepared('SELECT id
		FROM snmp_query_graph
		WHERE snmp_query_id = ?',
		[$id]);

	if (cacti_sizeof($snmp_query_graph)) {
		foreach ($snmp_query_graph as $item) {
			db_execute_prepared('DELETE
				FROM snmp_query_graph_rrd
				WHERE snmp_query_graph_id = ?',
				[$item['id']]);
		}
	}

	db_execute_prepared('DELETE
		FROM snmp_query
		WHERE id = ?',
		[$id]);

	db_execute_prepared('DELETE
		FROM snmp_query_graph
		WHERE snmp_query_id = ?',
		[$id]);

	db_execute_prepared('DELETE
		FROM host_template_snmp_query
		WHERE snmp_query_id = ?',
		[$id]);

	db_execute_prepared('DELETE
		FROM host_snmp_query
		WHERE snmp_query_id = ?',
		[$id]);

	db_execute_prepared('DELETE
		FROM host_snmp_cache
		WHERE snmp_query_id = ?',
		[$id]);

	update_replication_crc(0, 'poller_replicate_snmp_query_crc');
}

function data_query_edit() : void {
	global $fields_data_query_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$snmp_query = db_fetch_row_prepared('SELECT *
			FROM snmp_query WHERE
			id = ?',
			[grv('id')]);

		if (!cacti_sizeof($snmp_query)) {
			raise_message('data_query_missing', __('The Data Query ID [%s] that you are trying to Edit does not exist.  Please run the repair_database.php CLI script to resolve this database issue.', grv('id')), MESSAGE_LEVEL_ERROR);
			header('Location: data_queries.php');

			exit;
		}

		$header_label = __esc('Data Queries [edit: %s]', $snmp_query['name']);
	} else {
		$header_label = __('Data Queries [new]');
	}

	form_start('data_queries.php', 'data_queries');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_data_query_edit, (isset($snmp_query) ? $snmp_query : []))
		]
	);

	html_end_box(false, true);

	if (!empty($snmp_query['id'])) {
		$search       = ['<path_cacti>', '<path_snmpget>', '<path_php_binary>'];
		$replace      = [CACTI_PATH_BASE, read_config_option('path_snmpget'), read_config_option('path_php_binary')];
		$xml_filename = str_replace($search, $replace, $snmp_query['xml_path']);

		if ((file_exists($xml_filename)) && (is_file($xml_filename))) {
			$text            = "<span class='deviceUp'>" . __('Successfully located XML file') . '</span>';
			$xml_file_exists = true;
		} else {
			$text            = "<span class='deviceDown'>" . __('Could not locate XML file.') . '</span>';
			$xml_file_exists = false;
		}

		html_start_box('', '100%', false, 3, 'center', '');
		print "<tr class='tableRow debug'><td>$text</td></tr>";
		html_end_box(false);

		$display_text = [
			[
				'display' => __('Name'),
				'align'   => 'left'
			],
			[
				'display' => __('Graph Template Name'),
				'align'   => 'left'
			],
			[
				'display' => __('Graphs Using'),
				'align'   => 'right'
			],
			[
				'display' => __('Mapping ID'),
				'align'   => 'right'
			],
			[
				'display' => __('Action'),
				'align'   => 'right'
			]
		];

		html_start_box(__('Associated Graph Templates'), '100%', false, 3, 'center', 'data_queries.php?action=item_edit&snmp_query_id=' . $snmp_query['id']);

		html_header($display_text, 1);

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
			[$snmp_query['id']]);

		if (cacti_sizeof($snmp_query_graphs)) {
			$i = 0;

			foreach ($snmp_query_graphs as $snmp_query_graph) {
				form_alternate_row('line' . $i, true);

				if ($xml_file_exists) {
					$url = 'data_queries.php?action=item_edit&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id'];

					form_selectable_cell(filter_value($snmp_query_graph['name'], '', $url), $i);
				} else {
					form_selectable_cell(filter_value($snmp_query_graph['name'], '', '', __('Association Read Only until XML file located')), $i);
				}

				form_selectable_ecell($snmp_query_graph['graph_template_name'], $i);

				form_selectable_cell(number_format_i18n($snmp_query_graph['graphs'], -1), $i, '', 'right');
				form_selectable_cell($snmp_query_graph['id'], $i, '', 'right');

				if ($snmp_query_graph['graphs'] == 0) {
					$url = htmle('data_queries.php?action=item_remove_confirm&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id']);

					form_selectable_cell("<a class='delete deleteMarker ti ti-x' title='" . __('Delete') . "' href='" . $url . "'</a>", $i, '', 'right');
				} else {
					form_selectable_cell("<a class='deleteMarkerDisabled ti ti-x' title='" . __esc('Mapped Graph Templates with Graphs are read only') . "' href='#'></a>", $i, '', 'right');
				}

				form_end_row();

				$i++;
			}
		} else {
			print "<tr class='tableRow odd'><td colspan='" . cacti_sizeof($display_text) . "'><em>" . __('No Graph Templates Defined.') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_queries.php', 'return');

	?>
	<script type='text/javascript'>

	var snmp_query_id = '<?php print isset($snmp_query['id']) ? $snmp_query['id'] : '0'; ?>';
	var snmp_query_graph_id = '<?php print isset($snmp_query_graph['id']) ? $snmp_query_graph['id'] : '0'; ?>';

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
						title: '<?php print __('Delete Associated Graph'); ?>',
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

function data_query() : void {
	global $actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Data Queries'), 'data_queries.php', 'form_data_queries', 'sess_dq', 'data_queries.php?action=edit');

	$pageFilter->rows_label = __('Data Queries');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (sq.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR di.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM snmp_query AS sq
		INNER JOIN data_input AS di
		ON sq.data_input_id=di.id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$snmp_queries = db_fetch_assoc("SELECT sq.id, sq.name, sq.graphs, sq.templates, sq.last_updated,
		di.name AS data_input_method
		FROM snmp_query AS sq
		LEFT JOIN data_input AS di
		ON sq.data_input_id=di.id
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = [
		'name' => [
			'display' => __('Data Query Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Data Query.')
		],
		'id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal ID for this Graph Template.  Useful when performing automation or debugging.')
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Data Queries that are in use cannot be Deleted. In use is defined as being referenced by either a Graph or a Graph Template.')
		],
		'graphs' => [
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs using this Data Query.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs Templates using this Data Query.')
		],
		'data_input_method' => [
			'display' => __('Data Input Method'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Data Input Method used to collect data for Data Sources associated with this Data Query.')
		],
		'last_updated' => [
			'display' => __('Last Updated'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The last time this Template was updated.')
		]
	];

	$nav = html_nav_bar('data_queries.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Data Queries'), 'page', 'main');

	form_start('data_queries.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($snmp_queries)) {
		foreach ($snmp_queries as $snmp_query) {
			if ($snmp_query['graphs'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $snmp_query['id'], true, $disabled);
			form_selectable_cell(filter_value($snmp_query['name'], grv('filter'), 'data_queries.php?action=edit&id=' . $snmp_query['id']), $snmp_query['id']);
			form_selectable_cell($snmp_query['id'], $snmp_query['id'], '', 'right');
			form_selectable_cell($disabled ? __('No') : __('Yes'), $snmp_query['id'], '', 'right');
			form_selectable_cell(number_format_i18n($snmp_query['graphs'], -1), $snmp_query['id'], '', 'right');
			form_selectable_cell(number_format_i18n($snmp_query['templates'], -1), $snmp_query['id'], '', 'right');
			form_selectable_cell(filter_value($snmp_query['data_input_method'], grv('filter')), $snmp_query['id'], '', 'right');
			form_selectable_cell($snmp_query['last_updated'], $snmp_query['id'], '', 'right');

			form_checkbox_cell($snmp_query['name'], $snmp_query['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Queries Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($snmp_queries)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
