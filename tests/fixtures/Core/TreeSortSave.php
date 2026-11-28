<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/Helpers/TreeSqliteFixture.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

// The real form exits after redirecting. Check persisted state at shutdown,
// before the coverage writer runs, so an early exit cannot pass this contract.
register_shutdown_function(static function () : void {
	try {
		$tree = TreeSqliteFixture::$pdo->query('SELECT name, sort_type, modified_by FROM graph_tree WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

		if ($tree !== ['name' => 'Sorted tree', 'sort_type' => 2, 'modified_by' => 1]
			|| TreeSqliteFixture::childTitles(0) !== ['Alpha', 'Zulu']
			|| TreeSqliteFixture::childTitles(1) !== ['Zulu child', 'Alpha child']
			|| isset($_SESSION['dhtml_tree']) || is_error_message()) {
			throw new RuntimeException('Saving the tree did not persist the requested root ordering.');
		}
		print json_encode(['assertions' => 5, 'passed' => true], JSON_THROW_ON_ERROR) . "\n";
	} finally {
		TreeSqliteFixture::restore();
	}
});
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

$_SESSION = [SESS_USER_ID => 1, 'dhtml_tree' => ['stale']];
TreeSqliteFixture::reset();
TreeSqliteFixture::$pdo->exec('ALTER TABLE graph_tree ADD COLUMN last_modified TEXT');
TreeSqliteFixture::$pdo->exec('ALTER TABLE graph_tree ADD COLUMN modified_by INTEGER');
TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name, sort_type) VALUES (1, 'Old tree', 1)");
TreeSqliteFixture::items([
	['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'Zulu', 'sort_children_type' => 1],
	['id' => 2, 'parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'Alpha'],
	['id' => 3, 'parent' => 1, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'Zulu child'],
	['id' => 4, 'parent' => 1, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'Alpha child'],
]);
$_REQUEST = $_POST = [
	'save_component_tree' => '1', 'id' => '1', 'sequence' => '1',
	'name'                => 'Sorted tree', 'sort_type' => '2', 'enabled' => 'on',
];
$_CACTI_REQUEST = [];
cacti_fixture_source_function(dirname(__DIR__, 3) . '/tree.php', 'form_save', '');
form_save();
