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
function traffic_legend_plan($rows) {
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
		$ordered[] = array('peak' => $maximum);
	}
	foreach ($rows as $row) {
		if (!isset($used[$row['id']])) {
			$ordered[] = array('existing' => $row);
		}
	}
	return $ordered;
}

function upgrade_interface_traffic_legends() {
	$hashes = array(
		'5deb0d66c81262843dce5f3861be9966',
		'1742b2066384637022d178cc5072905a',
		'13b47e10b2d5db45707d61851f69c52b',
		'df244b337547b434b486662c3c5c7472',
		'8ad6790c22b693680e041f21d62537ac'
	);
	foreach ($hashes as $hash) {
		$tid = db_fetch_cell_prepared('SELECT id FROM graph_templates WHERE hash = ?', array($hash));
		if (!$tid) {
			continue;
		}
		$rows = db_fetch_assoc_prepared('SELECT * FROM graph_templates_item WHERE graph_template_id = ? ORDER BY local_graph_id, sequence, id', array($tid));
		$groups = array();
		foreach ($rows as $row) {
			$groups[$row['local_graph_id']][] = $row;
		}
		if (empty($groups[0])) {
			continue;
		}
		$template_plan = traffic_legend_plan($groups[0]);
		if ($template_plan === false) {
			continue; // Already updated or customized beyond the stock layout.
		}
		$template_peaks = array();
		db_execute('START TRANSACTION');
		try {
			foreach ($groups as $gid => $items) {
				$plan = $gid == 0 ? $template_plan : traffic_legend_plan($items);
				if ($plan === false) {
					continue;
				}
				$sequence = 1;
				foreach ($plan as $entry) {
					if (isset($entry['existing'])) {
						$row = $entry['existing'];
						$text = $row['text_format'] == 'Maximum:' ? 'Max average:' : $row['text_format'];
						if (!db_execute_prepared('UPDATE graph_templates_item SET sequence = ?, text_format = ? WHERE id = ?', array($sequence++, $text, $row['id']))) {
							throw new RuntimeException('Unable to update traffic legend');
						}
					} else {
						$row = $entry['peak'];
						$source_id = $gid == 0 ? $row['id'] : $row['local_graph_template_item_id'];
						if ($gid != 0 && !isset($template_peaks[$source_id])) {
							throw new RuntimeException('Missing template linkage for traffic peak');
						}
						unset($row['id']);
						$row['sequence'] = $sequence++;
						$row['hash'] = $gid == 0 ? md5('traffic-max-peak:' . $row['hash']) : '';
						$row['local_graph_template_item_id'] = $gid == 0 ? 0 : $template_peaks[$source_id];
						$row['text_format'] = str_repeat(' ', 53) . 'Max peak:   ';
						$row['hard_return'] = 'on';
						$id = sql_save($row, 'graph_templates_item');
						if (!$id) {
							throw new RuntimeException('Unable to add traffic peak');
						}
						if ($gid == 0) {
							$template_peaks[$source_id] = $id;
							$inputs = db_fetch_assoc_prepared('SELECT graph_template_input_id FROM graph_template_input_defs WHERE graph_template_item_id = ?', array($source_id));
							foreach ($inputs as $input) {
								if (!db_execute_prepared('REPLACE INTO graph_template_input_defs (graph_template_input_id, graph_template_item_id) VALUES (?, ?)', array($input['graph_template_input_id'], $id))) {
									throw new RuntimeException('Unable to link traffic peak input');
								}
							}
						}
					}
				}
			}
			db_execute('COMMIT');
		} catch (Throwable $e) {
			db_execute('ROLLBACK');
			throw $e;
		}
	}
}
