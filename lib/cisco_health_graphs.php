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

// MD5 values below are stable 32-character Cacti object identifiers, not security hashes.
// Preserve them so existing imports reuse their CDEFs and GPRINT formats.

/** Definitions are identified by portable template hashes, never installation IDs. */
function cisco_health_graph_kinds() : array {
	return [
		'memory'      => ['data' => 'ecb774cc01db293d7ef0c3a6cfe90389', 'graph' => 'd0e843dee0462653833556d6eafa87d3', 'title' => 'Memory Usage - All Pools'],
		'qfp_memory'  => ['data' => '46e7879bd6fd785bb85f3921a292ba23', 'graph' => '252272b74a0120f442f281adc2b9d2ff', 'title' => 'QFP Memory Usage - All Pools'],
		'temperature' => ['data' => 'ea966522026f109473d6cd4085a5d8c6', 'graph' => 'a96490cb87fb4f11757feaba2806593c', 'title' => 'Temperature - All Sensors'],
	];
}

/** Group existing data sources; duplicate pool indexes are ambiguous and must be resolved first. */
function cisco_health_graph_plan(array $rows, bool $temperature) : array {
	$pools = [];

	foreach ($rows as $row) {
		$index = (string)$row['snmp_index'];
		$ds    = $row['data_source_name'];

		if (!in_array($ds, $temperature ? ['temperature'] : ['used', 'free'], true)) {
			continue;
		}

		if (isset($pools[$index]['sources'][$ds])) {
			throw new RuntimeException('Multiple data sources exist for index ' . $index . '; resolve the duplicate before combining.');
		}
		$pools[$index]['name']         = $row['label'] ?: 'Index ' . $index;
		$pools[$index]['sources'][$ds] = (int)$row['id'];
	}
	uksort($pools, 'strnatcmp');

	foreach ($pools as $index => $pool) {
		if (!$temperature && (!isset($pool['sources']['used']) || !isset($pool['sources']['free']))) {
			throw new RuntimeException('Index ' . $index . ' is missing its used/free pair.');
		}
	}

	return $pools;
}

function cisco_health_graph_save(array $record, string $table) : int {
	$id = (int)sql_save($record, $table);

	if (!$id) {
		throw new RuntimeException('Could not save ' . $table . '.');
	}

	return $id;
}

