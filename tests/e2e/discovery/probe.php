<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
declare(strict_types = 1);

// CLI-only observer for the disposable Docker database, never a web endpoint.
if (PHP_SAPI !== 'cli' || getenv('CACTI_DISCOVERY_FIXTURE') !== '1' || getenv('CACTI_DB_HOST') !== 'mariadb') {
	exit(1);
}
$command         = $argv[1] ?? '';
$argv            = [$argv[0]];
$_SERVER['argv'] = $argv;
require dirname(__DIR__, 3) . '/include/cli_check.php';

if ($database_hostname !== 'mariadb' || $database_default !== 'cacti') {
	throw new RuntimeException('Discovery fixture requires the disposable Docker connection');
}

if ($command === 'seed' || $command === 'prune-defaults') {
	db_execute('DELETE FROM automation_networks');
	db_execute('DELETE FROM automation_snmp_items');
	db_execute('DELETE FROM automation_snmp');
}

if ($command === 'seed') {
	foreach (['path_php_binary' => PHP_BINARY, 'path_webroot' => CACTI_PATH_BASE, 'path_snmpget' => '/usr/bin/snmpget', 'path_snmpwalk' => '/usr/bin/snmpwalk', 'path_snmpbulkwalk' => '/usr/bin/snmpbulkwalk', 'path_rrdtool' => '/usr/bin/rrdtool', 'auth_method' => '1', 'guest_user' => '0', 'automation_graphs_enabled' => 'on', 'automation_enabled' => 'on', 'log_verbosity' => '5'] as $name => $value) {
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
	}
	db_execute_prepared('UPDATE user_auth SET password = ?, must_change_password = "", enabled = "on" WHERE id = 1', [password_hash('discovery-admin-fixture', PASSWORD_DEFAULT)]);
	// A normal local account with no automation realms protects actual UI gates.
	db_execute_prepared('INSERT INTO user_auth (username,password,realm,enabled,must_change_password) VALUES (?, ?, 0, "on", "")', ['discovery-reader', password_hash('discovery-reader-fixture', PASSWORD_DEFAULT)]);
	db_execute_prepared('INSERT INTO user_auth_realm (user_id,realm_id) SELECT id,7 FROM user_auth WHERE username = ?', ['discovery-reader']);
	// Packages are imported by the normal CLI before this seed. No synthetic templates.
	$profile = db_fetch_row('SELECT id,name FROM host_template WHERE name LIKE "%Generic SNMP%" LIMIT 1');

	if (!$profile) {
		throw new RuntimeException('Generic SNMP package was not imported');
	}
	// Scalar graph auto-creation is unrelated to the selective interface scenario.
	db_execute_prepared('DELETE FROM host_template_graph WHERE host_template_id = ?', [$profile['id']]);
}

$data = [
	'profiles'         => db_fetch_assoc('SELECT id,name FROM host_template ORDER BY id'),
	'queries'          => db_fetch_assoc('SELECT id,name FROM snmp_query ORDER BY id'),
	'graph_types'      => db_fetch_assoc('SELECT id,snmp_query_id,name FROM snmp_query_graph ORDER BY id'),
	'credentials'      => db_fetch_assoc('SELECT id,name FROM automation_snmp ORDER BY id'),
	'credential_items' => db_fetch_assoc('SELECT id,snmp_id,snmp_timeout FROM automation_snmp_items ORDER BY id'),
	'rules'            => db_fetch_assoc('SELECT id,host_template,sysDescr FROM automation_templates ORDER BY id'),
	'graph_criteria'   => db_fetch_assoc('SELECT id,rule_id,field,hash FROM automation_graph_rule_items ORDER BY id'),
	'match_criteria'   => db_fetch_assoc('SELECT id,rule_id,field,hash FROM automation_match_rule_items ORDER BY id'),
	'graph_rules'      => db_fetch_assoc('SELECT id,name FROM automation_graph_rules ORDER BY id'),
	'networks'         => db_fetch_assoc('SELECT id,name,subnet_range,total_ips,last_started,last_runtime FROM automation_networks ORDER BY id'),
	'devices'          => db_fetch_assoc('SELECT id,network_id,host_id,ip,sysName,snmp FROM automation_devices ORDER BY ip'),
	'hosts'            => db_fetch_assoc('SELECT id,hostname,description,host_template_id,snmp_version FROM host ORDER BY id'),
	'graphs'           => db_fetch_assoc('SELECT id,host_id,snmp_query_id,snmp_index FROM graph_local ORDER BY id'),
	'processes'        => db_fetch_assoc('SELECT network_id,task,status FROM automation_processes'),
	'data_sources'     => (int) db_fetch_cell('SELECT COUNT(*) FROM data_local'),
];
print json_encode($data, JSON_THROW_ON_ERROR) . PHP_EOL;
