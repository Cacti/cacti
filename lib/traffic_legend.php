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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Clarify the standard interface traffic legends without changing RRD data.
 * Template hashes identify stock templates independently of installation IDs.
 */
function traffic_legend_plan($rows, $template_peaks = array()) {
	$ordered = array();
	$used = array();
	foreach (array('Inbound', 'Outbound') as $direction) {
		$average = null;
		foreach ($rows as $row) {
			if ($row['text_format'] == $direction && $row['consolidation_function_id'] == 1) {
				$average = $row;
			}
		}
		if (!$average) {
			return false;
		}
		$task = $average['task_item_id'];
		$block = array($average);
		$maximum = null;
		$peak = null;
		$total = null;
		foreach (array('Current:', 'Average:', 'Maximum:') as $label) {
			$matches = array();
			foreach ($rows as $row) {
				if ($row['task_item_id'] == $task && $row['graph_type_id'] == 9 && $row['text_format'] == $label) {
					$matches[] = $row;
				}
			}
			if (count($matches) != 1) {
				return false;
			}
			$block[] = $matches[0];
			if ($label == 'Maximum:') {
				$maximum = $matches[0];
			}
		}
		foreach ($rows as $row) {
			if ($row['task_item_id'] != $task) {
				continue;
			}
			if ($row['graph_type_id'] == 4 && $row['consolidation_function_id'] == 3 && $row['text_format'] == '') {
				if ($peak) {
					return false;
				}
				$peak = $row;
			}
			if ($row['graph_type_id'] == 1 && strpos($row['text_format'], 'Total ') === 0) {
				$total = $row;
			}
		}
		if (!$peak || $peak['cdef_id'] != $maximum['cdef_id']) {
			return false;
		}
		if ($total) {
			$block[] = $total;
		}
		$block[] = $peak;
		foreach ($block as $row) {
			$used[$row['id']] = true;
			$ordered[] = array('existing' => $row);
		}
		$entry = array('peak' => $maximum);
		// Package reimports restore stock labels but may retain migrated peaks.
		foreach ($rows as $row) {
			$matches_peak = $maximum['local_graph_id'] == 0
				? $row['hash'] == traffic_legend_peak_hash($maximum['hash'])
				: isset($template_peaks[$maximum['local_graph_template_item_id']]) &&
					$row['local_graph_template_item_id'] == $template_peaks[$maximum['local_graph_template_item_id']];
			if (!$matches_peak) {
				continue;
			}
			if (isset($entry['reuse']) || $row['graph_type_id'] != 9 ||
				$row['task_item_id'] != $task || trim($row['text_format']) != 'Max peak:') {
				return false;
			}
			$entry['reuse'] = $row['id'];
			$used[$row['id']] = true;
		}
		$ordered[] = $entry;
	}
	foreach ($rows as $row) {
		if (!isset($used[$row['id']])) {
			// Reordering an additional GPRINT can change its inherited RRD series.
			if (in_array($row['graph_type_id'], array(9, 11, 12, 13, 14))) {
				return false;
			}
			$ordered[] = array('existing' => $row);
		}
	}
	return $ordered;
}

/** Stable Cacti object identifiers; these are not cryptographic hashes. */
function traffic_legend_peak_hash($source) {
	$hashes = array(
		'55acbcc33f46ee6d754e8e81d1b54808' => '7a26e592ed4f91dca3456ee2eb7f6878',
		'671e989be7cbf12c623b4e79d91c7bed' => 'c5891bc8b6ec4e19ccac789fcd284e7d',
		'ccbd86e03ccf07483b4d29e63612fb18' => 'e95aeb21587c73b504241d0290d52976',
		'2302f80c2c70b897d12182a1fc11ecd6' => '4143a9a2eb69dce3751bcf22901ec342',
		'88d3094d5dc2164cbf2f974aeb92f051' => '1eb7bf611fecb2603a98f5c96698799c',
		'0e715933830112c23c15f7e3463f77b6' => 'f7b6fdcf25dc5794cb784136456d4fe8',
		'175c0a68689bebc38aad2fbc271047b3' => 'f64b4cf916e0f343f2ef29832a6fff9c',
		'6bcedd61e3ccf7518ca431940c93c439' => '7061d48f6ab1fb14f2121e30562bd256',
		'de265acbbfa99eb4b3e9f7e90c7feeda' => '444550ad167de0dfa27831656146c6fb',
		'fdaec5b9227522c758ad55882c483a83' => '56d594656005dd3fd6c49e7d2e06fedd',
	);

	return $hashes[$source] ?? null;
}

