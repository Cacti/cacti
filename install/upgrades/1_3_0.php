<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_3_0() {
	db_install_change_column('version', array('name' => 'cacti', 'type' => 'char(30)', 'null' => false, 'default' => ''));

	db_install_add_column('user_auth', array('name' => 'tfa_enabled', 'type' => 'char(3)', 'null' => false, 'default' => ''));
	db_install_add_column('user_auth', array('name' => 'tfa_secret', 'type' => 'char(50)', 'null' => false, 'default' => ''));

	db_install_add_column('poller', array('name' => 'log_level', 'type' => 'int', 'null' => false, 'default' => '-1', 'after' => 'status'));
	db_install_add_column('poller', array('name' => 'dbsslkey', 'type' => 'varchar(255)', 'after' => 'dbssl'));
	db_install_add_column('poller', array('name' => 'dbsslcert', 'type' => 'varchar(255)', 'after' => 'dbsslkey'));
	db_install_add_column('poller', array('name' => 'dbsslca', 'type' => 'varchar(255)', 'after' => 'dbsslcert'));
	db_install_add_column('poller', array('name' => 'dbsslcapath', 'type' => 'varchar(255)', 'after' => 'dbsslca'));
	db_install_add_column('poller', array('name' => 'dbsslverifyservercert', 'type' => 'char(3)', 'after' => 'dbsslcapath', 'default' => 'on'));

	db_install_add_column('host', array('name' => 'created', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'));
	db_install_add_column('host', array('name' => 'snmp_options', 'type' => 'tinyint(3)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'external_id'));
	db_install_add_column('host', array('name' => 'status_options_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00', 'after' => 'status_rec_date'));

	db_install_add_column('host', array('name' => 'snmp_retries', 'type' => 'tinyint(3) unsigned', 'NULL' => false, 'default' => '3', 'after' => 'snmp_timeout'));
	db_install_add_column('poller_item', array('name' => 'snmp_retries', 'type' => 'tinyint(3) unsigned', 'NULL' => false, 'default' => '3', 'after' => 'snmp_timeout'));

	db_execute_prepared('UPDATE host SET snmp_retries = ?', array(read_config_option('snmp_retries')));
	db_execute_prepared('UPDATE poller_item SET snmp_retries = ?', array(read_config_option('snmp_retries')));

	db_install_add_column('graph_templates_item', array('name' => 'legend', 'type' => 'varchar(30)', 'default' => '', 'after' => 'text_format'));
	db_install_add_column('graph_templates_item', array('name' => 'color2_id', 'type' => 'mediumint(8)', 'unsigned' => true, 'default' => '0', 'after' => 'alpha'));
	db_install_add_column('graph_templates_item', array('name' => 'alpha2', 'type' => 'char(2)', 'default' => 'FF', 'after' => 'color2_id'));
	db_install_add_column('graph_templates_item', array('name' => 'gradheight', 'type' => 'tinyint(4)', 'default' => '50', 'after' => 'alpha2'));

	db_install_add_column('sites', array('name' => 'disabled', 'type' => 'char(2)', 'null' => false, 'default' => '', 'after' => 'name'));

	db_install_add_column('user_domains_ldap', array('name' => 'tls_certificate', 'type' => 'tinyint(3)', 'unsigned' => true, 'null' => false, 'default' => '3'));

	db_install_add_column('graph_templates_graph', array('name' => 't_left_axis_format', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 'left_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_left_axis_format'));

	db_install_add_column('graph_templates', array('name' => 'class', 'type' => 'char(40)', 'default' => '', 'NULL' => true, 'after' => 'name'));
	db_install_add_column('graph_templates', array('name' => 'version', 'type' => 'char(10)', 'default' => '', 'NULL' => true, 'after' => 'class'));

	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_left_axis_format', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'left_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_left_axis_format'));

	db_install_add_column('plugin_config', array('name' => 'last_updated', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP', 'after' => 'version'));

	db_install_execute('UPDATE plugin_config SET last_updated = NOW() WHERE status IN (1,2,3,4) AND last_updated = NULL');

	db_install_execute("UPDATE graph_templates SET class='unspecified' WHERE class = ''");
	db_install_execute("UPDATE graph_templates SET version = '" . CACTI_VERSION . "' WHERE version = ''");

	db_install_add_column('data_input_data', array('name' => 'data_template_id', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'data_template_data_id'));
	db_install_add_column('data_input_data', array('name' => 'local_data_id', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'data_template_id'));
	db_install_add_column('data_input_data', array('name' => 'host_id', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'local_data_id'));

	db_add_index('data_input_data', 'INDEX', 'data_template_id', array('data_template_id'));
	db_add_index('data_input_data', 'INDEX', 'local_data_id', array('local_data_id'));
	db_add_index('data_input_data', 'INDEX', 'host_id', array('host_id'));

	db_add_index('poller_output_boost', 'INDEX', 'time', array('time'));

	db_install_execute("UPDATE data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON did.data_template_data_id = dtd.id
		SET did.local_data_id = dtd.local_data_id, did.data_template_id = dtd.data_template_id");

	db_install_execute("UPDATE data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON did.data_template_data_id = dtd.id
		INNER JOIN data_local AS dl ON dl.id = dtd.local_data_id
		SET did.host_id = dl.host_id");

	/* remove all the legacy debounce entries */
	db_install_execute('DELETE FROM settings WHERE name LIKE "debounce_%" AND value > 0');

	$data = array();
	$data['columns'][] = array('name' => 'plugin', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'author', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'webpage', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tag_name', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'published_at', 'type' => 'timestamp', 'NULL' => true);
	$data['columns'][] = array('name' => 'compat', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'requires', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'body', 'type' => 'blob', 'NULL' => true);
	$data['columns'][] = array('name' => 'info', 'type' => 'blob', 'NULL' => true);
	$data['columns'][] = array('name' => 'readme', 'type' => 'blob', 'NULL' => true);
	$data['columns'][] = array('name' => 'changelog', 'type' => 'blob', 'NULL' => true);
	$data['columns'][] = array('name' => 'archive', 'type' => 'longblob', 'NULL' => true);
	$data['columns'][] = array('name' => 'last_updated', 'type' => 'timestamp', 'NULL' => true, 'default' => 'CURRENT_TIMESTAMP');
	$data['primary'] = 'plugin`,`tag_name';
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('plugin_available', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'mediumint(8)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'plugin', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'author', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'webpage', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'user_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'version', 'type' => 'varchar(10)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'requires', 'type' => 'varchar(128)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'compat', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dir_md5sum', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'last_updated', 'type' => 'timestamp', 'NULL' => true);
	$data['columns'][] = array('name' => 'archive_note', 'type' => 'varchar(256)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'archive', 'type' => 'longblob', 'NULL' => true);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'directory', 'columns' => array('plugin'));
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('plugin_archive', $data);

	//Not sure why we were adding this...
	//db_install_add_column('user_domains', array('name' => 'tls_verify', 'type' => 'int', 'null' => false, 'default' => '0'));

	db_install_execute('UPDATE host AS h
		LEFT JOIN sites AS s
		ON s.id = h.site_id
		SET status = 0
		WHERE IFNULL(h.disabled,"") = "on"
		OR IFNULL(s.disabled, "") = "on"');

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'poller_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'total_time', 'type' => 'double', 'NULL' => true);
	$data['columns'][] = array('name' => 'time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('poller_time_stats', $data);

	$ldap_converted = read_config_option('install_ldap_builtin');

	if (!$ldap_converted) {
		ldap_convert_1_3_0();
	}

	upgrade_dsstats();

	$data = array();
	$data['columns'][] = array('name' => 'host_id', 'unsigned' => true, 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'dimension', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'value', 'type' => 'varchar(8192)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'time_to_live', 'type' => 'int(11)', 'NULL' => false, 'default' => '-1');
	$data['columns'][] = array('name' => 'last_updated', 'type' => 'timestamp', 'NULL' => true, 'default' => 'CURRENT_TIMESTAMP');
	$data['primary'] = 'host_id`,`dimension';
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('host_value_cache', $data);

	$data = array();
	$data['columns'][] = array('name' => 'local_data_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'stats_command', 'type' => 'blob', 'NULL' => false, 'default' => '');
	$data['primary'] = 'local_data_id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Holds the RRDfile Stats Commands';
	$data['row_format'] = 'Dynamic';
	db_update_table('data_source_stats_command_cache', $data);

	install_unlink('aggregate_items.php');
	install_unlink('color_template_items.php');
	install_unlink('graphs_items.php');
	install_unlink('graph_templates_items.php');
	install_unlink('graph_templates_inputs.php');

	/* create new automation template rules table */
	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'template_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'rule_type', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'rule_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sequence', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'exit_rules', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'unique_key', 'unique' => true, 'columns' => array('template_id','rule_type','rule_id'));
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Holds mappings of Automation Templates to Rules';
	$data['row_format'] = 'Dynamic';
	db_update_table('automation_templates_rules', $data);

	/* add automation hashes */
	$tables = array(
		'automation_graph_rule_items',
		'automation_graph_rules',
		'automation_match_rule_items',
		'automation_networks',
		'automation_templates',
		'automation_snmp',
		'automation_snmp_items',
		'automation_tree_rules',
		'automation_tree_rule_items'
	);

	foreach($tables as $table) {
		if (!db_column_exists($table, 'hash')) {
			db_install_execute("ALTER TABLE $table
				ADD COLUMN hash VARCHAR(32) NOT NULL DEFAULT '' AFTER id");

			$rows = db_fetch_assoc("SELECT id
				FROM $table
				WHERE hash = ''");

			if (cacti_sizeof($rows)) {
				foreach($rows as $row) {
					$hash = generate_hash();

					db_execute_prepared("UPDATE $table
						SET hash = ?
						WHERE id = ?",
						array($hash, $row['id']));
				}
			}
		}
	}

	if (!db_column_exists('automation_devices', 'host_id')) {
		db_install_execute("ALTER TABLE automation_devices ADD COLUMN host_id INT UNSIGNED NOT NULL DEFAULT '0' AFTER network_id");
	}

	if (!db_column_exists('automation_templates', 'description_pattern')) {
		db_install_execute("ALTER TABLE automation_templates ADD COLUMN description_pattern varchar(128) DEFAULT '' AFTER sysOid");
	}

	if (!db_column_exists('automation_templates', 'populate_location')) {
		db_install_execute("ALTER TABLE automation_templates ADD COLUMN populate_location char(2) DEFAULT '' AFTER description_pattern");
	}

	if (!db_column_exists('automation_networks', 'ignore_ips')) {
		db_install_execute("ALTER TABLE automation_networks ADD COLUMN ignore_ips varchar(1024) NOT NULL DEFAULT '' AFTER subnet_range");
	}

	if (!db_column_exists('poller_output_boost', 'last_updated')) {
		db_install_execute('ALTER TABLE poller_output_boost
			ADD COLUMN last_updated timestamp NOT NULL default CURRENT_TIMESTAMP,
			ADD INDEX last_updated(last_updated)');
	}

	db_install_execute("ALTER TABLE `settings` MODIFY `name` varchar(75) not null default ''");
	db_install_execute("ALTER TABLE `settings_user` MODIFY `name` varchar(75) not null default ''");

	$tables = array(
		'aggregate_graph_templates' => array(
			'after' => 'user_id',
			'columns' => 'graphs',
		),
		'cdef' => array(
			'after' => 'name',
			'columns' => 'graphs, templates',
		),
		'colors' => array(
			'after' => 'read_only',
			'columns' => 'graphs, templates',
		),
		'color_templates' => array(
			'after' => 'name',
			'columns' => 'graphs, templates',
		),
		'host' => array(
			'after' => 'disabled',
			'columns' => 'graphs, data_sources',
		),
		'data_input' => array(
			'after' => 'type_id',
			'columns' => 'data_sources, templates',
		),
		'data_source_profiles' => array(
			'after' => 'default',
			'columns' => 'data_sources, templates',
		),
		'data_template' => array(
			'after' => 'name',
			'columns' => 'data_sources',
		),
		'graph_templates' => array(
			'after' => 'test_source',
			'columns' => 'graphs',
		),
		'graph_templates_gprint' => array(
			'after' => 'gprint_text',
			'columns' => 'graphs, templates',
		),
		'host_template' => array(
			'after' => 'class',
			'columns' => 'devices',
		),
		'sites' => array(
			'after' => 'notes',
			'columns' => 'devices',
		),
		'poller' => array(
			'after' => 'sync_interval',
			'columns' => 'devices',
		),
		'snmp_query' => array(
			'after' => 'data_input_id',
			'columns' => 'graphs, templates',
		),
		'vdef' => array(
			'after' => 'name',
			'columns' => 'graphs, templates',
		),
	);

	foreach($tables as $table_name => $attribs) {
		$columns = explode(',', $attribs['columns']);
		$after   = $attribs['after'];
		$alter   = '';
		$count   = 0;

		foreach($columns as $column) {
			$column = trim($column);

			if (!db_column_exists($table_name, $column)) {
				if ($alter == '') {
					$alter .= 'ALTER TABLE `' . $table_name . '`';
				}

				$alter .= ($count > 0 ? ',':'') . " ADD COLUMN `$column` int(10) UNSIGNED NOT NULL default '0' AFTER `$after`";

				$count++;

				$after = $column;
			}
		}

		if ($alter != '') {
			db_execute($alter);
		}
	}

	object_cache_update_device_totals();
	object_cache_update_data_source_totals();
	object_cache_update_graph_totals();
	object_cache_update_aggregate_totals();

	/* remove legacy files from old cacti releases */
	prune_deprecated_files();

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'enabled', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'default', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'repo_type', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'repo_location', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'repo_branch', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'repo_api_key', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'location_branch', 'columns' => array('repo_location','repo_branch'));
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Holds Repository Locations that hold Packages';
	$data['row_format'] = 'Dynamic';
	db_update_table('package_repositories', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'md5sum', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'author', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'homepage', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email_address', 'type' => 'varchar(60)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'public_key', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'md5sum', 'columns' => array('md5sum'), 'unique' => true);
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Hold Trusted Package Public Keys';
	$data['row_format'] = 'Dynamic';
	db_update_table('package_public_keys', $data);

	$repos[] = array(1,'Local Packages','on','on',1,'/var/www/html/cacti/install/templates','','');
	$repos[] = array(2,'TheWitness Percona','on','',0,'https://github.com/TheWitness/percona_packages','main','');

	$repos = db_fetch_cell('SELECT COUNT(*) FROM package_repositories');

	/* example repositories */
	if ($repos == 0) {
		foreach($repos as $r) {
			db_execute_prepared('INSERT INTO package_repositories
				(id, name, enabled, `default`, repo_type, repo_location, repo_branch, repo_api_key)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $r);
		}
	}

	/* add package meta information to the host_template table */
	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'mediumint(8)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'version', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'class', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tags', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'author', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email', 'type' => 'varchar(60)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'homepage', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'copyright', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'installation', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'devices', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'name', 'columns' => array('name'));
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('host_template', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_template_id', 'unsigned' => true, 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'version', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'class', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tags', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'author', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email', 'type' => 'varchar(60)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'homepage', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'copyright', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'installation', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'archive_note', 'type' => 'varchar(256)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'archive_date', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP');
	$data['columns'][] = array('name' => 'archive_md5sum', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'archive', 'type' => 'longblob', 'NULL' => true);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'host_template_id', 'columns' => array('host_template_id'));
	$data['keys'][] = array('name' => 'name', 'columns' => array('name'));
	$data['type'] = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	db_update_table('host_template_archive', $data);

	/* provide the primary admin access to packages */
	$admin = read_config_option('admin_user');

	if ($admin > 0) {
		db_execute_prepared('REPLACE INTO user_auth_realm
			(realm_id, user_id)
			VALUES (29, ?)', array($admin));
	}

	$data = array();
	$data['columns'][] = array('name' => 'user_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expiry', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'user_id`,`expiry';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Table that Contains User Password Reset Hashes';
	$data['row_format'] = 'Dynamic';
	db_update_table('user_auth_reset_hashes', $data);

	db_execute('UPDATE settings
		SET name = "business_hours_hide_weekends"
		WHERE name = "business_hours_hideWeekends"');
}

function ldap_convert_1_3_0() {
	$ldap_fields = array(
		'ldap_server'            => 'server',
		'ldap_port'              => 'port',
		'ldap_port_ssl'          => 'port_ssl',
		'ldap_version'           => 'proto_version',
		'ldap_encryption'        => 'encryption',
		'ldap_tls_certificate'   => 'tls_certificate',
		'ldap_referrals'         => 'referrals',
		'ldap_mode'              => 'mode',
		'ldap_dn'                => 'dn',
		'ldap_group_require'     => 'group_require',
		'ldap_group_dn'          => 'group_dn',
		'ldap_group_attrib'      => 'group_attrib',
		'ldap_group_member_type' => 'group_member_type',
		'ldap_search_base'       => 'search_base',
		'ldap_search_filter'     => 'search_filter',
		'ldap_specific_dn'       => 'specific_dn',
		'ldap_specific_password' => 'specific_password',
		'cn_full_name'           => 'cn_full_name',
		'cn_email'               => 'cn_email',
	);

	db_execute('ALTER TABLE user_domains_ldap
		MODIFY COLUMN dn varchar(128) NOT NULL default "",
		MODIFY COLUMN group_require char(2) NOT NULL default "",
		MODIFY COLUMN group_dn varchar(128) NOT NULL default "",
		MODIFY COLUMN group_attrib varchar(128) NOT NULL default "",
		MODIFY COLUMN search_base varchar(128) NOT NULL default "",
		MODIFY COLUMN search_filter varchar(512) NOT NULL default "",
		MODIFY COLUMN specific_dn varchar(128) NOT NULL default "",
		MODIFY COLUMN specific_password varchar(128) NOT NULL default ""');

	$ldap_server = read_config_option('ldap_server');

	if (!empty($ldap_server)) {
		$domain = db_fetch_row('SELECT * FROM user_domains WHERE domain_name = \'LDAP\'');

		if (!cacti_sizeof($domain)) {
			cacti_log('NOTE: Creating new LDAP domain', true, 'INSTALL');

			db_install_execute('INSERT INTO user_domains (domain_name, type, enabled) VALUES (\'LDAP\', 1, \'on\')');

			$domain = db_fetch_row('SELECT * FROM user_domains WHERE domain_name = \'LDAP\'');
		}

		if (cacti_sizeof($domain)) {
			$domain_id = $domain['domain_id'];

			/* Reset LDAP users to the new LDAP domain */
			db_execute_prepared('UPDATE user_auth
				SET realm = ? + 1000
				WHERE realm = 3',
				array($domain['domain_id']));

			$ldap_settings = array();

			$ldap = db_fetch_row_prepared('SELECT *
				FROM user_domains_ldap
				WHERE domain_id = ?',
				array($domain['domain_id']));

			if (!cacti_sizeof($ldap)) {
				$columns = db_get_table_column_types('user_domains_ldap');

				$ldap_settings['domain_id'] = $domain['domain_id'];

				foreach ($columns as $column => $attribs) {
					if ($column != 'domain_id' && $column != 'proto_version') {
						$setting = read_config_option('ldap_' . $column);

						if ($setting != '') {
							$ldap_settings[$column] = $setting;
						}
					} elseif ($column == 'proto_version') {
						$setting = read_config_option('ldap_version');

						if ($setting != '') {
							$ldap_settings[$column] = $setting;
						} else {
							$ldap_settings[$column] = '3';
						}
					}
				}

				$ldap_sql = 'INSERT INTO user_domains_ldap (' . implode(', ', array_keys($ldap_settings)) . ')
					VALUES (' . implode(', ', explode(' ', trim(str_repeat('? ', count($ldap_settings))))) . ')';

				db_install_execute($ldap_sql, array_values($ldap_settings));
			}
		}

		if (read_config_option('auth_method') == '3') {
			set_config_option('auth_method', '4');
		}

		set_config_option('install_ldap_builtin', $domain_id);
	}
}

function upgrade_dsstats() {
	$columns = array(
		'p95n',
		'p90n',
		'p75n',
		'p50n',
		'p25n',
		'sum',
		'stddev',
		'lslslope',
		'lslint',
		'lslcorrel'
	);

	$tables = array(
		'data_source_stats_daily',
		'data_source_stats_weekly',
		'data_source_stats_monthly',
		'data_source_stats_yearly'
	);

	$version = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "version"');

	if (cacti_sizeof($version)) {
		if (stripos($version['Value'], 'MariaDB') !== false) {
			$db = 'mariadb';
		} else {
			$db = 'mysql';
		}
	}

	foreach ($tables as $table) {
		if (!db_column_exists($table, 'cf')) {
			$sql = "ALTER TABLE $table
				ADD COLUMN cf TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER rrd_name";
		} else {
			$sql = "ALTER TABLE $table ";
		}

		$i = 0;

		foreach ($columns as $index => $column) {
			$type = 'DOUBLE';

			if (!db_column_exists($table, $column)) {
				$sql .= ", ADD COLUMN $column $type";

				$i++;
			}
		}

		$suffix = ($i > 0 ? ',':'') . ' DROP PRIMARY KEY,
			ADD PRIMARY KEY(local_data_id, rrd_name, cf)';

		if ($db == 'mariadb') {
			$suffix .= ", ENGINE=Aria ROW_FORMAT=Page";
		}

		db_install_execute("$sql $suffix");

		/* if re-upgrading, move existing partitions to aria */
		if ($db == 'mariadb') {
			$tables = db_fetch_assoc("SELECT *
				FROM information_schema.TABLES
				WHERE TABLE_NAME LIKE '{$table}_v%'
				AND TABLE_SCHEMA=SCHEMA()");

			if (cacti_sizeof($tables)) {
				foreach($tables as $t) {
					db_install_execute("ALTER TABLE {$t['TABLE_NAME']} ENGINE=Aria ROW_FORMAT=Page");
				}
			}
		}
	}

	if (!db_column_exists('data_source_stats_hourly', 'cf')) {
		db_install_execute('ALTER TABLE data_source_stats_hourly
			ADD column cf tinyint(3) unsigned not null default "0" AFTER rrd_name,
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (local_data_id, rrd_name, cf)');
	}

	db_install_execute('ALTER TABLE data_source_stats_hourly_cache ENGINE=InnoDB ROW_FORMAT=Dynamic');
}
