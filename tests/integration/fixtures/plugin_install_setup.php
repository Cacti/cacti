<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software under the GNU General Public License v2  |
 | or later.                                                             |
 +-------------------------------------------------------------------------+
*/

// The integration runner replaces the controlled fixture name and outcome.
function plugin___PLUGIN___version() {
	return ['longname' => 'Install fixture', 'author' => 'Cacti tests', 'version' => '1.0'];
}

function plugin___PLUGIN___install() {
	$plugin = '__PLUGIN__';
	if (!db_fetch_cell_prepared('SELECT id FROM plugin_config WHERE directory = ?', [$plugin])) {
		throw new RuntimeException('Plugin metadata must precede the install hook.');
	}

	api_plugin_register_hook($plugin, 'page_head', 'fixture_hook', 'setup.php', true);
	api_plugin_register_hook($plugin, 'config_arrays', 'fixture_hook', 'setup.php', true);
	api_plugin_register_realm($plugin, 'index.php', 'Install fixture', false);
	api_plugin_db_table_create($plugin, $plugin . '_data', [
		'columns' => [['name' => 'event', 'type' => 'varchar(40)', 'NULL' => false]],
		'keys' => [], 'type' => 'InnoDB'
	]);

	db_execute('INSERT INTO `' . $plugin . '_data` VALUES (\'install\')');
	__OUTCOME__;
}

function plugin___PLUGIN___check_config() {
	db_execute('INSERT INTO `__PLUGIN___data` VALUES (\'check_config\')');
	return __READY__;
}

function plugin___PLUGIN___uninstall() {
}