/** Update one template or graph. Transactions never span multiple graphs. */
function traffic_legend_update_graph($tid, $gid, &$template_peaks, $apply) {
	$started = false;
	try {
		if ($apply) {
			if (!db_execute('START TRANSACTION')) {
				throw new RuntimeException('Unable to start traffic legend transaction');
			}
			$started = true;
		}
		$rows = db_fetch_assoc_prepared('SELECT * FROM graph_templates_item WHERE graph_template_id = ? AND local_graph_id = ? ORDER BY sequence, id' . ($apply ? ' FOR UPDATE' : ''), array($tid, $gid));
		$plan = traffic_legend_plan($rows, $template_peaks);
		if ($plan === false) {
			if ($started) {
				db_execute('ROLLBACK');
			}
			return false;
		}
		// Validate every link before changing any item.
		foreach ($plan as $entry) {
			if (!isset($entry['peak'])) {
				continue;
			}
			$row = $entry['peak'];
			if ($gid == 0 ? traffic_legend_peak_hash($row['hash']) === null : !isset($template_peaks[$row['local_graph_template_item_id']])) {
				throw new RuntimeException('Missing template linkage for traffic peak');
			}
		}
		$sequence = 1;
		foreach ($plan as $entry) {
			if (isset($entry['existing'])) {
				$row = $entry['existing'];
				$text = $row['text_format'] == 'Maximum:' ? 'Max average:' : $row['text_format'];
				if ($apply && !db_execute_prepared('UPDATE graph_templates_item SET sequence = ?, text_format = ? WHERE id = ?', array($sequence, $text, $row['id']))) {
					throw new RuntimeException('Unable to update traffic legend');
				}
				$sequence++;
				continue;
			}
			$row = $entry['peak'];
			$source_id = $gid == 0 ? $row['id'] : $row['local_graph_template_item_id'];
			unset($row['id']);
			if (isset($entry['reuse'])) {
				$row['id'] = $entry['reuse'];
			}
			$row['sequence'] = $sequence++;
			$row['hash'] = $gid == 0 ? traffic_legend_peak_hash($row['hash']) : '';
			$row['local_graph_template_item_id'] = $gid == 0 ? 0 : $template_peaks[$source_id];
			$row['text_format'] = str_repeat(' ', 53) . 'Max peak:   ';
			$row['hard_return'] = 'on';
			$id = $apply ? sql_save($row, 'graph_templates_item') : ($entry['reuse'] ?? -$source_id);
			if (!$id) {
				throw new RuntimeException('Unable to add traffic peak');
			}
			if ($gid == 0) {
				$template_peaks[$source_id] = $id;
				if ($apply) {
					$inputs = db_fetch_assoc_prepared('SELECT graph_template_input_id FROM graph_template_input_defs WHERE graph_template_item_id = ?', array($source_id));
					foreach ($inputs as $input) {
						if (!db_execute_prepared('REPLACE INTO graph_template_input_defs (graph_template_input_id, graph_template_item_id) VALUES (?, ?)', array($input['graph_template_input_id'], $id))) {
							throw new RuntimeException('Unable to link traffic peak input');
						}
					}
				}
			}
		}
		if ($started && !db_execute('COMMIT')) {
			throw new RuntimeException('Unable to commit traffic legend');
		}
		return true;
	} catch (Throwable $e) {
		if ($started) {
			db_execute('ROLLBACK');
		}
		throw $e;
	}
}

/** Explicit CLI operation: bounded ID batches and one graph's items at a time. */
function update_interface_traffic_legends($apply = false, $batch_size = 100, $report = null) {
	$hashes = array(
		'5deb0d66c81262843dce5f3861be9966',
		'1742b2066384637022d178cc5072905a',
		'13b47e10b2d5db45707d61851f69c52b',
		'df244b337547b434b486662c3c5c7472',
		'8ad6790c22b693680e041f21d62537ac'
	);
	$batch_size = max(1, min(1000, (int) $batch_size));
	$counts = array('changed' => 0, 'skipped' => 0);
	foreach ($hashes as $hash) {
		$tid = db_fetch_cell_prepared('SELECT id FROM graph_templates WHERE hash = ?', array($hash));
		if (!$tid) {
			continue;
		}
		$template_peaks = array();
		$rows = db_fetch_assoc_prepared('SELECT * FROM graph_templates_item WHERE graph_template_id = ? AND local_graph_id = ? ORDER BY sequence, id', array($tid, 0));
		// Recover links on resumed runs, including templates imported already updated.
		foreach ($rows as $source) {
			$peak_hash = traffic_legend_peak_hash($source['hash']);
			if ($peak_hash === null) {
				continue;
			}
			foreach ($rows as $peak) {
				if ($peak['hash'] === $peak_hash && trim($peak['text_format']) === 'Max peak:') {
					$template_peaks[$source['id']] = $peak['id'];
				}
			}
		}
		$changed = traffic_legend_update_graph($tid, 0, $template_peaks, $apply);
		$counts[$changed ? 'changed' : 'skipped']++;
		if ($report) {
			$report($tid, 0, $changed);
		}
		$after = 0;
		do {
			$graphs = db_fetch_assoc_prepared('SELECT id FROM graph_local WHERE graph_template_id = ? AND id > ? ORDER BY id LIMIT ' . $batch_size, array($tid, $after));
			foreach ($graphs as $graph) {
				$after = (int) $graph['id'];
				// A customized template cannot safely supply links to new items.
				$changed = count($template_peaks) === 2 && traffic_legend_update_graph($tid, $after, $template_peaks, $apply);
				$counts[$changed ? 'changed' : 'skipped']++;
				if ($report) {
					$report($tid, $after, $changed);
				}
			}
		} while (count($graphs) === $batch_size);
	}
	return $counts;
}
