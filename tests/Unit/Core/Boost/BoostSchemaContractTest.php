<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

test('Boost sample identity is enforced by its full primary key', function () use ($root) {
	$schema = file_get_contents($root . '/cacti.sql');

	expect($schema)->toContain('PRIMARY KEY USING BTREE (`local_data_id`, `time`, `rrd_name`)');
});

test('child completion identity is run scoped in new and upgraded installs', function () use ($root) {
	$schema  = file_get_contents($root . '/cacti.sql');
	$upgrade = file_get_contents($root . '/install/upgrades/1_2_32.php');

	expect($schema)->toContain('UNIQUE KEY `run_child` (`run_id`,`child_id`)')
		->and($upgrade)->toContain("db_install_add_key('poller_output_boost_processes', 'UNIQUE', 'run_child', array('run_id', 'child_id'))");
});
