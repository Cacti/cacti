<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Runs the real upgrade_rrdcheck_user_log_keys() through the real installer
// helpers. The coverage job has no MySQL server and sqlite cannot parse the
// ALTER statements, so the connection below keeps the two tables as a model:
// it answers the SHOW queries the helpers ask, applies each ALTER to the model
// and rejects what MySQL would reject.

$source = dirname(__DIR__, 3);

require $source . '/tests/Helpers/RequestCoverage.php';

class RrdcheckUserLogKeysConnection extends PDO {
	/** @var array<string, array<int, string>> */
	public array $columns;

	/** @var array<string, array<string, array<int, string>>> */
	public array $keys;

	/** @var array<int, string> */
	public array $alters = [];

	/**
	 * @param array<string, array<int, string>>                $columns
	 * @param array<string, array<string, array<int, string>>> $keys
	 */
	public function __construct(array $columns, array $keys) {
		parent::__construct('sqlite::memory:');

		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

		$this->columns = $columns;
		$this->keys    = $keys;
	}

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		$query = trim((string) preg_replace('/\s+/', ' ', $query));

		if (preg_match('/^SHOW columns FROM `(\w+)` LIKE \'(\w+)\'$/i', $query, $m)) {
			$rows = in_array($m[2], $this->columns[$m[1]], true) ? [['Field' => $m[2]]] : [];

			return parent::prepare($this->select(['Field'], $rows), $options);
		}

		if (preg_match('/^SHOW KEYS FROM `(\w+)`$/i', $query, $m)) {
			$rows = [];

			foreach ($this->keys[$m[1]] as $name => $columns) {
				foreach ($columns as $column) {
					$rows[] = ['Key_name' => $name, 'Column_name' => $column];
				}
			}

			return parent::prepare($this->select(['Key_name', 'Column_name'], $rows), $options);
		}

		if (preg_match('/^ALTER TABLE `(\w+)` (.+)$/i', $query, $m)) {
			$this->alter($m[1], $m[2]);
			$this->alters[] = $query;

			return parent::prepare('SELECT 1 WHERE 0', $options);
		}

		// db_install_add_cache() stamps install_updated after every step.
		if (str_starts_with($query, 'INSERT INTO settings (name, value) VALUES (?, ?)')) {
			return parent::prepare('INSERT OR REPLACE INTO settings (name, value) VALUES (?, ?)', $options);
		}

		throw new RuntimeException('Unexpected statement: ' . $query);
	}

	/**
	 * @param array<int, string>                $names
	 * @param array<int, array<string, string>> $rows
	 */
	private function select(array $names, array $rows) : string {
		$selects = [];

		foreach ($rows as $row) {
			$selects[] = 'SELECT ' . implode(', ', array_map(fn ($name) => $this->quote($row[$name]) . ' AS ' . $name, $names));
		}

		if (!$selects) {
			return 'SELECT ' . implode(', ', array_map(static fn ($name) => "'' AS " . $name, $names)) . ' WHERE 0';
		}

		return implode(' UNION ALL ', $selects);
	}

	private function alter(string $table, string $changes) : void {
		$columns = $this->columns[$table];
		$keys    = $this->keys[$table];

		if (str_contains($changes, 'DROP PRIMARY KEY')) {
			if (!isset($keys['PRIMARY'])) {
				throw new PDOException("Can't DROP PRIMARY KEY on `$table`; check that it exists");
			}

			unset($keys['PRIMARY']);
		}

		if (str_contains($changes, 'ADD COLUMN `id` bigint(20) unsigned NOT NULL auto_increment FIRST')) {
			if (in_array('id', $columns, true)) {
				throw new PDOException("Duplicate column name 'id' on `$table`");
			}

			array_unshift($columns, 'id');
		}

		if (str_contains($changes, 'ADD PRIMARY KEY (`id`)')) {
			if (isset($keys['PRIMARY'])) {
				throw new PDOException("Multiple primary key defined on `$table`");
			}

			$keys['PRIMARY'] = ['id'];
		}

		if (preg_match('/ADD INDEX (\w+)\(([\w,]+)\)/', $changes, $m)) {
			if (isset($keys[$m[1]])) {
				throw new PDOException("Duplicate key name '$m[1]' on `$table`");
			}

			$keys[$m[1]] = explode(',', $m[2]);
		}

		// An auto_increment column that is not the leading column of a key is
		// rejected by the server, which is why the upgrade adds both at once.
		if (in_array('id', $columns, true) && ($keys['PRIMARY'] ?? []) !== ['id']) {
			throw new PDOException("Incorrect table definition on `$table`; an auto column must be defined as a key");
		}

		$this->columns[$table] = $columns;
		$this->keys[$table]    = $keys;
	}
}

function rrdcheck_user_log_keys_check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/**
 * @return array<int, int> the installer status of every recorded step
 */
function rrdcheck_user_log_keys_run(RrdcheckUserLogKeysConnection $connection) : array {
	global $database_sessions, $database_hostname, $database_port, $database_default;
	global $database_upgrade_status, $cacti_upgrade_version;

	$database_sessions["$database_hostname:$database_port:$database_default"] = $connection;

	$database_upgrade_status = [];
	$connection->alters      = [];

	// The installer prints one progress character per step.
	ob_start();

	try {
		upgrade_rrdcheck_user_log_keys();
	} finally {
		ob_end_clean();
	}

	return array_column($database_upgrade_status[$cacti_upgrade_version] ?? [], 'status');
}

