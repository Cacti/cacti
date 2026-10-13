<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/
if (PHP_SAPI !== 'cli' || !getenv('BOOST_DB_HOST') || (getenv('BOOST_DB_NAME') ?: 'cacti_boost_contract') !== 'cacti_boost_contract') {
	throw new RuntimeException('Use the dedicated cacti_boost_contract database.');
}
$pdo     = new PDO('mysql:host=' . getenv('BOOST_DB_HOST') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';dbname=cacti_boost_contract', getenv('BOOST_DB_USER') ?: 'root', getenv('BOOST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix  = 'ack_profile_' . bin2hex(random_bytes(5));
$results = [];
$pdo->exec('SET SESSION max_heap_table_size=536870912');

foreach (['InnoDB', 'MEMORY'] as $engine) {
	$name = $prefix . '_' . strtolower($engine);

	try {
		$pdo->exec("CREATE TABLE $name (local_data_id INT UNSIGNED NOT NULL, rrd_name VARCHAR(19) NOT NULL, time TIMESTAMP NOT NULL, output VARCHAR(64), PRIMARY KEY(local_data_id,rrd_name,time)) ENGINE=$engine");
		$insert = $pdo->prepare("INSERT INTO $name VALUES " . implode(',',array_fill(0,1000,'(?,?,?,?)')));

		for ($start = 1; $start <= 100000; $start += 1000) {
			$params = [];

			for ($id = $start; $id < $start + 1000; $id++) {
				array_push($params,$id,'in','2026-10-12 00:00:00','42');
			}$insert->execute($params);
		}
		$keys = [];

		for ($id = 1; $id <= 1000; $id++) {
			array_push($keys,$id * 50,'in','2026-10-12 00:00:00');
		}
		$sql     = "DELETE FROM $name WHERE (local_data_id,rrd_name,time) IN (" . implode(',',array_fill(0,1000,'(?,?,?)')) . ')';
		$explain = $pdo->prepare('EXPLAIN ' . $sql);
		$explain->execute($keys);
		$old_plan = $explain->fetchAll(PDO::FETCH_ASSOC);
		$pdo->exec("CREATE TEMPORARY TABLE $prefix (PRIMARY KEY(local_data_id,rrd_name,time)) ENGINE=MEMORY AS SELECT local_data_id,rrd_name,time FROM $name LIMIT 0");
		$fill = $pdo->prepare("INSERT INTO $prefix VALUES " . implode(',',array_fill(0,1000,'(?,?,?)')));
		$fill->execute($keys);
		$join     = "DELETE po FROM $prefix AS ack STRAIGHT_JOIN $name AS po ON po.local_data_id=ack.local_data_id AND po.rrd_name=ack.rrd_name AND po.time=ack.time";
		$new_plan = $pdo->query('EXPLAIN ' . $join)->fetchAll(PDO::FETCH_ASSOC);
		$samples  = [];

		for ($round = 0; $round < 5; $round++) {
			foreach (($round % 2 ? ['joined', 'tuple'] : ['tuple', 'joined']) as $kind) {
				$t = hrtime(true);

				if ($kind === 'tuple') {
					$s = $pdo->prepare($sql);
					$s->execute($keys);
				} else {
					$pdo->exec("DELETE FROM $prefix");
					$fill->execute($keys);
					$s = $pdo->prepare($join);
					$s->execute();
				}
				$elapsed = (hrtime(true) - $t) / 1e6;
				$deleted = $s->rowCount();

				if ($deleted !== 1000) {
					throw new RuntimeException('Acknowledgement identity mismatch');
				}
				$restore = $pdo->prepare("INSERT INTO $name VALUES " . implode(',',array_fill(0,1000,'(?,?,?,?)')));
				$params  = [];

				for ($i = 0; $i < count($keys); $i += 3) {
					array_push($params,$keys[$i],$keys[$i + 1],$keys[$i + 2],'42');
				}$restore->execute($params);
				$samples[] = ['kind'=>$kind, 'round'=>$round + 1, 'milliseconds'=>$elapsed];
			}
		}
		$results[] = ['engine'=>$engine, 'rows'=>100000, 'batch'=>1000, 'tuple_plan'=>$old_plan, 'joined_plan'=>$new_plan, 'samples'=>$samples];
	} finally {
		$pdo->exec("DROP TEMPORARY TABLE IF EXISTS $prefix");
		$pdo->exec("DROP TABLE IF EXISTS $name");
	}
}
echo json_encode(['database'=>$pdo->query('SELECT VERSION()')->fetchColumn(), 'limitations'=>'Synthetic local database, 100k rows, VARCHAR(64) output; no representative RTM concurrency or purge pressure. Joined timings include clearing and refilling keys, exclude initial temporary-table creation.', 'results'=>$results],JSON_PRETTY_PRINT),"\n";
