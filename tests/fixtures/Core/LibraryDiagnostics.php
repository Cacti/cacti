<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Verify persisted worker totals and the operator's completion message together. */
function library_diagnostic_totals(): void {
	global $start, $debug;
	require_once CACTI_PATH_LIBRARY . '/dsstats.php';
	require_once CACTI_PATH_LIBRARY . '/rrdcheck.php';
	$priorStart = $start ?? null;
	$priorDebug = $debug ?? null;
	$debug      = false;
	$saved      = [];
	$metrics    = ['rrd_user' => 1.25, 'rrd_system' => 2.5, 'rrd_real' => 4.75, 'total_rrds' => 7, 'total_dsses' => 11];

	try {
		foreach (['dsstats', 'rrdcheck'] as $system) {
			foreach ($metrics as $metric => $value) {
				$key         = $system . '_' . $metric . '_bchild_987654';
				$saved[$key] = db_fetch_row_prepared('SELECT * FROM settings WHERE name = ?', [$key]);
				set_config_option($key, (string) $value);
			}
			$key         = 'stats_' . $system . '_BOOST';
			$saved[$key] = db_fetch_row_prepared('SELECT * FROM settings WHERE name = ?', [$key]);
			ob_start();

			try {
				($system . '_log_child_stats')('bchild', 987654, 3.5);
				$child = ob_get_contents();
			} finally {
				ob_end_clean();
			}
			library_expect(str_contains($child, 'Time:3.50 Type:BCHILD ProcessNumber:987654 RRDfiles:7 DSSes:11 RRDUser:1.25 RRDSystem:2.50 RRDReal:4.75'), true, 'Worker completion reports the stored counters and timing');
			$start = microtime(true) - 1;
			ob_start();

			try {
				($system . '_log_statistics')('BOOST');
				$summary = ob_get_contents();
			} finally {
				ob_end_clean();
			}
			$stored = db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', [$key]);
			library_expect(is_string($stored) && str_contains($summary, $stored), true, 'Persisted and logged summaries agree');
			library_expect(str_contains($stored, 'RRDfiles:7 DSSes:11 RRDUser:1.25 RRDSystem:2.50 RRDReal:4.75'), true, 'Parent aggregates the completed worker counters');

			foreach ($metrics as $metric => $value) {
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM settings WHERE name = ?', [$system . '_' . $metric . '_bchild_987654']), 0, 'Consumed worker totals cannot be counted twice');
			}
		}
	} finally {
		foreach ($saved as $key => $row) {
			db_execute_prepared('DELETE FROM settings WHERE name = ?', [$key]);

			if ($row) {
				db_execute_prepared('INSERT INTO settings (name, value) VALUES (?, ?)', [$key, $row['value']]);
			}
			unset($GLOBALS['config'][OPTIONS_CLI][$key]);
		}
		$start = $priorStart;
		$debug = $priorDebug;
	}
}

/** Exercise method, realm and object guards before permitting a callback. */
function library_dispatch(): void {
	global $_CACTI_REQUEST;
	require_once CACTI_PATH_LIBRARY . '/cacti_dispatch.php';
	$saved    = [$_CACTI_REQUEST ?? [], $_REQUEST, $_GET, $_POST, $_SERVER];
	$status   = http_response_code();
	$events   = [];
	$callback = static function () use (&$events): void { $events[] = 'callback'; };
	$acl      = static function () use (&$events): bool {
		$events[] = 'acl';

		return true;
	};

	try {
		foreach ([
			['missing', [], '', 403, []],
			['go', ['method' => 'PATCH', 'callback' => $callback], '', 403, []],
			['go', ['method' => 'POST', 'object_acl' => $acl, 'callback' => $callback], '', 405, []],
			['go', ['realm' => 987654, 'object_acl' => $acl, 'callback' => $callback], '', 403, []],
			['go', ['object_acl' => 'not_a_callable', 'callback' => $callback], '', 403, []],
			['go', ['object_acl' => static fn (): bool => false, 'callback' => $callback], '', 403, []],
			['go', ['object_acl' => $acl, 'callback' => null], '', 500, ['acl']],
			['go', ['method' => 'GET', 'object_acl' => $acl, 'callback' => $callback], '', 200, ['acl', 'callback']],
			['bad/name', ['callback' => $callback], 'go', 200, ['callback']],
			['', ['callback' => $callback], 'go', 200, ['callback']],
		] as [$action, $entry, $default, $expected, $calls]) {
			$events                    = [];
			$_SERVER['REQUEST_METHOD'] = 'GET';
			unset($_SERVER['HTTP_X_REQUESTED_WITH']);
			srv('action', $action);
			http_response_code(200);
			cacti_dispatch(['go' => $entry], $default);
			library_expect(http_response_code(), $expected, 'Dispatch exposes the applicable guard status');
			library_expect($events, $calls, 'Rejected dispatch cannot reach later guards or mutation');
		}
	} finally {
		[$_CACTI_REQUEST, $_REQUEST, $_GET, $_POST, $_SERVER] = $saved;
		http_response_code($status ?: 200);
	}
}
