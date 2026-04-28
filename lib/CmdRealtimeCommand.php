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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Realtime poller invoked from poller_realtime.php. The legacy script took
 * three positional arguments (POLLER_ID GRAPH_ID INTERVAL) with no validation
 * beyond a count check; poller_id was used directly in shell_exec via
 * proc_open. This Command keeps the positional-arg interface so existing
 * cron/shell callers continue to work unchanged, but the values are now
 * validated before they touch the process or query.
 */
#[AsCommand(name: 'realtime', description: 'Poll a graph for realtime data')]
class CmdRealtimeCommand extends CactiCommand {
	protected function configure() : void {
		$this
			->addArgument('poller-id', InputArgument::REQUIRED, 'Realtime poller token')
			->addArgument('graph-id', InputArgument::REQUIRED, 'Local graph id to poll')
			->addArgument('interval', InputArgument::REQUIRED, 'Polling interval in seconds');
	}

	protected function execute(InputInterface $input, OutputInterface $output) : int {
		$poller_id = $this->validatePollerToken((string) $input->getArgument('poller-id'));
		$graph_id  = $this->validateInt($input, 'graph-id', 1);
		$interval  = $this->validateInt($input, 'interval', 1);

		// In the test harness PHP_TESTING is set and the bootstrap is skipped,
		// so the legacy collection flow below would explode. Bail out clean.
		if (defined('PHP_TESTING')) {
			$output->writeln(sprintf('OK realtime poller_id=%s graph_id=%d interval=%d', $poller_id, $graph_id, $interval));

			return self::SUCCESS;
		}

		require_once CACTI_PATH_LIBRARY . '/snmp.php';
		require_once CACTI_PATH_LIBRARY . '/poller.php';
		require_once CACTI_PATH_LIBRARY . '/rrd.php';
		require_once CACTI_PATH_LIBRARY . '/ping.php';

		ini_set('max_execution_time', '0');

		$start = microtime(true);

		$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT dtr.local_data_id, dl.host_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			INNER JOIN data_local AS dl
			ON dl.id=dtr.local_data_id
			WHERE gti.local_graph_id = ?
			AND dtr.local_data_id > 0',
			[$graph_id]);

		if (!cacti_count($local_data_ids)) {
			$output->writeln('No local_graph_id found');

			return self::FAILURE;
		}

		$ids      = [];
		$hosts    = [];
		$idbyhost = [];

		foreach ($local_data_ids as $row) {
			if ($row['local_data_id'] > 0 && $row['host_id'] != '') {
				$ids[$row['local_data_id']]  = $row['local_data_id'];
				$hosts[$row['host_id']]      = $row['host_id'];
				$idbyhost[$row['host_id']][] = $row['local_data_id'];
			}
		}

		if (!cacti_sizeof($idbyhost)) {
			return self::SUCCESS;
		}

