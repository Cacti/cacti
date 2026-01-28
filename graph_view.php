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
require_once(CACTI_PATH_LIBRARY . '/html_tree.php');
require_once(CACTI_PATH_LIBRARY . '/html_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/graphs.php');
require_once(CACTI_PATH_LIBRARY . '/reports.php');
require_once(CACTI_PATH_LIBRARY . '/timespan_settings.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

// set the default graph action
set_default_graph_action();

// perform spikekill action
html_spikekill_actions();

// process tree settings
process_tree_settings();

// setup realtime defaults if they are not set
initialize_realtime_step_and_window();

switch (gnrv('action')) {
	case 'ajax_hosts':
		$sql_where = html_make_device_where();
		get_allowed_ajax_hosts(true, true, $sql_where);

		break;
	case 'ajax_search':
		get_matching_nodes();

		break;
	case 'ajax_reports':
		html_graph_get_reports();

		break;
	case 'update_timespan':
		html_graph_update_timespan();

		break;
	case 'save':
		html_save_graph_settings();

		break;
	case 'tree':
		html_tree_init();

		break;
	case 'get_node':
		html_tree_get_node();

		break;
	case 'tree_content':
		html_tree_get_content();

		break;
	case 'preview':
		html_graph_preview_view();

		break;
	case 'list':
		html_graph_list_view();

		break;
	case 'view-preview':
	case 'view-tree':
	case 'view':
		html_graph_single_view();

		break;
	case 'zoom-preview':
	case 'zoom-tree':
	case 'zoom':
		html_graph_zoom();

		break;
	case 'properties-preview':
	case 'properties-tree':
	case 'properties':
		html_graph_properties();

		break;
}
