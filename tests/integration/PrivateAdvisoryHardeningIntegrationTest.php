<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 |
 | Docker-backed integration test for private advisory hardening.
 |
 | Run inside the Cacti container:
 |   docker exec cacti-e2e-master php /var/www/html/tests/integration/PrivateAdvisoryHardeningIntegrationTest.php
 |
 | Exit 0 on all assertions passing; exit 1 on first failure.
 +-------------------------------------------------------------------------+
*/

define('CACTI_CLI_ONLY', true);
define('IN_PLUGIN_INSTALL', true);
chdir('/var/www/html');

require_once 'include/global.php';
require_once 'lib/database.php';
require_once 'lib/rrd.php';

$failures = 0;

function private_advisory_pass($label) {
	echo "PASS: {$label}" . PHP_EOL;
}

function private_advisory_fail($label, $detail = '') {
	global $failures;

	$failures++;
	echo "FAIL: {$label}" . ($detail !== '' ? " -- {$detail}" : '') . PHP_EOL;
}

function private_advisory_assert_true($label, $value) {
	if ($value) {
		private_advisory_pass($label);
	} else {
		private_advisory_fail($label);
	}
}

function private_advisory_assert_false($label, $value) {
	if (!$value) {
		private_advisory_pass($label);
	} else {
		private_advisory_fail($label);
	}
}

private_advisory_assert_true('normal RRDtool path is valid', cacti_rrdtool_valid_path('/var/www/html/rra/test.rrd'));
private_advisory_assert_false('newline RRDtool path is rejected', cacti_rrdtool_valid_path("/var/www/html/rra/test.rrd\nquit\n"));
private_advisory_assert_false('space-delimited RRDtool path token is rejected', cacti_rrdtool_valid_path_token('/var/www/html/rra/test file.rrd'));
private_advisory_assert_true('safe rejected value logging escapes control chars', cacti_log_safe_value("bad\npath") === '"bad\\npath"');
private_advisory_assert_false('rrdtool_file_exists rejects newline path before RRDtool', rrdtool_file_exists("/var/www/html/rra/test.rrd\nquit\n", false));
private_advisory_assert_true('rrdtool path command builder accepts safe path', rrdtool_build_path_command('info', '/var/www/html/rra/test.rrd') === 'info /var/www/html/rra/test.rrd');
private_advisory_assert_false('rrdtool path command builder rejects newline path', rrdtool_build_path_command('info', "/var/www/html/rra/test.rrd\nquit\n"));
private_advisory_assert_false('rrdtool path command builder rejects whitespace path token', rrdtool_build_path_command('info', '/var/www/html/rra/test file.rrd'));
private_advisory_assert_false('rrdtool restore command rejects newline xml path', rrdtool_execute_restore_command("/var/www/html/rra/test.rrd.xml\nquit\n", '/var/www/html/rra/test.rrd'));
private_advisory_assert_true('numeric rrd maximum is valid', cacti_rrdtool_valid_bound('1.23e+10'));
private_advisory_assert_true('U rrd maximum is valid', cacti_rrdtool_valid_bound('U'));
private_advisory_assert_false('newline rrd maximum is rejected', cacti_rrdtool_valid_bound("1\nquit\n"));
private_advisory_assert_true('rrd data source template is valid', cacti_rrdtool_valid_ds_template('traffic_in:traffic_out'));
private_advisory_assert_false('rrd data source template with command break is rejected', cacti_rrdtool_valid_ds_template("traffic_in\nquit\n"));
private_advisory_assert_true('safe ddl identifier is valid', db_is_safe_identifier('plugin_example_column'));
private_advisory_assert_false('backtick ddl identifier is rejected', db_is_safe_identifier("plugin_example` int; --"));
private_advisory_assert_true('decimal column type is valid', db_is_safe_column_type('decimal(7,5)'));
private_advisory_assert_false('comma injected column type is rejected', db_is_safe_column_type('int, ADD injected int'));
private_advisory_assert_true('safe column definition is valid', db_is_safe_column_definition(array('name' => 'private_advisory_test', 'type' => 'varchar (255)', 'NULL' => false)));
private_advisory_assert_false('injected column definition is rejected', db_is_safe_column_definition(array('name' => "private_advisory_test` int; --", 'type' => 'varchar (255)', 'NULL' => false)));
private_advisory_assert_false('injected column type is rejected', db_is_safe_column_definition(array('name' => 'private_advisory_test', 'type' => "varchar (255); DROP TABLE colors", 'NULL' => false)));
private_advisory_assert_true('safe table definition is valid', db_is_safe_table_definition(array('type' => 'InnoDB', 'columns' => array(array('name' => 'id', 'type' => 'int', 'NULL' => false)), 'primary' => array('id'))));
private_advisory_assert_false('injected table engine is rejected', db_is_safe_table_definition(array('type' => "InnoDB; DROP TABLE colors", 'columns' => array(array('name' => 'id', 'type' => 'int', 'NULL' => false)))));
private_advisory_assert_true('safe prefixed index column is formatted', db_format_index_create(array('name(10)')) === '`name`(10)');
private_advisory_assert_false('injected index column is rejected', db_format_index_create(array('name); DROP TABLE colors; --')));
private_advisory_assert_false('missing column type is rejected', db_is_safe_column_definition(array('name' => 'missing_type')));

