<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_reports(int $reportId, int $graphId, int $deviceId): void {
	$session  = $_SESSION;
	$request  = [$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST'] ?? []];
	$original = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', [$reportId]);
	$queueIds = [];

	try {
		// An empty notification list stores the real generated report without delivery.
		foreach ([[1, 'admin'], [null, 'system'], [999999, 'unknown']] as [$actor, $username]) {
			if ($actor === null) {
				unset($_SESSION[SESS_USER_ID]);
			} else {
				$_SESSION[SESS_USER_ID] = (int) $actor;
			}
			$id = (int) reports_queue('Queued fixture', REPORTS_SEND_NOW, 'reports', $reportId, '', []);
			library_expect($id > 0, true, 'Report queue allocates an identity');
			$queueIds[] = $id;
			$queued     = db_fetch_row_prepared('SELECT * FROM reports_queued WHERE id = ?', [$id]);
			library_expect($queued['requested_by'], $username, 'Queue records the requesting actor');
			library_expect((int) $queued['requested_id'], $actor ?? -1, 'Queue preserves the actor identifier');
			library_expect($queued['status'], 'pending', 'New report is pending');
			library_expect((int) $queued['source_id'], $reportId, 'Queue retains report identity');
			library_expect(json_decode($queued['notification'], true), [], 'Queue has no delivery destinations');
		}
		$_SESSION[SESS_USER_ID] = 1;
		$id                     = $queueIds[0];
		$types                  = [
			[REPORTS_TYPE_INLINE_PNG, 'png', IMAGETYPE_PNG, 'inline'],
			[REPORTS_TYPE_INLINE_JPG, 'jpg', IMAGETYPE_JPEG, 'inline'],
			[REPORTS_TYPE_INLINE_GIF, 'gif', IMAGETYPE_GIF, 'inline'],
			[REPORTS_TYPE_ATTACH_PNG, 'png', IMAGETYPE_PNG, 'attachment'],
			[REPORTS_TYPE_ATTACH_JPG, 'jpg', IMAGETYPE_JPEG, 'attachment'],
			[REPORTS_TYPE_ATTACH_GIF, 'gif', IMAGETYPE_GIF, 'attachment'],
		];

		foreach ($types as [$type, $extension, $imageType, $disposition]) {
			$report = array_replace($original, ['attachment_type' => $type, 'thumbnails' => 'on']);
			$before = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE source = ? AND source_id = ?', ['reports', $reportId]);
			library_expect(generate_report($id, $report), true, 'Queued report generation completes');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE source = ? AND source_id = ?', ['reports', $reportId]), $before + 1, 'Generation persists exactly one history record');
			$history     = db_fetch_row_prepared('SELECT * FROM reports_log WHERE source = ? AND source_id = ? ORDER BY id DESC LIMIT 1', ['reports', $reportId]);
			$attachments = json_decode(base64_decode($history['report_attachments'], true), true, 512, JSON_THROW_ON_ERROR);
			library_expect(count($attachments), 1, 'One graph creates one stored attachment');
			$attachment = $attachments[0];
			library_expect($attachment['filename'], 'graph_' . $graphId . '.' . $extension, 'Stored image filename matches the requested format');
			library_expect($attachment['mime_type'], 'image/' . $extension, 'Attachment declares its generated format');
			library_expect($attachment['inline'], $disposition, 'Attachment retains inline or download disposition');
			library_expect((int) $attachment['local_graph_id'], $graphId, 'Attachment retains graph identity');
			library_expect((int) $attachment['timespan'], GT_LAST_DAY, 'Attachment retains the graph time range');
			$bytes = base64_decode($attachment['attachment'], true);
			library_expect(is_string($bytes) && strlen($bytes) > 100, true, 'Graph image contains real encoded bytes');
			$image = getimagesizefromstring($bytes);
			library_expect($image[2], $imageType, 'Stored bytes decode as the requested image format');
			library_expect($image[0] >= 500 && $image[1] >= 200, true, 'Graph preserves the configured dimensions');
			library_expect($history['sent_by'], 'admin', 'History preserves queue attribution');
			library_expect((int) $history['sent_id'], 1, 'History preserves the requesting account');
			library_expect(str_contains($history['report_html_output'], '<GRAPH:' . $graphId . ':'), true, 'History stores the graph placeholder with its attachment');
			$stored = db_fetch_row_prepared('SELECT last_started, last_runtime FROM reports WHERE id = ?', [$reportId]);
			library_expect($stored['last_started'] !== '0000-00-00 00:00:00', true, 'Generation updates the report start time');
			library_expect((float) $stored['last_runtime'] >= 0, true, 'Generation records a nonnegative runtime');

			if ($imageType === IMAGETYPE_PNG) {
				foreach (['true', 'false'] as $style) {
					set_request_var('style', $style);
					$dom = library_form_dom(static function () use ($history): void { print reports_generate_history_html((int) $history['id']); });
					library_expect($dom->query('//img[@class="graph"]')->length, 1, 'History renders its stored graph once');
					library_expect($dom->evaluate('string(//img[@class="graph"]/@src)'), 'data:image/png;base64,' . $attachment['attachment'], 'History uses the saved image bytes');
					library_expect($dom->query('//script')->length, 0, 'Report text stays inert in history');
				}
				set_request_var('style', 'true');
			}
		}
		library_expect(reports_generate_history_html(0), '', 'Absent history renders no report');
		library_expect(reports_run(0), false, 'Absent queue item does not run');

		foreach (['{broken', 'null', '{"email":"invalid"}', '{"email":{}}', '{"notification_list":{}}', '{"unsupported":{}}'] as $notification) {
			library_expect(db_execute_prepared('UPDATE reports_queued SET notification = ? WHERE id = ?', [$notification, $id]), true, 'Malformed legacy notification fixture persists');
			$raw    = ['samples' => [0, null, 2.5]];
			$output = "value\t0\n";
			$html   = '<p>Saved report</p>';
			$text   = 'Saved report';
			reports_log_and_notify($id, time(), 'html', 'reports', $reportId, 'Fixture', $raw, $output, $html, $text);
			$history = db_fetch_row_prepared('SELECT * FROM reports_log WHERE source = ? AND source_id = ? ORDER BY id DESC LIMIT 1', ['reports', $reportId]);
			library_expect(json_decode($history['report_raw_data'], true), $raw, 'Invalid notification preserves zero, null, and fractional raw samples');
			library_expect($history['report_raw_output'], $output, 'Invalid notification preserves raw output bytes');
			library_expect($history['report_html_output'], $html, 'Invalid notification preserves the HTML report');
			library_expect($history['report_txt_output'], $text, 'Invalid notification preserves the text report');
			library_expect($history['notification'], $notification, 'History retains the notification input for diagnosis');
		}

		$item = db_fetch_row_prepared('SELECT * FROM reports_items WHERE report_id = ? AND local_graph_id = ? LIMIT 1', [$reportId, $graphId]);

		foreach ([-1, (int) db_fetch_cell_prepared('SELECT graph_template_id FROM graph_local WHERE id = ?', [$graphId])] as $template) {
			$item['graph_template_id'] = $template;

			foreach (['', '^Does not match any fixture graph$'] as $filter) {
				$item['graph_name_regexp'] = $filter;
				$html                      = reports_expand_device($original, $item, $deviceId, REPORTS_OUTPUT_EMAIL);
				library_expect(str_contains($html, '<GRAPH:' . $graphId . ':'), $filter === '', 'Device report applies its template and graph-name filters');
			}
		}
		// A removed report owner disables future runs before any output is saved.
		$report = array_replace($original, ['user_id' => 999999]);
		$before = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE source_id = ?', [$reportId]);
		library_expect(generate_report($id, $report), false, 'Missing report owner prevents generation');
		library_expect(db_fetch_cell_prepared('SELECT enabled FROM reports WHERE id = ?', [$reportId]), '', 'Orphaned report is disabled');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log WHERE source_id = ?', [$reportId]), $before, 'Rejected generation saves no history');
	} finally {
		foreach ($queueIds as $id) {
			db_execute_prepared('DELETE FROM reports_queued WHERE id = ?', [$id]);
		}
		db_execute_prepared('DELETE FROM reports_log WHERE source = ? AND source_id = ?', ['reports', $reportId]);
		sql_save($original, 'reports');
		$_SESSION                                              = $session;
		[$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST']] = $request;
	}
}

function library_report_tree(int $tree, int $branch, int $graph): void {
	$report = ['user_id' => 1, 'graph_columns' => 2, 'graph_width' => 500, 'graph_height' => 200,
		'graph_linked'      => '', 'thumbnails' => '', 'attachment_type' => REPORTS_TYPE_INLINE_PNG];
	$item = ['tree_id'   => $tree, 'branch_id' => $branch, 'timespan' => GT_LAST_DAY,
		'graph_name_regexp' => '', 'align' => 1, 'font_size' => 10];
	library_expect(reports_tree_has_graphs($tree, $branch, 1, '') > 0, true, 'Report branch discovers its stored graph');
	library_expect(reports_tree_has_graphs($tree, $branch, 1, '^No matching fixture$'), 0, 'Tree graph search excludes unmatched titles');

	foreach ([true, false] as $formatted) {
		foreach ([REPORTS_OUTPUT_EMAIL, REPORTS_OUTPUT_STDOUT] as $output) {
			$html   = reports_expand_tree($report, $item, $branch, $output, $formatted);
			$needle = $output === REPORTS_OUTPUT_EMAIL ? '<GRAPH:' . $graph . ':' : 'local_graph_id=' . $graph;
			library_expect(str_contains($html, $needle), true, 'Tree report renders the selected branch graph');
			library_expect(str_contains($html, 'Fixture branch'), true, 'Tree report retains the branch title');
			$html = expand_branch($report, $item, 0, $output, $formatted, 'modern');
			library_expect(str_contains($html, $needle), true, 'Cascading report discovers a graph below a nested header');
		}
	}
	$device = (int) db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$graph]);
	$leaf   = (int) api_tree_item_save(0, $tree, TREE_ITEM_TYPE_HOST, $branch, '', 0, $device, 0, HOST_GROUPING_GRAPH_TEMPLATE, TREE_ORDERING_INHERIT, false);
	library_expect($leaf > 0, true, 'Report fixture owns a device leaf');

	try {
		foreach ([HOST_GROUPING_GRAPH_TEMPLATE, HOST_GROUPING_DATA_QUERY_INDEX] as $grouping) {
			db_execute_prepared('UPDATE graph_tree_items SET host_grouping_type = ? WHERE id = ?', [$grouping, $leaf]);

			foreach (['', 'on'] as $cascade) {
				$item['tree_cascade'] = $cascade;

				foreach ([false, true] as $format) {
					$html = reports_expand_tree($report, $item, $leaf, REPORTS_OUTPUT_EMAIL, $format);
					library_expect(str_contains($html, '<GRAPH:' . $graph . ':'), true, 'Device leaf renders its graph with the requested grouping and cascade');
					library_expect(str_contains($html, 'Updated fixture'), true, 'Device report identifies its device');
				}
			}
		}
	} finally {
		db_execute_prepared('DELETE FROM graph_tree_items WHERE id = ?', [$leaf]);
		unset($item['tree_cascade']);
	}
	$id = (int) sql_save(array_replace($report, ['id' => 0, 'name' => 'Tree report fixture', 'font_size' => 10,
		'alignment'                                      => 1, 'from_name' => '', 'from_email' => '', 'email' => '', 'bcc' => '']), 'reports');
	library_expect($id > 0, true, 'Tree report fixture persists');

	try {
		$itemId = (int) sql_save(array_replace($item, ['id' => 0, 'report_id' => $id, 'item_type' => REPORTS_ITEM_TREE, 'item_text' => '']), 'reports_items');
		library_expect($itemId > 0, true, 'Tree report item persists');

		foreach (['', '^No matching fixture$'] as $filter) {
			library_expect(db_execute_prepared('UPDATE reports_items SET graph_name_regexp = ? WHERE id = ?', [$filter, $itemId]), true, 'Report graph filter persists');
			$theme = 'modern';
			$html  = reports_generate_html($id, REPORTS_OUTPUT_EMAIL, $theme);
			library_expect(str_contains($html, '<GRAPH:' . $graph . ':'), $filter === '', 'Report generation applies the branch filter before expanding graphs');
		}
	} finally {
		db_execute_prepared('DELETE FROM reports_items WHERE report_id = ?', [$id]);
		db_execute_prepared('DELETE FROM reports WHERE id = ?', [$id]);
	}
	$item['tree_id'] = 0;
	library_expect(reports_expand_tree($report, $item, 0, REPORTS_OUTPUT_EMAIL, false), '', 'An absent tree renders no graph');
}
