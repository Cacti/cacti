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

--
-- Allow MySQL to handle Cacti's legacy syntax
--

DELIMITER //

--- Remove this legacy function if it exists
DROP FUNCTION IF EXISTS NOAUTOCREATENEEDED//

SET @sqlmode= "";
SET SESSION sql_mode = @sqlmode;

--
-- Table structure for table `aggregate_graph_templates`
--

CREATE TABLE `aggregate_graph_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `graph_template_id` int(10) unsigned NOT NULL,
  `gprint_prefix` varchar(64) NOT NULL,
  `gprint_format` char(2) DEFAULT '',
  `graph_type` int(10) unsigned NOT NULL,
  `total` int(10) unsigned NOT NULL,
  `total_type` int(10) unsigned NOT NULL,
  `total_prefix` varchar(64) NOT NULL,
  `order_type` int(10) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `graph_template_id` (`graph_template_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Template Definitions for Aggregate Graphs';

--
-- Table structure for table `aggregate_graph_templates_graph`
--
CREATE TABLE `aggregate_graph_templates_graph` (
  `aggregate_template_id` int(10) unsigned NOT NULL,
  `t_image_format_id` char(2) DEFAULT '',
  `image_format_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `t_height` char(2) DEFAULT '',
  `height` mediumint(8) NOT NULL DEFAULT '0',
  `t_width` char(2) DEFAULT '',
  `width` mediumint(8) NOT NULL DEFAULT '0',
  `t_upper_limit` char(2) DEFAULT '',
  `upper_limit` varchar(20) NOT NULL DEFAULT '0',
  `t_lower_limit` char(2) DEFAULT '',
  `lower_limit` varchar(20) NOT NULL DEFAULT '0',
  `t_vertical_label` char(2) DEFAULT '',
  `vertical_label` varchar(200) DEFAULT '',
  `t_slope_mode` char(2) DEFAULT '',
  `slope_mode` char(2) DEFAULT 'on',
  `t_auto_scale` char(2) DEFAULT '',
  `auto_scale` char(2) DEFAULT '',
  `t_auto_scale_opts` char(2) DEFAULT '',
  `auto_scale_opts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `t_auto_scale_log` char(2) DEFAULT '',
  `auto_scale_log` char(2) DEFAULT '',
  `t_scale_log_units` char(2) DEFAULT '',
  `scale_log_units` char(2) DEFAULT '',
  `t_auto_scale_rigid` char(2) DEFAULT '',
  `auto_scale_rigid` char(2) DEFAULT '',
  `t_auto_padding` char(2) DEFAULT '',
  `auto_padding` char(2) DEFAULT '',
  `t_base_value` char(2) DEFAULT '',
  `base_value` mediumint(8) NOT NULL DEFAULT '0',
  `t_grouping` char(2) DEFAULT '',
  `grouping` char(2) NOT NULL DEFAULT '',
  `t_unit_value` char(2) DEFAULT '',
  `unit_value` varchar(20) DEFAULT '',
  `t_unit_exponent_value` char(2) DEFAULT '',
  `unit_exponent_value` varchar(5) NOT NULL DEFAULT '',
  t_alt_y_grid char(2) default '',
  alt_y_grid char(2) default NULL,
  t_right_axis char(2) DEFAULT '',
  right_axis varchar(20) DEFAULT NULL,
  t_right_axis_label char(2) DEFAULT '',
  right_axis_label varchar(200) DEFAULT NULL,
  t_right_axis_format char(2) DEFAULT '',
  right_axis_format mediumint(8) DEFAULT NULL,
  t_right_axis_formatter char(2) DEFAULT '',
  right_axis_formatter varchar(10) DEFAULT NULL,
  t_left_axis_format char(2) DEFAULT '',
  left_axis_format mediumint(8) DEFAULT NULL,
  t_left_axis_formatter char(2) DEFAULT '',
  left_axis_formatter varchar(10) DEFAULT NULL,
  t_no_gridfit char(2) DEFAULT '',
  no_gridfit char(2) DEFAULT NULL,
  t_unit_length char(2) DEFAULT '',
  unit_length varchar(10) DEFAULT NULL,
  t_tab_width char(2) DEFAULT '',
  tab_width varchar(20) DEFAULT '30',
  t_dynamic_labels char(2) default '',
  dynamic_labels char(2) default NULL,
  t_force_rules_legend char(2) DEFAULT '',
  force_rules_legend char(2) DEFAULT NULL,
  t_legend_position char(2) DEFAULT '',
  legend_position varchar(10) DEFAULT NULL,
  t_legend_direction char(2) DEFAULT '',
  legend_direction varchar(10) DEFAULT NULL,
  PRIMARY KEY (`aggregate_template_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Aggregate Template Graph Data';

--
-- Table structure for table `aggregate_graph_templates_item`
--

CREATE TABLE `aggregate_graph_templates_item` (
  `aggregate_template_id` int(10) unsigned NOT NULL,
  `graph_templates_item_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_template` int(10) unsigned NOT NULL,
  `t_graph_type_id` char(2) DEFAULT '',
  `graph_type_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `t_cdef_id` char(2) DEFAULT '',
  `cdef_id` mediumint(8) unsigned DEFAULT NULL,
  `item_skip` char(2) NOT NULL,
  `item_total` char(2) NOT NULL,
  PRIMARY KEY (`aggregate_template_id`,`graph_templates_item_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Aggregate Template Graph Items';

--
-- Table structure for table `aggregate_graphs`
--

CREATE TABLE `aggregate_graphs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aggregate_template_id` int(10) unsigned NOT NULL,
  `template_propogation` char(2) NOT NULL DEFAULT '',
  `local_graph_id` int(10) unsigned NOT NULL,
  `title_format` varchar(128) NOT NULL,
  `graph_template_id` int(10) unsigned NOT NULL,
  `gprint_prefix` varchar(64) NOT NULL,
  `gprint_format` char(2) DEFAULT '',
  `graph_type` int(10) unsigned NOT NULL,
  `total` int(10) unsigned NOT NULL,
  `total_type` int(10) unsigned NOT NULL,
  `total_prefix` varchar(64) NOT NULL,
  `order_type` int(10) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `aggregate_template_id` (`aggregate_template_id`),
  KEY `local_graph_id` (`local_graph_id`),
  KEY `title_format` (`title_format`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Aggregate Graph Definitions';

--
-- Table structure for table `aggregate_graphs_graph_item`
--

CREATE TABLE `aggregate_graphs_graph_item` (
  `aggregate_graph_id` int(10) unsigned NOT NULL,
  `graph_templates_item_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_template` int(10) unsigned NOT NULL,
  `t_graph_type_id` char(2) DEFAULT '',
  `graph_type_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `t_cdef_id` char(2) DEFAULT '',
  `cdef_id` mediumint(8) unsigned DEFAULT NULL,
  `item_skip` char(2) NOT NULL,
  `item_total` char(2) NOT NULL,
  PRIMARY KEY (`aggregate_graph_id`,`graph_templates_item_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Aggregate Graph Graph Items';

--
-- Table structure for table `aggregate_graphs_items`
--

CREATE TABLE `aggregate_graphs_items` (
  `aggregate_graph_id` int(10) unsigned NOT NULL,
  `local_graph_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`aggregate_graph_id`,`local_graph_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Aggregate Graph Items';

--
-- Table structure for table `automation_devices`
--

CREATE TABLE `automation_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `network_id` int(10) unsigned NOT NULL DEFAULT '0',
  `host_id` int(10) unsigned NOT NULL DEFAULT '0',
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `ip` varchar(17) NOT NULL DEFAULT '',
  `snmp_community` varchar(100) NOT NULL DEFAULT '',
  `snmp_version` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `snmp_port` mediumint(8) unsigned NOT NULL DEFAULT '161',
  `snmp_username` varchar(50) DEFAULT NULL,
  `snmp_password` varchar(50) DEFAULT NULL,
  `snmp_auth_protocol` char(6) DEFAULT '',
  `snmp_priv_passphrase` varchar(200) DEFAULT '',
  `snmp_priv_protocol` char(6) DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(64) DEFAULT '',
  `sysName` varchar(100) NOT NULL DEFAULT '',
  `sysLocation` varchar(255) NOT NULL DEFAULT '',
  `sysContact` varchar(255) NOT NULL DEFAULT '',
  `sysDescr` varchar(255) NOT NULL DEFAULT '',
  `sysUptime` bigint(20) unsigned NOT NULL DEFAULT '0',
  `os` varchar(64) NOT NULL DEFAULT '',
  `snmp` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `known` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `up` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table of Discovered Devices';

--
-- Table structure for table `automation_graph_rule_items`
--

CREATE TABLE `automation_graph_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `operation` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `operator` smallint(3) unsigned NOT NULL DEFAULT '0',
  `pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Automation Graph Rule Items';

--
-- Dumping data for table `automation_graph_rule_items`
--

INSERT INTO `automation_graph_rule_items` VALUES (1,'',1,1,0,'ifOperStatus',7,'Up'),(2,'',1,2,1,'ifIP',16,''),(3,'',1,3,1,'ifHwAddr',16,''),(4,'',2,1,0,'ifOperStatus',7,'Up'),(5,'',2,2,1,'ifIP',16,''),(6,'',2,3,1,'ifHwAddr',16,'');

--
-- Table structure for table `automation_graph_rules`
--

CREATE TABLE `automation_graph_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `snmp_query_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `graph_type_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Automation Graph Rules';

--
-- Dumping data for table `automation_graph_rules`
--

INSERT INTO `automation_graph_rules` VALUES (1,'','Traffic 64 bit Server',1,12,'on'),(2,'','Traffic 64 bit Server Linux',1,12,'on'),(3,'','Disk Space',3,17,'on');

--
-- Table structure for table `automation_ips`
--

CREATE TABLE `automation_ips` (
  `ip_address` varchar(20) NOT NULL DEFAULT '',
  `hostname` varchar(100) DEFAULT NULL,
  `network_id` int(10) unsigned DEFAULT NULL,
  `pid` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned DEFAULT NULL,
  `thread` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`ip_address`),
  KEY `pid` (`pid`)
) ENGINE=MEMORY COMMENT='List of discoverable ip addresses used for scanning';

--
-- Table structure for table `automation_match_rule_items`
--

CREATE TABLE `automation_match_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rule_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `operation` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `operator` smallint(3) unsigned NOT NULL DEFAULT '0',
  `pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Automation Match Rule Items';

--
-- Dumping data for table `automation_match_rule_items`
--

INSERT INTO `automation_match_rule_items` VALUES (1,'',1,1,1,0,'h.snmp_sysDescr',8,''),(2,'',1,1,2,1,'h.snmp_version',12,'2'),(3,'',1,3,1,0,'ht.name',1,'Linux'),(4,'',2,1,1,0,'ht.name',1,'Linux'),(5,'',2,1,2,1,'h.snmp_version',12,'2'),(6,'',2,3,1,0,'ht.name',1,'SNMP'),(7,'',2,3,2,1,'gt.name',1,'Traffic'),(8,'',1,1,3,1,'h.snmp_sysDescr',2,'Windows');

--
-- Table structure for table `automation_networks`
--

CREATE TABLE `automation_networks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `poller_id` int(10) unsigned DEFAULT '1',
  `site_id` int(10) unsigned DEFAULT '1',
  `name` varchar(128) NOT NULL DEFAULT '' COMMENT 'The name for this network',
  `subnet_range` varchar(1024) NOT NULL DEFAULT '' COMMENT 'Defined subnet ranges for discovery',
  `ignore_ips` varchar(1024) NOT NULL DEFAULT '' COMMENT 'IP addresses to skip during discovery',
  `dns_servers` varchar(128) NOT NULL DEFAULT '' COMMENT 'DNS Servers to use for name resolution',
  `enabled` char(2) DEFAULT '',
  `notification_enabled` char(2) DEFAULT '',
  `notification_email` varchar(255) DEFAULT '',
  `notification_fromname` varchar(32) DEFAULT '',
  `notification_fromemail` varchar(128) DEFAULT '',
  `snmp_id` int(10) unsigned DEFAULT NULL,
  `enable_netbios` char(2) DEFAULT '',
  `add_to_cacti` char(2) DEFAULT '',
  `same_sysname` char(2) DEFAULT '',
  `total_ips` int(10) unsigned DEFAULT '0',
  `up_hosts` int(10) unsigned NOT NULL DEFAULT '0',
  `snmp_hosts` int(10) unsigned NOT NULL DEFAULT '0',
  `ping_method` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'The ping method (ICMP:TCP:UDP)',
  `ping_port` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'For TCP:UDP the port to ping',
  `ping_timeout` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'The ping timeout in seconds',
  `ping_retries` int(10) unsigned DEFAULT '0',
  `sched_type` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Schedule type: manual or automatic',
  `threads` int(10) unsigned DEFAULT '1',
  `run_limit` int(10) unsigned DEFAULT '0' COMMENT 'The maximum runtime for the discovery',
  `start_at` varchar(20) DEFAULT NULL,
  `next_start` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `recur_every` int(10) unsigned DEFAULT '1',
  `day_of_week` varchar(45) DEFAULT NULL COMMENT 'The days of week to run in crontab format',
  `month` varchar(45) DEFAULT NULL COMMENT 'The months to run in crontab format',
  `day_of_month` varchar(45) DEFAULT NULL COMMENT 'The days of month to run in crontab format',
  `monthly_week` varchar(45) DEFAULT NULL,
  `monthly_day` varchar(45) DEFAULT NULL,
  `last_runtime` double NOT NULL DEFAULT '0' COMMENT 'The last runtime for discovery',
  `last_started` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'The time the discovery last started',
  `last_status` varchar(128) NOT NULL DEFAULT '' COMMENT 'The last exit message if any',
  `rerun_data_queries` char(2) DEFAULT NULL COMMENT 'Rerun data queries or not for existing hosts',
  PRIMARY KEY (`id`),
  KEY `poller_id` (`poller_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores scanning subnet definitions';

--
-- Dumping data for table `automation_networks`
--

INSERT INTO `automation_networks` VALUES (1,'',1,0,'Test Network','192.168.1.0/24','','','on','','','','',1,'on','on','',254,0,0,1,22,400,1,2,10,1200,'0000-00-00 00:00:00','0000-00-00 00:00:00',2,'4','','','','',0,'0000-00-00 00:00:00','','on');

--
-- Table structure for table `automation_processes`
--

CREATE TABLE `automation_processes` (
  `pid` int(10) unsigned NOT NULL,
  `poller_id` int(10) unsigned DEFAULT '1',
  `network_id` int(10) unsigned NOT NULL DEFAULT '0',
  `task` varchar(20) DEFAULT '',
  `status` varchar(20) DEFAULT NULL,
  `command` varchar(20) DEFAULT NULL,
  `up_hosts` int(10) unsigned DEFAULT '0',
  `snmp_hosts` int(10) unsigned DEFAULT '0',
  `heartbeat` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`pid`,`network_id`)
) ENGINE=MEMORY COMMENT='Table tracking active poller processes';

--
-- Table structure for table `automation_snmp`
--

CREATE TABLE `automation_snmp` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Group of SNMP Option Sets';

--
-- Dumping data for table `automation_snmp`
--

INSERT INTO `automation_snmp` VALUES (1,'','Default Option Set');

--
-- Table structure for table `automation_snmp_items`
--

CREATE TABLE `automation_snmp_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `snmp_id` int(10) unsigned NOT NULL DEFAULT '0',
  `sequence` int(10) unsigned NOT NULL DEFAULT '0',
  `snmp_version` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `snmp_community` varchar(100) NOT NULL,
  `snmp_port` mediumint(8) unsigned NOT NULL DEFAULT '161',
  `snmp_timeout` int(10) unsigned NOT NULL DEFAULT '500',
  `snmp_retries` tinyint(3) unsigned NOT NULL DEFAULT '3',
  `max_oids` int(10) unsigned DEFAULT '10',
  `bulk_walk_size` int(11) DEFAULT '-1',
  `snmp_username` varchar(50) DEFAULT NULL,
  `snmp_password` varchar(50) DEFAULT NULL,
  `snmp_auth_protocol` char(6) DEFAULT '',
  `snmp_priv_passphrase` varchar(200) DEFAULT '',
  `snmp_priv_protocol` char(6) DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(64) DEFAULT '',
  PRIMARY KEY (`id`,`snmp_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Set of SNMP Options';

--
-- Dumping data for table `automation_snmp_items`
--

INSERT INTO `automation_snmp_items` VALUES (1,'',1,1,'2','public',161,1000,3,10,-1,'admin','baseball','MD5','','DES','',''),(2,'',1,2,'2','private',161,1000,3,10,-1,'admin','baseball','MD5','','DES','','');

--
-- Table structure for table `automation_templates`
--

CREATE TABLE `automation_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `host_template` int(10) unsigned NOT NULL DEFAULT '0',
  `availability_method` int(10) unsigned DEFAULT '2',
  `sysDescr` varchar(255) DEFAULT '',
  `sysName` varchar(255) DEFAULT '',
  `sysOid` varchar(60) DEFAULT '',
  `description_pattern` varchar(128) DEFAULT '',
  `populate_location` char(2) DEFAULT '',
  `sequence` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Templates of SNMP Sys variables used for automation';

--
-- Dumping data for table `automation_templates`
--

INSERT INTO `automation_templates` VALUES (1,'',3,2,'Linux','','','','',2),(2,'',1,2,'Windows','','','','',1),(3,'',2,2,'(Cisco Internetwork Operating System Software|IOS)','','','','',3);

--
-- Table structure for table `automation_templates_rules`
--

CREATE TABLE `automation_templates_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `template_id` int(10) unsigned NOT NULL DEFAULT 0,
  `rule_type` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `rule_id` int(10) unsigned NOT NULL DEFAULT 0,
  `sequence` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `exit_rules` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`template_id`,`rule_type`,`rule_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Holds mappings of Automation Templates to Rules';

--
-- Dumping data for table `automation_templates_rules`
--

--
-- Table structure for table `automation_tree_rule_items`
--

CREATE TABLE `automation_tree_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `sort_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `propagate_changes` char(2) DEFAULT '',
  `search_pattern` varchar(255) NOT NULL DEFAULT '',
  `replace_pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Automation Tree Rule Items';

--
-- Dumping data for table `automation_tree_rule_items`
--

INSERT INTO `automation_tree_rule_items` VALUES (1,'',1,1,'ht.name',1,'','^(.*)\\s*Linux\\s*(.*)$','${1}\\n${2}'),(2,'',1,2,'h.hostname',1,'','^(\\w*)\\s*(\\w*)\\s*(\\w*).*$',''),(3,'',2,1,'0',2,'on','Traffic',''),(4,'',2,2,'gtg.title_cache',1,'','^(.*)\\s*-\\s*Traffic -\\s*(.*)$','${1}\\n${2}');

--
-- Table structure for table `automation_tree_rules`
--

CREATE TABLE `automation_tree_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `tree_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `tree_item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `leaf_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `host_grouping_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Automation Tree Rules';

--
-- Dumping data for table `automation_tree_rules`
--

INSERT INTO `automation_tree_rules` VALUES (1,'','New Device',1,0,3,1,'on'),(2,'','New Graph',1,0,2,1,'');

--
-- Table structure for table `cdef`
--

CREATE TABLE cdef (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `hash` varchar(32) NOT NULL default '',
  `system` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY `hash` (`hash`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `cdef`
--

INSERT INTO cdef VALUES (3,'3d352eed9fa8f7b2791205b3273708c7',0,'Make Stack Negative');
INSERT INTO cdef VALUES (4,'e961cc8ec04fda6ed4981cf5ad501aa5',0,'Make Per 5 Minutes');
INSERT INTO cdef VALUES (12,'f1ac79f05f255c02f914c920f1038c54',0,'Total All Data Sources');
INSERT INTO cdef VALUES (2,'73f95f8b77b5508157d64047342c421e',0,'Turn Bytes into Bits');
INSERT INTO cdef VALUES (14,'634a23af5e78af0964e8d33b1a4ed26b',0,'Multiply by 1024');
INSERT INTO cdef VALUES (15,'068984b5ccdfd2048869efae5166f722',0,'Total All Data Sources, Multiply by 1024');

--
-- Table structure for table `cdef_items`
--

CREATE TABLE cdef_items (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  cdef_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  type tinyint(3) unsigned NOT NULL default '0',
  value varchar(150) NOT NULL default '',
  PRIMARY KEY (id),
  KEY cdef_id_sequence (`cdef_id`,`sequence`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `cdef_items`
--

INSERT INTO cdef_items VALUES (7,'9bbf6b792507bb9bb17d2af0970f9be9',2,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO cdef_items VALUES (9,'a4b8eb2c3bf4920a3ef571a7a004be53',2,2,6,'8');
INSERT INTO cdef_items VALUES (8,'caa4e023ac2d7b1c4b4c8c4adfd55dfe',2,3,2,'3');
INSERT INTO cdef_items VALUES (10,'c888c9fe6b62c26c4bfe23e18991731d',3,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO cdef_items VALUES (11,'1e1d0b29a94e08b648c8f053715442a0',3,3,2,'3');
INSERT INTO cdef_items VALUES (12,'4355c197998c7f8b285be7821ddc6da4',3,2,6,'-1');
INSERT INTO cdef_items VALUES (13,'40bb7a1143b0f2e2efca14eb356236de',4,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO cdef_items VALUES (14,'42686ea0925c0220924b7d333599cd67',4,3,2,'3');
INSERT INTO cdef_items VALUES (15,'faf1b148b2c0e0527362ed5b8ca1d351',4,2,6,'300');
INSERT INTO cdef_items VALUES (16,'0ef6b8a42dc83b4e43e437960fccd2ea',12,1,4,'ALL_DATA_SOURCES_NODUPS');
INSERT INTO cdef_items VALUES (18,'86370cfa0008fe8c56b28be80ee39a40',14,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO cdef_items VALUES (19,'9a35cc60d47691af37f6fddf02064e20',14,2,6,'1024');
INSERT INTO cdef_items VALUES (20,'5d7a7941ec0440b257e5598a27dd1688',14,3,2,'3');
INSERT INTO cdef_items VALUES (21,'44fd595c60539ff0f5817731d9f43a85',15,1,4,'ALL_DATA_SOURCES_NODUPS');
INSERT INTO cdef_items VALUES (22,'aa38be265e5ac31783e57ce6f9314e9a',15,2,6,'1024');
INSERT INTO cdef_items VALUES (23,'204423d4b2598f1f7252eea19458345c',15,3,2,'3');


CREATE TABLE `color_templates` (
  `color_template_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`color_template_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Color Templates';

--
-- Dumping data for table `color_templates`
--

INSERT INTO `color_templates` VALUES (1,'Yellow: light -> dark, 4 colors');
INSERT INTO `color_templates` VALUES (2,'Red: light yellow > dark red, 8 colors');
INSERT INTO `color_templates` VALUES (3,'Red: light -> dark, 16 colors');
INSERT INTO `color_templates` VALUES (4,'Green: dark -> light, 16 colors');

--
-- Table structure for table `color_template_items`
--

CREATE TABLE `color_template_items` (
  `color_template_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `color_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`color_template_item_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Color Items for Color Templates';

--
-- Dumping data for table `color_template_items`
--

INSERT INTO `color_template_items` VALUES (1,1,4,1);
INSERT INTO `color_template_items` VALUES (2,1,24,2);
INSERT INTO `color_template_items` VALUES (3,1,98,3);
INSERT INTO `color_template_items` VALUES (4,1,25,4);
INSERT INTO `color_template_items` VALUES (5,2,25,1);
INSERT INTO `color_template_items` VALUES (6,2,29,2);
INSERT INTO `color_template_items` VALUES (7,2,30,3);
INSERT INTO `color_template_items` VALUES (8,2,31,4);
INSERT INTO `color_template_items` VALUES (9,2,33,5);
INSERT INTO `color_template_items` VALUES (10,2,35,6);
INSERT INTO `color_template_items` VALUES (11,2,41,7);
INSERT INTO `color_template_items` VALUES (12,2,9,8);
INSERT INTO `color_template_items` VALUES (13,3,15,1);
INSERT INTO `color_template_items` VALUES (14,3,31,2);
INSERT INTO `color_template_items` VALUES (15,3,28,3);
INSERT INTO `color_template_items` VALUES (16,3,8,4);
INSERT INTO `color_template_items` VALUES (17,3,34,5);
INSERT INTO `color_template_items` VALUES (18,3,33,6);
INSERT INTO `color_template_items` VALUES (19,3,35,7);
INSERT INTO `color_template_items` VALUES (20,3,41,8);
INSERT INTO `color_template_items` VALUES (21,3,36,9);
INSERT INTO `color_template_items` VALUES (22,3,42,10);
INSERT INTO `color_template_items` VALUES (23,3,44,11);
INSERT INTO `color_template_items` VALUES (24,3,48,12);
INSERT INTO `color_template_items` VALUES (25,3,9,13);
INSERT INTO `color_template_items` VALUES (26,3,49,14);
INSERT INTO `color_template_items` VALUES (27,3,51,15);
INSERT INTO `color_template_items` VALUES (28,3,52,16);
INSERT INTO `color_template_items` VALUES (29,4,76,1);
INSERT INTO `color_template_items` VALUES (30,4,84,2);
INSERT INTO `color_template_items` VALUES (31,4,89,3);
INSERT INTO `color_template_items` VALUES (32,4,17,4);
INSERT INTO `color_template_items` VALUES (33,4,86,5);
INSERT INTO `color_template_items` VALUES (34,4,88,6);
INSERT INTO `color_template_items` VALUES (35,4,90,7);
INSERT INTO `color_template_items` VALUES (36,4,94,8);
INSERT INTO `color_template_items` VALUES (37,4,96,9);
INSERT INTO `color_template_items` VALUES (38,4,93,10);
INSERT INTO `color_template_items` VALUES (39,4,91,11);
INSERT INTO `color_template_items` VALUES (40,4,22,12);
INSERT INTO `color_template_items` VALUES (41,4,12,13);
INSERT INTO `color_template_items` VALUES (42,4,95,14);
INSERT INTO `color_template_items` VALUES (43,4,6,15);
INSERT INTO `color_template_items` VALUES (44,4,92,16);

--
-- Table structure for table `colors`
--

CREATE TABLE colors (
  id mediumint(8) unsigned NOT NULL auto_increment,
  name varchar(40) default '',
  hex varchar(6) NOT NULL default '',
  read_only char(2) default '',
  PRIMARY KEY (id),
  UNIQUE KEY hex (hex)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `colors`
--

INSERT INTO colors VALUES (1,'Black','000000','on');
INSERT INTO colors VALUES (2,'White','FFFFFF','on');
INSERT INTO colors VALUES (4,'','FAFD9E','');
INSERT INTO colors VALUES (5,'','C0C0C0','');
INSERT INTO colors VALUES (6,'','74C366','');
INSERT INTO colors VALUES (7,'','6DC8FE','');
INSERT INTO colors VALUES (8,'','EA8F00','');
INSERT INTO colors VALUES (9,'Red','FF0000','on');
INSERT INTO colors VALUES (10,'','4444FF','');
INSERT INTO colors VALUES (11,'Magenta','FF00FF','on');
INSERT INTO colors VALUES (12,'Green','00FF00','on');
INSERT INTO colors VALUES (13,'','8D85F3','');
INSERT INTO colors VALUES (14,'','AD3B6E','');
INSERT INTO colors VALUES (15,'','EACC00','');
INSERT INTO colors VALUES (16,'','12B3B5','');
INSERT INTO colors VALUES (17,'','157419','');
INSERT INTO colors VALUES (18,'','C4FD3D','');
INSERT INTO colors VALUES (19,'','817C4E','');
INSERT INTO colors VALUES (20,'','002A97','');
INSERT INTO colors VALUES (21,'','0000FF','');
INSERT INTO colors VALUES (22,'','00CF00','');
INSERT INTO colors VALUES (24,'','F9FD5F','');
INSERT INTO colors VALUES (25,'','FFF200','');
INSERT INTO colors VALUES (26,'','CCBB00','');
INSERT INTO colors VALUES (27,'','837C04','');
INSERT INTO colors VALUES (28,'','EAAF00','');
INSERT INTO colors VALUES (29,'','FFD660','');
INSERT INTO colors VALUES (30,'','FFC73B','');
INSERT INTO colors VALUES (31,'','FFAB00','');
INSERT INTO colors VALUES (33,'','FF7D00','');
INSERT INTO colors VALUES (34,'','ED7600','');
INSERT INTO colors VALUES (35,'','FF5700','');
INSERT INTO colors VALUES (36,'','EE5019','');
INSERT INTO colors VALUES (37,'','B1441E','');
INSERT INTO colors VALUES (38,'','FFC3C0','');
INSERT INTO colors VALUES (39,'','FF897C','');
INSERT INTO colors VALUES (40,'','FF6044','');
INSERT INTO colors VALUES (41,'','FF4105','');
INSERT INTO colors VALUES (42,'','DA4725','');
INSERT INTO colors VALUES (43,'','942D0C','');
INSERT INTO colors VALUES (44,'','FF3932','');
INSERT INTO colors VALUES (45,'','862F2F','');
INSERT INTO colors VALUES (46,'','FF5576','');
INSERT INTO colors VALUES (47,'','562B29','');
INSERT INTO colors VALUES (48,'','F51D30','');
INSERT INTO colors VALUES (49,'','DE0056','');
INSERT INTO colors VALUES (50,'','ED5394','');
INSERT INTO colors VALUES (51,'','B90054','');
INSERT INTO colors VALUES (52,'','8F005C','');
INSERT INTO colors VALUES (53,'','F24AC8','');
INSERT INTO colors VALUES (54,'','E8CDEF','');
INSERT INTO colors VALUES (55,'','D8ACE0','');
INSERT INTO colors VALUES (56,'','A150AA','');
INSERT INTO colors VALUES (57,'','750F7D','');
INSERT INTO colors VALUES (58,'','8D00BA','');
INSERT INTO colors VALUES (59,'','623465','');
INSERT INTO colors VALUES (60,'','55009D','');
INSERT INTO colors VALUES (61,'','3D168B','');
INSERT INTO colors VALUES (62,'','311F4E','');
INSERT INTO colors VALUES (63,'','D2D8F9','');
INSERT INTO colors VALUES (64,'','9FA4EE','');
INSERT INTO colors VALUES (65,'','6557D0','');
INSERT INTO colors VALUES (66,'','4123A1','');
INSERT INTO colors VALUES (67,'','4668E4','');
INSERT INTO colors VALUES (68,'','0D006A','');
INSERT INTO colors VALUES (69,'','00004D','');
INSERT INTO colors VALUES (70,'','001D61','');
INSERT INTO colors VALUES (71,'','00234B','');
INSERT INTO colors VALUES (72,'','002A8F','');
INSERT INTO colors VALUES (73,'','2175D9','');
INSERT INTO colors VALUES (74,'','7CB3F1','');
INSERT INTO colors VALUES (75,'','005199','');
INSERT INTO colors VALUES (76,'','004359','');
INSERT INTO colors VALUES (77,'','00A0C1','');
INSERT INTO colors VALUES (78,'','007283','');
INSERT INTO colors VALUES (79,'','00BED9','');
INSERT INTO colors VALUES (80,'','AFECED','');
INSERT INTO colors VALUES (81,'','55D6D3','');
INSERT INTO colors VALUES (82,'','00BBB4','');
INSERT INTO colors VALUES (83,'','009485','');
INSERT INTO colors VALUES (84,'','005D57','');
INSERT INTO colors VALUES (85,'','008A77','');
INSERT INTO colors VALUES (86,'','008A6D','');
INSERT INTO colors VALUES (87,'','00B99B','');
INSERT INTO colors VALUES (88,'','009F67','');
INSERT INTO colors VALUES (89,'','00694A','');
INSERT INTO colors VALUES (90,'','00A348','');
INSERT INTO colors VALUES (91,'','00BF47','');
INSERT INTO colors VALUES (92,'','96E78A','');
INSERT INTO colors VALUES (93,'','00BD27','');
INSERT INTO colors VALUES (94,'','35962B','');
INSERT INTO colors VALUES (95,'','7EE600','');
INSERT INTO colors VALUES (96,'','6EA100','');
INSERT INTO colors VALUES (97,'','CAF100','');
INSERT INTO colors VALUES (98,'','F5F800','');
INSERT INTO colors VALUES (99,'','CDCFC4','');
INSERT INTO colors VALUES (100,'','BCBEB3','');
INSERT INTO colors VALUES (101,'','AAABA1','');
INSERT INTO colors VALUES (102,'','8F9286','');
INSERT INTO colors VALUES (103,'','797C6E','');
INSERT INTO colors VALUES (104,'','2E3127','');
INSERT INTO colors VALUES (105,'Night','0C090A','on');
INSERT INTO colors VALUES (106,'Gunmetal','2C3539','on');
INSERT INTO colors VALUES (107,'Midnight','2B1B17','on');
INSERT INTO colors VALUES (108,'Charcoal','34282C','on');
INSERT INTO colors VALUES (109,'Dark Slate Grey','25383C','on');
INSERT INTO colors VALUES (110,'Oil','3B3131','on');
INSERT INTO colors VALUES (111,'Black Cat','413839','on');
INSERT INTO colors VALUES (112,'Iridium','3D3C3A','on');
INSERT INTO colors VALUES (113,'Black Eel','463E3F','on');
INSERT INTO colors VALUES (114,'Black Cow','4C4646','on');
INSERT INTO colors VALUES (115,'Gray Wolf','504A4B','on');
INSERT INTO colors VALUES (116,'Vampire Gray','565051','on');
INSERT INTO colors VALUES (117,'Gray Dolphin','5C5858','on');
INSERT INTO colors VALUES (118,'Carbon Gray','625D5D','on');
INSERT INTO colors VALUES (119,'Ash Gray','666362','on');
INSERT INTO colors VALUES (120,'Cloudy Gray','6D6968','on');
INSERT INTO colors VALUES (121,'Smokey Gray','726E6D','on');
INSERT INTO colors VALUES (122,'Gray','736F6E','on');
INSERT INTO colors VALUES (123,'Granite','837E7C','on');
INSERT INTO colors VALUES (124,'Battleship Gray','848482','on');
INSERT INTO colors VALUES (125,'Gray Cloud','B6B6B4','on');
INSERT INTO colors VALUES (126,'Gray Goose','D1D0CE','on');
INSERT INTO colors VALUES (127,'Platinum','E5E4E2','on');
INSERT INTO colors VALUES (128,'Metallic Silver','BCC6CC','on');
INSERT INTO colors VALUES (129,'Blue Gray','98AFC7','on');
INSERT INTO colors VALUES (130,'Light Slate Gray','6D7B8D','on');
INSERT INTO colors VALUES (131,'Slate Gray','657383','on');
INSERT INTO colors VALUES (132,'Jet Gray','616D7E','on');
INSERT INTO colors VALUES (133,'Mist Blue','646D7E','on');
INSERT INTO colors VALUES (134,'Marble Blue','566D7E','on');
INSERT INTO colors VALUES (135,'Slate Blue','737CA1','on');
INSERT INTO colors VALUES (136,'Steel Blue','4863A0','on');
INSERT INTO colors VALUES (137,'Blue Jay','2B547E','on');
INSERT INTO colors VALUES (138,'Dark Slate Blue','2B3856','on');
INSERT INTO colors VALUES (139,'Midnight Blue','151B54','on');
INSERT INTO colors VALUES (140,'Navy Blue','000080','on');
INSERT INTO colors VALUES (141,'Blue Whale','342D7E','on');
INSERT INTO colors VALUES (142,'Lapis Blue','15317E','on');
INSERT INTO colors VALUES (143,'Cornflower Blue','151B8D','on');
INSERT INTO colors VALUES (144,'Earth Blue','0000A0','on');
INSERT INTO colors VALUES (145,'Cobalt Blue','0020C2','on');
INSERT INTO colors VALUES (146,'Blueberry Blue','0041C2','on');
INSERT INTO colors VALUES (147,'Sapphire Blue','2554C7','on');
INSERT INTO colors VALUES (148,'Blue Eyes','1569C7','on');
INSERT INTO colors VALUES (149,'Royal Blue','2B60DE','on');
INSERT INTO colors VALUES (150,'Blue Orchid','1F45FC','on');
INSERT INTO colors VALUES (151,'Blue Lotus','6960EC','on');
INSERT INTO colors VALUES (152,'Light Slate Blue','736AFF','on');
INSERT INTO colors VALUES (153,'Slate Blue','357EC7','on');
INSERT INTO colors VALUES (154,'Glacial Blue Ice','368BC1','on');
INSERT INTO colors VALUES (155,'Silk Blue','488AC7','on');
INSERT INTO colors VALUES (156,'Blue Ivy','3090C7','on');
INSERT INTO colors VALUES (157,'Blue Koi','659EC7','on');
INSERT INTO colors VALUES (158,'Columbia Blue','87AFC7','on');
INSERT INTO colors VALUES (159,'Baby Blue','95B9C7','on');
INSERT INTO colors VALUES (160,'Light Steel Blue','728FCE','on');
INSERT INTO colors VALUES (161,'Ocean Blue','2B65EC','on');
INSERT INTO colors VALUES (162,'Blue Ribbon','306EFF','on');
INSERT INTO colors VALUES (163,'Blue Dress','157DEC','on');
INSERT INTO colors VALUES (164,'Dodger Blue','1589FF','on');
INSERT INTO colors VALUES (165,'Cornflower Blue','6495ED','on');
INSERT INTO colors VALUES (166,'Sky Blue','6698FF','on');
INSERT INTO colors VALUES (167,'Butterfly Blue','38ACEC','on');
INSERT INTO colors VALUES (168,'Iceberg','56A5EC','on');
INSERT INTO colors VALUES (169,'Crystal Blue','5CB3FF','on');
INSERT INTO colors VALUES (170,'Deep Sky Blue','3BB9FF','on');
INSERT INTO colors VALUES (171,'Denim Blue','79BAEC','on');
INSERT INTO colors VALUES (172,'Light Sky Blue','82CAFA','on');
INSERT INTO colors VALUES (173,'Day Sky Blue','82CAFF','on');
INSERT INTO colors VALUES (174,'Jeans Blue','A0CFEC','on');
INSERT INTO colors VALUES (175,'Blue Angel','B7CEEC','on');
INSERT INTO colors VALUES (176,'Pastel Blue','B4CFEC','on');
INSERT INTO colors VALUES (177,'Sea Blue','C2DFFF','on');
INSERT INTO colors VALUES (178,'Powder Blue','C6DEFF','on');
INSERT INTO colors VALUES (179,'Coral Blue','AFDCEC','on');
INSERT INTO colors VALUES (180,'Light Blue','ADDFFF','on');
INSERT INTO colors VALUES (181,'Robin Egg Blue','BDEDFF','on');
INSERT INTO colors VALUES (182,'Pale Blue Lily','CFECEC','on');
INSERT INTO colors VALUES (183,'Light Cyan','E0FFFF','on');
INSERT INTO colors VALUES (184,'Water','EBF4FA','on');
INSERT INTO colors VALUES (185,'Alice Blue','F0F8FF','on');
INSERT INTO colors VALUES (186,'Azure','F0FFFF','on');
INSERT INTO colors VALUES (187,'Light Slate','CCFFFF','on');
INSERT INTO colors VALUES (188,'Light Aquamarine','93FFE8','on');
INSERT INTO colors VALUES (189,'Electric Blue','9AFEFF','on');
INSERT INTO colors VALUES (190,'Aquamarine','7FFFD4','on');
INSERT INTO colors VALUES (191,'Cyan or Aqua','00FFFF','on');
INSERT INTO colors VALUES (192,'Tron Blue','7DFDFE','on');
INSERT INTO colors VALUES (193,'Blue Zircon','57FEFF','on');
INSERT INTO colors VALUES (194,'Blue Lagoon','8EEBEC','on');
INSERT INTO colors VALUES (195,'Celeste','50EBEC','on');
INSERT INTO colors VALUES (196,'Blue Diamond','4EE2EC','on');
INSERT INTO colors VALUES (197,'Tiffany Blue','81D8D0','on');
INSERT INTO colors VALUES (198,'Cyan Opaque','92C7C7','on');
INSERT INTO colors VALUES (199,'Blue Hosta','77BFC7','on');
INSERT INTO colors VALUES (200,'Northern Lights Blue','78C7C7','on');
INSERT INTO colors VALUES (201,'Medium Turquoise','48CCCD','on');
INSERT INTO colors VALUES (202,'Turquoise','43C6DB','on');
INSERT INTO colors VALUES (203,'Jellyfish','46C7C7','on');
INSERT INTO colors VALUES (204,'Macaw Blue Green','43BFC7','on');
INSERT INTO colors VALUES (205,'Light Sea Green','3EA99F','on');
INSERT INTO colors VALUES (206,'Dark Turquoise','3B9C9C','on');
INSERT INTO colors VALUES (207,'Sea Turtle Green','438D80','on');
INSERT INTO colors VALUES (208,'Medium Aquamarine','348781','on');
INSERT INTO colors VALUES (209,'Greenish Blue','307D7E','on');
INSERT INTO colors VALUES (210,'Grayish Turquoise','5E7D7E','on');
INSERT INTO colors VALUES (211,'Beetle Green','4C787E','on');
INSERT INTO colors VALUES (212,'Teal','008080','on');
INSERT INTO colors VALUES (213,'Sea Green','4E8975','on');
INSERT INTO colors VALUES (214,'Camouflage Green','78866B','on');
INSERT INTO colors VALUES (215,'Sage Green','848b79','on');
INSERT INTO colors VALUES (216,'Hazel Green','617C58','on');
INSERT INTO colors VALUES (217,'Venom Green','728C00','on');
INSERT INTO colors VALUES (218,'Fern Green','667C26','on');
INSERT INTO colors VALUES (219,'Dark Forrest Green','254117','on');
INSERT INTO colors VALUES (220,'Medium Sea Green','306754','on');
INSERT INTO colors VALUES (221,'Medium Forest Green','347235','on');
INSERT INTO colors VALUES (222,'Seaweed Green','437C17','on');
INSERT INTO colors VALUES (223,'Pine Green','387C44','on');
INSERT INTO colors VALUES (224,'Jungle Green','347C2C','on');
INSERT INTO colors VALUES (225,'Shamrock Green','347C17','on');
INSERT INTO colors VALUES (226,'Medium Spring Green','348017','on');
INSERT INTO colors VALUES (227,'Forest Green','4E9258','on');
INSERT INTO colors VALUES (228,'Green Onion','6AA121','on');
INSERT INTO colors VALUES (229,'Spring Green','4AA02C','on');
INSERT INTO colors VALUES (230,'Lime Green','41A317','on');
INSERT INTO colors VALUES (231,'Clover Green','3EA055','on');
INSERT INTO colors VALUES (232,'Green Snake','6CBB3C','on');
INSERT INTO colors VALUES (233,'Alien Green','6CC417','on');
INSERT INTO colors VALUES (234,'Green Apple','4CC417','on');
INSERT INTO colors VALUES (235,'Yellow Green','52D017','on');
INSERT INTO colors VALUES (236,'Kelly Green','4CC552','on');
INSERT INTO colors VALUES (237,'Zombie Green','54C571','on');
INSERT INTO colors VALUES (238,'Frog Green','99C68E','on');
INSERT INTO colors VALUES (239,'Green Peas','89C35C','on');
INSERT INTO colors VALUES (240,'Dollar Bill Green','85BB65','on');
INSERT INTO colors VALUES (241,'Dark Sea Green','8BB381','on');
INSERT INTO colors VALUES (242,'Iguana Green','9CB071','on');
INSERT INTO colors VALUES (243,'Avocado Green','B2C248','on');
INSERT INTO colors VALUES (244,'Pistachio Green','9DC209','on');
INSERT INTO colors VALUES (245,'Salad Green','A1C935','on');
INSERT INTO colors VALUES (246,'Hummingbird Green','7FE817','on');
INSERT INTO colors VALUES (247,'Nebula Green','59E817','on');
INSERT INTO colors VALUES (248,'Stoplight Go Green','57E964','on');
INSERT INTO colors VALUES (249,'Algae Green','64E986','on');
INSERT INTO colors VALUES (250,'Jade Green','5EFB6E','on');
INSERT INTO colors VALUES (251,'Emerald Green','5FFB17','on');
INSERT INTO colors VALUES (252,'Lawn Green','87F717','on');
INSERT INTO colors VALUES (253,'Chartreuse','8AFB17','on');
INSERT INTO colors VALUES (254,'Dragon Green','6AFB92','on');
INSERT INTO colors VALUES (255,'Mint green','98FF98','on');
INSERT INTO colors VALUES (256,'Green Thumb','B5EAAA','on');
INSERT INTO colors VALUES (257,'Light Jade','C3FDB8','on');
INSERT INTO colors VALUES (258,'Tea Green','CCFB5D','on');
INSERT INTO colors VALUES (259,'Green Yellow','B1FB17','on');
INSERT INTO colors VALUES (260,'Slime Green','BCE954','on');
INSERT INTO colors VALUES (261,'Goldenrod','EDDA74','on');
INSERT INTO colors VALUES (262,'Harvest Gold','EDE275','on');
INSERT INTO colors VALUES (263,'Sun Yellow','FFE87C','on');
INSERT INTO colors VALUES (264,'Yellow','FFFF00','on');
INSERT INTO colors VALUES (265,'Corn Yellow','FFF380','on');
INSERT INTO colors VALUES (266,'Parchment','FFFFC2','on');
INSERT INTO colors VALUES (267,'Cream','FFFFCC','on');
INSERT INTO colors VALUES (268,'Lemon Chiffon','FFF8C6','on');
INSERT INTO colors VALUES (269,'Cornsilk','FFF8DC','on');
INSERT INTO colors VALUES (270,'Beige','F5F5DC','on');
INSERT INTO colors VALUES (271,'Blonde','FBF6D9','on');
INSERT INTO colors VALUES (272,'Antique White','FAEBD7','on');
INSERT INTO colors VALUES (273,'Champagne','F7E7CE','on');
INSERT INTO colors VALUES (274,'Blanched Almond','FFEBCD','on');
INSERT INTO colors VALUES (275,'Vanilla','F3E5AB','on');
INSERT INTO colors VALUES (276,'Tan Brown','ECE5B6','on');
INSERT INTO colors VALUES (277,'Peach','FFE5B4','on');
INSERT INTO colors VALUES (278,'Mustard','FFDB58','on');
INSERT INTO colors VALUES (279,'Rubber Ducky Yellow','FFD801','on');
INSERT INTO colors VALUES (280,'Bright Gold','FDD017','on');
INSERT INTO colors VALUES (281,'Golden Brown','EAC117','on');
INSERT INTO colors VALUES (282,'Macaroni and Cheese','F2BB66','on');
INSERT INTO colors VALUES (283,'Saffron','FBB917','on');
INSERT INTO colors VALUES (284,'Beer','FBB117','on');
INSERT INTO colors VALUES (285,'Cantaloupe','FFA62F','on');
INSERT INTO colors VALUES (286,'Bee Yellow','E9AB17','on');
INSERT INTO colors VALUES (287,'Brown Sugar','E2A76F','on');
INSERT INTO colors VALUES (288,'BurlyWood','DEB887','on');
INSERT INTO colors VALUES (289,'Deep Peach','FFCBA4','on');
INSERT INTO colors VALUES (290,'Ginger Brown','C9BE62','on');
INSERT INTO colors VALUES (291,'School Bus Yellow','E8A317','on');
INSERT INTO colors VALUES (292,'Sandy Brown','EE9A4D','on');
INSERT INTO colors VALUES (293,'Fall Leaf Brown','C8B560','on');
INSERT INTO colors VALUES (294,'Orange Gold','D4A017','on');
INSERT INTO colors VALUES (295,'Sand','C2B280','on');
INSERT INTO colors VALUES (296,'Cookie Brown','C7A317','on');
INSERT INTO colors VALUES (297,'Caramel','C68E17','on');
INSERT INTO colors VALUES (298,'Brass','B5A642','on');
INSERT INTO colors VALUES (299,'Khaki','ADA96E','on');
INSERT INTO colors VALUES (300,'Camel Brown','C19A6B','on');
INSERT INTO colors VALUES (301,'Bronze','CD7F32','on');
INSERT INTO colors VALUES (302,'Tiger Orange','C88141','on');
INSERT INTO colors VALUES (303,'Cinnamon','C58917','on');
INSERT INTO colors VALUES (304,'Bullet Shell','AF9B60','on');
INSERT INTO colors VALUES (305,'Dark Goldenrod','AF7817','on');
INSERT INTO colors VALUES (306,'Copper','B87333','on');
INSERT INTO colors VALUES (307,'Wood','966F33','on');
INSERT INTO colors VALUES (308,'Oak Brown','806517','on');
INSERT INTO colors VALUES (309,'Moccasin','827839','on');
INSERT INTO colors VALUES (310,'Army Brown','827B60','on');
INSERT INTO colors VALUES (311,'Sandstone','786D5F','on');
INSERT INTO colors VALUES (312,'Mocha','493D26','on');
INSERT INTO colors VALUES (313,'Taupe','483C32','on');
INSERT INTO colors VALUES (314,'Coffee','6F4E37','on');
INSERT INTO colors VALUES (315,'Brown Bear','835C3B','on');
INSERT INTO colors VALUES (316,'Red Dirt','7F5217','on');
INSERT INTO colors VALUES (317,'Sepia','7F462C','on');
INSERT INTO colors VALUES (318,'Orange Salmon','C47451','on');
INSERT INTO colors VALUES (319,'Rust','C36241','on');
INSERT INTO colors VALUES (320,'Red Fox','C35817','on');
INSERT INTO colors VALUES (321,'Chocolate','C85A17','on');
INSERT INTO colors VALUES (322,'Sedona','CC6600','on');
INSERT INTO colors VALUES (323,'Papaya Orange','E56717','on');
INSERT INTO colors VALUES (324,'Halloween Orange','E66C2C','on');
INSERT INTO colors VALUES (325,'Pumpkin Orange','F87217','on');
INSERT INTO colors VALUES (326,'Construction Cone Orange','F87431','on');
INSERT INTO colors VALUES (327,'Sunrise Orange','E67451','on');
INSERT INTO colors VALUES (328,'Mango Orange','FF8040','on');
INSERT INTO colors VALUES (329,'Dark Orange','F88017','on');
INSERT INTO colors VALUES (330,'Coral','FF7F50','on');
INSERT INTO colors VALUES (331,'Basket Ball Orange','F88158','on');
INSERT INTO colors VALUES (332,'Light Salmon','F9966B','on');
INSERT INTO colors VALUES (333,'Tangerine','E78A61','on');
INSERT INTO colors VALUES (334,'Dark Salmon','E18B6B','on');
INSERT INTO colors VALUES (335,'Light Coral','E77471','on');
INSERT INTO colors VALUES (336,'Bean Red','F75D59','on');
INSERT INTO colors VALUES (337,'Valentine Red','E55451','on');
INSERT INTO colors VALUES (338,'Shocking Orange','E55B3C','on');
INSERT INTO colors VALUES (339,'Scarlet','FF2400','on');
INSERT INTO colors VALUES (340,'Ruby Red','F62217','on');
INSERT INTO colors VALUES (341,'Ferrari Red','F70D1A','on');
INSERT INTO colors VALUES (342,'Fire Engine Red','F62817','on');
INSERT INTO colors VALUES (343,'Lava Red','E42217','on');
INSERT INTO colors VALUES (344,'Love Red','E41B17','on');
INSERT INTO colors VALUES (345,'Grapefruit','DC381F','on');
INSERT INTO colors VALUES (346,'Chestnut Red','C34A2C','on');
INSERT INTO colors VALUES (347,'Cherry Red','C24641','on');
INSERT INTO colors VALUES (348,'Mahogany','C04000','on');
INSERT INTO colors VALUES (349,'Chilli Pepper','C11B17','on');
INSERT INTO colors VALUES (350,'Cranberry','9F000F','on');
INSERT INTO colors VALUES (351,'Red Wine','990012','on');
INSERT INTO colors VALUES (352,'Burgundy','8C001A','on');
INSERT INTO colors VALUES (353,'Chestnut','954535','on');
INSERT INTO colors VALUES (354,'Blood Red','7E3517','on');
INSERT INTO colors VALUES (355,'Sienna','8A4117','on');
INSERT INTO colors VALUES (356,'Sangria','7E3817','on');
INSERT INTO colors VALUES (357,'Firebrick','800517','on');
INSERT INTO colors VALUES (358,'Maroon','810541','on');
INSERT INTO colors VALUES (359,'Plum Pie','7D0541','on');
INSERT INTO colors VALUES (360,'Velvet Maroon','7E354D','on');
INSERT INTO colors VALUES (361,'Plum Velvet','7D0552','on');
INSERT INTO colors VALUES (362,'Rosy Finch','7F4E52','on');
INSERT INTO colors VALUES (363,'Puce','7F5A58','on');
INSERT INTO colors VALUES (364,'Dull Purple','7F525D','on');
INSERT INTO colors VALUES (365,'Rosy Brown','B38481','on');
INSERT INTO colors VALUES (366,'Khaki Rose','C5908E','on');
INSERT INTO colors VALUES (367,'Pink Bow','C48189','on');
INSERT INTO colors VALUES (368,'Lipstick Pink','C48793','on');
INSERT INTO colors VALUES (369,'Rose','E8ADAA','on');
INSERT INTO colors VALUES (370,'Desert Sand','EDC9AF','on');
INSERT INTO colors VALUES (371,'Pig Pink','FDD7E4','on');
INSERT INTO colors VALUES (372,'Cotton Candy','FCDFFF','on');
INSERT INTO colors VALUES (373,'Pink Bubblegum','FFDFDD','on');
INSERT INTO colors VALUES (374,'Misty Rose','FBBBB9','on');
INSERT INTO colors VALUES (375,'Pink','FAAFBE','on');
INSERT INTO colors VALUES (376,'Light Pink','FAAFBA','on');
INSERT INTO colors VALUES (377,'Flamingo Pink','F9A7B0','on');
INSERT INTO colors VALUES (378,'Pink Rose','E7A1B0','on');
INSERT INTO colors VALUES (379,'Pink Daisy','E799A3','on');
INSERT INTO colors VALUES (380,'Cadillac Pink','E38AAE','on');
INSERT INTO colors VALUES (381,'Carnation Pink','F778A1','on');
INSERT INTO colors VALUES (382,'Blush Red','E56E94','on');
INSERT INTO colors VALUES (383,'Hot Pink','F660AB','on');
INSERT INTO colors VALUES (384,'Watermelon Pink','FC6C85','on');
INSERT INTO colors VALUES (385,'Violet Red','F6358A','on');
INSERT INTO colors VALUES (386,'Deep Pink','F52887','on');
INSERT INTO colors VALUES (387,'Pink Cupcake','E45E9D','on');
INSERT INTO colors VALUES (388,'Pink Lemonade','E4287C','on');
INSERT INTO colors VALUES (389,'Neon Pink','F535AA','on');
INSERT INTO colors VALUES (390,'Dimorphotheca Magenta','E3319D','on');
INSERT INTO colors VALUES (391,'Bright Neon Pink','F433FF','on');
INSERT INTO colors VALUES (392,'Pale Violet Red','D16587','on');
INSERT INTO colors VALUES (393,'Tulip Pink','C25A7C','on');
INSERT INTO colors VALUES (394,'Medium Violet Red','CA226B','on');
INSERT INTO colors VALUES (395,'Rogue Pink','C12869','on');
INSERT INTO colors VALUES (396,'Burnt Pink','C12267','on');
INSERT INTO colors VALUES (397,'Bashful Pink','C25283','on');
INSERT INTO colors VALUES (398,'Carnation Pink','C12283','on');
INSERT INTO colors VALUES (399,'Plum','B93B8F','on');
INSERT INTO colors VALUES (400,'Viola Purple','7E587E','on');
INSERT INTO colors VALUES (401,'Purple Iris','571B7E','on');
INSERT INTO colors VALUES (402,'Plum Purple','583759','on');
INSERT INTO colors VALUES (403,'Indigo','4B0082','on');
INSERT INTO colors VALUES (404,'Purple Monster','461B7E','on');
INSERT INTO colors VALUES (405,'Purple Haze','4E387E','on');
INSERT INTO colors VALUES (406,'Eggplant','614051','on');
INSERT INTO colors VALUES (407,'Grape','5E5A80','on');
INSERT INTO colors VALUES (408,'Purple Jam','6A287E','on');
INSERT INTO colors VALUES (409,'Dark Orchid','7D1B7E','on');
INSERT INTO colors VALUES (410,'Purple Flower','A74AC7','on');
INSERT INTO colors VALUES (411,'Medium Orchid','B048B5','on');
INSERT INTO colors VALUES (412,'Purple Amethyst','6C2DC7','on');
INSERT INTO colors VALUES (413,'Dark Violet','842DCE','on');
INSERT INTO colors VALUES (414,'Violet','8D38C9','on');
INSERT INTO colors VALUES (415,'Purple Sage Bush','7A5DC7','on');
INSERT INTO colors VALUES (416,'Lovely Purple','7F38EC','on');
INSERT INTO colors VALUES (417,'Purple','8E35EF','on');
INSERT INTO colors VALUES (418,'Aztech Purple','893BFF','on');
INSERT INTO colors VALUES (419,'Medium Purple','8467D7','on');
INSERT INTO colors VALUES (420,'Jasmine Purple','A23BEC','on');
INSERT INTO colors VALUES (421,'Purple Daffodil','B041FF','on');
INSERT INTO colors VALUES (422,'Tyrian Purple','C45AEC','on');
INSERT INTO colors VALUES (423,'Crocus Purple','9172EC','on');
INSERT INTO colors VALUES (424,'Purple Mimosa','9E7BFF','on');
INSERT INTO colors VALUES (425,'Heliotrope Purple','D462FF','on');
INSERT INTO colors VALUES (426,'Crimson','E238EC','on');
INSERT INTO colors VALUES (427,'Purple Dragon','C38EC7','on');
INSERT INTO colors VALUES (428,'Lilac','C8A2C8','on');
INSERT INTO colors VALUES (429,'Blush Pink','E6A9EC','on');
INSERT INTO colors VALUES (430,'Mauve','E0B0FF','on');
INSERT INTO colors VALUES (431,'Wisteria Purple','C6AEC7','on');
INSERT INTO colors VALUES (432,'Blossom Pink','F9B7FF','on');
INSERT INTO colors VALUES (433,'Thistle','D2B9D3','on');
INSERT INTO colors VALUES (434,'Periwinkle','E9CFEC','on');
INSERT INTO colors VALUES (435,'Lavender Pinocchio','EBDDE2','on');
INSERT INTO colors VALUES (436,'Lavender Blue','E3E4FA','on');
INSERT INTO colors VALUES (437,'Pearl','FDEEF4','on');
INSERT INTO colors VALUES (438,'SeaShell','FFF5EE','on');
INSERT INTO colors VALUES (439,'Milk White','FEFCFF','on');

--
-- Table structure for table `data_input`
--

CREATE TABLE data_input (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(200) NOT NULL default '',
  input_string varchar(512) default NULL,
  type_id tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY `name_type_id` (`name`(171), `type_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_input`
--

INSERT INTO data_input VALUES (1,'3eb92bb845b9660a7445cf9740726522','Get SNMP Data','',2);
INSERT INTO data_input VALUES (2,'bf566c869ac6443b0c75d1c32b5a350e','Get SNMP Data (Indexed)','',3);
INSERT INTO data_input VALUES (3,'274f4685461170b9eb1b98d22567ab5e','Unix - Get Free Disk Space','<path_cacti>/scripts/diskfree.sh <partition>',1);
INSERT INTO data_input VALUES (4,'95ed0993eb3095f9920d431ac80f4231','Unix - Get Load Average','perl <path_cacti>/scripts/loadavg_multi.pl',1);
INSERT INTO data_input VALUES (5,'79a284e136bb6b061c6f96ec219ac448','Unix - Get Logged In Users','perl <path_cacti>/scripts/unix_users.pl <username>',1);
INSERT INTO data_input VALUES (6,'362e6d4768937c4f899dd21b91ef0ff8','Linux - Get Memory Usage','perl <path_cacti>/scripts/linux_memory.pl <grepstr>',1);
INSERT INTO data_input VALUES (7,'a637359e0a4287ba43048a5fdf202066','Unix - Get System Processes','perl <path_cacti>/scripts/unix_processes.pl',1);
INSERT INTO data_input VALUES (8,'47d6bfe8be57a45171afd678920bd399','Unix - Get TCP Connections','perl <path_cacti>/scripts/unix_tcp_connections.pl <grepstr>',1);
INSERT INTO data_input VALUES (9,'cc948e4de13f32b6aea45abaadd287a3','Unix - Get Web Hits','perl <path_cacti>/scripts/webhits.pl <log_path>',1);
INSERT INTO data_input VALUES (10,'8bd153aeb06e3ff89efc73f35849a7a0','Unix - Ping Host','perl <path_cacti>/scripts/ping.pl <ip>',1);
INSERT INTO data_input VALUES (11,'80e9e4c4191a5da189ae26d0e237f015','Get Script Data (Indexed)','',4);
INSERT INTO data_input VALUES (12,'332111d8b54ac8ce939af87a7eac0c06','Get Script Server Data (Indexed)','',6);

--
-- Table structure for table `data_input_data`
--

CREATE TABLE data_input_data (
  data_input_field_id mediumint(8) unsigned NOT NULL default '0',
  data_template_data_id int(10) unsigned NOT NULL default '0',
  t_value char(2) default NULL,
  value text,
  PRIMARY KEY (data_input_field_id,data_template_data_id),
  KEY data_template_data_id (data_template_data_id),
  KEY t_value (t_value)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_input_data`
--

INSERT INTO `data_input_data` VALUES (1,4,'','');
INSERT INTO `data_input_data` VALUES (1,5,'','');
INSERT INTO `data_input_data` VALUES (1,6,'','');
INSERT INTO `data_input_data` VALUES (1,19,'','');
INSERT INTO `data_input_data` VALUES (1,20,'','');
INSERT INTO `data_input_data` VALUES (1,22,'','');
INSERT INTO `data_input_data` VALUES (1,23,'','');
INSERT INTO `data_input_data` VALUES (1,24,'','');
INSERT INTO `data_input_data` VALUES (1,25,'','');
INSERT INTO `data_input_data` VALUES (1,26,'','');
INSERT INTO `data_input_data` VALUES (1,27,'','');
INSERT INTO `data_input_data` VALUES (1,30,'','');
INSERT INTO `data_input_data` VALUES (1,31,'','');
INSERT INTO `data_input_data` VALUES (1,32,'','');
INSERT INTO `data_input_data` VALUES (1,33,'','');
INSERT INTO `data_input_data` VALUES (1,34,'','');
INSERT INTO `data_input_data` VALUES (1,58,'','');
INSERT INTO `data_input_data` VALUES (1,59,'','');
INSERT INTO `data_input_data` VALUES (1,68,'','');
INSERT INTO `data_input_data` VALUES (2,4,'','');
INSERT INTO `data_input_data` VALUES (2,5,'','');
INSERT INTO `data_input_data` VALUES (2,6,'','');
INSERT INTO `data_input_data` VALUES (2,19,'','');
INSERT INTO `data_input_data` VALUES (2,20,'','');
INSERT INTO `data_input_data` VALUES (2,22,'','');
INSERT INTO `data_input_data` VALUES (2,23,'','');
INSERT INTO `data_input_data` VALUES (2,24,'','');
INSERT INTO `data_input_data` VALUES (2,25,'','');
INSERT INTO `data_input_data` VALUES (2,26,'','');
INSERT INTO `data_input_data` VALUES (2,27,'','');
INSERT INTO `data_input_data` VALUES (2,30,'','');
INSERT INTO `data_input_data` VALUES (2,31,'','');
INSERT INTO `data_input_data` VALUES (2,32,'','');
INSERT INTO `data_input_data` VALUES (2,33,'','');
INSERT INTO `data_input_data` VALUES (2,34,'','');
INSERT INTO `data_input_data` VALUES (2,58,'','');
INSERT INTO `data_input_data` VALUES (2,59,'','');
INSERT INTO `data_input_data` VALUES (2,68,'','');
INSERT INTO `data_input_data` VALUES (3,4,'','');
INSERT INTO `data_input_data` VALUES (3,5,'','');
INSERT INTO `data_input_data` VALUES (3,6,'','');
INSERT INTO `data_input_data` VALUES (3,19,'','');
INSERT INTO `data_input_data` VALUES (3,20,'','');
INSERT INTO `data_input_data` VALUES (3,22,'','');
INSERT INTO `data_input_data` VALUES (3,23,'','');
INSERT INTO `data_input_data` VALUES (3,24,'','');
INSERT INTO `data_input_data` VALUES (3,25,'','');
INSERT INTO `data_input_data` VALUES (3,26,'','');
INSERT INTO `data_input_data` VALUES (3,27,'','');
INSERT INTO `data_input_data` VALUES (3,30,'','');

--
-- Table structure for table `data_input_fields`
--

CREATE TABLE data_input_fields (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  data_input_id mediumint(8) unsigned NOT NULL default '0',
  name varchar(200) NOT NULL default '',
  data_name varchar(50) NOT NULL default '',
  input_output char(3) NOT NULL default '',
  update_rra char(2) default '0',
  sequence smallint(5) NOT NULL default '0',
  type_code varchar(40) default NULL,
  regexp_match varchar(200) default NULL,
  allow_nulls char(2) default NULL,
  PRIMARY KEY (id),
  KEY data_input_id (data_input_id),
  KEY input_output (input_output),
  KEY type_code_data_input_id (type_code, data_input_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_input_fields`
--

INSERT INTO `data_input_fields` VALUES (1,'92f5906c8dc0f964b41f4253df582c38',1,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO `data_input_fields` VALUES (2,'32285d5bf16e56c478f5e83f32cda9ef',1,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO `data_input_fields` VALUES (3,'ad14ac90641aed388139f6ba86a2e48b',1,'SNMP Username','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO `data_input_fields` VALUES (4,'9c55a74bd571b4f00a96fd4b793278c6',1,'SNMP Password','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO `data_input_fields` VALUES (5,'012ccb1d3687d3edb29c002ea66e72da',1,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','on');
INSERT INTO `data_input_fields` VALUES (6,'4276a5ec6e3fe33995129041b1909762',1,'OID','oid','in','',0,'snmp_oid','','');
INSERT INTO `data_input_fields` VALUES (7,'617cdc8a230615e59f06f361ef6e7728',2,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO `data_input_fields` VALUES (8,'acb449d1451e8a2a655c2c99d31142c7',2,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO `data_input_fields` VALUES (9,'f4facc5e2ca7ebee621f09bc6d9fc792',2,'SNMP Username (v3)','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO `data_input_fields` VALUES (10,'1cc1493a6781af2c478fa4de971531cf',2,'SNMP Password (v3)','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO `data_input_fields` VALUES (11,'b5c23f246559df38662c255f4aa21d6b',2,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','');
INSERT INTO `data_input_fields` VALUES (12,'6027a919c7c7731fbe095b6f53ab127b',2,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` VALUES (13,'cbbe5c1ddfb264a6e5d509ce1c78c95f',2,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` VALUES (14,'e6deda7be0f391399c5130e7c4a48b28',2,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` VALUES (15,'edfd72783ad02df128ff82fc9324b4b9',3,'Disk Partition','partition','in','',1,'','','');
INSERT INTO `data_input_fields` VALUES (16,'8b75fb61d288f0b5fc0bd3056af3689b',3,'Kilobytes Free','kilobytes','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (17,'363588d49b263d30aecb683c52774f39',4,'1 Minute Average','1min','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (18,'ad139a9e1d69881da36fca07889abf58',4,'5 Minute Average','5min','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (19,'5db9fee64824c08258c7ff6f8bc53337',4,'10 Minute Average','10min','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (20,'c0cfd0beae5e79927c5a360076706820',5,'Username (Optional)','username','in','',1,'','','on');
INSERT INTO `data_input_fields` VALUES (21,'52c58ad414d9a2a83b00a7a51be75a53',5,'Logged In Users','users','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (22,'05eb5d710f0814871b8515845521f8d7',6,'Grep String','grepstr','in','',1,'','','');
INSERT INTO `data_input_fields` VALUES (23,'86cb1cbfde66279dbc7f1144f43a3219',6,'Result (in Kilobytes)','kilobytes','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (24,'d5a8dd5fbe6a5af11667c0039af41386',7,'Number of Processes','proc','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (25,'8848cdcae831595951a3f6af04eec93b',8,'Grep String','grepstr','in','',1,'','','on');
INSERT INTO `data_input_fields` VALUES (26,'3d1288d33008430ce354e8b9c162f7ff',8,'Connections','connections','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (27,'c6af570bb2ed9c84abf32033702e2860',9,'(Optional) Log Path','log_path','in','',1,'','','on');
INSERT INTO `data_input_fields` VALUES (28,'f9389860f5c5340c9b27fca0b4ee5e71',9,'Web Hits','webhits','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (29,'5fbadb91ad66f203463c1187fe7bd9d5',10,'IP Address','ip','in','',1,'hostname','','');
INSERT INTO `data_input_fields` VALUES (30,'6ac4330d123c69067d36a933d105e89a',10,'Milliseconds','out_ms','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (31,'d39556ecad6166701bfb0e28c5a11108',11,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` VALUES (32,'3b7caa46eb809fc238de6ef18b6e10d5',11,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` VALUES (33,'74af2e42dc12956c4817c2ef5d9983f9',11,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` VALUES (34,'8ae57f09f787656bf4ac541e8bd12537',11,'Output Value','output','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (35,'172b4b0eacee4948c6479f587b62e512',12,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` VALUES (36,'30fb5d5bcf3d66bb5abe88596f357c26',12,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` VALUES (37,'31112c85ae4ff821d3b288336288818c',12,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` VALUES (38,'5be8fa85472d89c621790b43510b5043',12,'Output Value','output','out','on',0,'','','');
INSERT INTO `data_input_fields` VALUES (39,'c1f36ee60c3dc98945556d57f26e475b',2,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO `data_input_fields` VALUES (40,'fc64b99742ec417cc424dbf8c7692d36',1,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO `data_input_fields` VALUES (41,'20832ce12f099c8e54140793a091af90',1,'SNMP Authentication Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO `data_input_fields` VALUES (42,'c60c9aac1e1b3555ea0620b8bbfd82cb',1,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO `data_input_fields` VALUES (43,'feda162701240101bc74148415ef415a',1,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');
INSERT INTO `data_input_fields` VALUES (44,'2cf7129ad3ff819a7a7ac189bee48ce8',2,'SNMP Authentication Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO `data_input_fields` VALUES (45,'6b13ac0a0194e171d241d4b06f913158',2,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO `data_input_fields` VALUES (46,'3a33d4fc65b8329ab2ac46a36da26b72',2,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');

--
-- Table structure for table `data_local`
--

CREATE TABLE data_local (
  id int(10) unsigned NOT NULL auto_increment,
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) NOT NULL default '0',
  snmp_index varchar(255) NOT NULL default '',
  orphan tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY data_template_id (data_template_id),
  KEY snmp_query_id (snmp_query_id),
  KEY snmp_index (snmp_index),
  KEY host_id_snmp_query_id (host_id, snmp_query_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_local`
--

--
-- Table structure for table `data_debug`
--

CREATE TABLE `data_debug` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `started` int(10) unsigned NOT NULL default '0',
  `done` int(10) unsigned NOT NULL default '0',
  `user` int(10) unsigned NOT NULL default '0',
  `datasource` int(10) unsigned NOT NULL default '0',
  `info` text NOT NULL,
  `issue` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `done` (`done`),
  KEY `datasource` (`datasource`),
  KEY `started` (`started`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Datasource Debugger Information';

--
-- Dumping data for table `data_debug`
--

--
-- Table structure for table `data_source_profiles`
--

CREATE TABLE `data_source_profiles` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `step` int(10) unsigned NOT NULL DEFAULT '300',
  `heartbeat` int(10) unsigned NOT NULL DEFAULT '600',
  `x_files_factor` double DEFAULT '0.5',
  `default` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores Data Source Profiles';

--
-- Dumping data for table `data_source_profiles`
--

INSERT INTO `data_source_profiles` VALUES (1,'d62c52891f4f9688729a5bc9fad91b18','5 Minute Collection',300,600,0.5,'on');
INSERT INTO `data_source_profiles` VALUES (2,'c0dd0e46b9ca268e7ed4162d329f9215','30 Second Collection',30,1200,0.5,'');
INSERT INTO `data_source_profiles` VALUES (3,'66d35da8f75c912ede3dbe901fedcae0','1 Minute Collection',60,600,0.5,'');

--
-- Table structure for table `data_source_profiles_cf`
--

CREATE TABLE `data_source_profiles_cf` (
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `consolidation_function_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`data_source_profile_id`,`consolidation_function_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Maps the Data Source Profile Consolidation Functions';

--
-- Dumping data for table `data_source_profiles_cf`
--

INSERT INTO `data_source_profiles_cf` VALUES (1,1);
INSERT INTO `data_source_profiles_cf` VALUES (1,2);
INSERT INTO `data_source_profiles_cf` VALUES (1,3);
INSERT INTO `data_source_profiles_cf` VALUES (1,4);
INSERT INTO `data_source_profiles_cf` VALUES (2,1);
INSERT INTO `data_source_profiles_cf` VALUES (2,2);
INSERT INTO `data_source_profiles_cf` VALUES (2,3);
INSERT INTO `data_source_profiles_cf` VALUES (2,4);
INSERT INTO `data_source_profiles_cf` VALUES (3,1);
INSERT INTO `data_source_profiles_cf` VALUES (3,2);
INSERT INTO `data_source_profiles_cf` VALUES (3,3);
INSERT INTO `data_source_profiles_cf` VALUES (3,4);

--
-- Table structure for table `data_source_profiles_rra`
--

CREATE TABLE `data_source_profiles_rra` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `steps` int(10) unsigned DEFAULT '1',
  `rows` int(10) unsigned NOT NULL DEFAULT '700',
  `timespan` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `data_source_profile_id` (`data_source_profile_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores RRA Definitions for Data Source Profiles';

--
-- Dumping data for table `data_source_profiles_rra`
--

INSERT INTO `data_source_profiles_rra` VALUES (1,1,'Daily (5 Minute Average)',1,600,86400);
INSERT INTO `data_source_profiles_rra` VALUES (2,1,'Weekly (30 Minute Average)',6,700,604800);
INSERT INTO `data_source_profiles_rra` VALUES (3,1,'Monthly (2 Hour Average)',24,775,2618784);
INSERT INTO `data_source_profiles_rra` VALUES (4,1,'Yearly (1 Day Average)',288,797,31536000);
INSERT INTO `data_source_profiles_rra` VALUES (5,2,'Daily (30 Second Average)',1,2900,86400);
INSERT INTO `data_source_profiles_rra` VALUES (6,2,'Weekly (15 Minute Average)',30,1346,604800);
INSERT INTO `data_source_profiles_rra` VALUES (7,2,'Monthly (1 Hour Average)',120,1445,2618784);
INSERT INTO `data_source_profiles_rra` VALUES (8,2,'Yearly (4 Hour Average)',480,4380,31536000);
INSERT INTO `data_source_profiles_rra` VALUES (9,3,'Daily (1 Minute Average)',1,2900,86400);
INSERT INTO `data_source_profiles_rra` VALUES (10,3,'Weekly (15 Minute Average)',15,1440,604800);
INSERT INTO `data_source_profiles_rra` VALUES (11,3,'Monthly (1 Hour Average)',60,8784,2618784);
INSERT INTO `data_source_profiles_rra` VALUES (12,3,'Yearly (12 Hour Average)',720,7305,31536000);

--
-- Table structure for table `data_source_purge_action`
--

CREATE TABLE `data_source_purge_action` (
  `id` integer UNSIGNED auto_increment,
  `name` varchar(128) NOT NULL default '',
  `local_data_id` int(10) unsigned NOT NULL default '0',
  `action` tinyint(3) unsigned NOT NULL default 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY name (`name`))
  ENGINE=InnoDB
  ROW_FORMAT=Dynamic
  COMMENT='RRD Cleaner File Actions';

--
-- Table structure for table `data_source_purge_temp`
--

CREATE TABLE `data_source_purge_temp` (
  `id` integer UNSIGNED auto_increment,
  `name_cache` varchar(255) NOT NULL default '',
  `local_data_id` int(10) unsigned NOT NULL default '0',
  `name` varchar(128) NOT NULL default '',
  `size` integer UNSIGNED NOT NULL default '0',
  `last_mod` TIMESTAMP NOT NULL default '0000-00-00 00:00:00',
  `in_cacti` tinyint(3) unsigned NOT NULL default '0',
  `data_template_id` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY name (`name`),
  KEY local_data_id (`local_data_id`),
  KEY in_cacti (`in_cacti`),
  KEY data_template_id (`data_template_id`))
  ENGINE=InnoDB
  ROW_FORMAT=Dynamic
  COMMENT='RRD Cleaner File Repository';

--
-- Table structure for table `data_source_stats_command_cache`
--

CREATE TABLE `data_source_stats_command_cache` (
  `local_data_id` int(10) unsigned NOT NULL DEFAULT 0,
  `stats_command` BLOB NOT NULL DEFAULT '',
  PRIMARY KEY (`local_data_id`)) 
  ENGINE=InnoDB 
  ROW_FORMAT=DYNAMIC 
  COMMENT='Holds the RRDfile Stats Commands';

--
-- Table structure for table `data_source_stats_daily`
--

CREATE TABLE `data_source_stats_daily` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `cf` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  `p95n` double DEFAULT NULL,
  `p90n` double DEFAULT NULL,
  `p75n` double DEFAULT NULL,
  `p50n` double DEFAULT NULL,
  `p25n` double DEFAULT NULL,
  `sum` double DEFAULT NULL,
  `stddev` double DEFAULT NULL,
  `lslslope` double DEFAULT NULL,
  `lslint` double DEFAULT NULL,
  `lslcorrel` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`cf`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `data_source_stats_hourly`
--

CREATE TABLE `data_source_stats_hourly` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `cf` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`cf`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `data_source_stats_hourly_cache`
--

CREATE TABLE `data_source_stats_hourly_cache` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `value` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`time`,`rrd_name`),
  KEY `time` USING BTREE (`time`)
  ) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `data_source_stats_hourly_last`
--

CREATE TABLE `data_source_stats_hourly_last` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `value` DOUBLE DEFAULT NULL,
  `calculated` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=MEMORY;

--
-- Table structure for table `data_source_stats_monthly`
--

CREATE TABLE `data_source_stats_monthly` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `cf` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  `p95n` double DEFAULT NULL,
  `p90n` double DEFAULT NULL,
  `p75n` double DEFAULT NULL,
  `p50n` double DEFAULT NULL,
  `p25n` double DEFAULT NULL,
  `sum` double DEFAULT NULL,
  `stddev` double DEFAULT NULL,
  `lslslope` double DEFAULT NULL,
  `lslint` double DEFAULT NULL,
  `lslcorrel` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`cf`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `data_source_stats_weekly`
--

CREATE TABLE `data_source_stats_weekly` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `cf` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  `p95n` double DEFAULT NULL,
  `p90n` double DEFAULT NULL,
  `p75n` double DEFAULT NULL,
  `p50n` double DEFAULT NULL,
  `p25n` double DEFAULT NULL,
  `sum` double DEFAULT NULL,
  `stddev` double DEFAULT NULL,
  `lslslope` double DEFAULT NULL,
  `lslint` double DEFAULT NULL,
  `lslcorrel` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`cf`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `data_source_stats_yearly`
--

CREATE TABLE `data_source_stats_yearly` (
  `local_data_id` int(10) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `cf` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  `p95n` double DEFAULT NULL,
  `p90n` double DEFAULT NULL,
  `p75n` double DEFAULT NULL,
  `p50n` double DEFAULT NULL,
  `p25n` double DEFAULT NULL,
  `sum` double DEFAULT NULL,
  `stddev` double DEFAULT NULL,
  `lslslope` double DEFAULT NULL,
  `lslint` double DEFAULT NULL,
  `lslcorrel` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`cf`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `data_template`
--

CREATE TABLE data_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(150) NOT NULL default '',
  PRIMARY KEY (id),
  KEY `name` (`name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_template`
--

--
-- Table structure for table `data_template_data`
--

CREATE TABLE data_template_data (
  id int(10) unsigned NOT NULL auto_increment,
  local_data_template_data_id int(10) unsigned NOT NULL default '0',
  local_data_id int(10) unsigned NOT NULL default '0',
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  data_input_id mediumint(8) unsigned NOT NULL default '0',
  t_name char(2) default NULL,
  name varchar(250) NOT NULL default '',
  name_cache varchar(255) NOT NULL default '',
  data_source_path varchar(255) default '',
  t_active char(2) default '',
  active char(2) default NULL,
  t_rrd_step char(2) default '',
  rrd_step mediumint(8) unsigned NOT NULL default '0',
  t_data_source_profile_id char(2) default '',
  data_source_profile_id mediumint(8) unsigned NOT NULL default '1',
  PRIMARY KEY (id),
  KEY local_data_id (local_data_id),
  KEY data_template_id (data_template_id),
  KEY data_input_id (data_input_id),
  KEY name_cache (name_cache)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_template_data`
--

--
-- Table structure for table `data_template_rrd`
--

CREATE TABLE data_template_rrd (
  id int(10) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  local_data_template_rrd_id int(10) unsigned NOT NULL default '0',
  local_data_id int(10) unsigned NOT NULL default '0',
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  t_rrd_maximum char(2) default NULL,
  rrd_maximum varchar(20) NOT NULL default '0',
  t_rrd_minimum char(2) default NULL,
  rrd_minimum varchar(20) NOT NULL default '0',
  t_rrd_heartbeat char(2) default NULL,
  rrd_heartbeat mediumint(6) NOT NULL default '0',
  t_data_source_type_id char(2) default NULL,
  data_source_type_id smallint(5) NOT NULL default '0',
  t_data_source_name char(2) default NULL,
  data_source_name varchar(19) NOT NULL default '',
  t_data_input_field_id char(2) default NULL,
  data_input_field_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  UNIQUE KEY `duplicate_dsname_contraint` (`local_data_id`,`data_source_name`,`data_template_id`),
  KEY data_template_id (data_template_id),
  KEY local_data_template_rrd_id (local_data_template_rrd_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `data_template_rrd`
--

CREATE TABLE external_links (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  sortorder int(10) unsigned NOT NULL DEFAULT '0',
  enabled char(2) DEFAULT 'on',
  contentfile varchar(255) NOT NULL default '',
  title varchar(20) NOT NULL default '',
  style varchar(10) NOT NULL DEFAULT '',
  extendedstyle varchar(50) NOT NULL DEFAULT '',
  refresh int unsigned default NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Contains external links that are embedded into Cacti';

--
-- Table structure for table `graph_local`
--

CREATE TABLE graph_local (
  id int(10) unsigned NOT NULL auto_increment,
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) NOT NULL default '0',
  snmp_query_graph_id mediumint(8) NOT NULL default '0',
  snmp_index varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY host_id (host_id),
  KEY graph_template_id (graph_template_id),
  KEY snmp_query_id (snmp_query_id),
  KEY snmp_query_graph_id (snmp_query_graph_id),
  KEY snmp_index (snmp_index)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Creates a relationship for each item in a custom graph.';

--
-- Dumping data for table `graph_local`
--

--
-- Table structure for table `graph_template_input`
--

CREATE TABLE graph_template_input (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  description text,
  column_name varchar(50) NOT NULL default '',
  PRIMARY KEY (id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores the names for graph item input groups.';

--
-- Dumping data for table `graph_template_input`
--

--
-- Table structure for table `graph_template_input_defs`
--

CREATE TABLE graph_template_input_defs (
  graph_template_input_id int(10) unsigned NOT NULL default '0',
  graph_template_item_id int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (graph_template_input_id,graph_template_item_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores the relationship for what graph items are associated';

--
-- Dumping data for table `graph_template_input_defs`
--

--
-- Table structure for table `graph_templates`
--

CREATE TABLE graph_templates (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `hash` char(32) NOT NULL default '',
  `name` char(255) NOT NULL default '',
  `multiple` char(2) NOT NULL default '',
  `test_source` char(2) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `multiple_name` (`multiple`, `name`),
  KEY `name` (`name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Contains each graph template name.';

--
-- Dumping data for table `graph_templates`
--

--
-- Table structure for table `graph_templates_gprint`
--

CREATE TABLE graph_templates_gprint (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  gprint_text varchar(255) default NULL,
  PRIMARY KEY (id),
  KEY `name` (`name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `graph_templates_gprint`
--

INSERT INTO graph_templates_gprint VALUES (2,'e9c43831e54eca8069317a2ce8c6f751','Normal','%8.2lf %s');
INSERT INTO graph_templates_gprint VALUES (3,'19414480d6897c8731c7dc6c5310653e','Exact Numbers','%8.0lf');
INSERT INTO graph_templates_gprint VALUES (4,'304a778405392f878a6db435afffc1e9','Load Average','%8.2lf');

--
-- Table structure for table `graph_templates_graph`
--

CREATE TABLE graph_templates_graph (
  id int(10) unsigned NOT NULL auto_increment,
  local_graph_template_graph_id int(10) unsigned NOT NULL default '0',
  local_graph_id int(10) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  t_image_format_id char(2) default '',
  image_format_id tinyint(3) unsigned NOT NULL default '0',
  t_title char(2) default '',
  title varchar(255) NOT NULL default '',
  title_cache varchar(255) NOT NULL default '',
  t_height char(2) default '',
  height mediumint(8) NOT NULL default '0',
  t_width char(2) default '',
  width mediumint(8) NOT NULL default '0',
  t_upper_limit char(2) default '',
  upper_limit varchar(20) NOT NULL default '0',
  t_lower_limit char(2) default '',
  lower_limit varchar(20) NOT NULL default '0',
  t_vertical_label char(2) default '',
  vertical_label varchar(200) default NULL,
  t_slope_mode char(2) default '',
  slope_mode char(2) default 'on',
  t_auto_scale char(2) default '',
  auto_scale char(2) default NULL,
  t_auto_scale_opts char(2) default '',
  auto_scale_opts tinyint(3) unsigned NOT NULL default '0',
  t_auto_scale_log char(2) default '',
  auto_scale_log char(2) default NULL,
  t_scale_log_units char(2) default '',
  scale_log_units char(2) default NULL,
  t_auto_scale_rigid char(2) default '',
  auto_scale_rigid char(2) default NULL,
  t_auto_padding char(2) default '',
  auto_padding char(2) default NULL,
  t_base_value char(2) default '',
  base_value mediumint(8) NOT NULL default '0',
  `t_grouping` char(2) default '',
  `grouping` char(2) NOT NULL default '',
  t_unit_value char(2) default '',
  unit_value varchar(20) default NULL,
  t_unit_exponent_value char(2) default '',
  unit_exponent_value varchar(5) NOT NULL default '',
  t_alt_y_grid char(2) default '',
  alt_y_grid char(2) default NULL,
  t_right_axis char(2) DEFAULT '',
  right_axis varchar(20) DEFAULT NULL,
  t_right_axis_label char(2) DEFAULT '',
  right_axis_label varchar(200) DEFAULT NULL,
  t_right_axis_format char(2) DEFAULT '',
  right_axis_format mediumint(8) DEFAULT NULL,
  t_right_axis_formatter char(2) DEFAULT '',
  right_axis_formatter varchar(10) DEFAULT NULL,
  t_left_axis_format char(2) DEFAULT '',
  left_axis_format mediumint(8) DEFAULT NULL,
  t_left_axis_formatter char(2) DEFAULT '',
  left_axis_formatter varchar(10) DEFAULT NULL,
  t_no_gridfit char(2) DEFAULT '',
  no_gridfit char(2) DEFAULT NULL,
  t_unit_length char(2) DEFAULT '',
  unit_length varchar(10) DEFAULT NULL,
  t_tab_width char(2) DEFAULT '',
  tab_width varchar(20) DEFAULT '30',
  t_dynamic_labels char(2) default '',
  dynamic_labels char(2) default NULL,
  t_force_rules_legend char(2) DEFAULT '',
  force_rules_legend char(2) DEFAULT NULL,
  t_legend_position char(2) DEFAULT '',
  legend_position varchar(10) DEFAULT NULL,
  t_legend_direction char(2) DEFAULT '',
  legend_direction varchar(10) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY local_graph_id (local_graph_id),
  KEY graph_template_id (graph_template_id),
  KEY title_cache (title_cache)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores the actual graph data.';

--
-- Dumping data for table `graph_templates_graph`
--

--
-- Table structure for table `graph_templates_item`
--

CREATE TABLE graph_templates_item (
  id int(10) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  local_graph_template_item_id int(10) unsigned NOT NULL default '0',
  local_graph_id int(10) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  task_item_id int(10) unsigned NOT NULL default '0',
  color_id mediumint(8) unsigned NOT NULL default '0',
  alpha char(2) default 'FF',
  graph_type_id tinyint(3) unsigned NOT NULL default '0',
  line_width DECIMAL(4,2) DEFAULT 0,
  dashes varchar(20) DEFAULT NULL,
  dash_offset mediumint(4) DEFAULT NULL,
  cdef_id mediumint(8) unsigned NOT NULL default '0',
  vdef_id mediumint(8) unsigned NOT NULL default '0',
  shift char(2) default NULL,
  consolidation_function_id tinyint(3) unsigned NOT NULL default '0',
  textalign varchar(10) default NULL,
  text_format varchar(255) default NULL,
  value varchar(255) default NULL,
  hard_return char(2) default NULL,
  gprint_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY graph_template_id (graph_template_id),
  KEY local_graph_id_sequence (local_graph_id, sequence),
  KEY local_graph_template_item_id (local_graph_template_item_id),
  KEY task_item_id (task_item_id),
  KEY cdef_id (cdef_id),
  KEY vdef_id (vdef_id),
  KEY color_id (color_id),
  KEY gprint_id (gprint_id),
  KEY `lgi_gti` (`local_graph_id`,`graph_template_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores the actual graph item data.';

--
-- Dumping data for table `graph_templates_item`
--

--
-- Table structure for table `graph_tree`
--

CREATE TABLE graph_tree (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `enabled` char(2) DEFAULT 'on',
  `locked` tinyint(3) unsigned DEFAULT '0',
  `locked_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sort_type` tinyint(3) unsigned NOT NULL default '1',
  `name` varchar(255) NOT NULL default '',
  `sequence` int(10) unsigned DEFAULT '1',
  `user_id` int(10) unsigned DEFAULT '1',
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified_by` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `sequence` (`sequence`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `graph_tree`
--

INSERT INTO graph_tree VALUES (1,'on',0,'0000-00-00',1,'Default Tree',1,1,'0000-00-00','1');

--
-- Table structure for table `graph_tree_items`
--

CREATE TABLE graph_tree_items (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  parent bigint(20) unsigned DEFAULT NULL,
  position int(10) unsigned DEFAULT NULL,
  graph_tree_id smallint(5) unsigned NOT NULL DEFAULT '0',
  local_graph_id int(10) unsigned NOT NULL DEFAULT '0',
  title varchar(255) DEFAULT NULL,
  host_id mediumint(8) unsigned NOT NULL DEFAULT '0',
  site_id int unsigned DEFAULT '0',
  host_grouping_type tinyint(3) unsigned NOT NULL DEFAULT '1',
  sort_children_type tinyint(3) unsigned NOT NULL DEFAULT '0',
  graph_regex varchar(60) DEFAULT '',
  host_regex varchar(60) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `graph_tree_id` (`graph_tree_id`),
  KEY `host_id` (`host_id`),
  KEY `site_id` (`site_id`),
  KEY `local_graph_id` (`local_graph_id`),
  KEY `parent_position`(`parent`, `position`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `graph_tree_items`
--

--
-- Table structure for table `host`
--

CREATE TABLE host (
  id mediumint(8) unsigned NOT NULL auto_increment,
  poller_id int(10) unsigned NOT NULL default '1',
  site_id int(10) unsigned NOT NULL default '1',
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  description varchar(150) NOT NULL default '',
  hostname varchar(100) default NULL,
  location varchar(40) default NULL,
  notes text,
  external_id varchar(40) default NULL,
  snmp_community varchar(100) default NULL,
  snmp_version tinyint(3) unsigned NOT NULL default '1',
  snmp_username varchar(50) default NULL,
  snmp_password varchar(50) default NULL,
  snmp_auth_protocol char(6) default '',
  snmp_priv_passphrase varchar(200) default '',
  snmp_priv_protocol char(6) default '',
  snmp_context varchar(64) default '',
  snmp_engine_id varchar(64) default '',
  snmp_port mediumint(8) unsigned NOT NULL default '161',
  snmp_timeout mediumint(8) unsigned NOT NULL default '500',
  snmp_sysDescr varchar(300) NOT NULL default '',
  snmp_sysObjectID varchar(128) NOT NULL default '',
  snmp_sysUpTimeInstance bigint(20) unsigned NOT NULL default '0',
  snmp_sysContact varchar(300) NOT NULL default '',
  snmp_sysName varchar(300) NOT NULL default '',
  snmp_sysLocation varchar(300) NOT NULL default '',
  availability_method smallint(5) unsigned NOT NULL default '1',
  ping_method smallint(5) unsigned default '0',
  ping_port int(10) unsigned default '0',
  ping_timeout int(10) unsigned default '500',
  ping_retries int(10) unsigned default '2',
  max_oids int(10) unsigned default '10',
  bulk_walk_size int(11) DEFAULT '-1',
  device_threads tinyint(3) unsigned NOT NULL DEFAULT '1',
  deleted char(2) NOT NULL default '',
  disabled char(2) NOT NULL default '',
  status tinyint(3) unsigned NOT NULL default '0',
  status_event_count mediumint(8) unsigned NOT NULL default '0',
  status_fail_date timestamp NOT NULL default '0000-00-00 00:00:00',
  status_rec_date timestamp NOT NULL default '0000-00-00 00:00:00',
  status_last_error varchar(255) default '',
  min_time decimal(10,5) default '9.99999',
  max_time decimal(10,5) default '0.00000',
  cur_time decimal(10,5) default '0.00000',
  avg_time decimal(10,5) default '0.00000',
  polling_time DOUBLE default '0',
  total_polls int(10) unsigned default '0',
  failed_polls int(10) unsigned default '0',
  availability decimal(8,5) NOT NULL default '100.00000',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY poller_id_disabled (poller_id, disabled),
  KEY external_id (external_id),
  KEY disabled (disabled),
  KEY status (status),
  KEY site_id_location (site_id, location),
  KEY hostname (hostname),
  KEY poller_id_last_updated (poller_id, last_updated)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host`
--

--
-- Table structure for table `host_graph`
--

CREATE TABLE host_graph (
  host_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (host_id,graph_template_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_graph`
--

--
-- Table structure for table `host_snmp_cache`
--

CREATE TABLE host_snmp_cache (
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  field_name varchar(50) NOT NULL default '',
  field_value varchar(512) default NULL,
  snmp_index varchar(255) NOT NULL default '',
  oid TEXT NOT NULL,
  present tinyint(3) unsigned NOT NULL DEFAULT '1',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (host_id, snmp_query_id, field_name, snmp_index),
  KEY host_id (host_id, field_name),
  KEY snmp_index (snmp_index),
  KEY field_name (field_name),
  KEY field_value (field_value),
  KEY snmp_query_id (snmp_query_id),
  KEY last_updated (last_updated),
  KEY present (present)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_snmp_cache`
--

--
-- Table structure for table `host_snmp_query`
--

CREATE TABLE host_snmp_query (
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  sort_field varchar(50) NOT NULL default '',
  title_format varchar(50) NOT NULL default '',
  reindex_method tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY (host_id,snmp_query_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_snmp_query`
--

--
-- Table structure for table `host_value_cache`
--

CREATE TABLE host_value_cache (
  host_id mediumint(8) unsigned NOT NULL default '0',
  dimension varchar(40) NOT NULL default '',
  value varchar(8192) NOT NULL default '',
  time_to_live int(11) NOT NULL default '-1',
  last_updated TIMESTAMP default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (host_id, dimension)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_value_cache`
--

--
-- Table structure for table `host_template`
--

CREATE TABLE host_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  class varchar(40) NOT NULL default '',
  PRIMARY KEY (id),
  KEY `name` (`name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_template`
--

--
-- Table structure for table `host_template_graph`
--

CREATE TABLE host_template_graph (
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (host_template_id,graph_template_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_template_graph`
--

--
-- Table structure for table `host_template_snmp_query`
--

CREATE TABLE host_template_snmp_query (
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (host_template_id, snmp_query_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `host_template_snmp_query`
--

--
-- Table structure for table `plugin_config`
--

CREATE TABLE `plugin_config` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `directory` varchar(32) NOT NULL default '',
  `name` varchar(64) NOT NULL default '',
  `status` tinyint(3) unsigned NOT NULL default '0',
  `author` varchar(64) NOT NULL default '',
  `webpage` varchar(255) NOT NULL default '',
  `version` varchar(10) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `directory` (`directory`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `plugin_hooks`
--

CREATE TABLE `plugin_hooks` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `name` varchar(32) NOT NULL default '',
  `hook` varchar(64) NOT NULL default '',
  `file` varchar(255) NOT NULL default '',
  `function` varchar(128) NOT NULL default '',
  `status` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `hook` (`hook`),
  KEY `status` (`status`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `plugin_realms`
--

CREATE TABLE `plugin_realms` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `plugin` varchar(32) NOT NULL default '',
  `file` text NOT NULL,
  `display` varchar(64) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `plugin_db_changes`
--

CREATE TABLE `plugin_db_changes` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `plugin` varchar(16) NOT NULL default '',
  `table` varchar(64) NOT NULL default '',
  `column` varchar(64) NOT NULL,
  `method` varchar(16) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`),
  KEY `method` (`method`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

REPLACE INTO `plugin_realms` VALUES (1, 'internal', 'plugins.php', 'Plugin Management');
INSERT INTO `plugin_hooks` VALUES (1, 'internal', 'config_arrays', '', 'plugin_config_arrays', 1);
INSERT INTO `plugin_hooks` VALUES (2, 'internal', 'draw_navigation_text', '', 'plugin_draw_navigation_text', 1);

--
-- Table structure for table `poller`
--

CREATE TABLE `poller` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `disabled` char(2) DEFAULT '',
  `name` varchar(30) DEFAULT NULL,
  `notes` varchar(1024) DEFAULT '',
  `status` int(10) unsigned NOT NULL DEFAULT '0',
  `log_level` int(10) NOT NULL DEFAULT '-1',
  `timezone` varchar(40) DEFAULT '',
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `dbdefault` varchar(20) NOT NULL DEFAULT '',
  `dbhost` varchar(64) NOT NULL DEFAULT '',
  `dbuser` varchar(20) NOT NULL DEFAULT '',
  `dbpass` varchar(64) NOT NULL DEFAULT '',
  `dbport` int(10) unsigned DEFAULT '3306',
  `dbretries` int(10) unsigned DEFAULT '2',
  `dbssl` char(3) DEFAULT '',
  `dbsslkey` varchar(255) DEFAULT NULL,
  `dbsslcert` varchar(255) DEFAULT NULL,
  `dbsslca` varchar(255) DEFAULT NULL,
  `total_time` double DEFAULT '0',
  `max_time` double DEFAULT NULL,
  `min_time` double DEFAULT NULL,
  `avg_time` double DEFAULT NULL,
  `total_polls` int(10) unsigned DEFAULT '0',
  `processes` int(10) unsigned DEFAULT '1',
  `threads` int(10) unsigned DEFAULT '1',
  `sync_interval` int(10) unsigned DEFAULT '7200',
  `snmp` mediumint(8) unsigned DEFAULT '0',
  `script` mediumint(8) unsigned DEFAULT '0',
  `server` mediumint(8) unsigned DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_status` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_sync` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `requires_sync` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `disabled` (`disabled`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Pollers for Cacti';

INSERT INTO poller (id,name,hostname) VALUES (1,'Main Poller', 'localhost');

--
-- Table structure for table `poller_command`
--

CREATE TABLE poller_command (
  poller_id smallint(5) unsigned NOT NULL default '1',
  time timestamp NOT NULL default '0000-00-00 00:00:00',
  action tinyint(3) unsigned NOT NULL default '0',
  command varchar(191) NOT NULL default '',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (poller_id, action, command),
  KEY poller_id_last_updated (poller_id, last_updated)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_data_template_field_mappings`
--

CREATE TABLE `poller_data_template_field_mappings` (
  `data_template_id` int(10) unsigned NOT NULL DEFAULT '0',
  `data_name` varchar(40) NOT NULL DEFAULT '',
  `data_source_names` varchar(125) NOT NULL DEFAULT '',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`data_template_id`, `data_name`, `data_source_names`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Tracks mapping of Data Templates to their Data Source Names';

--
-- Table structure for table `poller_item`
--

CREATE TABLE poller_item (
  `local_data_id` int(10) unsigned NOT NULL default '0',
  `poller_id` int(10) unsigned NOT NULL default '1',
  `host_id` mediumint(8) unsigned NOT NULL default '0',
  `action` tinyint(3) unsigned NOT NULL default '1',
  `present` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hostname` varchar(100) NOT NULL default '',
  `snmp_community` varchar(100) NOT NULL default '',
  `snmp_version` tinyint(3) unsigned NOT NULL default '0',
  `snmp_username` varchar(50) NOT NULL default '',
  `snmp_password` varchar(50) NOT NULL default '',
  `snmp_auth_protocol` char(6) NOT NULL default '',
  `snmp_priv_passphrase` varchar(200) NOT NULL default '',
  `snmp_priv_protocol` char(6) NOT NULL default '',
  `snmp_context` varchar(64) default '',
  `snmp_engine_id` varchar(64) default '',
  `snmp_port` mediumint(8) unsigned NOT NULL default '161',
  `snmp_timeout` mediumint(8) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `rrd_path` varchar(255) NOT NULL default '',
  `rrd_num` tinyint(3) unsigned NOT NULL default '0',
  `rrd_step` mediumint(8) NOT NULL default '300',
  `rrd_next_step` mediumint(8) NOT NULL default '0',
  `arg1` TEXT,
  `arg2` varchar(255) default NULL,
  `arg3` varchar(255) default NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`),
  KEY `host_id` (`host_id`),
  KEY `action` (`action`),
  KEY `present` (`present`),
  KEY `poller_id_host_id` (`poller_id`,`host_id`),
  KEY `poller_id_rrd_next_step` (`poller_id`,`rrd_next_step`),
  KEY `poller_id_action` (`poller_id`,`action`),
  KEY `poller_id_last_updated` (`poller_id`, `last_updated`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_output`
--

CREATE TABLE poller_output (
  local_data_id int(10) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  time timestamp NOT NULL default '0000-00-00 00:00:00',
  output varchar(512) NOT NULL default '',
  PRIMARY KEY (local_data_id, rrd_name, time) /*!50060 USING BTREE */
) ENGINE=MEMORY;

--
-- Table structure for table `poller_output_boost`
--

CREATE TABLE `poller_output_boost` (
  `local_data_id` int(10) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `output` varchar(512) NOT NULL,
  `last_updated` timestamp NOT NULL default current_timestamp,
  PRIMARY KEY USING BTREE (`local_data_id`, `time`, `rrd_name`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_output_boost_local_data_ids`
--

CREATE TABLE `poller_output_boost_local_data_ids` (
  `local_data_id` int(10) unsigned NOT NULL DEFAULT 0,
  `process_handler` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`local_data_id`),
  KEY `process_handler` (`process_handler`)
) ENGINE=MEMORY;

--
-- Table structure for table `poller_output_boost_processes`
--

CREATE TABLE `poller_output_boost_processes` (
  `sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
  `status` varchar(255) default NULL,
  PRIMARY KEY (`sock_int_value`)
) ENGINE=MEMORY;

--
-- Table structure for table `poller_output_realtime`
--

CREATE TABLE poller_output_realtime (
  local_data_id int(10) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  output text NOT NULL,
  poller_id varchar(256) NOT NULL default '1',
  PRIMARY KEY (local_data_id, rrd_name, time, poller_id),
  KEY poller_id (poller_id(191)),
  KEY `time` (`time`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_reindex`
--

CREATE TABLE poller_reindex (
  host_id mediumint(8) unsigned NOT NULL default '0',
  data_query_id mediumint(8) unsigned NOT NULL default '0',
  action tinyint(3) unsigned NOT NULL default '0',
  present tinyint(3) unsigned NOT NULL DEFAULT '1',
  op char(1) NOT NULL default '',
  assert_value varchar(100) NOT NULL default '',
  arg1 varchar(255) NOT NULL default '',
  PRIMARY KEY (host_id, data_query_id, arg1(187)),
  KEY present (present)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_resource_cache`
--

CREATE TABLE poller_resource_cache (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resource_type` varchar(20) DEFAULT NULL,
  `md5sum` varchar(32) DEFAULT NULL,
  `path` varchar(191) DEFAULT NULL,
  `update_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `contents` longblob,
  `attributes` INT unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Caches all scripts, resources files, and plugins';

--
-- Table structure for table `poller_time`
--

CREATE TABLE poller_time (
  id bigint(20) unsigned NOT NULL auto_increment,
  pid int(10) unsigned NOT NULL default '0',
  poller_id int(10) unsigned NOT NULL default '1',
  start_time timestamp NOT NULL default '0000-00-00 00:00:00',
  end_time timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (id),
  KEY `poller_id_end_time` (`poller_id`, `end_time`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `poller_time_stats`
--

CREATE TABLE poller_time_stats (
  id bigint(20) unsigned NOT NULL auto_increment,
  poller_id int(10) unsigned NOT NULL default '1',
  total_time double default NULL,
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Table structure for table `processes`
--

CREATE TABLE `processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `tasktype` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `taskname` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `taskid` int(10) unsigned NOT NULL DEFAULT 0,
  `timeout` int(10) unsigned DEFAULT 300,
  `started` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`pid`,`tasktype`,`taskname`,`taskid`),
  KEY `tasktype` (`tasktype`),
  KEY `id` (`id`)
) ENGINE=MEMORY COMMENT='Stores Process Status for Cacti Background Processes';

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `cformat` char(2) NOT NULL DEFAULT '',
  `format_file` varchar(255) NOT NULL DEFAULT '',
  `font_size` smallint(2) unsigned NOT NULL DEFAULT '0',
  `alignment` smallint(2) unsigned NOT NULL DEFAULT '0',
  `graph_linked` char(2) NOT NULL DEFAULT '',
  `intrvl` smallint(2) unsigned NOT NULL DEFAULT '0',
  `count` smallint(2) unsigned NOT NULL DEFAULT '0',
  `offset` int(10) unsigned NOT NULL DEFAULT '0',
  `mailtime` bigint(20) unsigned NOT NULL DEFAULT '0',
  `subject` varchar(64) NOT NULL DEFAULT '',
  `from_name` varchar(40) NOT NULL,
  `from_email` text NOT NULL,
  `email` text NOT NULL,
  `bcc` text NOT NULL,
  `attachment_type` smallint(2) unsigned NOT NULL DEFAULT '1',
  `graph_height` smallint(2) unsigned NOT NULL DEFAULT '0',
  `graph_width` smallint(2) unsigned NOT NULL DEFAULT '0',
  `graph_columns` smallint(2) unsigned NOT NULL DEFAULT '0',
  `thumbnails` char(2) NOT NULL DEFAULT '',
  `lastsent` bigint(20) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `mailtime` (`mailtime`))
  ENGINE=InnoDB
  ROW_FORMAT=Dynamic
  COMMENT='Cacti Reporting Reports';

--
-- Table structure for table `reports_items`
--

CREATE TABLE `reports_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL DEFAULT '0',
  `item_type` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `tree_id` int(10) unsigned NOT NULL DEFAULT '0',
  `branch_id` int(10) unsigned NOT NULL DEFAULT '0',
  `tree_cascade` char(2) NOT NULL DEFAULT '',
  `graph_name_regexp` varchar(128) NOT NULL DEFAULT '',
  `site_id` int(11) NOT NULL DEFAULT '-1',
  `host_template_id` int(11) NOT NULL DEFAULT '-1',
  `host_id` int(11) NOT NULL DEFAULT '-1',
  `graph_template_id` int(11) NOT NULL DEFAULT '-1',
  `local_graph_id` int(10) unsigned NOT NULL DEFAULT '0',
  `timespan` int(10) unsigned NOT NULL DEFAULT '0',
  `align` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `item_text` text NOT NULL,
  `font_size` smallint(2) unsigned NOT NULL DEFAULT '10',
  `sequence` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`))
  ENGINE=InnoDB
  ROW_FORMAT=Dynamic
  COMMENT='Cacti Reporting Items';

--
-- Table structure for table `settings`
--

CREATE TABLE settings (
  name varchar(50) NOT NULL default '',
  value varchar(2048) NOT NULL default '',
  PRIMARY KEY (name)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `settings`
--

INSERT INTO settings VALUES ('auth_method', 1);
INSERT INTO settings VALUES ('selected_theme', 'modern');

--
-- Table structure for table `settings_user`
--

CREATE TABLE settings_user (
  user_id smallint(8) unsigned NOT NULL default '0',
  name varchar(50) NOT NULL default '',
  value varchar(2048) NOT NULL default '',
  PRIMARY KEY (user_id, name)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `settings_user`
--


--
-- Table structure for table `settings_user_group`
--

CREATE TABLE settings_user_group (
  group_id smallint(8) unsigned NOT NULL DEFAULT '0',
  name varchar(50) NOT NULL DEFAULT '',
  value varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (group_id, name)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Stores the Default User Group Graph Settings';

--
-- Table structure for table `settings_tree`
--

CREATE TABLE settings_tree (
  user_id mediumint(8) unsigned NOT NULL default '0',
  graph_tree_item_id int(10) unsigned NOT NULL default '0',
  status tinyint(4) NOT NULL default '0',
  PRIMARY KEY (user_id, graph_tree_item_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `settings_tree`
--


--
-- Table structure for table `snmp_query`
--

CREATE TABLE snmp_query (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  xml_path varchar(255) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  description varchar(255) default NULL,
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  data_input_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY name (name)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `snmp_query`
--

--
-- Table structure for table `snmp_query_graph`
--

CREATE TABLE snmp_query_graph (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  name varchar(100) NOT NULL default '',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY `graph_template_id_name` (`graph_template_id`, `name`),
  KEY `snmp_query_id_name` (`snmp_query_id`, `name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `snmp_query_graph`
--

--
-- Table structure for table `snmp_query_graph_rrd`
--

CREATE TABLE snmp_query_graph_rrd (
  snmp_query_graph_id mediumint(8) unsigned NOT NULL default '0',
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  data_template_rrd_id int(10) unsigned NOT NULL default '0',
  snmp_field_name varchar(50) NOT NULL default '0',
  PRIMARY KEY (snmp_query_graph_id,data_template_id,data_template_rrd_id),
  KEY data_template_rrd_id (data_template_rrd_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `snmp_query_graph_rrd`
--

--
-- Table structure for table `snmp_query_graph_rrd_sv`
--

CREATE TABLE snmp_query_graph_rrd_sv (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  snmp_query_graph_id mediumint(8) unsigned NOT NULL default '0',
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  field_name varchar(100) NOT NULL default '',
  text varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY snmp_query_graph_id (snmp_query_graph_id),
  KEY data_template_id (data_template_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `snmp_query_graph_rrd_sv`
--

--
-- Table structure for table `snmp_query_graph_sv`
--

CREATE TABLE snmp_query_graph_sv (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  snmp_query_graph_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  field_name varchar(100) NOT NULL default '',
  text varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY snmp_query_graph_id (snmp_query_graph_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `snmp_query_graph_sv`
--

--
-- Table structure for table `user_auth`
--

CREATE TABLE user_auth (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `username` varchar(50) NOT NULL default '0',
  `password` varchar(256) NOT NULL default '',
  `realm` mediumint(8) NOT NULL default '0',
  `full_name` varchar(100) default '0',
  `email_address` varchar(128) NULL,
  `must_change_password` char(2) default NULL,
  `password_change` char(2) default 'on',
  `show_tree` char(2) default 'on',
  `show_list` char(2) default 'on',
  `show_preview` char(2) NOT NULL default 'on',
  `graph_settings` char(2) default NULL,
  `login_opts` tinyint(3) unsigned NOT NULL default '1',
  `policy_graphs` tinyint(3) unsigned NOT NULL default '1',
  `policy_trees` tinyint(3) unsigned NOT NULL default '1',
  `policy_hosts` tinyint(3) unsigned NOT NULL default '1',
  `policy_graph_templates` tinyint(3) unsigned NOT NULL default '1',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  `lastchange` int(11) NOT NULL DEFAULT '-1',
  `lastlogin` int(11) NOT NULL DEFAULT '-1',
  `password_history` varchar(4096) NOT NULL DEFAULT '-1',
  `locked` varchar(3) NOT NULL DEFAULT '',
  `failed_attempts` int(5) NOT NULL DEFAULT '0',
  `lastfail` int(10) unsigned NOT NULL DEFAULT '0',
  `reset_perms` int(10) unsigned NOT NULL DEFAULT '0',
  `tfa_enabled` char(3) NOT NULL DEFAULT '',
  `tfa_secret` char(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `realm` (`realm`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `user_auth`
--

INSERT INTO user_auth VALUES (1,'admin','21232f297a57a5a743894a0e4a801fc3',0,'Administrator','','on','on','on','on','on','on',2,1,1,1,1,'on',-1,-1,'-1','',0,0,0,'','');
INSERT INTO user_auth VALUES (3,'guest','43e9a4ab75570f5b',0,'Guest Account','','on','on','on','on','on',3,1,1,1,1,1,'',-1,-1,'-1','',0,0,0,'','');

--
-- Table structure for table `user_auth_cache`
--

CREATE TABLE `user_auth_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tokenkey` (`token`),
  KEY `hostname` (`hostname`),
  KEY `user_id` (`user_id`),
  KEY `last_update` (`last_update`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Caches Remember Me Details';

--
-- Dumping data for table `user_auth`
--

--
-- Table structure for table `user_auth_group`
--

CREATE TABLE `user_auth_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `graph_settings` varchar(2) DEFAULT NULL,
  `login_opts` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `show_tree` varchar(2) DEFAULT 'on',
  `show_list` varchar(2) DEFAULT 'on',
  `show_preview` varchar(2) NOT NULL DEFAULT 'on',
  `policy_graphs` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `policy_trees` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `policy_hosts` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `policy_graph_templates` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table that Contains User Groups';

--
-- Dumping data for table `user_auth_group`
--

--
-- Table structure for table `user_auth_group_members`
--

CREATE TABLE `user_auth_group_members` (
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`user_id`),
  KEY `realm_id` (`user_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table that Contains User Group Members';

--
-- Dumping data for table `user_auth_group_members`
--

--
-- Table structure for table `user_auth_group_perms`
--

CREATE TABLE `user_auth_group_perms` (
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`,`item_id`,`type`),
  KEY `group_id` (`group_id`,`type`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table that Contains User Group Permissions';

--
-- Dumping data for table `user_auth_group_perms`
--

--
-- Table structure for table `user_auth_group_realm`
--

CREATE TABLE `user_auth_group_realm` (
  `group_id` int(10) unsigned NOT NULL,
  `realm_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`realm_id`),
  KEY `realm_id` (`realm_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table that Contains User Group Realm Permissions';

--
-- Dumping data for table `user_auth_group_realm`
--

--
-- Table structure for table `user_auth_perms`
--

CREATE TABLE user_auth_perms (
  user_id mediumint(8) unsigned NOT NULL default '0',
  item_id mediumint(8) unsigned NOT NULL default '0',
  type tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY (user_id,item_id,type),
  KEY user_id (user_id,type)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `user_auth_perms`
--


--
-- Table structure for table `user_auth_realm`
--

CREATE TABLE user_auth_realm (
  realm_id mediumint(8) unsigned NOT NULL default '0',
  user_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (realm_id,user_id),
  KEY user_id (user_id)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `user_auth_realm`
--

INSERT INTO user_auth_realm VALUES (1,1);
INSERT INTO user_auth_realm VALUES (2,1);
INSERT INTO user_auth_realm VALUES (3,1);
INSERT INTO user_auth_realm VALUES (4,1);
INSERT INTO user_auth_realm VALUES (5,1);
INSERT INTO user_auth_realm VALUES (7,1);
INSERT INTO user_auth_realm VALUES (7,3);
INSERT INTO user_auth_realm VALUES (8,1);
INSERT INTO user_auth_realm VALUES (9,1);
INSERT INTO user_auth_realm VALUES (10,1);
INSERT INTO user_auth_realm VALUES (11,1);
INSERT INTO user_auth_realm VALUES (12,1);
INSERT INTO user_auth_realm VALUES (13,1);
INSERT INTO user_auth_realm VALUES (14,1);
INSERT INTO user_auth_realm VALUES (15,1);
INSERT INTO user_auth_realm VALUES (16,1);
INSERT INTO user_auth_realm VALUES (17,1);
INSERT INTO user_auth_realm VALUES (18,1);
INSERT INTO user_auth_realm VALUES (19,1);
INSERT INTO user_auth_realm VALUES (20,1);
INSERT INTO user_auth_realm VALUES (21,1);
INSERT INTO user_auth_realm VALUES (22,1);
INSERT INTO user_auth_realm VALUES (23,1);
INSERT INTO user_auth_realm VALUES (24,1);
INSERT INTO user_auth_realm VALUES (25,1);
INSERT INTO user_auth_realm VALUES (26,1);
INSERT INTO user_auth_realm VALUES (27,1);
INSERT INTO user_auth_realm VALUES (28,1);
INSERT INTO user_auth_realm VALUES (101,1);
INSERT INTO user_auth_realm VALUES (1043,1);

--
-- Table structure for table `user_auth_row_cache`
--

CREATE TABLE user_auth_row_cache (
  `user_id` mediumint(8) NOT NULL default '0',
  `class` varchar(20) NOT NULL default '',
  `hash` varchar(32) NOT NULL default '0',
  `total_rows` int(10) unsigned NOT NULL default '0',
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`class`,`hash`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `user_auth_row_cache`
--

--
-- Table structure for table `user_log`
--

CREATE TABLE user_log (
  `username` varchar(50) NOT NULL default '0',
  `user_id` mediumint(8) NOT NULL default '0',
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `result` tinyint(3) unsigned NOT NULL default '0',
  `ip` varchar(40) NOT NULL default '',
  PRIMARY KEY (`username`,`user_id`,`time`),
  KEY user_id (`user_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `user_log`
--

--
-- Table structure for table `user_domains`
--

CREATE TABLE `user_domains` (
  `domain_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(20) NOT NULL,
  `type` int(10) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  `defdomain` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table to Hold Login Domains';

--
-- Dumping data for table `user_domains`
--

--
-- Table structure for table `user_domains_ldap`
--

CREATE TABLE `user_domains_ldap` (
  `domain_id` int(10) unsigned NOT NULL,
  `server` varchar(128) NOT NULL,
  `port` int(10) unsigned NOT NULL,
  `port_ssl` int(10) unsigned NOT NULL,
  `proto_version` tinyint(3) unsigned NOT NULL,
  `encryption` tinyint(3) unsigned NOT NULL,
  `tls_certificate` tinyint(3) unsigned NOT NULL default '3',
  `referrals` tinyint(3) unsigned NOT NULL,
  `mode` tinyint(3) unsigned NOT NULL,
  `dn` varchar(128) NOT NULL DEFAULT '',
  `group_require` char(2) NOT NULL DEFAULT '',
  `group_dn` varchar(128) NOT NULL DEFAULT '',
  `group_attrib` varchar(128) NOT NULL DEFAULT '',
  `group_member_type` tinyint(3) unsigned NOT NULL,
  `search_base` varchar(128) NOT NULL DEFAULT '',
  `search_filter` varchar(512) NOT NULL DEFAULT '',
  `specific_dn` varchar(128) NOT NULL DEFAULT '',
  `specific_password` varchar(128) NOT NULL DEFAULT '',
  `cn_full_name` varchar(50) NULL DEFAULT '',
  `cn_email` varchar (50) NULL DEFAULT '',
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Table to Hold Login Domains for LDAP';

--
-- Dumping data for table `user_domains_ldap`
--

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(32) NOT NULL,
  `remote_addr` varchar(25) NOT NULL DEFAULT '',
  `access` int(10) unsigned DEFAULT NULL,
  `data` mediumblob,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_agent` varchar(128) NOT NULL DEFAULT '',
  `start_time` timestamp NOT NULL DEFAULT current_timestamp,
  `transactions` int(10) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Used for Database based Session Storage';

--
-- Dumping data for table `sessions`
--

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `disabled` char(2) NOT NULL default '',
  `address1` varchar(100) DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(50) DEFAULT '',
  `state` varchar(20) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT '',
  `country` varchar(30) DEFAULT '',
  `timezone` varchar(40) DEFAULT '',
  `latitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
  `longitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
  `zoom` tinyint(3) unsigned DEFAULT NULL,
  `alternate_id` varchar(30) DEFAULT '',
  `notes` varchar(1024),
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `city` (`city`),
  KEY `state` (`state`),
  KEY `postal_code` (`postal_code`),
  KEY `country` (`country`),
  KEY `alternate_id` (`alternate_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Contains information about customer sites';

--
-- Dumping data for table `sites`
--

INSERT INTO `sites` VALUES (1,'Edge','','','','','','','','',0.0000000000,0.0000000000,'','','');
INSERT INTO `sites` VALUES (2,'Core','','','','','','','','',0.0000000000,0.0000000000,'','','');

--
-- Table structure for table `snmpagent_cache`
--

CREATE TABLE `snmpagent_cache` (
  `oid` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `mib` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT '',
  `otype` varchar(50) NOT NULL DEFAULT '',
  `kind` varchar(50) NOT NULL DEFAULT '',
  `max-access` varchar(50) NOT NULL DEFAULT 'not-accessible',
  `value` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(5000) NOT NULL DEFAULT '',
  PRIMARY KEY (`oid`),
  KEY `name` (`name`),
  KEY `mib_name` (`mib`,`name`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='SNMP MIB CACHE';

--
-- Dumping data for table `snmpagent_cache`
--

--
-- Table structure for table `snmpagent_mibs`
--
CREATE TABLE `snmpagent_mibs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Registered MIB files';

--
-- Dumping data for table `snmpagent_mibs`
--

--
-- Table structure for table `snmpagent_cache_notifications`
--

CREATE TABLE `snmpagent_cache_notifications` (
  `name` varchar(50) NOT NULL,
  `mib` varchar(50) NOT NULL,
  `attribute` varchar(50) NOT NULL,
  `sequence_id` smallint(6) NOT NULL,
  PRIMARY KEY (`name`,`mib`,`attribute`,`sequence_id`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Notifications and related attributes';

--
-- Dumping data for table `snmpagent_cache_notifications`
--

--
-- Table structure for table `snmpagent_cache_textual_conventions`
--

CREATE TABLE `snmpagent_cache_textual_conventions` (
  `name` varchar(50) NOT NULL,
  `mib` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT '',
  `description` varchar(5000) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`mib`,`type`),
  KEY `mib` (`mib`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='Textual conventions';

--
-- Dumping data for table `snmpagent_cache_textual_conventions`
--

--
-- Table structure for table `snmpagent_managers`
--

CREATE TABLE `snmpagent_managers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `disabled` char(2) DEFAULT NULL,
  `max_log_size` tinyint(4) NOT NULL,
  `snmp_version` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `snmp_community` varchar(100) NOT NULL DEFAULT '',
  `snmp_username` varchar(50) NOT NULL,
  `snmp_password` varchar(50) NOT NULL,
  `snmp_auth_protocol` char(6) NOT NULL,
  `snmp_priv_passphrase` varchar(200) NOT NULL,
  `snmp_priv_protocol` char(6) NOT NULL,
  `snmp_engine_id` varchar(64) DEFAULT NULL,
  `snmp_port` mediumint(8) unsigned NOT NULL DEFAULT '161',
  `snmp_message_type` tinyint(4) NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='snmp notification receivers';

--
-- Dumping data for table `snmpagent_managers`
--

--
-- Table structure for table `snmpagent_managers_notifications`
--

CREATE TABLE `snmpagent_managers_notifications` (
  `manager_id` int(10) unsigned NOT NULL,
  `notification` varchar(50) NOT NULL,
  `mib` varchar(50) NOT NULL,
  PRIMARY KEY(`manager_id`,`notification`,`mib`),
  KEY `mib` (`mib`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='snmp notifications to receivers';

--
-- Dumping data for table `snmpagent_managers_notifications`
--

--
-- Table structure for table `snmpagent_notifications_log`
--

CREATE TABLE `snmpagent_notifications_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `time` int(24) NOT NULL,
  `severity` tinyint(4) NOT NULL,
  `manager_id` int(10) unsigned NOT NULL,
  `notification` varchar(190) NOT NULL,
  `mib` varchar(50) NOT NULL,
  `varbinds` varchar(5000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `severity` (`severity`),
  KEY `manager_id_notification` (`manager_id`,`notification`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='logs snmp notifications to receivers';

--
-- Dumping data for table `snmpagent_notifications_log`
--

--
-- Table structure for table `vdef`
--

CREATE TABLE vdef (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY `hash` (`hash`),
  KEY `name` (`name`(171))
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='vdef';

--
-- Dumping data for table `vdef`
--

INSERT INTO vdef VALUES(1, 'e06ed529238448773038601afb3cf278', 'Maximum');
INSERT INTO vdef VALUES(2, 'e4872dda82092393d6459c831a50dc3b', 'Minimum');
INSERT INTO vdef VALUES(3, '5ce1061a46bb62f36840c80412d2e629', 'Average');
INSERT INTO vdef VALUES(4, '06bd3cbe802da6a0745ea5ba93af554a', 'Last (Current)');
INSERT INTO vdef VALUES(5, '631c1b9086f3979d6dcf5c7a6946f104', 'First');
INSERT INTO vdef VALUES(6, '6b5335843630b66f858ce6b7c61fc493', 'Total: Current Data Source');
INSERT INTO vdef VALUES(7, 'c80d12b0f030af3574da68b28826cd39', '95th Percentage: Current Data Source');

--
-- Table structure for table `vdef_items`
--

CREATE TABLE vdef_items (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `hash` varchar(32) NOT NULL default '',
  `vdef_id` mediumint(8) unsigned NOT NULL default '0',
  `sequence` mediumint(8) unsigned NOT NULL default '0',
  `type` tinyint(3) unsigned NOT NULL default '0',
  `value` varchar(150) NOT NULL default '',
  PRIMARY KEY (id),
  KEY `vdef_id_sequence` (`vdef_id`,`sequence`)
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='vdef items';

--
-- Dumping data for table `vdef_items`
--

INSERT INTO vdef_items VALUES(1, '88d33bf9271ac2bdf490cf1784a342c1', 1, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(2, 'a307afab0c9b1779580039e3f7c4f6e5', 1, 2, 1, '1');
INSERT INTO vdef_items VALUES(3, '0945a96068bb57c80bfbd726cf1afa02', 2, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(4, '95a8df2eac60a89e8a8ca3ea3d019c44', 2, 2, 1, '2');
INSERT INTO vdef_items VALUES(5, 'cc2e1c47ec0b4f02eb13708cf6dac585', 3, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(6, 'a2fd796335b87d9ba54af6a855689507', 3, 2, 1, '3');
INSERT INTO vdef_items VALUES(7, 'a1d7974ee6018083a2053e0d0f7cb901', 4, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(8, '26fccba1c215439616bc1b83637ae7f3', 4, 2, 1, '5');
INSERT INTO vdef_items VALUES(9, 'a8993b265f4c5398f4a47c44b5b37a07', 5, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(10, '5a380d469d611719057c3695ce1e4eee', 5, 2, 1, '6');
INSERT INTO vdef_items VALUES(11, '65cfe546b17175fad41fcca98c057feb', 6, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(12, 'f330b5633c3517d7c62762cef091cc9e', 6, 2, 1, '7');
INSERT INTO vdef_items VALUES(13, 'f1bf2ecf54ca0565cf39c9c3f7e5394b', 7, 1, 4, 'CURRENT_DATA_SOURCE');
INSERT INTO vdef_items VALUES(14, '11a26f18feba3919be3af426670cba95', 7, 2, 6, '95');
INSERT INTO vdef_items VALUES(15, 'e7ae90275bc1efada07c19ca3472d9db', 7, 3, 1, '8');

--
-- Table structure for table `rrdcheck`
--

CREATE TABLE rrdcheck (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `test_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `message` varchar(250) default ''
) ENGINE=InnoDB ROW_FORMAT=Dynamic COMMENT='rrdcheck';

--
-- Table structure for table `version`
--

CREATE TABLE version (
  cacti char(30) default '',
  PRIMARY KEY (cacti)
) ENGINE=InnoDB ROW_FORMAT=Dynamic;

--
-- Dumping data for table `version`
--

INSERT INTO version VALUES ('new_install');
