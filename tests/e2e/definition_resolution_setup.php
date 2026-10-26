<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || !getenv('CACTI_DEFINITION_APP') || !getenv('CACTI_DEFINITION_DB_PORT')) {
	http_response_code(403);
	exit(1);
}

$app    = getenv('CACTI_DEFINITION_APP');
$port   = (int) getenv('CACTI_DEFINITION_DB_PORT');
$config = "<?php\nrequire __DIR__ . '/config.php.dist';\n";

foreach (['database_hostname' => '127.0.0.1', 'database_default' => 'cacti_test',
	'database_username'          => 'root', 'database_password' => 'definition_fixture',
	'database_port'              => $port, 'url_path' => '/'] as $key => $value) {
	$config .= '$' . $key . ' = ' . var_export($value, true) . ";\n";
}
file_put_contents($app . '/include/config.php', $config);
require $app . '/include/global_constants.php';
$conn = new PDO("mysql:host=127.0.0.1;port=$port;dbname=cacti_test", 'root', 'definition_fixture', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conn->prepare('UPDATE version SET cacti = ?')->execute([CACTI_DEV_VERSION]);
$setting = $conn->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)');

foreach (['install_complete' => '1', 'auth_method' => '1', 'guest_user' => '0', 'admin_user' => '1', 'user_template' => '0'] as $key => $value) {
	$setting->execute([$key, $value]);
}
$conn->prepare("UPDATE user_auth SET password = ?, must_change_password = '', lastchange = ?, login_opts = 2 WHERE id = 1")
	->execute([password_hash('CactiDefinitionFixture!42', PASSWORD_DEFAULT), time()]);

foreach (['cdef', 'vdef'] as $kind) {
	$definition = $conn->prepare("INSERT INTO $kind (id, hash, name) VALUES (?, ?, ?)");

	foreach ([9100 => 'E2E Base', 9101 => 'E2E Nested', 9102 => 'E2E Empty', 9103 => 'E2E Cycle', 9104 => 'E2E Unknown'] as $id => $name) {
		$definition->execute([$id, md5($kind . $id), $name]);
	}
	$items = $kind === 'cdef' ? [
		[91000, 9100, 1, 4, 'CURRENT_DATA_SOURCE'], [91001, 9100, 2, 6, '8'], [91002, 9100, 3, 2, '3'],
		[91010, 9101, 1, 5, '9100'], [91011, 9101, 2, 6, '2'], [91012, 9101, 3, 2, '3'],
		[91030, 9103, 1, 5, '9103'], [91040, 9104, 1, 1, '999999'],
	] : [
		[91000, 9100, 1, 4, 'CURRENT_DATA_SOURCE'], [91001, 9100, 2, 6, '95'], [91002, 9100, 3, 1, '8'],
		[91010, 9101, 1, 5, '9100'], [91040, 9104, 1, 99, 'unknown'],
	];
	$item = $conn->prepare("INSERT INTO {$kind}_items (id, {$kind}_id, sequence, type, value, hash) VALUES (?, ?, ?, ?, ?, ?)");

	foreach ($items as $row) {
		$item->execute([...$row, md5($kind . $row[0])]);
	}
}
