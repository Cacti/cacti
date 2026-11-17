<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiMessageCallersFixture;

// MessageCallers owns the isolated schema and the real message gateway.
function push_out_aggregates($template, $graph = 0, $transaction = true, &$context = null) {
	$GLOBALS['propagated'][] = [$template, $graph];
}
function retemplate_graphs($template, $graph = 0, $sequence_only = false) {
	$_SESSION['sess_gt_repairs'] = $GLOBALS['repairs'];
}
function api_graph_change_device($graph, $host) {
	return $GLOBALS['move_success'];
}
function automation_add_device($device, $web = false) {
	check($device['hostname'] === $GLOBALS['name'], 'Discovery caller altered the device name');

	return $GLOBALS['add_success'] ? 7 : false;
}
function poller_check_duplicate_poller_id($poller_id, $hostname, $column) {
	return true;
}
function poller_host_duplicate($poller_id, $host) {
	return false;
}
function snmpagent_graphs_action_bottom($args) {
}
function reports_html_account_exists($user_id) {
	return true;
}
function reports_generate_html($reports_id, $output = REPORTS_OUTPUT_STDOUT, &$theme = '') {
	return '<p>Report body</p>';
}
function read_user_setting($name, $default = false, $force = false, $user = 0) {
	return 0;
}
function get_cacti_version() {
	return '1.2.32';
}
function mailer($from, $to, $cc, $bcc, $replyto, $subject, $body, $body_text = '', $attachments = '', $headers = '', $html = true) {
	check($to === 'to@example.test' && $body === '<p>Report body</p>', 'Report mail contract changed');

	return $GLOBALS['mail_error'];
}

cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);

