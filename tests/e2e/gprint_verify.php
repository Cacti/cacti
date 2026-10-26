<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || !getenv('CACTI_DEFINITION_DB_PORT') || !getenv('CACTI_DEFINITION_DB_PASSWORD')) {
	http_response_code(403);
	exit(1);
}

$port   = (int) getenv('CACTI_DEFINITION_DB_PORT');
$conn   = new PDO("mysql:host=127.0.0.1;port=$port;dbname=cacti_test", 'root', getenv('CACTI_DEFINITION_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$hashes = json_decode(file_get_contents(__DIR__ . '/gprint_fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = [
	'SELECT COUNT(*) FROM graph_templates_gprint WHERE id IN (9202, 9203)'                                                         => 0,
	'SELECT COUNT(*) FROM graph_templates_gprint WHERE id = 9201 AND graphs = 1 AND templates = 2'                                 => 1,
	"SELECT COUNT(*) FROM graph_templates_gprint WHERE name = 'E2E Renamed GPRINT' AND gprint_text = '%8.4lf %s\\\\n'"             => 1,
	"SELECT COUNT(*) FROM graph_templates_gprint WHERE id = 9200 AND name = 'E2E Validation GPRINT' AND gprint_text = '%8.2lf %s'" => 1,
];

foreach ($checks as $sql => $expected) {
	if ((int) $conn->query($sql)->fetchColumn() !== $expected) {
		throw new RuntimeException('GPRINT browser database assertion failed: ' . $sql);
	}
}
$lookup = $conn->prepare('SELECT hash FROM graph_templates_gprint WHERE id = ?');

foreach ([9200, 9201] as $id) {
	$lookup->execute([$id]);

	if ($lookup->fetchColumn() !== $hashes[$id]) {
		throw new RuntimeException('An unchanged GPRINT hash was replaced.');
	}
}
print "GPRINT browser database assertions passed.\n";
