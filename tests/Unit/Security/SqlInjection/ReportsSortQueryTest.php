<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Run the real reports() list page against an in-memory database, so the
 * ORDER BY it builds from the sort allowlist is executed rather than only
 * read. SQLite, like MySQL and MariaDB, rejects a table-qualified ORDER BY
 * term on a UNION, which the combined Classic and ReportIt view sends.
 *
 * db_table_exists() caches its answer for the life of the process, so every
 * case installs the ReportIt table and exercises that combined view. The
 * single-table branches are driven by tests/fixtures/Core/ReportsListSortCoverage.php.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

function reports_sort_query_rows(string $column, string $direction) : array {
	// The page and its filter read these arrays as globals.
	global $item_rows, $graph_timespans, $graph_timeshifts, $sched_types, $reports_actions, $attach_types;
	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$key = $GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default'];
	$db  = new class extends FakeMySQLPDO {
		public array $orderedQueries = [];

		public function prepare(string $query, array $options = []): PDOStatement|false {
			return parent::prepare($this->server($query), $options);
		}

		public function query(string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
			return parent::query($this->server($statement));
		}

		// The page reads the server version while it sanitizes the sort request.
		private function server(string $query): string {
			if (str_contains($query, 'ORDER BY')) {
				$this->orderedQueries[] = $query;
			}

			return preg_match('/^\s*SHOW\s+GLOBAL\s+VARIABLES\b/i', $query)
				? "SELECT 'version' AS Variable_name, '10.11.6-MariaDB' AS Value"
				: $query;
		}
	};

	$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$db->exec('CREATE TABLE plugin_config (directory TEXT, status INTEGER)');
	$db->exec('CREATE TABLE user_auth (id INTEGER, username TEXT, full_name TEXT, enabled TEXT, reset_perms INTEGER)');
	$db->exec('CREATE TABLE user_auth_realm (user_id INTEGER, realm_id INTEGER)');
	$db->exec('CREATE TABLE user_auth_group (id INTEGER, enabled TEXT)');
	$db->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER, realm_id INTEGER)');
	$db->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER)');
	$db->exec("INSERT INTO user_auth VALUES (1, 'admin', 'Zed Admin', 'on', 0), (2, 'alice', 'Alice Owner', 'on', 0), (3, 'mia', 'Mia Owner', 'on', 0)");

	$columns = 'id INTEGER, user_id INTEGER, name TEXT, enabled TEXT, sched_type INTEGER, last_runtime REAL, last_started TEXT, run_limit INTEGER, next_start TEXT';
	$db->exec("CREATE TABLE reports ($columns, from_email TEXT, from_name TEXT, email TEXT, bcc TEXT)");
	$db->exec("INSERT INTO reports VALUES
		(1, 1, 'Bravo', 'on', 1, 0.5, '0000-00-00 00:00:00', 60, '2026-01-01 00:00:00', '', '', '', ''),
		(2, 2, 'Alpha', '', 1, 2.5, '0000-00-00 00:00:00', 30, '2026-01-01 00:00:00', '', '', '', '')");
	$db->exec("CREATE TABLE plugin_reportit_reports ($columns)");
	$db->exec("INSERT INTO plugin_reportit_reports VALUES (5, 3, 'Charlie', 'on', 1, 1.5, '0000-00-00 00:00:00', 45, '2026-01-01 00:00:00')");

	$sessions                           = $GLOBALS['database_sessions'] ?? [];
	$savedSession                       = $_SESSION ?? [];
	$savedRequest                       = $_REQUEST;
	$GLOBALS['database_sessions'][$key] = $db;
	$_SESSION                           = [SESS_USER_ID => 1, SESS_USER_REALMS => [21 => true]];
	$_REQUEST                           = [];

	try {
		require_once CACTI_PATH_LIBRARY . '/reports.php';
		require_once CACTI_PATH_LIBRARY . '/api_scheduler.php';
		require_once CACTI_PATH_LIBRARY . '/html_reports.php';

		$request = ['report_type' => '-1', 'rows' => '30', 'sort_column' => $column, 'sort_direction' => $direction];

		foreach ($request as $name => $value) {
			set_request_var($name, $value);
		}

		ob_start();

		try {
			reports();
		} finally {
			$html = ob_get_clean();
		}
	} finally {
		$GLOBALS['database_sessions'] = $sessions;
		$_SESSION                     = $savedSession;
		$_REQUEST                     = $savedRequest;
	}

	preg_match_all("/<tr[^>]* id='line([a-z]+_\d+)'/", $html, $rows);

	return ['rows' => $rows[1], 'queries' => $db->orderedQueries];
}

test('reports(): the combined Classic and ReportIt UNION sorts by a result column', function (string $column, string $direction, array $expected) {
	$result = reports_sort_query_rows($column, $direction);

	expect($result['rows'])->toBe($expected)
		->and($result['queries'])->toHaveCount(1)
		->and($result['queries'][0])->toContain(' UNION ')
		->and($result['queries'][0])->toContain('ORDER BY ' . $column . ' ' . $direction . ' LIMIT');
})->with([
	'owner ascending'  => ['full_name', 'ASC', ['reports_2', 'reportit_5', 'reports_1']],
	'owner descending' => ['full_name', 'DESC', ['reports_1', 'reportit_5', 'reports_2']],
	'run limit'        => ['run_limit', 'DESC', ['reports_1', 'reportit_5', 'reports_2']],
	'last runtime'     => ['last_runtime', 'DESC', ['reports_2', 'reportit_5', 'reports_1']],
]);

test('reports(): a sort column outside the allowlist falls back to the report name', function (string $column) {
	$result = reports_sort_query_rows($column, 'ASC');

	expect($result['rows'])->toBe(['reports_2', 'reports_1', 'reportit_5'])
		->and($result['queries'][0])->toContain('ORDER BY name ASC LIMIT');
})->with([
	'joined credential column' => ['ua.password'],
	'qualified header'         => ['report.name'],
]);
