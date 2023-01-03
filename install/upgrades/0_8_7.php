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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_7() {

	/* add slope mode as an option */
	db_install_add_column('graph_templates_graph', array('name' => 't_slope_mode', 'type' => 'CHAR(2)', 'default' => '0', 'after' => 'vertical_label'));
	db_install_add_column('graph_templates_graph', array('name' => 'slope_mode', 'type' => 'CHAR(2)', 'default' => 'on', 'after' => 't_slope_mode'));

	/* change the width of the last error field */
	db_install_execute("ALTER TABLE `host` MODIFY COLUMN `status_last_error` VARCHAR(255);");

	/* fix rrd min and max values for data templates */
	db_install_execute("ALTER TABLE `data_template_rrd` MODIFY COLUMN `rrd_maximum` VARCHAR(20) NOT NULL DEFAULT 0, MODIFY COLUMN `rrd_minimum` VARCHAR(20) NOT NULL DEFAULT 0");

	/* speed up the poller */
	db_install_add_key('host', 'index', 'disabled', array('disabled'));
	db_install_add_key('poller_item', 'index', 'rrd_next_step', array('rrd_next_step'));

	/* speed up the UI */
	db_install_add_key('poller_item', 'index', 'action', array('action'));
	db_install_add_key('user_auth', 'index', 'username', array('username'));
	db_install_add_key('user_auth', 'index', 'realm', array('realm'));
	db_install_add_key('user_log', 'index', 'username', array('username'));
	db_install_add_key('data_input', 'index', 'name', array('name'));

	/* Add enable/disable to users */
	db_install_add_column('user_auth', array('name' => 'enabled', 'type' => 'CHAR(2)', 'default' => 'on'));
	db_install_add_key('user_auth', 'index', 'enabled', array('enabled'));

	/* add additional fields to the host table */
	db_install_add_column('host', array('name' => 'availability_method', 'type' => 'SMALLINT(5) UNSIGNED', 'NULL' => false, 'default' => '2', 'after' => 'snmp_timeout'));
	db_install_add_column('host', array('name' => 'ping_method', 'type' => 'SMALLINT(5) UNSIGNED', 'default' => '0', 'after' => 'availability_method'));
	db_install_add_column('host', array('name' => 'ping_port', 'type' => 'INT(12) UNSIGNED', 'default' => '0', 'after' => 'ping_method'));
	db_install_add_column('host', array('name' => 'ping_timeout', 'type' => 'INT(12) UNSIGNED', 'default' => '500', 'after' => 'ping_port'));
	db_install_add_column('host', array('name' => 'ping_retries', 'type' => 'INT(12) UNSIGNED', 'default' => '2', 'after' => 'ping_timeout'));
	db_install_add_column('host', array('name' => 'max_oids', 'type' => 'INT(12) UNSIGNED', 'default' => '10', 'after' => 'ping_retries'));
	db_install_add_column('host', array('name' => 'notes', 'type' => 'TEXT', 'after' => 'hostname'));
	db_install_add_column('host', array('name' => 'snmp_auth_protocol', 'type' => 'CHAR(5)', 'default' => '', 'after' => 'snmp_password'));
	db_install_add_column('host', array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'default' => '', 'after' => 'snmp_auth_protocol'));
	db_install_add_column('host', array('name' => 'snmp_priv_protocol', 'type' => 'CHAR(6)', 'default' => '', 'after' => 'snmp_priv_passphrase'));
	db_install_add_column('host', array('name' => 'snmp_context', 'type' => 'VARCHAR(64)', 'default' => '', 'after' => 'snmp_priv_protocol'));

	/* additional poller items fields required */
	db_install_add_column('poller_item', array('name' => 'snmp_auth_protocol', 'type' => 'CHAR(5)', 'default' => '', 'after' => 'snmp_password'));
	db_install_add_column('poller_item', array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'default' => '', 'after' => 'snmp_auth_protocol'));
	db_install_add_column('poller_item', array('name' => 'snmp_priv_protocol', 'type' => 'CHAR(6)', 'default' => '', 'after' => 'snmp_priv_passphrase'));
	db_install_add_column('poller_item', array('name' => 'snmp_context', 'type' => 'VARCHAR(64)', 'default' => '', 'after' => 'snmp_priv_protocol'));

	/* Convert to new authentication system */
	$global_auth = "on";
	$global_auth_db_results = db_install_fetch_row("SELECT value FROM settings WHERE name = 'global_auth'");
	$global_auth_db         = $global_auth_db_results['data'];

	if (cacti_sizeof($global_auth_db)) {
		$global_auth = $global_auth_db["value"];
	}

	$ldap_enabled = "";
	$ldap_enabled_db_results = db_install_fetch_row("SELECT value FROM settings WHERE name = 'ldap_enabled'");
	$ldap_enabled_db         = $ldap_enabled_db_results['data'];

	if (cacti_sizeof($ldap_enabled_db)) {
		$ldap_enabled = $ldap_enabled_db["value"];
	}

	$auth_method_results = db_install_fetch_cell('SELECT value FROM settings WHERE name = \'auth_method\'');
	if ($auth_method_results['data'] !== false) {
		if ($global_auth == "on") {
			if ($ldap_enabled == "on") {
				db_install_execute("REPLACE INTO settings VALUES ('auth_method','3')");
			} else {
				db_install_execute("REPLACE INTO settings VALUES ('auth_method','1')");
			}
		} else {
			db_install_execute("REPLACE INTO settings VALUES ('auth_method','0')");
		}
	}

	db_install_execute("UPDATE `settings` SET value = '0' WHERE name = 'guest_user' and value = ''");

	db_install_swap_setting('ldap_template', 'user_template');

	db_install_execute("UPDATE `settings` SET value = '0' WHERE name = 'user_template' and value = ''");
	db_install_execute("DELETE FROM `settings` WHERE name = 'global_auth'");
	db_install_execute("DELETE FROM `settings` WHERE name = 'ldap_enabled'");

	/* host settings for availability */
	$ping_port           = 0;
	$ping_method         = read_config_option("ping_method");
	$ping_retries        = read_config_option("ping_retries");
	$ping_timeout        = read_config_option("ping_timeout");
	$availability_method = read_config_option("availability_method");
	$hosts_results       = db_install_fetch_assoc("SELECT id, snmp_community, snmp_version FROM host");
	$hosts               = $hosts_results['data'];

	if (cacti_sizeof($hosts)) {
		foreach($hosts as $host) {
			if (strlen($host["snmp_community"]) != 0) {
				if ($ping_method != PING_ICMP) {
					$ping_port = 33439;
				} else {
					$ping_port = 0;
				}

				$fields = array(
					'snmp_priv_protocol',
					'availability_method',
					'ping_method',
					'ping_timeout',
					'ping_retries',
				);

				$params = array(
					'[None]',
					$availability_method,
					$ping_method,
					$ping_timeout,
					$ping_retries
				);

				if (!empty($ping_port)) {
					$fields['ping_port'] = '?';
					array_push($params, $ping_port);
				}

				array_push($params, $host['id']);

				$sqlFields = '';
				foreach ($fields as $field) {
					$sqlFields .= (empty($sqlFields) ? '' : ', ') . $field . ' => ?';
				}

				db_install_execute("UPDATE host SET " . $sqlFields . " WHERE id=?", $params);
			}
		}
	}

	/* Add SNMPv3 to SNMP Input Methods */
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, '20832ce12f099c8e54140793a091af90',1,'SNMP Authentication Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','')");
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, 'c60c9aac1e1b3555ea0620b8bbfd82cb',1,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','')");
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, 'feda162701240101bc74148415ef415a',1,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','')");
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, '2cf7129ad3ff819a7a7ac189bee48ce8',2,'SNMP Authentication Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','')");
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, '6b13ac0a0194e171d241d4b06f913158',2,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','')");
	db_install_execute("INSERT INTO data_input_fields VALUES (DEFAULT, '3a33d4fc65b8329ab2ac46a36da26b72',2,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','')");

	/* Add 1 min rra */
	if (db_table_exists('rra')) {
		db_install_execute("INSERT INTO rra VALUES (DEFAULT,'283ea2bf1634d92ce081ec82a634f513','Hourly (1 Minute Average)',0.5,1,500,14400)");
		$rrd_id = db_fetch_cell("SELECT id FROM rra WHERE hash='283ea2bf1634d92ce081ec82a634f513'");
		db_install_execute("INSERT INTO `rra_cf` VALUES (?,1), (?,3)", array($rrd_id, $rrd_id));
	}

	/* rename cactid path to spine path */
	db_install_swap_setting('path_cactid', 'path_spine');
}

