<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Validates aggregate automation settings for the template editor and for execution.
 *
 * @param array $values The submitted or stored template values.
 *
 * @throws InvalidArgumentException When a setting is malformed or out of range.
 *
 * @return array The normalised automation settings.
 */
function automation_aggregate_settings(array $values) : array {
	if (isset($values['automation_pattern']) && !is_string($values['automation_pattern']) ||
		isset($values['automation_title']) && !is_string($values['automation_title'])) {
		throw new InvalidArgumentException('Aggregate automation pattern and title must be strings.');
	}
	$settings = [
		'automation_enabled'          => ($values['automation_enabled'] ?? '') === 'on' ? 'on' : '',
		'automation_pattern'          => trim((string) ($values['automation_pattern'] ?? '')),
		'automation_title'            => trim((string) ($values['automation_title'] ?? '|host_description| - |aggregate_template_name|')),
		'automation_host_template_id' => filter_var($values['automation_host_template_id'] ?? 0, FILTER_VALIDATE_INT),
		'automation_max_members'      => filter_var($values['automation_max_members'] ?? 1000, FILTER_VALIDATE_INT),
	];

	if ($settings['automation_host_template_id'] === false || $settings['automation_host_template_id'] < 0 ||
		$settings['automation_max_members']         === false || $settings['automation_max_members'] < 1 || $settings['automation_max_members'] > 5000) {
		throw new InvalidArgumentException('Invalid aggregate automation device template or member limit.');
	}

	if (strlen($settings['automation_pattern']) > 512 || strlen($settings['automation_title']) > 255 || $settings['automation_title'] === '') {
		throw new InvalidArgumentException('Invalid aggregate automation pattern or title length.');
	}

	if ($settings['automation_pattern'] !== '' && automation_aggregate_match($settings['automation_pattern'], '') === false) {
		throw new InvalidArgumentException('Aggregate automation requires a valid PCRE pattern with delimiters.');
	}

	if ($settings['automation_enabled'] === 'on' && $settings['automation_pattern'] === '') {
		throw new InvalidArgumentException('Enabled aggregate automation requires a data source pattern.');
	}

	return $settings;
}

/**
 * Matches an administrator-supplied pattern under bounded PCRE work limits.
 *
 * The pattern keeps its own delimiters and flags.
 *
 * @param string $pattern The delimited PCRE pattern.
 * @param string $subject The data source name to test.
 *
 * @return int|false 1 on a match, 0 on no match, or false when the pattern fails or hits a limit.
 */
function automation_aggregate_match(string $pattern, string $subject) : int|false {
	$backtrack = ini_get('pcre.backtrack_limit');
	$recursion = ini_get('pcre.recursion_limit');

	try {
		if (ini_set('pcre.backtrack_limit', (string) min((int) $backtrack, 10000)) === false ||
			ini_set('pcre.recursion_limit', (string) min((int) $recursion, 100))      === false) {
			return false;
		}

		// Patterns are administrator-controlled and execute under the hard PCRE
		// work limits above, which are restored in finally and covered by tests.
		return @preg_match($pattern, $subject); // NOSONAR
	} finally {
		ini_set('pcre.backtrack_limit', $backtrack);
		ini_set('pcre.recursion_limit', $recursion);
	}
}

/**
 * Expands the aggregate title template for one device.
 *
 * Each placeholder is replaced once, so a device description that contains a
 * placeholder is not expanded again.
 *
 * @param array $rule The aggregate template with its automation title.
 * @param array $host The device row with id, description and hostname.
 *
 * @return string The title, bounded to 255 bytes without splitting a UTF-8 character.
 */
function automation_aggregate_title(array $rule, array $host) : string {
	return mb_strcut(strtr($rule['automation_title'], [
		'|host_description|'        => (string) $host['description'],
		'|host_hostname|'           => (string) $host['hostname'],
		'|host_id|'                 => (string) $host['id'],
		'|aggregate_template_name|' => (string) $rule['name'],
	]), 0, 255, 'UTF-8');
}

/**
 * Returns the last database error recorded by the db_* helpers.
 *
 * db_fetch_* helpers can return an empty result on SQL failure, so callers
 * check this instead of treating an empty result as no matches.
 *
 * @return string The last database error, or an empty string.
 */
