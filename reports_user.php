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

$guest_account = true;
include('./include/auth.php');
include_once(CACTI_PATH_LIBRARY . '/reports.php');
include_once(CACTI_PATH_LIBRARY . '/html_reports.php');
include_once(CACTI_PATH_LIBRARY . '/timespan_settings.php');

get_filter_request_var('id');
get_filter_request_var('tree_id');
get_filter_request_var('site_id');
get_filter_request_var('host_id');
get_filter_request_var('host_template_id');
get_filter_request_var('graph_template_id');

/* set a longer execution time for large reports */
ini_set('max_execution_time', '300');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		reports_form_save();

		break;
	case 'send':
		get_request_var('id');

		reports_send(get_request_var('id'));

		header('Location: reports_user.php?action=edit&tab=' . get_request_var('tab') . '&id=' . get_request_var('id'));

		break;
	case 'ajax_dnd':
		reports_item_dnd();

		header('Location: reports_admin.php?action=edit&tab=items&id=' . get_request_var('id'));

		break;
	case 'setvar':
		$changed = reports_item_validate();

		print $changed;

		break;
	case 'ajax_get_branches':
		print reports_get_branch_select(get_request_var('tree_id'));

		break;
	case 'ajax_hosts':
		reports_item_validate();

		$sql_where = '';

		if (get_request_var('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.site_id = ' . get_request_var('site_id');
		}

		if (get_request_var('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.host_template_id = ' . get_request_var('host_template_id');
		}

		get_allowed_ajax_hosts(true, 'applyFilter', $sql_where);

		break;
	case 'ajax_graphs':
		reports_item_validate();

		$sql_where = '';

		if (get_request_var('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.site_id = ' . get_request_var('site_id');
		}

		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.host_id = ' . get_request_var('host_id');
		}

		if (get_request_var('graph_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.graph_template_id = ' . get_request_var('graph_template_id');
		}

		if (get_request_var('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.host_template_id = ' . get_request_var('host_template_id');
		}

		get_allowed_ajax_graphs($sql_where);

		break;
	case 'ajax_graph_template':
		reports_item_validate();

		$sql_where = '';

		if (get_request_var('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.site_id = ' . get_request_var('site_id');
		}

		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.id = ' . get_request_var('host_id');
		}

		if (get_request_var('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.host_template_id = ' . get_request_var('host_template_id');
		}

		get_allowed_ajax_graph_templates(true, true, $sql_where);

		break;
	case 'actions':
		reports_form_actions();

		break;
	case 'item_movedown':
		get_request_var('id');

		reports_item_movedown();

		header('Location: reports_user.php?action=edit&tab=items&id=' . get_request_var('id'));

		break;
	case 'item_moveup':
		get_request_var('id');

		reports_item_moveup();

		header('Location: reports_user.php?action=edit&tab=items&id=' . get_request_var('id'));

		break;
	case 'item_remove':
		get_request_var('id');

		reports_item_remove();

		header('Location: reports_user.php?action=edit&tab=items&id=' . get_request_var('id'));

		break;
	case 'item_edit':
		general_header();
		reports_item_edit();
		bottom_footer();

		break;
	case 'edit':
		general_header();
		reports_edit();
		bottom_footer();

		break;

	default:
		general_header();
		reports();
		bottom_footer();

		break;
}
