
--
-- Allow MySQL to handle Cacti's legacy syntax
--

SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE', '')) ;

--
-- Table structure for table `aggregate_graph_templates`
--

CREATE TABLE `aggregate_graph_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `graph_template_id` int(10) unsigned NOT NULL,
  `gprint_prefix` varchar(64) NOT NULL,
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
) ENGINE=InnoDB COMMENT='Template Definitions for Aggregate Graphs';

--
-- Table structure for table `aggregate_graph_templates_graph`
--
CREATE TABLE `aggregate_graph_templates_graph` (
  `aggregate_template_id` int(10) unsigned NOT NULL,
  `t_image_format_id` char(2) DEFAULT '',
  `image_format_id` tinyint(1) NOT NULL DEFAULT '0',
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
  `auto_scale_opts` tinyint(1) NOT NULL DEFAULT '0',
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
  t_legend_direction char(2) DEFAULT '0',
  legend_direction varchar(10) DEFAULT NULL,
  PRIMARY KEY (`aggregate_template_id`)
) ENGINE=InnoDB COMMENT='Aggregate Template Graph Data';

--
-- Table structure for table `aggregate_graph_templates_item`
--

CREATE TABLE `aggregate_graph_templates_item` (
  `aggregate_template_id` int(10) unsigned NOT NULL,
  `graph_templates_item_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_template` int(11) NOT NULL,
  `t_graph_type_id` char(2) DEFAULT '',
  `graph_type_id` tinyint(3) NOT NULL DEFAULT '0',
  `t_cdef_id` char(2) DEFAULT '',
  `cdef_id` mediumint(8) unsigned DEFAULT NULL,
  `item_skip` char(2) NOT NULL,
  `item_total` char(2) NOT NULL,
  PRIMARY KEY (`aggregate_template_id`,`graph_templates_item_id`)
) ENGINE=InnoDB COMMENT='Aggregate Template Graph Items';

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
) ENGINE=InnoDB COMMENT='Aggregate Graph Definitions';

--
-- Table structure for table `aggregate_graphs_graph_item`
--

CREATE TABLE `aggregate_graphs_graph_item` (
  `aggregate_graph_id` int(10) unsigned NOT NULL,
  `graph_templates_item_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_template` int(11) unsigned NOT NULL,
  `t_graph_type_id` char(2) DEFAULT '',
  `graph_type_id` tinyint(3) NOT NULL DEFAULT '0',
  `t_cdef_id` char(2) DEFAULT '',
  `cdef_id` mediumint(8) unsigned DEFAULT NULL,
  `item_skip` char(2) NOT NULL,
  `item_total` char(2) NOT NULL,
  PRIMARY KEY (`aggregate_graph_id`,`graph_templates_item_id`)
) ENGINE=InnoDB COMMENT='Aggregate Graph Graph Items';

--
-- Table structure for table `aggregate_graphs_items`
--

CREATE TABLE `aggregate_graphs_items` (
  `aggregate_graph_id` int(10) unsigned NOT NULL,
  `local_graph_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`aggregate_graph_id`,`local_graph_id`)
) ENGINE=InnoDB COMMENT='Aggregate Graph Items';

--
-- Table structure for table `automation_devices`
--

CREATE TABLE `automation_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `network_id` int(10) unsigned NOT NULL DEFAULT '0',
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `ip` varchar(17) NOT NULL DEFAULT '',
  `community` varchar(100) NOT NULL DEFAULT '',
  `snmp_version` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `snmp_port` int(10) unsigned NOT NULL DEFAULT '161',
  `snmp_username` varchar(50) DEFAULT NULL,
  `snmp_password` varchar(50) DEFAULT NULL,
  `snmp_auth_protocol` char(5) DEFAULT '',
  `snmp_priv_passphrase` varchar(200) DEFAULT '',
  `snmp_priv_protocol` char(6) DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(30) DEFAULT '',
  `sysName` varchar(100) NOT NULL DEFAULT '',
  `sysLocation` varchar(255) NOT NULL DEFAULT '',
  `sysContact` varchar(255) NOT NULL DEFAULT '',
  `sysDescr` varchar(255) NOT NULL DEFAULT '',
  `sysUptime` int(32) NOT NULL DEFAULT '0',
  `os` varchar(64) NOT NULL DEFAULT '',
  `snmp` tinyint(4) NOT NULL DEFAULT '0',
  `known` tinyint(4) NOT NULL DEFAULT '0',
  `up` tinyint(4) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB COMMENT='Table of Discovered Devices';

--
-- Table structure for table `automation_graph_rule_items`
--

CREATE TABLE `automation_graph_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `operation` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `operator` smallint(3) unsigned NOT NULL DEFAULT '0',
  `pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 COMMENT='Automation Graph Rule Items';

--
-- Dumping data for table `automation_graph_rule_items`
--

INSERT INTO `automation_graph_rule_items` VALUES (1,1,1,0,'ifOperStatus',7,'Up'),(2,1,2,1,'ifIP',16,''),(3,1,3,1,'ifHwAddr',16,''),(4,2,1,0,'ifOperStatus',7,'Up'),(5,2,2,1,'ifIP',16,''),(6,2,3,1,'ifHwAddr',16,'');

--
-- Table structure for table `automation_graph_rules`
--

CREATE TABLE `automation_graph_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `snmp_query_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `graph_type_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 COMMENT='Automation Graph Rules';

--
-- Dumping data for table `automation_graph_rules`
--

INSERT INTO `automation_graph_rules` VALUES (1,'Traffic 64 bit Server',1,14,''),(2,'Traffic 64 bit Server Linux',1,14,''),(3,'Disk Space',8,18,'');

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
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rule_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `operation` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `operator` smallint(3) unsigned NOT NULL DEFAULT '0',
  `pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 COMMENT='Automation Match Rule Items';

--
-- Dumping data for table `automation_match_rule_items`
--

INSERT INTO `automation_match_rule_items` VALUES (1,1,1,1,0,'h.description',14,''),(2,1,1,2,1,'h.snmp_version',12,'2'),(3,1,3,1,0,'ht.name',1,'Linux'),(4,2,1,1,0,'ht.name',1,'Linux'),(5,2,1,2,1,'h.snmp_version',12,'2'),(6,2,3,1,0,'ht.name',1,'SNMP'),(7,2,3,2,1,'gt.name',1,'Traffic');

--
-- Table structure for table `automation_networks`
--

CREATE TABLE `automation_networks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poller_id` int(10) unsigned DEFAULT '1',
  `name` varchar(128) NOT NULL DEFAULT '' COMMENT 'The name for this network',
  `subnet_range` varchar(255) NOT NULL DEFAULT '' COMMENT 'Defined subnet ranges for discovery',
  `dns_servers` varchar(128) NOT NULL DEFAULT '' COMMENT 'DNS Servers to use for name resolution',
  `enabled` char(2) DEFAULT '',
  `snmp_id` int(10) unsigned DEFAULT NULL,
  `enable_netbios` char(2) DEFAULT '',
  `add_to_cacti` char(2) DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=2 COMMENT='Stores scanning subnet definitions';

--
-- Dumping data for table `automation_networks`
--

INSERT INTO `automation_networks` VALUES (1,1,'Test Network','192.168.1.0/24','','',1,'on','',254,14,8,2,22,400,1,2,10,1200,'2015-05-17 16:15','0000-00-00 00:00:00',2,'4','1,2,6','1,2,3,4,6,7,11,12,14,15,17,19,26,32','','',40.178689002991,'2015-05-19 02:23:22','','on');

--
-- Table structure for table `automation_processes`
--

CREATE TABLE `automation_processes` (
  `pid` int(8) unsigned NOT NULL,
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
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 COMMENT='Group of SNMP Option Sets';

--
-- Dumping data for table `automation_snmp`
--

INSERT INTO `automation_snmp` VALUES (1,'Default Option Set');

--
-- Table structure for table `automation_snmp_items`
--

CREATE TABLE `automation_snmp_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `snmp_id` int(10) unsigned NOT NULL DEFAULT '0',
  `sequence` int(10) unsigned NOT NULL DEFAULT '0',
  `snmp_version` varchar(100) NOT NULL DEFAULT '',
  `snmp_readstring` varchar(100) NOT NULL,
  `snmp_port` int(10) NOT NULL DEFAULT '161',
  `snmp_timeout` int(10) unsigned NOT NULL DEFAULT '500',
  `snmp_retries` tinyint(11) unsigned NOT NULL DEFAULT '3',
  `max_oids` int(12) unsigned DEFAULT '10',
  `snmp_username` varchar(50) DEFAULT NULL,
  `snmp_password` varchar(50) DEFAULT NULL,
  `snmp_auth_protocol` char(5) DEFAULT '',
  `snmp_priv_passphrase` varchar(200) DEFAULT '',
  `snmp_priv_protocol` char(6) DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(30) DEFAULT '',
  PRIMARY KEY (`id`,`snmp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 COMMENT='Set of SNMP Options';

--
-- Dumping data for table `automation_snmp_items`
--

INSERT INTO `automation_snmp_items` VALUES (1,1,1,'2','public',161,1000,3,10,'admin','baseball','MD5','','DES','',''),(2,1,2,'2','private',161,1000,3,10,'admin','baseball','MD5','','DES','','');

--
-- Table structure for table `automation_templates`
--

CREATE TABLE `automation_templates` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `host_template` int(8) NOT NULL DEFAULT '0',
  `availability_method` int(10) unsigned DEFAULT '2',
  `sysDescr` varchar(255) DEFAULT '',
  `sysName` varchar(255) DEFAULT '',
  `sysOid` varchar(60) DEFAULT '',
  `sequence` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 COMMENT='Templates of SNMP Sys variables used for automation';

--
-- Dumping data for table `automation_templates`
--

INSERT INTO `automation_templates` VALUES (1,3,2,'Linux','','',2),(2,1,2,'HP ETHERNET','','',1);

--
-- Table structure for table `automation_tree_rule_items`
--

CREATE TABLE `automation_tree_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `sort_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `propagate_changes` char(2) DEFAULT '',
  `search_pattern` varchar(255) NOT NULL DEFAULT '',
  `replace_pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 COMMENT='Automation Tree Rule Items';

--
-- Dumping data for table `automation_tree_rule_items`
--

INSERT INTO `automation_tree_rule_items` VALUES (1,1,1,'ht.name',1,'','^(.*)\\s*Linux\\s*(.*)$','${1}\\n${2}'),(2,1,2,'h.hostname',1,'','^(\\w*)\\s*(\\w*)\\s*(\\w*).*$',''),(3,2,1,'0',2,'on','Traffic',''),(4,2,2,'gtg.title_cache',1,'','^(.*)\\s*-\\s*Traffic -\\s*(.*)$','${1}\\n${2}');

--
-- Table structure for table `automation_tree_rules`
--

CREATE TABLE `automation_tree_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `tree_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `tree_item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `leaf_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `host_grouping_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 COMMENT='Automation Tree Rules';

--
-- Dumping data for table `automation_tree_rules`
--

INSERT INTO `automation_tree_rules` VALUES (1,'New Device',1,0,3,0,''),(2,'New Graph',1,0,2,0,'');

--
-- Table structure for table `cdef`
--

CREATE TABLE cdef (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  system mediumint(8) unsigned NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL default '',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
  type tinyint(2) NOT NULL default '0',
  value varchar(150) NOT NULL default '',
  PRIMARY KEY (id),
  KEY cdef_id (cdef_id)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB COMMENT='Color Templates';

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
  `color_template_item_id` int(12) unsigned NOT NULL AUTO_INCREMENT,
  `color_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`color_template_item_id`)
) ENGINE=InnoDB COMMENT='Color Items for Color Templates';

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
) ENGINE=InnoDB;

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
  input_string varchar(255) default NULL,
  type_id tinyint(2) NOT NULL default '0',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
  data_template_data_id mediumint(8) unsigned NOT NULL default '0',
  t_value char(2) default NULL,
  value text,
  PRIMARY KEY (data_input_field_id,data_template_data_id),
  KEY t_value (t_value)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_input_data`
--

INSERT INTO data_input_data VALUES (14,1,'on','');
INSERT INTO data_input_data VALUES (13,1,'on','');
INSERT INTO data_input_data VALUES (12,1,'on','');
INSERT INTO data_input_data VALUES (14,2,'on','');
INSERT INTO data_input_data VALUES (13,2,'on','');
INSERT INTO data_input_data VALUES (12,2,'on','');
INSERT INTO data_input_data VALUES (14,3,'on','');
INSERT INTO data_input_data VALUES (13,3,'on','');
INSERT INTO data_input_data VALUES (12,3,'on','');
INSERT INTO data_input_data VALUES (1,4,'','');
INSERT INTO data_input_data VALUES (1,5,'','');
INSERT INTO data_input_data VALUES (1,6,'','');
INSERT INTO data_input_data VALUES (14,7,'on','');
INSERT INTO data_input_data VALUES (13,7,'on','');
INSERT INTO data_input_data VALUES (12,7,'on','');
INSERT INTO data_input_data VALUES (14,8,'on','');
INSERT INTO data_input_data VALUES (13,8,'on','');
INSERT INTO data_input_data VALUES (12,8,'on','');
INSERT INTO data_input_data VALUES (14,9,'on','');
INSERT INTO data_input_data VALUES (13,9,'on','');
INSERT INTO data_input_data VALUES (12,9,'on','');
INSERT INTO data_input_data VALUES (14,10,'on','');
INSERT INTO data_input_data VALUES (13,10,'on','');
INSERT INTO data_input_data VALUES (12,10,'on','');
INSERT INTO data_input_data VALUES (22,12,'','Buffers:');
INSERT INTO data_input_data VALUES (22,13,'','MemFree:');
INSERT INTO data_input_data VALUES (22,14,'','^Cached:');
INSERT INTO data_input_data VALUES (22,15,'','SwapFree:');
INSERT INTO data_input_data VALUES (29,18,'','');
INSERT INTO data_input_data VALUES (1,19,'','');
INSERT INTO data_input_data VALUES (2,19,'','');
INSERT INTO data_input_data VALUES (6,21,'','.1.3.6.1.2.1.25.3.3.1.2.1');
INSERT INTO data_input_data VALUES (1,27,'','');
INSERT INTO data_input_data VALUES (6,28,'','.1.3.6.1.4.1.9.9.109.1.1.1.1.3.1');
INSERT INTO data_input_data VALUES (6,29,'','.1.3.6.1.4.1.9.9.109.1.1.1.1.4.1');
INSERT INTO data_input_data VALUES (1,30,'','');
INSERT INTO data_input_data VALUES (1,31,'','');
INSERT INTO data_input_data VALUES (1,32,'','');
INSERT INTO data_input_data VALUES (1,33,'','');
INSERT INTO data_input_data VALUES (1,34,'','');
INSERT INTO data_input_data VALUES (14,35,'on','');
INSERT INTO data_input_data VALUES (13,35,'on','');
INSERT INTO data_input_data VALUES (12,35,'on','');
INSERT INTO data_input_data VALUES (14,36,'on','');
INSERT INTO data_input_data VALUES (13,36,'on','');
INSERT INTO data_input_data VALUES (12,36,'on','');
INSERT INTO data_input_data VALUES (1,22,'','');
INSERT INTO data_input_data VALUES (1,23,'','');
INSERT INTO data_input_data VALUES (1,24,'','');
INSERT INTO data_input_data VALUES (1,25,'','');
INSERT INTO data_input_data VALUES (1,26,'','');
INSERT INTO data_input_data VALUES (33,37,'on','');
INSERT INTO data_input_data VALUES (32,37,'on','');
INSERT INTO data_input_data VALUES (31,37,'on','');
INSERT INTO data_input_data VALUES (14,38,'on','');
INSERT INTO data_input_data VALUES (13,38,'on','');
INSERT INTO data_input_data VALUES (12,38,'on','');
INSERT INTO data_input_data VALUES (14,39,'on','');
INSERT INTO data_input_data VALUES (13,39,'on','');
INSERT INTO data_input_data VALUES (12,39,'on','');
INSERT INTO data_input_data VALUES (14,40,'on','');
INSERT INTO data_input_data VALUES (13,40,'on','');
INSERT INTO data_input_data VALUES (12,40,'on','');
INSERT INTO data_input_data VALUES (14,41,'on','');
INSERT INTO data_input_data VALUES (13,41,'on','');
INSERT INTO data_input_data VALUES (12,41,'on','');
INSERT INTO data_input_data VALUES (14,55,'on','');
INSERT INTO data_input_data VALUES (13,55,'on','');
INSERT INTO data_input_data VALUES (12,55,'on','');
INSERT INTO data_input_data VALUES (37,56,'on','');
INSERT INTO data_input_data VALUES (36,56,'on','');
INSERT INTO data_input_data VALUES (35,56,'on','');
INSERT INTO data_input_data VALUES (37,57,'on','');
INSERT INTO data_input_data VALUES (36,57,'on','');
INSERT INTO data_input_data VALUES (35,57,'on','');
INSERT INTO data_input_data VALUES (1,58,'','');
INSERT INTO data_input_data VALUES (1,59,'','');
INSERT INTO data_input_data VALUES (1,20,'','');
INSERT INTO data_input_data VALUES (5,6,'','');
INSERT INTO data_input_data VALUES (22,62,NULL,'MemFree:');
INSERT INTO data_input_data VALUES (22,63,NULL,'SwapFree:');
INSERT INTO data_input_data VALUES (4,6,'','');
INSERT INTO data_input_data VALUES (3,6,'','');
INSERT INTO data_input_data VALUES (2,6,'','');
INSERT INTO data_input_data VALUES (6,69,'on','');
INSERT INTO data_input_data VALUES (1,68,'','');
INSERT INTO data_input_data VALUES (2,68,'','');
INSERT INTO data_input_data VALUES (6,6,'','.1.3.6.1.4.1.2021.11.51.0');
INSERT INTO data_input_data VALUES (2,27,'','');
INSERT INTO data_input_data VALUES (3,27,'','');
INSERT INTO data_input_data VALUES (4,27,'','');
INSERT INTO data_input_data VALUES (5,27,'','');
INSERT INTO data_input_data VALUES (6,27,'','.1.3.6.1.4.1.9.2.1.58.0');
INSERT INTO data_input_data VALUES (2,59,'','');
INSERT INTO data_input_data VALUES (3,59,'','');
INSERT INTO data_input_data VALUES (4,59,'','');
INSERT INTO data_input_data VALUES (5,59,'','');
INSERT INTO data_input_data VALUES (6,59,'','.1.3.6.1.2.1.25.1.5.0');
INSERT INTO data_input_data VALUES (2,58,'','');
INSERT INTO data_input_data VALUES (3,58,'','');
INSERT INTO data_input_data VALUES (4,58,'','');
INSERT INTO data_input_data VALUES (5,58,'','');
INSERT INTO data_input_data VALUES (6,58,'','.1.3.6.1.2.1.25.1.6.0');
INSERT INTO data_input_data VALUES (2,24,'','');
INSERT INTO data_input_data VALUES (3,24,'','');
INSERT INTO data_input_data VALUES (4,24,'','');
INSERT INTO data_input_data VALUES (5,24,'','');
INSERT INTO data_input_data VALUES (6,24,'','.1.3.6.1.4.1.23.2.28.2.5.0');
INSERT INTO data_input_data VALUES (2,25,'','');
INSERT INTO data_input_data VALUES (3,25,'','');
INSERT INTO data_input_data VALUES (4,25,'','');
INSERT INTO data_input_data VALUES (5,25,'','');
INSERT INTO data_input_data VALUES (6,25,'','.1.3.6.1.4.1.23.2.28.2.6.0');
INSERT INTO data_input_data VALUES (2,22,'','');
INSERT INTO data_input_data VALUES (3,22,'','');
INSERT INTO data_input_data VALUES (4,22,'','');
INSERT INTO data_input_data VALUES (5,22,'','');
INSERT INTO data_input_data VALUES (6,22,'','.1.3.6.1.4.1.23.2.28.2.1.0');
INSERT INTO data_input_data VALUES (2,23,'','');
INSERT INTO data_input_data VALUES (3,23,'','');
INSERT INTO data_input_data VALUES (4,23,'','');
INSERT INTO data_input_data VALUES (5,23,'','');
INSERT INTO data_input_data VALUES (6,23,'','.1.3.6.1.4.1.23.2.28.2.2.0');
INSERT INTO data_input_data VALUES (2,26,'','');
INSERT INTO data_input_data VALUES (3,26,'','');
INSERT INTO data_input_data VALUES (4,26,'','');
INSERT INTO data_input_data VALUES (5,26,'','');
INSERT INTO data_input_data VALUES (6,26,'','.1.3.6.1.4.1.23.2.28.2.7.0');
INSERT INTO data_input_data VALUES (2,20,'','');
INSERT INTO data_input_data VALUES (3,20,'','');
INSERT INTO data_input_data VALUES (4,20,'','');
INSERT INTO data_input_data VALUES (5,20,'','');
INSERT INTO data_input_data VALUES (6,20,'','.1.3.6.1.4.1.23.2.28.3.2.0');
INSERT INTO data_input_data VALUES (3,19,'','');
INSERT INTO data_input_data VALUES (4,19,'','');
INSERT INTO data_input_data VALUES (5,19,'','');
INSERT INTO data_input_data VALUES (6,19,'','.1.3.6.1.4.1.23.2.28.3.1');
INSERT INTO data_input_data VALUES (2,4,'','');
INSERT INTO data_input_data VALUES (3,4,'','');
INSERT INTO data_input_data VALUES (4,4,'','');
INSERT INTO data_input_data VALUES (5,4,'','');
INSERT INTO data_input_data VALUES (6,4,'','.1.3.6.1.4.1.2021.11.52.0');
INSERT INTO data_input_data VALUES (2,5,'','');
INSERT INTO data_input_data VALUES (3,5,'','');
INSERT INTO data_input_data VALUES (4,5,'','');
INSERT INTO data_input_data VALUES (5,5,'','');
INSERT INTO data_input_data VALUES (6,5,'','.1.3.6.1.4.1.2021.11.50.0');
INSERT INTO data_input_data VALUES (2,30,'','');
INSERT INTO data_input_data VALUES (3,30,'','');
INSERT INTO data_input_data VALUES (4,30,'','');
INSERT INTO data_input_data VALUES (5,30,'','');
INSERT INTO data_input_data VALUES (6,30,'','.1.3.6.1.4.1.2021.10.1.3.1');
INSERT INTO data_input_data VALUES (2,32,'','');
INSERT INTO data_input_data VALUES (3,32,'','');
INSERT INTO data_input_data VALUES (4,32,'','');
INSERT INTO data_input_data VALUES (5,32,'','');
INSERT INTO data_input_data VALUES (6,32,'','.1.3.6.1.4.1.2021.10.1.3.3');
INSERT INTO data_input_data VALUES (2,31,'','');
INSERT INTO data_input_data VALUES (3,31,'','');
INSERT INTO data_input_data VALUES (4,31,'','');
INSERT INTO data_input_data VALUES (5,31,'','');
INSERT INTO data_input_data VALUES (6,31,'','.1.3.6.1.4.1.2021.10.1.3.2');
INSERT INTO data_input_data VALUES (2,33,'','');
INSERT INTO data_input_data VALUES (3,33,'','');
INSERT INTO data_input_data VALUES (4,33,'','');
INSERT INTO data_input_data VALUES (5,33,'','');
INSERT INTO data_input_data VALUES (6,33,'','.1.3.6.1.4.1.2021.4.14.0');
INSERT INTO data_input_data VALUES (3,68,'','');
INSERT INTO data_input_data VALUES (4,68,'','');
INSERT INTO data_input_data VALUES (5,68,'','');
INSERT INTO data_input_data VALUES (6,68,'','.1.3.6.1.4.1.2021.4.15.0');
INSERT INTO data_input_data VALUES (2,34,'','');
INSERT INTO data_input_data VALUES (3,34,'','');
INSERT INTO data_input_data VALUES (4,34,'','');
INSERT INTO data_input_data VALUES (5,34,'','');
INSERT INTO data_input_data VALUES (6,34,'','.1.3.6.1.4.1.2021.4.6.0');
INSERT INTO data_input_data VALUES (20,17,'','');
INSERT INTO data_input_data VALUES (20,65,NULL,'');

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
  KEY type_code (type_code)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_input_fields`