/** Create/update one combined health graph. No data sources or RRD files are created or removed. */
function cisco_health_graph_build(int $host_id, string $kind, bool $apply = false) : array {
	$definition = cisco_health_graph_kinds()[$kind] ?? null;

	if (!$definition || $host_id < 1) {
		throw new InvalidArgumentException('Specify a device ID and memory, qfp_memory or temperature.');
	}
	$host = db_fetch_row_prepared('SELECT description FROM host WHERE id = ?', [$host_id]);

	if (!is_array($host) || !$host) {
		throw new RuntimeException('Device not found.');
	}

	if (!$apply) {
		$rows = db_fetch_assoc_prepared('SELECT dtr.id, dtr.data_source_name, dl.snmp_index,
			(SELECT hsc.field_value FROM host_snmp_cache AS hsc
			WHERE hsc.host_id = dl.host_id AND hsc.snmp_query_id = dl.snmp_query_id
			AND hsc.snmp_index = dl.snmp_index AND hsc.field_name = ? LIMIT 1) AS label
			FROM data_local AS dl
			INNER JOIN data_template AS dt ON dt.id = dl.data_template_id
			INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id = dl.id
			INNER JOIN data_template_data AS dtd ON dtd.local_data_id = dl.id
			WHERE dl.host_id = ? AND dt.hash = ? AND dtd.active = ?
			ORDER BY dl.id, dtr.id', ['healthName', $host_id, $definition['data'], 'on']);
		$temperature = $kind === 'temperature';
		$pools       = cisco_health_graph_plan($rows, $temperature);
		$result      = ['graph_id' => 0, 'pools' => $pools, 'applied' => false];

		return $result;
	}

	$key = 'cisco_health_combined_' . $host_id . '_' . $kind;

	if (!db_fetch_cell_prepared('SELECT GET_LOCK(?, 0)', ['cisco_health_combined_' . $host_id . '_' . $kind])) {
		throw new RuntimeException('Another combined-graph update is running for this device.');
	}

	$transaction_started = false;
	try {
		$rows = db_fetch_assoc_prepared('SELECT dtr.id, dtr.data_source_name, dl.snmp_index,
			(SELECT hsc.field_value FROM host_snmp_cache AS hsc
			WHERE hsc.host_id = dl.host_id AND hsc.snmp_query_id = dl.snmp_query_id
			AND hsc.snmp_index = dl.snmp_index AND hsc.field_name = ? LIMIT 1) AS label
			FROM data_local AS dl
			INNER JOIN data_template AS dt ON dt.id = dl.data_template_id
			INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id = dl.id
			INNER JOIN data_template_data AS dtd ON dtd.local_data_id = dl.id
			WHERE dl.host_id = ? AND dt.hash = ? AND dtd.active = ?
			ORDER BY dl.id, dtr.id', ['healthName', $host_id, $definition['data'], 'on']);
		$temperature = $kind === 'temperature';
		$pools       = cisco_health_graph_plan($rows, $temperature);
		$result      = ['graph_id' => 0, 'pools' => $pools, 'applied' => false];

		if (!$pools) {
			return $result;
		}

		if (!db_begin_transaction()) {
			throw new RuntimeException('Cannot begin graph transaction.');
		}
		$transaction_started = true;
		$id = (int)db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', [$key]);

		if ($id && !db_fetch_cell_prepared('SELECT id FROM graph_local WHERE id = ?', [$id])) {
			$id = 0; // A user deleted the managed graph; rebuild it using existing sources.
		}

		if ($id && !db_fetch_cell_prepared('SELECT id FROM graph_local WHERE id = ? AND host_id = ? AND graph_template_id IN (0, (SELECT id FROM graph_templates WHERE hash = ?))', [$id, $host_id, $definition['graph']])) {
			throw new RuntimeException('The recorded combined graph was removed or changed; refusing to overwrite another graph.');
		}
		$base = db_fetch_row_prepared('SELECT gtg.* FROM graph_templates_graph AS gtg
			INNER JOIN graph_templates AS gt ON gt.id = gtg.graph_template_id
			WHERE gt.hash = ? AND gtg.local_graph_id = 0', [$definition['graph']]);

		if (!is_array($base) || !$base) {
			throw new RuntimeException('Import the Cisco health graph templates first.');
		}

		if (!$id) {
			$id = cisco_health_graph_save(['id' => 0, 'host_id' => $host_id, 'graph_template_id' => (int)$base['graph_template_id']], 'graph_local');
		}

		// Retain the template identity for Cacti's user/group graph permissions.
		if (!db_execute_prepared('UPDATE graph_local SET graph_template_id = ? WHERE id = ?', [(int)$base['graph_template_id'], $id])) {
			throw new RuntimeException('Cannot preserve combined graph template permissions.');
		}
		$base['id'] = (int)db_fetch_cell_prepared('SELECT id FROM graph_templates_graph WHERE local_graph_id = ?', [$id]);

		foreach ($base as $field => $value) {
			if (str_starts_with($field, 't_')) {
				$base[$field] = '';
			}
		}
		$base = array_replace($base, [
			'local_graph_id'  => $id, 'local_graph_template_graph_id' => 0, 'graph_template_id' => (int)$base['graph_template_id'],
			'title'           => '|host_description| - ' . $definition['title'],
			'title_cache'     => $host['description'] . ' - ' . $definition['title'],
			'image_format_id' => 3, 'height' => 200, 'width' => 700, 'base_value' => 1000,
			'auto_scale'      => $temperature ? 'on' : '', 'auto_scale_opts' => 1, 'auto_scale_rigid' => $temperature ? '' : 'on',
			'lower_limit'     => $temperature ? '' : '0', 'upper_limit' => $temperature ? '' : '100',
			'vertical_label'  => $temperature ? 'Temperature (°C)' : 'Memory used (%)',
			'alt_y_grid'      => $temperature ? 'on' : '',
		]);
		$existing = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', [$id]);

		if (is_array($existing) && $existing) {
			$existing['graph_template_id'] = $base['graph_template_id'];
			$base                          = $existing;
		}
		cisco_health_graph_save($base, 'graph_templates_graph');

		if (!db_execute_prepared('DELETE FROM graph_templates_item WHERE local_graph_id = ?', [$id])) {
			throw new RuntimeException('Cannot replace combined graph items.');
		}
		$vdefs = [];

		foreach (['last' => '06bd3cbe802da6a0745ea5ba93af554a', 'average' => '5ce1061a46bb62f36840c80412d2e629', 'maximum' => 'e06ed529238448773038601afb3cf278'] as $name => $hash) {
			$vdefs[$name] = (int)db_fetch_cell_prepared('SELECT id FROM vdef WHERE hash = ?', [$hash]);

			if (!$vdefs[$name]) {
				throw new RuntimeException('Required standard VDEF is missing: ' . $name);
			}
		}
		$last    = $vdefs['last'];
		$formats = [];

		foreach (['value' => $temperature ? '%6.1lf °C' : '%6.2lf%%', 'bytes' => '%8.2lf %sB'] as $name => $text) {
			$formats[$name] = (int)db_fetch_cell_prepared('SELECT id FROM graph_templates_gprint WHERE gprint_text = ? LIMIT 1', [$text]);

			if (!$formats[$name]) {
				$formats[$name] = cisco_health_graph_save(['id' => 0, 'hash' => md5('cisco-health-' . $text), 'name' => 'Cisco Health - ' . $text, 'gprint_text' => $text], 'graph_templates_gprint');
			}
		}
		$sequence = 1;
		$rank     = 0;
		$palette  = $temperature ? ['0072B2', '009E73', 'CC79A7', 'E69F00', 'D55E00', '56B4E9'] : ['E69F00', 'CC6600', 'A65E00', 'F5B041', 'D35400', '804000'];

		foreach ($pools as $pool) {
			$hex   = $palette[$rank % count($palette)];
			$color = (int)db_fetch_cell_prepared('SELECT id FROM colors WHERE hex = ? LIMIT 1', [$hex]);

			if (!$color) {
				$color = cisco_health_graph_save(['id' => 0, 'hex' => $hex], 'colors');
			}
			$cdef = 0;

			if (!$temperature) {
				// Every item uses AVERAGE; each pool introduces used then free exactly once.
				$used_def   = generate_graph_def_name(2 * $rank);
				$free_def   = generate_graph_def_name(2 * $rank + 1);
				$expression = "$used_def,$free_def,+,0,GT,$used_def,$used_def,$free_def,+,/,100,*,UNKN,IF";
				$hash       = md5('cisco-health-percentage-v1-' . $rank);
				$cdef       = (int)db_fetch_cell_prepared('SELECT id FROM cdef WHERE hash = ?', [$hash]);

				if (!$cdef) {
					$cdef = cisco_health_graph_save(['id' => 0, 'hash' => $hash, 'name' => 'Cisco Health - Pool ' . ($rank + 1) . ' Percentage'], 'cdef');
					cisco_health_graph_save(['id' => 0, 'hash' => md5($hash . 'item'), 'cdef_id' => $cdef, 'sequence' => 1, 'type' => 6, 'value' => $expression], 'cdef_items');
				}
			}
			$source = $pool['sources'][$temperature ? 'temperature' : 'used'];
			$items  = [
				['graph_type_id' => 4, 'task_item_id' => $source, 'color_id' => $color, 'cdef_id' => $cdef, 'text_format' => str_replace(['\\', ':', '|'], ['/', '-', '/'], $pool['name']), 'dashes' => $rank >= count($palette) ? '5,3' : ''],
				['graph_type_id' => 9, 'task_item_id' => $source, 'cdef_id' => $cdef, 'vdef_id' => $last, 'gprint_id' => $formats['value'], 'text_format' => 'Current:', 'hard_return' => ''],
			];

			if ($temperature) {
				foreach (['average' => 'Average:', 'maximum' => 'Maximum:'] as $stat => $label) {
					$items[] = ['graph_type_id' => 9, 'task_item_id' => $source, 'vdef_id' => $vdefs[$stat], 'gprint_id' => $formats['value'], 'text_format' => $label, 'hard_return' => $stat === 'maximum' ? 'on' : ''];
				}
			} else {
				$items[] = ['graph_type_id' => 9, 'task_item_id' => $source, 'vdef_id' => $last, 'gprint_id' => $formats['bytes'], 'text_format' => 'Used:'];
				$items[] = ['graph_type_id' => 9, 'task_item_id' => $pool['sources']['free'], 'vdef_id' => $last, 'gprint_id' => $formats['bytes'], 'text_format' => 'Free:', 'hard_return' => 'on'];
			}

			foreach ($items as $item) {
				cisco_health_graph_save(array_replace(['id' => 0, 'local_graph_id' => $id, 'graph_template_id' => 0, 'sequence' => $sequence++, 'consolidation_function_id' => 1, 'alpha' => 'FF', 'text_format' => '', 'value' => '', 'hard_return' => '', 'textalign' => '', 'dashes' => '', 'shift' => ''], $item), 'graph_templates_item');
			}
			$rank++;
		}

		if (!db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$key, (string)$id])) {
			throw new RuntimeException('Cannot record combined graph.');
		}

		if (!db_commit_transaction()) {
			throw new RuntimeException('Cannot commit graph changes.');
		}
		$transaction_started = false;
		$result['graph_id'] = $id;
		$result['applied']  = true;

		return $result;
	} catch (Throwable $error) {
		if ($transaction_started) {
			db_rollback_transaction();
		}

		throw $error;
	} finally {
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', ['cisco_health_combined_' . $host_id . '_' . $kind]);
	}
}

/** Return a combined graph only when it already contains every source for this index. */
function cisco_health_graph_existing(int $host_id, int $template_id, array $query) : array {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates WHERE id = ?', [$template_id]);

	foreach (cisco_health_graph_kinds() as $kind => $definition) {
		if ($hash !== $definition['graph'] || empty($query['snmp_query_id']) || !isset($query['snmp_index'])) {
			continue;
		}
		$id = (int)db_fetch_cell_prepared('SELECT gl.id FROM settings AS s INNER JOIN graph_local AS gl ON gl.id = s.value WHERE s.name = ? AND gl.host_id = ? AND gl.graph_template_id = ?', ['cisco_health_combined_' . $host_id . '_' . $kind, $host_id, $template_id]);

		if (!$id) {
			return [];
		}
		$rows     = db_fetch_assoc_prepared('SELECT dtr.id, dl.id AS local_data_id, dtr.data_source_name FROM data_local AS dl INNER JOIN data_template AS dt ON dt.id = dl.data_template_id INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id = dl.id WHERE dl.host_id = ? AND dl.snmp_query_id = ? AND dl.snmp_index = ? AND dt.hash = ?', [$host_id, $query['snmp_query_id'], $query['snmp_index'], $definition['data']]);
		$required = $kind === 'temperature' ? ['temperature'] : ['used', 'free'];
		$found    = [];
		$data     = [];

		foreach ($rows as $row) {
			if (!in_array($row['data_source_name'], $required, true)) {
				continue;
			}

			if (!db_fetch_cell_prepared('SELECT id FROM graph_templates_item WHERE local_graph_id = ? AND task_item_id = ? LIMIT 1', [$id, $row['id']])) {
				return [];
			}
			$found[]                          = $row['data_source_name'];
			$data[(int)$row['local_data_id']] = (int)$row['local_data_id'];
		}

		if (!array_diff($required, $found)) {
			return ['local_graph_id' => $id, 'local_data_id' => array_values($data)];
		}
	}

	return [];
}

/** Remove graph definitions only after all their data items exist in the combined view. */
function cisco_health_graph_remove_components(int $host_id, int $template_id, int $combined_id, int $component_id) : void {
	require_once __DIR__ . '/api_graph.php';
	require_once __DIR__ . '/utility.php';
	$graphs = db_fetch_assoc_prepared('SELECT id FROM graph_local WHERE host_id = ? AND graph_template_id = ? AND id = ? AND snmp_query_id > 0', [$host_id, $template_id, $component_id]);

	foreach ($graphs as $graph) {
		$items = db_fetch_assoc_prepared('SELECT DISTINCT task_item_id FROM graph_templates_item WHERE local_graph_id = ? AND task_item_id > 0', [$graph['id']]);

		if (!$items) {
			continue;
		}

		foreach ($items as $item) {
			if (!db_fetch_cell_prepared('SELECT id FROM graph_templates_item WHERE local_graph_id = ? AND task_item_id = ? LIMIT 1', [$combined_id, $item['task_item_id']])) {
				continue 2;
			}
		}
		// This API removes graph definitions/references, never data sources or RRD files.
		api_graph_remove((int)$graph['id']);
	}
}

/** Called only after the normal indexed Create Graphs workflow has created its data sources. */
function cisco_health_graph_after_create(int $host_id, int $graph_template_id, int $component_id) : int {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates WHERE id = ?', [$graph_template_id]);

	foreach (cisco_health_graph_kinds() as $kind => $definition) {
		if ($hash !== $definition['graph']) {
			continue;
		}

		try {
			$result = cisco_health_graph_build($host_id, $kind, true);

			if ($result['applied']) {
				cisco_health_graph_remove_components($host_id, $graph_template_id, (int)$result['graph_id'], $component_id);

				return (int)$result['graph_id'];
			}
		} catch (Throwable $error) {
			cacti_log('Combined Cisco health graph failed: ' . $error->getMessage(), false, 'GRAPH');
			raise_message('combined_health_failed', __('The individual graph was kept because the combined graph could not be built. See the Cacti log.'), MESSAGE_LEVEL_ERROR);
		}

		break;
	}

	return $component_id;
}

/** Install native automation rules once, after all Cisco Router dependencies were imported. */
function cisco_health_graph_install_rules() : void {
	$key = 'cisco_health_rule_install';

	if (!db_fetch_cell_prepared('SELECT GET_LOCK(?, 10)', [$key])) {
		throw new RuntimeException('Another Cisco health rule installation is running.');
	}

	try {
		cisco_health_graph_install_rules_unlocked();
	} finally {
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$key]);
	}
}

