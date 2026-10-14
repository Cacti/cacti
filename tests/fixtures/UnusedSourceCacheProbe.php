<?php
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/
function probe_load(string $path, string $name) : void {
	cacti_fixture_source_function($path, $name, __NAMESPACE__);
}
$scenario = $argv[1] ?? 'eligibility';
if ((getenv('BOOST_DB_NAME') ?: 'cacti_boost_contract') !== 'cacti_boost_contract') throw new RuntimeException('Use the dedicated contract database.');
$dsn = 'mysql:host=' . getenv('BOOST_DB_HOST') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';dbname=cacti_boost_contract';
$pdo = new PDO($dsn, getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$remote = new PDO($dsn, getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$observer = new PDO($dsn, getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = 'unused_' . bin2hex(random_bytes(6)) . '_';
$tables = ['poller_data_source_unused', 'data_input_fields', 'data_template_data', 'data_template_rrd', 'graph_templates_item', 'data_input', 'data_local', 'settings', 'poller', 'host'];
$database_hostname = 'contract'; $database_port = '3306'; $database_default = 'contract';
$database_sessions = ['contract:3306:contract' => $pdo];
define('DATA_INPUT_TYPE_SCRIPT', 1); define('DATA_INPUT_TYPE_PHP_SCRIPT_SERVER', 5);
function cache_sql($sql, $conn = false) {
	global $prefix, $tables, $remote;
	foreach ($tables as $table) $sql = preg_replace('/\b' . $table . '\b/', $prefix . ($conn === $remote ? 'remote_' : '') . $table, $sql);
	return $sql;
}
function cache_connection($conn) { return $conn ?: $GLOBALS['database_sessions']['contract:3306:contract']; }
function db_fetch_assoc($sql, $log = true, $conn = false) { return cache_connection($conn)->query(cache_sql($sql, $conn))->fetchAll(PDO::FETCH_ASSOC); }
function db_fetch_assoc_prepared($sql, $params = [], $log = true, $conn = false) {
	if (!empty($GLOBALS['cache_race']) && str_contains($sql, 'SELECT value FROM settings')) {
		$GLOBALS['observer']->exec("UPDATE " . $GLOBALS['prefix'] . "settings SET value='changed' WHERE name='poller_unused_cache_generation'");
	}
	$s = cache_connection($conn)->prepare(cache_sql($sql, $conn)); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function db_fetch_cell($sql, $log = true, $conn = false) { return cache_connection($conn)->query(cache_sql($sql, $conn))->fetchColumn(); }
function db_fetch_cell_prepared($sql, $params = [], $log = true, $conn = false) { $s=cache_connection($conn)->prepare(cache_sql($sql,$conn));$s->execute($params);return $s->fetchColumn(); }
function db_execute($sql, $log = true, $conn = false) {
	$ok = cache_connection($conn)->exec(cache_sql($sql, $conn)) !== false;
	if (!empty($GLOBALS['cache_observe']) && $sql === 'DELETE FROM poller_data_source_unused') {
		$GLOBALS['during_delete'] = $GLOBALS['observer']->query(cache_sql('SELECT local_data_id, data_source_name FROM poller_data_source_unused ORDER BY local_data_id'))->fetchAll(PDO::FETCH_ASSOC);
	}
	return $ok;
}
function db_execute_prepared($sql, $params = [], $log = true, $conn = false) {
	if (!empty($GLOBALS['cache_fail_insert']) && str_starts_with($sql,'INSERT INTO poller_data_source_unused')) return false;
	$s=cache_connection($conn)->prepare(cache_sql($sql,$conn));return $s->execute($params);
}
function db_table_exists($name) { return db_fetch_cell("SHOW TABLES LIKE '" . $name . "'") !== false; }
function db_begin_transaction($conn = false) { return cache_connection($conn)->beginTransaction(); }
function db_commit_transaction($conn = false) { return cache_connection($conn)->commit(); }
function db_rollback_transaction($conn = false) { return cache_connection($conn)->rollBack(); }
function cacti_log(...$args) {}
function cache_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$root = dirname(__DIR__, 2);
foreach (['api_data_source_cache_crc_update', 'api_data_source_cache_unused', 'api_data_source_cache_unused_lock', 'api_data_source_cache_unused_unlock', 'api_data_source_cache_unused_invalidate', 'api_data_source_cache_unused_update'] as $fn) probe_load($root . '/lib/api_data_source.php', $fn);
foreach (['boost_load_unused_data_source_names','boost_get_unused_data_source_names'] as $fn) probe_load($root . '/lib/boost.php', $fn);
probe_load($root . '/lib/poller.php', 'poller_replicate_unused_sources');
$schema = [
	'settings'=>'name VARCHAR(128) PRIMARY KEY, value VARCHAR(255)',
	'poller_data_source_unused'=>'local_data_id INT, data_source_name VARCHAR(19), PRIMARY KEY(local_data_id,data_source_name)',
	'poller'=>'id INT', 'host'=>'id INT, poller_id INT, deleted CHAR(2)',
	'data_input'=>'id INT, type_id INT',
	'data_input_fields'=>'id INT, data_input_id INT, input_output VARCHAR(3), update_rra CHAR(2)',
	'data_local'=>'id INT, snmp_query_id INT, host_id INT',
	'data_template_data'=>'local_data_id INT, data_input_id INT',
	'data_template_rrd'=>'id INT, local_data_id INT, data_template_id INT, data_source_name VARCHAR(19)',
	'graph_templates_item'=>'task_item_id INT'
];
try {
	foreach ($schema as $table=>$columns) db_execute("CREATE TABLE $table ($columns) ENGINE=InnoDB");
	foreach (['settings','poller_data_source_unused'] as $table) db_execute("CREATE TABLE $table (" . $schema[$table] . ') ENGINE=InnoDB', true, $remote);
	db_execute("INSERT INTO data_input VALUES (1,1),(2,5),(3,1),(4,2)");
	db_execute("INSERT INTO data_input_fields VALUES (1,1,'out','on'),(2,1,'out','on'),(3,2,'out','on'),(4,2,'out','on'),(5,3,'out','on'),(6,4,'out','on'),(7,4,'out','on')");
	db_execute("INSERT INTO data_local VALUES (1,0,1),(2,0,2),(3,7,1),(4,0,1),(5,0,1),(6,0,1)");
	db_execute("INSERT INTO host VALUES (1,2,''),(2,3,'')");
	db_execute("INSERT INTO data_template_data VALUES (1,1),(2,2),(3,1),(4,3),(5,4),(6,1)");
	foreach ([1,2,3,4,5,6] as $id) {
		db_execute_prepared('INSERT INTO data_template_rrd VALUES (?, ?, ?, ?)',[$id*2-1,$id,$id===6 ? 0 : 1,'used']);
		db_execute_prepared('INSERT INTO data_template_rrd VALUES (?, ?, ?, ?)',[$id*2,$id,$id===6 ? 0 : 1,'unused']);
		db_execute_prepared('INSERT INTO graph_templates_item VALUES (?)',[$id*2-1]);
	}
	cache_check(api_data_source_cache_unused(),'Initial publish failed');
	$expected=[['local_data_id'=>1,'data_source_name'=>'unused'],['local_data_id'=>2,'data_source_name'=>'unused']];
	cache_check(db_fetch_assoc('SELECT local_data_id,data_source_name FROM poller_data_source_unused ORDER BY local_data_id')===$expected,'Eligibility included a data query, manual, single-output or SNMP source');
	if ($scenario==='publish-failure') {
		$before=db_fetch_cell("SELECT value FROM settings WHERE name='poller_unused_cache_generation'");
		$cache_fail_insert=true;$cache_observe=true;
		cache_check(!api_data_source_cache_unused(),'Failed insert was accepted');
		cache_check($during_delete===$expected,'Concurrent reader observed an empty rebuild');
		cache_check(db_fetch_assoc('SELECT local_data_id,data_source_name FROM poller_data_source_unused ORDER BY local_data_id')===$expected,'Failed rebuild changed exclusions');
		cache_check(db_fetch_cell("SELECT value FROM settings WHERE name='poller_unused_cache_generation'")==='', 'Failed rebuild published a generation');
	} elseif ($scenario==='graph-lifecycle') {
		cache_check(boost_get_unused_data_source_names(1)===['unused'=>'unused'],'Initial reader missed exclusions');
		api_data_source_cache_unused_update(1);
		cache_check(boost_load_unused_data_source_names(true)===false,'Reader accepted unpublished metadata');
		db_execute('INSERT INTO graph_templates_item VALUES (2)');
		cache_check(api_data_source_cache_unused(),'Graph refresh failed');
		cache_check(boost_load_unused_data_source_names(true)!==false && boost_get_unused_data_source_names(1)===[],'Reader retained stale orphan');
		$database_sessions['contract:3306:contract']=$observer;$GLOBALS['poller_unused_cache_lock']=false;
		$blocked=false;try {api_data_source_cache_unused_lock(0);} catch(RuntimeException $e) {$blocked=true;}
		$database_sessions['contract:3306:contract']=$pdo;$GLOBALS['poller_unused_cache_lock']=true;
		cache_check($blocked,'Concurrent management edit bypassed publication lock');
	} elseif ($scenario==='remote' || $scenario==='remote-race') {
		$generation=db_fetch_cell("SELECT value FROM settings WHERE name='poller_unused_cache_generation'");
		db_execute("INSERT INTO settings VALUES ('poller_unused_cache_generation','')",true,$remote);
		db_execute("INSERT INTO poller_data_source_unused VALUES (99,'old')",true,$remote);
		$cache_race=$scenario==='remote-race';
		$ok=poller_replicate_unused_sources($remote,2,$generation);
		cache_check($ok===!$cache_race,'Replication accepted an inconsistent generation');
		$rows=db_fetch_assoc('SELECT local_data_id,data_source_name FROM poller_data_source_unused',true,$remote);
		cache_check($rows===($cache_race ? [['local_data_id'=>99,'data_source_name'=>'old']] : [$expected[0]]),'Remote membership or rollback is wrong');
		cache_check(db_fetch_cell("SELECT value FROM settings WHERE name='poller_unused_cache_generation'",true,$remote)===($cache_race ? '' : $generation),'Remote generation was published prematurely');
	}
	echo json_encode(['scenario'=>$scenario,'passed'=>true]),"\n";
} finally {
	$GLOBALS['poller_unused_cache_pending']=false;api_data_source_cache_unused_unlock();
	if ($pdo->inTransaction()) $pdo->rollBack();
	if ($remote->inTransaction()) $remote->rollBack();
	foreach (array_keys($schema) as $table) db_execute('DROP TABLE IF EXISTS ' . $table);
	foreach (['settings','poller_data_source_unused'] as $table) db_execute('DROP TABLE IF EXISTS '.$table,true,$remote);
}