--

INSERT INTO data_input_fields VALUES (1,'92f5906c8dc0f964b41f4253df582c38',1,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO data_input_fields VALUES (2,'32285d5bf16e56c478f5e83f32cda9ef',1,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO data_input_fields VALUES (3,'ad14ac90641aed388139f6ba86a2e48b',1,'SNMP Username','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO data_input_fields VALUES (4,'9c55a74bd571b4f00a96fd4b793278c6',1,'SNMP Password','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO data_input_fields VALUES (5,'012ccb1d3687d3edb29c002ea66e72da',1,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','on');
INSERT INTO data_input_fields VALUES (6,'4276a5ec6e3fe33995129041b1909762',1,'OID','oid','in','',0,'snmp_oid','','');
INSERT INTO data_input_fields VALUES (7,'617cdc8a230615e59f06f361ef6e7728',2,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO data_input_fields VALUES (8,'acb449d1451e8a2a655c2c99d31142c7',2,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO data_input_fields VALUES (9,'f4facc5e2ca7ebee621f09bc6d9fc792',2,'SNMP Username (v3)','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO data_input_fields VALUES (10,'1cc1493a6781af2c478fa4de971531cf',2,'SNMP Password (v3)','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO data_input_fields VALUES (11,'b5c23f246559df38662c255f4aa21d6b',2,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','');
INSERT INTO data_input_fields VALUES (12,'6027a919c7c7731fbe095b6f53ab127b',2,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO data_input_fields VALUES (13,'cbbe5c1ddfb264a6e5d509ce1c78c95f',2,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO data_input_fields VALUES (14,'e6deda7be0f391399c5130e7c4a48b28',2,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO data_input_fields VALUES (15,'edfd72783ad02df128ff82fc9324b4b9',3,'Disk Partition','partition','in','',1,'','','');
INSERT INTO data_input_fields VALUES (16,'8b75fb61d288f0b5fc0bd3056af3689b',3,'Kilobytes Free','kilobytes','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (17,'363588d49b263d30aecb683c52774f39',4,'1 Minute Average','1min','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (18,'ad139a9e1d69881da36fca07889abf58',4,'5 Minute Average','5min','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (19,'5db9fee64824c08258c7ff6f8bc53337',4,'10 Minute Average','10min','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (20,'c0cfd0beae5e79927c5a360076706820',5,'Username (Optional)','username','in','',1,'','','on');
INSERT INTO data_input_fields VALUES (21,'52c58ad414d9a2a83b00a7a51be75a53',5,'Logged In Users','users','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (22,'05eb5d710f0814871b8515845521f8d7',6,'Grep String','grepstr','in','',1,'','','');
INSERT INTO data_input_fields VALUES (23,'86cb1cbfde66279dbc7f1144f43a3219',6,'Result (in Kilobytes)','kilobytes','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (24,'d5a8dd5fbe6a5af11667c0039af41386',7,'Number of Processes','proc','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (25,'8848cdcae831595951a3f6af04eec93b',8,'Grep String','grepstr','in','',1,'','','on');
INSERT INTO data_input_fields VALUES (26,'3d1288d33008430ce354e8b9c162f7ff',8,'Connections','connections','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (27,'c6af570bb2ed9c84abf32033702e2860',9,'(Optional) Log Path','log_path','in','',1,'','','on');
INSERT INTO data_input_fields VALUES (28,'f9389860f5c5340c9b27fca0b4ee5e71',9,'Web Hits','webhits','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (29,'5fbadb91ad66f203463c1187fe7bd9d5',10,'IP Address','ip','in','',1,'hostname','','');
INSERT INTO data_input_fields VALUES (30,'6ac4330d123c69067d36a933d105e89a',10,'Milliseconds','out_ms','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (31,'d39556ecad6166701bfb0e28c5a11108',11,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO data_input_fields VALUES (32,'3b7caa46eb809fc238de6ef18b6e10d5',11,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO data_input_fields VALUES (33,'74af2e42dc12956c4817c2ef5d9983f9',11,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO data_input_fields VALUES (34,'8ae57f09f787656bf4ac541e8bd12537',11,'Output Value','output','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (35,'172b4b0eacee4948c6479f587b62e512',12,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO data_input_fields VALUES (36,'30fb5d5bcf3d66bb5abe88596f357c26',12,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO data_input_fields VALUES (37,'31112c85ae4ff821d3b288336288818c',12,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO data_input_fields VALUES (38,'5be8fa85472d89c621790b43510b5043',12,'Output Value','output','out','on',0,'','','');
INSERT INTO data_input_fields VALUES (39,'c1f36ee60c3dc98945556d57f26e475b',2,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO data_input_fields VALUES (40,'fc64b99742ec417cc424dbf8c7692d36',1,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO data_input_fields VALUES (41,'20832ce12f099c8e54140793a091af90',1,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO data_input_fields VALUES (42,'c60c9aac1e1b3555ea0620b8bbfd82cb',1,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO data_input_fields VALUES (43,'feda162701240101bc74148415ef415a',1,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');
INSERT INTO data_input_fields VALUES (44,'2cf7129ad3ff819a7a7ac189bee48ce8',2,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO data_input_fields VALUES (45,'6b13ac0a0194e171d241d4b06f913158',2,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO data_input_fields VALUES (46,'3a33d4fc65b8329ab2ac46a36da26b72',2,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');

--
-- Table structure for table `data_local`
--

CREATE TABLE data_local (
  id mediumint(8) unsigned NOT NULL auto_increment,
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) NOT NULL default '0',
  snmp_index varchar(255) NOT NULL default '',
  PRIMARY KEY (id),
  KEY data_template_id (data_template_id),
  KEY snmp_query_id (snmp_query_id),
  KEY snmp_index (snmp_index(191)),
  KEY host_id (host_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_local`
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='Stores Data Source Profiles';

--
-- Dumping data for table `data_source_profiles`
--

INSERT INTO `data_source_profiles` VALUES (1,'d62c52891f4f9688729a5bc9fad91b18','System Default',300,600,0.5,'on');
INSERT INTO `data_source_profiles` VALUES (2,'c0dd0e46b9ca268e7ed4162d329f9215','High Collection Rate',30,1200,0.5,'');

--
-- Table structure for table `data_source_profiles_cf`
--

CREATE TABLE `data_source_profiles_cf` (
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `consolidation_function_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`data_source_profile_id`,`consolidation_function_id`),
  KEY `data_source_profile_id` (`data_source_profile_id`)
) ENGINE=InnoDB COMMENT='Maps the Data Source Profile Consolidation Functions';

--
-- Dumping data for table `data_source_profiles_cf`
--

INSERT INTO `data_source_profiles_cf` VALUES (1,1),(1,2),(1,3),(1,4);
INSERT INTO `data_source_profiles_cf` VALUES (2,1),(2,2),(2,3),(2,4);

--
-- Table structure for table `data_source_profiles_rra`
--

CREATE TABLE `data_source_profiles_rra` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `steps` int(10) unsigned DEFAULT '1',
  `rows` int(10) unsigned NOT NULL DEFAULT '700',
  PRIMARY KEY (`id`),
  KEY `data_source_profile_id` (`data_source_profile_id`)
) ENGINE=InnoDB COMMENT='Stores RRA Definitions for Data Source Profiles';

--
-- Dumping data for table `data_source_profiles_rra`
--

INSERT INTO `data_source_profiles_rra` VALUES (1,1,'Daily (5 Minute Average)',1,600);
INSERT INTO `data_source_profiles_rra` VALUES (2,1,'Weekly (30 Minute Average)',6,700);
INSERT INTO `data_source_profiles_rra` VALUES (3,1,'Monthly (2 Hour Average)',24,775);
INSERT INTO `data_source_profiles_rra` VALUES (4,1,'Yearly (1 Day Average)',288,797);
INSERT INTO `data_source_profiles_rra` VALUES (5,2,'30 Second Samples',1,1500);
INSERT INTO `data_source_profiles_rra` VALUES (6,2,'15 Minute Average',30,1346);
INSERT INTO `data_source_profiles_rra` VALUES (7,2,'1 Hour Average',120,1445);
INSERT INTO `data_source_profiles_rra` VALUES (8,2,'4 Hour Average',480,4380);


--
-- Table structure for table `data_source_purge_action`
--

CREATE TABLE `data_source_purge_action` (
  `id` integer UNSIGNED auto_increment,
  `name` varchar(128) NOT NULL default '',
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `action` tinyint(2) NOT NULL default 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY name (`name`))
  ENGINE=InnoDB
  COMMENT='RRD Cleaner File Actions';

--
-- Table structure for table `data_source_purge_temp`
--

CREATE TABLE `data_source_purge_temp` (
  `id` integer UNSIGNED auto_increment,
  `name_cache` varchar(255) NOT NULL default '',
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `name` varchar(128) NOT NULL default '',
  `size` integer UNSIGNED NOT NULL default '0',
  `last_mod` TIMESTAMP NOT NULL default '0000-00-00 00:00:00',
  `in_cacti` tinyint NOT NULL default '0',
  `data_template_id` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY name (`name`),
  KEY local_data_id (`local_data_id`),
  KEY in_cacti (`in_cacti`),
  KEY data_template_id (`data_template_id`))
  ENGINE=InnoDB
  COMMENT='RRD Cleaner File Repository';

	
--
-- Table structure for table `data_source_stats_daily`
--
	
CREATE TABLE `data_source_stats_daily` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE DEFAULT NULL,
  `peak` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=InnoDB;

--
-- Table structure for table `data_source_stats_hourly`
--

CREATE TABLE `data_source_stats_hourly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE DEFAULT NULL,
  `peak` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=InnoDB;

--
-- Table structure for table `data_source_stats_hourly_cache`
--

CREATE TABLE `data_source_stats_hourly_cache` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `value` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`time`,`rrd_name`),
  KEY `time` USING BTREE (`time`)
  ) ENGINE=MEMORY;

--
-- Table structure for table `data_source_stats_hourly_last`
--

CREATE TABLE `data_source_stats_hourly_last` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `value` DOUBLE DEFAULT NULL,
  `calculated` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=MEMORY;

--
-- Table structure for table `data_source_stats_monthly`
--

CREATE TABLE `data_source_stats_monthly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE DEFAULT NULL,
  `peak` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=InnoDB;

--
-- Table structure for table `data_source_stats_weekly`
--

CREATE TABLE `data_source_stats_weekly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE DEFAULT NULL,
  `peak` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=InnoDB;

--
-- Table structure for table `data_source_stats_yearly`
--

CREATE TABLE `data_source_stats_yearly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE DEFAULT NULL,
  `peak` DOUBLE DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
  ) ENGINE=InnoDB;

--
-- Table structure for table `data_template`
--

CREATE TABLE data_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(150) NOT NULL default '',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_template`
--

INSERT INTO data_template VALUES (3,'c8a8f50f5f4a465368222594c5709ede','ucd/net - Hard Drive Space');
INSERT INTO data_template VALUES (4,'cdfed2d401723d2f41fc239d4ce249c7','ucd/net - CPU Usage - System');
INSERT INTO data_template VALUES (5,'a27e816377d2ac6434a87c494559c726','ucd/net - CPU Usage - User');
INSERT INTO data_template VALUES (6,'c06c3d20eccb9598939dc597701ff574','ucd/net - CPU Usage - Nice');
INSERT INTO data_template VALUES (11,'9e72511e127de200733eb502eb818e1d','Unix - Load Average');
INSERT INTO data_template VALUES (13,'dc33aa9a8e71fb7c61ec0e7a6da074aa','Linux - Memory - Free');
INSERT INTO data_template VALUES (15,'41f55087d067142d702dd3c73c98f020','Linux - Memory - Free Swap');
INSERT INTO data_template VALUES (16,'9b8c92d3c32703900ff7dd653bfc9cd8','Unix - Processes');
INSERT INTO data_template VALUES (17,'c221c2164c585b6da378013a7a6a2c13','Unix - Logged in Users');
INSERT INTO data_template VALUES (18,'a30a81cb1de65b52b7da542c8df3f188','Unix - Ping Host');
INSERT INTO data_template VALUES (27,'e9def3a0e409f517cb804dfeba4ccd90','Cisco Router - 5 Minute CPU');
INSERT INTO data_template VALUES (30,'9b82d44eb563027659683765f92c9757','ucd/net - Load Average - 1 Minute');
INSERT INTO data_template VALUES (31,'87847714d19f405ff3c74f3341b3f940','ucd/net - Load Average - 5 Minute');
INSERT INTO data_template VALUES (32,'308ac157f24e2763f8cd828a80b3e5ff','ucd/net - Load Average - 15 Minute');
INSERT INTO data_template VALUES (33,'797a3e92b0039841b52e441a2823a6fb','ucd/net - Memory - Buffers');
INSERT INTO data_template VALUES (34,'fa15932d3cab0da2ab94c69b1a9f5ca7','ucd/net - Memory - Free');
INSERT INTO data_template VALUES (37,'e4ac6919d4f6f21ec5b281a1d6ac4d4e','Unix - Hard Drive Space');
INSERT INTO data_template VALUES (38,'36335cd98633963a575b70639cd2fdad','Interface - Errors/Discards');
INSERT INTO data_template VALUES (39,'2f654f7d69ac71a5d56b1db8543ccad3','Interface - Unicast Packets');
INSERT INTO data_template VALUES (40,'c84e511401a747409053c90ba910d0fe','Interface - Non-Unicast Packets');
INSERT INTO data_template VALUES (41,'6632e1e0b58a565c135d7ff90440c335','Interface - Traffic');
INSERT INTO data_template VALUES (43,'d814fa3b79bd0f8933b6e0834d3f16d0','Host MIB - Hard Drive Space');
INSERT INTO data_template VALUES (44,'f6e7d21c19434666bbdac00ccef9932f','Host MIB - CPU Utilization');
INSERT INTO data_template VALUES (45,'f383db441d1c246cff8482f15e184e5f','Host MIB - Processes');
INSERT INTO data_template VALUES (46,'2ef027cc76d75720ee5f7a528f0f1fda','Host MIB - Logged in Users');
INSERT INTO data_template VALUES (47,'a274deec1f78654dca6c446ba75ebca4','ucd/net - Memory - Cache');
INSERT INTO data_template VALUES (48,'d429e4a6019c91e6e84562593c1968ca','SNMP - Generic OID Template');

--
-- Table structure for table `data_template_data`
--

CREATE TABLE data_template_data (
  id mediumint(8) unsigned NOT NULL auto_increment,
  local_data_template_data_id mediumint(8) unsigned NOT NULL default '0',
  local_data_id mediumint(8) unsigned NOT NULL default '0',
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
  KEY data_input_id (data_input_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_template_data`
--

INSERT INTO data_template_data VALUES (3,0,0,3,2,'on','|host_description| - Hard Drive Space','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (4,0,0,4,1,'','|host_description| - CPU Usage - System','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (5,0,0,5,1,'','|host_description| - CPU Usage - User','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (6,0,0,6,1,'','|host_description| - CPU Usage - Nice','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (11,0,0,11,4,'','|host_description| - Load Average','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (13,0,0,13,6,'','|host_description| - Memory - Free','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (15,0,0,15,6,'','|host_description| - Memory - Free Swap','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (16,0,0,16,7,'','|host_description| - Processes','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (17,0,0,17,5,'','|host_description| - Logged in Users','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (18,0,0,18,10,'','|host_description| - Ping Host','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (27,0,0,27,1,'','|host_description| - 5 Minute CPU','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (30,0,0,30,1,'','|host_description| - Load Average - 1 Minute','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (31,0,0,31,1,'','|host_description| - Load Average - 5 Minute','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (32,0,0,32,1,'','|host_description| - Load Average - 15 Minute','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (33,0,0,33,1,'','|host_description| - Memory - Buffers','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (34,0,0,34,1,'','|host_description| - Memory - Free','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (37,0,0,37,11,'on','|host_description| - Hard Drive Space','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (38,0,0,38,2,'on','|host_description| - Errors/Discards','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (39,0,0,39,2,'on','|host_description| - Unicast Packets','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (40,0,0,40,2,'on','|host_description| - Non-Unicast Packets','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (41,0,0,41,2,'on','|host_description| - Traffic','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (56,0,0,43,12,'','|host_description| - Hard Drive Space','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (57,0,0,44,12,'','|host_description| - CPU Utilization','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (58,0,0,45,1,'','|host_description| - Processes','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (59,0,0,46,1,'','|host_description| - Logged in Users','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (68,0,0,47,1,'','|host_description| - Memory - Cache','',NULL,'','on','',300,'',1);
INSERT INTO data_template_data VALUES (69,0,0,48,1,'on','|host_description| -','',NULL,'','on','',300,'',1);

--
-- Table structure for table `data_template_rrd`
--

CREATE TABLE data_template_rrd (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  local_data_template_rrd_id mediumint(8) unsigned NOT NULL default '0',
  local_data_id mediumint(8) unsigned NOT NULL default '0',
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
  KEY local_data_id (local_data_id),
  KEY data_template_id (data_template_id),
  KEY local_data_template_rrd_id (local_data_template_rrd_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `data_template_rrd`
--

INSERT INTO data_template_rrd VALUES (3,'2d53f9c76767a2ae8909f4152fd473a4',0,0,3,'','U','','0','',600,'',1,'','hdd_free','',0);
INSERT INTO data_template_rrd VALUES (4,'93d91aa7a3cc5473e7b195d5d6e6e675',0,0,3,'','U','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (5,'7bee7987bbf30a3bc429d2a67c6b2595',0,0,4,'','100','','0','',600,'',2,'','cpu_system','',0);
INSERT INTO data_template_rrd VALUES (6,'ddccd7fbdece499da0235b4098b87f9e',0,0,5,'','100','','0','',600,'',2,'','cpu_user','',0);
INSERT INTO data_template_rrd VALUES (7,'122ab2097f8c6403b7b90cde7b9e2bc2',0,0,6,'','100','','0','',600,'',2,'','cpu_nice','',0);
INSERT INTO data_template_rrd VALUES (12,'8175ca431c8fe50efff5a1d3ae51b55d',0,0,11,'','500','','0','',600,'',1,'','load_1min','',17);
INSERT INTO data_template_rrd VALUES (13,'a2eeb8acd6ea01cd0e3ac852965c0eb6',0,0,11,'','500','','0','',600,'',1,'','load_5min','',18);
INSERT INTO data_template_rrd VALUES (14,'9f951b7fb3b19285a411aebb5254a831',0,0,11,'','500','','0','',600,'',1,'','load_15min','',19);
INSERT INTO data_template_rrd VALUES (16,'a4df3de5238d3beabee1a2fe140d3d80',0,0,13,'','U','','0','',600,'',1,'','mem_buffers','',23);
INSERT INTO data_template_rrd VALUES (18,'7fea6acc9b1a19484b4cb4cef2b6c5da',0,0,15,'','U','','0','',600,'',1,'','mem_swap','',23);
INSERT INTO data_template_rrd VALUES (19,'f1ba3a5b17b95825021241398bb0f277',0,0,16,'','1000','','0','',600,'',1,'','proc','',24);
INSERT INTO data_template_rrd VALUES (20,'46a5afe8e6c0419172c76421dc9e304a',0,0,17,'','500','','0','',600,'',1,'','users','',21);
INSERT INTO data_template_rrd VALUES (21,'962fd1994fe9cae87fb36436bdb8a742',0,0,18,'','5000','','0','',600,'',1,'','ping','',30);
INSERT INTO data_template_rrd VALUES (30,'3c0fd1a188b64a662dfbfa985648397b',0,0,27,'','100','','0','',600,'',1,'','5min_cpu','',0);
INSERT INTO data_template_rrd VALUES (33,'ed44c2438ef7e46e2aeed2b6c580815c',0,0,30,'','500','','0','',600,'',1,'','load_1min','',0);
INSERT INTO data_template_rrd VALUES (34,'9b3a00c9e3530d9e58895ac38271361e',0,0,31,'','500','','0','',600,'',1,'','load_5min','',0);
INSERT INTO data_template_rrd VALUES (35,'6746c2ed836ecc68a71bbddf06b0e5d9',0,0,32,'','500','','0','',600,'',1,'','load_15min','',0);
INSERT INTO data_template_rrd VALUES (36,'9835d9e1a8c78aa2475d752e8fa74812',0,0,33,'','U','','0','',600,'',1,'','mem_buffers','',0);
INSERT INTO data_template_rrd VALUES (37,'9c78dc1981bcea841b8c827c6dc0d26c',0,0,34,'','U','','0','',600,'',1,'','mem_free','',0);
INSERT INTO data_template_rrd VALUES (44,'4c82df790325d789d304e6ee5cd4ab7d',0,0,37,'','U','','0','',600,'',1,'','hdd_free','',0);
INSERT INTO data_template_rrd VALUES (46,'c802e2fd77f5b0a4c4298951bf65957c',0,0,38,'','10000000','','0','',600,'',2,'','errors_in','',0);
INSERT INTO data_template_rrd VALUES (47,'4e2a72240955380dc8ffacfcc8c09874',0,0,38,'','10000000','','0','',600,'',2,'','discards_in','',0);
INSERT INTO data_template_rrd VALUES (48,'636672962b5bb2f31d86985e2ab4bdfe',0,0,39,'','1000000000','','0','',600,'',2,'','unicast_in','',0);
INSERT INTO data_template_rrd VALUES (49,'18ce92c125a236a190ee9dd948f56268',0,0,39,'','1000000000','','0','',600,'',2,'','unicast_out','',0);
INSERT INTO data_template_rrd VALUES (50,'13ebb33f9cbccfcba828db1075a8167c',0,0,38,'','10000000','','0','',600,'',2,'','discards_out','',0);
INSERT INTO data_template_rrd VALUES (51,'31399c3725bee7e09ec04049e3d5cd17',0,0,38,'','10000000','','0','',600,'',2,'','errors_out','',0);
INSERT INTO data_template_rrd VALUES (52,'7be68cbc4ee0b2973eb9785f8c7a35c7',0,0,40,'','1000000000','','0','',600,'',2,'','nonunicast_out','',0);
INSERT INTO data_template_rrd VALUES (53,'93e2b6f59b10b13f2ddf2da3ae98b89a',0,0,40,'','1000000000','','0','',600,'',2,'','nonunicast_in','',0);
INSERT INTO data_template_rrd VALUES (54,'2df25c57022b0c7e7d0be4c035ada1a0',0,0,41,'on','100000000','','0','',600,'',2,'','traffic_in','',0);
INSERT INTO data_template_rrd VALUES (55,'721c0794526d1ac1c359f27dc56faa49',0,0,41,'on','100000000','','0','',600,'',2,'','traffic_out','',0);
INSERT INTO data_template_rrd VALUES (56,'07175541991def89bd02d28a215f6fcc',0,0,37,'','U','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (78,'0ee6bb54957f6795a5369a29f818d860',0,0,43,'','U','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (79,'9825aaf7c0bdf1554c5b4b86680ac2c0',0,0,44,'','100','','0','',600,'',1,'','cpu','',0);
INSERT INTO data_template_rrd VALUES (80,'50ccbe193c6c7fc29fb9f726cd6c48ee',0,0,45,'','1000','','0','',600,'',1,'','proc','',0);
INSERT INTO data_template_rrd VALUES (81,'9464c91bcff47f23085ae5adae6ab987',0,0,46,'','5000','','0','',600,'',1,'','users','',0);
INSERT INTO data_template_rrd VALUES (92,'165a0da5f461561c85d092dfe96b9551',0,0,43,'','U','','0','',600,'',1,'','hdd_total','',0);
INSERT INTO data_template_rrd VALUES (95,'7a6ca455bbeff99ca891371bc77d5cf9',0,0,47,'','U','','0','',600,'',1,'','mem_cache','',0);
INSERT INTO data_template_rrd VALUES (96,'224b83ea73f55f8a861bcf4c9bea0472',0,0,48,'on','100','','0','',600,'on',1,'','snmp_oid','',0);

CREATE TABLE external_links (
  id int(11) NOT NULL AUTO_INCREMENT,
  sortorder int(11) NOT NULL DEFAULT '0',
  enabled char(2) DEFAULT 'on',
  contentfile varchar(255) NOT NULL default '',
  title varchar(20) NOT NULL default '',
  style varchar(10) NOT NULL DEFAULT '',
  extendedstyle varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='Contains external links that are embedded into Cacti';

--
-- Table structure for table `graph_local`
--

CREATE TABLE graph_local (
  id mediumint(8) unsigned NOT NULL auto_increment,
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
  KEY snmp_index (snmp_index(191))
) ENGINE=InnoDB COMMENT='Creates a relationship for each item in a custom graph.';

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
) ENGINE=InnoDB COMMENT='Stores the names for graph item input groups.';

--
-- Dumping data for table `graph_template_input`
--

INSERT INTO graph_template_input VALUES (3,'e9d4191277fdfd7d54171f153da57fb0',2,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (4,'7b361722a11a03238ee8ab7ce44a1037',2,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (5,'b33eb27833614056e06ee5952c3e0724',3,'Available Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (6,'ef8799e63ee00e8904bcc4228015784a',3,'Used Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (15,'433f328369f9569446ddc59555a63eb8',7,'Ping Host Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (16,'a1a91c1514c65152d8cb73522ea9d4e6',7,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (17,'2fb4deb1448379b27ddc64e30e70dc42',7,'Legend Text','','text_format');
INSERT INTO graph_template_input VALUES (18,'592cedd465877bc61ab549df688b0b2a',8,'Processes Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (19,'1d51dbabb200fcea5c4b157129a75410',8,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (20,'8cb8ed3378abec21a1819ea52dfee6a3',9,'1 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (21,'5dfcaf9fd771deb8c5430bce1562e371',9,'5 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (22,'6f3cc610315ee58bc8e0b1f272466324',9,'15 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (23,'b457a982bf46c6760e6ef5f5d06d41fb',10,'Logged in Users Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (24,'bd4a57adf93c884815b25a8036b67f98',10,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (25,'d7cdb63500c576e0f9f354de42c6cf3a',11,'1 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (26,'a23152f5ec02e7762ca27608c0d89f6c',11,'5 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (27,'2cc5d1818da577fba15115aa18f64d85',11,'15 Minute Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (30,'6273c71cdb7ed4ac525cdbcf6180918c',12,'Free Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (31,'5e62dbea1db699f1bda04c5863e7864d',12,'Swap Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (32,'4d52e112a836d4c9d451f56602682606',4,'System CPU Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (33,'f0310b066cc919d2f898b8d1ebf3b518',4,'User CPU Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (34,'d9eb6b9eb3d7dd44fd14fdefb4096b54',4,'Nice CPU Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (35,'f45def7cad112b450667aa67262258cb',13,'Memory Free Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (36,'f8c361a8c8b7ad80e8be03ba7ea5d0d6',13,'Memory Buffers Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (45,'bb9d83a02261583bc1f92d9e66ea705d',18,'CPU Usage Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (46,'51196222ed37b44236d9958116028980',18,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (53,'940beb0f0344e37f4c6cdfc17d2060bc',21,'Available Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (54,'7b0674dd447a9badf0d11bec688028a8',21,'Used Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (55,'fa83cd3a3b4271b644cb6459ea8c35dc',22,'Discards In Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (56,'7946e8ee1e38a65462b85e31a15e35e5',22,'Errors In Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (57,'00ae916640272f5aca54d73ae34c326b',23,'Unicast Packets Out Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (58,'1bc1652f82488ebfb7242c65d2ffa9c7',23,'Unicast Packets In Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (59,'e3177d0e56278de320db203f32fb803d',24,'Non-Unicast Packets In Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (60,'4f20fba2839764707f1c3373648c5fef',24,'Non-Unicast Packets Out Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (61,'e5acdd5368137c408d56ecf55b0e077c',22,'Discards Out Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (62,'a028e586e5fae667127c655fe0ac67f0',22,'Errors Out Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (63,'2764a4f142ba9fd95872106a1b43541e',25,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (64,'f73f7ddc1f4349356908122093dbfca2',25,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (65,'86bd8819d830a81d64267761e1fd8ec4',26,'Total Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (66,'6c8967850102202de166951e4411d426',26,'Used Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (67,'bdad718851a52b82eca0a310b0238450',27,'CPU Utilization Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (68,'e7b578e12eb8a82627557b955fd6ebd4',27,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (69,'37d09fb7ce88ecec914728bdb20027f3',28,'Logged in Users Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (70,'699bd7eff7ba0c3520db3692103a053d',28,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (71,'df905e159d13a5abed8a8a7710468831',29,'Processes Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (72,'8ca9e3c65c080dbf74a59338d64b0c14',29,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (74,'562726cccdb67d5c6941e9e826ef4ef5',31,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (75,'82426afec226f8189c8928e7f083f80f',31,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (76,'69a23877302e7d142f254b208c58b596',32,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (77,'f28013abf8e5813870df0f4111a5e695',32,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (78,'8644b933b6a09dde6c32ff24655eeb9a',33,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (79,'49c4b4800f3e638a6f6bb681919aea80',33,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (80,'e0b395be8db4f7b938d16df7ae70065f',13,'Cache Memory Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (81,'2dca37011521501b9c2b705d080db750',34,'Data Source [snmp_oid]',NULL,'task_item_id');
INSERT INTO graph_template_input VALUES (82,'b8d8ade5f5f3dd7b12f8cc56bbb4083e',34,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (83,'ac2355b4895c37e14df827f969f31c12',34,'Legend Text','','text_format');

--
-- Table structure for table `graph_template_input_defs`
--

CREATE TABLE graph_template_input_defs (
  graph_template_input_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_item_id int(12) unsigned NOT NULL default '0',
  PRIMARY KEY (graph_template_input_id,graph_template_item_id),
  KEY graph_template_input_id (graph_template_input_id)
) ENGINE=InnoDB COMMENT='Stores the relationship for what graph iitems are associated';

--
-- Dumping data for table `graph_template_input_defs`
--

INSERT INTO graph_template_input_defs VALUES (3,9);
INSERT INTO graph_template_input_defs VALUES (3,10);
INSERT INTO graph_template_input_defs VALUES (3,11);
INSERT INTO graph_template_input_defs VALUES (3,12);
INSERT INTO graph_template_input_defs VALUES (4,13);
INSERT INTO graph_template_input_defs VALUES (4,14);
INSERT INTO graph_template_input_defs VALUES (4,15);
INSERT INTO graph_template_input_defs VALUES (4,16);
INSERT INTO graph_template_input_defs VALUES (5,21);
INSERT INTO graph_template_input_defs VALUES (5,22);
INSERT INTO graph_template_input_defs VALUES (5,23);
INSERT INTO graph_template_input_defs VALUES (5,24);
INSERT INTO graph_template_input_defs VALUES (6,17);
INSERT INTO graph_template_input_defs VALUES (6,18);
INSERT INTO graph_template_input_defs VALUES (6,19);
INSERT INTO graph_template_input_defs VALUES (6,20);
INSERT INTO graph_template_input_defs VALUES (15,61);
INSERT INTO graph_template_input_defs VALUES (15,62);
INSERT INTO graph_template_input_defs VALUES (15,63);
INSERT INTO graph_template_input_defs VALUES (15,64);
INSERT INTO graph_template_input_defs VALUES (16,61);
INSERT INTO graph_template_input_defs VALUES (17,61);
INSERT INTO graph_template_input_defs VALUES (18,65);
INSERT INTO graph_template_input_defs VALUES (18,66);
INSERT INTO graph_template_input_defs VALUES (18,67);
INSERT INTO graph_template_input_defs VALUES (18,68);
INSERT INTO graph_template_input_defs VALUES (19,65);
INSERT INTO graph_template_input_defs VALUES (20,69);
INSERT INTO graph_template_input_defs VALUES (20,70);
INSERT INTO graph_template_input_defs VALUES (21,71);
INSERT INTO graph_template_input_defs VALUES (21,72);
INSERT INTO graph_template_input_defs VALUES (22,73);
INSERT INTO graph_template_input_defs VALUES (22,74);
INSERT INTO graph_template_input_defs VALUES (23,76);
INSERT INTO graph_template_input_defs VALUES (23,77);
INSERT INTO graph_template_input_defs VALUES (23,78);
INSERT INTO graph_template_input_defs VALUES (23,79);
INSERT INTO graph_template_input_defs VALUES (24,76);
INSERT INTO graph_template_input_defs VALUES (25,80);
INSERT INTO graph_template_input_defs VALUES (25,81);
INSERT INTO graph_template_input_defs VALUES (26,82);
INSERT INTO graph_template_input_defs VALUES (26,83);
INSERT INTO graph_template_input_defs VALUES (27,84);
INSERT INTO graph_template_input_defs VALUES (27,85);
INSERT INTO graph_template_input_defs VALUES (30,95);
INSERT INTO graph_template_input_defs VALUES (30,96);
INSERT INTO graph_template_input_defs VALUES (30,97);
INSERT INTO graph_template_input_defs VALUES (30,98);
INSERT INTO graph_template_input_defs VALUES (31,99);
INSERT INTO graph_template_input_defs VALUES (31,100);
INSERT INTO graph_template_input_defs VALUES (31,101);
INSERT INTO graph_template_input_defs VALUES (31,102);
INSERT INTO graph_template_input_defs VALUES (32,29);
INSERT INTO graph_template_input_defs VALUES (32,30);
INSERT INTO graph_template_input_defs VALUES (32,31);
INSERT INTO graph_template_input_defs VALUES (32,32);
INSERT INTO graph_template_input_defs VALUES (33,33);
INSERT INTO graph_template_input_defs VALUES (33,34);
INSERT INTO graph_template_input_defs VALUES (33,35);
INSERT INTO graph_template_input_defs VALUES (33,36);
INSERT INTO graph_template_input_defs VALUES (34,37);
INSERT INTO graph_template_input_defs VALUES (34,38);
INSERT INTO graph_template_input_defs VALUES (34,39);
INSERT INTO graph_template_input_defs VALUES (34,40);
INSERT INTO graph_template_input_defs VALUES (35,103);
INSERT INTO graph_template_input_defs VALUES (35,104);
INSERT INTO graph_template_input_defs VALUES (35,105);
INSERT INTO graph_template_input_defs VALUES (35,106);
INSERT INTO graph_template_input_defs VALUES (36,107);
INSERT INTO graph_template_input_defs VALUES (36,108);
INSERT INTO graph_template_input_defs VALUES (36,109);
INSERT INTO graph_template_input_defs VALUES (36,110);
INSERT INTO graph_template_input_defs VALUES (45,139);
INSERT INTO graph_template_input_defs VALUES (45,140);
INSERT INTO graph_template_input_defs VALUES (45,141);
INSERT INTO graph_template_input_defs VALUES (45,142);
INSERT INTO graph_template_input_defs VALUES (46,139);
INSERT INTO graph_template_input_defs VALUES (53,172);
INSERT INTO graph_template_input_defs VALUES (53,173);
INSERT INTO graph_template_input_defs VALUES (53,174);
INSERT INTO graph_template_input_defs VALUES (53,175);
INSERT INTO graph_template_input_defs VALUES (54,167);
INSERT INTO graph_template_input_defs VALUES (54,169);
INSERT INTO graph_template_input_defs VALUES (54,170);
INSERT INTO graph_template_input_defs VALUES (54,171);
INSERT INTO graph_template_input_defs VALUES (55,180);
INSERT INTO graph_template_input_defs VALUES (55,181);
INSERT INTO graph_template_input_defs VALUES (55,182);
INSERT INTO graph_template_input_defs VALUES (55,183);
INSERT INTO graph_template_input_defs VALUES (56,184);
INSERT INTO graph_template_input_defs VALUES (56,185);
INSERT INTO graph_template_input_defs VALUES (56,186);
INSERT INTO graph_template_input_defs VALUES (56,187);
INSERT INTO graph_template_input_defs VALUES (57,188);
INSERT INTO graph_template_input_defs VALUES (57,189);
INSERT INTO graph_template_input_defs VALUES (57,190);
INSERT INTO graph_template_input_defs VALUES (57,191);
INSERT INTO graph_template_input_defs VALUES (58,192);
INSERT INTO graph_template_input_defs VALUES (58,193);
INSERT INTO graph_template_input_defs VALUES (58,194);
INSERT INTO graph_template_input_defs VALUES (58,195);
INSERT INTO graph_template_input_defs VALUES (59,196);
INSERT INTO graph_template_input_defs VALUES (59,197);
INSERT INTO graph_template_input_defs VALUES (59,198);
INSERT INTO graph_template_input_defs VALUES (59,199);
INSERT INTO graph_template_input_defs VALUES (60,200);
INSERT INTO graph_template_input_defs VALUES (60,201);
INSERT INTO graph_template_input_defs VALUES (60,202);
INSERT INTO graph_template_input_defs VALUES (60,203);
INSERT INTO graph_template_input_defs VALUES (61,204);
INSERT INTO graph_template_input_defs VALUES (61,205);
INSERT INTO graph_template_input_defs VALUES (61,206);
INSERT INTO graph_template_input_defs VALUES (61,207);
INSERT INTO graph_template_input_defs VALUES (62,208);
INSERT INTO graph_template_input_defs VALUES (62,209);
INSERT INTO graph_template_input_defs VALUES (62,210);
INSERT INTO graph_template_input_defs VALUES (62,211);
INSERT INTO graph_template_input_defs VALUES (63,212);
INSERT INTO graph_template_input_defs VALUES (63,213);
INSERT INTO graph_template_input_defs VALUES (63,214);
INSERT INTO graph_template_input_defs VALUES (63,215);
INSERT INTO graph_template_input_defs VALUES (64,216);
INSERT INTO graph_template_input_defs VALUES (64,217);
INSERT INTO graph_template_input_defs VALUES (64,218);
INSERT INTO graph_template_input_defs VALUES (64,219);
INSERT INTO graph_template_input_defs VALUES (65,307);
INSERT INTO graph_template_input_defs VALUES (65,308);
INSERT INTO graph_template_input_defs VALUES (65,309);
INSERT INTO graph_template_input_defs VALUES (65,310);
INSERT INTO graph_template_input_defs VALUES (66,303);
INSERT INTO graph_template_input_defs VALUES (66,304);
INSERT INTO graph_template_input_defs VALUES (66,305);
INSERT INTO graph_template_input_defs VALUES (66,306);
INSERT INTO graph_template_input_defs VALUES (67,315);
INSERT INTO graph_template_input_defs VALUES (67,316);
INSERT INTO graph_template_input_defs VALUES (67,317);
INSERT INTO graph_template_input_defs VALUES (67,318);
INSERT INTO graph_template_input_defs VALUES (68,315);
INSERT INTO graph_template_input_defs VALUES (69,319);
INSERT INTO graph_template_input_defs VALUES (69,320);
INSERT INTO graph_template_input_defs VALUES (69,321);
INSERT INTO graph_template_input_defs VALUES (69,322);
INSERT INTO graph_template_input_defs VALUES (70,319);
INSERT INTO graph_template_input_defs VALUES (71,323);
INSERT INTO graph_template_input_defs VALUES (71,324);
INSERT INTO graph_template_input_defs VALUES (71,325);
INSERT INTO graph_template_input_defs VALUES (71,326);
INSERT INTO graph_template_input_defs VALUES (72,323);
INSERT INTO graph_template_input_defs VALUES (74,362);
INSERT INTO graph_template_input_defs VALUES (74,363);
INSERT INTO graph_template_input_defs VALUES (74,364);
INSERT INTO graph_template_input_defs VALUES (74,365);
INSERT INTO graph_template_input_defs VALUES (75,366);
INSERT INTO graph_template_input_defs VALUES (75,367);
INSERT INTO graph_template_input_defs VALUES (75,368);
INSERT INTO graph_template_input_defs VALUES (75,369);
INSERT INTO graph_template_input_defs VALUES (75,371);
INSERT INTO graph_template_input_defs VALUES (75,372);
INSERT INTO graph_template_input_defs VALUES (76,373);
INSERT INTO graph_template_input_defs VALUES (76,374);
INSERT INTO graph_template_input_defs VALUES (76,375);
INSERT INTO graph_template_input_defs VALUES (76,376);
INSERT INTO graph_template_input_defs VALUES (76,383);
INSERT INTO graph_template_input_defs VALUES (77,377);
INSERT INTO graph_template_input_defs VALUES (77,378);
INSERT INTO graph_template_input_defs VALUES (77,379);
INSERT INTO graph_template_input_defs VALUES (77,380);
INSERT INTO graph_template_input_defs VALUES (77,384);
INSERT INTO graph_template_input_defs VALUES (78,385);
INSERT INTO graph_template_input_defs VALUES (78,386);
INSERT INTO graph_template_input_defs VALUES (78,387);
INSERT INTO graph_template_input_defs VALUES (78,388);
INSERT INTO graph_template_input_defs VALUES (78,393);
INSERT INTO graph_template_input_defs VALUES (79,389);
INSERT INTO graph_template_input_defs VALUES (79,390);
INSERT INTO graph_template_input_defs VALUES (79,391);
INSERT INTO graph_template_input_defs VALUES (79,392);
INSERT INTO graph_template_input_defs VALUES (79,394);
INSERT INTO graph_template_input_defs VALUES (80,403);
INSERT INTO graph_template_input_defs VALUES (80,404);
INSERT INTO graph_template_input_defs VALUES (80,405);
INSERT INTO graph_template_input_defs VALUES (80,406);
INSERT INTO graph_template_input_defs VALUES (81,407);
INSERT INTO graph_template_input_defs VALUES (81,408);
INSERT INTO graph_template_input_defs VALUES (81,409);
INSERT INTO graph_template_input_defs VALUES (81,410);
INSERT INTO graph_template_input_defs VALUES (82,407);
INSERT INTO graph_template_input_defs VALUES (83,407);

--
-- Table structure for table `graph_templates`
--

CREATE TABLE graph_templates (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash char(32) NOT NULL default '',
  name char(255) NOT NULL default '',
  PRIMARY KEY (id)) 
  ENGINE=InnoDB 
  COMMENT='Contains each graph template name.';

--
-- Dumping data for table `graph_templates`
--

INSERT INTO graph_templates VALUES (34,'010b90500e1fc6a05abfd542940584d0','SNMP - Generic OID Template');
INSERT INTO graph_templates VALUES (2,'5deb0d66c81262843dce5f3861be9966','Interface - Traffic (bits/sec)');
INSERT INTO graph_templates VALUES (3,'abb5e813c9f1e8cd6fc1e393092ef8cb','ucd/net - Available Disk Space');
INSERT INTO graph_templates VALUES (4,'e334bdcf821cd27270a4cc945e80915e','ucd/net - CPU Usage');
INSERT INTO graph_templates VALUES (7,'cf96dfb22b58e08bf101ca825377fa4b','Unix - Ping Latency');
INSERT INTO graph_templates VALUES (8,'9fe8b4da353689d376b99b2ea526cc6b','Unix - Processes');
INSERT INTO graph_templates VALUES (9,'fe5edd777a76d48fc48c11aded5211ef','Unix - Load Average');
INSERT INTO graph_templates VALUES (10,'63610139d44d52b195cc375636653ebd','Unix - Logged in Users');
INSERT INTO graph_templates VALUES (11,'5107ec0206562e77d965ce6b852ef9d4','ucd/net - Load Average');
INSERT INTO graph_templates VALUES (12,'6992ed4df4b44f3d5595386b8298f0ec','Linux - Memory Usage');
INSERT INTO graph_templates VALUES (13,'be275639d5680e94c72c0ebb4e19056d','ucd/net - Memory Usage');
INSERT INTO graph_templates VALUES (18,'9a5e6d7781cc1bd6cf24f64dd6ffb423','Cisco - CPU Usage');
INSERT INTO graph_templates VALUES (21,'8e7c8a511652fe4a8e65c69f3d34779d','Unix - Available Disk Space');
INSERT INTO graph_templates VALUES (22,'06621cd4a9289417cadcb8f9b5cfba80','Interface - Errors/Discards');
INSERT INTO graph_templates VALUES (23,'e0d1625a1f4776a5294583659d5cee15','Interface - Unicast Packets');
INSERT INTO graph_templates VALUES (24,'10ca5530554da7b73dc69d291bf55d38','Interface - Non-Unicast Packets');
INSERT INTO graph_templates VALUES (25,'df244b337547b434b486662c3c5c7472','Interface - Traffic (bytes/sec)');
INSERT INTO graph_templates VALUES (26,'7489e44466abee8a7d8636cb2cb14a1a','Host MIB - Available Disk Space');
INSERT INTO graph_templates VALUES (27,'c6bb62bedec4ab97f9db9fd780bd85a6','Host MIB - CPU Utilization');
INSERT INTO graph_templates VALUES (28,'e8462bbe094e4e9e814d4e681671ea82','Host MIB - Logged in Users');
INSERT INTO graph_templates VALUES (29,'62205afbd4066e5c4700338841e3901e','Host MIB - Processes');
INSERT INTO graph_templates VALUES (31,'1742b2066384637022d178cc5072905a','Interface - Traffic (bits/sec, 95th Percentile)');
INSERT INTO graph_templates VALUES (32,'13b47e10b2d5db45707d61851f69c52b','Interface - Traffic (bits/sec, Total Bandwidth)');
INSERT INTO graph_templates VALUES (33,'8ad6790c22b693680e041f21d62537ac','Interface - Traffic (bytes/sec, Total Bandwidth)');

--
-- Table structure for table `graph_templates_gprint`
--

CREATE TABLE graph_templates_gprint (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  gprint_text varchar(255) default NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
  id mediumint(8) unsigned NOT NULL auto_increment,
  local_graph_template_graph_id mediumint(8) unsigned NOT NULL default '0',
  local_graph_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  t_image_format_id char(2) default '',
  image_format_id tinyint(1) NOT NULL default '0',
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
  auto_scale_opts tinyint(1) NOT NULL default '0',
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
  t_grouping char(2) default '',
  grouping char(2) NOT NULL default '',
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
  KEY title_cache (title_cache(191))
) ENGINE=InnoDB COMMENT='Stores the actual graph data.';

--
-- Dumping data for table `graph_templates_graph`
--

INSERT INTO graph_templates_graph VALUES (2,0,0,2,'',1,'on','|host_description| - Traffic','','',150,'',600,'','100','','0','','bits per second','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (3,0,0,3,'',1,'on','|host_description| - Hard Drive Space','','',150,'',600,'','100','','0','','bytes','','on','','on','',2,'','','','','','on','','on','',1024,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (4,0,0,4,'',1,'','|host_description| - CPU Usage','','',150,'',600,'','100','','0','','percent','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (7,0,0,7,'',1,'','|host_description| - Ping Latency','','',150,'',600,'','100','','0','','milliseconds','','on','','on','',2,'','','','','','','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (8,0,0,8,'',1,'','|host_description| - Processes','','',150,'',600,'','100','','0','','processes','','on','','on','',2,'','','','','','','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (9,0,0,9,'',1,'','|host_description| - Load Average','','',150,'',600,'','100','','0','','processes in queue','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','0','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (10,0,0,10,'',1,'','|host_description| - Logged in Users','','',150,'',600,'','100','','0','','users','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (11,0,0,11,'',1,'','|host_description| - Load Average','','',150,'',600,'','100','','0','','processes in queue','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','0','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (12,0,0,12,'',1,'','|host_description| - Memory Usage','','',150,'',600,'','100','','0','','kilobytes','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (13,0,0,13,'',1,'','|host_description| - Memory Usage','','',150,'',600,'','100','','0','','bytes','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (18,0,0,18,'',1,'','|host_description| - CPU Usage','','',150,'',600,'','100','','0','','percent','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (21,0,0,21,'',1,'on','|host_description| - Available Disk Space','','',150,'',600,'','100','','0','','bytes','','on','','on','',2,'','','','','','on','','on','',1024,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (22,0,0,22,'',1,'on','|host_description| - Errors/Discards','','',150,'',600,'','100','','0','','errors/sec','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (23,0,0,23,'',1,'on','|host_description| - Unicast Packets','','',150,'',600,'','100','','0','','packets/sec','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (24,0,0,24,'',1,'on','|host_description| - Non-Unicast Packets','','',150,'',600,'','100','','0','','packets/sec','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (25,0,0,25,'',1,'on','|host_description| - Traffic','','',150,'',600,'','100','','0','','bytes per second','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (34,0,0,26,'',1,'on','|host_description| - Available Disk Space','','',150,'',600,'','100','','0','','bytes','','on','','on','',2,'','','','','','on','','on','',1024,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (35,0,0,27,'',1,'on','|host_description| - CPU Utilization','','',150,'',600,'','100','','0','','percent','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (36,0,0,28,'',1,'','|host_description| - Logged in Users','','',150,'',600,'','100','','0','','users','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (37,0,0,29,'',1,'','|host_description| - Processes','','',150,'',600,'','100','','0','','processes','','on','','on','',2,'','','','','','','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (43,0,0,31,'',1,'on','|host_description| - Traffic','','',150,'',600,'','100','','0','','bits per second','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (44,0,0,32,'',1,'on','|host_description| - Traffic','','',150,'',600,'','100','','0','','bits per second','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);
INSERT INTO graph_templates_graph VALUES (45,0,0,33,'',1,'on','|host_description| - Traffic','','',150,'',600,'','100','','0','','bytes per second','','on','','on','',2,'','','','','','on','','on','',1000,'','','','','','','',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',NULL,'','30','',NULL,'',NULL,'',NULL,'',NULL);

--
-- Table structure for table `graph_templates_item`
--

CREATE TABLE graph_templates_item (
  id int(12) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  local_graph_template_item_id int(12) unsigned NOT NULL default '0',
  local_graph_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  task_item_id mediumint(8) unsigned NOT NULL default '0',
  color_id mediumint(8) unsigned NOT NULL default '0',
  alpha char(2) default 'FF',
  graph_type_id tinyint(3) NOT NULL default '0',
  line_width DECIMAL(4,2) DEFAULT 0,
  dashes varchar(20) DEFAULT NULL,
  dash_offset mediumint(4) DEFAULT NULL,
  cdef_id mediumint(8) unsigned NOT NULL default '0',
  vdef_id mediumint(8) unsigned NOT NULL default '0',
  shift char(2) default NULL,
  consolidation_function_id tinyint(2) NOT NULL default '0',
  textalign varchar(10) default NULL,
  text_format varchar(255) default NULL,
  value varchar(255) default NULL,
  hard_return char(2) default NULL,
  gprint_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY graph_template_id (graph_template_id),
  KEY local_graph_id (local_graph_id),
  KEY task_item_id (task_item_id)
) ENGINE=InnoDB COMMENT='Stores the actual graph item data.';

--
-- Dumping data for table `graph_templates_item`
--

INSERT INTO graph_templates_item VALUES (9,'0470b2427dbfadb6b8346e10a71268fa',0,0,2,54,22,'FF',7,0.00,NULL,NULL,2,0,NULL,1,NULL,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (10,'84a5fe0db518550266309823f994ce9c',0,0,2,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (11,'2f222f28084085cd06a1f46e4449c793',0,0,2,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (12,'55acbcc33f46ee6d754e8e81d1b54808',0,0,2,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (13,'fdaf2321fc890e355711c2bffc07d036',0,0,2,55,20,'FF',4,0.00,NULL,NULL,2,0,NULL,1,NULL,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (14,'768318f42819217ed81196d2179d3e1b',0,0,2,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (15,'cb3aa6256dcb3acd50d4517b77a1a5c3',0,0,2,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (16,'671e989be7cbf12c623b4e79d91c7bed',0,0,2,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (17,'b561ed15b3ba66d277e6d7c1640b86f7',0,0,3,4,48,'FF',7,0.00,NULL,NULL,14,0,NULL,1,NULL,'Used','','',2,1);
INSERT INTO graph_templates_item VALUES (18,'99ef051057fa6adfa6834a7632e9d8a2',0,0,3,4,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (19,'3986695132d3f4716872df4c6fbccb65',0,0,3,4,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (20,'0444300017b368e6257f010dca8bbd0d',0,0,3,4,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (21,'4d6a0b9063124ca60e2d1702b3e15e41',0,0,3,3,20,'FF',8,0.00,NULL,NULL,14,0,NULL,1,NULL,'Available','','',2,5);
INSERT INTO graph_templates_item VALUES (22,'181b08325e4d00cd50b8cdc8f8ae8e77',0,0,3,3,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (23,'bba0a9ff1357c990df50429d64314340',0,0,3,3,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (24,'d4a67883d53bc1df8aead21c97c0bc52',0,0,3,3,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (25,'253c9ec2d66905245149c1c2dc8e536e',0,0,3,0,1,'FF',5,0.00,NULL,NULL,15,0,NULL,1,NULL,'Total','','',2,9);
INSERT INTO graph_templates_item VALUES (26,'ea9ea883383f4eb462fec6aa309ba7b5',0,0,3,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (27,'83b746bcaba029eeca170a9f77ec4864',0,0,3,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (28,'82e01dd92fd37887c0696192efe7af65',0,0,3,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (29,'ff0a6125acbb029b814ed1f271ad2d38',0,0,4,5,9,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'System','','',2,1);
INSERT INTO graph_templates_item VALUES (30,'f0776f7d6638bba76c2c27f75a424f0f',0,0,4,5,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (31,'39f4e021aa3fed9207b5f45a82122b21',0,0,4,5,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (32,'800f0b067c06f4ec9c2316711ea83c1e',0,0,4,5,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (33,'9419dd5dbf549ba4c5dc1462da6ee321',0,0,4,6,21,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'User','','',2,5);
INSERT INTO graph_templates_item VALUES (34,'e461dd263ae47657ea2bf3fd82bec096',0,0,4,6,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (35,'f2d1fbb8078a424ffc8a6c9d44d8caa0',0,0,4,6,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (36,'e70a5de639df5ba1705b5883da7fccfc',0,0,4,6,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (37,'85fefb25ce9fd0317da2706a5463fc42',0,0,4,7,12,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'Nice','','',2,9);
INSERT INTO graph_templates_item VALUES (38,'a1cb26878776999db16f1de7577b3c2a',0,0,4,7,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (39,'7d0f9bf64a0898a0095f099674754273',0,0,4,7,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (40,'b2879248a522d9679333e1f29e9a87c3',0,0,4,7,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (41,'d800aa59eee45383b3d6d35a11cdc864',0,0,4,0,1,'FF',4,0.00,NULL,NULL,12,0,NULL,1,NULL,'Total','','',2,13);
INSERT INTO graph_templates_item VALUES (42,'cab4ae79a546826288e273ca1411c867',0,0,4,0,0,'FF',9,0.00,NULL,NULL,12,0,NULL,4,NULL,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (43,'d44306ae85622fec971507460be63f5c',0,0,4,0,0,'FF',9,0.00,NULL,NULL,12,0,NULL,1,NULL,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (44,'aa5c2118035bb83be497d4e099afcc0d',0,0,4,0,0,'FF',9,0.00,NULL,NULL,12,0,NULL,3,NULL,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (61,'80e0aa956f50c261e5143273da58b8a3',0,0,7,21,25,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'','','',2,1);
INSERT INTO graph_templates_item VALUES (62,'48fdcae893a7b7496e1a61efc3453599',0,0,7,21,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (63,'22f43e5fa20f2716666ba9ed9a7d1727',0,0,7,21,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (64,'3e86d497bcded7af7ab8408e4908e0d8',0,0,7,21,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (65,'ba00ecd28b9774348322ff70a96f2826',0,0,8,19,48,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Running Processes','','',2,1);
INSERT INTO graph_templates_item VALUES (66,'8d76de808efd73c51e9a9cbd70579512',0,0,8,19,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (67,'304244ca63d5b09e62a94c8ec6fbda8d',0,0,8,19,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (68,'da1ba71a93d2ed4a2a00d54592b14157',0,0,8,19,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (69,'93ad2f2803b5edace85d86896620b9da',0,0,9,12,15,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'1 Minute Average','','',2,1);
INSERT INTO graph_templates_item VALUES (70,'e28736bf63d3a3bda03ea9f1e6ecb0f1',0,0,9,12,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,2);
INSERT INTO graph_templates_item VALUES (71,'bbdfa13adc00398eed132b1ccb4337d2',0,0,9,13,8,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'5 Minute Average','','',2,3);
INSERT INTO graph_templates_item VALUES (72,'2c14062c7d67712f16adde06132675d6',0,0,9,13,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,4);
INSERT INTO graph_templates_item VALUES (73,'9cf6ed48a6a54b9644a1de8c9929bd4e',0,0,9,14,9,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'15 Minute Average','','',2,5);
INSERT INTO graph_templates_item VALUES (74,'c9824064305b797f38feaeed2352e0e5',0,0,9,14,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,6);
INSERT INTO graph_templates_item VALUES (75,'fa1bc4eff128c4da70f5247d55b8a444',0,0,9,0,1,'FF',4,0.00,NULL,NULL,12,0,NULL,1,NULL,'','','on',2,7);
INSERT INTO graph_templates_item VALUES (76,'5c94ac24bc0d6d2712cc028fa7d4c7d2',0,0,10,20,67,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Users','','',2,1);
INSERT INTO graph_templates_item VALUES (77,'8bc7f905526f62df7d5c2d8c27c143c1',0,0,10,20,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (78,'cd074cd2b920aab70d480c020276d45b',0,0,10,20,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (79,'415630f25f5384ba0c82adbdb05fe98b',0,0,10,20,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (80,'d77d2050be357ab067666a9485426e6b',0,0,11,33,15,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'1 Minute Average','','',2,1);
INSERT INTO graph_templates_item VALUES (81,'13d22f5a0eac6d97bf6c97d7966f0a00',0,0,11,33,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,2);
INSERT INTO graph_templates_item VALUES (82,'8580230d31d2851ec667c296a665cbf9',0,0,11,34,8,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'5 Minute Average','','',2,3);
INSERT INTO graph_templates_item VALUES (83,'b5b7d9b64e7640aa51dbf58c69b86d15',0,0,11,34,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,4);
INSERT INTO graph_templates_item VALUES (84,'2ec10edf4bfaa866b7efd544d4c3f446',0,0,11,35,9,'FF',8,0.00,NULL,NULL,0,0,NULL,1,NULL,'15 Minute Average','','',2,5);
INSERT INTO graph_templates_item VALUES (85,'b65666f0506c0c70966f493c19607b93',0,0,11,35,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','on',4,6);
INSERT INTO graph_templates_item VALUES (86,'6c73575c74506cfc75b89c4276ef3455',0,0,11,0,1,'FF',4,0.00,NULL,NULL,12,0,NULL,1,NULL,'Total','','on',2,7);
INSERT INTO graph_templates_item VALUES (95,'5fa7c2317f19440b757ab2ea1cae6abc',0,0,12,16,41,'FF',7,0.00,NULL,NULL,14,0,NULL,1,NULL,'Free','','',2,9);
INSERT INTO graph_templates_item VALUES (96,'b1d18060bfd3f68e812c508ff4ac94ed',0,0,12,16,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (97,'780b6f0850aaf9431d1c246c55143061',0,0,12,16,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (98,'2d54a7e7bb45e6c52d97a09e24b7fba7',0,0,12,16,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (99,'40206367a3c192b836539f49801a0b15',0,0,12,18,30,'FF',8,0.00,NULL,NULL,14,0,NULL,1,NULL,'Swap','','',2,13);
INSERT INTO graph_templates_item VALUES (100,'7ee72e2bb3722d4f8a7f9c564e0dd0d0',0,0,12,18,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (101,'c8af33b949e8f47133ee25e63c91d4d0',0,0,12,18,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (102,'568128a16723d1195ce6a234d353ce00',0,0,12,18,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (103,'7517a40d478e28ed88ba2b2a65e16b57',0,0,13,37,52,'FF',7,0.00,NULL,NULL,14,0,NULL,1,NULL,'Memory Free','','',2,1);
INSERT INTO graph_templates_item VALUES (104,'df0c8b353d26c334cb909dc6243957c5',0,0,13,37,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (105,'c41a4cf6fefaf756a24f0a9510580724',0,0,13,37,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (106,'9efa8f01c6ed11364a21710ff170f422',0,0,13,37,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (107,'95d6e4e5110b456f34324f7941d08318',0,0,13,36,35,'FF',8,0.00,NULL,NULL,14,0,NULL,1,NULL,'Memory Buffers','','',2,5);
INSERT INTO graph_templates_item VALUES (108,'0c631bfc0785a9cca68489ea87a6c3da',0,0,13,36,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (109,'3468579d3b671dfb788696df7dcc1ec9',0,0,13,36,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (110,'c3ddfdaa65449f99b7f1a735307f9abe',0,0,13,36,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (139,'098b10c13a5701ddb7d4d1d2e2b0fdb7',0,0,18,30,9,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'CPU Usage','','',2,1);
INSERT INTO graph_templates_item VALUES (140,'1dbda412a9926b0ee5c025aa08f3b230',0,0,18,30,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (141,'725c45917146807b6a4257fc351f2bae',0,0,18,30,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (142,'4e336fdfeb84ce65f81ded0e0159a5e0',0,0,18,30,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (171,'a751838f87068e073b95be9555c57bde',0,0,21,56,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (170,'3b13eb2e542fe006c9bf86947a6854fa',0,0,21,56,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (169,'8ef3e7fb7ce962183f489725939ea40f',0,0,21,56,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (167,'6ca2161c37b0118786dbdb46ad767e5d',0,0,21,56,48,'FF',7,0.00,NULL,NULL,14,0,NULL,1,NULL,'Used','','',2,1);
INSERT INTO graph_templates_item VALUES (172,'5d6dff9c14c71dc1ebf83e87f1c25695',0,0,21,44,20,'FF',8,0.00,NULL,NULL,14,0,NULL,1,NULL,'Available','','',2,5);
INSERT INTO graph_templates_item VALUES (173,'b27cb9a158187d29d17abddc6fdf0f15',0,0,21,44,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (174,'6c0555013bb9b964e51d22f108dae9b0',0,0,21,44,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (175,'42ce58ec17ef5199145fbf9c6ee39869',0,0,21,44,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (176,'9bdff98f2394f666deea028cbca685f3',0,0,21,0,1,'FF',5,0.00,NULL,NULL,15,0,NULL,1,NULL,'Total','','',2,9);
INSERT INTO graph_templates_item VALUES (177,'fb831fefcf602bc31d9d24e8e456c2e6',0,0,21,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (178,'5a958d56785a606c08200ef8dbf8deef',0,0,21,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (179,'5ce67a658cec37f526dc84ac9e08d6e7',0,0,21,0,0,'FF',9,0.00,NULL,NULL,15,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (180,'7e04a041721df1f8828381a9ea2f2154',0,0,22,47,31,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Discards In','','',2,1);
INSERT INTO graph_templates_item VALUES (181,'afc8bca6b1b3030a6d71818272336c6c',0,0,22,47,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (182,'6ac169785f5aeaf1cc5cdfd38dfcfb6c',0,0,22,47,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (183,'178c0a0ce001d36a663ff6f213c07505',0,0,22,47,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (184,'8e3268c0abde7550616bff719f10ee2f',0,0,22,46,48,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Errors In','','',2,5);
INSERT INTO graph_templates_item VALUES (185,'18891392b149de63b62c4258a68d75f8',0,0,22,46,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (186,'dfc9d23de0182c9967ae3dabdfa55a16',0,0,22,46,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (187,'c47ba64e2e5ea8bf84aceec644513176',0,0,22,46,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (188,'9d052e7d632c479737fbfaced0821f79',0,0,23,49,71,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Unicast Packets Out','','',2,5);
INSERT INTO graph_templates_item VALUES (189,'9b9fa6268571b6a04fa4411d8e08c730',0,0,23,49,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (190,'8e8f2fbeb624029cbda1d2a6ddd991ba',0,0,23,49,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (191,'c76495beb1ed01f0799838eb8a893124',0,0,23,49,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (192,'d4e5f253f01c3ea77182c5a46418fc44',0,0,23,48,25,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Unicast Packets In','','',2,1);
INSERT INTO graph_templates_item VALUES (193,'526a96add143da021c5f00d8764a6c12',0,0,23,48,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (194,'81eeb46f451212f00fd7caee42a81c0b',0,0,23,48,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (195,'089e4d1c3faeb00fd5dcc9622b06d656',0,0,23,48,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (196,'fe66cb973966d22250de073405664200',0,0,24,53,25,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Non-Unicast Packets In','','',2,1);
INSERT INTO graph_templates_item VALUES (197,'1ba3fc3466ad32fdd2669cac6cad6faa',0,0,24,53,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (198,'f810154d3a934c723c21659e66199cdf',0,0,24,53,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (199,'98a161df359b01304346657ff1a9d787',0,0,24,53,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (200,'d5e55eaf617ad1f0516f6343b3f07c5e',0,0,24,52,71,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Non-Unicast Packets Out','','',2,5);
INSERT INTO graph_templates_item VALUES (201,'9fde6b8c84089b9f9044e681162e7567',0,0,24,52,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (202,'9a3510727c3d9fa7e2e7a015783a99b3',0,0,24,52,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (203,'451afd23f2cb59ab9b975fd6e2735815',0,0,24,52,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (204,'617d10dff9bbc3edd9d733d9c254da76',0,0,22,50,18,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Discards Out','','',2,9);
INSERT INTO graph_templates_item VALUES (205,'9269a66502c34d00ac3c8b1fcc329ac6',0,0,22,50,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (206,'d45deed7e1ad8350f3b46b537ae0a933',0,0,22,50,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (207,'2f64cf47dc156e8c800ae03c3b893e3c',0,0,22,50,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (208,'57434bef8cb21283c1a73f055b0ada19',0,0,22,51,89,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Errors Out','','',2,13);
INSERT INTO graph_templates_item VALUES (209,'660a1b9365ccbba356fd142faaec9f04',0,0,22,51,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (210,'28c5297bdaedcca29acf245ef4bbed9e',0,0,22,51,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (211,'99098604fd0c78fd7dabac8f40f1fb29',0,0,22,51,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (212,'de3eefd6d6c58afabdabcaf6c0168378',0,0,25,54,22,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (213,'1a80fa108f5c46eecb03090c65bc9a12',0,0,25,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (214,'fe458892e7faa9d232e343d911e845f3',0,0,25,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (215,'175c0a68689bebc38aad2fbc271047b3',0,0,25,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (216,'1bf2283106510491ddf3b9c1376c0b31',0,0,25,55,20,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (217,'c5202f1690ffe45600c0d31a4a804f67',0,0,25,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (218,'eb9794e3fdafc2b74f0819269569ed40',0,0,25,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (219,'6bcedd61e3ccf7518ca431940c93c439',0,0,25,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (303,'b7b381d47972f836785d338a3bef6661',0,0,26,78,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (304,'36fa8063df3b07cece878d54443db727',0,0,26,78,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (305,'2c35b5cae64c5f146a55fcb416dd14b5',0,0,26,78,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (306,'16d6a9a7f608762ad65b0841e5ef4e9c',0,0,26,78,48,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Used','','',2,5);
INSERT INTO graph_templates_item VALUES (307,'d80e4a4901ab86ee39c9cc613e13532f',0,0,26,92,20,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Total','','',2,1);
INSERT INTO graph_templates_item VALUES (308,'567c2214ee4753aa712c3d101ea49a5d',0,0,26,92,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (309,'ba0b6a9e316ef9be66abba68b80f7587',0,0,26,92,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (310,'4b8e4a6bf2757f04c3e3a088338a2f7a',0,0,26,92,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (317,'8536e034ab5268a61473f1ff2f6bd88f',0,0,27,79,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (316,'d478a76de1df9edf896c9ce51506c483',0,0,27,79,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (315,'42537599b5fb8ea852240b58a58633de',0,0,27,79,9,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'CPU Utilization','','',2,1);
INSERT INTO graph_templates_item VALUES (318,'87e10f9942b625aa323a0f39b60058e7',0,0,27,79,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (319,'38f6891b0db92aa8950b4ce7ae902741',0,0,28,81,67,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Users','','',2,1);
INSERT INTO graph_templates_item VALUES (320,'af13152956a20aa894ef4a4067b88f63',0,0,28,81,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (321,'1b2388bbede4459930c57dc93645284e',0,0,28,81,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (322,'6407dc226db1d03be9730f4d6f3eeccf',0,0,28,81,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (323,'fca6a530c8f37476b9004a90b42ee988',0,0,29,80,48,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Running Processes','','',2,1);
INSERT INTO graph_templates_item VALUES (324,'5acebbde3dc65e02f8fda03955852fbe',0,0,29,80,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (325,'311079ffffac75efaab2837df8123122',0,0,29,80,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (326,'724d27007ebf31016cfa5530fee1b867',0,0,29,80,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (373,'1995d8c23e7d8e1efa2b2c55daf3c5a7',0,0,32,54,22,'FF',7,0.00,NULL,NULL,2,0,NULL,1,NULL,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (362,'918e6e7d41bb4bae0ea2937b461742a4',0,0,31,54,22,'FF',7,0.00,NULL,NULL,2,0,NULL,1,NULL,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (363,'f19fbd06c989ea85acd6b4f926e4a456',0,0,31,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (364,'fc150a15e20c57e11e8d05feca557ef9',0,0,31,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (365,'ccbd86e03ccf07483b4d29e63612fb18',0,0,31,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (366,'964c5c30cd05eaf5a49c0377d173de86',0,0,31,55,20,'FF',4,0.00,NULL,NULL,2,0,NULL,1,NULL,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (367,'b1a6fb775cf62e79e1c4bc4933c7e4ce',0,0,31,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (368,'721038182a872ab266b5cf1bf7f7755c',0,0,31,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (369,'2302f80c2c70b897d12182a1fc11ecd6',0,0,31,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (370,'4ffc7af8533d103748316752b70f8e3c',0,0,31,0,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'','','',2,9);
INSERT INTO graph_templates_item VALUES (371,'64527c4b6eeeaf627acc5117ff2180fd',0,0,31,55,9,'FF',2,0.00,NULL,NULL,0,0,NULL,1,NULL,'95th Percentile','|95:bits:0:max:2|','',2,10);
INSERT INTO graph_templates_item VALUES (372,'d5bbcbdbf83ae858862611ac6de8fc62',0,0,31,55,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'(|95:bits:6:max:2| mbit in+out)','','on',2,11);
INSERT INTO graph_templates_item VALUES (374,'55083351cd728b82cc4dde68eb935700',0,0,32,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (375,'54782f71929e7d1734ed5ad4b8dda50d',0,0,32,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (376,'88d3094d5dc2164cbf2f974aeb92f051',0,0,32,54,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (377,'4a381a8e87d4db1ac99cf8d9078266d3',0,0,32,55,20,'FF',4,0.00,NULL,NULL,2,0,NULL,1,NULL,'Outbound','','',2,6);
INSERT INTO graph_templates_item VALUES (378,'5bff63207c7bf076d76ff3036b5dad54',0,0,32,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,4,NULL,'Current:','','',2,7);
INSERT INTO graph_templates_item VALUES (379,'979fff9d691ca35e3f4b3383d9cae43f',0,0,32,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,1,NULL,'Average:','','',2,8);
INSERT INTO graph_templates_item VALUES (380,'0e715933830112c23c15f7e3463f77b6',0,0,32,55,0,'FF',9,0.00,NULL,NULL,2,0,NULL,3,NULL,'Maximum:','','on',2,11);
INSERT INTO graph_templates_item VALUES (383,'5b43e4102600ad75379c5afd235099c4',0,0,32,54,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'Total In:  |sum:auto:current:2:auto|','','on',2,5);
INSERT INTO graph_templates_item VALUES (384,'db7c15d253ca666601b3296f2574edc9',0,0,32,55,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'Total Out: |sum:auto:current:2:auto|','','on',2,12);
INSERT INTO graph_templates_item VALUES (385,'fdaec5b9227522c758ad55882c483a83',0,0,33,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,11);
INSERT INTO graph_templates_item VALUES (386,'6824d29c3f13fe1e849f1dbb8377d3f1',0,0,33,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,8);
INSERT INTO graph_templates_item VALUES (387,'54e3971b3dd751dd2509f62721c12b41',0,0,33,55,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,7);
INSERT INTO graph_templates_item VALUES (388,'cf8c9f69878f0f595d583eac109a9be1',0,0,33,55,20,'FF',4,0.00,NULL,NULL,0,0,NULL,1,NULL,'Outbound','','',2,6);
INSERT INTO graph_templates_item VALUES (389,'de265acbbfa99eb4b3e9f7e90c7feeda',0,0,33,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (390,'777aa88fb0a79b60d081e0e3759f1cf7',0,0,33,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (391,'66bfdb701c8eeadffe55e926d6e77e71',0,0,33,54,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (392,'3ff8dba1ca6279692b3fcabed0bc2631',0,0,33,54,22,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (393,'d6041d14f9c8fb9b7ddcf3556f763c03',0,0,33,55,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'Total Out: |sum:auto:current:2:auto|','','on',2,12);
INSERT INTO graph_templates_item VALUES (394,'76ae747365553a02313a2d8a0dd55c8a',0,0,33,54,0,'FF',1,0.00,NULL,NULL,0,0,NULL,1,NULL,'Total In:  |sum:auto:current:2:auto|','','on',2,5);
INSERT INTO graph_templates_item VALUES (403,'8a1b44ab97d3b56207d0e9e77a035d25',0,0,13,95,30,'FF',8,0.00,NULL,NULL,14,0,NULL,1,NULL,'Cache Memory','','',2,9);
INSERT INTO graph_templates_item VALUES (404,'6db3f439e9764941ff43fbaae348f5dc',0,0,13,95,0,'FF',9,0.00,NULL,NULL,14,0,NULL,4,NULL,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (405,'cc9b2fe7acf0820caa61c1519193f65e',0,0,13,95,0,'FF',9,0.00,NULL,NULL,14,0,NULL,1,NULL,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (406,'9eea140bdfeaa40d50c5cdcd1f23f72d',0,0,13,95,0,'FF',9,0.00,NULL,NULL,14,0,NULL,3,NULL,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (407,'41316670b1a36171de2bda91a0cc2364',0,0,34,96,98,'FF',7,0.00,NULL,NULL,0,0,NULL,1,NULL,'','','',2,1);
INSERT INTO graph_templates_item VALUES (408,'c9e8cbdca0215b434c902e68755903ea',0,0,34,96,0,'FF',9,0.00,NULL,NULL,0,0,NULL,4,NULL,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (409,'dab91d7093e720841393feea5bdcba85',0,0,34,96,0,'FF',9,0.00,NULL,NULL,0,0,NULL,1,NULL,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (410,'03e5bd2151fea3c90843eb1130b84458',0,0,34,96,0,'FF',9,0.00,NULL,NULL,0,0,NULL,3,NULL,'Maximum:','','on',2,4);

--
-- Table structure for table `graph_tree`
--

CREATE TABLE graph_tree (
  id smallint(5) unsigned NOT NULL auto_increment,
  enabled char(2) DEFAULT 'on',
  locked tinyint(4) DEFAULT '0',
  locked_date timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  sort_type tinyint(3) unsigned NOT NULL default '1',
  name varchar(255) NOT NULL default '',
  sequence int(10) unsigned DEFAULT '1',
  user_id int(10) unsigned DEFAULT '1',
  last_modified timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  modified_by int(10) unsigned DEFAULT '1',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
  local_graph_id mediumint(8) unsigned NOT NULL DEFAULT '0',
  title varchar(255) DEFAULT NULL,
  host_id mediumint(8) unsigned NOT NULL DEFAULT '0',
  host_grouping_type tinyint(3) unsigned NOT NULL DEFAULT '1',
  sort_children_type tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `graph_tree_id` (`graph_tree_id`),
  KEY `host_id` (`host_id`),
  KEY `local_graph_id` (`local_graph_id`),
  KEY `parent`(`parent`)
) ENGINE=InnoDB;

--
-- Dumping data for table `graph_tree_items`
--

INSERT INTO graph_tree_items VALUES (1,0,0,1,0,'',1,1,1);

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
  notes text,
  snmp_community varchar(100) default NULL,
  snmp_version tinyint(1) unsigned NOT NULL default '1',
  snmp_username varchar(50) default NULL,
  snmp_password varchar(50) default NULL,
  snmp_auth_protocol char(5) default '',
  snmp_priv_passphrase varchar(200) default '',
  snmp_priv_protocol char(6) default '',
  snmp_context varchar(64) default '',
  snmp_engine_id varchar(30) default '',
  snmp_port mediumint(5) unsigned NOT NULL default '161',
  snmp_timeout mediumint(8) unsigned NOT NULL default '500',
  snmp_sysDescr varchar(300) NOT NULL default '',
  snmp_sysObjectID varchar(64) NOT NULL default '',
  snmp_sysUpTimeInstance int unsigned NOT NULL default '0',
  snmp_sysContact varchar(300) NOT NULL default '',
  snmp_sysName varchar(300) NOT NULL default '',
  snmp_sysLocation varchar(300) NOT NULL default '',
  availability_method smallint(5) unsigned NOT NULL default '1',
  ping_method smallint(5) unsigned default '0',
  ping_port int(12) unsigned default '0',
  ping_timeout int(12) unsigned default '500',
  ping_retries int(12) unsigned default '2',
  max_oids int(12) unsigned default '10',
  device_threads tinyint(2) unsigned NOT NULL DEFAULT '1',
  disabled char(2) default NULL,
  status tinyint(2) NOT NULL default '0',
  status_event_count mediumint(8) unsigned NOT NULL default '0',
  status_fail_date timestamp NOT NULL default '0000-00-00 00:00:00',
  status_rec_date timestamp NOT NULL default '0000-00-00 00:00:00',
  status_last_error varchar(255) default '',
  min_time decimal(10,5) default '9.99999',
  max_time decimal(10,5) default '0.00000',
  cur_time decimal(10,5) default '0.00000',
  avg_time decimal(10,5) default '0.00000',
  polling_time DOUBLE default '0',
  total_polls int(12) unsigned default '0',
  failed_polls int(12) unsigned default '0',
  availability decimal(8,5) NOT NULL default '100.00000',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY poller_id (poller_id),
  KEY site_id (site_id),
  KEY disabled (disabled)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
  snmp_index varchar(191) NOT NULL default '',
  oid TEXT NOT NULL,
  present tinyint NOT NULL DEFAULT '1',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (host_id, snmp_query_id, field_name, snmp_index),
  KEY host_id (host_id,field_name),
  KEY snmp_index (snmp_index(191)),
  KEY field_name (field_name),
  KEY field_value (field_value(191)),
  KEY snmp_query_id (snmp_query_id),
  KEY present (present)
) ENGINE=InnoDB;

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
  PRIMARY KEY (host_id,snmp_query_id),
  KEY host_id (host_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `host_snmp_query`
--

--
-- Table structure for table `host_template`
--

CREATE TABLE host_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

--
-- Dumping data for table `host_template`
--

INSERT INTO host_template VALUES (1,'4855b0e3e553085ed57219690285f91f','Generic SNMP-enabled Host');
INSERT INTO host_template VALUES (3,'07d3fe6a52915f99e642d22e27d967a4','ucd/net SNMP Host');
INSERT INTO host_template VALUES (5,'cae6a879f86edacb2471055783bec6d0','Cisco Router');
INSERT INTO host_template VALUES (7,'5b8300be607dce4f030b026a381b91cd','Windows Host');
INSERT INTO host_template VALUES (8,'2d3e47f416738c2d22c87c40218cc55e','Local Linux Machine');

--
-- Table structure for table `host_template_graph`
--

CREATE TABLE host_template_graph (
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (host_template_id,graph_template_id),
  KEY host_template_id (host_template_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `host_template_graph`
--

INSERT INTO host_template_graph VALUES (3,4);
INSERT INTO host_template_graph VALUES (3,11);
INSERT INTO host_template_graph VALUES (3,13);
INSERT INTO host_template_graph VALUES (5,18);
INSERT INTO host_template_graph VALUES (7,28);
INSERT INTO host_template_graph VALUES (7,29);
INSERT INTO host_template_graph VALUES (8,8);
INSERT INTO host_template_graph VALUES (8,9);
INSERT INTO host_template_graph VALUES (8,10);
INSERT INTO host_template_graph VALUES (8,12);

--
-- Table structure for table `host_template_snmp_query`
--

CREATE TABLE host_template_snmp_query (
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (host_template_id, snmp_query_id),
  KEY host_template_id (host_template_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `host_template_snmp_query`
--

INSERT INTO host_template_snmp_query VALUES (1,1);
INSERT INTO host_template_snmp_query VALUES (3,1);
INSERT INTO host_template_snmp_query VALUES (3,2);
INSERT INTO host_template_snmp_query VALUES (5,1);
INSERT INTO host_template_snmp_query VALUES (7,1);
INSERT INTO host_template_snmp_query VALUES (7,8);
INSERT INTO host_template_snmp_query VALUES (7,9);
INSERT INTO host_template_snmp_query VALUES (8,6);

--
-- Table structure for table `plugin_config`
--

CREATE TABLE `plugin_config` (
  `id` int(8) NOT NULL auto_increment,
  `directory` varchar(32) NOT NULL default '',
  `name` varchar(64) NOT NULL default '',
  `status` tinyint(2) NOT NULL default '0',
  `author` varchar(64) NOT NULL default '',
  `webpage` varchar(255) NOT NULL default '',
  `version` varchar(8) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `directory` (`directory`)
) ENGINE=InnoDB;

--
-- Table structure for table `plugin_hooks`
--

CREATE TABLE `plugin_hooks` (
  `id` int(8) NOT NULL auto_increment,
  `name` varchar(32) NOT NULL default '',
  `hook` varchar(64) NOT NULL default '',
  `file` varchar(255) NOT NULL default '',
  `function` varchar(128) NOT NULL default '',
  `status` int(8) NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `hook` (`hook`),
  KEY `status` (`status`)
) ENGINE=InnoDB;

--
-- Table structure for table `plugin_realms`
--

CREATE TABLE `plugin_realms` (
  `id` int(8) NOT NULL auto_increment,
  `plugin` varchar(32) NOT NULL default '',
  `file` text NOT NULL,
  `display` varchar(64) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`)
) ENGINE=InnoDB;

--
-- Table structure for table `plugin_db_changes`
--

CREATE TABLE `plugin_db_changes` (
  `id` int(10) NOT NULL auto_increment,
  `plugin` varchar(16) NOT NULL default '',
  `table` varchar(64) NOT NULL default '',
  `column` varchar(64) NOT NULL,
  `method` varchar(16) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`),
  KEY `method` (`method`)
) ENGINE=InnoDB;

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
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `dbdefault` varchar(20) NOT NULL DEFAULT 'cacti',
  `dbhost` varchar(64) NOT NULL DEFAULT 'cacti',
  `dbuser` varchar(20) NOT NULL DEFAULT '',
  `dbpass` varchar(64) NOT NULL DEFAULT '',
  `dbport` int(10) unsigned DEFAULT '3306',
  `dbssl` char(3) DEFAULT '',
  `total_time` double DEFAULT '0',
  `snmp` mediumint(8) unsigned DEFAULT '0',
  `script` mediumint(8) unsigned DEFAULT '0',
  `server` mediumint(8) unsigned DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_status` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='Pollers for Cacti';

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
  PRIMARY KEY (poller_id, action, command)
) ENGINE=InnoDB;

--
-- Table structure for table `poller_data_template_field_mappings`
--

CREATE TABLE `poller_data_template_field_mappings` (
  `data_template_id` int(10) unsigned NOT NULL DEFAULT '0',
  `data_name` varchar(25) NOT NULL DEFAULT '',
  `data_source_names` varchar(191) NOT NULL DEFAULT '',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`data_template_id`, `data_name`, `data_source_names`)
) ENGINE=InnoDB COMMENT='Tracks mapping of Data Templates to their Data Source Names';

--
-- Table structure for table `poller_item`
--

CREATE TABLE poller_item (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  poller_id int(10) unsigned NOT NULL default '1',
  host_id mediumint(8) unsigned NOT NULL default '0',
  action tinyint(2) unsigned NOT NULL default '1',
  present tinyint NOT NULL DEFAULT '1',
  last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  hostname varchar(100) NOT NULL default '',
  snmp_community varchar(100) NOT NULL default '',
  snmp_version tinyint(1) unsigned NOT NULL default '0',
  snmp_username varchar(50) NOT NULL default '',
  snmp_password varchar(50) NOT NULL default '',
  snmp_auth_protocol varchar(5) NOT NULL default '',
  snmp_priv_passphrase varchar(200) NOT NULL default '',
  snmp_priv_protocol varchar(6) NOT NULL default '',
  snmp_context varchar(64) default '',
  snmp_engine_id varchar(30) default '',
  snmp_port mediumint(5) unsigned NOT NULL default '161',
  snmp_timeout mediumint(8) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  rrd_path varchar(255) NOT NULL default '',
  rrd_num tinyint(2) unsigned NOT NULL default '0',
  rrd_step mediumint(8) NOT NULL default '300',
  rrd_next_step mediumint(8) NOT NULL default '0',
  arg1 TEXT default NULL,
  arg2 varchar(255) default NULL,
  arg3 varchar(255) default NULL,
  PRIMARY KEY (local_data_id,rrd_name),
  KEY local_data_id (local_data_id),
  KEY host_id (host_id),
  KEY rrd_next_step (rrd_next_step),
  KEY action (action),
  KEY present (present)
) ENGINE=InnoDB;

--
-- Table structure for table `poller_output`
--

CREATE TABLE poller_output (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  time timestamp NOT NULL default '0000-00-00 00:00:00',
  output text NOT NULL,
  PRIMARY KEY (local_data_id, rrd_name, time) /*!50060 USING BTREE */
) ENGINE=InnoDB;

--
-- Table structure for table `poller_output_boost`
--

CREATE TABLE  `poller_output_boost` (
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `output` varchar(512) NOT NULL,
  PRIMARY KEY USING BTREE (`local_data_id`, `time`, `rrd_name`)
) ENGINE=InnoDB;

--
-- Table structure for table `poller_output_boost_processes`
--

CREATE TABLE  `poller_output_boost_processes` (
  `sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
  `status` varchar(255) default NULL,
  PRIMARY KEY (`sock_int_value`)
) ENGINE=MEMORY;

--
-- Table structure for table `poller_output_realtime`
--

CREATE TABLE poller_output_realtime (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  `time` timestamp NOT NULL default '0000-00-00 00:00:00',
  output text NOT NULL,
  poller_id varchar(256) NOT NULL default '1',
  PRIMARY KEY (local_data_id,rrd_name,`time`),
  KEY poller_id(poller_id(191))
) ENGINE=InnoDB;

--
-- Table structure for table `poller_reindex`
--

CREATE TABLE poller_reindex (
  host_id mediumint(8) unsigned NOT NULL default '0',
  data_query_id mediumint(8) unsigned NOT NULL default '0',
  action tinyint(3) unsigned NOT NULL default '0',
  present tinyint NOT NULL DEFAULT '1',
  op char(1) NOT NULL default '',
  assert_value varchar(100) NOT NULL default '',
  arg1 varchar(255) NOT NULL default '',
  PRIMARY KEY (host_id, data_query_id),
  KEY present (present)
) ENGINE=InnoDB;

--
-- Table structure for table `poller_resource_cache`
--

CREATE TABLE poller_resource_cache (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  resource_type varchar(20) DEFAULT NULL,
  md5sum varchar(32) DEFAULT NULL,
  path varchar(191) DEFAULT NULL,
  update_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  contents longblob,
  PRIMARY KEY (id),
  UNIQUE KEY path (path)
) ENGINE=InnoDB COMMENT='Caches all scripts, resources files, and plugins';

--
-- Table structure for table `poller_time`
--

CREATE TABLE poller_time (
  id mediumint(8) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  poller_id int(10) unsigned NOT NULL default '1',
  start_time timestamp NOT NULL default '0000-00-00 00:00:00',
  end_time timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
  `offset` int(12) unsigned NOT NULL DEFAULT '0',
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
  COMMENT='Cacri Reporting Reports';

--
-- Table structure for table `reports_items`
--

CREATE TABLE `reports_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL DEFAULT '0',
  `item_type` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `tree_id` int(10) unsigned NOT NULL DEFAULT '0',
  `branch_id` int(10) unsigned NOT NULL DEFAULT '0',
  `tree_cascade` char(2) NOT NULL DEFAULT '',
  `graph_name_regexp` varchar(128) NOT NULL DEFAULT '',
  `host_template_id` int(10) unsigned NOT NULL DEFAULT '0',
  `host_id` int(10) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` int(10) unsigned NOT NULL DEFAULT '0',
  `local_graph_id` int(10) unsigned NOT NULL DEFAULT '0',
  `timespan` int(10) unsigned NOT NULL DEFAULT '0',
  `align` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `item_text` text NOT NULL,
  `font_size` smallint(2) unsigned NOT NULL DEFAULT '10',
  `sequence` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)) 
  ENGINE=InnoDB 
  COMMENT='Cacti Reporting Items';

--
-- Table structure for table `settings`
--

CREATE TABLE settings (
  name varchar(50) NOT NULL default '',
  value varchar(2048) NOT NULL default '',
  PRIMARY KEY (name)
) ENGINE=InnoDB;

--
-- Dumping data for table `settings`
--


--
-- Table structure for table `settings_user`
--

CREATE TABLE settings_user (
  user_id smallint(8) unsigned NOT NULL default '0',
  name varchar(50) NOT NULL default '',
  value varchar(2048) NOT NULL default '',
  PRIMARY KEY (user_id, name)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB COMMENT='Stores the Default User Group Graph Settings';

--
-- Table structure for table `settings_tree`
--

CREATE TABLE settings_tree (
  user_id mediumint(8) unsigned NOT NULL default '0',
  graph_tree_item_id mediumint(8) unsigned NOT NULL default '0',
  status tinyint(1) NOT NULL default '0',
  PRIMARY KEY (user_id, graph_tree_item_id)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

--
-- Dumping data for table `snmp_query`
--

INSERT INTO snmp_query VALUES (1,'d75e406fdeca4fcef45b8be3a9a63cbc','<path_cacti>/resource/snmp_queries/interface.xml','SNMP - Interface Statistics','Queries a host for a list of monitorable interfaces',0,2);
INSERT INTO snmp_query VALUES (2,'3c1b27d94ad208a0090f293deadde753','<path_cacti>/resource/snmp_queries/net-snmp_disk.xml','ucd/net -  Get Monitored Partitions','Retrieves a list of monitored partitions/disks from a net-snmp enabled host.',0,2);
INSERT INTO snmp_query VALUES (6,'8ffa36c1864124b38bcda2ae9bd61f46','<path_cacti>/resource/script_queries/unix_disk.xml','Unix - Get Mounted Partitions','Queries a list of mounted partitions on a unix-based host with the',0,11);
INSERT INTO snmp_query VALUES (8,'9343eab1f4d88b0e61ffc9d020f35414','<path_cacti>/resource/script_server/host_disk.xml','SNMP - Get Mounted Partitions','Gets a list of partitions using SNMP',0,12);
INSERT INTO snmp_query VALUES (9,'0d1ab53fe37487a5d0b9e1d3ee8c1d0d','<path_cacti>/resource/script_server/host_cpu.xml','SNMP - Get Processor Information','Gets usage for each processor in the system using the host MIB.',0,12);

--
-- Table structure for table `snmp_query_graph`
--

CREATE TABLE snmp_query_graph (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  name varchar(100) NOT NULL default '',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id)
) ENGINE=InnoDB;

--
-- Dumping data for table `snmp_query_graph`
--

INSERT INTO snmp_query_graph VALUES (2,'a4b829746fb45e35e10474c36c69c0cf',1,'In/Out Errors/Discarded Packets',22);
INSERT INTO snmp_query_graph VALUES (3,'01e33224f8b15997d3d09d6b1bf83e18',1,'In/Out Non-Unicast Packets',24);
INSERT INTO snmp_query_graph VALUES (4,'1e6edee3115c42d644dbd014f0577066',1,'In/Out Unicast Packets',23);
INSERT INTO snmp_query_graph VALUES (6,'da43655bf1f641b07579256227806977',2,'Available/Used Disk Space',3);
INSERT INTO snmp_query_graph VALUES (9,'ab93b588c29731ab15db601ca0bc9dec',1,'In/Out Bytes (64-bit Counters)',25);
INSERT INTO snmp_query_graph VALUES (13,'ae34f5f385bed8c81a158bf3030f1089',1,'In/Out Bits',2);
INSERT INTO snmp_query_graph VALUES (14,'1e16a505ddefb40356221d7a50619d91',1,'In/Out Bits (64-bit Counters)',2);
INSERT INTO snmp_query_graph VALUES (15,'a0b3e7b63c2e66f9e1ea24a16ff245fc',6,'Available Disk Space',21);
INSERT INTO snmp_query_graph VALUES (16,'d1e0d9b8efd4af98d28ce2aad81a87e7',1,'In/Out Bytes',25);
INSERT INTO snmp_query_graph VALUES (18,'46c4ee688932cf6370459527eceb8ef3',8,'Available Disk Space',26);
INSERT INTO snmp_query_graph VALUES (19,'4a515b61441ea5f27ab7dee6c3cb7818',9,'Get Processor Utilization',27);
INSERT INTO snmp_query_graph VALUES (20,'ed7f68175d7bb83db8ead332fc945720',1,'In/Out Bits with 95th Percentile',31);
INSERT INTO snmp_query_graph VALUES (21,'f85386cd2fc94634ef167c7f1e5fbcd0',1,'In/Out Bits with Total Bandwidth',32);
INSERT INTO snmp_query_graph VALUES (22,'7d309bf200b6e3cdb59a33493c2e58e0',1,'In/Out Bytes with Total Bandwidth',33);

--
-- Table structure for table `snmp_query_graph_rrd`
--

CREATE TABLE snmp_query_graph_rrd (
  snmp_query_graph_id mediumint(8) unsigned NOT NULL default '0',
  data_template_id mediumint(8) unsigned NOT NULL default '0',
  data_template_rrd_id mediumint(8) unsigned NOT NULL default '0',
  snmp_field_name varchar(50) NOT NULL default '0',
  PRIMARY KEY (snmp_query_graph_id,data_template_id,data_template_rrd_id),
  KEY data_template_rrd_id (data_template_rrd_id),
  KEY snmp_query_graph_id (snmp_query_graph_id)
) ENGINE=InnoDB;

--
-- Dumping data for table `snmp_query_graph_rrd`
--

INSERT INTO snmp_query_graph_rrd VALUES (2,38,47,'ifInDiscards');
INSERT INTO snmp_query_graph_rrd VALUES (3,40,52,'ifOutNUcastPkts');
INSERT INTO snmp_query_graph_rrd VALUES (3,40,53,'ifInNUcastPkts');
INSERT INTO snmp_query_graph_rrd VALUES (4,39,48,'ifInUcastPkts');
INSERT INTO snmp_query_graph_rrd VALUES (2,38,51,'ifOutErrors');
INSERT INTO snmp_query_graph_rrd VALUES (6,3,3,'dskAvail');
INSERT INTO snmp_query_graph_rrd VALUES (6,3,4,'dskUsed');
INSERT INTO snmp_query_graph_rrd VALUES (9,41,55,'ifHCOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (9,41,54,'ifHCInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (2,38,50,'ifOutDiscards');
INSERT INTO snmp_query_graph_rrd VALUES (2,38,46,'ifInErrors');
INSERT INTO snmp_query_graph_rrd VALUES (13,41,54,'ifInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (14,41,54,'ifHCInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (14,41,55,'ifHCOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (13,41,55,'ifOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (4,39,49,'ifOutUcastPkts');
INSERT INTO snmp_query_graph_rrd VALUES (15,37,44,'dskAvailable');
INSERT INTO snmp_query_graph_rrd VALUES (16,41,54,'ifInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (16,41,55,'ifOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (15,37,56,'dskUsed');
INSERT INTO snmp_query_graph_rrd VALUES (18,43,78,'hrStorageUsed');
INSERT INTO snmp_query_graph_rrd VALUES (18,43,92,'hrStorageSize');
INSERT INTO snmp_query_graph_rrd VALUES (19,44,79,'hrProcessorLoad');
INSERT INTO snmp_query_graph_rrd VALUES (20,41,55,'ifOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (20,41,54,'ifInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (21,41,55,'ifOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (21,41,54,'ifInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (22,41,55,'ifOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (22,41,54,'ifInOctets');

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
) ENGINE=InnoDB;

--
-- Dumping data for table `snmp_query_graph_rrd_sv`
--

INSERT INTO snmp_query_graph_rrd_sv VALUES (10,'5d3a8b2f4a454e5b0a1494e00fe7d424',6,3,1,'name','|host_description| - Partition - |query_dskDevice|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (80,'27eb220995925e1a5e0e41b2582a2af6',16,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (85,'e85ddc56efa677b70448f9e931360b77',14,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (84,'37bb8c5b38bb7e89ec88ea7ccacf44d4',14,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (83,'62a47c18be10f273a5f5a13a76b76f54',14,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (32,'',12,37,1,'name','|host_description| - Partition - |query_dskDevice|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (49,'6537b3209e0697fbec278e94e7317b52',2,38,1,'name','|host_description| - Errors - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (50,'6d3f612051016f48c951af8901720a1c',2,38,2,'name','|host_description| - Errors - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (51,'62bc981690576d0b2bd0041ec2e4aa6f',2,38,3,'name','|host_description| - Errors - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (52,'adb270d55ba521d205eac6a21478804a',2,38,4,'name','|host_description| - Errors - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (54,'77065435f3bbb2ff99bc3b43b81de8fe',3,40,1,'name','|host_description| - Non-Unicast Packets - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (55,'240d8893092619c97a54265e8d0b86a1',3,40,2,'name','|host_description| - Non-Unicast Packets - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (56,'4b200ecf445bdeb4c84975b74991df34',3,40,3,'name','|host_description| - Non-Unicast Packets - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (57,'d6da3887646078e4d01fe60a123c2179',3,40,4,'name','|host_description| - Non-Unicast Packets - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (59,'ce7769b97d80ca31d21f83dc18ba93c2',4,39,1,'name','|host_description| - Unicast Packets - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (60,'1ee1f9717f3f4771f7f823ca5a8b83dd',4,39,2,'name','|host_description| - Unicast Packets - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (61,'a7dbd54604533b592d4fae6e67587e32',4,39,3,'name','|host_description| - Unicast Packets - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (62,'b148fa7199edcf06cd71c89e5c5d7b63',4,39,4,'name','|host_description| - Unicast Packets - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (69,'cb09784ba05e401a3f1450126ed1e395',15,37,1,'name','|host_description| - Free Space - |query_dskDevice|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (70,'87a659326af8c75158e5142874fd74b0',13,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (72,'14aa2dead86bbad0f992f1514722c95e',13,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (73,'70390712158c3c5052a7d830fb456489',13,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (74,'084efd82bbddb69fb2ac9bd0b0f16ac6',13,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (75,'7e093c535fa3d810fa76fc3d8c80c94b',13,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (76,'c7ee2110bf81639086d2da03d9d88286',16,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (77,'8ef8ae2ef548892ab95bb6c9f0b3170e',16,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (78,'3a0f707d1c8fd0e061b70241541c7e2e',16,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (79,'2347e9f53564a54d43f3c00d4b60040d',16,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (81,'2e8b27c63d98249096ad5bc320787f43',14,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (82,'8d820d091ec1a9683cfa74a462f239ee',14,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (86,'c582d3b37f19e4a703d9bf4908dc6548',9,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (88,'e1be83d708ed3c0b8715ccb6517a0365',9,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (89,'57a9ae1f197498ca8dcde90194f61cbc',9,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (90,'0110e120981c7ff15304e4a85cb42cbe',9,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (91,'ce0b9c92a15759d3ddbd7161d26a98b7',9,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (93,'a3f280327b1592a1a948e256380b544f',18,43,1,'name','|host_description| - Used Space - |query_hrStorageDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (94,'b5a724edc36c10891fa2a5c370d55b6f',19,44,1,'name','|host_description| - CPU Utilization - CPU|query_hrProcessorFrwID|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (95,'7e87efd0075caba9908e2e6e569b25b0',20,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (96,'dd28d96a253ab86846aedb25d1cca712',20,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (97,'ce425fed4eb3174e4f1cde9713eeafa0',20,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (98,'d0d05156ddb2c65181588db4b64d3907',20,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (99,'3b018f789ff72cc5693ef79e3a794370',20,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (100,'b225229dbbb48c1766cf90298674ceed',21,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (101,'c79248ddbbd195907260887b021a055d',21,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (102,'12a6750d973b7f14783f205d86220082',21,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (103,'25b151fcfe093812cb5c208e36dd697e',21,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (104,'e9ab404a294e406c20fdd30df766161f',21,41,1,'rrd_maximum','|query_ifSpeed|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (105,'119578a4f01ab47e820b0e894e5e5bb3',22,41,1,'name','|host_description| - Traffic - |query_ifIP| - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (106,'940e57d24b2623849c77b59ed05931b9',22,41,2,'name','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (107,'0f045eab01bbc4437b30da568ed5cb03',22,41,3,'name','|host_description| - Traffic - |query_ifIP|/|query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (108,'bd70bf71108d32f0bf91b24c85b87ff0',22,41,4,'name','|host_description| - Traffic - |query_ifDescr|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (109,'fdc4cb976c4b9053bfa2af791a21c5b5',22,41,1,'rrd_maximum','|query_ifSpeed|');

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
) ENGINE=InnoDB;

--
-- Dumping data for table `snmp_query_graph_sv`
--

INSERT INTO snmp_query_graph_sv VALUES (7,'437918b8dcd66a64625c6cee481fff61',6,1,'title','|host_description| - Disk Space - |query_dskPath|');
INSERT INTO snmp_query_graph_sv VALUES (14,'',12,1,'title','|host_description| - Disk Space - |query_dskDevice|');
INSERT INTO snmp_query_graph_sv VALUES (15,'49dca5592ac26ff149a4fbd18d690644',13,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (16,'bda15298139ad22bdc8a3b0952d4e3ab',13,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (17,'29e48483d0471fcd996bfb702a5960aa',13,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (18,'3f42d358965cb94ce4f708b59e04f82b',14,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (19,'45f44b2f811ea8a8ace1cbed8ef906f1',14,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (20,'69c14fbcc23aecb9920b3cdad7f89901',14,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (21,'299d3434851fc0d5c0e105429069709d',2,1,'title','|host_description| - Errors - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (22,'8c8860b17fd67a9a500b4cb8b5e19d4b',2,2,'title','|host_description| - Errors - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (23,'d96360ae5094e5732e7e7496ceceb636',2,3,'title','|host_description| - Errors - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (24,'750a290cadc3dc60bb682a5c5f47df16',3,1,'title','|host_description| - Non-Unicast Packets - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (25,'bde195eecc256c42ca9725f1f22c1dc0',3,2,'title','|host_description| - Non-Unicast Packets - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (26,'d9e97d22689e4ffddaca23b46f2aa306',3,3,'title','|host_description| - Non-Unicast Packets - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (27,'48ceaba62e0c2671a810a7f1adc5f751',4,1,'title','|host_description| - Unicast Packets - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (28,'d6258884bed44abe46d264198adc7c5d',4,2,'title','|host_description| - Unicast Packets - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (29,'6eb58d9835b2b86222306d6ced9961d9',4,3,'title','|host_description| - Unicast Packets - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (30,'f21b23df740bc4a2d691d2d7b1b18dba',15,1,'title','|host_description| - Disk Space - |query_dskDevice|');
INSERT INTO snmp_query_graph_sv VALUES (31,'7fb4a267065f960df81c15f9022cd3a4',16,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (32,'e403f5a733bf5c8401a110609683deb3',16,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (33,'809c2e80552d56b65ca496c1c2fff398',16,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (34,'0a5eb36e98c04ad6be8e1ef66caeed3c',9,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (35,'4c4386a96e6057b7bd0b78095209ddfa',9,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (36,'fd3a384768b0388fa64119fe2f0cc113',9,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (38,'9852782792ede7c0805990e506ac9618',18,1,'title','|host_description| - Used Space - |query_hrStorageDescr|');
INSERT INTO snmp_query_graph_sv VALUES (39,'fa2f07ab54fce72eea684ba893dd9c95',19,1,'title','|host_description| - CPU Utilization - CPU|query_hrProcessorFrwID|');
INSERT INTO snmp_query_graph_sv VALUES (41,'f434ec853c479d424276f367e9806a75',20,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (42,'9b085245847444c5fb90ebbf4448e265',20,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (43,'5977863f28629bd8eb93a2a9cbc3e306',20,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (44,'37b6711af3930c56309cf8956d8bbf14',21,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (45,'cc435c5884a75421329a9b08207c1c90',21,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (46,'82edeea1ec249c9818773e3145836492',21,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');
INSERT INTO snmp_query_graph_sv VALUES (47,'87522150ee8a601b4d6a1f6b9e919c47',22,1,'title','|host_description| - Traffic - |query_ifName|');
INSERT INTO snmp_query_graph_sv VALUES (48,'993a87c04f550f1209d689d584aa8b45',22,2,'title','|host_description| - Traffic - |query_ifIP| (|query_ifDescr|)');
INSERT INTO snmp_query_graph_sv VALUES (49,'183bb486c92a566fddcb0585ede37865',22,3,'title','|host_description| - Traffic - |query_ifDescr|/|query_ifIndex|');

--
-- Table structure for table `user_auth`
--

CREATE TABLE user_auth (
  id mediumint(8) unsigned NOT NULL auto_increment,
  username varchar(50) NOT NULL default '0',
  password varchar(2048) NOT NULL default '',
  realm mediumint(8) NOT NULL default '0',
  full_name varchar(100) default '0',
  email_address varchar(128) NULL,
  must_change_password char(2) default NULL,
  password_change char(2) default 'on',
  show_tree char(2) default 'on',
  show_list char(2) default 'on',
  show_preview char(2) NOT NULL default 'on',
  graph_settings char(2) default NULL,
  login_opts tinyint(1) NOT NULL default '1',
  policy_graphs tinyint(1) unsigned NOT NULL default '1',
  policy_trees tinyint(1) unsigned NOT NULL default '1',
  policy_hosts tinyint(1) unsigned NOT NULL default '1',
  policy_graph_templates tinyint(1) unsigned NOT NULL default '1',
  enabled char(2) NOT NULL DEFAULT 'on',
  lastchange int(12) NOT NULL DEFAULT '-1',
  lastlogin int(12) NOT NULL DEFAULT '-1',
  password_history varchar(4096) NOT NULL DEFAULT '-1',
  locked varchar(3) NOT NULL DEFAULT '',
  failed_attempts int(5) NOT NULL DEFAULT '0',
  lastfail int(12) NOT NULL DEFAULT '0',
  reset_perms int(12) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY username (username),
  KEY realm (realm),
  KEY enabled (enabled)
) ENGINE=InnoDB;

--
-- Dumping data for table `user_auth`
--

INSERT INTO user_auth VALUES (1,'admin','21232f297a57a5a743894a0e4a801fc3',0,'Administrator','','on','on','on','on','on','',2,1,1,1,1,'on',-1,-1,'-1','',0,0,0);
INSERT INTO user_auth VALUES (3,'guest','43e9a4ab75570f5b',0,'Guest Account','','on','on','on','on','on',3,1,1,1,1,1,'',-1,-1,'-1','',0,0,0);

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
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB COMMENT='Caches Remember Me Details';

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
  `login_opts` tinyint(1) NOT NULL DEFAULT '1',
  `show_tree` varchar(2) DEFAULT 'on',
  `show_list` varchar(2) DEFAULT 'on',
  `show_preview` varchar(2) NOT NULL DEFAULT 'on',
  `policy_graphs` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_trees` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_hosts` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_graph_templates` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 COMMENT='Table that Contains User Groups';

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
  KEY `group_id` (`group_id`),
  KEY `realm_id` (`user_id`)
) ENGINE=InnoDB COMMENT='Table that Contains User Group Members';

--
-- Dumping data for table `user_auth_group_members`
--

--
-- Table structure for table `user_auth_group_perms`
--

CREATE TABLE `user_auth_group_perms` (
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`,`item_id`,`type`),
  KEY `group_id` (`group_id`,`type`)
) ENGINE=InnoDB COMMENT='Table that Contains User Group Permissions';

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
  KEY `group_id` (`group_id`),
  KEY `realm_id` (`realm_id`)
) ENGINE=InnoDB COMMENT='Table that Contains User Group Realm Permissions';

--
-- Dumping data for table `user_auth_group_realm`
--

--
-- Table structure for table `user_auth_perms`
--

CREATE TABLE user_auth_perms (
  user_id mediumint(8) unsigned NOT NULL default '0',
  item_id mediumint(8) unsigned NOT NULL default '0',
  type tinyint(2) unsigned NOT NULL default '0',
  PRIMARY KEY (user_id,item_id,type),
  KEY user_id (user_id,type)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
INSERT INTO user_auth_realm VALUES (20,1);
INSERT INTO user_auth_realm VALUES (21,1);
INSERT INTO user_auth_realm VALUES (22,1);
INSERT INTO user_auth_realm VALUES (23,1);
INSERT INTO user_auth_realm VALUES (101,1);

--
-- Table structure for table `user_log`
--

CREATE TABLE user_log (
  username varchar(50) NOT NULL default '0',
  user_id mediumint(8) NOT NULL default '0',
  time timestamp NOT NULL default '0000-00-00 00:00:00',
  result tinyint(1) NOT NULL default '0',
  ip varchar(40) NOT NULL default '',
  PRIMARY KEY (username,user_id,time),
  KEY username (username),
  KEY user_id (user_id)
) ENGINE=InnoDB;

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
  `defdomain` tinyint(3) NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB COMMENT='Table to Hold Login Domains';

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
  `referrals` tinyint(3) unsigned NOT NULL,
  `mode` tinyint(3) unsigned NOT NULL,
  `dn` varchar(128) NOT NULL,
  `group_require` char(2) NOT NULL,
  `group_dn` varchar(128) NOT NULL,
  `group_attrib` varchar(128) NOT NULL,
  `group_member_type` tinyint(3) unsigned NOT NULL,
  `search_base` varchar(128) NOT NULL,
  `search_filter` varchar(128) NOT NULL,
  `specific_dn` varchar(128) NOT NULL,
  `specific_password` varchar(128) NOT NULL,
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB COMMENT='Table to Hold Login Domains for LDAP';

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='Used for Database based Session Storage';

--
-- Dumping data for table `sessions`
--

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `address1` varchar(100) DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(50) DEFAULT '',
  `state` varchar(20) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT '',
  `country` varchar(30) DEFAULT '',
  `timezone` varchar(40) DEFAULT '',
  `latitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
  `longitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
  `alternate_id` varchar(30) DEFAULT '',
  `notes` varchar(1024),
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `city` (`city`),
  KEY `state` (`state`),
  KEY `postal_code` (`postal_code`),
  KEY `country` (`country`),
  KEY `alternate_id` (`alternate_id`)
) ENGINE=InnoDB COMMENT='Contains information about customer sites';

--
-- Dumping data for table `sites`
--

--
-- Table structure for table `snmpagent_cache`
--

CREATE TABLE `snmpagent_cache` (
  `oid` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `mib` varchar(191) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `otype` varchar(255) NOT NULL DEFAULT '',
  `kind` varchar(255) NOT NULL DEFAULT '',
  `max-access` varchar(255) NOT NULL DEFAULT 'not-accessible',
  `value` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(5000) NOT NULL DEFAULT '',
  PRIMARY KEY (`oid`),
  KEY `name` (`name`),
  KEY `mib` (`mib`)
) ENGINE=InnoDB COMMENT='SNMP MIB CACHE';

--
-- Dumping data for table `snmpagent_cache`
--

--
-- Table structure for table `snmpagent_mibs`
--
CREATE TABLE `snmpagent_mibs` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='Registered MIB files';

--
-- Dumping data for table `snmpagent_mibs`
--

--
-- Table structure for table `snmpagent_cache_notifications`
--

CREATE TABLE `snmpagent_cache_notifications` (
  `name` varchar(191) NOT NULL,
  `mib` varchar(255) NOT NULL,
  `attribute` varchar(255) NOT NULL,
  `sequence_id` smallint(6) NOT NULL,
  KEY `name` (`name`)
) ENGINE=InnoDB COMMENT='Notifcations and related attributes';

--
-- Dumping data for table `snmpagent_cache_notifications`
--

--
-- Table structure for table `snmpagent_cache_textual_conventions`
--

CREATE TABLE `snmpagent_cache_textual_conventions` (
  `name` varchar(191) NOT NULL,
  `mib` varchar(191) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(5000) NOT NULL DEFAULT '',
  KEY `name` (`name`),
  KEY `mib` (`mib`)
) ENGINE=InnoDB COMMENT='Textual conventions';

--
-- Dumping data for table `snmpagent_cache_textual_conventions`
--

--
-- Table structure for table `snmpagent_managers`
--

CREATE TABLE `snmpagent_managers` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `disabled` char(2) DEFAULT NULL,
  `max_log_size` tinyint(1) NOT NULL,
  `snmp_version` varchar(255) NOT NULL,
  `snmp_community` varchar(255) NOT NULL,
  `snmp_username` varchar(255) NOT NULL,
  `snmp_auth_password` varchar(255) NOT NULL,
  `snmp_auth_protocol` varchar(255) NOT NULL,
  `snmp_priv_password` varchar(255) NOT NULL,
  `snmp_priv_protocol` varchar(255) NOT NULL,
  `snmp_engine_id` varchar(64) NOT NULL DEFAULT '80005d750302FFFFFFFFFF',
  `snmp_port` varchar(255) NOT NULL,
  `snmp_message_type` tinyint(1) NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB COMMENT='snmp notification receivers';

--
-- Dumping data for table `snmpagent_managers`
--

--
-- Table structure for table `snmpagent_managers_notifications`
--

CREATE TABLE `snmpagent_managers_notifications` (
  `manager_id` int(8) NOT NULL,
  `notification` varchar(180) NOT NULL,
  `mib` varchar(191) NOT NULL,
  KEY `mib` (`mib`),
  KEY `manager_id` (`manager_id`),
  KEY `manager_id2` (`manager_id`,`notification`)
) ENGINE=InnoDB COMMENT='snmp notifications to receivers';

--
-- Dumping data for table `snmpagent_managers_notifications`
--

--
-- Table structure for table `snmpagent_notifications_log`
--

CREATE TABLE `snmpagent_notifications_log` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `time` int(24) NOT NULL,
  `severity` tinyint(1) NOT NULL,
  `manager_id` int(8) NOT NULL,
  `notification` varchar(180) NOT NULL,
  `mib` varchar(191) NOT NULL,
  `varbinds` varchar(5000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `severity` (`severity`),
  KEY `manager_id` (`manager_id`),
  KEY `manager_id2` (`manager_id`,`notification`)
) ENGINE=InnoDB COMMENT='logs snmp notifications to receivers';

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
  PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='vdef';

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
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  vdef_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  type tinyint(2) NOT NULL default '0',
  value varchar(150) NOT NULL default '',
  PRIMARY KEY (id),
  KEY vdef_id (vdef_id)
) ENGINE=InnoDB COMMENT='vdef items';

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
-- Table structure for table `version`
--

CREATE TABLE version (
  cacti char(20) default '',
  PRIMARY KEY (cacti)
) ENGINE=InnoDB;

--
-- Dumping data for table `version`
--

INSERT INTO version VALUES ('new_install');
