<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

function upgrade_to_0_8_6k() {
	/* add slope mode as an option */
	db_install_execute("0.8.6k", "ALTER TABLE `graph_templates_graph` ADD COLUMN `t_slope_mode` CHAR(2) DEFAULT 0 AFTER `vertical_label`, ADD COLUMN `slope_mode` CHAR(2) DEFAULT 'on' AFTER `t_slope_mode`;");

	/* change the width of the last error field */
	db_install_execute("0.8.6k", "ALTER TABLE `host` MODIFY COLUMN `status_last_error` VARCHAR(255);");

	/* fix rrd min and max values for data templates */
	db_install_execute("0.8.6k", "ALTER TABLE `data_template_rrd` MODIFY COLUMN `rrd_maximum` VARCHAR(20) NOT NULL DEFAULT 0, MODIFY COLUMN `rrd_minimum` VARCHAR(20) NOT NULL DEFAULT 0");

	/* speed up the poller */
	db_install_execute("0.8.6k", "ALTER TABLE `host` ADD INDEX `disabled`(`disabled`)");
	db_install_execute("0.8.6k", "ALTER TABLE `poller_item` ADD INDEX `rrd_next_step`(`rrd_next_step`)");

	/* speed up the UI */
	db_install_execute("0.8.6k", "ALTER TABLE `poller_item` ADD INDEX `action`(`action`)");
	db_install_execute("0.8.6k", "ALTER TABLE `user_auth` ADD INDEX `username`(`username`)");
	db_install_execute("0.8.6k", "ALTER TABLE `user_log` ADD INDEX `username`(`username`)");
	db_install_execute("0.8.6k", "ALTER TABLE `data_input` ADD INDEX `name`(`name`)");

	/* Add enable/disable to users */
	db_install_execute("0.8.6k", "ALTER TABLE `user_auth` ADD COLUMN `enabled` CHAR(2) DEFAULT 'on'");
	db_install_execute("0.8.6k", "ALTER TABLE `user_auth` ADD INDEX `enabled`(`enabled`)");

	/* add additional fields to the host table */
	db_install_execute("0.8.6k", "ALTER TABLE `host` ADD COLUMN `availability_method` SMALLINT(5) UNSIGNED NOT NULL default '1' AFTER `snmp_timeout`");
	db_install_execute("0.8.6k", "ALTER TABLE `host` ADD COLUMN `ping_method` SMALLINT(5) UNSIGNED default '0' AFTER `availability_method`");
	db_install_execute("0.8.6k", "ALTER TABLE `host` ADD COLUMN `ping_port` INT(12) UNSIGNED default '0' AFTER `ping_method`");
	db_install_execute("0.8.6k", "ALTER TABLE `host` ADD COLUMN `max_oids` INT(12) UNSIGNED default '10' AFTER `ping_port`");

	/* Convert to new authentication system */
	if (db_fetch_cell("SELECT `value` FROM `settings` WHERE `name` = 'global_auth'") == "on") {
		if (db_fetch_cell("SELECT `value` FROM `settings` WHERE `name` = 'ldap_enable'") == "on") {
			db_install_execute("0.8.6k", "INSERT INTO settings VALUES ('auth_method','3')");
		}else{
			db_install_execute("0.8.6k", "INSERT INTO settings VALUES ('auth_method','1')");
		}
	}else{
		db_install_execute("0.8.6k", "INSERT INTO settings VALUES ('auth_method','0')");
	}

	db_install_execute("0.8.6k", "UPDATE `settings` SET name = 'user_template' WHERE name = 'ldap_template'");
	db_install_execute("0.8.6k", "DELETE FROM `settings` WHERE name = 'global_auth'");
	db_install_execute("0.8.6k", "DELETE FROM `settings` WHERE name = 'ldap_enabled'");

	/* Add 1 min poller templates */
	db_install_execute("0.8.6k", "INSERT INTO data_template VALUES (DEFAULT, '86b2eabe1ce5be31326a8ec84f827380','Interface - Traffic 1 min')");
	$data_temp_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO data_template_data VALUES (DEFAULT,0,0,$data_temp_id,2,'on','|host_description| - Traffic','',NULL,'','on','',60,'')");
	$data_temp_data_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO rra VALUES (DEFAULT,'283ea2bf1634d92ce081ec82a634f513','Hourly (1 Minute Average)',0.5,1,500,14400)");
	$rrd_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO `rra_cf` VALUES ($rrd_id,1), ($rrd_id,3)");

	db_install_execute("0.8.6k", "INSERT INTO data_input_data VALUES (39,$data_temp_data_id,'',''),(14,$data_temp_data_id,'on',''),(13,$data_temp_data_id,'on',''),(12,$data_temp_data_id,'on',''),(11,$data_temp_data_id,'',''),(10,$data_temp_data_id,'',''),(9,$data_temp_data_id,'',''),(8,$data_temp_data_id,'',''),(7,$data_temp_data_id,'','')");
	db_install_execute("0.8.6k", "INSERT INTO data_template_data_rra VALUES ($data_temp_data_id,1),($data_temp_data_id,2),($data_temp_data_id,3),($data_temp_data_id,4),($data_temp_data_id,$rrd_id)");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates VALUES (DEFAULT,'9f30faece8c389d16c457e9ea7395c7c','Interface - Traffic (bits/sec) 1 min')");
	$graph_temp_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_graph VALUES (DEFAULT,0,0,$graph_temp_id,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bits per second','','on','','on','',2,'','','','on','','on','',1000,'0','','','on','','','','')");

	db_install_execute("0.8.6k","INSERT INTO snmp_query_graph VALUES (DEFAULT,'ab9be2ac012a0459f838a95338fe6c8b',1,'In/Out Bits 1 min',$graph_temp_id)");
	$snmp_query_graph1 = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph VALUES (DEFAULT,'d8149dc09ac2eef48e24a65b8dda4a03',1,'In/Out Bits 1 min (64-bit Counters)',$graph_temp_id)");
	$snmp_query_graph2 = mysql_insert_id();

	db_install_execute("0.8.6k", "INSERT INTO graph_template_input VALUES (DEFAULT,'a77087e2c9f879bebb3b1af78461b5e8',$graph_temp_id,'Inbound Data Source','','task_item_id')");
	$graph_temp_input_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO data_template_rrd VALUES (DEFAULT, '0b099ce689bd7f86c57ef3dbc5d8c796', 0, 0, $data_temp_id, '', '100000000', '', '0', '', 120, '', 2, '', 'traffic_in', '', 0)");
	$data_temp_rrd = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_rrd VALUES ($snmp_query_graph1,$data_temp_id,$data_temp_rrd,'ifInOctets')");
	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_rrd VALUES ($snmp_query_graph2,$data_temp_id,$data_temp_rrd,'ifHCInOctets')");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'52fdeefafb7750bddbe7f09197ca5f49',0,0,$graph_temp_id,$data_temp_rrd,22,7,2,1,'Inbound','','',2,1)");
	$graph_temp_item_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'1ea3f40c7dda74e2d2532b1112a907cb',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,4,'Current:','','',2,2)");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'f474e072aab78dff22974cc023d4b330',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,1,'Average:','','',2,3)");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'189515e66a3c4d184cfa6f39f93a1705',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,3,'Maximum:','','on',2,4)");
	db_install_execute("0.8.6k", "INSERT INTO graph_template_input_defs VALUES ($graph_temp_input_id,$graph_temp_item_id),($graph_temp_input_id,($graph_temp_item_id+1)),($graph_temp_input_id,($graph_temp_item_id+2)),($graph_temp_input_id,($graph_temp_item_id+3))");

	db_install_execute("0.8.6k", "INSERT INTO graph_template_input VALUES (DEFAULT,'07caac9f9df540d543e0efe4b074b31b',$graph_temp_id,'Outbound Data Source','','task_item_id');");
	$graph_temp_input_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO data_template_rrd VALUES (DEFAULT, '05c88d9ea799f6fd1cf707aa9a5412b4', 0, 0, $data_temp_id, '', '100000000', '', '0', '', 120, '', 2, '', 'traffic_out', '', 0)");
	$data_temp_rrd = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_rrd VALUES ($snmp_query_graph1,$data_temp_id,$data_temp_rrd,'ifOutOctets')");
	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_rrd VALUES ($snmp_query_graph2,$data_temp_id,$data_temp_rrd,'ifHCOutOctets')");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'4a77b7575e55317abd9346eee4db1532',0,0,$graph_temp_id,$data_temp_rrd,20,4,2,1,'Outbound','','',2,5)");
	$graph_temp_item_id = mysql_insert_id();
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'4cdb0517f55e0dc2ef14f44f1261fc50',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,4,'Current:','','',2,6)");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'ed7df282e81ca6f1de37c1f8d084e3d0',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,1,'Average:','','',2,7)");
	db_install_execute("0.8.6k", "INSERT INTO graph_templates_item VALUES (DEFAULT,'af94e71eb62ebd6cd1b396ae81c5e1eb',0,0,$graph_temp_id,$data_temp_rrd,0,9,2,3,'Maximum:','','',2,8)");
	db_install_execute("0.8.6k", "INSERT INTO graph_template_input_defs VALUES ($graph_temp_input_id,$graph_temp_item_id),($graph_temp_input_id,($graph_temp_item_id+1)),($graph_temp_input_id,($graph_temp_item_id+2)),($graph_temp_input_id,($graph_temp_item_id+3))");

	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_rrd_sv VALUES (DEFAULT,'746e652267ce5528c09166d2a4059a50',$snmp_query_graph1,$data_temp_id,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|'),(DEFAULT,'ab0490c6e63f7208da173e8c5e2c7be5',$snmp_query_graph1,$data_temp_id,2,'name','|host_description| - Traffic - |query_ifName|'),(DEFAULT,'1e856751952bd953b5f42bd8440c74d7',$snmp_query_graph1,$data_temp_id,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|'),(DEFAULT,'10bf3e80f6eb9006be58efd5c86f6b3c',$snmp_query_graph1,$data_temp_id,4,'name','|host_description| - Traffic - |query_ifDescr|'),(DEFAULT,'611b272ef90bbb1aab6275df61668776',$snmp_query_graph1,$data_temp_id,5,'rrd_maximum','|query_ifSpeed|'),(DEFAULT,'cf040e3920956ee6876610d5a053370a',$snmp_query_graph2,$data_temp_id,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|'),(DEFAULT,'4d58f061af99afb868605438c4fb85eb',$snmp_query_graph2,$data_temp_id,2,'name','|host_description| - Traffic - |query_ifName|'),(DEFAULT,'98696b64f95b6fdbcc2f3007067e525d',$snmp_query_graph2,$data_temp_id,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|'),(DEFAULT,'5723c88453f577a4c399bbc3e4486a26',$snmp_query_graph2,$data_temp_id,4,'name','|host_description| - Traffic - |query_ifDescr|'),(DEFAULT,'be8210af674da28db58093cab87a70b0',$snmp_query_graph2,$data_temp_id,5,'rrd_maximum','|query_ifSpeed|')");

	db_install_execute("0.8.6k", "INSERT INTO snmp_query_graph_sv VALUES (DEFAULT,'6867508d4c21bc223065e5fcec0ae4be',$snmp_query_graph1,1,'title','|host_description| - Traffic - |query_ifName|'),(DEFAULT,'40560a33ba2742df87c9f5b448a09514',$snmp_query_graph1,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)'),(DEFAULT,'478b957031bd4a2ba3ca61ffe6bc75ed',$snmp_query_graph1,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|'),(DEFAULT,'b372d29eb28c33e83e87029f208f30f8',$snmp_query_graph2,1,'title','|host_description| - Traffic - |query_ifName|'),(DEFAULT,'5fd05f44e69bac16b98796cc945ac060',$snmp_query_graph2,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)'),(DEFAULT,'6ffb207bd1bdb6d64989eea7a26a1323',$snmp_query_graph2,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|')");

}
?>
