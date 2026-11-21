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
namespace CactiReportItemReview;

function isset_request_var($name) { return $name === 'item_id' && $GLOBALS['report_review_item_id'] > 0; }
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) { return get_request_var($name); }
function get_request_var($name, $default = '') { return $name === 'item_id' ? $GLOBALS['report_review_item_id'] : $GLOBALS['report_review_report_id']; }
function get_reports_page() { return 'reports_user.php'; }
/* the first translated label is only reached once the checks have passed */
function __(...$args) { throw new \RuntimeException('authorized-render'); }
function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) { return $GLOBALS['report_review_item']; }
function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	if (preg_match('/FROM\s+reports\s/', $sql)) {
		return $GLOBALS['report_review_owner'];
	}
	throw new \RuntimeException('authorized-render');
}
function cacti_authorize_is_admin($user_id) { return $GLOBALS['report_review_admin']; }
function cacti_authorize_has_realm($user_id, $realm_id) { return false; }
function raise_message($message_id, $message = '', $level = null) {}
function header($header, $replace = true, $response_code = 0) { throw new \RuntimeException('denied-redirect'); }