define('CACTI_PATH_BASE', $source);
define('CACTI_PATH_LIBRARY', $source . '/lib');
define('CACTI_PATH_INCLUDE', $source . '/include');
define('CACTI_PATH_INSTALL', $source . '/install');
define('CACTI_PATH_LOG', sys_get_temp_dir());
define('CACTI_WEB', false);
define('CACTI_CLI', true);
define('POLLER_ID', 1);

// Upgrades run in install mode, where schema probes are never served from
// the per-process cache. Each scenario below starts from a different schema.
define('IN_CACTI_INSTALL', 1);

require $source . '/include/global_constants.php';
require $source . '/lib/functions.php';
require $source . '/lib/database.php';
require $source . '/install/functions.php';
require $source . '/install/upgrades/1_3_0.php';

$config                = ['cacti_server_os' => 'unix', 'is_web' => false];
$database_sessions     = [];
$database_hostname     = 'rrdcheck-user-log-keys';
$database_port         = 3306;
$database_default      = 'cacti';
$cacti_upgrade_version = '1.3.0';

$rrdcheck_columns = ['local_data_id', 'test_date', 'message'];
$user_log_columns = ['username', 'user_id', 'time', 'result', 'ip'];

$upgraded_columns = [
	'rrdcheck' => ['id', ...$rrdcheck_columns],
	'user_log' => ['id', ...$user_log_columns],
];

// Compared with ==, because the order keys were added in does not matter.
$upgraded_keys = [
	'rrdcheck' => ['PRIMARY' => ['id'], 'local_data_id' => ['local_data_id']],
	'user_log' => ['PRIMARY' => ['id'], 'user_id' => ['user_id'], 'username_user_id_time' => ['username', 'user_id', 'time']],
];

$add_rrdcheck_id    = 'ALTER TABLE `rrdcheck` ADD COLUMN `id` bigint(20) unsigned NOT NULL auto_increment FIRST, ADD PRIMARY KEY (`id`)';
$add_rrdcheck_index = 'ALTER TABLE `rrdcheck` ADD INDEX local_data_id(local_data_id)';
$add_user_log_id    = 'ALTER TABLE `user_log` DROP PRIMARY KEY, ADD COLUMN `id` bigint(20) unsigned NOT NULL auto_increment FIRST, ADD PRIMARY KEY (`id`)';
$add_user_log_index = 'ALTER TABLE `user_log` ADD INDEX username_user_id_time(username,user_id,time)';

// A database installed from the previous schema.
$connection = new RrdcheckUserLogKeysConnection(
	['rrdcheck' => $rrdcheck_columns, 'user_log' => $user_log_columns],
	['rrdcheck' => [], 'user_log' => ['PRIMARY' => ['username', 'user_id', 'time'], 'user_id' => ['user_id']]]
);

$statuses = rrdcheck_user_log_keys_run($connection);

rrdcheck_user_log_keys_check($connection->alters === [$add_rrdcheck_id, $add_rrdcheck_index, $add_user_log_id, $add_user_log_index], 'Upgrade issued unexpected statements: ' . implode('; ', $connection->alters));
rrdcheck_user_log_keys_check($connection->columns === $upgraded_columns && $connection->keys == $upgraded_keys, 'Upgrade did not reach the fresh install definition');
rrdcheck_user_log_keys_check(!in_array(DB_STATUS_ERROR, $statuses, true) && in_array(DB_STATUS_SUCCESS, $statuses, true), 'Upgrade recorded a failed step');

// The same database again: nothing may be altered.
$statuses = rrdcheck_user_log_keys_run($connection);

rrdcheck_user_log_keys_check($connection->alters === [], 'Second run altered an upgraded schema: ' . implode('; ', $connection->alters));
rrdcheck_user_log_keys_check(array_unique($statuses) === [DB_STATUS_SKIPPED], 'Second run did not skip every step');

// A fresh install already has the new definition.
$connection = new RrdcheckUserLogKeysConnection($upgraded_columns, $upgraded_keys);
$statuses   = rrdcheck_user_log_keys_run($connection);

rrdcheck_user_log_keys_check($connection->alters === [] && array_unique($statuses) === [DB_STATUS_SKIPPED], 'Upgrade altered a fresh install schema');

// An upgrade interrupted after the id columns were added still gets its indexes.
$connection = new RrdcheckUserLogKeysConnection(
	$upgraded_columns,
	['rrdcheck' => ['PRIMARY' => ['id']], 'user_log' => ['PRIMARY' => ['id'], 'user_id' => ['user_id']]]
);

$statuses = rrdcheck_user_log_keys_run($connection);

rrdcheck_user_log_keys_check($connection->alters === [$add_rrdcheck_index, $add_user_log_index], 'Resumed upgrade issued unexpected statements: ' . implode('; ', $connection->alters));
rrdcheck_user_log_keys_check($connection->keys == $upgraded_keys && !in_array(DB_STATUS_ERROR, $statuses, true), 'Resumed upgrade did not add the missing indexes');

print "PASS: rrdcheck and user_log keys upgrade once, skip when present and resume after an interruption\n";