function automation_aggregate_database_error() : string {
	global $database_last_error;

	return (string) ($database_last_error ?? '');
}

/**
 * Fetches rows and fails loudly instead of returning an empty set on SQL errors.
 *
 * @param string $sql    The prepared query.
 * @param array  $params The bound parameters.
 *
 * @throws RuntimeException When the query fails.
 *
 * @return array The fetched rows.
 */
function automation_aggregate_rows(string $sql, array $params = []) : array {
	global $database_last_error;
	$previous_error      = $database_last_error ?? '';
	$database_last_error = '';
	$rows                = db_fetch_assoc_prepared($sql, $params);

	if (automation_aggregate_database_error() !== '' || !is_array($rows)) {
		throw new RuntimeException('Unable to read aggregate automation metadata.');
	}
	$database_last_error = $previous_error;

	return $rows;
}

/**
 * Collects the device graphs whose bound data sources match the rule pattern.
 *
 * Candidates are read in batches of 200 graph IDs. Aggregate graphs are never
 * members, and a graph with several matching data sources is included once.
 *
 * @param array $rule    The compiled aggregate template rule.
 * @param int   $host_id The device whose graphs are matched.
 *
 * @throws LengthException  When a batch or the member limit is exceeded.
 * @throws RuntimeException When matching or a metadata read fails.
 *
 * @return list<int> Matching graph IDs in ascending order.
 */
