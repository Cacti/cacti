<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
	db_install_add_column('user_domains', array('name' => 'tls_verify', 'type' => 'int', 'null' => false, 'defalut' => '0'));

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
}

function ldap_convert_1_3_0() {
	$ldap_fields = [
		'user_template'          => 'user_id',
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
	];

	$ldap_domain = [
		'domain_name' => 'Default Domain ' . date('Y-m-d'),
		'type' => 1,
		'enabled' => true,
	];

	foreach ($ldap_fields as $old => $new) {
		$ldap_domain[$new] = read_config_option($old);
	}

	$ldap_domain_id  = sql_save($ldap_domain, 'user_domains', 'domain_id');
	if (read_config_option('auth_method') == '3') {
		set_config_option('auth_method', '4');
	}
	set_config_option('install_ldap_builtin', true);
}