$ddl_table = 'private_advisory_ddl_tmp';
db_execute("DROP TABLE IF EXISTS `$ddl_table`", false);
private_advisory_assert_true('safe db_table_create executes', db_table_create($ddl_table, array(
	'type' => 'InnoDB',
	'columns' => array(
		array('name' => 'id', 'type' => 'int', 'NULL' => false, 'auto_increment' => true),
		array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false)
	),
	'primary' => array('id')
), false));
private_advisory_assert_true('safe db_add_index executes', db_add_index($ddl_table, 'UNIQUE INDEX', 'idx_name', array('name'), false));
private_advisory_assert_true('existing db_add_index replacement uses safe drop', db_add_index($ddl_table, 'UNIQUE INDEX', 'idx_name', array('name'), false));
private_advisory_assert_false('db_add_index rejects injected index column', db_add_index($ddl_table, 'INDEX', 'idx_bad', array('name); DROP TABLE colors; --'), false));

$table_definition = array(
	'type'    => 'InnoDB',
	'charset' => 'utf8mb4',
	'collate' => 'utf8mb4_unicode_ci',
	'comment' => 'compound alter test',
	'columns' => array(
		array('name' => 'id', 'type' => 'int', 'NULL' => false, 'auto_increment' => true),
		array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => false),
		array('name' => 'enabled', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'name')
	),
	'primary' => array('id'),
	'keys'    => array(
		array('name' => 'idx_name', 'columns' => array('name')),
		array('name' => 'idx_enabled', 'columns' => array('enabled'))
	)
);

$table_updated = db_update_table($ddl_table, $table_definition, true, false);

private_advisory_assert_true('db_update_table executes a compound alter' . ($table_updated ? '' : ': ' . db_error()), $table_updated);

$name_column    = db_fetch_row("SHOW COLUMNS FROM `$ddl_table` LIKE 'name'", false);
$enabled_column = db_fetch_row("SHOW COLUMNS FROM `$ddl_table` LIKE 'enabled'", false);
private_advisory_assert_true('compound alter changes and adds columns',
	isset($name_column['Type'], $enabled_column['Type']) && $name_column['Type'] === 'varchar(128)' && $enabled_column['Type'] === 'tinyint(4)');

$collate_only_definition            = $table_definition;
$collate_only_definition['collate'] = 'utf8mb4_bin';
unset($collate_only_definition['charset']);
$collate_only_updated = db_update_table($ddl_table, $collate_only_definition, true, false);

$table_info = db_fetch_row('SELECT TABLE_COMMENT, TABLE_COLLATION
	FROM information_schema.TABLES
	WHERE TABLE_SCHEMA = SCHEMA()
	AND TABLE_NAME = ' . db_qstr($ddl_table), false);
private_advisory_assert_true('compound alter applies indexes and table options',
	$collate_only_updated && db_index_exists($ddl_table, 'idx_enabled', false)
	&& ($table_info['TABLE_COMMENT'] ?? '') === 'compound alter test'
	&& ($table_info['TABLE_COLLATION'] ?? '') === 'utf8mb4_bin');

db_execute("DROP TABLE IF EXISTS `$ddl_table`", false);

$total  = 33;
$passed = $total - $failures;

echo PHP_EOL . "Tests: {$total}, Passed: {$passed}, Failed: {$failures}" . PHP_EOL;

exit($failures > 0 ? 1 : 0);
