<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/data_query.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');

$host_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Sync Devices')
);

/* set default action */
set_default_action();

api_plugin_hook('device_template_top');

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_add_gt':
		template_item_add_gt();

		header('Location: host_templates.php?header=false&action=edit&id=' . get_filter_request_var('host_template_id'));
		break;
    case 'item_remove_gt_confirm':
        template_item_remove_gt_confirm();

        break;
	case 'item_remove_gt':
		template_item_remove_gt();

		header('Location: host_templates.php?header=false&action=edit&id=' . get_filter_request_var('host_template_id'));
		break;
	case 'item_add_dq':
		template_item_add_dq();

		header('Location: host_templates.php?header=false&action=edit&id=' . get_filter_request_var('host_template_id'));
		break;
    case 'item_remove_dq_confirm':
        template_item_remove_dq_confirm();

        break;
	case 'item_remove_dq':
		template_item_remove_dq();

		header('Location: host_templates.php?header=false&action=edit&id=' . get_filter_request_var('host_template_id'));
		break;
	case 'edit':
		top_header();

		template_edit();

		bottom_footer();
		break;
	default:
		top_header();

		template();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	get_filter_request_var('snmp_query_id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	if (isset_request_var('save_component_template')) {
		$save['id']    = get_nfilter_request_var('id');
		$save['hash']  = get_hash_host_template(get_nfilter_request_var('id'));
		$save['name']  = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['class'] = form_input_validate(get_nfilter_request_var('class'), 'class', '', false, 3);

		if (!is_error_message()) {
			$host_template_id = sql_save($save, 'host_template');

			if ($host_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: host_templates.php?header=false&action=edit&id=' . (empty($host_template_id) ? get_nfilter_request_var('id') : $host_template_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function template_item_add_dq() {
	/* ================= input validation ================= */
	get_filter_request_var('host_template_id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	db_execute_prepared('REPLACE INTO host_template_snmp_query
		(host_template_id, snmp_query_id)
		VALUES (?, ?)',
		array(get_request_var('host_template_id'), get_request_var('snmp_query_id')));

	raise_message(41);
}

function template_item_add_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('host_template_id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	db_execute_prepared('REPLACE INTO host_template_graph
		(host_template_id, graph_template_id)
		VALUES (?, ?)',
		array(get_request_var('host_template_id'), get_request_var('graph_template_id')));

	raise_message(41);
}

function form_actions() {
	global $host_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { // delete
				db_execute('DELETE FROM host_template WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM host_template_snmp_query WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));
				db_execute('DELETE FROM host_template_graph WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));

				/* "undo" any device that is currently using this template */
				db_execute('UPDATE host SET host_template_id = 0 WHERE deleted = "" AND ' . array_to_sql_or($selected_items, 'host_template_id'));
			} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_duplicate_device_template($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { // sync
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_device_template_sync_template($selected_items[$i]);
				}
			}
		}

		header('Location: host_templates.php?header=false');
		exit;
	}

	/* setup some variables */
	$host_list = ''; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM host_template WHERE id = ?', array($matches[1]))) . '</li>';
			$host_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('host_templates.php');

	html_start_box($host_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($host_array) && cacti_sizeof($host_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Device Template(s).') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Device Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Device Template(s).  Optionally change the title for the new Device Template(s).') ."</p>
					<div class='itemlist'><ul>$host_list</ul></div>
					<p><strong>" . __('Title Format:'). "</strong><br>\n";

			form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text');

			print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') ."' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Duplicate Device Template(s)') ."'>";
		} elseif (get_request_var('drp_action') == '3') { // sync devices
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Synchronize Devices associated with the selected Device Template(s).  Note that this action may take some time depending on the number of Devices mapped to the Device Template.') ."</p>
					<div class='itemlist'><ul>$host_list</ul></div>\n";

			print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') ."' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Sync Devices to Device Template(s)') ."'>";
		}
	} else {
		raise_message(40);
		header('Location: host_templates.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Template Functions
   --------------------- */

function template_item_remove_gt_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	form_start('host_templates.php?action=edit&id' . get_request_var('host_template_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$template = db_fetch_row_prepared('SELECT *
		FROM graph_templates
		WHERE id = ?',
		array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Graph Template will be disassociated from the Device Template.');?></p>
			<p><?php print __('Graph Template Name: %s', html_escape($template['name']));?>'<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Data Input Field');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$('#continue').click(function(data) {
		$.post('host_templates.php?action=item_remove_gt', {
			__csrf_magic: csrfMagicToken,
			host_template_id: <?php print get_request_var('host_template_id');?>,
			id: <?php print get_request_var('id');?>
		}, function(data) {
			$('#cdialog').dialog('close');
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			applySkin();
		});
	});
	</script>
	<?php
}

function template_item_remove_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_graph
		WHERE graph_template_id = ?
		AND host_template_id = ?',
		array(get_request_var('id'), get_request_var('host_template_id')));

	raise_message(41);
}

function template_item_remove_dq_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	form_start('host_templates.php?action=edit&id' . get_request_var('host_template_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$query = db_fetch_row_prepared('SELECT * FROM snmp_query WHERE id = ?', array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Queries will be disassociated from the Device Template.');?></p>
			<p><?php print __('Data Query Name: %s', html_escape($query['name']));?>'<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Data Input Field');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$('#continue').click(function(data) {
		$.post('host_templates.php?action=item_remove_dq', {
			__csrf_magic: csrfMagicToken,
			host_template_id: <?php print get_request_var('host_template_id');?>,
			id: <?php print get_request_var('id');?>
		}, function(data) {
			$('#cdialog').dialog('close');
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			applySkin();
		});
	});
	</script>
	<?php
}

