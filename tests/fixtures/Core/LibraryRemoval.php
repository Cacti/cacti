<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Fail only transaction acknowledgement; SQL still uses the disposable MariaDB. */
final class LibraryRemovalConnection extends PDO {
	public string $failure = '';
	public function beginTransaction(): bool {
		return $this->failure === 'begin' ? false : parent::beginTransaction();
	}
	public function commit(): bool {
		return $this->failure === 'commit' ? false : parent::commit();
	}
}

function library_removal(int $template): void {
	global $database_sessions, $database_hostname, $database_port, $database_default, $database_username, $database_password;
	$key        = "$database_hostname:$database_port:$database_default";
	$primary    = $database_sessions[$key];
	$connection = new LibraryRemovalConnection("mysql:host=$database_hostname;port=$database_port;dbname=$database_default;charset=utf8mb4", $database_username, $database_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
	$session    = $_SESSION;
	$device     = 0;
	$trigger    = 'coverage_remove_' . bin2hex(random_bytes(6));

	try {
		$device = library_save_device(['description' => 'Removal transaction fixture', 'snmp_version' => 0]);
		library_expect($device > 0, true, 'Removal fixture owns its device');
		library_expect(db_execute_prepared('INSERT INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', [$device, $template]), true, 'Removal fixture owns a dependent graph mapping');
		$database_sessions[$key] = $connection;

		foreach (['begin', 'commit'] as $failure) {
			$connection->failure = $failure;

			foreach ([false, true] as $bulk) {
				$message = $bulk ? 'device_remove_multi_failed' : 'device_remove_failed_' . $device;
				unset($_SESSION[SESS_MESSAGES][$message]);
				$bulk ? api_device_remove_multi([$device]) : api_device_remove($device);
				library_expect($connection->inTransaction(), false, 'Unacknowledged removal leaves no open transaction');
				library_expect($_SESSION[SESS_MESSAGES][$message]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Failed transaction acknowledgement reaches the operator');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device]), 1, 'Failed transaction preserves the device');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device]), 1, 'Failed transaction restores dependent mappings');
			}
		}
		$connection->failure     = '';
		$database_sessions[$key] = $primary;
		$suggested               = [];
		$graphs                  = create_complete_graph_from_template($template, $device, [], $suggested);
		$graph                   = (int) $graphs['local_graph_id'];
		library_expect($graph > 0, true, 'Removal fixture owns an actual graph and data source');

		foreach ([['graph_local', 'DELETE', 'id', $graph, 2, 'graph'], ['poller_command', 'DELETE', 'poller_id', 1, 1, 'dependent'], ['graph_local', 'UPDATE', 'id', $graph, 1, 'detach']] as [$table, $event, $column, $identity, $mode, $stage]) {
			$command = '';

			if ($table === 'poller_command') {
				$command = $device . ':fixture';
				library_expect(db_execute_prepared('INSERT INTO poller_command (poller_id, action, command) VALUES (1, 1, ?)', [$command]), true, 'Removal fixture owns a pending poller command');
			}
			$condition = $command !== '' ? 'OLD.command = ' . db_qstr($command) : "OLD.$column = $identity";
			library_expect(db_execute("CREATE TRIGGER `$trigger` BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'coverage removal failure'; END IF; END"), true, 'Removal fixture installs a scoped server failure');

			try {
				foreach ($stage === 'graph' ? [false, true] : [true] as $bulk) {
					$message = $stage === 'graph' ? ($bulk ? 'device_remove_multi_graph_failed' : 'device_graph_remove_failed_' . $device) : ($stage === 'detach' ? 'device_remove_multi_detach_failed' : 'device_remove_multi_failed');
					unset($_SESSION[SESS_MESSAGES][$message]);
					$bulk ? api_device_remove_multi([$device], $mode) : api_device_remove($device);
					library_expect($_SESSION[SESS_MESSAGES][$message]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Failed ' . $stage . ' cleanup reports its actual stage');
					library_expect((int) db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$graph]), $device, 'Failed cleanup preserves graph ownership');
					library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device]), 1, 'Failed cleanup preserves its device');
				}
			} finally {
				db_execute('DROP TRIGGER `' . $trigger . '`');

				if ($command !== '') {
					db_execute_prepared('DELETE FROM poller_command WHERE poller_id = 1 AND action = 1 AND command = ?', [$command]);
				}
			}
		}
		api_device_remove_multi([$device], 2);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device]), 0, 'Retry removes the device after the failed dependency recovers');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_local WHERE id = ?', [$graph]), 0, 'Retry also removes its graph');
		$device = 0;
	} finally {
		$connection->failure = '';

		if ($connection->inTransaction()) {
			$connection->rollBack();
		}
		$database_sessions[$key] = $primary;
		db_execute('DROP TRIGGER IF EXISTS `' . $trigger . '`');

		if ($device) {
			api_device_remove($device);
		}
		$_SESSION = $session;
	}
}