function automation_aggregate_members(array $rule, int $host_id) : array {
	$members = [];
	$cursor  = 0;

	do {
		$graphs = automation_aggregate_rows('SELECT gl.id FROM graph_local AS gl
			WHERE gl.host_id = ? AND gl.graph_template_id = ? AND gl.id > ?
			AND NOT EXISTS (SELECT 1 FROM aggregate_graphs AS ag WHERE ag.local_graph_id = gl.id)
			ORDER BY gl.id LIMIT 200', [$host_id, $rule['graph_template_id'], $cursor]);

		if (!$graphs) {
			break;
		}
		$ids          = array_map('intval', array_column($graphs, 'id'));
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$names        = automation_aggregate_rows("SELECT DISTINCT gti.local_graph_id, COALESCE(NULLIF(dtd.name_cache, ''), dtd.name) AS name
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr ON dtr.id = gti.task_item_id
			INNER JOIN data_template_data AS dtd ON dtd.local_data_id = dtr.local_data_id
			INNER JOIN data_local AS dl ON dl.id = dtd.local_data_id
			WHERE gti.local_graph_id IN ($placeholders) AND dl.host_id = ?
			LIMIT 10001", [...$ids, $host_id]);

		if (count($names) > 10000) {
			throw new LengthException('Aggregate automation candidate batch exceeds 10000 data source names.');
		}

		foreach ($names as $source) {
			$matched = automation_aggregate_match($rule['automation_pattern'], (string) $source['name']);

			if ($matched === false) {
				throw new RuntimeException('Aggregate automation pattern failed while matching a data source.');
			}

			if ($matched === 1) {
				$members[(int) $source['local_graph_id']] = (int) $source['local_graph_id'];
			}
		}

		if (count($members) > $rule['automation_max_members']) {
			throw new LengthException('Aggregate automation member limit exceeded.');
		}
		$cursor = max($ids);
	} while (count($graphs) === 200);
	sort($members, SORT_NUMERIC);

	return $members;
}

/**
 * Builds the aggregate_create_update() attributes from an aggregate template.
 *
 * @param array $template The aggregate template row.
 *
 * @throws RuntimeException When the template items cannot be read.
 *
 * @return array The graph attributes, including per-sequence item settings.
 */
function automation_aggregate_attributes(array $template) : array {
	$items = automation_aggregate_rows('SELECT sequence, item_skip, item_total, color_template,
		t_graph_type_id, graph_type_id, t_cdef_id, cdef_id
		FROM aggregate_graph_templates_item WHERE aggregate_template_id = ? ORDER BY sequence', [$template['id']]);
	$attributes = [
		'aggregate_template_id' => (int) $template['id'], 'template_propogation' => 'on',
		'user_id'               => (int) $template['user_id'],
		'graph_template_id'     => (int) $template['graph_template_id'],
		'gprint_prefix'         => $template['gprint_prefix'], 'gprint_format' => $template['gprint_format'],
		'graph_type'            => $template['graph_type'], 'total' => $template['total'],
		'total_type'            => $template['total_type'], 'total_prefix' => $template['total_prefix'],
		'reorder'               => $template['order_type'], 'item_no' => count($items),
		'skipped_items'         => [], 'total_items' => [], 'color_templates' => [], 'graph_item_types' => [], 'cdefs' => [],
	];

	foreach ($items as $item) {
		$sequence = (int) $item['sequence'];

		if ($item['item_skip'] === 'on') {
			$attributes['skipped_items'][$sequence] = $sequence;
		}

		if ($item['item_total'] === 'on') {
			$attributes['total_items'][$sequence] = $sequence;
		}

		if ((int) $item['color_template'] >= 0) {
			$attributes['color_templates'][$sequence] = (int) $item['color_template'];
		}

		if ($item['t_graph_type_id'] === 'on') {
			$attributes['graph_item_types'][$sequence] = (int) $item['graph_type_id'];
		}

		if ($item['t_cdef_id'] === 'on') {
			$attributes['cdefs'][$sequence] = (int) $item['cdef_id'];
		}
	}

	return $attributes;
}

/**
 * Creates or updates the aggregate this template owns for one device.
 *
 * Only the graph recorded for the template and device is managed. An advisory
 * lock and a transaction prevent concurrent passes from creating duplicates.
 *
 * @param array $rule       The compiled aggregate template rule.
 * @param array $host       The device row.
 * @param array $attributes The attributes from automation_aggregate_attributes().
 *
 * @throws Throwable When matching, rebuilding or persisting fails; the transaction is rolled back.
 *
 * @return string One of busy, empty, detached, unchanged, created or updated.
 */
function automation_aggregate_reconcile(array $rule, array $host, array $attributes) : string {
	global $database_last_error;
	$lock = 'aggregate_automation_' . $rule['id'] . '_' . $host['id'];

	if ((int) db_fetch_cell_prepared('SELECT GET_LOCK(?, 0)', [$lock]) !== 1) {
		return 'busy';
	}
	$transaction = false;

	try {
		$members = automation_aggregate_members($rule, (int) $host['id']);

		// No matching members leaves an existing automated aggregate untouched; it is
		// never created for an empty set and never removed by a rule that stops matching.
		if (!$members) {
			return 'empty';
		}
		$attributes['graph_title'] = automation_aggregate_title($rule, $host);
		$hash                      = hash('sha256', json_encode([$members, $attributes], JSON_THROW_ON_ERROR));

		if (!db_begin_transaction()) {
			throw new RuntimeException('Unable to start aggregate automation transaction.');
		}
		$transaction = true;
		/* Ownership follows the mapped graph, not its template link. Turning off
		 * template propagation saves aggregate_template_id = 0 on the aggregate,
		 * and requiring the link here would make every later pass build a
		 * duplicate and move the mapping onto it. */
		$mapping     = automation_aggregate_rows('SELECT aam.local_graph_id, aam.configuration_hash,
			ag.id AS aggregate_id, ag.aggregate_template_id
			FROM automation_aggregate_graphs AS aam
			INNER JOIN graph_local AS gl ON gl.id = aam.local_graph_id
			LEFT JOIN aggregate_graphs AS ag ON ag.local_graph_id = aam.local_graph_id
			WHERE aam.aggregate_template_id = ? AND aam.host_id = ? FOR UPDATE', [$rule['id'], $host['id']]);
		$mapping = $mapping[0] ?? [];

		// A surviving graph that no longer follows this template belongs to the user.
		// Keep the mapping so reruns stay idempotent and never rebuild or replace it.
		if ($mapping && (int) ($mapping['aggregate_template_id'] ?? 0) !== (int) $rule['id']) {
			if (!db_commit_transaction()) {
				throw new RuntimeException('Unable to commit aggregate automation transaction.');
			}
			$transaction = false;

			return 'detached';
		}
		$local_graph_id = $mapping ? (int) $mapping['local_graph_id'] : 0;

		if ($local_graph_id > 0 && $mapping['configuration_hash'] === $hash) {
			if (!db_commit_transaction()) {
				throw new RuntimeException('Unable to commit aggregate automation transaction.');
			}
			$transaction = false;

			return 'unchanged';
		}

		if ($local_graph_id > 0 && !db_execute_prepared('DELETE FROM aggregate_graphs_items WHERE aggregate_graph_id = ?', [$mapping['aggregate_id']])) {
			throw new RuntimeException('Unable to replace automated aggregate members.');
		}
		$previous_error      = $database_last_error ?? '';
		$database_last_error = '';
		$created             = $local_graph_id === 0;
		$rebuilt             = aggregate_create_update($local_graph_id, $members, $attributes, false);

		if (!$rebuilt || $local_graph_id <= 0 || automation_aggregate_database_error() !== '') {
			throw new RuntimeException('Unable to rebuild automated aggregate.');
		}
		$database_last_error = $previous_error;

		if (!db_execute_prepared('REPLACE INTO automation_aggregate_graphs
			(aggregate_template_id, host_id, local_graph_id, configuration_hash) VALUES (?, ?, ?, ?)',
			[$rule['id'], $host['id'], $local_graph_id, $hash]) || !db_commit_transaction()) {
			throw new RuntimeException('Unable to persist aggregate automation ownership.');
		}
		$transaction = false;

		return $created ? 'created' : 'updated';
	} finally {
		if ($transaction) {
			db_rollback_transaction();
		}
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$lock]);
	}
}

/**
 * Runs every enabled aggregate automation template against one device.
 *
 * Called after a device's ordinary graph rules or a data query reindex,
 * never once per graph or polling sample.
 *
 * @param int  $host_id The device to reconcile.
 * @param bool $reload  Whether to reload the per-process template cache.
 *
 * @return array<int, string> Reconcile state keyed by aggregate template ID.
 */
function automation_execute_device_aggregates(int $host_id, bool $reload = false) : array {
	static $rules = null;

	if ($reload) {
		$rules = null;
	}

	if ($rules === null) {
		$loaded = automation_aggregate_rows("SELECT id, name, user_id, graph_template_id, gprint_prefix, gprint_format,
			graph_type, total, total_type, total_prefix, order_type, automation_enabled, automation_pattern,
			automation_title, automation_host_template_id, automation_max_members
			FROM aggregate_graph_templates WHERE automation_enabled = 'on' ORDER BY id");
		$compiled = [];

		foreach ($loaded as $template) {
			try {
				$template   = array_replace($template, automation_aggregate_settings($template));
				$compiled[] = [$template, automation_aggregate_attributes($template)];
			} catch (InvalidArgumentException $error) {
				cacti_log('ERROR: Invalid aggregate automation template ' . $template['id'] . ': ' . $error->getMessage(), false, 'AUTOM8');
			}
		}
		$rules = $compiled;
	}

	if (!$rules) {
		return [];
	}
	require_once __DIR__ . '/api_aggregate.php';
	require_once __DIR__ . '/aggregate.php';
	$hosts = automation_aggregate_rows("SELECT id, description, hostname, host_template_id
		FROM host WHERE id = ? AND deleted = ''", [$host_id]);

	if (!$hosts) {
		return [];
	}
	$host    = $hosts[0];
	$results = [];

	foreach ($rules as [$rule, $attributes]) {
		if ($rule['automation_host_template_id'] > 0 && $rule['automation_host_template_id'] !== (int) $host['host_template_id']) {
			continue;
		}

		try {
			$results[(int) $rule['id']] = automation_aggregate_reconcile($rule, $host, $attributes);
		} catch (Throwable $error) {
			$results[(int) $rule['id']] = 'failed';
			cacti_log('ERROR: Aggregate automation template ' . $rule['id'] . ' Device[' . $host_id . ']: ' . $error->getMessage(), false, 'AUTOM8');
		}
	}

	return $results;
}