switch ($argv[1]) {
	case 'color':
		cacti_fixture_source_function($root . '/color_templates.php', 'sync_color_templates', __NAMESPACE__);
		query('INSERT INTO color_templates (color_template_id, name) VALUES (1, ?)', [$name]);
		sync_color_templates(1);
		message('color_template_sync', 'no Aggregate', MESSAGE_LEVEL_INFO, true);
		query('INSERT INTO aggregate_graph_templates_item (aggregate_template_id, graph_templates_item_id, color_template) VALUES (1, 1, 1)');
		sync_color_templates(1);
		message('color_template_sync', '1 Aggregate Templates', MESSAGE_LEVEL_INFO, true);
		check($propagated === [[1, 0]], 'Color propagation did not run');
		break;
	case 'templates':
		cacti_fixture_source_function($root . '/graph_templates.php', 'form_actions', __NAMESPACE__);
		query('INSERT INTO graph_templates (id, name) VALUES (2, ?)', [$name]);
		foreach ([4, 5] as $action) {
			foreach ([0, 3] as $repairs) {
				$request = ['selected_items' => serialize([2]), 'drp_action' => (string) $action];
				try {
					form_actions();
					check(false, 'Graph template action must redirect');
				} catch (Redirect $e) {
					check(str_contains($e->getMessage(), 'graph_templates.php'), 'Wrong template redirect');
				}
				message('gt_repair2', $repairs ? '3 Repairs' : 'no Repairs', $repairs ? MESSAGE_LEVEL_WARN : MESSAGE_LEVEL_INFO, true);
			}
		}
		break;
	case 'graphs':
		cacti_fixture_source_function($root . '/graphs.php', 'form_actions', __NAMESPACE__);
		query('INSERT INTO graph_templates_graph (local_graph_id, title_cache) VALUES (1, ?)', [$name]);
		$request = ['selected_items' => serialize([1]), 'drp_action' => '5', 'host_id' => 7];
		foreach ([true, false] as $move_success) {
			try {
				form_actions();
				check(false, 'Graph move must redirect');
			} catch (Redirect $e) {
				check(str_contains($e->getMessage(), 'graphs.php'), 'Wrong graph redirect');
			}
			message(($move_success ? 'moved_' : 'notmoved_') . '1', $move_success ? 'Moved to' : 'missing Data Query', $move_success ? MESSAGE_LEVEL_INFO : MESSAGE_LEVEL_WARN, true);
		}
		break;
	case 'automation':
		cacti_fixture_source_function($root . '/automation_devices.php', 'form_actions', __NAMESPACE__);
		query('INSERT INTO automation_devices (id, hostname, ip, network_id) VALUES (1, ?, ?, 1)', [$name, '192.0.2.1']);
		$request = ['selected_items' => serialize([1]), 'drp_action' => '1', 'poller_id' => 1, 'host_template' => 0, 'availability_method' => 1];
		foreach ([true, false] as $add_success) {
			try {
				form_actions();
				check(false, 'Discovery action must redirect');
			} catch (Redirect $e) {
				check(str_contains($e->getMessage(), 'automation_devices.php'), 'Wrong discovery redirect');
			}
			message('automation_msg_0', $add_success ? 'Added to Cacti' : 'Not Added to Cacti', $add_success ? MESSAGE_LEVEL_INFO : MESSAGE_LEVEL_ERROR, true);
		}
		break;
	case 'pollers':
		cacti_fixture_source_function($root . '/pollers.php', 'form_save', __NAMESPACE__);
		$request = ['save_component_poller' => '1', 'id' => 2, 'name' => $name, 'hostname' => $name, 'timezone' => 'UTC', 'notes' => '', 'processes' => 1, 'threads' => 1, 'sync_interval' => 300, 'dbdefault' => 'cacti', 'dbhost' => 'db.example.test', 'dbuser' => '', 'dbpass' => '', 'dbport' => 3306, 'dbretries' => 1, 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => ''];
		try {
			form_save();
			check(false, 'Duplicate collector must redirect');
		} catch (Redirect $e) {
			check(str_contains($e->getMessage(), 'pollers.php'), 'Wrong collector redirect');
		}
		message('bad_host', 'insecure', MESSAGE_LEVEL_WARN, true);
		message('dupe_hostname', 'non-duplicate hostname', MESSAGE_LEVEL_ERROR, true);
		message('dupe_dbhost', 'non-duplicate database hostname', MESSAGE_LEVEL_ERROR, true);
		check((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller') === 0, 'Duplicate collector was saved');
		break;
	case 'query':
		cacti_fixture_source_function($root . '/lib/data_query.php', 'api_data_query_errors', __NAMESPACE__);
		query('INSERT INTO graph_templates (id, name) VALUES (1, ?)', [$name]);
		query('INSERT INTO snmp_query_graph (id, graph_template_id) VALUES (1, 1)');
		query('INSERT INTO data_template_rrd (id, local_data_id, data_template_id, data_source_name) VALUES (1, 0, 1, ?)', [$name]);
		query('INSERT INTO graph_templates_item (graph_template_id, task_item_id) VALUES (1, 1)');
		check(api_data_query_errors(1, []) === true, 'Unmapped query field must fail');
		message('mapping_error', 'XML output column', MESSAGE_LEVEL_ERROR, true);
		check(api_data_query_errors(1, ['dsdt_1_1_check' => 'on']) === false, 'Mapped query field should pass');
		break;
	case 'regex':
		define('IN_CACTI_INSTALL', 1);
		require $root . '/lib/html_utility.php';
		$_REQUEST = ['regex' => $name . '['];
		\get_filter_request_var('regex', FILTER_VALIDATE_IS_REGEX);
		message('custom', 'not valid', MESSAGE_LEVEL_ERROR, true);
		check(\get_request_var('regex') === '', 'Invalid regular expression must be cleared');
		$_REQUEST = ['regex' => $name . '['];
		$_CACTI_REQUEST = [];
		\validate_store_request_vars(['regex' => ['filter' => FILTER_VALIDATE_IS_REGEX, 'default' => '']]);
		message('custom', 'not valid', MESSAGE_LEVEL_ERROR, true);
		check(\get_request_var('regex') === '', 'Stored invalid regular expression must be cleared');
		break;
	case 'report':
		cacti_fixture_source_function($root . '/lib/reports.php', 'reports_get_format_files', '');
		cacti_fixture_source_function($root . '/lib/html_reports.php', 'reports_send', __NAMESPACE__);
		query('UPDATE reports SET name = ?, email = ?, subject = ? WHERE id = 1', ['', 'to@example.test', '']);
		reports_send(1);
		message('report_message', 'e-mail subject', MESSAGE_LEVEL_ERROR);
		query('UPDATE reports SET name = ?, email = ?, subject = ?, from_name = ?, from_email = ? WHERE id = 1', [$name, 'to@example.test', 'Subject', 'Sender', 'from@example.test']);
		cacti_fixture_source_function($root . '/lib/reports.php', 'generate_report', __NAMESPACE__);
		$request = ['id' => 1];
		foreach (['SMTP <b>unavailable</b> & retry', ''] as $mail_error) {
			$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = 1');
			check(generate_report($report, true) === ($mail_error === ''), 'Mailer failure was not propagated');
			message('report_message', $mail_error ? \html_escape($mail_error) : 'Sent Successfully', $mail_error ? MESSAGE_LEVEL_ERROR : MESSAGE_LEVEL_INFO, true);
			$sent = (int) db_fetch_cell_prepared('SELECT lastsent FROM reports WHERE id = 1');
			check($mail_error ? $sent === 0 : $sent > 0, 'Last-sent time must reflect mail delivery');
		}
		break;
	default:
		throw new \RuntimeException('Unknown message scenario');
}
