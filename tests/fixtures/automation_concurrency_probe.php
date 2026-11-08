<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require __DIR__ . '/automation_creation_bootstrap.php';
$kind = $argv[1];
if (($argv[2] ?? '') == 'worker') {
	fwrite(STDOUT, "ready\n");
	fgets(STDIN);
	$pdo->before = function ($sql) {
		if (preg_match('/INSERT INTO (?:`?graph_tree_items|`?graph_local)/i', $sql)) usleep(200000);
	};
	if ($kind == 'graph') {
		$values = array();
		$value = automation_create_graph(1, 1, array(), $values);
		$id = $value['local_graph_id'] ?? 0;
	} else {
		$parent = create_header_node('Shared header', array('tree_id'=>1), array('sort_type'=>4, 'propagate_changes'=>''), 0);
		$id = create_graph_node(1, $parent, array('tree_id'=>1, 'host_grouping_type'=>1));
	}
	print json_encode(array('id'=>(int)$id, 'errors'=>$review_logs)) . PHP_EOL;
	exit;
}
clear_fixture();
seed_graph_template();
$pdo->exec("INSERT INTO graph_tree (id,name,sort_type) VALUES (1,'fixture',4)");
$pdo->exec("INSERT INTO graph_templates_graph (local_graph_id,title_cache) VALUES (1,'Concurrent graph')");
if ($kind == 'graph') $pdo->exec('DELETE FROM graph_templates_graph WHERE local_graph_id=1');
$workers = array();
for ($i=0; $i<2; $i++) {
	$process = proc_open(array(PHP_BINARY, '-d', 'display_errors=stderr', __FILE__, $kind, 'worker'), array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
	check(is_resource($process), 'worker starts');
	stream_set_timeout($pipes[1], 15);
	check(fgets($pipes[1]) === "ready\n", 'worker reaches barrier');
	$workers[] = array($process, $pipes);
}
foreach ($workers as $worker) { fwrite($worker[1][0], "go\n"); fclose($worker[1][0]); }
$ids = array();
foreach ($workers as $worker) {
	$out = stream_get_contents($worker[1][1]);
	$err = stream_get_contents($worker[1][2]);
	fclose($worker[1][1]); fclose($worker[1][2]);
	check(proc_close($worker[0]) === 0, 'worker completes: ' . $err);
	$row = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
	check(!array_filter($row['errors'], fn($message) => str_contains($message, 'ERROR:') || str_contains($message, 'WARNING:')), 'worker has no database errors: ' . json_encode($row['errors']));
	$ids[] = $row['id'];
}
print json_encode(array('ids'=>$ids, 'graphs'=>(int)$pdo->query('SELECT COUNT(*) FROM graph_local')->fetchColumn(), 'sources'=>(int)$pdo->query('SELECT COUNT(*) FROM data_local')->fetchColumn(), 'headers'=>(int)$pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE local_graph_id=0')->fetchColumn(), 'leaves'=>(int)$pdo->query('SELECT COUNT(*) FROM graph_tree_items WHERE local_graph_id=1')->fetchColumn())) . PHP_EOL;
