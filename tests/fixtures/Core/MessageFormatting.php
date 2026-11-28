<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiMessageFormattingFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';

foreach (['raise_message', 'raise_message_html', 'raise_message_has_markup'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

function __(string $format, mixed ...$arguments) : string {
	return vsprintf($format, $arguments);
}

function __esc(string $format, mixed ...$arguments) : string {
	return html_escape(__($format, ...$arguments));
}

function cacti_log(mixed ...$arguments) : void {
	$GLOBALS['formatting_logs'][] = $arguments;
}

function get_username(mixed $id = null) : string {
	return "O'Brien";
}

function get_nfilter_request_var(string $key) : string {
	return $_REQUEST[$key];
}

/**
 * Exercise the UI formatting expressions independently of the operations that
 * produce the notifications. No device is deleted, plugin installed or email
 * sent. ReportMessageDelivery and DeviceCollectorMessages exercise their
 * corresponding actions, including dispatch and side effects.
 * Only checked-in, side-effect-free message arguments may be used here.
 */
function formatted_messages(string $file, array $ids, array $variables) : array {
	$lines     = file($file);
	$rendered  = [];
	$pattern   = '/^\s*raise_message(?:_html)?\(\s*[\'"](' . implode('|', array_map('preg_quote', $ids)) . ')(?=[\'"$])/';
	$functions = ['raise_message', 'raise_message_html', '__', '__esc', 'html_escape', 'str_replace', 'basename', 'dirname', 'ucfirst', 'md5', 'date', 'get_username', 'get_nfilter_request_var'];

	foreach ($lines as $i => $line) {
		if (!preg_match('/^\s*raise_message(?:_html)?\(/', $line)) {
			continue;
		}

		$statement = $line;
		$end       = $i;

		while (!str_ends_with(rtrim($statement), ';') && isset($lines[$end + 1])) {
			$statement .= $lines[++$end];
		}

		if (!preg_match($pattern, $statement) || !preg_match('/\b__(?:esc)?\(/', $statement)) {
			continue;
		}

		if (preg_match('/\$\w+\s*\(|`/', $statement)) {
			throw new RuntimeException("Message contains a dynamic function call in $file:" . ($i + 1));
		}

		// Fail if a future change adds I/O to an argument of a reviewed message.
		foreach (token_get_all('<?php ' . $statement) as $token) {
			if (is_array($token) && !in_array($token[0], [
				T_OPEN_TAG, T_WHITESPACE, T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE,
				T_CURLY_OPEN, T_LNUMBER, T_STRING_CAST, T_INT_CAST,
			], true)) {
				throw new RuntimeException("Unsupported message argument in $file:" . ($i + 1));
			}

			if (is_array($token) && $token[0] === T_STRING && !in_array($token[1], $functions, true) && !preg_match('/^(?:MESSAGE_LEVEL_\w+|SESS_USER_ID)$/', $token[1])) {
				throw new RuntimeException("Unexpected expression {$token[1]} in $file:" . ($i + 1));
			}
		}

		$temporary = tempnam(sys_get_temp_dir(), 'cacti-formatted-message-');
		file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $i) . $statement);
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

		try {
			(static function (string $scenario_path, array $variables) : void {
				extract($variables, EXTR_SKIP);
				require $scenario_path;
			})($temporary, $variables);
		} finally {
			unlink($temporary);
		}

		$messages = $_SESSION[SESS_MESSAGES];

		if (count($messages) !== 1) {
			throw new RuntimeException("Expected one session message from $file:" . ($i + 1));
		}
		$id                      = array_key_first($messages);
		$rendered[]              = [$id, $messages[$id]['level'], $messages[$id]['message']];
		$_SESSION[SESS_MESSAGES] = [];
	}

	return $rendered;
}

$no_http_headers = true;
$_SESSION        = [SESS_USER_ID => 1, 'sess_gt_repairs' => 2, SESS_MESSAGES => []];
$_REQUEST        = ['field' => 'A & B', 'Field & Name' => 'X & Y'];
date_default_timezone_set('UTC');

