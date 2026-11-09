<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise configuration diagnostics and client address resolution, restoring request state. */
function library_runtime(): void {
	global $config, $_CACTI_REQUEST;
	$session         = $_SESSION;
	$request         = $_REQUEST;
	$filteredRequest = $_CACTI_REQUEST;
	$post            = $_POST;
	$get             = $_GET;
	$server          = $_SERVER;
	$configuration   = $config;
	$alert           = db_fetch_row_prepared('SELECT * FROM settings WHERE name = ?', ['proxy_alert']);
	$setting         = 'coverage_runtime_' . bin2hex(random_bytes(8));
	$log             = sys_get_temp_dir() . '/cacti-option.log';
	$ownedLog        = !file_exists($log);

	try {
		$config['DEBUG_SET_CONFIG_OPTION']  = true;
		$config['DEBUG_READ_CONFIG_OPTION'] = true;
		set_config_option($setting, '0');
		library_expect(read_config_option($setting, true), '0', 'Configuration diagnostics preserve the stored zero value');
		library_expect(str_contains(file_get_contents($log), $setting), true, 'Configuration diagnostics identify the option being accessed');
		unset($config['DEBUG_SET_CONFIG_OPTION'], $config['DEBUG_READ_CONFIG_OPTION']);

		foreach (['::ffff:192.0.2.7' => '192.0.2.7', '::ffff:c000:207' => '192.0.2.7',
			'192.0.2.7'                 => null, '2001:db8::7' => null, 'invalid' => null] as $address => $expected) {
			library_expect(cacti_http_ipv4_mapped_address($address), $expected, 'Address normalization distinguishes mapped IPv4 from other address families');
		}
		$_SERVER['REMOTE_ADDR']          = '192.0.2.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
		unset($config['proxy_headers']);

		foreach (['', '2000-01-01', date('Y-m-d')] as $previous) {
			set_config_option('proxy_alert', $previous);
			library_expect(get_client_addr(), '192.0.2.7', 'Unconfigured proxy trust uses the immediate peer');
			library_expect(read_config_option('proxy_alert', true), date('Y-m-d'), 'Proxy configuration notice records its daily throttle');
		}
		$config['proxy_headers'] = ['HTTP_X_FORWARDED_FOR'];
		$clientAddressFor        = static function (array $trusted): string {
			global $config;

			$config['trusted_proxies'] = $trusted;

			return get_client_addr();
		};
		library_expect($clientAddressFor(['192.0.2.7']), '198.51.100.9', 'Explicitly trusted proxy contributes the client address');
		library_expect($clientAddressFor([]), '192.0.2.7', 'Untrusted peer cannot select its client identity');
		set_user_setting($setting, 'must not persist', 0);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM settings_user WHERE name = ?', [$setting]), 0, 'An anonymous user cannot persist preferences');
		set_user_setting($setting, '0', 1);
		library_expect(db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = 1', [$setting]), '0', 'Zero is a persistent preference value');
		set_user_setting($setting, 'changed', 1);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM settings_user WHERE name = ?', [$setting]), 1, 'Updating a preference preserves its unique identity');
		library_expect(db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = 1', [$setting]), 'changed', 'Updated preference reaches storage');

		$_REQUEST       = ['action' => 'edit', 'tab' => 'general'];
		$_CACTI_REQUEST = [];
		$general        = form_get_table_id();
		set_request_var('tab', 'permissions');
		$permissions = form_get_table_id();
		library_expect($general !== $permissions, true, 'Distinct editor tabs do not share a table preference identity');
		library_expect(str_ends_with($general, ':action-tab-edit-general:'), true, 'Table identity includes both action and tab');
		library_expect(form_get_table_id(true) !== $permissions, true, 'Two tables in the same tab have distinct identities');
		unset($_REQUEST['tab'], $_CACTI_REQUEST['tab']);
		library_expect(str_ends_with(form_get_table_id(), ':action-edit:'), true, 'An action without a tab keeps its own identity');

		foreach ([['host.description', 'DESC'], ['hostname', 'ASC'], ['ip_address', 'DESC']] as [$column, $direction]) {
			$_CACTI_REQUEST = [];
			$_REQUEST       = ['add' => 'reset', 'sort_column' => $column, 'sort_direction' => $direction];
			update_order_string();
			$page = get_order_string_page(false);
			library_expect($_SESSION['sort_data'][$page], [$column => $direction], 'Reset sorting replaces prior columns without losing direction');
			$order = $_SESSION['sort_string'][$page];
			library_expect(str_starts_with($order, 'ORDER BY ') && str_ends_with($order, ' ' . $direction), true, 'Sort clause retains the requested direction');
			library_expect(str_contains($order, $column === 'ip_address' ? 'INET_ATON(ip_address)' : ($column === 'host.description' ? '`host`.`description`' : 'hostname')), true, 'Sort clause preserves qualified column identity and address semantics');
		}
		library_runtime_helpers();
	} finally {
		db_execute_prepared('DELETE FROM settings WHERE name = ?', [$setting]);
		db_execute_prepared('DELETE FROM settings_user WHERE name = ?', [$setting]);
		db_execute_prepared('DELETE FROM settings WHERE name = ?', ['proxy_alert']);

		if ($alert) {
			sql_save($alert, 'settings', 'name');
		}
		$config         = $configuration;
		$_SESSION       = $session;
		$_REQUEST       = $request;
		$_CACTI_REQUEST = $filteredRequest;
		$_POST          = $post;
		$_GET           = $get;
		$_SERVER        = $server;

		if ($ownedLog && is_file($log)) {
			unlink($log);
		}
	}
}

/** Exercise request, table and version helpers whose fallbacks only run for unusual input. */
function library_runtime_helpers(): void {
	global $_CACTI_REQUEST, $tableCount;
	$validation = read_config_option('log_validation');
	$counts     = $tableCount;
	$setting    = 'coverage_remote_' . bin2hex(random_bytes(8));
	$poller     = 0;

	try {
		library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM poller WHERE id > 1 AND disabled = ""'), 0, 'Remote settings scenario starts without reachable collectors');
		$poller                  = (int) sql_save(['id' => 0, 'name' => 'Silent collector fixture', 'hostname' => 'collector.example.invalid', 'disabled' => '', 'last_status' => '2000-01-01 00:00:00'], 'poller');
		$_SESSION[SESS_MESSAGES] = [];
		set_config_option($setting, 'replicated', true);
		library_expect($_SESSION[SESS_MESSAGES]['poller_' . $poller]['level'] ?? null, MESSAGE_LEVEL_WARN, 'A collector without a recent heartbeat is skipped with a warning');
		library_expect(read_config_option($setting, true), 'replicated', 'Skipping a collector still stores the setting locally');
		db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);

		$_SESSION['sort_data'] = [];
		$_CACTI_REQUEST        = [];
		$_REQUEST              = ['add' => 'reset', 'sort_column' => 'ip_address', 'sort_direction' => 'ASC'];
		update_order_string();
		$_CACTI_REQUEST = [];
		$_REQUEST       = ['sort_column' => 'hostname', 'sort_direction' => 'DESC'];
		update_order_string();
		// MariaDB 10.11 provides NATURAL_SORT_KEY, so stored multi-column sorts keep natural hostname order.
		library_expect($_SESSION['sort_string'][get_order_string_page(false)], 'ORDER BY INET_ATON(ip_address) ASC, NATURAL_SORT_KEY(`hostname`) DESC', 'Accumulated sort keeps address and natural hostname order');

		set_config_option('log_validation', 'on');
		$_CACTI_REQUEST = [];
		$_REQUEST       = ['action' => ['edit', 'save']];
		$log            = library_log_written_by(static function (): void { set_default_action('list'); });
		library_expect(get_request_var('action'), 'edit', 'An array action falls back to its first submitted value');
		library_expect(str_contains($log, "Request variable 'action' was passed as array"), true, 'Array action is reported when validation logging is enabled');

		$_CACTI_REQUEST          = [];
		$_REQUEST                = ['rfilter' => 'fixture('];
		$_SESSION[SESS_MESSAGES] = [];
		library_expect(get_filter_request_var('rfilter', FILTER_VALIDATE_IS_REGEX), null, 'An invalid regular expression yields no filter value');
		library_expect(get_request_var('rfilter'), '', 'An invalid regular expression is cleared rather than applied');
		library_expect($_SESSION[SESS_MESSAGES]['custom']['level'], MESSAGE_LEVEL_ERROR, 'An invalid regular expression is reported to the operator');
		library_expect(str_contains($_SESSION[SESS_MESSAGES]['custom']['message'], 'fixture('), true, 'Regular expression error identifies the rejected pattern');

		$form = ['field' => ['value' => 'before |arg1:| after', 'description' => 'unchanged']];
		inject_form_variables($form, 'scalar');
		library_expect($form['field'], ['value' => 'before scalar after', 'description' => 'unchanged'], 'A scalar form argument replaces its placeholder');

		$_CACTI_REQUEST = [];
		$_REQUEST       = [];
		$tableCount     = ['coverage_vcell' => 0];
		$dom            = library_form_dom(static function (): void {
			form_selectable_cell('<b>cell</b>', 'coverage_cell', '', '', 'Cell "quoted" \'title\'');
			form_selectable_vcell('<i>vcell</i>', 'coverage_vcell', 'column', '', 'Vcell "quoted" \'title\'');
		});
		library_expect($dom->evaluate('string(//td[1]/span/@title)'), 'Cell quoted title', 'Cell tooltips cannot break out of their attribute');
		library_expect($dom->evaluate('string(//td[1]/span/b)'), 'cell', 'Cell tooltip wraps the rendered contents');
		library_expect($dom->evaluate('string(//td[2]/span/@title)'), 'Vcell quoted title', 'Vertical cell tooltips cannot break out of their attribute');
		library_expect($dom->evaluate('string(//td[2]/span/i)'), 'vcell', 'Vertical cell tooltip wraps the rendered contents');

		// A tab without an action keeps one table identity, so the second table reads the next stored layout.
		$_REQUEST = ['tab' => 'coverage'];
		$display  = ['name' => ['display' => 'Name'], 'value' => ['display' => 'Value']];
		// Only a stored layout keeps the first table registered, so give the first table one.
		set_user_setting('visible_columns_' . form_get_table_id() . '0', json_encode(['name' => true, 'value' => true]), 1);
		$first    = form_process_visible_display_text('coverage', $display);
		$second   = form_process_visible_display_text('coverage', $display);
		library_expect(array_keys($second), array_keys($first), 'Repeated tables on one tab keep their column set');
		library_expect($tableCount[form_get_table_id()], 1, 'Repeated tables on one tab are counted separately');

		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		library_expect(is_page_ajax(), true, 'XMLHttpRequest marks an AJAX request');
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
		library_expect(is_page_ajax(), false, 'Other requested-with values are not AJAX requests');
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);

		foreach (['host:up port:on' => true, 'host:up port:on extra' => false] as $result => $expected) {
			library_expect(prepare_validate_result($result), $expected, 'Name/value output requires one field per space-separated token');
		}
		library_expect(format_cacti_version('new_install'), CACTI_VERSION . '.0.0', 'A new installation reports the full current version');
		library_expect(format_cacti_version('new_install', CACTI_VERSION_FORMAT_SHORT), CACTI_VERSION, 'A new installation reports the short current version');
		$bits = 0;
		$log  = library_log_written_by(static function () use (&$bits): void { $bits = version_to_bits('99999999.1.1'); });
		library_expect($bits < 0, true, 'An oversized major version overflows the packed version');
		library_expect(str_contains($log, 'Invalid hex passed - '), true, 'An overflowed packed version is reported');

		$items = db_fetch_assoc('SELECT gti.id, gti.graph_template_id, gti.sequence
			FROM graph_templates_item AS gti
			INNER JOIN (SELECT graph_template_id FROM graph_templates_item WHERE local_graph_id = 0 GROUP BY graph_template_id HAVING COUNT(DISTINCT sequence) > 1 LIMIT 1) AS t
			ON t.graph_template_id = gti.graph_template_id
			WHERE gti.local_graph_id = 0
			ORDER BY gti.sequence, gti.id');
		library_expect(count($items) > 1, true, 'Installed templates provide ordered graph items');
		$scope = 'local_graph_id = 0 AND graph_template_id = ' . (int) $items[0]['graph_template_id'];
		$next  = (int) db_fetch_cell_prepared('SELECT id FROM graph_templates_item WHERE ' . $scope . ' AND sequence > ? ORDER BY sequence LIMIT 1', [$items[0]['sequence']]);
		library_expect(get_item('graph_templates_item', 'sequence', (int) $items[0]['id'], $scope, 'next'), $next, 'Next item stays within the requested template');
		library_expect(get_item('graph_templates_item', 'sequence', (int) $items[0]['id'], ['local_graph_id' => 0, 'graph_template_id' => (int) $items[0]['graph_template_id']], 'previous'), (int) $items[0]['id'], 'The first item has no previous item');
	} finally {
		db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
		db_execute_prepared('DELETE FROM settings WHERE name = ?', [$setting]);
		db_execute_prepared('DELETE FROM settings_user WHERE name LIKE ?', ['visible_columns_%:tab-coverage:%']);
		set_config_option('log_validation', $validation);
		$tableCount = $counts;
		unset($_SESSION['sort_data'], $_SESSION['sort_string']);
	}
}