function template_item_remove_dq() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_snmp_query
		WHERE snmp_query_id = ?
		AND host_template_id = ?',
		array(get_request_var('id'), get_request_var('host_template_id')));

	raise_message(41);
}

function template_edit() {
	global $fields_host_template_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$host_template = db_fetch_row_prepared('SELECT *
			FROM host_template
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __esc('Device Templates [edit: %s]', $host_template['name']);
	} else {
		$header_label = __('Device Templates [new]');
		set_request_var('id', 0);
	}

	form_start('host_templates.php', 'form_network');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => 'true'),
			'fields' => inject_form_variables($fields_host_template_edit, (isset($host_template) ? $host_template : array()))
		)
	);

	/* we have to hide this button to make a form change in the main form trigger the correct
	 * submit action */
	echo "<div style='display:none;'><input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Default Submit Button') . "'></div>";

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		html_start_box(__('Associated Graph Templates'), '100%', '', '3', 'center', '');

		$selected_graph_templates = db_fetch_assoc_prepared('SELECT
			graph_templates.id,
			graph_templates.name
			FROM (graph_templates,host_template_graph)
			WHERE graph_templates.id = host_template_graph.graph_template_id
			AND host_template_graph.host_template_id = ?
			ORDER BY graph_templates.name', array(get_request_var('id')));

		$i = 0;
		if (cacti_sizeof($selected_graph_templates)) {
			foreach ($selected_graph_templates as $item) {
				form_alternate_row("gt$i", true);
				?>
					<td class='left'>
						<strong><?php print $i;?>)</strong> <?php print html_escape($item['name']);?>
					</td>
					<td class='right'>
						<a class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete');?>' href='<?php print html_escape('host_templates.php?action=item_remove_gt_confirm&id=' . $item['id'] . '&host_template_id=' . get_request_var('id'));?>'></a>
					</td>
				<?php
				form_end_row();

				$i++;
			}
		} else {
			print '<tr><td><em>' . __('No associated graph templates.') . '</em></td></tr>';
		}

		?>
		<tr class='odd'>
			<td colspan='2'>
				<table>
					<tr style='line-height:10px'>
						<td class='nowrap templateAdd'>
							<?php print __('Add Graph Template');?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('graph_template_id', db_fetch_assoc_prepared('SELECT gt.id, gt.name
								FROM graph_templates AS gt
								LEFT JOIN host_template_graph AS htg
								ON gt.id=htg.graph_template_id
								AND htg.host_template_id = ?
								WHERE htg.host_template_id IS NULL
								AND gt.id NOT IN (SELECT graph_template_id FROM snmp_query_graph)
								ORDER BY gt.name', array(get_request_var('id'))),'name','id','','','');?>
						</td>
						<td class='noHide'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add');?>' id='add_gt' title='<?php print __esc('Add Graph Template to Device Template');?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box(__('Associated Data Queries'), '100%', '', '3', 'center', '');

		$selected_data_queries = db_fetch_assoc_prepared('SELECT snmp_query.id, snmp_query.name
			FROM (snmp_query, host_template_snmp_query)
			WHERE snmp_query.id = host_template_snmp_query.snmp_query_id
			AND host_template_snmp_query.host_template_id = ?
			ORDER BY snmp_query.name', array(get_request_var('id')));

		$i = 0;
		if (cacti_sizeof($selected_data_queries)) {
			foreach ($selected_data_queries as $item) {
				form_alternate_row("dq$i", true);
				?>
					<td class='left'>
						<strong><?php print $i;?>)</strong> <?php print html_escape($item['name']);?>
					</td>
					<td class='right'>
						<a class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete');?>' href='<?php print html_escape('host_templates.php?action=item_remove_dq_confirm&id=' . $item['id'] . '&host_template_id=' . get_request_var('id'));?>'></a>
					</td>
				<?php
				form_end_row();

				$i++;
			}
		} else {
			print '<tr><td><em>' . __('No associated data queries.') . '</em></td></tr>';
		}

		?>
		<tr class='odd'>
			<td colspan='2'>
				<table>
					<tr style='line-height:10px;'>
						<td class='nowrap queryAdd'>
							<?php print __('Add Data Query');?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('snmp_query_id', db_fetch_assoc_prepared('SELECT snmp_query.id, snmp_query.name
								FROM snmp_query LEFT JOIN host_template_snmp_query
								ON (snmp_query.id = host_template_snmp_query.snmp_query_id AND host_template_snmp_query.host_template_id = ?)
								WHERE host_template_snmp_query.host_template_id is null
								ORDER BY snmp_query.name', array(get_request_var('id'))),'name','id','','','');?>
						</td>
						<td class='noHide'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add');?>' id='add_dq' title='<?php print __esc('Add Data Query to Device Template');?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php

		html_end_box();

		api_plugin_hook('device_template_edit');
	}

	form_save_button('host_templates.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#cdialog').remove();
		$('#main').append("<div id='cdialog' class='cdialog'></div>");

		$('.delete').click(function (event) {
			event.preventDefault();

			request = $(this).attr('href');
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#cdialog').dialog({
						title: '<?php print __('Delete Item from Device Template');?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					})
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');

		$('#add_dq').click(function() {
			$.post('host_templates.php?action=item_add_dq', {
				host_template_id: $('#id').val(),
				snmp_query_id: $('#snmp_query_id').val(),
				reindex_method: $('#reindex_method').val(),
				__csrf_magic: csrfMagicToken
			}).done(function(data) {
				$('div[class^="ui-"]').remove();
				$('#main').html(data);
				applySkin();
			});
		});

		$('#add_gt').click(function() {
			$.post('host_templates.php?action=item_add_gt', {
				host_template_id: $('#id').val(),
				graph_template_id: $('#graph_template_id').val(),
				__csrf_magic: csrfMagicToken
			}).done(function(data) {
				$('div[class^="ui-"]').remove();
				$('#main').html(data);
				applySkin();
			});
		});
	});

	</script>
	<?php
}

function template() {
	global $host_actions, $item_rows, $device_classes;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'graph_template' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '-1'
		),
		'class' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'pageset' => true,
			'sort'    => 'ASC',
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
		'has_hosts' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
		)
	);

	validate_store_request_vars($filters, 'sess_ht');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Templates'), '100%', '', '3', 'center', 'host_templates.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_host_template' action='host_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Class');?>
					</td>
					<td>
						<select id='class'>
							<option value='-1'<?php print (get_request_var('class') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
							if (cacti_sizeof($device_classes)) {
								foreach ($device_classes as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('class') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Graph Template');?>
					</td>
					<td>
						<select id='graph_template'>
							<option value='-1'<?php print (get_request_var('graph_template') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
							if (get_request_var('class') == -1) {
								$graph_templates = db_fetch_assoc('SELECT DISTINCT id, name
									FROM (
										SELECT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN host_template_graph AS htg
										ON htg.graph_template_id = gt.id
										UNION
										SELECT gt.id, gt.name
										FROM graph_templates AS gt
										INNER JOIN snmp_query_graph AS sqg
										ON gt.id = sqg.graph_template_id
										INNER JOIN host_template_snmp_query AS htsq
										ON sqg.snmp_query_id = htsq.snmp_query_id
									) AS rs
									ORDER BY name');
							} else {
								$graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT ht.id, ht.name
									FROM (
										SELECT gt.id, gt.name, htg.host_template_id
										FROM graph_templates AS gt
										INNER JOIN host_template_graph AS htg
										ON htg.graph_template_id = gt.id
										UNION
										SELECT gt.id, gt.name, htsq.host_template_id
										FROM graph_templates AS gt
										INNER JOIN snmp_query_graph AS sqg
										ON gt.id = sqg.graph_template_id
										INNER JOIN host_template_snmp_query AS htsq
										ON sqg.snmp_query_id = htsq.snmp_query_id
									) AS rs
									INNER JOIN host_template AS ht
									ON rs.host_template_id = ht.id
									WHERE ht.id = ?
									ORDER BY name',
									array(get_request_var('class')));
							}

							if (cacti_sizeof($graph_templates)) {
								foreach ($graph_templates as $row) {
									print "<option value='" . $row['id'] . "'" . (get_request_var('graph_template') == $row['id'] ? ' selected ':'') . '>' . html_escape($row['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Device Templates');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_hosts' <?php print (get_request_var('has_hosts') == 'true' ? 'checked':'');?>>
							<label for='has_hosts'><?php print __('Has Devices');?></label>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'host_templates.php?header=false';
			strURL += '&filter='+$('#filter').val();
			strURL += '&class='+$('#class').val();
			strURL += '&rows='+$('#rows').val();
			strURL += '&graph_template='+$('#graph_template').val();
			strURL += '&has_hosts='+$('#has_hosts').is(':checked');
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'host_templates.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#graph_template, #class, #rows').change(function() {
				applyFilter();
			});

			$('#refresh, #has_hosts').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_host_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	$sql_join  = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(ht.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('class') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(ht.class = ' . db_qstr(get_request_var('class')) . ')';
	}

	if (get_request_var('graph_template') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(gt_id = ' . get_request_var('graph_template') . ')';
		$sql_join   = "INNER JOIN (
			SELECT DISTINCT host_template_id, id AS gt_id
			FROM (
				SELECT htg.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT htsq.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
		) AS htdata
		ON htdata.host_template_id = ht.id";
	}

	if (get_request_var('has_hosts') == 'true') {
		$sql_having = 'HAVING hosts > 0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(`rows`)
		FROM (
			SELECT
			COUNT(ht.id) AS `rows`, COUNT(DISTINCT host.id) AS hosts
			FROM host_template AS ht
			$sql_join
			LEFT JOIN host
			ON host.host_template_id = ht.id
			$sql_where
			GROUP BY ht.id
			$sql_having
		) AS rs");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$template_list = db_fetch_assoc("SELECT
		ht.id, ht.name, ht.class, COUNT(DISTINCT host.id) AS hosts
		FROM host_template AS ht
		$sql_join
		LEFT JOIN host
		ON host.host_template_id=ht.id
		$sql_where
		GROUP BY ht.id
		$sql_having
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Device Template Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name of this Device Template.')
		),
		'ht.class' => array(
			'display' => __('Device Class'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The Class of this Device Template.  The Class Name should be representative of it\'s function.')
		),
		'ht.id' => array(
			'display' => __('ID'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal database ID for this Device Template.  Useful when performing automation or debugging.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align' => 'right',
			'sort' => '',
			'tip' => __('Device Templates in use cannot be Deleted.  In use is defined as being referenced by a Device.')
		),
		'hosts' => array(
			'display' => __('Devices Using'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The number of Devices using this Device Template.')
		)
	);

	$nav = html_nav_bar('host_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Device Templates'), 'page', 'main');

	form_start('host_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['hosts'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'host_templates.php?action=edit&id=' . $template['id']), $template['id']);

			if ($template['class'] != '') {
				form_selectable_cell($device_classes[$template['class']], $template['id']);
			} else {
				form_selectable_cell(__('Unassigned'), $template['id']);
			}

			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $template['id'], '', 'right');
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('host.php?reset=true&host_template_id=' . $template['id']) . '">' . number_format_i18n($template['hosts'], '-1') . '</a>', $template['id'], '', 'right');
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Device Templates Found') . "</em></td></tr>\n";
	}
	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($host_actions);

	form_end();
}


