<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

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
		$config['proxy_headers']   = ['HTTP_X_FORWARDED_FOR'];
		$config['trusted_proxies'] = ['192.0.2.7'];
		library_expect(get_client_addr(), '198.51.100.9', 'Explicitly trusted proxy contributes the client address');
		$config['trusted_proxies'] = [];
		library_expect(get_client_addr(), '192.0.2.7', 'Untrusted peer cannot select its client identity');
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
