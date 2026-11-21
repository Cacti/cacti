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
namespace CactiReportActionReview;

/* request, database and page boundaries of the reports bulk action confirmation page */
function isset_request_var($name) { return false; }
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = array()) { return REPORTS_DELETE; }
function get_nfilter_request_var($name, $default = '') { return REPORTS_DELETE; }
function input_validate_input_number($value, $variable = '') {}
function get_reports_page() { return 'reports_user.php'; }
function is_reports_admin() { return $GLOBALS['report_action_admin']; }
function __(...$args) { return $args[0]; }
function __esc(...$args) { return $args[0]; }
function html_escape($string) { return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8'); }
function escape_page_action($actions, $action) { return ''; }
function general_header() {}
function form_start($action, $id = '') {}
function html_start_box(...$args) {}
function html_end_box(...$args) {}
function form_end(...$args) {}
function bottom_footer() {}
function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	$report = $GLOBALS['report_action_reports'][(int) $params[0]] ?? null;

	if ($report === null) {
		return false;
	}

	return preg_match('/SELECT\s+user_id/', $sql) ? $report['user_id'] : $report['name'];
}
function cacti_authorize_is_admin($user_id) { return false; }
function cacti_authorize_has_realm($user_id, $realm_id) { return $GLOBALS['report_action_admin']; }
function raise_message($message_id, $message = '', $level = null) {}
function header($header, $replace = true, $response_code = 0) { throw new \RuntimeException('nothing-selected-redirect'); }
