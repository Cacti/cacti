<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_logs(): void {
	global $_CACTI_REQUEST;
	require_once CACTI_PATH_LIBRARY . '/clog_webapi.php';
	$directory = sys_get_temp_dir() . '/cacti-log-fixture-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Log fixture owns its directory');
	$session  = $_SESSION;
	$requests = [$_CACTI_REQUEST, $_REQUEST, $_GET, $_POST];
	$options  = ['path_cactilog' => $directory . '/fixture.log', 'log_action' => (string) LOG_ACTION_PURGE_ROTATE];
	$previous = [];

	foreach ($options as $name => $value) {
		$previous[$name] = read_config_option($name);
		set_config_option($name, $value);
	}
	$path    = $directory . '/fixture.log';
	$payload = "2026-01-01 12:00:00 - SYSTEM STATS: fixture log marker\n";
	$user    = (int) sql_save(['id' => 0, 'username' => 'Log <fixture>'], 'user_auth');
	$rule    = (int) sql_save(['id' => 0, 'name' => 'Rule <fixture>'], 'automation_graph_rules');

	try {
		foreach ([['text_regex_users', $user, 'Log <fixture>', 'user_admin.php'], ['text_regex_rule', $rule, 'Rule <fixture>', 'automation_graph_rules.php']] as [$formatter, $id, $label, $page]) {
			$matches = ['Reference [' . $id . ']', 'Reference [', (string) $id, ']'];
			library_expect($formatter($matches, ''), 'Reference [' . $label . ']', 'Plain log references resolve the stored identity');
			$dom = library_form_dom(static function () use ($formatter, $matches): void { print $formatter($matches, '1'); });
			library_expect($dom->query('//a')->length, 1, 'HTML log reference has one navigable target');
			library_expect($dom->query('//a')->item(0)->textContent, $label, 'HTML reference escapes operator-controlled names');
			library_expect(str_contains($dom->query('//a')->item(0)->getAttribute('href'), $page), true, 'Log reference links to the correct administration page');
			library_expect($dom->query('//fixture')->length, 0, 'Log labels cannot create markup');
		}
		library_expect(file_put_contents($path, $payload), strlen($payload), 'Fixture log contains known bytes');

		foreach ([[LOG_ACTION_PURGE, 'rotate', 'rotate_failed'], [LOG_ACTION_ROTATE, 'purge', 'purge_failed']] as [$policy, $action, $message]) {
			set_config_option('log_action', (string) $policy);
			srv('filename', 'fixture.log');
			$_SESSION[SESS_MESSAGES] = [];
			clog_purge_logfile($action);
			library_expect(file_get_contents($path), $payload, 'Disabled log operation preserves the file');
			library_expect($_SESSION[SESS_MESSAGES][$message]['level'], MESSAGE_LEVEL_ERROR, 'Disabled log operation reports the applicable error');
		}
		set_config_option('log_action', (string) LOG_ACTION_PURGE_ROTATE);
		clog_purge_logfile('rotate');
		$archives = glob($path . '-*');
		library_expect(count($archives), 1, 'Rotation creates exactly one archive');
		library_expect(file_get_contents($archives[0]), $payload, 'Rotation preserves the original log bytes');
		library_expect(str_contains(file_get_contents($path), 'Rotated by user'), true, 'Rotation starts a new log with its audit event');
		srv('filename', basename($archives[0]));
		$_SESSION[SESS_MESSAGES] = [];
		clog_purge_logfile('rotate');
		library_expect(file_get_contents($archives[0]), $payload, 'An existing archive cannot be rotated again');
		library_expect($_SESSION[SESS_MESSAGES]['rotate_failed']['level'], MESSAGE_LEVEL_ERROR, 'Archive rotation is rejected explicitly');
		clog_purge_logfile('purge');
		library_expect(file_exists($archives[0]), false, 'Purging removes the selected archive');
		library_expect(is_file($path), true, 'Archive removal preserves the active log');
		srv('filename', 'fixture.log');
		clog_purge_logfile('purge');
		library_expect(str_contains(file_get_contents($path), 'fixture log marker'), false, 'Purging the active log clears its old contents');
		library_expect(str_contains(file_get_contents($path), 'Removed by user'), true, 'Active-log purge retains its audit event');
		srv('filename', 'fixture.log-missing');
		$_SESSION[SESS_MESSAGES] = [];
		clog_purge_logfile('purge');
		library_expect(isset($_SESSION[SESS_MESSAGES]['clog_missing']), true, 'A missing log reports failure');
	} finally {
		user_remove($user);
		db_execute_prepared('DELETE FROM automation_graph_rules WHERE id = ?', [$rule]);

		foreach ($previous as $name => $value) {
			set_config_option($name, $value);
		}
		$_SESSION                                   = $session;
		[$_CACTI_REQUEST, $_REQUEST, $_GET, $_POST] = $requests;

		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
}