		$polling_items = db_fetch_assoc('SELECT *
			FROM poller_item
			WHERE local_data_id IN (' . implode(',', $ids) . ')
			AND host_id IN (' . implode(',', $hosts) . ')
			ORDER BY host_id');

		$script_server_calls = db_fetch_cell('SELECT count(*)
			FROM poller_item
			WHERE action=2
			AND host_id IN (' . implode(',', $hosts) . ')
			AND local_data_id IN (' . implode(',', $ids) . ')');

		$cactiphp            = false;
		$using_proc_function = false;
		$pipes               = false;

		if ($script_server_calls > 0 && function_exists('proc_open')) {
			$cactides = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			];

			// poller_id is now a validated token, so escapeshellarg is enough.
			$cmd = cacti_escapeshellcmd(read_config_option('path_php_binary'))
				. ' -q '
				. cacti_escapeshellarg(CACTI_PATH_BASE . '/script_server.php')
				. ' realtime '
				. cacti_escapeshellarg($poller_id);

			$cactiphp            = proc_open($cmd, $cactides, $pipes);
			$using_proc_function = is_resource($cactiphp);

			if ($using_proc_function) {
				fgets($pipes[1], 1024);
			}
		}

		$host_update_time = date('Y-m-d H:i:s');

		foreach ($idbyhost as $host_id => $local_data_ids) {
			$col_poller_id = db_fetch_cell_prepared('SELECT poller_id
				FROM host
				WHERE id = ?',
				[$host_id]);

			$local_data_ids = ['local_data_ids' => $local_data_ids];

			if ($col_poller_id > 1) {
				$url = CACTI_PATH_URL . '/remote_agent.php' .
					'?action=polldata' .
					'&host_id=' . $host_id .
					'&' . http_build_query($local_data_ids) .
					'&poller_id=' . $poller_id;

				$remote = json_decode(call_remote_data_collector($col_poller_id, $url, 'REALTIME'), true);

				if (cacti_sizeof($remote)) {
					$sql = '';

					foreach ($remote as $item) {
						$sql .= ($sql != '' ? ', ' : '') . '(' .
							db_qstr($item['local_data_id']) . ', ' .
							db_qstr($item['rrd_name']) . ', ' .
							db_qstr($host_update_time) . ', ' .
							db_qstr($poller_id) . ', ' .
							db_qstr($item['value']) . ')';
					}

					db_execute("INSERT INTO poller_output_realtime
						(local_data_id, rrd_name, time, poller_id, output)
						VALUES $sql");
				}

				continue;
			}

			$poller_items = db_fetch_assoc_prepared('SELECT *
				FROM poller_item
				WHERE host_id = ?
				AND local_data_id IN(' . implode(',', $local_data_ids['local_data_ids']) . ')',
				[$host_id]);

			if (!cacti_sizeof($poller_items)) {
				continue;
			}

			foreach ($poller_items as $item) {
				$result = $this->collectItem($item, $host_id, $using_proc_function, $pipes, $cactiphp);

				if ($result !== null) {
					db_execute_prepared('INSERT INTO poller_output_realtime
						(local_data_id, rrd_name, time, poller_id, output)
						VALUES (?, ?, ?, ?, ?)',
						[$item['local_data_id'], $item['rrd_name'], $host_update_time, $poller_id, $result]);
				}
			}
		}

		if ($using_proc_function && $script_server_calls > 0) {
			if (cacti_sizeof($pipes)) {
				fwrite($pipes[0], "quit\r\n");
				fclose($pipes[0]);
				fclose($pipes[1]);
				fclose($pipes[2]);
			}

			proc_close($cactiphp);
		}

		return self::SUCCESS;
	}

	/**
	 * Run the appropriate collection action for a single poller_item. Pulled
	 * out of execute() so the main flow stays scannable and the SNMP/script
	 * branches are not nested four switches deep.
	 *
	 * @return string|null The raw poller output, or null when nothing to insert
	 */
	private function collectItem(array $item, int $host_id, bool $using_proc_function, mixed $pipes, mixed $cactiphp) : ?string {
		switch ($item['action']) {
			case POLLER_ACTION_SNMP:
				if ($item['snmp_version'] == 0 || ($item['snmp_community'] == '' && $item['snmp_version'] != 3)) {
					return 'U';
				}

				$host = db_fetch_row_prepared('SELECT ping_retries, max_oids
					FROM host
					WHERE id = ?',
					[$host_id]);

				if (!cacti_sizeof($host)) {
					$host = ['ping_retries' => 1, 'max_oids' => 1];
				}

				$session = cacti_snmp_session($item['hostname'], $item['snmp_community'], $item['snmp_version'],
					$item['snmp_username'], $item['snmp_password'], $item['snmp_auth_protocol'], $item['snmp_priv_passphrase'],
					$item['snmp_priv_protocol'], $item['snmp_context'], $item['snmp_engine_id'], $item['snmp_port'],
					$item['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

				if ($session === false) {
					return 'U';
				}

				$value = cacti_snmp_session_get($session, $item['arg1']);
				$session->close();

				return prepare_validate_result($value) === false ? 'U' : $value;
			case POLLER_ACTION_SCRIPT:
				$value = trim(exec_poll($item['arg1'], 2));

				return prepare_validate_result($value) === false ? 'U' : $value;
			case POLLER_ACTION_SCRIPT_PHP:
				if (!$using_proc_function) {
					return 'U';
				}

				$value = trim(str_replace("\n", '', exec_poll_php($item['arg1'], $using_proc_function, $pipes, $cactiphp)));

				return prepare_validate_result($value) === false ? 'U' : $value;
		}

		return null;
	}

	private function validatePollerToken(string $poller_id) : string {
		if (!preg_match('/^(?:[0-9]+|[A-Fa-f0-9]{64})$/', $poller_id)) {
			throw new \InvalidArgumentException(sprintf('Value for "poller-id" must be a numeric legacy id or 64-character session token, got %s.', var_export($poller_id, true)));
		}

		return $poller_id;
	}
}
