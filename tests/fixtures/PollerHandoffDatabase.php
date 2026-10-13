<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Real database boundaries for the existing isolated poller probe.
$handoff_database = getenv('BOOST_DB_NAME') ?: 'cacti_boost_contract';
if ($handoff_database !== 'cacti_boost_contract') {
	throw new RuntimeException('Handoff tests require the dedicated cacti_boost_contract database.');
}
$handoff_dsn = 'mysql:host=' . (getenv('BOOST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';dbname=' . $handoff_database;
$handoff_pdo = new PDO($handoff_dsn, getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$remote_db_cnn_id = new PDO($handoff_dsn, getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$handoff_prefix = 'handoff_' . bin2hex(random_bytes(6)) . '_';
$handoff_tables = ['data_input', 'poller_output_boost_arch_1', 'poller_output_boost_local_data_ids', 'poller_output_boost', 'poller_data_template_field_mappings', 'data_source_profiles_rra', 'data_source_profiles_cf', 'data_source_profiles', 'data_template_data', 'data_template_rrd', 'data_input_fields', 'graph_templates_item', 'poller_output', 'poller_item', 'data_local'];
$handoff_dir = sys_get_temp_dir() . '/' . $handoff_prefix;
mkdir($handoff_dir, 0700);
$handoff_rrd = $handoff_dir . '/source.rrd';
$probe_rrd_binary = getenv('RRDTOOL_TEST_BINARY');
file_put_contents($handoff_dir . '/global_arrays.php', '<?php $data_source_types = [1 => "GAUGE"]; $consolidation_functions = [1 => "AVERAGE"];');

function handoff_sql($sql) {
	foreach ($GLOBALS['handoff_tables'] as $table) {
		$sql = preg_replace('/\b' . $table . '\b/', $GLOBALS['handoff_prefix'] . $table, $sql);
	}
	return $sql;
}

function handoff_fetch($sql, $params = [], $conn = false) {
	global $state, $scenario;
	if (str_contains($sql, 'FROM poller_output AS po')) $state['queries']++;
	if (str_contains($sql, 'poller_data_template_field_mappings')) {
		$state['mapping_queries']++;
		if ($scenario === 'integration-scheduled-mapping-retry' && $state['mapping_queries'] === 1) return false;
	}
	if (str_contains($sql, 'LEFT JOIN graph_templates_item')) {
		$state['orphan_queries'] = ($state['orphan_queries'] ?? 0) + 1;
		if ($scenario === 'integration-drain-orphan-retry' && $state['orphan_queries'] === 1) return false;
	}
	if (str_contains($sql, 'INNER JOIN data_input_fields')) {
		$state['manual_queries'] = ($state['manual_queries'] ?? 0) + 1;
		if (in_array($scenario, ['integration-manual-retry', 'integration-drain-manual-retry'], true) && $state['manual_queries'] === 1) return false;
	}
	if ($scenario === 'integration-owner-failure' && str_contains($sql, 'WHERE poller_id = ?')) return false;
	$pdo = $conn instanceof PDO ? $conn : $GLOBALS['handoff_pdo'];
	$statement = $pdo->prepare(handoff_sql($sql));
	$statement->execute($params);
	return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function handoff_execute($sql, $params = [], $conn = false) {
	global $state, $scenario;
	if (in_array($scenario, ['integration-archive-copy-retry', 'integration-archive-active-copy-retry'], true) && str_starts_with($sql, 'INSERT IGNORE INTO poller_output_boost_temp_') && ($scenario === 'integration-archive-copy-retry' || preg_match('/FROM poller_output_boost\s/', $sql)) && !isset($state['archive_copy_failed'])) {
		$state['archive_copy_failed'] = true;
		return false;
	}
	if (str_starts_with($sql, 'DELETE FROM poller_output WHERE') || str_starts_with($sql, 'DELETE FROM poller_output_boost\n') || preg_match('/^DELETE FROM poller_output_boost\s+WHERE/', $sql)) {
		$state['delete_calls']++;
        if ($scenario === 'integration-drain-arrival' && $state['delete_calls'] === 1) {
            $incoming = $GLOBALS['handoff_pdo']->prepare(handoff_sql('INSERT INTO poller_output_boost VALUES (1,"in",FROM_UNIXTIME(?),"43")'));
            $incoming->execute([time()-30]);
        }

		if (in_array($scenario, ['integration-delete-retry', 'integration-drain-delete-retry'], true) && $state['delete_calls'] === 1) return false;
	}
	if (str_contains($sql, 'INSERT IGNORE INTO poller_output_boost')) {
		$state['boost_calls']++;
		if ($scenario === 'integration-stage-retry' && $state['boost_calls'] === 1) return false;
	}
	$pdo = $conn instanceof PDO ? $conn : $GLOBALS['handoff_pdo'];
	$statement = $pdo->prepare(handoff_sql($sql));
	$statement->execute($params);
	$GLOBALS['handoff_affected'] = $statement->rowCount();
	return true;
}

function handoff_seed(&$config, &$state, $time) {
	global $scenario, $handoff_dir, $handoff_rrd, $handoff_pdo;
	$schema = [
		'data_input' => 'id INT, type_id INT',
		'poller_output' => 'local_data_id INT, rrd_name VARCHAR(30), time DATETIME, output VARCHAR(255), PRIMARY KEY(local_data_id, rrd_name, time)',
		'poller_output_boost' => 'local_data_id INT, rrd_name VARCHAR(30), time DATETIME, output VARCHAR(255), PRIMARY KEY(local_data_id, rrd_name, time)',
		'poller_item' => 'local_data_id INT, rrd_name VARCHAR(30), rrd_path VARCHAR(255), rrd_num INT, poller_id INT',
		'data_local' => 'id INT, data_template_id INT, host_id INT, snmp_query_id INT, snmp_index VARCHAR(20)',
		'poller_data_template_field_mappings' => 'data_template_id INT, data_name VARCHAR(30), data_source_names VARCHAR(30)',
		'data_template_rrd' => 'id INT, local_data_id INT, local_data_template_rrd_id INT, data_source_name VARCHAR(30), data_input_field_id INT, rrd_heartbeat INT, rrd_minimum VARCHAR(30), rrd_maximum VARCHAR(30), data_source_type_id INT, data_template_id INT',
		'graph_templates_item' => 'task_item_id INT',
		'data_input_fields' => 'id INT, data_name VARCHAR(30), data_input_id INT, input_output VARCHAR(3), update_rra CHAR(2)',
		'data_template_data' => 'local_data_id INT, rrd_step INT, data_source_profile_id INT, data_input_id INT',
		'data_source_profiles' => 'id INT, x_files_factor DOUBLE',
		'data_source_profiles_rra' => 'data_source_profile_id INT, steps INT, `rows` INT',
		'data_source_profiles_cf' => 'data_source_profile_id INT, consolidation_function_id INT'
	];
	foreach ($schema as $table => $columns) handoff_execute("CREATE TABLE $table ($columns) ENGINE=InnoDB");
	$manual = in_array($scenario, ['integration-manual-retry', 'integration-drain-manual-retry'], true);
	$remote = !in_array($scenario, ['integration-create', 'integration-manual-retry'], true);
	$config['include_path'] = $handoff_dir;
	$config['rra_path'] = $handoff_dir;
	$config['is_web'] = false;
	$config['poller_id'] = $remote ? 2 : 1;
	$config['force_storage_location_local'] = true;
	$assignment = in_array($scenario, ['integration-owner-mismatch', 'integration-owner-change'], true) ? 3 : $config['poller_id'];
	handoff_execute('INSERT INTO data_local VALUES (1, ?, 1, 0, "")', [$manual ? 0 : 1]);
	handoff_execute('INSERT INTO poller_item VALUES (1, ?, ?, 1, ?)', [$manual ? '' : 'in', $handoff_rrd, $assignment]);
	handoff_execute('INSERT INTO poller_output VALUES (1, ?, FROM_UNIXTIME(?), ?)', [$manual ? '' : 'in', $time, $manual ? 'value:42' : '42']);
	handoff_execute('INSERT INTO poller_data_template_field_mappings VALUES (1, "value", "in")');
	handoff_execute('INSERT INTO data_template_rrd VALUES (1, 1, 1, "in", 1, 120, "U", "U", 1, ?)',$manual ? [0] : [1]);
	handoff_execute('INSERT INTO graph_templates_item VALUES (1)');
	handoff_execute('INSERT INTO data_input VALUES (1, 1)');
	handoff_execute('INSERT INTO data_input_fields VALUES (1, "value", 1, "out", "on")');
	handoff_execute('INSERT INTO data_template_data VALUES (1, 60, 1, 1)');
	handoff_execute('INSERT INTO data_source_profiles VALUES (1, 0.5)');
	handoff_execute('INSERT INTO data_source_profiles_rra VALUES (1, 1, 10)');
	handoff_execute('INSERT INTO data_source_profiles_cf VALUES (1, 1)');
	$state['rows'] = [];
}

function handoff_verdict() {
	global $handoff_rrd, $remote_db_cnn_id;
	$staged = handoff_fetch('SELECT output FROM poller_output_boost', [], $remote_db_cnn_id);
	$last = null;
	if (file_exists($handoff_rrd)) {
		$process = proc_open([getenv('RRDTOOL_TEST_BINARY'), 'lastupdate', $handoff_rrd], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
		$last = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		if (proc_close($process) !== 0) throw new RuntimeException('Unable to verify stored RRD values.');
	}
	return ['staged' => $staged, 'rrd_exists' => file_exists($handoff_rrd), 'lastupdate' => $last];
}

register_shutdown_function(static function () {
	if (function_exists('rrdtool_acknowledged_close')) rrdtool_acknowledged_close();
	foreach ($GLOBALS['handoff_tables'] as $table) $GLOBALS['handoff_pdo']->exec('DROP TABLE IF EXISTS ' . $GLOBALS['handoff_prefix'] . $table);
	foreach (glob($GLOBALS['handoff_dir'] . '/*') as $file) unlink($file);
	rmdir($GLOBALS['handoff_dir']);
});
