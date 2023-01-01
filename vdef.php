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
include_once('./lib/vdef.php');

$vdef_actions = array(
	'1' => __('Delete'),
	'2' => __('Duplicate')
);

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		vdef_form_save();

		break;
	case 'actions':
		vdef_form_actions();

		break;
	case 'item_remove_confirm':
		vdef_item_remove_confirm();

		break;
	case 'item_remove':
		vdef_item_remove();

		break;
	case 'item_movedown':
		get_filter_request_var('vdef_id');

		item_movedown();

		header('Location: vdef.php?action=edit&id=' . get_request_var('vdef_id'));

		break;
	case 'item_moveup':
		get_filter_request_var('vdef_id');

		item_moveup();

		header('Location: vdef.php?action=edit&id=' . get_request_var('vdef_id'));

		break;
	case 'item_edit':
		top_header();
		vdef_item_edit();
		bottom_footer();

		break;
	case 'edit':
		top_header();

		vdef_edit();

		bottom_footer();

		break;
	case 'ajax_dnd':
		vdef_item_dnd();

		break;

	default:
		top_header();

		vdef();

		bottom_footer();

		break;
}

/* --------------------------
	Global Form Functions
   -------------------------- */

function draw_vdef_preview($vdef_id) {
	?>
	<tr class='even'>
		<td style='padding:4px'>
			<pre>vdef=<?php print html_escape(get_vdef($vdef_id, true));?></pre>
		</td>
	</tr>
	<?php
}

/* --------------------------
	The Save Function
   -------------------------- */