function cisco_health_graph_install_rules_unlocked() : void {
	$rules = [
		['13c2362427e515a9f361996b16b5beee', 'Cisco Router Health - Cellular Signal Quality', '2d5c00c1e9d4cf7e0a469b1c7f488eca', 'c20d35f3aba333f6050c55b28798031a'],
		['20805250bd2540a60dea90b62a8895c4', 'Cisco Router Health - Cellular Signal Strength', '2d5c00c1e9d4cf7e0a469b1c7f488eca', '6b58aef7a850239f41946aa3a51f58d7'],
		['55b920451faa3510b755a447ec4c837e', 'Cisco Router Health - Memory Usage', 'a6c14c6668afb189b3310883ea826139', '3839c80bfc207626acc27eff7f30e321'],
		['79cf556b8f5345e639879a6cc3ab19bb', 'Cisco Router Health - QFP Memory Usage', '1e5234984161029f057620c81a684f7d', '09559c0417631929b195665de99a3910'],
		['1dfef7f18310b134301276ddf40fafcb', 'Cisco Router Health - QFP Processor Utilisation', 'b12b0d0f58f0487b2246518317fb2e9a', '35f5d4a981c4bdc5ad81448e314a5f37'],
		['0e18464b52169f3a94aea6b02b5f9bcd', 'Cisco Router Health - Temperature', '993324fbe1552cad49c8cc9a0d1911a5', '4eeca9472c73a4c86a547a36ef29ab6b'],
	];

	foreach ($rules as [$hash, $name, $query_hash, $graph_hash]) {
		$key = 'cisco_health_rule_' . $hash;

		if (db_fetch_cell_prepared('SELECT name FROM settings WHERE name = ?', [$key])) {
			continue;
		}
		$query_id = (int)db_fetch_cell_prepared('SELECT id FROM snmp_query WHERE hash = ?', [$query_hash]);
		$graph_id = (int)db_fetch_cell_prepared('SELECT id FROM snmp_query_graph WHERE hash = ? AND snmp_query_id = ?', [$graph_hash, $query_id]);

		if (!$query_id || !$graph_id) {
			continue;
		}
		$existing = (int)db_fetch_cell_prepared('SELECT id FROM automation_graph_rules WHERE name = ?', [$name]);

		if ($existing) {
			// Do not change an administrator's existing rule or enable it again.
			db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$key, (string)$existing]);

			continue;
		}

		if (!db_begin_transaction()) {
			throw new RuntimeException('Cannot begin Cisco health automation installation.');
		}

		try {
			$record         = ['id' => 0, 'name' => $name, 'snmp_query_id' => $query_id, 'graph_type_id' => $graph_id, 'enabled' => 'on'];
			$record['hash'] = $hash;
			$id             = cisco_health_graph_save($record, 'automation_graph_rules');
			$match          = ['id' => 0, 'rule_id' => $id, 'rule_type' => 1, 'sequence' => 1, 'operation' => 0, 'field' => 'ht.hash', 'operator' => 7, 'pattern' => 'cae6a879f86edacb2471055783bec6d0'];
			$match['hash']  = md5('cisco-health-match-' . $hash);
			cisco_health_graph_save($match, 'automation_match_rule_items');

			if (!db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', [$key, (string)$id]) || !db_commit_transaction()) {
				throw new RuntimeException('Cannot save Cisco health automation installation.');
			}
		} catch (Throwable $error) {
			db_rollback_transaction();

			throw $error;
		}
	}
}

