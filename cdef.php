<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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
include_once('./lib/cdef.php');

$cdef_actions = array(
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
		cdef_item_remove_confirm();

		break;
	case 'item_remove':
		cdef_item_remove();

		break;
	case 'item_movedown':
		get_filter_request_var('cdef_id');

		item_movedown();

		header('Location: cdef.php?action=edit&id=' . get_request_var('cdef_id'));
		break;
	case 'item_moveup':
		get_filter_request_var('cdef_id');

		item_moveup();

		header('Location: cdef.php?action=edit&id=' . get_request_var('cdef_id'));
		break;
	case 'item_remove':
		get_filter_request_var('cdef_id');

		item_remove();

		header('Location: cdef.php?action=edit&id=' . get_request_var('cdef_id'));
		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();
		break;
	case 'edit':
		top_header();

		cdef_edit();

		bottom_footer();
		break;
    case 'ajax_dnd':
        cdef_item_dnd();

        break;
	default:
		top_header();

		cdef();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_cdef_preview($cdef_id) {
	?>
	<tr class='even'>
		<td style='padding:4px'>
			<pre>cdef=<?php print get_cdef($cdef_id, true);?></pre>
		</td>
	</tr>
	<?php
}


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {

	// make sure ids are numeric
	if (isset_request_var('id') && ! is_numeric(get_filter_request_var('id'))) {
		set_request_var('id', 0);
	}

	if (isset_request_var('cdef_id') && ! is_numeric(get_filter_request_var('cdef_id'))) {
		set_request_var('cdef_id', 0);
	}

	if (isset_request_var('save_component_cdef')) {
		$save['id']     = form_input_validate(get_nfilter_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']   = get_hash_cdef(get_nfilter_request_var('id'));
		$save['name']   = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['system'] = 0;

		if (!is_error_message()) {
			$cdef_id = sql_save($save, 'cdef');

			if ($cdef_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: cdef.php?header=false&action=edit&id=' . (empty($cdef_id) ? get_nfilter_request_var('id') : $cdef_id));
	} elseif (isset_request_var('save_component_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('cdef_id');
		get_filter_request_var('type');
		/* ==================================================== */

		$sequence = get_sequence(get_nfilter_request_var('id'), 'sequence', 'cdef_items', 'cdef_id=' . get_nfilter_request_var('cdef_id'));

		$save['id']       = form_input_validate(get_nfilter_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']     = get_hash_cdef(get_nfilter_request_var('id'), 'cdef_item');
		$save['cdef_id']  = form_input_validate(get_nfilter_request_var('cdef_id'), 'cdef_id', '^[0-9]+$', false, 3);
		$save['sequence'] = $sequence;
		$save['type']     = form_input_validate(get_nfilter_request_var('type'), 'type', '^[0-9]+$', false, 3);
		$save['value']    = form_input_validate(get_nfilter_request_var('value'), 'value', '', false, 3);

		if (!is_error_message()) {
			$cdef_item_id = sql_save($save, 'cdef_items');

			if ($cdef_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: cdef.php?header=false&action=item_edit&cdef_id=' . get_nfilter_request_var('cdef_id') . '&id=' . (empty($cdef_item_id) ? get_nfilter_request_var('id') : $cdef_item_id));
		} else {
			header('Location: cdef.php?header=false&action=edit&id=' . get_nfilter_request_var('cdef_id'));
		}
	}
}

function duplicate_cdef($_cdef_id, $cdef_title) {
	global $fields_cdef_edit;

	$cdef       = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', array($_cdef_id));
	$cdef_items = db_fetch_assoc_prepared('SELECT * FROM cdef_items WHERE cdef_id = ?', array($_cdef_id));

	/* substitute the title variable */
	$cdef['name'] = str_replace('<cdef_title>', $cdef['name'], $cdef_title);

	/* create new entry: host_template */
	$save['id']   = 0;
	$save['hash'] = get_hash_cdef(0);

	foreach ($fields_cdef_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $cdef[$field];
		}
	}

	$cdef_id = sql_save($save, 'cdef');

	/* create new entry(s): cdef_items */
	if (sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_cdef(0, 'cdef_item');
			$save['cdef_id']  = $cdef_id;
			$save['sequence'] = $cdef_item['sequence'];
			$save['type']     = $cdef_item['type'];
			$save['value']    = $cdef_item['value'];

			sql_save($save, 'cdef_items');
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $cdef_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM cdef WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM cdef_items WHERE ' . array_to_sql_or($selected_items, 'cdef_id'));
			} elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_cdef($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			}
		}

		header('Location: cdef.php?header=false');
		exit;
	}

	/* setup some variables */
	$cdef_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$cdef_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', array($matches[1]))) . '</li>';
			$cdef_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('cdef.php');

	html_start_box($cdef_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($cdef_array) && sizeof($cdef_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following CDEF.', 'Click \'Continue\' to delete all following CDEFs.', sizeof($cdef_array)) . "</p>
					<div class='itemlist'><ul>$cdef_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __n('Delete CDEF', 'Delete CDEFs', sizeof($cdef_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to duplicate the following CDEF. You can optionally change the title format for the new CDEF.', 'Click \'Continue\' to duplicate the following CDEFs. You can optionally change the title format for the new CDEFs.', sizeof($cdef_array)) . "</p>
					<div class='itemlist'><ul>$cdef_list</ul></div>
					<p>" . __('Title Format:') . '<br>';
					form_text_box('title_format', '<cdef_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __n('Duplicate CDEF', 'Duplicate CDEFs', sizeof($cdef_array)) . "'>";
		}
	} else {
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one CDEF.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($cdef_array) ? serialize($cdef_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function cdef_item_remove_confirm() {
	global $cdef_functions, $cdef_item_types, $custom_cdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	form_start('cdef.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$cdef       = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', array(get_request_var('id')));
	$cdef_item  = db_fetch_row_prepared('SELECT * FROM cdef_items WHERE id = ?', array(get_request_var('cdef_id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following CDEF Item.');?></p>
			<p><?php print __('CDEF Name: \'%s\'', $cdef['name']);?><br>
			<em><?php $cdef_item_type = $cdef_item['type']; print $cdef_item_types[$cdef_item_type];?></em>: <strong><?php print get_cdef_item_name($cdef_item['id']);?></strong></p>
		</td>
	</tr>
	<tr>
		<td align='right'>
			<input id='cancel' type='button' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close");$(".deleteMarker").blur();' name='cancel'>
			<input id='continue' type='button' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove CDEF Item');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').click(function(data) {
			$.post('cdef.php?action=item_remove', {
				__csrf_magic: csrfMagicToken,
				cdef_id: <?php print get_request_var('cdef_id');?>,
				id: <?php print get_request_var('id');?>
			}, function(data) {
				$('#cdialog').dialog('close');
				$('.deleteMarker').blur();
				loadPageNoHeader('cdef.php?action=edit&header=false&id=<?php print get_request_var('id');?>');
			});
		});
	});
	</script>
	<?php
}

function cdef_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM cdef_items WHERE id = ?', array(get_request_var('cdef_id')));
}

function item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	move_item_down('cdef_items', get_request_var('id'), 'cdef_id=' . get_request_var('cdef_id'));
}

function item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	move_item_up('cdef_items', get_request_var('id'), 'cdef_id=' . get_request_var('cdef_id'));
}

function item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM cdef_items WHERE id = ?', array(get_request_var('id')));
}

function item_edit() {
	global $cdef_item_types, $cdef_functions, $cdef_operators, $custom_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('cdef_id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$cdef = db_fetch_row_prepared('SELECT *
			FROM cdef_items
			WHERE id = ?',
			array(get_request_var('id')));

		if (sizeof($cdef)) {
			$current_type = $cdef['type'];
			$values[$current_type] = $cdef['value'];
		}
	} else {
		$cdef = array();
	}

	html_start_box(__('CDEF Preview'), '100%', '', '3', 'center', '');
	draw_cdef_preview(get_request_var('cdef_id'));
	html_end_box();

	form_start('cdef.php', 'form_cdef');

	$cdef_name = db_fetch_cell_prepared('SELECT name
		FROM cdef
		WHERE id = ?',
		array(get_request_var('cdef_id')));

	html_start_box(__('CDEF Items [edit: %s]', html_escape($cdef_name)), '100%', '', '3', 'center', '');

	if (isset_request_var('type_select')) {
		$current_type = get_request_var('type_select');
	} elseif (isset($cdef['type'])) {
		$current_type = $cdef['type'];
	} else {
		$current_type = '1';
	}

	$form_cdef = array(
		'type_select' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('CDEF Item Type'),
			'description'   => __('Choose what type of CDEF item this is.'),
			'value'         => $current_type,
			'array'         => $cdef_item_types
		),
		'value' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('CDEF Item Value'),
			'description'   => __('Enter a value for this CDEF item.'),
			'value'         => (isset($cdef['value']) ? $cdef['value']:'')
		),
		'id' => array(
			'method'        => 'hidden',
			'value'         => isset_request_var('id') ?  get_request_var('id') : '0',
		),
		'type' => array(
			'method'        => 'hidden',
			'value'         => $current_type
		),
		'cdef_id' => array(
			'method'        => 'hidden',
			'value'         => get_request_var('cdef_id')
		),
		'save_component_item' => array(
			'method'        => 'hidden',
			'value'         => '1'
		)
	);

	switch ($current_type) {
	case '1':
		$form_cdef['value']['array'] = $cdef_functions;

		break;
	case '2':
		$form_cdef['value']['array'] = $cdef_operators;

		break;
	case '4':
		$form_cdef['value']['array'] = $custom_data_source_types;

		break;
	case '5':
		$form_cdef['value']['method'] = 'drop_sql';
		$form_cdef['value']['sql']    = 'SELECT name, id FROM cdef WHERE system=0 ORDER BY name';

		break;
	case '6':
		$form_cdef['value']['method']     = 'textbox';
		$form_cdef['value']['max_length'] = '255';
		$form_cdef['value']['size']       = '30';

		break;
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_cdef, $cdef)
		)
	);

	?>
	<script type='text/javascript'>
	$(function() {
		$('#type_select').unbind().change(function() {
			strURL  = 'cdef.php?action=item_edit';
			strURL += '&id=' + $('#id').val();
			strURL += '&cdef_id=' + $('#cdef_id').val();
			strURL += '&type_select=' + $('#type_select').val();
			strURL += '&header=false';
			loadPageNoHeader(strURL);
		});
	});
	</script>
	<?php

	html_end_box();

	form_save_button('cdef.php?action=edit&id=' . get_request_var('cdef_id'));
}