function vdef_form_save() {
	if (isset_request_var('save_component_vdef')) {
		$save['id']   = get_filter_request_var('id');
		$save['hash'] = get_hash_vdef(get_request_var('id'));
		$save['name'] = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);

		if (!is_error_message()) {
			$vdef_id = sql_save($save, 'vdef');

			if ($vdef_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: vdef.php?action=edit&id=' . (empty($vdef_id) ? get_request_var('id') : $vdef_id));
	} elseif (isset_request_var('save_component_item')) {
		$sequence = get_sequence(get_filter_request_var('id'), 'sequence', 'vdef_items', 'vdef_id=' . get_filter_request_var('vdef_id'));

		$save['id']       = get_filter_request_var('id');
		$save['hash']     = get_hash_vdef(get_request_var('id'), 'vdef_item');
		$save['vdef_id']  = get_filter_request_var('vdef_id');
		$save['sequence'] = $sequence;
		$save['type']     = get_nfilter_request_var('type');
		$save['value']    = get_nfilter_request_var('value');

		if (!is_error_message()) {
			$vdef_item_id = sql_save($save, 'vdef_items');

			if ($vdef_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: vdef.php?action=item_edit&vdef_id=' . get_request_var('vdef_id') . '&id=' . (empty($vdef_item_id) ? get_request_var('id') : $vdef_item_id));
		} else {
			header('Location: vdef.php?action=edit&id=' . get_request_var('vdef_id'));
		}
	}
}

function duplicate_vdef($_vdef_id, $vdef_title) {
	global $fields_vdef_edit;

	$vdef       = db_fetch_row_prepared('SELECT * FROM vdef WHERE id = ?', array($_vdef_id));
	$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ?', array($_vdef_id));

	/* substitute the title variable */
	$vdef['name'] = str_replace('<vdef_title>', $vdef['name'], $vdef_title);

	/* create new entry: device_template */
	$save['id']   = 0;
	$save['hash'] = get_hash_vdef(0);

	$fields_vdef_edit = preset_vdef_form_list();

	foreach ($fields_vdef_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $vdef[$field];
		}
	}

	$vdef_id = sql_save($save, 'vdef');

	/* create new entry(s): vdef_items */
	if (cacti_sizeof($vdef_items) > 0) {
		foreach ($vdef_items as $vdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_vdef(0, 'vdef_item');
			$save['vdef_id']  = $vdef_id;
			$save['sequence'] = $vdef_item['sequence'];
			$save['type']     = $vdef_item['type'];
			$save['value']    = $vdef_item['value'];

			sql_save($save, 'vdef_items');
		}
	}
}

/* ------------------------
	The 'actions' function
   ------------------------ */

function vdef_form_actions() {
	global $vdef_actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') === '1') { // delete
				/* do a referential integrity check */
				if (cacti_sizeof($selected_items)) {
					foreach ($selected_items as $vdef_id) {
						/* ================= input validation ================= */
						input_validate_input_number($vdef_id, 'vdef_id');
						/* ==================================================== */

						$vdef_ids[] = $vdef_id;
					}
				}

				if (isset($vdef_ids)) {
					db_execute('DELETE FROM vdef WHERE ' . array_to_sql_or($vdef_ids, 'id'));
					db_execute('DELETE FROM vdef_items WHERE ' . array_to_sql_or($vdef_ids, 'vdef_id'));
				}
			} elseif (get_nfilter_request_var('drp_action') === '2') { // duplicate
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i], "selected_items[$i]");
					/* ==================================================== */

					duplicate_vdef($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			}
		}

		header('Location: vdef.php');

		exit;
	}

	/* setup some variables */
	$vdef_list = '';

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$vdef_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM vdef WHERE id = ?', array($matches[1]))) . '</li>';
			$vdef_array[] = $matches[1];
		}
	}

	top_header();

	form_start('vdef.php', 'vdef_actions');

	html_start_box($vdef_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($vdef_array)) {
		if (get_nfilter_request_var('drp_action') === '1') { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following VDEF.', 'Click \'Continue\' to delete following VDEFs.', cacti_sizeof($vdef_array)) . "</p>
						<div class='itemlist'><ul>$vdef_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Delete VDEF', 'Delete VDEFs', cacti_sizeof($vdef_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '2') { // duplicate
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to duplicate the following VDEF. You can optionally change the title format for the new VDEF.', 'Click \'Continue\' to duplicate following VDEFs. You can optionally change the title format for the new VDEFs.', cacti_sizeof($vdef_array)) . "</p>
						<div class='itemlist'><ul>$vdef_list</ul></div>
						<p><strong>" . __('Title Format:') . '</strong><br>';
			form_text_box('title_format', '<vdef_title> (1)', '', '255', '30', 'text');
			print "</p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate VDEF', 'Duplicate VDEFs', cacti_sizeof($vdef_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: vdef.php');

		exit;
	}

	print "<tr>
        <td class='saveRow'>
            <input type='hidden' name='action' value='actions'>
            <input type='hidden' name='selected_items' value='" . (isset($vdef_array) ? serialize($vdef_array) : '') . "'>
            <input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
            $save_html
        </td>
    </tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
	VDEF Item Functions
   -------------------------- */

function vdef_item_remove_confirm() {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	/* sort the vdef functions */
	asort($vdef_functions);

	form_start('vdef.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$vdef       = db_fetch_row_prepared('SELECT * FROM vdef WHERE id = ?', array(get_request_var('id')));
	$vdef_item  = db_fetch_row_prepared('SELECT * FROM vdef_items WHERE id = ?', array(get_request_var('vdef_id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following VDEF\'s.'); ?></p>
			<p><?php print __esc('VDEF Name: %s', $vdef['name']);?><br>
			<em><?php $vdef_item_type = $vdef_item['type'];
			print $vdef_item_types[$vdef_item_type];?></em>: <strong><?php print html_escape(get_vdef_item_name($vdef_item['id']));?></strong></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove VDEF Item');?>'>
			<input type='hidden' id='my_vdef_id' value='<?php print $vdef['id'];?>'>
			<input type='hidden' id='my_id' value='<?php print $vdef_item['id'];?>'>
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
				url: 'vdef.php?action=item_remove',
				funcEnd: 'removeVdefItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				vdef_id: <?php print get_request_var('vdef_id');?>,
				id: <?php print get_request_var('id');?>
			}
		});
	});

	function removeVdefItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'vdef.php?action=edit&id=<?php print get_request_var('id');?>'})
	}
	</script>
	<?php
}

