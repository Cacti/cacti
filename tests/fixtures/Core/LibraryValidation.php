<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Invalid calendar input must preserve the last usable graph window. */
function library_calendar_validation(): void {
	global $_CACTI_REQUEST;
	require_once CACTI_PATH_LIBRARY . '/timespan_settings.php';
	$state = [$_REQUEST, $_CACTI_REQUEST, $_SESSION];

	try {
		$start                                       = '2031-01-20 10:00:00';
		$end                                         = '2031-01-20 12:00:00';
		$_SESSION['sess_current_date1']              = $start;
		$_SESSION['sess_current_date2']              = $end;
		$_SESSION['sess_current_timespan_begin_now'] = strtotime($start);
		$_SESSION['sess_current_timespan_end_now']   = strtotime($end);

		foreach ([['1992-12-31', $end, 'Start'], [$start, '1992-12-31', 'End']] as [$date1, $date2, $label]) {
			$_REQUEST       = ['date1' => $date1, 'date2' => $date2];
			$_CACTI_REQUEST = [];
			unset($_SESSION[SESS_MESSAGES]['start_too_early']);
			$span = [];
			process_user_input($span, 300);
			library_expect($span['begin_now'], strtotime($start), 'Invalid calendar input retains the prior start');
			library_expect($span['end_now'], strtotime($end), 'Invalid calendar input retains the prior end');
			library_expect($_SESSION[SESS_MESSAGES]['start_too_early']['level'], MESSAGE_LEVEL_WARN, 'Calendar rejection supplies an operator warning');
			library_expect(str_contains($_SESSION[SESS_MESSAGES]['start_too_early']['message'], 'Your ' . $label . ' Date'), true, 'Calendar warning identifies the invalid boundary');
		}
	} finally {
		[$_REQUEST, $_CACTI_REQUEST, $_SESSION] = $state;
	}
}

/** Corrupt stored archives cannot replace an installed plugin. */
function library_archive_validation(): void {
	$plugin      = 'covarchive_' . bin2hex(random_bytes(6));
	$destination = CACTI_PATH_PLUGINS . '/' . $plugin;
	$session     = $_SESSION;
	library_expect(file_exists($destination), false, 'Archive validation owns its plugin identity');

	try {
		library_expect(db_execute_prepared('INSERT INTO plugin_archive (plugin, archive) VALUES (?, ?)', [$plugin, '%%%invalid-base64%%%']), true, 'Invalid archive fixture is stored');
		$id = (string) db_fetch_insert_id();
		library_expect(db_execute_prepared('INSERT INTO plugin_available (plugin, tag_name, archive) VALUES (?, ?, ?)', [$plugin, 'fixture', '%%%invalid-base64%%%']), true, 'Invalid available archive fixture is stored');

		foreach ([['archive', $id], ['available', 'fixture']] as [$type, $identity]) {
			unset($_SESSION[SESS_MESSAGES]['archive_failed']);
			library_expect(api_plugin_archive_restore($plugin, $identity, $type), false, 'Invalid stored encoding cannot report a successful restore');
			library_expect($_SESSION[SESS_MESSAGES]['archive_failed']['level'], MESSAGE_LEVEL_ERROR, 'Invalid archive reports failure to the operator');
			library_expect(file_exists($destination), false, 'Rejected archive creates no destination');
		}
		api_plugin_archive($plugin);
		library_expect($_SESSION[SESS_MESSAGES]['plugin_archive_failed']['level'], MESSAGE_LEVEL_ERROR, 'Missing plugin cannot report successful archival');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_config WHERE directory = ?', [$plugin]), 0, 'Rejected install creates no plugin state');
	} finally {
		db_execute_prepared('DELETE FROM plugin_archive WHERE plugin = ?', [$plugin]);
		db_execute_prepared('DELETE FROM plugin_available WHERE plugin = ?', [$plugin]);
		$_SESSION = $session;
	}
}