/* ---------------------
    CDEF Functions
   --------------------- */

function cdef_item_dnd() {
	/* ================= Input validation ================= */
	get_filter_request_var('id');
	/* ================= Input validation ================= */

	$continue = true;

	if (isset_request_var('cdef_item') && is_array(get_nfilter_request_var('cdef_item'))) {
		$cdef_ids = get_nfilter_request_var('cdef_item');

		if (sizeof($cdef_ids)) {
			$sequence = 1;
			foreach($cdef_ids as $cdef_id) {
				$cdef_id = str_replace('line', '', $cdef_id);
				input_validate_input_number($cdef_id);

				db_execute_prepared('UPDATE cdef_items
					SET sequence = ?
					WHERE id = ?',
					array($sequence, $cdef_id));

				$sequence++;
			}
		}
	}

	header('Location: cdef.php?action=edit&header=false&id=' . get_request_var('id'));
}

function cdef_edit() {
	global $cdef_item_types, $fields_cdef_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$cdef = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', array(get_request_var('id')));
		$header_label = __('CDEF [edit: %s]', html_escape($cdef['name']));
	} else {
		$header_label = __('CDEF [new]');
	}

	form_start('cdef.php', 'cdef');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_cdef_edit, (isset($cdef) ? $cdef : array()))
		)
	);

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		html_start_box('', '100%', '', '3', 'center', '');
		draw_cdef_preview(get_request_var('id'));
		html_end_box();

		html_start_box(__('CDEF Items'), '100%', '', '3', 'center', 'cdef.php?action=item_edit&cdef_id=' . $cdef['id']);

		$display_text = array(
			array('display' => __('Item'), 'align' => 'left'),
			array('display' => __('Item Value'), 'align' => 'left')
		);

		html_header($display_text, 2);

		$cdef_items = db_fetch_assoc_prepared('SELECT *
			FROM cdef_items
			WHERE cdef_id = ?
			ORDER BY sequence',
			array(get_request_var('id')));

		$i = 1;
		$total_items = sizeof($cdef_items);
		if (sizeof($cdef_items)) {
			foreach ($cdef_items as $cdef_item) {
				form_alternate_row('line' . $cdef_item['id'], true, true);?>
					<td>
						<a class='linkEditMain' href='<?php print html_escape('cdef.php?action=item_edit&id=' . $cdef_item['id'] . '&cdef_id=' . $cdef['id']);?>'><?php print __('Item #%d', $i);?></a>
					</td>
					<td>
						<em><?php $cdef_item_type = $cdef_item['type']; print $cdef_item_types[$cdef_item_type];?></em>: <?php print html_escape(get_cdef_item_name($cdef_item['id']));?>
					</td>
					<td class='right'>
						<?php
						if (read_config_option('drag_and_drop') == '') {
							if ($i < $total_items && $total_items > 0) {
								echo '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape('cdef.php?action=item_movedown&id=' . $cdef_item['id'] . '&cdef_id=' . $cdef_item['cdef_id']) . '" title="' . __esc('Move Down') . '"></a>';
							} else {
								echo '<span class="moveArrowNone"></span>';
							}

							if ($i > 1 && $i <= $total_items) {
								echo '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('cdef.php?action=item_moveup&id=' . $cdef_item['id'] .	'&cdef_id=' . $cdef_item['cdef_id']) . '" title="' . __esc('Move Up') . '"></a>';
							} else {
								echo '<span class="moveArrowNone"></span>';
							}
						}
						?>
						<a id='<?php print $cdef['id'] . '_' . $cdef_item['id'];?>' class='delete deleteMarker fa fa-remove' title='<?php print __esc('Delete');?>' href='#'></a>
					</td>
				</tr>
				<?php

				$i++;
			}
		}

		html_end_box();
	}

	form_save_button('cdef.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#cdef_edit3').find('.cactiTable').attr('id', 'cdef_item');
		$('.cdialog').remove();
		$('body').append("<div class='cdialog' id='cdialog'></div>");

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#cdef_item').tableDnD({
			onDrop: function(table, row) {
				loadPageNoHeader('cdef.php?action=ajax_dnd&id=<?php isset_request_var('id') ? print get_request_var('id') : print 0;?>&'+$.tableDnD.serialize());
			}
		});
		<?php } ?>

		$('.delete').click(function(event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'cdef.php?action=item_remove_confirm&id='+id[0]+'&cdef_id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);
					applySkin();
					$('#cdialog').dialog({ title: '<?php print __('Delete CDEF Item');?>', minHeight: 80, minWidth: 500 });
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});
	});
	</script>
	<?php
}

