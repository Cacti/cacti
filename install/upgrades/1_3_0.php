<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
	db_install_add_column('poller', array('name' => 'log_level', 'type' => 'int', 'null' => false, 'default' => '-1'));
	db_install_add_column('host', array('name' => 'created', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'));
	db_install_add_column('sites', array('name' => 'disabled', 'type' => 'char(2)', 'null' => false, 'default' => '', 'after' => 'name'));
	db_install_add_column('user_domains_ldap', array('name' => 'tls_certificate', 'type' => 'tinyint(3)', 'unsigned' => true, 'null' => false, 'default' => '3'));
	db_install_add_column('graph_templates_graph', array('name' => 't_left_axis_format', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 'left_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_left_axis_format'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_left_axis_format', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'left_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_left_axis_format'));

	//Not sure why we were adding this...
	//db_install_add_column('user_domains', array('name' => 'tls_verify', 'type' => 'int', 'null' => false, 'default' => '0'));

	db_install_execute('UPDATE host h
		LEFT JOIN sites s
		ON s.id = h.site_id
		SET status = 0
		WHERE IFNULL(h.disabled,"") = "on"
		OR IFNULL(s.disabled, "") = "on"
	');

	db_install_execute("CREATE TABLE IF NOT EXISTS poller_time_stats (
		id bigint(20) unsigned NOT NULL auto_increment,
		poller_id int(10) unsigned NOT NULL default '1',
		total_time double default NULL,
		`time` timestamp NOT NULL default '0000-00-00 00:00:00',
		PRIMARY KEY (id))
		ENGINE=InnoDB ROW_FORMAT=Dynamic;");

	$ldap_converted = read_config_option('install_ldap_builtin');

	if (!$ldap_converted) {
		ldap_convert_1_3_0();
	}

	if (!db_column_exists('data_source_stats_daily', 'cf')) {
		upgrade_dsstats();
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS host_value_cache (
		host_id mediumint(8) unsigned NOT NULL default '0',
		dimension varchar(40) NOT NULL default '',
		value varchar(8192) NOT NULL default '',
  		time_to_live int(11) NOT NULL default '-1',
  		last_updated TIMESTAMP default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (host_id, dimension))
		ENGINE=InnoDB
		ROW_FORMAT=Dynamic");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_command_cache` (
		`local_data_id` int(10) unsigned NOT NULL DEFAULT 0,
		`stats_command` BLOB NOT NULL DEFAULT '',
		PRIMARY KEY (`local_data_id`))
		ENGINE=InnoDB
		ROW_FORMAT=DYNAMIC
		COMMENT='Holds the RRDfile Stats Commands'");
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
		MODIFY COLUMN search_filter varchar(128) NOT NULL default "",
		MODIFY COLUMN specific_dn varchar(128) NOT NULL default "",
		MODIFY COLUMN specific_password varchar(128) NOT NULL default ""');

	$ldap_server = read_config_option('ldap_server');

	if (!empty($ldap_server)) {
		$domain_id = db_fetch_cell('SELECT domain_id FROM user_domains WHERE domain_name = \'LDAP\'');

		if (!$domain_id) {
			cacti_log('NOTE: Creating new LDAP domain', true, 'INSTALL');
			db_install_execute('INSERT INTO user_domains (domain_name, type, enabled) VALUES (\'LDAP\', 1, \'on\')');
			$domain_id = db_fetch_cell('SELECT domain_id FROM user_domains WHERE domain_name = \'LDAP\'');
		}

		if ($domain_id) {
			$ldap_id = db_fetch_cell_prepared('SELECT domain_id FROM user_domains_ldap WHERE domain_id = ?', array($domain_id));

			if ($ldap_id != $domain_id) {
				$ldap_settings = array( 'domain_id' => $domain_id );

				foreach ($ldap_fields as $old => $new) {
					$ldap_settings[$new] = read_config_option($old);
				}

				$ldap_sql = 'INSERT INTO user_domains_ldap (' . implode(', ', array_keys($ldap_settings)) . ') VALUES (' . implode(', ', explode(' ', trim(str_repeat('? ', count($ldap_settings))))) . ')';

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

	foreach ($tables as $table) {
		if (!db_column_exists($table, 'cf')) {
			$sql = "ALTER TABLE $table
				ADD COLUMN cf TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER rrd_name";
			$suffix = ', DROP PRIMARY KEY,
				ADD PRIMARY KEY(local_data_id, rrd_name, cf)';
		} else {
			$sql = "ALTER TABLE $table ";

			$suffix = '';
		}

		$i = 0;

		foreach ($columns as $index => $column) {
			$type = 'DOUBLE';

			if (!db_column_exists($table, $column)) {
				$sql .= ", ADD COLUMN $column $type";

				$i++;
			}
		}

		cacti_log("$sql $suffix");
		db_install_execute("$sql $suffix");
	}

	if (!db_column_exists('data_source_stats_hourly', 'cf')) {
		db_install_execute('ALTER TABLE data_source_stats_hourly
			ADD column cf tinyint(3) unsigned not null default "0" AFTER rrd_name,
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (local_data_id, rrd_name, cf)');
	}

	db_install_execute('ALTER TABLE data_source_stats_hourly_cache ENGINE=InnoDB ROW_FORMAT=Dynamic');
}
