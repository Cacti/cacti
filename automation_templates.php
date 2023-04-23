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

$at_actions = array(
	1 => __('Delete'),
	2 => __('Export')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'import':

		break;
	case 'ajax_dnd':
		automation_template_dnd();

		break;
	case 'actions':
		form_actions();

		break;
	case 'movedown':
		automation_movedown();

		header('Location: automation_templates.php');

		break;
	case 'moveup':
		automation_moveup();

		header('Location: automation_templates.php');

		break;
	case 'remove':
		automation_remove();

		header('Location: automation_templates.php');

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

function automation_template_dnd() {
	/* ================= Input validation ================= */
	get_filter_request_var('id');
	/* ================= Input validation ================= */

	if (isset_request_var('template_ids') && is_array(get_nfilter_request_var('template_ids'))) {
		$aids     = get_nfilter_request_var('template_ids');
		$sequence = 1;

		foreach ($aids as $id) {
			$id = str_replace('line', '', $id);
			input_validate_input_number($id, 'id');

			db_execute_prepared('UPDATE automation_templates
				SET sequence = ?
				WHERE id = ?',
				array($sequence, $id));

			$sequence++;
		}
	}

	header('Location: automation_templates.php');

	exit;
}

function automation_movedown() {
	move_item_down('automation_templates', get_filter_request_var('id'));
}

function automation_moveup() {
	move_item_up('automation_templates', get_filter_request_var('id'));
}

function automation_remove() {
	db_execute_prepared('DELETE FROM automation_templates WHERE id = ?', array(get_filter_request_var('id')));
}

function form_actions() {
	global $at_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM automation_templates WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: automation_templates.php');

		exit;
	}

	/* setup some variables */
	$at_list = '';
	$i       = 0;

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$at_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT ht.name FROM automation_templates AS at INNER JOIN host_template AS ht ON ht.id=at.host_template WHERE at.id = ?', array($matches[1]))) . '</li>';
			$at_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('automation_templates.php');

	html_start_box($at_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($at_array) && cacti_sizeof($at_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __('Click \'Continue\' to delete the following Automation Template(s).') . "</p>
					<div class='itemlist'><ul>$at_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Automation Template(s)') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: automation_templates.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($at_array) ? serialize($at_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	if (isset_request_var('save_component_template')) {
		$redirect_back = false;

		$save['id']                   = get_nfilter_request_var('id');
		$save['hash']                 = get_hash_automation(get_request_var('id'), 'automation_templates');
		$save['host_template']        = form_input_validate(get_nfilter_request_var('host_template'), 'host_template', '', false, 3);
		$save['availability_method']  = form_input_validate(get_nfilter_request_var('availability_method'), 'availability_method', '', false, 3);
		$save['sysDescr']             = get_nfilter_request_var('sysDescr');
		$save['sysName']              = get_nfilter_request_var('sysName');
		$save['sysOid']               = get_nfilter_request_var('sysOid');
		$save['description_pattern']  = get_nfilter_request_var('description_pattern');
		$save['populate_location']    = isset_request_var('populate_location') ? 'on':'';

		if (function_exists('filter_var')) {
			$save['sysDescr'] = filter_var($save['sysDescr'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		} else {
			$save['sysDescr'] = strip_tags($save['sysDescr']);
		}

		if (!is_error_message()) {
			$template_id = sql_save($save, 'automation_templates');

			if ($template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message() || isempty_request_var('id')) {
			header('Location: automation_templates.php?id=' . (empty($template_id) ? get_nfilter_request_var('id') : $template_id));
		} else {
			header('Location: automation_templates.php');
		}
	}
}

function automation_get_child_branches($tree_id, $id, $spaces, $headers) {
	$items = db_fetch_assoc_prepared('SELECT id, title
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND host_id=0
		AND local_graph_id=0
		AND parent = ?
		ORDER BY position', array($tree_id, $id));

	$spaces .= '--';

	if (cacti_sizeof($items)) {
		foreach ($items as $i) {
			$headers['tr_' . $tree_id . '_bi_' . $i['id']] = $spaces . ' ' . $i['title'];
			$headers                                       = automation_get_child_branches($tree_id, $i['id'], $spaces, $headers);
		}
	}

	return $headers;
}

function automation_get_tree_headers() {
	$headers = array();
	$trees   = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	foreach ($trees as $tree) {
		$headers['tr_' . $tree['id'] . '_br_0'] = $tree['name'];
		$spaces                                 = '';
		$headers                                = automation_get_child_branches($tree['id'], 0, $spaces, $headers);
	}

	return $headers;
}

function template_edit() {
	global $availability_options;

	$host_template_names = db_fetch_assoc('SELECT id, name FROM host_template');

	$template_names = array();

	if (cacti_sizeof($host_template_names)) {
		foreach ($host_template_names as $ht) {
			$template_names[$ht['id']] = $ht['name'];
		}
	}

	$fields_automation_template_edit = array(
		'spacer0' => array(
			'method'        => 'spacer',
			'friendly_name' => __('Matching Settings'),
		),
		'host_template' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('Device Template'),
			'description'   => __('Select a Device Template that Devices will be matched to.'),
			'value'         => '|arg1:host_template|',
			'array'         => $template_names,
		),
		'availability_method' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('Availability Method'),
			'description'   => __('Choose the Availability Method to use for Discovered Devices.'),
			'value'         => '|arg1:availability_method|',
			'default'       => read_config_option('availability_method'),
			'array'         => $availability_options,
		),
		'sysDescr' => array(
			'method'        => 'textbox',
			'friendly_name' => __('System Description Match'),
			'description'   => __('This is a unique string that will be matched to a devices sysDescr string to pair it to this Automation Template.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysDescr|',
			'max_length'    => '255',
		),
		'sysName' => array(
			'method'        => 'textbox',
			'friendly_name' => __('System Name Match'),
			'description'   => __('This is a unique string that will be matched to a devices sysName string to pair it to this Automation Template.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysName|',
			'max_length'    => '128',
		),
		'sysOid' => array(
			'method'        => 'textbox',
			'friendly_name' => __('System OID Match'),
			'description'   => __('This is a unique string that will be matched to a devices sysOid string to pair it to this Automation Template.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysOid|',
			'max_length'    => '128',
		),
		'spacer1' => array(
			'method'        => 'spacer',
			'friendly_name' => __('Device Creation Defaults'),
		),
		'description_pattern' => array(
			'method'        => 'textbox',
			'friendly_name' => __('Device Description Pattern'),
			'description'   => __('Represents the final desired Device description to be used in Cacti.  The following replacement values can be used: |sysName|, |ipAddress|, |dnsName|, |dnsShortName|, |sysLocation|.  The following functions can also be used: CONCAT(), SUBSTRING(), SUBSTRING_INDEX().  See the MySQL/MariaDB documentation for examples on how to use these functions.  An example would be: CONCAT(\'|sysName|\', SUBSTRING(\'|sysLocation|\',1,3)).  Take care to include quoting around the variables names when used in the supported MySQL/MariaDB function examples.'),
			'value'         => '|arg1:description_pattern|',
			'default'       => '|sysName|',
			'max_length'    => '128',
			'size'          => '80'
		),
		'populate_location' => array(
			'method'        => 'checkbox',
			'friendly_name' => __('Populate Location with sysLocation'),
			'description'   => __('If checked, when the Automation Network is scanned if a Device is found that will be added to Cacti, its Location will be updated to match the Devices sysLocation.'),
			'value'         => '|arg1:populate_location|',
			'default'       => ''
		),
		'id' => array(
			'method' => 'hidden_zero',
			'value'  => '|arg1:id|'
		),
		'save_component_template' => array(
			'method' => 'hidden',
			'value'  => '1'
		)
	);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$host_template = db_fetch_row_prepared('SELECT *
			FROM automation_templates
			WHERE id = ?',
			array(get_request_var('id')));

		if (isset($template_names[$host_template['host_template']])) {
			$header_label = __esc('Automation Templates [edit: %s]', $template_names[$host_template['host_template']]);
		} else {
			$header_label = __('Automation Templates for [Deleted Template]');
		}
	} else {
		$header_label = __('Automation Templates [new]');
		set_request_var('id', 0);
	}

	form_start('automation_templates.php', 'form_network');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => 'true'),
			'fields' => inject_form_variables($fields_automation_template_edit, (isset($host_template) ? $host_template : array()))
		)
	);

	html_end_box(true, true);

	form_save_button('automation_templates.php');
}

function template() {
	global $at_actions, $item_rows, $availability_options;

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
			)
	);

	validate_store_request_vars($filters, 'sess_autot');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Automation Templates'), '100%', '', '3', 'center', 'automation_templates.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_at' action='automation_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Templates');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
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
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Import');?>' title='<?php print __esc('Import Device Automation Template');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'automation_templates.php' +
					'?filter='     + $('#filter').val() +
					'&rows='       + $('#rows').val() +
					'&has_graphs=' + $('#has_graphs').is(':checked');
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = 'automation_templates.php?clear=1';
				loadUrl({url:strURL})
			}

			function importTemplate() {
				strURL = 'automation_templates.php?import=1';
				loadUrl({url:strURL})
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#import').click(function() {
					importTemplate();
				});

				$('#form_at').submit(function(event) {
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
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'sysName LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'sysDescr LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'sysOID LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where");

	$dts = db_fetch_assoc("SELECT at.*, ht.name
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where
		ORDER BY sequence " .
		' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows);

	$nav = html_nav_bar('automation_templates.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Templates'), 'page', 'main');

	form_start('automation_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		array('display' => __('Template Name'), 'align' => 'left'),
		array('display' => __('Availability Method'), 'align' => 'left'),
		array('display' => __('System Description Match'), 'align' => 'left'),
		array('display' => __('System Name Match'), 'align' => 'left'),
		array('display' => __('System ObjectId Match'), 'align' => 'left')
	);

	if (read_config_option('drag_and_drop') == '') {
		$display_text[] = array('display' => __('Order'), 'align' => 'center');
	}

	html_header_checkbox($display_text, false);

	$i           = 1;
	$total_items = cacti_sizeof($dts);

	if (cacti_sizeof($dts)) {
		foreach ($dts as $dt) {
			if ($dt['name'] == '') {
				$name = __('Unknown Template');
			} else {
				$name = $dt['name'];
			}

			form_alternate_row('line' . $dt['id'], true);
			form_selectable_cell(filter_value($name, get_request_var('filter'), 'automation_templates.php?action=edit&id=' . $dt['id']), $dt['id']);
			form_selectable_cell($availability_options[$dt['availability_method']], $dt['id']);
			form_selectable_cell(filter_value($dt['sysDescr'], get_request_var('filter')), $dt['id']);
			form_selectable_cell(filter_value($dt['sysName'], get_request_var('filter')), $dt['id']);
			form_selectable_cell(filter_value($dt['sysOid'], get_request_var('filter')), $dt['id']);

			if (read_config_option('drag_and_drop') == '') {
				$add_text = '';

				if ($i < $total_items && $total_items > 1) {
					$add_text .= '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape('automation_templates.php?action=movedown&id=' . $dt['id']) . '" title="' . __esc('Move Down') . '"></a>';
				} else {
					$add_text .= '<span class="moveArrowNone"></span>';
				}

				if ($i > 1 && $i <= $total_items) {
					$add_text .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('automation_templates.php?action=moveup&id=' . $dt['id']) . '" title="' . __esc('Move Up') . '"></a>';
				} else {
					$add_text .= '<span class="moveArrowNone"></span>';
				}

				form_selectable_cell($add_text, $dt['id'], '', 'center');
			}

			form_checkbox_cell($name, $dt['id']);
			form_end_row();

			$i++;
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Automation Device Templates Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($dts)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($at_actions);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#automation_templates2_child').attr('id', 'template_ids');

		$('img.action').click(function() {
			strURL = $(this).attr('href');
			loadUrl({url:strURL})
		});

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#template_ids').find('tr:first').addClass('nodrag').addClass('nodrop');

		$('#template_ids').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'automation_templates.php?action=ajax_dnd&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

	});
	</script>
	<?php
}
