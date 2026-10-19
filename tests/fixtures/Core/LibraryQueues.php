<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_report_queue(): void {
	require_once CACTI_PATH_LIBRARY . '/html_reports.php';
	$session   = $_SESSION;
	$name      = 'Coverage queue ' . bin2hex(random_bytes(8));
	$directory = sys_get_temp_dir() . '/cacti-report-worker-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Report worker owns its temporary directory');
	$code = '<?php file_put_contents(__DIR__ . "/arguments.json", json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));';
	library_expect(file_put_contents($directory . '/worker.php', $code), strlen($code), 'Report worker argument recorder is complete');
	$trigger = 'coverage_report_' . bin2hex(random_bytes(6));
	$report  = $intruder = 0;

	try {
		foreach ([1, null] as $actor) {
			if ($actor === null) {
				unset($_SESSION[SESS_USER_ID]);
			} else {
				$_SESSION[SESS_USER_ID] = $actor;
			}
			$queue = (int) reports_queue($name, REPORTS_SEND_NOW, 'reports', 17, PHP_BINARY . ' ' . $directory . '/worker.php', []);
			library_expect($queue > 0, true, 'Both user and scheduled requests can queue a report');
			$row = db_fetch_row_prepared('SELECT requested_id, requested_by, status FROM reports_queued WHERE id = ?', [$queue]);
			library_expect((int) $row['requested_id'], $actor ?? -1, 'Queued report preserves the initiating actor');
			library_expect($row['requested_by'], $actor === null ? 'system' : get_username($actor), 'Queue audit label matches its actor');
			library_expect($row['status'], 'pending', 'New report remains pending before the runner starts');
			library_expect(reports_run($queue), true, 'Report runner executes the owned worker');
			library_expect(json_decode(file_get_contents($directory . '/arguments.json'), true, 512, JSON_THROW_ON_ERROR), ['--report-id=17', '--queue-id=' . $queue], 'Worker receives both report and queue identities');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_queued WHERE id = ?', [$queue]), 0, 'Completed worker leaves no pending queue record');
			library_expect(reports_run($queue), false, 'A completed queue identity cannot execute twice');
		}
		library_expect(db_execute('CREATE TRIGGER `' . $trigger . '` BEFORE INSERT ON reports_queued FOR EACH ROW BEGIN IF NEW.name = ' . db_qstr($name) . ' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'coverage queue write failure\'; END IF; END'), true, 'Queue fixture owns its insertion failure trigger');

		foreach ([1, null] as $actor) {
			if ($actor === null) {
				unset($_SESSION[SESS_USER_ID]);
			} else {
				$_SESSION[SESS_USER_ID] = $actor;
			}
			unset($_SESSION[SESS_MESSAGES]['report_scheduled'], $_SESSION[SESS_MESSAGES]['report_not_scheduled']);
			library_expect((int) reports_queue($name, REPORTS_SEND_NOW, 'reports', 17, '', []), 0, 'Failed queue write returns no runnable identity');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_queued WHERE name = ?', [$name]), 0, 'Failed queue write leaves no pending work');
			library_expect(isset($_SESSION[SESS_MESSAGES]['report_scheduled']), false, 'Failed queue write never emits a scheduled-success message');

			if ($actor !== null) {
				library_expect($_SESSION[SESS_MESSAGES]['report_not_scheduled']['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Interactive queue failure reaches the operator');
			}
		}
		library_expect(db_execute('DROP TRIGGER `' . $trigger . '`'), true, 'Queue failure trigger is removed before recovery');
		$_SESSION[SESS_USER_ID] = 1;
		$queue                  = (int) reports_queue($name, REPORTS_SEND_NOW, 'reports', 17, '', []);
		library_expect($queue > 0, true, 'Queue can recover after the storage failure is removed');
		$report   = (int) sql_save(['id' => 0, 'name' => $name, 'user_id' => 1], 'reports');
		$history  = (int) sql_save(['id' => 0, 'name' => $name, 'source' => 'reports', 'source_id' => $report], 'reports_log');
		$intruder = (int) sql_save(['id' => 0, 'username' => 'coverage_history_' . bin2hex(random_bytes(5)), 'enabled' => 'on', 'realm' => 0], 'user_auth');
		library_expect($report > 0 && $history > 0 && $intruder > 0, true, 'History fixture owns its report, history and unauthorized actor');
		library_expect(cacti_auth_transition($intruder), true, 'Unauthorized history actor has a valid session');
		$_SESSION[SESS_USER_ID] = $intruder;
		library_expect(is_reports_admin(), false, 'Unauthorized history actor has no report-administration realm');
		reports_remove_history($history, $report);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE id = ?', [$history]), 1, 'Another user cannot erase report history');
		library_expect(cacti_auth_transition(1), true, 'History fixture restores the disposable administrator');
		$_SESSION[SESS_USER_ID] = 1;
		reports_remove_history($history);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE id = ?', [$history]), 0, 'Authorized history removal resolves its report and deletes the selected record');
	} finally {
		db_execute('DROP TRIGGER IF EXISTS `' . $trigger . '`');
		db_execute_prepared('DELETE FROM reports_queued WHERE name = ?', [$name]);
		db_execute_prepared('DELETE FROM reports_log WHERE name = ?', [$name]);

		if ($report > 0) {
			db_execute_prepared('DELETE FROM reports WHERE id = ?', [$report]);
		}

		if ($intruder > 0) {
			user_remove($intruder);
		}

		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
		$_SESSION = $session;
	}
}