/** Serialize source creation as well as combination: two requests must not create the same pool twice. */
function cisco_health_graph_with_creation_lock(int $host_id, int $template_id, callable $create) : mixed {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates WHERE id = ?', [$template_id]);
	$kind = null;

	foreach (cisco_health_graph_kinds() as $name => $definition) {
		if ($definition['graph'] === $hash) {
			$kind = $name;

			break;
		}
	}

	if ($kind === null) {
		return $create();
	}
	$key = 'cisco_health_create_' . $host_id . '_' . $kind;

	if (!db_fetch_cell_prepared('SELECT GET_LOCK(?, 10)', [$key])) {
		raise_message('combined_health_busy', __('Another health graph creation is running for this device. Please try again.'), MESSAGE_LEVEL_ERROR);

		return false;
	}

	try {
		return $create();
	} finally {
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$key]);
	}
}

/** Managed graph items are generated from all discovered pools, not one template instance. */
function cisco_health_graph_is_managed(int $graph_id, int $template_id) : bool {
	$graph = db_fetch_row_prepared('SELECT gl.host_id, gt.hash FROM graph_local AS gl INNER JOIN graph_templates AS gt ON gt.id = gl.graph_template_id WHERE gl.id = ? AND gl.graph_template_id = ?', [$graph_id, $template_id]);

	if (!is_array($graph) || !$graph) {
		return false;
	}

	foreach (cisco_health_graph_kinds() as $kind => $definition) {
		if ($graph['hash'] === $definition['graph']) {
			return (int)db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', ['cisco_health_combined_' . $graph['host_id'] . '_' . $kind]) === $graph_id;
		}
	}

	return false;
}