/** Aggregate mutations retain member identity and respect template boundaries. */
function library_aggregate_validation(int $graph, int $other, int $template, int $aggregate): void {
	$session       = $_SESSION;
	$rows          = db_fetch_assoc_prepared('SELECT id, sequence, graph_type_id FROM graph_templates_item WHERE local_graph_id = ? ORDER BY sequence', [$graph]);
	$otherTemplate = db_fetch_cell_prepared('SELECT graph_template_id FROM graph_local WHERE id = ?', [$other]);

	try {
		$params = aggregate_validate_graph_params(['height' => '150', 'width' => '600', 'vertical_label' => '<Load>', 'auto_scale' => 'on']);
		library_expect($params['height'], '150', 'Aggregate form preserves valid dimensions');
		library_expect($params['vertical_label'], '&lt;Load&gt;', 'Aggregate form escapes operator-controlled labels');
		library_expect($params['auto_scale'], 'on', 'Aggregate form preserves enabled options');
		$params = aggregate_validate_graph_params(['height' => 'invalid']);
		library_expect(is_error_message(), true, 'Aggregate form rejects nonnumeric dimensions');
		library_expect(get_next_sequence(0, 'sequence', 'graph_templates_item', 'local_graph_id = ' . $graph), max(array_column($rows, 'sequence')) + 1, 'Next graph item follows the current last sequence');
		$first   = $rows[0];
		$newType = (int) $first['graph_type_id'] === GRAPH_ITEM_TYPE_LINE1 ? GRAPH_ITEM_TYPE_AREA : GRAPH_ITEM_TYPE_LINE1;
		aggregate_conditional_convert_graph_type($graph, (int) $first['graph_type_id'], $newType);
		library_expect((int) db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', [$first['id']]), $newType, 'Graph conversion changes the first matching item');

		foreach (array_slice($rows, 1) as $row) {
			library_expect((int) db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', [$row['id']]), (int) $row['graph_type_id'], 'Graph conversion preserves later items');
		}
		library_expect(aggregate_reorder_ds_graph($graph, (string) $template, $aggregate, AGGREGATE_ORDER_BASE_GRAPH, AGGREGATE_GRAPH_TYPE_KEEP), true, 'Aggregate can follow its base graph data-source order');
		$sequences = array_map('intval', array_column(db_fetch_assoc_prepared('SELECT sequence FROM graph_templates_item WHERE local_graph_id = ? ORDER BY sequence', [$aggregate]), 'sequence'));
		library_expect(count(array_unique($sequences)), count($sequences), 'Reordering retains distinct aggregate item positions');
		$selection        = [$graph, $other];
		$data             = [];
		$selectedTemplate = 0;
		$message          = '';
		library_expect(aggregate_get_data_sources($selection, $data, $selectedTemplate, $message), true, 'Members from one template are admitted');
		library_expect((int) $selectedTemplate, $template, 'Aggregate selection preserves the actual source template');
		db_execute_prepared('UPDATE graph_local SET graph_template_id = 0 WHERE id = ?', [$other]);
		library_expect(aggregate_get_data_sources($selection, $data, $selectedTemplate, $message), false, 'Mixed template identities are rejected');
		library_expect(str_contains($message, 'multiple Graph Templates'), true, 'Mixed template rejection explains the incompatible selection');
		$selection = [$other];
		library_expect(aggregate_get_data_sources($selection, $data, $selectedTemplate, $message), false, 'A non-templated graph cannot become an aggregate member');
		library_expect(str_contains($message, 'non-templated'), true, 'Non-templated rejection supplies actionable guidance');
	} finally {
		foreach ($rows as $row) {
			db_execute_prepared('UPDATE graph_templates_item SET sequence = ?, graph_type_id = ? WHERE id = ?', [$row['sequence'], $row['graph_type_id'], $row['id']]);
		}
		db_execute_prepared('UPDATE graph_local SET graph_template_id = ? WHERE id = ?', [$otherTemplate, $other]);
		$_SESSION = $session;
	}
}

/** A destination occupied by a file must fail before archive extraction. */
function library_archive_destination(): void {
	$plugin      = 'covblocked_' . bin2hex(random_bytes(6));
	$path        = CACTI_PATH_PLUGINS . '/' . $plugin;
	$archivePath = sys_get_temp_dir() . '/' . $plugin . '.tar';
	$session     = $_SESSION;
	$stream      = fopen($path, 'x');
	library_expect(is_resource($stream), true, 'Archive destination fixture owns its conflicting file');
	fwrite($stream, 'Preserved destination');
	fclose($stream);

	try {
		$archive = new PharData($archivePath);
		$archive->addFromString('root/payload.txt', 'Fixture archive payload');
		unset($archive);
		$bytes = base64_encode(file_get_contents($archivePath));
		db_execute_prepared('INSERT INTO plugin_archive (plugin, archive) VALUES (?, ?)', [$plugin, $bytes]);
		$id = (string) db_fetch_insert_id();
		db_execute_prepared('INSERT INTO plugin_available (plugin, tag_name, archive) VALUES (?, ?, ?)', [$plugin, 'fixture', $bytes]);

		foreach ([['archive', $id], ['available', 'fixture']] as [$type, $identity]) {
			$warning = false;
			$prior   = set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$warning, &$prior): bool {
				if ($level === E_WARNING && str_contains($message, 'mkdir(): File exists')) {
					$warning = true;

					return true;
				}

				return $prior ? $prior($level, $message, $file, $line) : false;
			});

			try {
				$result = api_plugin_archive_restore($plugin, $identity, $type);
			} finally {
				restore_error_handler();
			}
			library_expect($warning, true, 'Archive failure reaches the real filesystem boundary');
			library_expect($result, false, 'Blocked destination cannot report restoration success');
			library_expect($_SESSION[SESS_MESSAGES]['restore_failed']['level'], MESSAGE_LEVEL_ERROR, 'Blocked destination supplies a restore error');
			library_expect(file_get_contents($path), 'Preserved destination', 'Restore failure preserves the existing destination bytes');
		}
	} finally {
		db_execute_prepared('DELETE FROM plugin_archive WHERE plugin = ?', [$plugin]);
		db_execute_prepared('DELETE FROM plugin_available WHERE plugin = ?', [$plugin]);
		unlink($archivePath);
		unlink($path);
		$_SESSION = $session;
	}
}
