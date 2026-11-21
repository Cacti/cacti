<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require __DIR__ . '/automation_creation_bootstrap.php';

clear_fixture();
$mode = $argv[1];
$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'Tree',4)");
$pdo->exec("INSERT INTO graph_tree_items (id,graph_tree_id,parent,title,position,sort_children_type) VALUES
	(1,1,0,'Header',1,0),(2,1,1,'item 10',2,0),(3,1,1,'item 2',1,0)");
$before = $pdo->query('SELECT * FROM graph_tree_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

if (in_array($mode, ['delete-lock', 'save-lock', 'sort-lock', 'graph-lock'], true)) {
	// A second real connection owns the named lock while the operation times out.
	$owner = new PDO($dsn, getenv('CACTI_AUTOMATION_TEST_USER') ?: 'root', getenv('CACTI_AUTOMATION_TEST_PASSWORD') ?: '');
	$kind  = $mode === 'graph-lock' ? 'auto' : 'tree';
	$lock  = $owner->prepare("SELECT GET_LOCK(CONCAT('cacti.$kind.', SHA1(CONCAT(DATABASE(), ':', ?))), 0)");
	$lock->execute([1]);
	check((int)$lock->fetchColumn() === 1, 'competing worker owns the lock');

	if ($mode === 'delete-lock') {
		api_tree_delete_node(1, 'tbranch:1');
	} elseif ($mode === 'save-lock') {
		check(api_tree_item_save(0, 1, TREE_ITEM_TYPE_HEADER, 0, 'Blocked', 0, 0, 0, 0, 0, false) === false, 'locked insertion fails');
	} elseif ($mode === 'sort-lock') {
		api_tree_sort_branch(0, 1);
	} else {
		$values = [];
		check(automation_create_graph(1, 1, [], $values) === false, 'locked graph creation fails');
		check((int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn() === 0, 'locked graph creation leaves no shell');
	}
	$owner = null;
	check($pdo->query('SELECT * FROM graph_tree_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before, 'lock denial preserves the tree');
} elseif ($mode === 'parent') {
	check(api_tree_item_save(0, 1, TREE_ITEM_TYPE_HEADER, 999999, 'Orphan', 0, 0, 0, 0, 0, false) === false, 'missing parent rejects insertion');
	check($pdo->query('SELECT * FROM graph_tree_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before, 'invalid parent preserves the tree');
} elseif ($mode === 'recursive') {
	$pdo->exec('DELETE FROM sites');
	$pdo->exec("INSERT INTO sites (id,name) VALUES (1,'Site')");
	$pdo->exec("INSERT INTO graph_tree_items (id,graph_tree_id,parent,title,position,site_id,sort_children_type) VALUES
		(4,1,0,'',2,1,0),(5,1,4,'item 10',1,0,0),(6,1,4,'item 2',2,0,0)");
	api_tree_sort_branch(0, 1, true);

	foreach ([1, 4] as $parent) {
		check($pdo->query("SELECT title FROM graph_tree_items WHERE parent=$parent ORDER BY position")->fetchAll(PDO::FETCH_COLUMN) === ['item 2', 'item 10'], 'recursive natural order applies beneath headers and sites');
	}
}

emit_result($mode, ['verified' => true]);
