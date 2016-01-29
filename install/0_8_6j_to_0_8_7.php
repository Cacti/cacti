<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function upgrade_to_0_8_7() {
	/* add slope mode as an option */
	db_install_execute("0.8.7", "ALTER TABLE `graph_templates_graph` ADD COLUMN `t_slope_mode` CHAR(2) DEFAULT 0 AFTER `vertical_label`, ADD COLUMN `slope_mode` CHAR(2) DEFAULT 'on' AFTER `t_slope_mode`;");

	/* change the width of the last error field */
	db_install_execute("0.8.7", "ALTER TABLE `host` MODIFY COLUMN `status_last_error` VARCHAR(255);");

	/* fix rrd min and max values for data templates */
	db_install_execute("0.8.7", "ALTER TABLE `data_template_rrd` MODIFY COLUMN `rrd_maximum` VARCHAR(20) NOT NULL DEFAULT 0, MODIFY COLUMN `rrd_minimum` VARCHAR(20) NOT NULL DEFAULT 0");

	/* speed up the poller */
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD INDEX `disabled`(`disabled`)");
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD INDEX `rrd_next_step`(`rrd_next_step`)");

	/* speed up the UI */
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD INDEX `action`(`action`)");
	db_install_execute("0.8.7", "ALTER TABLE `user_auth` ADD INDEX `username`(`username`)");
	db_install_execute("0.8.7", "ALTER TABLE `user_auth` ADD INDEX `realm`(`realm`)");
	db_install_execute("0.8.7", "ALTER TABLE `user_log` ADD INDEX `username`(`username`)");
	db_install_execute("0.8.7", "ALTER TABLE `data_input` ADD INDEX `name`(`name`)");

	/* Add enable/disable to users */
	db_install_execute("0.8.7", "ALTER TABLE `user_auth` ADD COLUMN `enabled` CHAR(2) DEFAULT 'on'");
	db_install_execute("0.8.7", "ALTER TABLE `user_auth` ADD INDEX `enabled`(`enabled`)");

	/* add additional fields to the host table */
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `availability_method` SMALLINT(5) UNSIGNED NOT NULL default '2' AFTER `snmp_timeout`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `ping_method` SMALLINT(5) UNSIGNED default '0' AFTER `availability_method`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `ping_port` INT(12) UNSIGNED default '0' AFTER `ping_method`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `ping_timeout` INT(12) UNSIGNED default '500' AFTER `ping_port`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `ping_retries` INT(12) UNSIGNED default '2' AFTER `ping_timeout`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `max_oids` INT(12) UNSIGNED default '10' AFTER `ping_retries`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `notes` TEXT AFTER `hostname`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `snmp_auth_protocol` CHAR(5) default '' AFTER `snmp_password`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `snmp_priv_protocol` CHAR(6) default '' AFTER `snmp_priv_passphrase`");
	db_install_execute("0.8.7", "ALTER TABLE `host` ADD COLUMN `snmp_context` VARCHAR(64) default '' AFTER `snmp_priv_protocol`");

	/* additional poller items fields required */
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD COLUMN `snmp_auth_protocol` CHAR(5) default '' AFTER `snmp_password`");
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD COLUMN `snmp_priv_protocol` CHAR(6) default '' AFTER `snmp_priv_passphrase`");
	db_install_execute("0.8.7", "ALTER TABLE `poller_item` ADD COLUMN `snmp_context` VARCHAR(64) default '' AFTER `snmp_priv_protocol`");

	/* Convert to new authentication system */
        $global_auth = "on";
	$global_auth_db = db_fetch_row("SELECT value FROM settings WHERE name = 'global_auth'");
	if (sizeof($global_auth_db)) {
		$global_auth = $global_auth_db["value"];
	}
	$ldap_enabled = "";
	$ldap_enabled_db = db_fetch_row("SELECT value FROM settings WHERE name = 'ldap_enabled'");
	if (sizeof($ldap_enabled_db)) {
		$ldap_enabled = $ldap_enabled_db["value"];
	}
	if ($global_auth == "on") {
		if ($ldap_enabled == "on") {
			db_install_execute("0.8.7", "INSERT INTO settings VALUES ('auth_method','3')");
		}else{
			db_install_execute("0.8.7", "INSERT INTO settings VALUES ('auth_method','1')");
		}
	}else{
		db_install_execute("0.8.7", "INSERT INTO settings VALUES ('auth_method','0')");
	}
	db_install_execute("0.8.7", "UPDATE `settings` SET value = '0' WHERE name = 'guest_user' and value = ''");
	db_install_execute("0.8.7", "UPDATE `settings` SET name = 'user_template' WHERE name = 'ldap_template'");
	db_install_execute("0.8.7", "UPDATE `settings` SET value = '0' WHERE name = 'user_template' and value = ''");
	db_install_execute("0.8.7", "DELETE FROM `settings` WHERE name = 'global_auth'");
	db_install_execute("0.8.7", "DELETE FROM `settings` WHERE name = 'ldap_enabled'");

	/* host settings for availability */
	$ping_method         = read_config_option("ping_method");
	$ping_retries        = read_config_option("ping_retries");
	$ping_timeout        = read_config_option("ping_timeout");
	$availability_method = read_config_option("availability_method");
	$hosts               = db_fetch_assoc("SELECT id, snmp_community, snmp_version FROM host");

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			if (strlen($host["snmp_community"] != 0)) {
				if ($host["snmp_version"] == "3") {
					if ($availability_method == AVAIL_SNMP) {
						db_install_execute("0.8.7", "UPDATE host SET snmp_priv_protocol='[None]', snmp_auth_protocol='MD5', availability_method=" . AVAIL_SNMP . ", ping_method=" . PING_UDP . ",ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}else if ($availability_method == AVAIL_SNMP_AND_PING) {
						if ($ping_method == PING_ICMP) {
							db_install_execute("0.8.7", "UPDATE host SET snmp_priv_protocol='[None]', availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}else{
							db_install_execute("0.8.7", "UPDATE host SET snmp_priv_protocol='[None]', availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}
					}else{
						if ($ping_method == PING_ICMP) {
							db_install_execute("0.8.7", "UPDATE host SET snmp_priv_protocol='[None]', availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}else{
							db_install_execute("0.8.7", "UPDATE host SET snmp_priv_protocol='[None]', availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}
					}
				}else{
					if ($availability_method == AVAIL_SNMP) {
						db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP . ", ping_method=" . PING_UDP . ",ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}else if ($availability_method == AVAIL_SNMP_AND_PING) {
						if ($ping_method == PING_ICMP) {
							db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}else{
							db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}
					}else{
						if ($ping_method == PING_ICMP) {
							db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}else{
							db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
						}
					}
				}
			}else{
				if ($availability_method == AVAIL_SNMP) {
					db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP . ", ping_method=" . PING_UDP . ", ping_timeout = " . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
				}else if ($availability_method == AVAIL_SNMP_AND_PING) {
					if ($ping_method == PING_ICMP) {
						db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}else{
						db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_SNMP_AND_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}
				}else{
					if ($ping_method == PING_ICMP) {
						db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}else{
						db_install_execute("0.8.7", "UPDATE host SET availability_method=" . AVAIL_PING . ", ping_method=" . $ping_method . ", ping_port=33439, ping_timeout=" . $ping_timeout . ", ping_retries=" . $ping_retries . " WHERE id=" . $host["id"]);
					}
				}
			}
		}
	}

	/* Add SNMPv3 to SNMP Input Methods */
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, '20832ce12f099c8e54140793a091af90',1,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','')");
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, 'c60c9aac1e1b3555ea0620b8bbfd82cb',1,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','')");
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, 'feda162701240101bc74148415ef415a',1,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','')");
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, '2cf7129ad3ff819a7a7ac189bee48ce8',2,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','')");
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, '6b13ac0a0194e171d241d4b06f913158',2,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','')");
	db_install_execute("0.8.7", "INSERT INTO data_input_fields VALUES (DEFAULT, '3a33d4fc65b8329ab2ac46a36da26b72',2,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','')");

	/* Add 1 min rra */
	db_install_execute("0.8.7", "INSERT INTO rra VALUES (DEFAULT,'283ea2bf1634d92ce081ec82a634f513','Hourly (1 Minute Average)',0.5,1,500,14400)");
	$rrd_id = mysql_insert_id();
	db_install_execute("0.8.7", "INSERT INTO `rra_cf` VALUES ($rrd_id,1), ($rrd_id,3)");

	/* rename cactid path to spine path */
	db_install_execute("0.8.7", "UPDATE settings SET name='path_spine' WHERE name='path_cactid'");
}
?>
