<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require __DIR__ . '/profile-bootstrap.php';

profile_check((int) db_fetch_cell('SELECT COUNT(*) FROM graph_local') === 0, 'Profiling needs a fresh E2E database.');

$end = 1760000100;

foreach ([
	'auth_method'             => '1', 'path_rrdtool' => '/usr/bin/rrdtool', 'path_php_binary' => '/usr/local/bin/php',
	'rrdtool_version'         => '1.7.2', 'realtime_cache_path' => CACTI_PATH_BASE . '/cache/realtime',
	'boost_rrd_update_enable' => '', 'boost_rrd_update_system_enable' => '', 'boost_png_cache_enable' => '',
	'preview_graphs_per_page' => '50',
] as $name => $value) {
	set_config_option($name, $value);
}

if (!is_dir(CACTI_PATH_BASE . '/cache/realtime')) {
	mkdir(CACTI_PATH_BASE . '/cache/realtime', 0770, true);
}

$theme_names = [];

foreach (glob(CACTI_PATH_INCLUDE . '/themes/*/main.css') as $css) {
	$theme         = basename(dirname($css));
	$theme_names[] = $theme;
	$user          = db_fetch_row('SELECT * FROM user_auth WHERE id = 1');
	unset($user['id']);
	$user['username']             = 'profile-' . $theme;
	$user['password']             = password_hash('profile-fixture-pass', PASSWORD_DEFAULT);
	$user['must_change_password'] = '';
	$user['login_opts']           = 1;
	$user_id                      = sql_save($user, 'user_auth');
	profile_check($user_id > 0, 'Could not create a fixture user.');
	db_execute_prepared('INSERT INTO user_auth_realm (user_id, realm_id) SELECT ?, realm_id FROM user_auth_realm WHERE user_id = 1', [$user_id]);
	db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', [$user_id, 'selected_theme', $theme]);
	db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', [$user_id, 'hide_disabled', '']);
}
set_user_setting('hide_disabled', '', 1);

for ($id = 1; $id <= 500; $id++) {
	profile_check((bool) db_execute_prepared('INSERT INTO host (id, description, hostname, disabled, poller_id, snmp_version) VALUES (?, ?, ?, ?, 1, 0)',
		[$id, sprintf('Profile device %04d', $id), '127.0.0.1', 'on']), 'Could not seed a disabled device.');
}

for ($id = 1; $id <= 50; $id++) {
	db_execute_prepared('INSERT INTO data_local (id, host_id) VALUES (?, ?)', [$id, $id]);
	db_execute_prepared('INSERT INTO data_template_data (local_data_id, name, name_cache, data_source_path, active, rrd_step, data_source_profile_id) VALUES (?, ?, ?, ?, ?, 300, 1)',
		[$id, 'Profile source ' . $id, 'Profile source ' . $id, '<path_rra>/profile_' . $id . '.rrd', 'on']);
	db_execute_prepared('INSERT INTO data_template_rrd (id, local_data_id, data_source_name, data_source_type_id, rrd_minimum, rrd_maximum, rrd_heartbeat) VALUES (?, ?, ?, 1, 0, 1000, 600)', [$id, $id, 'value']);
	db_execute_prepared('INSERT INTO graph_local (id, host_id) VALUES (?, ?)', [$id, $id]);
	db_execute_prepared('INSERT INTO graph_templates_graph (local_graph_id, title, title_cache, image_format_id, height, width, base_value, auto_scale, vertical_label) VALUES (?, ?, ?, 1, 120, 400, 1000, ?, ?)',
		[$id, 'Profile graph ' . $id, 'Profile graph ' . $id, 'on', 'Value']);
	db_execute_prepared('INSERT INTO graph_templates_item (local_graph_id, task_item_id, color_id, graph_type_id, consolidation_function_id, text_format, sequence) VALUES (?, ?, 1, 4, 1, ?, 1)', [$id, $id, 'Fixture value']);

	$file = CACTI_PATH_BASE . '/rra/profile_' . $id . '.rrd';
	db_execute_prepared('INSERT INTO poller_item (local_data_id, host_id, poller_id, rrd_name, rrd_path, rrd_num, snmp_version) VALUES (?, ?, 1, ?, ?, 1, 0)', [$id, $id, 'value', $file]);
	profile_check(rrdtool_execute(['create', $file, '--start', (string) ($end - 86400 - 300), '--step', '300', 'DS:value:GAUGE:600:0:1000', 'RRA:AVERAGE:0.5:1:600'], false) !== false, 'RRD creation failed.');
	$samples = [];

	for ($offset = 86400; $offset >= 0; $offset -= 300) {
		$samples[] = ($end - $offset) . ':' . (20 + (($offset / 300 + $id) % 80));
	}
	profile_check(rrdtool_execute(array_merge(['update', $file], $samples), false) !== false, 'RRD sample update failed.');
}

// Normal graph forms save empty optional strings. SQL defaults leave several
// fields NULL, which produces PHP deprecation output instead of image bytes.
foreach (['graph_templates_graph', 'graph_templates_item'] as $table) {
	foreach (db_fetch_assoc('SHOW COLUMNS FROM ' . $table) as $column) {
		if ($column['Null'] === 'YES') {
			$field = $column['Field'];
			$value = str_contains($column['Type'], 'int') || str_contains($column['Type'], 'decimal') ? 0 : '';
			db_execute_prepared('UPDATE ' . $table . ' SET `' . $field . '` = ? WHERE `' . $field . '` IS NULL', [$value]);
		}
	}
}

$rrdtool_output = shell_exec('/usr/bin/rrdtool --version');
profile_check(preg_match('/^RRDtool ([0-9.]+) /', (string) $rrdtool_output, $rrdtool_match) === 1, 'Unable to determine the installed RRDtool version.');

file_put_contents('/profiles/fixture.json', json_encode([
	'kind'                          => 'disposable SQL/RRD fixture; no live SNMP', 'end' => $end, 'start' => $end - 86400,
	'devices'                       => 500, 'graphs' => 50, 'samples_per_rrd' => 289, 'themes' => $theme_names,
	'php'                           => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'rrdtool' => $rrdtool_match[1],
	'rrdtool_configured_capability' => read_config_option('rrdtool_version', true), 'database' => db_fetch_cell('SELECT VERSION()'),
	'frontend_dependencies'         => json_decode(file_get_contents(CACTI_PATH_BASE . '/package.json'), true, 512, JSON_THROW_ON_ERROR)['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
print "Seeded 500 disabled devices and 50 deterministic RRD graphs.\n";
