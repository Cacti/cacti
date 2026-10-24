<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || getenv('CACTI_E2E_DISPOSABLE', true) !== '1') {
	fwrite(STDERR, "This fixture requires an explicitly selected disposable E2E container.\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
require $root . '/include/cli_check.php';

function cacti_accessibility_fixture_command(string $script, array $arguments): void {
	$process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 3) . '/cli/' . $script], $arguments), [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);

	if (!is_resource($process) || proc_close($process) !== 0) {
		throw new RuntimeException('Fixture command failed: ' . $script);
	}
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM host_template')) {
	cacti_accessibility_fixture_command('import_package.php', ['--filename=' . $root . '/install/templates/Local_Linux_Machine.xml.gz', '--with-profile']);
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM host')) {
	cacti_accessibility_fixture_command('add_device.php', ['--description=Sonar browser fixture', '--ip=127.0.0.1', '--template=1', '--disable=1', '--version=0', '--avail=none']);
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM user_auth_group')) {
	cacti_accessibility_fixture_command('add_group.php', ['--type=add_group', '--name=Sonar browser fixture', '--description=Disposable browser regression fixture']);
}

// Parent directories must remain traversable by the web worker. Restrict the
// actual key directory separately; the CI caller assigns it to www-data.
if (!is_dir(CACTI_PATH_PKI) && !mkdir(CACTI_PATH_PKI, 0755, true) && !is_dir(CACTI_PATH_PKI)) {
	throw new RuntimeException('Unable to create the disposable key directory.');
}

if (!chmod(CACTI_PATH_PKI, 0700)) {
	throw new RuntimeException('Unable to restrict the disposable key directory.');
}

if (!is_file(CACTI_PATH_PKI . '/package.info')) {
	cacti_accessibility_fixture_command('genkey.php', ['--generate', '--author=Disposable Sonar fixture', '--homepage=https://example.invalid', '--email=fixture@example.invalid', '--country=US', '--state=Test', '--org=Test', '--unit=Test', '--days=1']);
}
$graph_id = (int) db_fetch_cell('SELECT id FROM graph_local ORDER BY id LIMIT 1');

if (!$graph_id) {
	$host_id     = (int) db_fetch_cell('SELECT id FROM host ORDER BY id LIMIT 1');
	$template_id = (int) db_fetch_cell_prepared('SELECT graph_template_id FROM host_template_graph
		WHERE host_template_id = (SELECT host_template_id FROM host WHERE id = ?)
		ORDER BY graph_template_id LIMIT 1', [$host_id]);

	if (!$host_id || !$template_id) {
		throw new RuntimeException('The disposable graph fixture requires a device and graph template.');
	}
	cacti_accessibility_fixture_command('add_graphs.php', ['--host-id=' . $host_id, '--graph-type=cg', '--graph-template-id=' . $template_id]);
	$graph_id = (int) db_fetch_cell_prepared('SELECT id FROM graph_local WHERE host_id = ? AND graph_template_id = ? ORDER BY id LIMIT 1', [$host_id, $template_id]);
}

if (!$graph_id) {
	throw new RuntimeException('The disposable graph fixture was not created.');
}
cacti_accessibility_fixture_command('add_perms.php', ['--user-id=1', '--item-type=graph', '--item-id=' . $graph_id]);
db_execute_prepared('INSERT INTO aggregate_graphs
	(aggregate_template_id, local_graph_id, title_format, graph_template_id,
	 gprint_prefix, graph_type, total, total_type, total_prefix, order_type, user_id)
	SELECT 0, gl.id, ?, gl.graph_template_id, ?, 1, 0, 0, ?, 0, 1
	FROM graph_local AS gl
	WHERE gl.id = ? AND NOT EXISTS (
		SELECT 1 FROM aggregate_graphs AS ag WHERE ag.local_graph_id = gl.id
	)', ['Disposable browser aggregate', '', '', $graph_id]);
$aggregate_id = (int) db_fetch_cell_prepared('SELECT id FROM aggregate_graphs WHERE local_graph_id = ?', [$graph_id]);
file_put_contents('/tmp/cacti-accessibility-fixture.json', json_encode(['graph_id' => $graph_id, 'aggregate_id' => $aggregate_id], JSON_THROW_ON_ERROR));
db_execute_prepared('UPDATE user_auth SET must_change_password = ?, password_change = ?, policy_graphs = ? WHERE id = ?', ['', 'on', 2, 1]);
db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', ['auth_method', '1']);
db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', [1, 'hide_disabled', '']);
db_execute_prepared('REPLACE INTO user_auth_reset_hashes (user_id, hash, expiry) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))', [1, 'sonarbrowserfixture20261010']);
print "Disposable native accessibility fixtures prepared.\n";
