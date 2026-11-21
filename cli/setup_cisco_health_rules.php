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

/** Install ordinary graph rules after importing the Cisco health templates.
 * Run from the Cacti root. Default is a read-only plan; --apply installs rules.
 * No device, graph, global setting, or existing rule is changed.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
$options = array_slice($argv, 1);
if (array_diff($options, ['--apply', '--help'])) {
	fwrite(STDERR, "Unknown option\n");
	exit(1);
}
if (in_array('--help', $options, true)) {
	echo "From the Cacti root: php cli/setup_cisco_health_rules.php [--apply]\n";
	exit;
}
$apply = in_array('--apply', $options, true);
$root = dirname(__DIR__);
if (!is_file($root . '/include/cli_check.php')) {
	fwrite(STDERR, "Install under Cacti's cli directory first.\n");
	exit(1);
}
chdir($root);
require 'include/cli_check.php';
require_once 'lib/api_automation.php';
$spec = json_decode(file_get_contents(__DIR__ . '/cisco_health_rules.json'), true, 512, JSON_THROW_ON_ERROR);
$locked = false;
$transaction = false;
try {
	// CLI bootstrap uses a remote collector's local database by default.
	if ((int)($config['poller_id'] ?? 1) > 1) {
		if (empty($remote_db_cnn_id) || !db_switch_remote_to_main() || (int)db_fetch_cell('SELECT 1') !== 1) {
			throw new RuntimeException('Cannot reach the main Cacti database. Run setup on the main server; no rules were written.');
		}
	}
	if (count($spec) !== 27) {
		throw new RuntimeException('Expected 27 packaged rules.');
	}
	$template = (int) db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash=?', ['cae6a879f86edacb2471055783bec6d0']);
	if (!$template) {
		throw new RuntimeException('Import the Cisco Router package first.');
	}
	if ($apply) {
		$locked = (int) db_fetch_cell("SELECT GET_LOCK('cisco_health_rules_setup', 10)") === 1;
		if (!$locked) {
			throw new RuntimeException('Another setup is running.');
		}
		foreach (['automation_graph_rules', 'automation_match_rule_items', 'automation_graph_rule_items'] as $table) {
			$engine = db_fetch_cell_prepared('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
			if (strtolower((string) $engine) !== 'innodb') {
				throw new RuntimeException('Transactional tables required: ' . $table);
			}
		}
	}
	$linkedRules = db_table_exists('automation_templates_rules') ? db_fetch_assoc_prepared('SELECT atr.rule_id
		FROM automation_templates_rules AS atr
		INNER JOIN automation_templates AS at ON at.id=atr.template_id
		WHERE at.host_template=? AND atr.rule_type=1', [$template]) : [];
	$restricted = (bool)$linkedRules;
	$linkedIds = array_map('intval', array_column($linkedRules, 'rule_id'));
	$plan = [];
	foreach ($spec as $item) {
		$qid = (int) db_fetch_cell_prepared('SELECT id FROM snmp_query WHERE hash=?', [$item['query_hash']]);
		$variants = db_fetch_assoc_prepared('SELECT id FROM snmp_query_graph WHERE snmp_query_id=? AND hash=?', [$qid, $item['variant_hash']]);
		if (!$qid || count($variants) !== 1) {
			throw new RuntimeException('Missing imported query/variant: ' . $item['graph_type']);
		}
		if (!db_fetch_cell_prepared('SELECT COUNT(*) FROM host_template_snmp_query WHERE host_template_id=? AND snmp_query_id=?', [$template, $qid])) {
			throw new RuntimeException('Query is not associated with Cisco Router: ' . $item['query']);
		}
		$gid = (int) $variants[0]['id'];
		$name = 'Cisco Health: ' . $item['graph_type'];
		$device = ['sequence' => '1', 'operation' => '0', 'field' => 'h.host_template_id', 'operator' => (string) AUTOMATION_OP_MATCHES, 'pattern' => (string) $template];
		$objects = $item['field'] === null ? [] : [['sequence' => '1', 'operation' => '0', 'field' => $item['field'], 'operator' => (string) AUTOMATION_OP_MATCHES, 'pattern' => (string) $item['matches']]];
		$existing = db_fetch_assoc_prepared('SELECT id,name,enabled,snmp_query_id,graph_type_id FROM automation_graph_rules WHERE (snmp_query_id=? AND graph_type_id=?) OR name=?', [$qid, $gid, $name]);
		$found = false;
		$applicable = !$restricted;
		foreach ($existing as $rule) {
			$match = db_fetch_assoc_prepared('SELECT sequence,operation,field,operator,pattern FROM automation_match_rule_items WHERE rule_id=? AND rule_type=? ORDER BY sequence,id', [$rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH]);
			$criteria = db_fetch_assoc_prepared('SELECT sequence,operation,field,operator,pattern FROM automation_graph_rule_items WHERE rule_id=? ORDER BY sequence,id', [$rule['id']]);
			// Compare values rather than driver-dependent integer/string types.
			if ((int) $rule['snmp_query_id'] === $qid && (int) $rule['graph_type_id'] === $gid && $rule['enabled'] === 'on' && $match == [$device] && $criteria == $objects) {
				$found = true;
				$applicable = $applicable || in_array((int)$rule['id'], $linkedIds, true);
			} else {
				throw new RuntimeException('Review existing rule ' . $rule['id'] . ' for ' . $item['graph_type'] . '; it will not be overwritten.');
			}
		}
		if (!$applicable) {
			throw new RuntimeException('Cisco Router uses linked Automation Template rules. Create and link ' . $item['graph_type'] . ' through Automation Templates, then rerun setup. No rules were written.');
		}
		$plan[] = compact('qid', 'gid', 'name', 'device', 'objects', 'found');
		echo ($found ? 'KEEP ' : ($apply ? 'ADD  ' : 'PLAN ')) . $item['graph_type'] . PHP_EOL;
	}
	$added = 0;
	if ($apply) {
		if (!db_execute('START TRANSACTION')) {
			throw new RuntimeException('Cannot start transaction.');
		}
		$transaction = true;
		foreach ($plan as $row) {
			if ($row['found']) {
				continue;
			}
			$id = sql_save(['name' => $row['name'], 'snmp_query_id' => $row['qid'], 'graph_type_id' => $row['gid'], 'enabled' => 'on'], 'automation_graph_rules');
			if (!$id || !sql_save(['rule_id' => $id, 'rule_type' => AUTOMATION_RULE_TYPE_GRAPH_MATCH] + $row['device'], 'automation_match_rule_items')) {
				throw new RuntimeException('Rule write failed.');
			}
			foreach ($row['objects'] as $criteria) {
				if (!sql_save(['rule_id' => $id] + $criteria, 'automation_graph_rule_items')) {
					throw new RuntimeException('Criteria write failed.');
				}
			}
			$added++;
		}
		if (!db_execute('COMMIT')) {
			throw new RuntimeException('Commit failed.');
		}
		$transaction = false;
	}
	echo ($apply ? "Installed $added new rules. " : 'Plan only; no changes. ') . "Existing rules preserved.\n";
	echo "Global automatic graph creation is " . (read_config_option('automation_graphs_enabled') === '' ? 'OFF; enable it in Settings to use these rules.' : 'ON.') . "\n";
} catch (Throwable $e) {
	if ($transaction) {
		db_execute('ROLLBACK');
	}
	fwrite(STDERR, $e->getMessage() . "\n");
	if ($locked) {
		db_fetch_cell("SELECT RELEASE_LOCK('cisco_health_rules_setup')");
	}
	exit(1);
}
if ($locked) {
	db_fetch_cell("SELECT RELEASE_LOCK('cisco_health_rules_setup')");
}
