<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * rrdcheck had no key, and user_log was keyed on (username, user_id, time), so
 * a second event for one user in the same second collided and its INSERT
 * IGNORE stored nothing (issue#8884). Both tables now carry an auto_increment
 * primary key. This suite has no live MySQL fixture, so these tests pin the
 * fresh-install schema, the upgrade and the audit baseline to one definition.
 */

$root = dirname(__DIR__, 3);

function schema_keys_create_table(string $sql, string $table) : string {
	$pos = strpos($sql, 'CREATE TABLE `' . $table . '` (');
	expect($pos)->not->toBeFalse();

	return substr($sql, $pos, strpos($sql, ') ENGINE=', $pos) - $pos);
}

function schema_keys_function_body(string $src, string $function) : string {
	$pos = strpos($src, 'function ' . $function . '(');
	expect($pos)->not->toBeFalse();

	return substr($src, $pos, strpos($src, "\n}\n", $pos) - $pos);
}

test('cacti.sql gives rrdcheck an auto_increment primary key and a local_data_id index', function () use ($root) {
	$table = schema_keys_create_table(file_get_contents($root . '/cacti.sql'), 'rrdcheck');

	expect($table)->toContain('`id` bigint(20) unsigned NOT NULL auto_increment,');
	expect($table)->toContain('PRIMARY KEY (`id`)');
	expect($table)->toContain('KEY `local_data_id` (`local_data_id`)');
});

test('cacti.sql keys user_log on id and keeps the old key columns as a plain index', function () use ($root) {
	$table = schema_keys_create_table(file_get_contents($root . '/cacti.sql'), 'user_log');

	expect($table)->toContain('`id` bigint(20) unsigned NOT NULL auto_increment,');
	expect($table)->toContain('PRIMARY KEY (`id`)');
	expect($table)->toContain('KEY `username_user_id_time` (`username`,`user_id`,`time`)');
	expect($table)->toContain('KEY user_id (`user_id`)');

	// A unique key over these columns is what dropped same-second events.
	expect($table)->not->toContain('PRIMARY KEY (`username`,`user_id`,`time`)');
	expect($table)->not->toContain('UNIQUE');
});

test('the 1.3.0 upgrade applies the same definitions and only while the id column is missing', function () use ($root) {
	$src = file_get_contents($root . '/install/upgrades/1_3_0.php');

	expect(schema_keys_function_body($src, 'upgrade_to_1_3_0'))->toContain('upgrade_rrdcheck_user_log_keys();');

	$body = schema_keys_function_body($src, 'upgrade_rrdcheck_user_log_keys');

	// An auto_increment column must be created with its key, so the ALTER
	// cannot be split across helpers and is guarded for a second run instead.
	expect($body)->toContain("if (!db_column_exists('rrdcheck', 'id', false)) {");
	expect($body)->toContain("if (!db_column_exists('user_log', 'id', false)) {");
	expect(substr_count($body, 'ADD COLUMN `id` bigint(20) unsigned NOT NULL auto_increment FIRST,'))->toBe(2);
	expect(substr_count($body, 'ADD PRIMARY KEY (`id`)'))->toBe(2);
	expect(substr_count($body, 'DROP PRIMARY KEY,'))->toBe(1);

	expect($body)->toContain("db_install_add_key('rrdcheck', 'INDEX', 'local_data_id', ['local_data_id']);");
	expect($body)->toContain("db_install_add_key('user_log', 'INDEX', 'username_user_id_time', ['username', 'user_id', 'time']);");
});

test('audit_schema.sql records the new columns and indexes', function () use ($root) {
	$audit = file_get_contents($root . '/docs/audit_schema.sql');

	$rows = [
		"INSERT INTO `table_columns` VALUES ('rrdcheck',1,'id','bigint(20) unsigned','NO','PRI',NULL,'auto_increment');",
		"INSERT INTO `table_columns` VALUES ('rrdcheck',2,'local_data_id','mediumint(8) unsigned','NO','MUL',NULL,'');",
		"INSERT INTO `table_columns` VALUES ('user_log',1,'id','bigint(20) unsigned','NO','PRI',NULL,'auto_increment');",
		"INSERT INTO `table_columns` VALUES ('user_log',2,'username','varchar(50)','NO','MUL','0','');",
		"INSERT INTO `table_columns` VALUES ('user_log',4,'time','timestamp','NO','','0000-00-00 00:00:00','');",
		"INSERT INTO `table_indexes` VALUES ('rrdcheck',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE','');",
		"INSERT INTO `table_indexes` VALUES ('rrdcheck',1,'local_data_id',1,'local_data_id','A',0,NULL,NULL,'','BTREE','');",
		"INSERT INTO `table_indexes` VALUES ('user_log',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE','');",
		"INSERT INTO `table_indexes` VALUES ('user_log',1,'username_user_id_time',1,'username','A',2,NULL,NULL,'','BTREE','');",
		"INSERT INTO `table_indexes` VALUES ('user_log',1,'username_user_id_time',2,'user_id','A',2,NULL,NULL,'','BTREE','');",
		"INSERT INTO `table_indexes` VALUES ('user_log',1,'username_user_id_time',3,'time','A',2,NULL,NULL,'','BTREE','');",
	];

	foreach ($rows as $row) {
		expect($audit)->toContain($row);
	}

	expect($audit)->not->toContain("('user_log',0,'PRIMARY',1,'username'");
});

test('every user_log and rrdcheck writer names its columns', function () use ($root) {
	$writers = [
		'auth_2fa.php', 'auth_changepassword.php', 'auth_login.php', 'auth_resetpassword.php',
		'login_sso.php', 'include/auth.php', 'lib/auth.php', 'lib/rrdcheck.php',
	];

	$inserts = 0;

	foreach ($writers as $file) {
		$src = file_get_contents($root . '/' . $file);

		// A positional VALUES list would now be one column short.
		expect(preg_match('/INSERT\s+(IGNORE\s+)?INTO\s+(user_log|rrdcheck)\s+VALUES/i', $src))->toBe(0);

		$inserts += preg_match_all('/INSERT\s+(IGNORE\s+)?INTO\s+(user_log|rrdcheck)\s*\(/i', $src);
	}

	expect($inserts)->toBeGreaterThan(0);
});

test('a failed login is written to user_log once', function () use ($root) {
	// A credential failure runs auth_process_lockout() and then reaches the
	// failure branch of auth_login.php. Both used to insert the same
	// (username, user_id, time) row and the old primary key discarded the
	// second one; with the id key each insert is stored, so only one may run.
	$lockout = schema_keys_function_body(file_get_contents($root . '/lib/auth.php'), 'auth_process_lockout');

	expect($lockout)->not->toContain('user_log');

	expect(file_get_contents($root . '/auth_login.php'))->toContain("// BAD username/password builtin and LDAP\n\t\tdb_execute_prepared('INSERT IGNORE INTO user_log");
});
