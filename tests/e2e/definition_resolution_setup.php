<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || !getenv('CACTI_DEFINITION_APP') || !getenv('CACTI_DEFINITION_DB_PORT') || !getenv('CACTI_DEFINITION_DB_PASSWORD') || !getenv('CACTI_DEFINITION_LOGIN_PASSWORD')) {
	http_response_code(403);
	exit(1);
}

$app    = getenv('CACTI_DEFINITION_APP');
$port   = (int) getenv('CACTI_DEFINITION_DB_PORT');
$config = "<?php\nrequire __DIR__ . '/config.php.dist';\n";

foreach (['database_hostname' => '127.0.0.1', 'database_default' => 'cacti_test',
	'database_username'          => 'root',
	'database_port'              => $port, 'url_path' => '/'] as $key => $value) {
	$config .= '$' . $key . ' = ' . var_export($value, true) . ";\n";
}
$config .= '$database_password = getenv("CACTI_DEFINITION_DB_PASSWORD");' . "\n";
file_put_contents($app . '/include/config.php', $config);
require $app . '/include/global_constants.php';
$conn = new PDO("mysql:host=127.0.0.1;port=$port;dbname=cacti_test", 'root', getenv('CACTI_DEFINITION_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conn->prepare('UPDATE version SET cacti = ?')->execute([CACTI_DEV_VERSION]);
$setting = $conn->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)');

foreach (['install_complete' => '1', 'auth_method' => '1', 'guest_user' => '0', 'admin_user' => '1', 'user_template' => '0'] as $key => $value) {
	$setting->execute([$key, $value]);
}
$conn->prepare("UPDATE user_auth SET password = ?, must_change_password = '', lastchange = ?, login_opts = 2 WHERE id = 1")
	->execute([password_hash(getenv('CACTI_DEFINITION_LOGIN_PASSWORD'), PASSWORD_DEFAULT), time()]);

$definitions = [
	'cdef' => $conn->prepare('INSERT INTO cdef (id, hash, name) VALUES (?, ?, ?)'),
	'vdef' => $conn->prepare('INSERT INTO vdef (id, hash, name) VALUES (?, ?, ?)'),
];
$item_statements = [
	'cdef' => $conn->prepare('INSERT INTO cdef_items (id, cdef_id, sequence, type, value, hash) VALUES (?, ?, ?, ?, ?, ?)'),
	'vdef' => $conn->prepare('INSERT INTO vdef_items (id, vdef_id, sequence, type, value, hash) VALUES (?, ?, ?, ?, ?, ?)'),
];

foreach ($definitions as $kind => $definition) {
	foreach ([9100 => 'E2E Base', 9101 => 'E2E Nested', 9102 => 'E2E Empty', 9103 => 'E2E Cycle', 9104 => 'E2E Unknown'] as $id => $name) {
		$definition->execute([$id, bin2hex(random_bytes(16)), $name]);
	}
	$items = $kind === 'cdef' ? [
		[91000, 9100, 1, 4, 'CURRENT_DATA_SOURCE'], [91001, 9100, 2, 6, '8'], [91002, 9100, 3, 2, '3'],
		[91010, 9101, 1, 5, '9100'], [91011, 9101, 2, 6, '2'], [91012, 9101, 3, 2, '3'],
		[91030, 9103, 1, 5, '9103'], [91040, 9104, 1, 1, '999999'],
	] : [
		[91000, 9100, 1, 4, 'CURRENT_DATA_SOURCE'], [91001, 9100, 2, 6, '95'], [91002, 9100, 3, 1, '8'],
		[91010, 9101, 1, 5, '9100'], [91040, 9104, 1, 99, 'unknown'],
	];
	$item = $item_statements[$kind];

	foreach ($items as $row) {
		$item->execute([...$row, bin2hex(random_bytes(16))]);
	}
}

if (getenv('CACTI_DEFINITION_KIND') === 'sites') {
	$site = $conn->prepare('INSERT INTO sites (id, name, address1, city, latitude, longitude, zoom, notes, disabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

	foreach ([9100 => 'E2E Base Site', 9101 => 'E2E Toggle Site', 9102 => 'E2E Delete Site', 9103 => 'E2E Stale Site'] as $id => $name) {
		$site->execute([$id, $name, '42 Example Street', 'Denver', 39.7, -104.9, 12, 'Preserve site notes', '']);
	}
	$conn->exec("INSERT INTO host (id, hostname, description, site_id, deleted) VALUES (9100, '127.0.0.1', 'E2E Active Site Device', 9102, ''), (9101, '127.0.0.2', 'E2E Deleted Site Device', 9102, 'on')");
}

if (getenv('CACTI_DEFINITION_KIND') === 'gprint') {
	$gprint = $conn->prepare('INSERT INTO graph_templates_gprint (id, hash, name, gprint_text, graphs, templates) VALUES (?, ?, ?, ?, ?, ?)');
	$hashes = [];

	foreach ([9200 => 'E2E Validation GPRINT', 9201 => 'E2E Used GPRINT', 9202 => 'E2E Delete GPRINT A', 9203 => 'E2E Delete GPRINT B'] as $id => $name) {
		$hashes[$id] = bin2hex(random_bytes(16));
		$gprint->execute([$id, $hashes[$id], $name, '%8.2lf %s', $id === 9201 ? 1 : 0, $id === 9201 ? 2 : 0]);
	}
	file_put_contents(__DIR__ . '/gprint_fixture.json', json_encode($hashes, JSON_THROW_ON_ERROR));
}
