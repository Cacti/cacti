<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || !getenv('CACTI_DEFINITION_DB_PORT') || !getenv('CACTI_DEFINITION_DB_PASSWORD')) {
	http_response_code(403);
	exit(1);
}

$port   = (int) getenv('CACTI_DEFINITION_DB_PORT');
$conn   = new PDO("mysql:host=127.0.0.1;port=$port;dbname=cacti_test", 'root', getenv('CACTI_DEFINITION_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = [
	'SELECT COUNT(*) FROM sites WHERE id = 9102'                                                                          => 0,
	'SELECT site_id FROM host WHERE id = 9100'                                                                            => 0,
	'SELECT site_id FROM host WHERE id = 9101'                                                                            => 9102,
	"SELECT COUNT(*) FROM settings WHERE name IN ('time_last_change_site', 'time_last_change_site_device') AND value > 0" => 2,
];

foreach ($checks as $sql => $expected) {
	if ((int) $conn->query($sql)->fetchColumn() !== $expected) {
		throw new RuntimeException('Sites browser database assertion failed: ' . $sql);
	}
}
print "Sites browser database assertions passed.\n";
