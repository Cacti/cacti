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

$guest_account = true;
require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_scheduler.php');
require_once(CACTI_PATH_LIBRARY . '/reports.php');
require_once(CACTI_PATH_LIBRARY . '/html_reports.php');
require_once(CACTI_PATH_LIBRARY . '/timespan_settings.php');

$reportit_api = CACTI_PATH_BASE . '/plugins/reportit/lib/funct_reports.php';

if (file_exists($reportit_api)) {
	require_once($reportit_api);
}

gfrv('id');
gfrv('tree_id');
gfrv('site_id');
gfrv('host_id');
gfrv('host_template_id');
gfrv('graph_template_id');
gfrv('tab', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);

// set a longer execution time for large reports
ini_set('max_execution_time', '300');

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		reports_form_save();

		break;
	case 'send':
		gfrv('id');

		reports_send(grv('id'));

		header('Location: reports.php?action=edit&tab=' . grv('tab') . '&id=' . grv('id'));

		break;
	case 'remove_history':
		draw_preview_filter();

		$history_id = grv('rdate');
		$report_id  = grv('id');

		if ($history_id > 0) {
			reports_remove_history($history_id, $report_id);
		}

		header('Location: reports.php?action=edit&tab=preview&rdate=-1&id=' . $report_id);

		break;
	case 'ajax_dnd':
		reports_item_dnd();

		header('Location: reports.php?action=edit&tab=items&id=' . grv('id'));

		break;
	case 'setvar':
		$changed = reports_item_validate();

		print $changed;

		break;
	case 'ajax_get_branches':
		print reports_get_branch_select(gfrv('tree_id'));

		break;
	case 'ajax_hosts':
		reports_item_validate();

		$sql_where = '';

		if (gfrv('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.site_id = ' . gfrv('site_id');
		}

		if (gfrv('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.host_template_id = ' . gfrv('host_template_id');
		}

		get_allowed_ajax_hosts(true, true, $sql_where);

		break;
	case 'ajax_graphs':
		reports_item_validate();

		$sql_where = '';

		if (grv('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.site_id = ' . grv('site_id');
		}

		if (grv('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.host_id = ' . grv('host_id');
		}

		if (grv('graph_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.graph_template_id = ' . grv('graph_template_id');
		}

		if (grv('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.host_template_id = ' . grv('host_template_id');
		}

		get_allowed_ajax_graphs($sql_where);

		break;
	case 'ajax_graph_template':
		reports_item_validate();

		$sql_where = '';

		if (grv('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.site_id = ' . grv('site_id');
		}

		if (grv('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.id = ' . grv('host_id');
		}

		if (grv('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.host_template_id = ' . grv('host_template_id');
		}

		get_allowed_ajax_graph_templates(true, true, $sql_where);

		break;
	case 'actions':
		reports_form_actions();

		break;
	case 'item_movedown':
		gfrv('id');

		reports_item_movedown();

		header('Location: reports.php?action=edit&tab=items&id=' . grv('id'));

		break;
	case 'item_moveup':
		gfrv('id');

		reports_item_moveup();

		header('Location: reports.php?action=edit&tab=items&id=' . grv('id'));

		break;
	case 'item_remove':
		gfrv('id');

		reports_item_remove();

		header('Location: reports.php?action=edit&tab=items&id=' . grv('id'));

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
