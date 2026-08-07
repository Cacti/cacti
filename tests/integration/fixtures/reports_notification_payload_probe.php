<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function cacti_sizeof($array) {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

function __($text, ...$args) {
	return $args ? vsprintf($text, $args) : $text;
}

function read_config_option($name) {
	return '';
}

function cacti_log($message, $output = false, $environ = 'REPORTS') {
	$GLOBALS['reports_probe_log'][] = $message;
}

function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'FROM reports_queued')) {
		return $GLOBALS['reports_probe_report'];
	}

	return [];
}

function mailer($from, $to, $cc, $bcc, $reply_to, $subject, $html, $text, $attachments, $headers) {
	$GLOBALS['reports_probe_mailer_calls'][] = compact('from', 'to', 'cc', 'bcc', 'reply_to', 'subject');

	return true;
}

function sql_save($save, $table) {
	$GLOBALS['reports_probe_sql_save'][] = ['save' => $save, 'table' => $table];

	return true;
}

$GLOBALS['reports_probe_log']          = [];
$GLOBALS['reports_probe_mailer_calls'] = [];
$GLOBALS['reports_probe_sql_save']     = [];
$GLOBALS['reports_probe_report']       = [
	'name'         => 'Nightly Report',
	'notification' => $argv[1] ?? '',
	'request_type' => 1,
	'requested_by' => 'admin',
	'requested_id' => 1,
];

require_once dirname(__DIR__, 3) . '/lib/reports.php';

$raw_data  = [];
$oput_raw  = '<html></html>';
$oput_html = '<html></html>';
$oput_text = '';

reports_log_and_notify(1, time() - 5, 'html', 'reports', 1, 'Nightly Report', $raw_data, $oput_raw, $oput_html, $oput_text);

print json_encode([
	'log'          => $GLOBALS['reports_probe_log'],
	'mailer_calls' => $GLOBALS['reports_probe_mailer_calls'],
	'sql_save'     => $GLOBALS['reports_probe_sql_save'],
], JSON_THROW_ON_ERROR);