function vdef_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM vdef_items
		WHERE id = ?',
		array(get_request_var('id')));
}

function vdef_item_edit() {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	get_filter_request_var('type_select');
	/* ==================================================== */

	/* sort the vdef functions */
	asort($vdef_functions);

	if (!isempty_request_var('id')) {
		$vdef = db_fetch_row_prepared('SELECT *
			FROM vdef_items
			WHERE id = ?',
			array(get_request_var('id')));

		if (cacti_sizeof($vdef)) {
			$current_type          = $vdef['type'];
			$values[$current_type] = $vdef['value'];
		}
	} else {
		$vdef = array();
	}

	html_start_box(__('VDEF Preview'), '100%', '', '3', 'center', '');
	draw_vdef_preview(get_request_var('vdef_id'));
	html_end_box();

	if (!isempty_request_var('vdef_id')) {
		$name = db_fetch_cell_prepared('SELECT name
			FROM vdef
			WHERE id = ?',
			array(get_request_var('vdef_id')));

		$header_label = __esc('VDEF Items [edit: %s]', $name);
	} else {
		$header_label = __('VDEF Items [new]');
	}

	form_start('vdef.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	if (isset_request_var('type_select')) {
		$current_type = get_request_var('type_select');
	} elseif (isset($vdef['type'])) {
		$current_type = $vdef['type'];
	} else {
		$current_type = CVDEF_ITEM_TYPE_FUNCTION;
	}

	$form_vdef = array(
		'type_select' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('VDEF Item Type'),
			'description'   => __('Choose what type of VDEF item this is.'),
			'value'         => $current_type,
			'array'         => $vdef_item_types
		),
		'value' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('VDEF Item Value'),
			'description'   => __('Enter a value for this VDEF item.'),
			'value'         => (isset($vdef['value']) ? $vdef['value']:'')
		),
		'id' => array(
			'method'        => 'hidden',
			'value'         => isset_request_var('id') ?  get_request_var('id') : '0',
		),
		'type' => array(
			'method'        => 'hidden',
			'value'         => $current_type
		),
		'vdef_id' => array(
			'method'        => 'hidden',
			'value'         => get_request_var('vdef_id')
		),
		'save_component_item' => array(
			'method'        => 'hidden',
			'value'         => '1'
		)
	);

	switch ($current_type) {
		case '1':
			$form_vdef['value']['array'] = $vdef_functions;

			break;
		case '4':
			$form_vdef['value']['array'] = $custom_vdef_data_source_types;

			break;
		case '6':
			$form_vdef['value']['method']     = 'textbox';
			$form_vdef['value']['max_length'] = '255';
			$form_vdef['value']['size']       = '30';

			break;
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_vdef, $vdef)
		)
	);

	?>
	<script type='text/javascript'>
	$(function() {
		$('#type_select').unbind().change(function() {
			strURL  = 'vdef.php?action=item_edit';
			strURL += '&id=' + $('#id').val();
			strURL += '&vdef_id=' + $('#vdef_id').val();
			strURL += '&type_select=' + $('#type_select').val();
			loadUrl({url:strURL})
		});
	});
	</script>
	<?php

	html_end_box();

	form_save_button('vdef.php?action=edit&id=' . get_request_var('vdef_id'));
}

/* ---------------------
	VDEF Functions
   --------------------- */

function item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	move_item_down('vdef_items', get_request_var('id'), 'vdef_id=' . get_request_var('vdef_id'));
}

function item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	move_item_up('vdef_items', get_request_var('id'), 'vdef_id=' . get_request_var('vdef_id'));
}