function cdef() {
	global $cdef_actions, $item_rows;

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
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_cdef');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('CDEFs'), '100%', '', '3', 'center', 'cdef.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_cdef' action='cdef.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('CDEFs');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_graphs' <?php print (get_request_var('has_graphs') == 'true' ? 'checked':'');?>>
							<label for='has_graphs'><?php print __('Has Graphs');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'cdef.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				strURL += '&has_graphs='+$('#has_graphs').is(':checked');
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'cdef.php?clear=1&header=false';
				loadPageNoHeader(strURL);
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

				$('#form_cdef').submit(function(event) {
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
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%' AND system=0)";
	} else {
		$sql_where = 'WHERE system=0';
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs>0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(`rows`)
		FROM (
			SELECT cd.id AS `rows`,
			SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
			FROM cdef AS cd
			LEFT JOIN graph_templates_item AS gti
			ON gti.cdef_id=cd.id
			$sql_where
			GROUP BY cd.id
			$sql_having
		) AS rs");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$cdef_list = db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
		FROM (
			SELECT cd.*, gti.local_graph_id
			FROM cdef AS cd
			LEFT JOIN graph_templates_item AS gti
			ON gti.cdef_id=cd.id
			WHERE system=0
			GROUP BY cd.id, gti.graph_template_id, gti.local_graph_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('cdef.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('CDEFs'), 'page', 'main');

	form_start('cdef.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'      => array('display' => __('CDEF Name'),       'align' => 'left',  'sort' => 'ASC', 'tip' => __('The name of this CDEF.')),
		'nosort'    => array('display' => __('Deletable'),       'align' => 'right', 'tip'  => __('CDEFs that are in use cannot be Deleted.  In use is defined as being referenced by a Graph or a Graph Template.')),
		'graphs'    => array('display' => __('Graphs Using'),    'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Graphs using this CDEF.')),
		'templates' => array('display' => __('Templates Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Graphs Templates using this CDEF.')));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($cdef_list)) {
		foreach ($cdef_list as $cdef) {
			if ($cdef['graphs'] == 0 && $cdef['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $cdef['id'], false, $disabled);
			form_selectable_cell(filter_value($cdef['name'], get_request_var('filter'), 'cdef.php?action=edit&id=' . $cdef['id']), $cdef['id']);
			form_selectable_cell($disabled ? __('No') : __('Yes'), $cdef['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($cdef['graphs'], '-1'), $cdef['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($cdef['templates'], '-1'), $cdef['id'], '', 'text-align:right');
			form_checkbox_cell($cdef['name'], $cdef['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='4'><em>" . __('No CDEFs') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($cdef_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($cdef_actions);

	form_end();
}

