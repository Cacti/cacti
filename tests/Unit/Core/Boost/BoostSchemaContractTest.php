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

test('Boost distribution ownership is run scoped without a durable cursor', function () use ($root) {
	$schema = file_get_contents($root . '/cacti.sql');

	expect($schema)->toContain('`run_id` char(32) NOT NULL DEFAULT \'\'')
		->and($schema)->toContain('PRIMARY KEY (`run_id`,`local_data_id`)')
		->and($schema)->toContain('KEY `process_handler` (`run_id`,`process_handler`,`local_data_id`)')
		->and($schema)->toContain('`archive_rows` bigint(20) unsigned NOT NULL DEFAULT 0')
		->and($schema)->not->toContain('`cursor_time`')
		->and($schema)->not->toContain('`cursor_rrd_name`')
		->and($schema)->toContain(') ENGINE=InnoDB ROW_FORMAT=Dynamic;');
});

test('the bounded RRD failure queue stores one retry payload per destination', function () use ($root) {
	$schema = file_get_contents($root . '/cacti.sql');

	expect($schema)->toContain('CREATE TABLE `poller_output_boost_failures`')
		->and($schema)->toContain('PRIMARY KEY (`local_data_id`)')
		->and($schema)->toContain('KEY `next_retry` (`next_retry`)')
		->and($schema)->toContain('`generation` char(32) NOT NULL');
});