$variables = [
	'admin'               => 1, 'i' => 0, 'id' => 23, 'device_id' => 24, 'rule_id' => 25, 'source_id' => 26,
	'poller_id'           => 2, 'old_poller_id' => 3, 'previous_poller' => 4, 'p' => 5, 'local_graph_id' => 31,
	'failed'              => 1, 'templates' => 2, 'graphs' => 3, 'items' => 4, 'total_time' => 1.25, 'end' => 5.5,
	'start'               => 3, 'total_plugins' => 6, 'updated_plugins' => 2, 'date1' => 100, 'date2' => 200,
	'selected_items'      => [32], 'description' => 'North & East', 'title' => 'Port "A"',
	'graph_template_name' => 'Traffic & Load', 'name' => 'Field & Name', 'variable' => 'field',
	'custom_error'        => 'Unmatched "A"', 'regex_valid' => 'Invalid & empty', 'field_name' => 'counter',
	'net'                 => 'Lab & Office', 'purgefile' => '/logs/old & new.log', 'logfile' => '/logs/primary.log',
	'info'                => ['name' => 'Edge & Core'], 'item' => ['field' => 'Rack & Unit'], 'tree_item' => ['field' => 'Building & Floor'],
	'snmp_query'          => ['id' => 5, 'name' => 'Cards & Ports'], 'data_template_field' => ['dtr_id' => 7, 'data_source_name' => 'Bytes & Packets'],
	'save'                => ['id' => 8, 'hostname' => 'edge.example', 'name' => 'Lab & QA', 'graph_name_regexp' => 'A & B'],
	'ds'                  => ['data_source_name' => 'CPU & IO'], 'repo' => ['name' => 'Repo & Mirror'], 'report' => ['name' => 'Traffic & Alerts'],
	'plugin'              => 'test_plugin', 'function' => 'plugin_test_version', 'plugin_name' => 'Table & Chart', 'display_action' => 'Enable',
	'required'            => 'monitor', 'xmlfile' => 'sample.xml', 'package_name' => 'Test & QA', 'filename' => 'package.manifest',
	'file'                => 'release & notes', 'restore_path' => '/plugins/test_plugin', 'basefile' => 'sub & dir/example.php',
	'tmp_dir'             => '/tmp/Test & QA', 'source' => 'daily', 'error' => 'Service & transport', 'message' => 'Required & missing',
	'debug'               => "Line & one\nLine \"two\"",
];

$cases = [
	'automation_devices.php'    => 'automation_msg_',
	'automation_networks.php'   => 'automation_message',
	'color_templates.php'       => 'color_template_sync',
	'data_source_profiles.php'  => 'heartbeat_change',
	'data_templates.php'        => 'data_template_rrd_ repopulate_',
	'graph_templates.php'       => 'gt_repair',
	'graphs.php'                => 'moved_ notmoved_',
	'graphs_new.php'            => 'xmlerror xmlfielderr',
	'host.php'                  => 'host_reindex reports_add_error',
	'host_templates.php'        => 'archives_removed_',
	'lib/api_automation.php'    => 'automation_message rule_missing sql_injection',
	'lib/api_data_source.php'   => 'poller_down_',
	'lib/api_device.php'        => 'device_graph_remove_failed_ device_remove_failed_ export_failed_ package_error_ package_success_ poller_down_',
	'lib/api_scheduler.php'     => 'sched_err',
	'lib/clog_webapi.php'       => 'clog_removed purge_failed rotate_failed',
	'lib/data_query.php'        => 'mapping_error poller_down_',
	'lib/functions.php'         => 'poller_',
	'lib/html_reports.php'      => 'custom report_message',
	'lib/html_utility.php'      => 'custom',
	'lib/package.php'           => 'missing_',
	'lib/plugins.php'           => 'archive_failed archive_restored directory_error install_error plugin_archive_failed plugin_archive_not_found plugin_archive_removed plugin_archived plugin_config plugins_failed plugins_fetched releases_warning restore_failed rmdata_complete rmdata_not_complete version_error',
	'lib/reports.php'           => 'remove_error remove_message report_message report_not_scheduled report_scheduled reports_add_device_ reports_no_add_device_',
	'lib/template.php'          => 'poller_down_',
	'lib/timespan_settings.php' => 'start_too_early',
	'lib/utility.php'           => 'poller_down_',
	'package.php'               => 'package_error',
	'package_import.php'        => 'import_fail_ import_success_ repo_exists repo_missing',
	'package_repos.php'         => 'repo_exists repo_missing',
	'plugins.php'               => 'force_remove_present force_remove_required invalid_plugin invalid_plugin_action plugin_good',
	'pollers.php'               => 'dupe_dbhost dupe_hostname poller_sync_permission',
	'settings.php'              => 'cacti_settings_ poller_',
	'support.php'               => 'lockout lockout_user',
];

$expected = json_decode(file_get_contents(__DIR__ . '/message-formatting-expected.json'), true, 512, JSON_THROW_ON_ERROR);
$tested   = 0;

foreach ($cases as $file => $keys) {
	$data = $variables;

	if ($file === 'lib/package.php') {
		$data['p'] = ['file' => 'data & settings.xml'];
	}
	$actual = formatted_messages($root . '/' . $file, explode(' ', $keys), $data);
	$wanted = $expected[$file];
	sort($actual);
	sort($wanted);

	if ($actual !== $wanted) {
		throw new RuntimeException("Rendered notifications changed in $file: " . json_encode($actual, JSON_THROW_ON_ERROR));
	}
	$tested += count($actual);
}

if (!empty($GLOBALS['formatting_logs'])) {
	throw new RuntimeException('A migrated plain-text message still produces the legacy markup diagnostic');
}

print "$tested message formatting cases passed\n";