function vdef_item_dnd() {
	/* ================= Input validation ================= */
	get_filter_request_var('id');
	/* ================= Input validation ================= */

	$continue = true;

	if (isset_request_var('vdef_item') && is_array(get_nfilter_request_var('vdef_item'))) {
		$vdef_ids = get_nfilter_request_var('vdef_item');

		if (cacti_sizeof($vdef_ids)) {
			$sequence = 1;

			foreach ($vdef_ids as $vdef_id) {
				$vdef_id = str_replace('line', '', $vdef_id);
				input_validate_input_number($vdef_id, 'vdef_id');

				db_execute_prepared('UPDATE vdef_items
					SET sequence = ?
					WHERE id = ?',
					array($sequence, $vdef_id));

				$sequence++;
			}
		}
	}

	header('Location: vdef.php?action=edit&id=' . get_request_var('id'));
}

function vdef_edit() {
	global $vdef_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$vdef = db_fetch_row_prepared('SELECT *
			FROM vdef
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __esc('VDEFs [edit: %s]', $vdef['name']);
	} else {
		$header_label = __('VDEFs [new]');
	}

	form_start('vdef.php', 'vdef_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	$preset_vdef_form_list = preset_vdef_form_list();
	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($preset_vdef_form_list, (isset($vdef) ? $vdef : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('id', (isset($vdef['id']) ? $vdef['id'] : '0'), '');
	form_hidden_box('save_component_vdef', '1', '');

	if (!isempty_request_var('id')) {
		html_start_box('', '100%', '', '3', 'center', '');
		draw_vdef_preview(get_request_var('id'));
		html_end_box();

		html_start_box(__('VDEF Items'), '100%', '', '3', 'center', 'vdef.php?action=item_edit&vdef_id=' . $vdef['id']);

		$header_items = array(
			array('display' => __('Item'), 'align' => 'left'),
			array('display' => __('Item Value'), 'align' => 'left')
		);

		html_header($header_items, 2);

		$vdef_items = db_fetch_assoc_prepared('SELECT *
			FROM vdef_items
			WHERE vdef_id = ?
			ORDER BY sequence',
			array(get_request_var('id')));

		$i           = 1;
		$total_items = cacti_sizeof($vdef_items);

		if (cacti_sizeof($vdef_items)) {
			foreach ($vdef_items as $vdef_item) {
				form_alternate_row('line' . $vdef_item['id'], true, true);
				?>
				<td>
					<a class='linkEditMain' href='<?php print html_escape('vdef.php?action=item_edit&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef['id']);?>'><?php print __('Item #%d', $i);?></a>
				</td>
				<td>
					<em><?php $vdef_item_type = $vdef_item['type'];
					print $vdef_item_types[$vdef_item_type];?></em>: <strong><?php print html_escape(get_vdef_item_name($vdef_item['id']));?></strong>
				</td>
				<td class='right'>
					<?php
					if (read_config_option('drag_and_drop') == '') {
						if ($i < $total_items && $total_items > 1) {
							print '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape('vdef.php?action=item_movedown&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef_item['vdef_id']) . '" title="' . __esc('Move Down') . '"></a>';
						} else {
							print '<span class="moveArrowNone"></span>';
						}

						if ($i > 1 && $i <= $total_items) {
							print '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('vdef.php?action=item_moveup&id=' . $vdef_item['id'] .	'&vdef_id=' . $vdef_item['vdef_id']) . '" title="' . __esc('Move Up') . '"></a>';
						} else {
							print '<span class="moveArrowNone"></span>';
						}
					}
				?>
					<a id='<?php print $vdef['id'] . '_' . $vdef_item['id'];?>' class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete VDEF Item');?>'></a>
				</td>
				<?php

				form_end_row();

				$i++;
			}
		}

		html_end_box();
	}

	form_save_button('vdef.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#vdef_edit3').find('.cactiTable').attr('id', 'vdef_item');
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#vdef_item').unbind().tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'vdef.php?action=ajax_dnd&id=<?php isset_request_var('id') ? print get_request_var('id') : print 0;?>&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

		$('.delete').unbind().click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'vdef.php?action=item_remove_confirm&id='+id[0]+'&vdef_id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').off('click').on('click', function(data) {
						$.post('vdef.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							vdef_id: $('#my_vdef_id').val(),
							id: $('#my_id').val()
						}).done(function(data) {
							$('#cdialog').dialog('close');
							loadUrl({url:'vdef.php?action=edit&id='+$('#my_vdef_id').val()});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __esc('Delete VDEF Item');?>',
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

function vdef_filter() {
	global $item_rows;

	html_start_box(__('VDEFs'), '100%', '', '3', 'center', 'vdef.php?action=edit');
	?>
	<tr class='even'>
		<td>
			<form id='form_vdef' action='vdef.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('VDEFs');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

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
							<input type='checkbox' id='has_graphs' <?php print(get_request_var('has_graphs') == 'true' ? 'checked':'');?>>
                        	<label for='has_graphs'><?php print __('Has Graphs');?></label>
						</span>
                    </td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' id='refresh'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' id='clear'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'vdef.php';
				strURL += '?filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				strURL += '&has_graphs='+$('#has_graphs').is(':checked');
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = 'vdef.php?clear=1';
				loadUrl({url:strURL})
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_graphs').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_vdef').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}

function get_vdef_records(&$total_rows, &$rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE rs.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs>0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(`rows`)
        FROM (
            SELECT vd.id AS `rows`, vd.name,
            SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
            FROM vdef AS vd
            LEFT JOIN graph_templates_item AS gti
            ON gti.vdef_id=vd.id
            GROUP BY vd.id
			$sql_having
        ) AS rs
        $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	return db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates,
        SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
        FROM (
            SELECT vd.*, gti.local_graph_id
            FROM vdef AS vd
            LEFT JOIN graph_templates_item AS gti
            ON gti.vdef_id=vd.id
            GROUP BY vd.id, gti.graph_template_id, gti.local_graph_id
        ) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		$sql_order
		$sql_limit");
}

function vdef($refresh = true) {
	global $vdef_actions;

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
			),
		'has_graphs' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_vdef');
	/* ================= input validation ================= */

	vdef_filter();

	$total_rows = 0;
	$vdefs      = array();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$vdefs = get_vdef_records($total_rows, $rows);

	$nav = html_nav_bar('vdef.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('VDEFs'), 'page', 'main');

	form_start('vdef.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'      => array('display' => __('VDEF Name'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __esc('The name of this VDEF.') ),
		'nosort'    => array('display' => __('Deletable'), 'align' => 'right', 'tip' => __esc('VDEFs that are in use cannot be Deleted. In use is defined as being referenced by a Graph or a Graph Template.') ),
		'graphs'    => array('display' => __('Graphs Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __esc('The number of Graphs using this VDEF.') ),
		'templates' => array('display' => __('Templates Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __esc('The number of Graphs Templates using this VDEF.') )
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($vdefs)) {
		foreach ($vdefs as $vdef) {
			if ($vdef['graphs'] == 0 && $vdef['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $vdef['id'], false, $disabled);
			form_selectable_cell(filter_value($vdef['name'], get_request_var('filter'), 'vdef.php?action=edit&id=' . $vdef['id']), $vdef['id']);
			form_selectable_cell($disabled ? __('No'):__('Yes'), $vdef['id'], '', 'right');
			form_selectable_cell(number_format_i18n($vdef['graphs'], '-1'), $vdef['id'], '', 'right');
			form_selectable_cell(number_format_i18n($vdef['templates'], '-1'), $vdef['id'], '', 'right');
			form_checkbox_cell($vdef['name'], $vdef['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No VDEFs') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($vdefs)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($vdef_actions);

	form_end();
}
