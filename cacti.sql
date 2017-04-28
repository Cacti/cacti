-- MySQL dump 10.13  Distrib 5.7.17, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: cacti_dev
-- ------------------------------------------------------
-- Server version	5.7.17
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `aggregate_graph_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Template Definitions for Aggregate Graphs';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graph_templates`
--

LOCK TABLES `aggregate_graph_templates` WRITE;
/*!40000 ALTER TABLE `aggregate_graph_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graph_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aggregate_graph_templates_graph`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `t_alt_y_grid` char(2) DEFAULT '',
  `alt_y_grid` char(2) DEFAULT NULL,
  `t_right_axis` char(2) DEFAULT '',
  `right_axis` varchar(20) DEFAULT NULL,
  `t_right_axis_label` char(2) DEFAULT '',
  `right_axis_label` varchar(200) DEFAULT NULL,
  `t_right_axis_format` char(2) DEFAULT '',
  `right_axis_format` mediumint(8) DEFAULT NULL,
  `t_right_axis_formatter` char(2) DEFAULT '',
  `right_axis_formatter` varchar(10) DEFAULT NULL,
  `t_left_axis_formatter` char(2) DEFAULT '',
  `left_axis_formatter` varchar(10) DEFAULT NULL,
  `t_no_gridfit` char(2) DEFAULT '',
  `no_gridfit` char(2) DEFAULT NULL,
  `t_unit_length` char(2) DEFAULT '',
  `unit_length` varchar(10) DEFAULT NULL,
  `t_tab_width` char(2) DEFAULT '',
  `tab_width` varchar(20) DEFAULT '30',
  `t_dynamic_labels` char(2) DEFAULT '',
  `dynamic_labels` char(2) DEFAULT NULL,
  `t_force_rules_legend` char(2) DEFAULT '',
  `force_rules_legend` char(2) DEFAULT NULL,
  `t_legend_position` char(2) DEFAULT '',
  `legend_position` varchar(10) DEFAULT NULL,
  `t_legend_direction` char(2) DEFAULT '0',
  `legend_direction` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`aggregate_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Aggregate Template Graph Data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graph_templates_graph`
--

LOCK TABLES `aggregate_graph_templates_graph` WRITE;
/*!40000 ALTER TABLE `aggregate_graph_templates_graph` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graph_templates_graph` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aggregate_graph_templates_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Aggregate Template Graph Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graph_templates_item`
--

LOCK TABLES `aggregate_graph_templates_item` WRITE;
/*!40000 ALTER TABLE `aggregate_graph_templates_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graph_templates_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aggregate_graphs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Aggregate Graph Definitions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graphs`
--

LOCK TABLES `aggregate_graphs` WRITE;
/*!40000 ALTER TABLE `aggregate_graphs` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graphs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aggregate_graphs_graph_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Aggregate Graph Graph Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graphs_graph_item`
--

LOCK TABLES `aggregate_graphs_graph_item` WRITE;
/*!40000 ALTER TABLE `aggregate_graphs_graph_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graphs_graph_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aggregate_graphs_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aggregate_graphs_items` (
  `aggregate_graph_id` int(10) unsigned NOT NULL,
  `local_graph_id` int(10) unsigned NOT NULL,
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`aggregate_graph_id`,`local_graph_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Aggregate Graph Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aggregate_graphs_items`
--

LOCK TABLES `aggregate_graphs_items` WRITE;
/*!40000 ALTER TABLE `aggregate_graphs_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `aggregate_graphs_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_devices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `snmp_engine_id` varchar(64) DEFAULT '',
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table of Discovered Devices';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_devices`
--

LOCK TABLES `automation_devices` WRITE;
/*!40000 ALTER TABLE `automation_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_graph_rule_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_graph_rule_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` smallint(3) unsigned NOT NULL DEFAULT '0',
  `operation` smallint(3) unsigned NOT NULL DEFAULT '0',
  `field` varchar(255) NOT NULL DEFAULT '',
  `operator` smallint(3) unsigned NOT NULL DEFAULT '0',
  `pattern` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COMMENT='Automation Graph Rule Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_graph_rule_items`
--

LOCK TABLES `automation_graph_rule_items` WRITE;
/*!40000 ALTER TABLE `automation_graph_rule_items` DISABLE KEYS */;
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (1,1,1,0,'ifOperStatus',7,'Up');
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (2,1,2,1,'ifIP',16,'');
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (3,1,3,1,'ifHwAddr',16,'');
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (4,2,1,0,'ifOperStatus',7,'Up');
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (5,2,2,1,'ifIP',16,'');
INSERT INTO `automation_graph_rule_items` (`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (6,2,3,1,'ifHwAddr',16,'');
/*!40000 ALTER TABLE `automation_graph_rule_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_graph_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_graph_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `snmp_query_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `graph_type_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COMMENT='Automation Graph Rules';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_graph_rules`
--

LOCK TABLES `automation_graph_rules` WRITE;
/*!40000 ALTER TABLE `automation_graph_rules` DISABLE KEYS */;
INSERT INTO `automation_graph_rules` (`id`, `name`, `snmp_query_id`, `graph_type_id`, `enabled`) VALUES (1,'Traffic 64 bit Server',1,14,'');
INSERT INTO `automation_graph_rules` (`id`, `name`, `snmp_query_id`, `graph_type_id`, `enabled`) VALUES (2,'Traffic 64 bit Server Linux',1,14,'');
INSERT INTO `automation_graph_rules` (`id`, `name`, `snmp_query_id`, `graph_type_id`, `enabled`) VALUES (3,'Disk Space',8,18,'');
/*!40000 ALTER TABLE `automation_graph_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_ips`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_ips` (
  `ip_address` varchar(20) NOT NULL DEFAULT '',
  `hostname` varchar(100) DEFAULT NULL,
  `network_id` int(10) unsigned DEFAULT NULL,
  `pid` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned DEFAULT NULL,
  `thread` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`ip_address`),
  KEY `pid` (`pid`)
) ENGINE=MEMORY DEFAULT CHARSET=latin1 COMMENT='List of discoverable ip addresses used for scanning';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_ips`
--

LOCK TABLES `automation_ips` WRITE;
/*!40000 ALTER TABLE `automation_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_ips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_match_rule_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COMMENT='Automation Match Rule Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_match_rule_items`
--

LOCK TABLES `automation_match_rule_items` WRITE;
/*!40000 ALTER TABLE `automation_match_rule_items` DISABLE KEYS */;
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (1,1,1,1,0,'h.description',14,'');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (2,1,1,2,1,'h.snmp_version',12,'2');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (3,1,3,1,0,'ht.name',1,'Linux');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (4,2,1,1,0,'ht.name',1,'Linux');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (5,2,1,2,1,'h.snmp_version',12,'2');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (6,2,3,1,0,'ht.name',1,'SNMP');
INSERT INTO `automation_match_rule_items` (`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) VALUES (7,2,3,2,1,'gt.name',1,'Traffic');
/*!40000 ALTER TABLE `automation_match_rule_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_networks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_networks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poller_id` int(10) unsigned DEFAULT '1',
  `site_id` int(10) unsigned DEFAULT '1',
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COMMENT='Stores scanning subnet definitions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_networks`
--

LOCK TABLES `automation_networks` WRITE;
/*!40000 ALTER TABLE `automation_networks` DISABLE KEYS */;
INSERT INTO `automation_networks` (`id`, `poller_id`, `site_id`, `name`, `subnet_range`, `dns_servers`, `enabled`, `snmp_id`, `enable_netbios`, `add_to_cacti`, `total_ips`, `up_hosts`, `snmp_hosts`, `ping_method`, `ping_port`, `ping_timeout`, `ping_retries`, `sched_type`, `threads`, `run_limit`, `start_at`, `next_start`, `recur_every`, `day_of_week`, `month`, `day_of_month`, `monthly_week`, `monthly_day`, `last_runtime`, `last_started`, `last_status`, `rerun_data_queries`) VALUES (1,1,1,'Test Network','192.168.1.0/24','','',1,'on','',254,14,8,2,22,400,1,2,10,1200,'2015-05-17 16:15','0000-00-00 00:00:00',2,'4','1,2,6','1,2,3,4,6,7,11,12,14,15,17,19,26,32','','',40.178689002991,'2015-05-19 09:23:22','','on');
/*!40000 ALTER TABLE `automation_networks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_processes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=MEMORY DEFAULT CHARSET=latin1 COMMENT='Table tracking active poller processes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_processes`
--

LOCK TABLES `automation_processes` WRITE;
/*!40000 ALTER TABLE `automation_processes` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_snmp`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_snmp` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Group of SNMP Option Sets';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_snmp`
--

LOCK TABLES `automation_snmp` WRITE;
/*!40000 ALTER TABLE `automation_snmp` DISABLE KEYS */;
INSERT INTO `automation_snmp` (`id`, `name`) VALUES (1,'Default Option Set');
/*!40000 ALTER TABLE `automation_snmp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_snmp_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `snmp_engine_id` varchar(64) DEFAULT '',
  PRIMARY KEY (`id`,`snmp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Set of SNMP Options';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_snmp_items`
--

LOCK TABLES `automation_snmp_items` WRITE;
/*!40000 ALTER TABLE `automation_snmp_items` DISABLE KEYS */;
INSERT INTO `automation_snmp_items` (`id`, `snmp_id`, `sequence`, `snmp_version`, `snmp_readstring`, `snmp_port`, `snmp_timeout`, `snmp_retries`, `max_oids`, `snmp_username`, `snmp_password`, `snmp_auth_protocol`, `snmp_priv_passphrase`, `snmp_priv_protocol`, `snmp_context`, `snmp_engine_id`) VALUES (1,1,1,'2','public',161,1000,3,10,'admin','baseball','MD5','','DES','','');
INSERT INTO `automation_snmp_items` (`id`, `snmp_id`, `sequence`, `snmp_version`, `snmp_readstring`, `snmp_port`, `snmp_timeout`, `snmp_retries`, `max_oids`, `snmp_username`, `snmp_password`, `snmp_auth_protocol`, `snmp_priv_passphrase`, `snmp_priv_protocol`, `snmp_context`, `snmp_engine_id`) VALUES (2,1,2,'2','private',161,1000,3,10,'admin','baseball','MD5','','DES','','');
/*!40000 ALTER TABLE `automation_snmp_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_templates` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `host_template` int(8) NOT NULL DEFAULT '0',
  `availability_method` int(10) unsigned DEFAULT '2',
  `sysDescr` varchar(255) DEFAULT '',
  `sysName` varchar(255) DEFAULT '',
  `sysOid` varchar(60) DEFAULT '',
  `sequence` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Templates of SNMP Sys variables used for automation';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_templates`
--

LOCK TABLES `automation_templates` WRITE;
/*!40000 ALTER TABLE `automation_templates` DISABLE KEYS */;
INSERT INTO `automation_templates` (`id`, `host_template`, `availability_method`, `sysDescr`, `sysName`, `sysOid`, `sequence`) VALUES (1,3,2,'Linux','','',2);
INSERT INTO `automation_templates` (`id`, `host_template`, `availability_method`, `sysDescr`, `sysName`, `sysOid`, `sequence`) VALUES (2,1,2,'HP ETHERNET','','',1);
/*!40000 ALTER TABLE `automation_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_tree_rule_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COMMENT='Automation Tree Rule Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_tree_rule_items`
--

LOCK TABLES `automation_tree_rule_items` WRITE;
/*!40000 ALTER TABLE `automation_tree_rule_items` DISABLE KEYS */;
INSERT INTO `automation_tree_rule_items` (`id`, `rule_id`, `sequence`, `field`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`) VALUES (1,1,1,'ht.name',1,'','^(.*)\\s*Linux\\s*(.*)$','${1}\\n${2}');
INSERT INTO `automation_tree_rule_items` (`id`, `rule_id`, `sequence`, `field`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`) VALUES (2,1,2,'h.hostname',1,'','^(\\w*)\\s*(\\w*)\\s*(\\w*).*$','');
INSERT INTO `automation_tree_rule_items` (`id`, `rule_id`, `sequence`, `field`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`) VALUES (3,2,1,'0',2,'on','Traffic','');
INSERT INTO `automation_tree_rule_items` (`id`, `rule_id`, `sequence`, `field`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`) VALUES (4,2,2,'gtg.title_cache',1,'','^(.*)\\s*-\\s*Traffic -\\s*(.*)$','${1}\\n${2}');
/*!40000 ALTER TABLE `automation_tree_rule_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_tree_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_tree_rules` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `tree_id` smallint(3) unsigned NOT NULL DEFAULT '0',
  `tree_item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `leaf_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `host_grouping_type` smallint(3) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COMMENT='Automation Tree Rules';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_tree_rules`
--

LOCK TABLES `automation_tree_rules` WRITE;
/*!40000 ALTER TABLE `automation_tree_rules` DISABLE KEYS */;
INSERT INTO `automation_tree_rules` (`id`, `name`, `tree_id`, `tree_item_id`, `leaf_type`, `host_grouping_type`, `enabled`) VALUES (1,'New Device',1,0,3,0,'');
INSERT INTO `automation_tree_rules` (`id`, `name`, `tree_id`, `tree_item_id`, `leaf_type`, `host_grouping_type`, `enabled`) VALUES (2,'New Graph',1,0,2,0,'');
/*!40000 ALTER TABLE `automation_tree_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cdef`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cdef` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `system` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cdef`
--

LOCK TABLES `cdef` WRITE;
/*!40000 ALTER TABLE `cdef` DISABLE KEYS */;
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (2,'73f95f8b77b5508157d64047342c421e',0,'Turn Bytes into Bits');
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (3,'3d352eed9fa8f7b2791205b3273708c7',0,'Make Stack Negative');
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (4,'e961cc8ec04fda6ed4981cf5ad501aa5',0,'Make Per 5 Minutes');
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (12,'f1ac79f05f255c02f914c920f1038c54',0,'Total All Data Sources');
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (14,'634a23af5e78af0964e8d33b1a4ed26b',0,'Multiply by 1024');
INSERT INTO `cdef` (`id`, `hash`, `system`, `name`) VALUES (15,'068984b5ccdfd2048869efae5166f722',0,'Total All Data Sources, Multiply by 1024');
/*!40000 ALTER TABLE `cdef` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cdef_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cdef_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `cdef_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(2) NOT NULL DEFAULT '0',
  `value` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `cdef_id_sequence` (`cdef_id`,`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cdef_items`
--

LOCK TABLES `cdef_items` WRITE;
/*!40000 ALTER TABLE `cdef_items` DISABLE KEYS */;
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (7,'9bbf6b792507bb9bb17d2af0970f9be9',2,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (8,'caa4e023ac2d7b1c4b4c8c4adfd55dfe',2,3,2,'3');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (9,'a4b8eb2c3bf4920a3ef571a7a004be53',2,2,6,'8');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (10,'c888c9fe6b62c26c4bfe23e18991731d',3,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (11,'1e1d0b29a94e08b648c8f053715442a0',3,3,2,'3');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (12,'4355c197998c7f8b285be7821ddc6da4',3,2,6,'-1');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (13,'40bb7a1143b0f2e2efca14eb356236de',4,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (14,'42686ea0925c0220924b7d333599cd67',4,3,2,'3');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (15,'faf1b148b2c0e0527362ed5b8ca1d351',4,2,6,'300');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (16,'0ef6b8a42dc83b4e43e437960fccd2ea',12,1,4,'ALL_DATA_SOURCES_NODUPS');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (18,'86370cfa0008fe8c56b28be80ee39a40',14,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (19,'9a35cc60d47691af37f6fddf02064e20',14,2,6,'1024');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (20,'5d7a7941ec0440b257e5598a27dd1688',14,3,2,'3');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (21,'44fd595c60539ff0f5817731d9f43a85',15,1,4,'ALL_DATA_SOURCES_NODUPS');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (22,'aa38be265e5ac31783e57ce6f9314e9a',15,2,6,'1024');
INSERT INTO `cdef_items` (`id`, `hash`, `cdef_id`, `sequence`, `type`, `value`) VALUES (23,'204423d4b2598f1f7252eea19458345c',15,3,2,'3');
/*!40000 ALTER TABLE `cdef_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `color_template_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `color_template_items` (
  `color_template_item_id` int(12) unsigned NOT NULL AUTO_INCREMENT,
  `color_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`color_template_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=latin1 COMMENT='Color Items for Color Templates';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `color_template_items`
--

LOCK TABLES `color_template_items` WRITE;
/*!40000 ALTER TABLE `color_template_items` DISABLE KEYS */;
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (1,1,4,1);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (2,1,24,2);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (3,1,98,3);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (4,1,25,4);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (5,2,25,1);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (6,2,29,2);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (7,2,30,3);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (8,2,31,4);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (9,2,33,5);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (10,2,35,6);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (11,2,41,7);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (12,2,9,8);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (13,3,15,1);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (14,3,31,2);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (15,3,28,3);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (16,3,8,4);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (17,3,34,5);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (18,3,33,6);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (19,3,35,7);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (20,3,41,8);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (21,3,36,9);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (22,3,42,10);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (23,3,44,11);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (24,3,48,12);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (25,3,9,13);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (26,3,49,14);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (27,3,51,15);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (28,3,52,16);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (29,4,76,1);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (30,4,84,2);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (31,4,89,3);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (32,4,17,4);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (33,4,86,5);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (34,4,88,6);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (35,4,90,7);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (36,4,94,8);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (37,4,96,9);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (38,4,93,10);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (39,4,91,11);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (40,4,22,12);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (41,4,12,13);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (42,4,95,14);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (43,4,6,15);
INSERT INTO `color_template_items` (`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES (44,4,92,16);
/*!40000 ALTER TABLE `color_template_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `color_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `color_templates` (
  `color_template_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`color_template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COMMENT='Color Templates';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `color_templates`
--

LOCK TABLES `color_templates` WRITE;
/*!40000 ALTER TABLE `color_templates` DISABLE KEYS */;
INSERT INTO `color_templates` (`color_template_id`, `name`) VALUES (1,'Yellow: light -> dark, 4 colors');
INSERT INTO `color_templates` (`color_template_id`, `name`) VALUES (2,'Red: light yellow > dark red, 8 colors');
INSERT INTO `color_templates` (`color_template_id`, `name`) VALUES (3,'Red: light -> dark, 16 colors');
INSERT INTO `color_templates` (`color_template_id`, `name`) VALUES (4,'Green: dark -> light, 16 colors');
/*!40000 ALTER TABLE `color_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colors`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colors` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) DEFAULT '',
  `hex` varchar(6) NOT NULL DEFAULT '',
  `read_only` char(2) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hex` (`hex`)
) ENGINE=InnoDB AUTO_INCREMENT=440 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colors`
--

LOCK TABLES `colors` WRITE;
/*!40000 ALTER TABLE `colors` DISABLE KEYS */;
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (1,'Black','000000','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (2,'White','FFFFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (4,'','FAFD9E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (5,'','C0C0C0','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (6,'','74C366','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (7,'','6DC8FE','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (8,'','EA8F00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (9,'Red','FF0000','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (10,'','4444FF','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (11,'Magenta','FF00FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (12,'Green','00FF00','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (13,'','8D85F3','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (14,'','AD3B6E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (15,'','EACC00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (16,'','12B3B5','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (17,'','157419','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (18,'','C4FD3D','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (19,'','817C4E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (20,'','002A97','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (21,'','0000FF','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (22,'','00CF00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (24,'','F9FD5F','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (25,'','FFF200','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (26,'','CCBB00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (27,'','837C04','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (28,'','EAAF00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (29,'','FFD660','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (30,'','FFC73B','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (31,'','FFAB00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (33,'','FF7D00','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (34,'','ED7600','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (35,'','FF5700','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (36,'','EE5019','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (37,'','B1441E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (38,'','FFC3C0','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (39,'','FF897C','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (40,'','FF6044','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (41,'','FF4105','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (42,'','DA4725','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (43,'','942D0C','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (44,'','FF3932','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (45,'','862F2F','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (46,'','FF5576','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (47,'','562B29','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (48,'','F51D30','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (49,'','DE0056','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (50,'','ED5394','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (51,'','B90054','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (52,'','8F005C','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (53,'','F24AC8','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (54,'','E8CDEF','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (55,'','D8ACE0','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (56,'','A150AA','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (57,'','750F7D','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (58,'','8D00BA','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (59,'','623465','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (60,'','55009D','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (61,'','3D168B','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (62,'','311F4E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (63,'','D2D8F9','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (64,'','9FA4EE','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (65,'','6557D0','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (66,'','4123A1','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (67,'','4668E4','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (68,'','0D006A','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (69,'','00004D','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (70,'','001D61','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (71,'','00234B','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (72,'','002A8F','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (73,'','2175D9','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (74,'','7CB3F1','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (75,'','005199','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (76,'','004359','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (77,'','00A0C1','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (78,'','007283','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (79,'','00BED9','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (80,'','AFECED','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (81,'','55D6D3','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (82,'','00BBB4','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (83,'','009485','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (84,'','005D57','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (85,'','008A77','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (86,'','008A6D','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (87,'','00B99B','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (88,'','009F67','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (89,'','00694A','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (90,'','00A348','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (91,'','00BF47','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (92,'','96E78A','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (93,'','00BD27','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (94,'','35962B','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (95,'','7EE600','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (96,'','6EA100','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (97,'','CAF100','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (98,'','F5F800','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (99,'','CDCFC4','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (100,'','BCBEB3','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (101,'','AAABA1','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (102,'','8F9286','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (103,'','797C6E','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (104,'','2E3127','');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (105,'Night','0C090A','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (106,'Gunmetal','2C3539','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (107,'Midnight','2B1B17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (108,'Charcoal','34282C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (109,'Dark Slate Grey','25383C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (110,'Oil','3B3131','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (111,'Black Cat','413839','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (112,'Iridium','3D3C3A','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (113,'Black Eel','463E3F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (114,'Black Cow','4C4646','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (115,'Gray Wolf','504A4B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (116,'Vampire Gray','565051','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (117,'Gray Dolphin','5C5858','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (118,'Carbon Gray','625D5D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (119,'Ash Gray','666362','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (120,'Cloudy Gray','6D6968','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (121,'Smokey Gray','726E6D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (122,'Gray','736F6E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (123,'Granite','837E7C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (124,'Battleship Gray','848482','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (125,'Gray Cloud','B6B6B4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (126,'Gray Goose','D1D0CE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (127,'Platinum','E5E4E2','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (128,'Metallic Silver','BCC6CC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (129,'Blue Gray','98AFC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (130,'Light Slate Gray','6D7B8D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (131,'Slate Gray','657383','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (132,'Jet Gray','616D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (133,'Mist Blue','646D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (134,'Marble Blue','566D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (135,'Slate Blue','737CA1','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (136,'Steel Blue','4863A0','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (137,'Blue Jay','2B547E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (138,'Dark Slate Blue','2B3856','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (139,'Midnight Blue','151B54','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (140,'Navy Blue','000080','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (141,'Blue Whale','342D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (142,'Lapis Blue','15317E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (143,'Cornflower Blue','151B8D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (144,'Earth Blue','0000A0','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (145,'Cobalt Blue','0020C2','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (146,'Blueberry Blue','0041C2','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (147,'Sapphire Blue','2554C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (148,'Blue Eyes','1569C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (149,'Royal Blue','2B60DE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (150,'Blue Orchid','1F45FC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (151,'Blue Lotus','6960EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (152,'Light Slate Blue','736AFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (153,'Slate Blue','357EC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (154,'Glacial Blue Ice','368BC1','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (155,'Silk Blue','488AC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (156,'Blue Ivy','3090C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (157,'Blue Koi','659EC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (158,'Columbia Blue','87AFC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (159,'Baby Blue','95B9C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (160,'Light Steel Blue','728FCE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (161,'Ocean Blue','2B65EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (162,'Blue Ribbon','306EFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (163,'Blue Dress','157DEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (164,'Dodger Blue','1589FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (165,'Cornflower Blue','6495ED','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (166,'Sky Blue','6698FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (167,'Butterfly Blue','38ACEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (168,'Iceberg','56A5EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (169,'Crystal Blue','5CB3FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (170,'Deep Sky Blue','3BB9FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (171,'Denim Blue','79BAEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (172,'Light Sky Blue','82CAFA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (173,'Day Sky Blue','82CAFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (174,'Jeans Blue','A0CFEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (175,'Blue Angel','B7CEEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (176,'Pastel Blue','B4CFEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (177,'Sea Blue','C2DFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (178,'Powder Blue','C6DEFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (179,'Coral Blue','AFDCEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (180,'Light Blue','ADDFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (181,'Robin Egg Blue','BDEDFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (182,'Pale Blue Lily','CFECEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (183,'Light Cyan','E0FFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (184,'Water','EBF4FA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (185,'Alice Blue','F0F8FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (186,'Azure','F0FFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (187,'Light Slate','CCFFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (188,'Light Aquamarine','93FFE8','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (189,'Electric Blue','9AFEFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (190,'Aquamarine','7FFFD4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (191,'Cyan or Aqua','00FFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (192,'Tron Blue','7DFDFE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (193,'Blue Zircon','57FEFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (194,'Blue Lagoon','8EEBEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (195,'Celeste','50EBEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (196,'Blue Diamond','4EE2EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (197,'Tiffany Blue','81D8D0','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (198,'Cyan Opaque','92C7C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (199,'Blue Hosta','77BFC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (200,'Northern Lights Blue','78C7C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (201,'Medium Turquoise','48CCCD','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (202,'Turquoise','43C6DB','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (203,'Jellyfish','46C7C7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (204,'Macaw Blue Green','43BFC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (205,'Light Sea Green','3EA99F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (206,'Dark Turquoise','3B9C9C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (207,'Sea Turtle Green','438D80','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (208,'Medium Aquamarine','348781','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (209,'Greenish Blue','307D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (210,'Grayish Turquoise','5E7D7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (211,'Beetle Green','4C787E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (212,'Teal','008080','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (213,'Sea Green','4E8975','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (214,'Camouflage Green','78866B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (215,'Sage Green','848b79','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (216,'Hazel Green','617C58','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (217,'Venom Green','728C00','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (218,'Fern Green','667C26','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (219,'Dark Forrest Green','254117','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (220,'Medium Sea Green','306754','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (221,'Medium Forest Green','347235','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (222,'Seaweed Green','437C17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (223,'Pine Green','387C44','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (224,'Jungle Green','347C2C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (225,'Shamrock Green','347C17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (226,'Medium Spring Green','348017','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (227,'Forest Green','4E9258','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (228,'Green Onion','6AA121','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (229,'Spring Green','4AA02C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (230,'Lime Green','41A317','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (231,'Clover Green','3EA055','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (232,'Green Snake','6CBB3C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (233,'Alien Green','6CC417','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (234,'Green Apple','4CC417','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (235,'Yellow Green','52D017','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (236,'Kelly Green','4CC552','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (237,'Zombie Green','54C571','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (238,'Frog Green','99C68E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (239,'Green Peas','89C35C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (240,'Dollar Bill Green','85BB65','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (241,'Dark Sea Green','8BB381','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (242,'Iguana Green','9CB071','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (243,'Avocado Green','B2C248','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (244,'Pistachio Green','9DC209','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (245,'Salad Green','A1C935','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (246,'Hummingbird Green','7FE817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (247,'Nebula Green','59E817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (248,'Stoplight Go Green','57E964','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (249,'Algae Green','64E986','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (250,'Jade Green','5EFB6E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (251,'Emerald Green','5FFB17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (252,'Lawn Green','87F717','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (253,'Chartreuse','8AFB17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (254,'Dragon Green','6AFB92','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (255,'Mint green','98FF98','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (256,'Green Thumb','B5EAAA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (257,'Light Jade','C3FDB8','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (258,'Tea Green','CCFB5D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (259,'Green Yellow','B1FB17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (260,'Slime Green','BCE954','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (261,'Goldenrod','EDDA74','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (262,'Harvest Gold','EDE275','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (263,'Sun Yellow','FFE87C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (264,'Yellow','FFFF00','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (265,'Corn Yellow','FFF380','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (266,'Parchment','FFFFC2','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (267,'Cream','FFFFCC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (268,'Lemon Chiffon','FFF8C6','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (269,'Cornsilk','FFF8DC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (270,'Beige','F5F5DC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (271,'Blonde','FBF6D9','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (272,'Antique White','FAEBD7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (273,'Champagne','F7E7CE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (274,'Blanched Almond','FFEBCD','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (275,'Vanilla','F3E5AB','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (276,'Tan Brown','ECE5B6','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (277,'Peach','FFE5B4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (278,'Mustard','FFDB58','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (279,'Rubber Ducky Yellow','FFD801','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (280,'Bright Gold','FDD017','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (281,'Golden Brown','EAC117','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (282,'Macaroni and Cheese','F2BB66','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (283,'Saffron','FBB917','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (284,'Beer','FBB117','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (285,'Cantaloupe','FFA62F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (286,'Bee Yellow','E9AB17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (287,'Brown Sugar','E2A76F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (288,'BurlyWood','DEB887','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (289,'Deep Peach','FFCBA4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (290,'Ginger Brown','C9BE62','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (291,'School Bus Yellow','E8A317','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (292,'Sandy Brown','EE9A4D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (293,'Fall Leaf Brown','C8B560','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (294,'Orange Gold','D4A017','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (295,'Sand','C2B280','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (296,'Cookie Brown','C7A317','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (297,'Caramel','C68E17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (298,'Brass','B5A642','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (299,'Khaki','ADA96E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (300,'Camel Brown','C19A6B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (301,'Bronze','CD7F32','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (302,'Tiger Orange','C88141','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (303,'Cinnamon','C58917','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (304,'Bullet Shell','AF9B60','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (305,'Dark Goldenrod','AF7817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (306,'Copper','B87333','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (307,'Wood','966F33','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (308,'Oak Brown','806517','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (309,'Moccasin','827839','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (310,'Army Brown','827B60','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (311,'Sandstone','786D5F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (312,'Mocha','493D26','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (313,'Taupe','483C32','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (314,'Coffee','6F4E37','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (315,'Brown Bear','835C3B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (316,'Red Dirt','7F5217','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (317,'Sepia','7F462C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (318,'Orange Salmon','C47451','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (319,'Rust','C36241','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (320,'Red Fox','C35817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (321,'Chocolate','C85A17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (322,'Sedona','CC6600','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (323,'Papaya Orange','E56717','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (324,'Halloween Orange','E66C2C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (325,'Pumpkin Orange','F87217','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (326,'Construction Cone Orange','F87431','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (327,'Sunrise Orange','E67451','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (328,'Mango Orange','FF8040','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (329,'Dark Orange','F88017','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (330,'Coral','FF7F50','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (331,'Basket Ball Orange','F88158','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (332,'Light Salmon','F9966B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (333,'Tangerine','E78A61','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (334,'Dark Salmon','E18B6B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (335,'Light Coral','E77471','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (336,'Bean Red','F75D59','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (337,'Valentine Red','E55451','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (338,'Shocking Orange','E55B3C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (339,'Scarlet','FF2400','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (340,'Ruby Red','F62217','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (341,'Ferrari Red','F70D1A','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (342,'Fire Engine Red','F62817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (343,'Lava Red','E42217','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (344,'Love Red','E41B17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (345,'Grapefruit','DC381F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (346,'Chestnut Red','C34A2C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (347,'Cherry Red','C24641','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (348,'Mahogany','C04000','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (349,'Chilli Pepper','C11B17','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (350,'Cranberry','9F000F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (351,'Red Wine','990012','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (352,'Burgundy','8C001A','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (353,'Chestnut','954535','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (354,'Blood Red','7E3517','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (355,'Sienna','8A4117','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (356,'Sangria','7E3817','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (357,'Firebrick','800517','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (358,'Maroon','810541','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (359,'Plum Pie','7D0541','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (360,'Velvet Maroon','7E354D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (361,'Plum Velvet','7D0552','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (362,'Rosy Finch','7F4E52','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (363,'Puce','7F5A58','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (364,'Dull Purple','7F525D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (365,'Rosy Brown','B38481','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (366,'Khaki Rose','C5908E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (367,'Pink Bow','C48189','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (368,'Lipstick Pink','C48793','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (369,'Rose','E8ADAA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (370,'Desert Sand','EDC9AF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (371,'Pig Pink','FDD7E4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (372,'Cotton Candy','FCDFFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (373,'Pink Bubblegum','FFDFDD','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (374,'Misty Rose','FBBBB9','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (375,'Pink','FAAFBE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (376,'Light Pink','FAAFBA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (377,'Flamingo Pink','F9A7B0','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (378,'Pink Rose','E7A1B0','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (379,'Pink Daisy','E799A3','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (380,'Cadillac Pink','E38AAE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (381,'Carnation Pink','F778A1','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (382,'Blush Red','E56E94','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (383,'Hot Pink','F660AB','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (384,'Watermelon Pink','FC6C85','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (385,'Violet Red','F6358A','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (386,'Deep Pink','F52887','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (387,'Pink Cupcake','E45E9D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (388,'Pink Lemonade','E4287C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (389,'Neon Pink','F535AA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (390,'Dimorphotheca Magenta','E3319D','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (391,'Bright Neon Pink','F433FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (392,'Pale Violet Red','D16587','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (393,'Tulip Pink','C25A7C','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (394,'Medium Violet Red','CA226B','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (395,'Rogue Pink','C12869','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (396,'Burnt Pink','C12267','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (397,'Bashful Pink','C25283','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (398,'Carnation Pink','C12283','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (399,'Plum','B93B8F','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (400,'Viola Purple','7E587E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (401,'Purple Iris','571B7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (402,'Plum Purple','583759','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (403,'Indigo','4B0082','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (404,'Purple Monster','461B7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (405,'Purple Haze','4E387E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (406,'Eggplant','614051','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (407,'Grape','5E5A80','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (408,'Purple Jam','6A287E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (409,'Dark Orchid','7D1B7E','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (410,'Purple Flower','A74AC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (411,'Medium Orchid','B048B5','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (412,'Purple Amethyst','6C2DC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (413,'Dark Violet','842DCE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (414,'Violet','8D38C9','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (415,'Purple Sage Bush','7A5DC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (416,'Lovely Purple','7F38EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (417,'Purple','8E35EF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (418,'Aztech Purple','893BFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (419,'Medium Purple','8467D7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (420,'Jasmine Purple','A23BEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (421,'Purple Daffodil','B041FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (422,'Tyrian Purple','C45AEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (423,'Crocus Purple','9172EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (424,'Purple Mimosa','9E7BFF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (425,'Heliotrope Purple','D462FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (426,'Crimson','E238EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (427,'Purple Dragon','C38EC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (428,'Lilac','C8A2C8','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (429,'Blush Pink','E6A9EC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (430,'Mauve','E0B0FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (431,'Wisteria Purple','C6AEC7','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (432,'Blossom Pink','F9B7FF','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (433,'Thistle','D2B9D3','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (434,'Periwinkle','E9CFEC','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (435,'Lavender Pinocchio','EBDDE2','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (436,'Lavender Blue','E3E4FA','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (437,'Pearl','FDEEF4','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (438,'SeaShell','FFF5EE','on');
INSERT INTO `colors` (`id`, `name`, `hex`, `read_only`) VALUES (439,'Milk White','FEFCFF','on');
/*!40000 ALTER TABLE `colors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_input`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_input` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL DEFAULT '',
  `input_string` varchar(512) DEFAULT NULL,
  `type_id` tinyint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_input`
--

LOCK TABLES `data_input` WRITE;
/*!40000 ALTER TABLE `data_input` DISABLE KEYS */;
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (1,'3eb92bb845b9660a7445cf9740726522','Get SNMP Data','',2);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (2,'bf566c869ac6443b0c75d1c32b5a350e','Get SNMP Data (Indexed)','',3);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (3,'274f4685461170b9eb1b98d22567ab5e','Unix - Get Free Disk Space','<path_cacti>/scripts/diskfree.sh <partition>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (4,'95ed0993eb3095f9920d431ac80f4231','Unix - Get Load Average','perl <path_cacti>/scripts/loadavg_multi.pl',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (5,'79a284e136bb6b061c6f96ec219ac448','Unix - Get Logged In Users','perl <path_cacti>/scripts/unix_users.pl <username>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (6,'362e6d4768937c4f899dd21b91ef0ff8','Linux - Get Memory Usage','perl <path_cacti>/scripts/linux_memory.pl <grepstr>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (7,'a637359e0a4287ba43048a5fdf202066','Unix - Get System Processes','perl <path_cacti>/scripts/unix_processes.pl',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (8,'47d6bfe8be57a45171afd678920bd399','Unix - Get TCP Connections','perl <path_cacti>/scripts/unix_tcp_connections.pl <grepstr>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (9,'cc948e4de13f32b6aea45abaadd287a3','Unix - Get Web Hits','perl <path_cacti>/scripts/webhits.pl <log_path>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (10,'8bd153aeb06e3ff89efc73f35849a7a0','Unix - Ping Host','perl <path_cacti>/scripts/ping.pl <ip>',1);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (11,'80e9e4c4191a5da189ae26d0e237f015','Get Script Data (Indexed)','',4);
INSERT INTO `data_input` (`id`, `hash`, `name`, `input_string`, `type_id`) VALUES (12,'332111d8b54ac8ce939af87a7eac0c06','Get Script Server Data (Indexed)','',6);
/*!40000 ALTER TABLE `data_input` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_input_data`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_input_data` (
  `data_input_field_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `t_value` char(2) DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`data_input_field_id`,`data_template_data_id`),
  KEY `t_value` (`t_value`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_input_data`
--

LOCK TABLES `data_input_data` WRITE;
/*!40000 ALTER TABLE `data_input_data` DISABLE KEYS */;
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,4,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,5,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,6,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,19,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,20,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,22,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,23,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,24,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,25,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,26,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,27,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,30,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,31,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,32,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,33,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,34,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,58,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,59,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (1,68,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,4,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,5,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,6,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,19,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,20,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,22,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,23,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,24,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,25,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,26,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,27,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,30,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,31,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,32,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,33,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,34,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,58,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,59,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (2,68,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,4,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,5,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,6,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,19,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,20,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,22,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,23,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,24,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,25,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,26,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,27,'','');
INSERT INTO `data_input_data` (`data_input_field_id`, `data_template_data_id`, `t_value`, `value`) VALUES (3,30,'','');
/*!40000 ALTER TABLE `data_input_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_input_fields`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_input_fields` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `data_input_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(200) NOT NULL DEFAULT '',
  `data_name` varchar(50) NOT NULL DEFAULT '',
  `input_output` char(3) NOT NULL DEFAULT '',
  `update_rra` char(2) DEFAULT '0',
  `sequence` smallint(5) NOT NULL DEFAULT '0',
  `type_code` varchar(40) DEFAULT NULL,
  `regexp_match` varchar(200) DEFAULT NULL,
  `allow_nulls` char(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_input_id` (`data_input_id`),
  KEY `type_code_data_input_id` (`type_code`,`data_input_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_input_fields`
--

LOCK TABLES `data_input_fields` WRITE;
/*!40000 ALTER TABLE `data_input_fields` DISABLE KEYS */;
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (1,'92f5906c8dc0f964b41f4253df582c38',1,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (2,'32285d5bf16e56c478f5e83f32cda9ef',1,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (3,'ad14ac90641aed388139f6ba86a2e48b',1,'SNMP Username','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (4,'9c55a74bd571b4f00a96fd4b793278c6',1,'SNMP Password','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (5,'012ccb1d3687d3edb29c002ea66e72da',1,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (6,'4276a5ec6e3fe33995129041b1909762',1,'OID','oid','in','',0,'snmp_oid','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (7,'617cdc8a230615e59f06f361ef6e7728',2,'SNMP IP Address','management_ip','in','',0,'hostname','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (8,'acb449d1451e8a2a655c2c99d31142c7',2,'SNMP Community','snmp_community','in','',0,'snmp_community','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (9,'f4facc5e2ca7ebee621f09bc6d9fc792',2,'SNMP Username (v3)','snmp_username','in','',0,'snmp_username','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (10,'1cc1493a6781af2c478fa4de971531cf',2,'SNMP Password (v3)','snmp_password','in','',0,'snmp_password','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (11,'b5c23f246559df38662c255f4aa21d6b',2,'SNMP Version (1, 2, or 3)','snmp_version','in','',0,'snmp_version','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (12,'6027a919c7c7731fbe095b6f53ab127b',2,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (13,'cbbe5c1ddfb264a6e5d509ce1c78c95f',2,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (14,'e6deda7be0f391399c5130e7c4a48b28',2,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (15,'edfd72783ad02df128ff82fc9324b4b9',3,'Disk Partition','partition','in','',1,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (16,'8b75fb61d288f0b5fc0bd3056af3689b',3,'Kilobytes Free','kilobytes','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (17,'363588d49b263d30aecb683c52774f39',4,'1 Minute Average','1min','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (18,'ad139a9e1d69881da36fca07889abf58',4,'5 Minute Average','5min','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (19,'5db9fee64824c08258c7ff6f8bc53337',4,'10 Minute Average','10min','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (20,'c0cfd0beae5e79927c5a360076706820',5,'Username (Optional)','username','in','',1,'','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (21,'52c58ad414d9a2a83b00a7a51be75a53',5,'Logged In Users','users','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (22,'05eb5d710f0814871b8515845521f8d7',6,'Grep String','grepstr','in','',1,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (23,'86cb1cbfde66279dbc7f1144f43a3219',6,'Result (in Kilobytes)','kilobytes','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (24,'d5a8dd5fbe6a5af11667c0039af41386',7,'Number of Processes','proc','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (25,'8848cdcae831595951a3f6af04eec93b',8,'Grep String','grepstr','in','',1,'','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (26,'3d1288d33008430ce354e8b9c162f7ff',8,'Connections','connections','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (27,'c6af570bb2ed9c84abf32033702e2860',9,'(Optional) Log Path','log_path','in','',1,'','','on');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (28,'f9389860f5c5340c9b27fca0b4ee5e71',9,'Web Hits','webhits','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (29,'5fbadb91ad66f203463c1187fe7bd9d5',10,'IP Address','ip','in','',1,'hostname','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (30,'6ac4330d123c69067d36a933d105e89a',10,'Milliseconds','out_ms','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (31,'d39556ecad6166701bfb0e28c5a11108',11,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (32,'3b7caa46eb809fc238de6ef18b6e10d5',11,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (33,'74af2e42dc12956c4817c2ef5d9983f9',11,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (34,'8ae57f09f787656bf4ac541e8bd12537',11,'Output Value','output','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (35,'172b4b0eacee4948c6479f587b62e512',12,'Index Type','index_type','in','',0,'index_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (36,'30fb5d5bcf3d66bb5abe88596f357c26',12,'Index Value','index_value','in','',0,'index_value','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (37,'31112c85ae4ff821d3b288336288818c',12,'Output Type ID','output_type','in','',0,'output_type','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (38,'5be8fa85472d89c621790b43510b5043',12,'Output Value','output','out','on',0,'','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (39,'c1f36ee60c3dc98945556d57f26e475b',2,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (40,'fc64b99742ec417cc424dbf8c7692d36',1,'SNMP Port','snmp_port','in','',0,'snmp_port','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (41,'20832ce12f099c8e54140793a091af90',1,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (42,'c60c9aac1e1b3555ea0620b8bbfd82cb',1,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (43,'feda162701240101bc74148415ef415a',1,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (44,'2cf7129ad3ff819a7a7ac189bee48ce8',2,'SNMP Authenticaion Protocol (v3)','snmp_auth_protocol','in','',0,'snmp_auth_protocol','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (45,'6b13ac0a0194e171d241d4b06f913158',2,'SNMP Privacy Passphrase (v3)','snmp_priv_passphrase','in','',0,'snmp_priv_passphrase','','');
INSERT INTO `data_input_fields` (`id`, `hash`, `data_input_id`, `name`, `data_name`, `input_output`, `update_rra`, `sequence`, `type_code`, `regexp_match`, `allow_nulls`) VALUES (46,'3a33d4fc65b8329ab2ac46a36da26b72',2,'SNMP Privacy Protocol (v3)','snmp_priv_protocol','in','',0,'snmp_priv_protocol','','');
/*!40000 ALTER TABLE `data_input_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_local`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_local` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_query_id` mediumint(8) NOT NULL DEFAULT '0',
  `snmp_index` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `data_template_id` (`data_template_id`),
  KEY `snmp_query_id` (`snmp_query_id`),
  KEY `snmp_index` (`snmp_index`(191)),
  KEY `host_id_snmp_query_id` (`host_id`,`snmp_query_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_local`
--

LOCK TABLES `data_local` WRITE;
/*!40000 ALTER TABLE `data_local` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_local` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_profiles` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `step` int(10) unsigned NOT NULL DEFAULT '300',
  `heartbeat` int(10) unsigned NOT NULL DEFAULT '600',
  `x_files_factor` double DEFAULT '0.5',
  `default` char(2) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Stores Data Source Profiles';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_profiles`
--

LOCK TABLES `data_source_profiles` WRITE;
/*!40000 ALTER TABLE `data_source_profiles` DISABLE KEYS */;
INSERT INTO `data_source_profiles` (`id`, `hash`, `name`, `step`, `heartbeat`, `x_files_factor`, `default`) VALUES (1,'d62c52891f4f9688729a5bc9fad91b18','System Default',300,600,0.5,'on');
INSERT INTO `data_source_profiles` (`id`, `hash`, `name`, `step`, `heartbeat`, `x_files_factor`, `default`) VALUES (2,'c0dd0e46b9ca268e7ed4162d329f9215','High Collection Rate',30,1200,0.5,'');
/*!40000 ALTER TABLE `data_source_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_profiles_cf`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_profiles_cf` (
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `consolidation_function_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`data_source_profile_id`,`consolidation_function_id`),
  KEY `data_source_profile_id` (`data_source_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Maps the Data Source Profile Consolidation Functions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_profiles_cf`
--

LOCK TABLES `data_source_profiles_cf` WRITE;
/*!40000 ALTER TABLE `data_source_profiles_cf` DISABLE KEYS */;
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (1,1);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (1,2);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (1,3);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (1,4);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (2,1);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (2,2);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (2,3);
INSERT INTO `data_source_profiles_cf` (`data_source_profile_id`, `consolidation_function_id`) VALUES (2,4);
/*!40000 ALTER TABLE `data_source_profiles_cf` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_profiles_rra`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_profiles_rra` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `steps` int(10) unsigned DEFAULT '1',
  `rows` int(10) unsigned NOT NULL DEFAULT '700',
  PRIMARY KEY (`id`),
  KEY `data_source_profile_id` (`data_source_profile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1 COMMENT='Stores RRA Definitions for Data Source Profiles';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_profiles_rra`
--

LOCK TABLES `data_source_profiles_rra` WRITE;
/*!40000 ALTER TABLE `data_source_profiles_rra` DISABLE KEYS */;
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (1,1,'Daily (5 Minute Average)',1,600);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (2,1,'Weekly (30 Minute Average)',6,700);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (3,1,'Monthly (2 Hour Average)',24,775);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (4,1,'Yearly (1 Day Average)',288,797);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (5,2,'30 Second Samples',1,1500);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (6,2,'15 Minute Average',30,1346);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (7,2,'1 Hour Average',120,1445);
INSERT INTO `data_source_profiles_rra` (`id`, `data_source_profile_id`, `name`, `steps`, `rows`) VALUES (8,2,'4 Hour Average',480,4380);
/*!40000 ALTER TABLE `data_source_profiles_rra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_purge_action`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_purge_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT '',
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `action` tinyint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='RRD Cleaner File Actions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_purge_action`
--

LOCK TABLES `data_source_purge_action` WRITE;
/*!40000 ALTER TABLE `data_source_purge_action` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_purge_action` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_purge_temp`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_purge_temp` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_cache` varchar(255) NOT NULL DEFAULT '',
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  `size` int(10) unsigned NOT NULL DEFAULT '0',
  `last_mod` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `in_cacti` tinyint(4) NOT NULL DEFAULT '0',
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `local_data_id` (`local_data_id`),
  KEY `in_cacti` (`in_cacti`),
  KEY `data_template_id` (`data_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='RRD Cleaner File Repository';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_purge_temp`
--

LOCK TABLES `data_source_purge_temp` WRITE;
/*!40000 ALTER TABLE `data_source_purge_temp` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_purge_temp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_daily`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_daily` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_daily`
--

LOCK TABLES `data_source_stats_daily` WRITE;
/*!40000 ALTER TABLE `data_source_stats_daily` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_daily` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_hourly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_hourly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_hourly`
--

LOCK TABLES `data_source_stats_hourly` WRITE;
/*!40000 ALTER TABLE `data_source_stats_hourly` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_hourly` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_hourly_cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_hourly_cache` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `value` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`time`,`rrd_name`),
  KEY `time` (`time`) USING BTREE
) ENGINE=MEMORY DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_hourly_cache`
--

LOCK TABLES `data_source_stats_hourly_cache` WRITE;
/*!40000 ALTER TABLE `data_source_stats_hourly_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_hourly_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_hourly_last`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_hourly_last` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `value` double DEFAULT NULL,
  `calculated` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=MEMORY DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_hourly_last`
--

LOCK TABLES `data_source_stats_hourly_last` WRITE;
/*!40000 ALTER TABLE `data_source_stats_hourly_last` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_hourly_last` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_monthly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_monthly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_monthly`
--

LOCK TABLES `data_source_stats_monthly` WRITE;
/*!40000 ALTER TABLE `data_source_stats_monthly` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_monthly` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_weekly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_weekly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_weekly`
--

LOCK TABLES `data_source_stats_weekly` WRITE;
/*!40000 ALTER TABLE `data_source_stats_weekly` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_weekly` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_source_stats_yearly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_source_stats_yearly` (
  `local_data_id` mediumint(8) unsigned NOT NULL,
  `rrd_name` varchar(19) NOT NULL,
  `average` double DEFAULT NULL,
  `peak` double DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_source_stats_yearly`
--

LOCK TABLES `data_source_stats_yearly` WRITE;
/*!40000 ALTER TABLE `data_source_stats_yearly` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_source_stats_yearly` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_template`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_template` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_template`
--

LOCK TABLES `data_template` WRITE;
/*!40000 ALTER TABLE `data_template` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_template_data`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_template_data` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `local_data_template_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_input_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `t_name` char(2) DEFAULT NULL,
  `name` varchar(250) NOT NULL DEFAULT '',
  `name_cache` varchar(255) NOT NULL DEFAULT '',
  `data_source_path` varchar(255) DEFAULT '',
  `t_active` char(2) DEFAULT '',
  `active` char(2) DEFAULT NULL,
  `t_rrd_step` char(2) DEFAULT '',
  `rrd_step` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `t_data_source_profile_id` char(2) DEFAULT '',
  `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `local_data_id` (`local_data_id`),
  KEY `data_template_id` (`data_template_id`),
  KEY `data_input_id` (`data_input_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_template_data`
--

LOCK TABLES `data_template_data` WRITE;
/*!40000 ALTER TABLE `data_template_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_template_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_template_rrd`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_template_rrd` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `local_data_template_rrd_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `t_rrd_maximum` char(2) DEFAULT NULL,
  `rrd_maximum` varchar(20) NOT NULL DEFAULT '0',
  `t_rrd_minimum` char(2) DEFAULT NULL,
  `rrd_minimum` varchar(20) NOT NULL DEFAULT '0',
  `t_rrd_heartbeat` char(2) DEFAULT NULL,
  `rrd_heartbeat` mediumint(6) NOT NULL DEFAULT '0',
  `t_data_source_type_id` char(2) DEFAULT NULL,
  `data_source_type_id` smallint(5) NOT NULL DEFAULT '0',
  `t_data_source_name` char(2) DEFAULT NULL,
  `data_source_name` varchar(19) NOT NULL DEFAULT '',
  `t_data_input_field_id` char(2) DEFAULT NULL,
  `data_input_field_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `duplicate_dsname_contraint` (`local_data_id`,`data_source_name`,`data_template_id`),
  KEY `local_data_id` (`local_data_id`),
  KEY `data_template_id` (`data_template_id`),
  KEY `local_data_template_rrd_id` (`local_data_template_rrd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_template_rrd`
--

LOCK TABLES `data_template_rrd` WRITE;
/*!40000 ALTER TABLE `data_template_rrd` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_template_rrd` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `external_links`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `external_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortorder` int(11) NOT NULL DEFAULT '0',
  `enabled` char(2) DEFAULT 'on',
  `contentfile` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(20) NOT NULL DEFAULT '',
  `style` varchar(10) NOT NULL DEFAULT '',
  `extendedstyle` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Contains external links that are embedded into Cacti';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `external_links`
--

LOCK TABLES `external_links` WRITE;
/*!40000 ALTER TABLE `external_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `external_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_local`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_local` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_query_id` mediumint(8) NOT NULL DEFAULT '0',
  `snmp_query_graph_id` mediumint(8) NOT NULL DEFAULT '0',
  `snmp_index` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `host_id` (`host_id`),
  KEY `graph_template_id` (`graph_template_id`),
  KEY `snmp_query_id` (`snmp_query_id`),
  KEY `snmp_query_graph_id` (`snmp_query_graph_id`),
  KEY `snmp_index` (`snmp_index`(191))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Creates a relationship for each item in a custom graph.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_local`
--

LOCK TABLES `graph_local` WRITE;
/*!40000 ALTER TABLE `graph_local` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_local` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_template_input`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_template_input` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` text,
  `column_name` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores the names for graph item input groups.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_template_input`
--

LOCK TABLES `graph_template_input` WRITE;
/*!40000 ALTER TABLE `graph_template_input` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_template_input` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_template_input_defs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_template_input_defs` (
  `graph_template_input_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_template_item_id` int(12) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`graph_template_input_id`,`graph_template_item_id`),
  KEY `graph_template_input_id` (`graph_template_input_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores the relationship for what graph items are associated';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_template_input_defs`
--

LOCK TABLES `graph_template_input_defs` WRITE;
/*!40000 ALTER TABLE `graph_template_input_defs` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_template_input_defs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_templates` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` char(32) NOT NULL DEFAULT '',
  `name` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Contains each graph template name.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_templates`
--

LOCK TABLES `graph_templates` WRITE;
/*!40000 ALTER TABLE `graph_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_templates_gprint`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_templates_gprint` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `gprint_text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_templates_gprint`
--

LOCK TABLES `graph_templates_gprint` WRITE;
/*!40000 ALTER TABLE `graph_templates_gprint` DISABLE KEYS */;
INSERT INTO `graph_templates_gprint` (`id`, `hash`, `name`, `gprint_text`) VALUES (2,'e9c43831e54eca8069317a2ce8c6f751','Normal','%8.2lf %s');
INSERT INTO `graph_templates_gprint` (`id`, `hash`, `name`, `gprint_text`) VALUES (3,'19414480d6897c8731c7dc6c5310653e','Exact Numbers','%8.0lf');
INSERT INTO `graph_templates_gprint` (`id`, `hash`, `name`, `gprint_text`) VALUES (4,'304a778405392f878a6db435afffc1e9','Load Average','%8.2lf');
/*!40000 ALTER TABLE `graph_templates_gprint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_templates_graph`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_templates_graph` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `local_graph_template_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `local_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `t_image_format_id` char(2) DEFAULT '',
  `image_format_id` tinyint(1) NOT NULL DEFAULT '0',
  `t_title` char(2) DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `title_cache` varchar(255) NOT NULL DEFAULT '',
  `t_height` char(2) DEFAULT '',
  `height` mediumint(8) NOT NULL DEFAULT '0',
  `t_width` char(2) DEFAULT '',
  `width` mediumint(8) NOT NULL DEFAULT '0',
  `t_upper_limit` char(2) DEFAULT '',
  `upper_limit` varchar(20) NOT NULL DEFAULT '0',
  `t_lower_limit` char(2) DEFAULT '',
  `lower_limit` varchar(20) NOT NULL DEFAULT '0',
  `t_vertical_label` char(2) DEFAULT '',
  `vertical_label` varchar(200) DEFAULT NULL,
  `t_slope_mode` char(2) DEFAULT '',
  `slope_mode` char(2) DEFAULT 'on',
  `t_auto_scale` char(2) DEFAULT '',
  `auto_scale` char(2) DEFAULT NULL,
  `t_auto_scale_opts` char(2) DEFAULT '',
  `auto_scale_opts` tinyint(1) NOT NULL DEFAULT '0',
  `t_auto_scale_log` char(2) DEFAULT '',
  `auto_scale_log` char(2) DEFAULT NULL,
  `t_scale_log_units` char(2) DEFAULT '',
  `scale_log_units` char(2) DEFAULT NULL,
  `t_auto_scale_rigid` char(2) DEFAULT '',
  `auto_scale_rigid` char(2) DEFAULT NULL,
  `t_auto_padding` char(2) DEFAULT '',
  `auto_padding` char(2) DEFAULT NULL,
  `t_base_value` char(2) DEFAULT '',
  `base_value` mediumint(8) NOT NULL DEFAULT '0',
  `t_grouping` char(2) DEFAULT '',
  `grouping` char(2) NOT NULL DEFAULT '',
  `t_unit_value` char(2) DEFAULT '',
  `unit_value` varchar(20) DEFAULT NULL,
  `t_unit_exponent_value` char(2) DEFAULT '',
  `unit_exponent_value` varchar(5) NOT NULL DEFAULT '',
  `t_alt_y_grid` char(2) DEFAULT '',
  `alt_y_grid` char(2) DEFAULT NULL,
  `t_right_axis` char(2) DEFAULT '',
  `right_axis` varchar(20) DEFAULT NULL,
  `t_right_axis_label` char(2) DEFAULT '',
  `right_axis_label` varchar(200) DEFAULT NULL,
  `t_right_axis_format` char(2) DEFAULT '',
  `right_axis_format` mediumint(8) DEFAULT NULL,
  `t_right_axis_formatter` char(2) DEFAULT '',
  `right_axis_formatter` varchar(10) DEFAULT NULL,
  `t_left_axis_formatter` char(2) DEFAULT '',
  `left_axis_formatter` varchar(10) DEFAULT NULL,
  `t_no_gridfit` char(2) DEFAULT '',
  `no_gridfit` char(2) DEFAULT NULL,
  `t_unit_length` char(2) DEFAULT '',
  `unit_length` varchar(10) DEFAULT NULL,
  `t_tab_width` char(2) DEFAULT '',
  `tab_width` varchar(20) DEFAULT '30',
  `t_dynamic_labels` char(2) DEFAULT '',
  `dynamic_labels` char(2) DEFAULT NULL,
  `t_force_rules_legend` char(2) DEFAULT '',
  `force_rules_legend` char(2) DEFAULT NULL,
  `t_legend_position` char(2) DEFAULT '',
  `legend_position` varchar(10) DEFAULT NULL,
  `t_legend_direction` char(2) DEFAULT '',
  `legend_direction` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `local_graph_id` (`local_graph_id`),
  KEY `graph_template_id` (`graph_template_id`),
  KEY `title_cache` (`title_cache`(191))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores the actual graph data.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_templates_graph`
--

LOCK TABLES `graph_templates_graph` WRITE;
/*!40000 ALTER TABLE `graph_templates_graph` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_templates_graph` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_templates_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_templates_item` (
  `id` int(12) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `local_graph_template_item_id` int(12) unsigned NOT NULL DEFAULT '0',
  `local_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `task_item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `color_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `alpha` char(2) DEFAULT 'FF',
  `graph_type_id` tinyint(3) NOT NULL DEFAULT '0',
  `line_width` decimal(4,2) DEFAULT '0.00',
  `dashes` varchar(20) DEFAULT NULL,
  `dash_offset` mediumint(4) DEFAULT NULL,
  `cdef_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `vdef_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `shift` char(2) DEFAULT NULL,
  `consolidation_function_id` tinyint(2) NOT NULL DEFAULT '0',
  `textalign` varchar(10) DEFAULT NULL,
  `text_format` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `hard_return` char(2) DEFAULT NULL,
  `gprint_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `graph_template_id` (`graph_template_id`),
  KEY `local_graph_id_sequence` (`local_graph_id`,`sequence`),
  KEY `task_item_id` (`task_item_id`),
  KEY `lgi_gti` (`local_graph_id`,`graph_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores the actual graph item data.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_templates_item`
--

LOCK TABLES `graph_templates_item` WRITE;
/*!40000 ALTER TABLE `graph_templates_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_templates_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_tree`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_tree` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `enabled` char(2) DEFAULT 'on',
  `locked` tinyint(4) DEFAULT '0',
  `locked_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sort_type` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL DEFAULT '',
  `sequence` int(10) unsigned DEFAULT '1',
  `user_id` int(10) unsigned DEFAULT '1',
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified_by` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `sequence` (`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_tree`
--

LOCK TABLES `graph_tree` WRITE;
/*!40000 ALTER TABLE `graph_tree` DISABLE KEYS */;
INSERT INTO `graph_tree` (`id`, `enabled`, `locked`, `locked_date`, `sort_type`, `name`, `sequence`, `user_id`, `last_modified`, `modified_by`) VALUES (1,'on',0,'0000-00-00 00:00:00',1,'Default Tree',1,1,'0000-00-00 00:00:00',1);
/*!40000 ALTER TABLE `graph_tree` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `graph_tree_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graph_tree_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent` bigint(20) unsigned DEFAULT NULL,
  `position` int(10) unsigned DEFAULT NULL,
  `graph_tree_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `local_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) DEFAULT NULL,
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `site_id` int(10) unsigned DEFAULT '0',
  `host_grouping_type` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `sort_children_type` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `graph_regex` varchar(60) DEFAULT '',
  `host_regex` varchar(60) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `graph_tree_id` (`graph_tree_id`),
  KEY `host_id` (`host_id`),
  KEY `site_id` (`site_id`),
  KEY `local_graph_id` (`local_graph_id`),
  KEY `parent_position` (`parent`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `graph_tree_items`
--

LOCK TABLES `graph_tree_items` WRITE;
/*!40000 ALTER TABLE `graph_tree_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `graph_tree_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `poller_id` int(10) unsigned NOT NULL DEFAULT '1',
  `site_id` int(10) unsigned NOT NULL DEFAULT '1',
  `host_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `description` varchar(150) NOT NULL DEFAULT '',
  `hostname` varchar(100) DEFAULT NULL,
  `notes` text,
  `external_id` varchar(40) DEFAULT NULL,
  `snmp_community` varchar(100) DEFAULT NULL,
  `snmp_version` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `snmp_username` varchar(50) DEFAULT NULL,
  `snmp_password` varchar(50) DEFAULT NULL,
  `snmp_auth_protocol` char(5) DEFAULT '',
  `snmp_priv_passphrase` varchar(200) DEFAULT '',
  `snmp_priv_protocol` char(6) DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(64) DEFAULT '',
  `snmp_port` mediumint(5) unsigned NOT NULL DEFAULT '161',
  `snmp_timeout` mediumint(8) unsigned NOT NULL DEFAULT '500',
  `snmp_sysDescr` varchar(300) NOT NULL DEFAULT '',
  `snmp_sysObjectID` varchar(64) NOT NULL DEFAULT '',
  `snmp_sysUpTimeInstance` int(10) unsigned NOT NULL DEFAULT '0',
  `snmp_sysContact` varchar(300) NOT NULL DEFAULT '',
  `snmp_sysName` varchar(300) NOT NULL DEFAULT '',
  `snmp_sysLocation` varchar(300) NOT NULL DEFAULT '',
  `availability_method` smallint(5) unsigned NOT NULL DEFAULT '1',
  `ping_method` smallint(5) unsigned DEFAULT '0',
  `ping_port` int(12) unsigned DEFAULT '0',
  `ping_timeout` int(12) unsigned DEFAULT '500',
  `ping_retries` int(12) unsigned DEFAULT '2',
  `max_oids` int(12) unsigned DEFAULT '10',
  `device_threads` tinyint(2) unsigned NOT NULL DEFAULT '1',
  `disabled` char(2) DEFAULT NULL,
  `status` tinyint(2) NOT NULL DEFAULT '0',
  `status_event_count` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `status_fail_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status_rec_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status_last_error` varchar(255) DEFAULT '',
  `min_time` decimal(10,5) DEFAULT '9.99999',
  `max_time` decimal(10,5) DEFAULT '0.00000',
  `cur_time` decimal(10,5) DEFAULT '0.00000',
  `avg_time` decimal(10,5) DEFAULT '0.00000',
  `polling_time` double DEFAULT '0',
  `total_polls` int(12) unsigned DEFAULT '0',
  `failed_polls` int(12) unsigned DEFAULT '0',
  `availability` decimal(8,5) NOT NULL DEFAULT '100.00000',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `poller_id` (`poller_id`),
  KEY `site_id` (`site_id`),
  KEY `external_id` (`external_id`),
  KEY `disabled` (`disabled`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host`
--

LOCK TABLES `host` WRITE;
/*!40000 ALTER TABLE `host` DISABLE KEYS */;
/*!40000 ALTER TABLE `host` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_graph`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_graph` (
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host_id`,`graph_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_graph`
--

LOCK TABLES `host_graph` WRITE;
/*!40000 ALTER TABLE `host_graph` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_graph` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_snmp_cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_snmp_cache` (
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_query_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `field_name` varchar(50) NOT NULL DEFAULT '',
  `field_value` varchar(512) DEFAULT NULL,
  `snmp_index` varchar(191) NOT NULL DEFAULT '',
  `oid` text NOT NULL,
  `present` tinyint(4) NOT NULL DEFAULT '1',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host_id`,`snmp_query_id`,`field_name`,`snmp_index`),
  KEY `host_id` (`host_id`,`field_name`),
  KEY `snmp_index` (`snmp_index`),
  KEY `field_name` (`field_name`),
  KEY `field_value` (`field_value`(191)),
  KEY `snmp_query_id` (`snmp_query_id`),
  KEY `present` (`present`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_snmp_cache`
--

LOCK TABLES `host_snmp_cache` WRITE;
/*!40000 ALTER TABLE `host_snmp_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_snmp_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_snmp_query`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_snmp_query` (
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_query_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sort_field` varchar(50) NOT NULL DEFAULT '',
  `title_format` varchar(50) NOT NULL DEFAULT '',
  `reindex_method` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host_id`,`snmp_query_id`),
  KEY `host_id` (`host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_snmp_query`
--

LOCK TABLES `host_snmp_query` WRITE;
/*!40000 ALTER TABLE `host_snmp_query` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_snmp_query` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_template`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_template` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_template`
--

LOCK TABLES `host_template` WRITE;
/*!40000 ALTER TABLE `host_template` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_template_graph`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_template_graph` (
  `host_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host_template_id`,`graph_template_id`),
  KEY `host_template_id` (`host_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_template_graph`
--

LOCK TABLES `host_template_graph` WRITE;
/*!40000 ALTER TABLE `host_template_graph` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_template_graph` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `host_template_snmp_query`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_template_snmp_query` (
  `host_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_query_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host_template_id`,`snmp_query_id`),
  KEY `host_template_id` (`host_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `host_template_snmp_query`
--

LOCK TABLES `host_template_snmp_query` WRITE;
/*!40000 ALTER TABLE `host_template_snmp_query` DISABLE KEYS */;
/*!40000 ALTER TABLE `host_template_snmp_query` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_config`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_config` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `directory` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(2) NOT NULL DEFAULT '0',
  `author` varchar(64) NOT NULL DEFAULT '',
  `webpage` varchar(255) NOT NULL DEFAULT '',
  `version` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `directory` (`directory`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_config`
--

LOCK TABLES `plugin_config` WRITE;
/*!40000 ALTER TABLE `plugin_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_db_changes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_db_changes` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `plugin` varchar(16) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `column` varchar(64) NOT NULL,
  `method` varchar(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`),
  KEY `method` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_db_changes`
--

LOCK TABLES `plugin_db_changes` WRITE;
/*!40000 ALTER TABLE `plugin_db_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_db_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_hooks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_hooks` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL DEFAULT '',
  `hook` varchar(64) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  `function` varchar(128) NOT NULL DEFAULT '',
  `status` int(8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `hook` (`hook`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_hooks`
--

LOCK TABLES `plugin_hooks` WRITE;
/*!40000 ALTER TABLE `plugin_hooks` DISABLE KEYS */;
INSERT INTO `plugin_hooks` (`id`, `name`, `hook`, `file`, `function`, `status`) VALUES (1,'internal','config_arrays','','plugin_config_arrays',1);
INSERT INTO `plugin_hooks` (`id`, `name`, `hook`, `file`, `function`, `status`) VALUES (2,'internal','draw_navigation_text','','plugin_draw_navigation_text',1);
/*!40000 ALTER TABLE `plugin_hooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_realms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_realms` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `plugin` varchar(32) NOT NULL DEFAULT '',
  `file` text NOT NULL,
  `display` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `plugin` (`plugin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_realms`
--

LOCK TABLES `plugin_realms` WRITE;
/*!40000 ALTER TABLE `plugin_realms` DISABLE KEYS */;
INSERT INTO `plugin_realms` (`id`, `plugin`, `file`, `display`) VALUES (1,'internal','plugins.php','Plugin Management');
/*!40000 ALTER TABLE `plugin_realms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COMMENT='Pollers for Cacti';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller`
--

LOCK TABLES `poller` WRITE;
/*!40000 ALTER TABLE `poller` DISABLE KEYS */;
INSERT INTO `poller` (`id`, `disabled`, `name`, `notes`, `status`, `hostname`, `dbdefault`, `dbhost`, `dbuser`, `dbpass`, `dbport`, `dbssl`, `total_time`, `snmp`, `script`, `server`, `last_update`, `last_status`) VALUES (1,'','Main Poller','',0,'localhost','cacti','cacti','','',3306,'',0,0,0,0,'0000-00-00 00:00:00','0000-00-00 00:00:00');
/*!40000 ALTER TABLE `poller` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_command`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_command` (
  `poller_id` smallint(5) unsigned NOT NULL DEFAULT '1',
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `action` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `command` varchar(191) NOT NULL DEFAULT '',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`poller_id`,`action`,`command`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_command`
--

LOCK TABLES `poller_command` WRITE;
/*!40000 ALTER TABLE `poller_command` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_command` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_data_template_field_mappings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_data_template_field_mappings` (
  `data_template_id` int(10) unsigned NOT NULL DEFAULT '0',
  `data_name` varchar(25) NOT NULL DEFAULT '',
  `data_source_names` varchar(191) NOT NULL DEFAULT '',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`data_template_id`,`data_name`,`data_source_names`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Tracks mapping of Data Templates to their Data Source Names';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_data_template_field_mappings`
--

LOCK TABLES `poller_data_template_field_mappings` WRITE;
/*!40000 ALTER TABLE `poller_data_template_field_mappings` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_data_template_field_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_item` (
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `poller_id` int(10) unsigned NOT NULL DEFAULT '1',
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `action` tinyint(2) unsigned NOT NULL DEFAULT '1',
  `present` tinyint(4) NOT NULL DEFAULT '1',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hostname` varchar(100) NOT NULL DEFAULT '',
  `snmp_community` varchar(100) NOT NULL DEFAULT '',
  `snmp_version` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `snmp_username` varchar(50) NOT NULL DEFAULT '',
  `snmp_password` varchar(50) NOT NULL DEFAULT '',
  `snmp_auth_protocol` varchar(5) NOT NULL DEFAULT '',
  `snmp_priv_passphrase` varchar(200) NOT NULL DEFAULT '',
  `snmp_priv_protocol` varchar(6) NOT NULL DEFAULT '',
  `snmp_context` varchar(64) DEFAULT '',
  `snmp_engine_id` varchar(64) DEFAULT '',
  `snmp_port` mediumint(5) unsigned NOT NULL DEFAULT '161',
  `snmp_timeout` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rrd_name` varchar(19) NOT NULL DEFAULT '',
  `rrd_path` varchar(255) NOT NULL DEFAULT '',
  `rrd_num` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `rrd_step` mediumint(8) NOT NULL DEFAULT '300',
  `rrd_next_step` mediumint(8) NOT NULL DEFAULT '0',
  `arg1` text,
  `arg2` varchar(255) DEFAULT NULL,
  `arg3` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`),
  KEY `local_data_id` (`local_data_id`),
  KEY `host_id` (`host_id`),
  KEY `rrd_next_step` (`rrd_next_step`),
  KEY `action` (`action`),
  KEY `present` (`present`),
  KEY `poller_id_host_id` (`poller_id`,`host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_item`
--

LOCK TABLES `poller_item` WRITE;
/*!40000 ALTER TABLE `poller_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_output`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_output` (
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rrd_name` varchar(19) NOT NULL DEFAULT '',
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `output` text NOT NULL,
  PRIMARY KEY (`local_data_id`,`rrd_name`,`time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_output`
--

LOCK TABLES `poller_output` WRITE;
/*!40000 ALTER TABLE `poller_output` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_output` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_output_boost`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_output_boost` (
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rrd_name` varchar(19) NOT NULL DEFAULT '',
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `output` varchar(512) NOT NULL,
  PRIMARY KEY (`local_data_id`,`time`,`rrd_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_output_boost`
--

LOCK TABLES `poller_output_boost` WRITE;
/*!40000 ALTER TABLE `poller_output_boost` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_output_boost` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_output_boost_processes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_output_boost_processes` (
  `sock_int_value` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`sock_int_value`)
) ENGINE=MEMORY DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_output_boost_processes`
--

LOCK TABLES `poller_output_boost_processes` WRITE;
/*!40000 ALTER TABLE `poller_output_boost_processes` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_output_boost_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_output_realtime`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_output_realtime` (
  `local_data_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rrd_name` varchar(19) NOT NULL DEFAULT '',
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `output` text NOT NULL,
  `poller_id` varchar(256) NOT NULL DEFAULT '1',
  PRIMARY KEY (`local_data_id`,`rrd_name`,`time`),
  KEY `poller_id` (`poller_id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_output_realtime`
--

LOCK TABLES `poller_output_realtime` WRITE;
/*!40000 ALTER TABLE `poller_output_realtime` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_output_realtime` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_reindex`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_reindex` (
  `host_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_query_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `action` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `present` tinyint(4) NOT NULL DEFAULT '1',
  `op` char(1) NOT NULL DEFAULT '',
  `assert_value` varchar(100) NOT NULL DEFAULT '',
  `arg1` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`host_id`,`data_query_id`),
  KEY `present` (`present`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_reindex`
--

LOCK TABLES `poller_reindex` WRITE;
/*!40000 ALTER TABLE `poller_reindex` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_reindex` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_resource_cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_resource_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resource_type` varchar(20) DEFAULT NULL,
  `md5sum` varchar(32) DEFAULT NULL,
  `path` varchar(191) DEFAULT NULL,
  `update_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `contents` longblob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Caches all scripts, resources files, and plugins';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_resource_cache`
--

LOCK TABLES `poller_resource_cache` WRITE;
/*!40000 ALTER TABLE `poller_resource_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_resource_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poller_time`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_time` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) unsigned NOT NULL DEFAULT '0',
  `poller_id` int(10) unsigned NOT NULL DEFAULT '1',
  `start_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poller_time`
--

LOCK TABLES `poller_time` WRITE;
/*!40000 ALTER TABLE `poller_time` DISABLE KEYS */;
/*!40000 ALTER TABLE `poller_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `mailtime` (`mailtime`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Cacri Reporting Reports';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Cacti Reporting Items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports_items`
--

LOCK TABLES `reports_items` WRITE;
/*!40000 ALTER TABLE `reports_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(32) NOT NULL,
  `remote_addr` varchar(25) NOT NULL DEFAULT '',
  `access` int(10) unsigned DEFAULT NULL,
  `data` mediumblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Used for Database based Session Storage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `name` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_tree`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings_tree` (
  `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `graph_tree_item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`graph_tree_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_tree`
--

LOCK TABLES `settings_tree` WRITE;
/*!40000 ALTER TABLE `settings_tree` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings_tree` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings_user` (
  `user_id` smallint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_user`
--

LOCK TABLES `settings_user` WRITE;
/*!40000 ALTER TABLE `settings_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_user_group`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings_user_group` (
  `group_id` smallint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`group_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores the Default User Group Graph Settings';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_user_group`
--

LOCK TABLES `settings_user_group` WRITE;
/*!40000 ALTER TABLE `settings_user_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings_user_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `notes` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `city` (`city`),
  KEY `state` (`state`),
  KEY `postal_code` (`postal_code`),
  KEY `country` (`country`),
  KEY `alternate_id` (`alternate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Contains information about customer sites';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmp_query`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmp_query` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `xml_path` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT NULL,
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_input_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmp_query`
--

LOCK TABLES `snmp_query` WRITE;
/*!40000 ALTER TABLE `snmp_query` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmp_query` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmp_query_graph`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmp_query_graph` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `snmp_query_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `graph_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmp_query_graph`
--

LOCK TABLES `snmp_query_graph` WRITE;
/*!40000 ALTER TABLE `snmp_query_graph` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmp_query_graph` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmp_query_graph_rrd`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmp_query_graph_rrd` (
  `snmp_query_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_rrd_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `snmp_field_name` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`snmp_query_graph_id`,`data_template_id`,`data_template_rrd_id`),
  KEY `data_template_rrd_id` (`data_template_rrd_id`),
  KEY `snmp_query_graph_id` (`snmp_query_graph_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmp_query_graph_rrd`
--

LOCK TABLES `snmp_query_graph_rrd` WRITE;
/*!40000 ALTER TABLE `snmp_query_graph_rrd` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmp_query_graph_rrd` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmp_query_graph_rrd_sv`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmp_query_graph_rrd_sv` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `snmp_query_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `field_name` varchar(100) NOT NULL DEFAULT '',
  `text` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `snmp_query_graph_id` (`snmp_query_graph_id`),
  KEY `data_template_id` (`data_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmp_query_graph_rrd_sv`
--

LOCK TABLES `snmp_query_graph_rrd_sv` WRITE;
/*!40000 ALTER TABLE `snmp_query_graph_rrd_sv` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmp_query_graph_rrd_sv` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmp_query_graph_sv`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmp_query_graph_sv` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `snmp_query_graph_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `field_name` varchar(100) NOT NULL DEFAULT '',
  `text` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `snmp_query_graph_id` (`snmp_query_graph_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmp_query_graph_sv`
--

LOCK TABLES `snmp_query_graph_sv` WRITE;
/*!40000 ALTER TABLE `snmp_query_graph_sv` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmp_query_graph_sv` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `mib_name` (`mib`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='SNMP MIB CACHE';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_cache`
--

LOCK TABLES `snmpagent_cache` WRITE;
/*!40000 ALTER TABLE `snmpagent_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_cache_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmpagent_cache_notifications` (
  `name` varchar(191) NOT NULL,
  `mib` varchar(255) NOT NULL,
  `attribute` varchar(255) NOT NULL,
  `sequence_id` smallint(6) NOT NULL,
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Notifcations and related attributes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_cache_notifications`
--

LOCK TABLES `snmpagent_cache_notifications` WRITE;
/*!40000 ALTER TABLE `snmpagent_cache_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_cache_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_cache_textual_conventions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmpagent_cache_textual_conventions` (
  `name` varchar(191) NOT NULL,
  `mib` varchar(191) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(5000) NOT NULL DEFAULT '',
  KEY `name` (`name`),
  KEY `mib` (`mib`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Textual conventions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_cache_textual_conventions`
--

LOCK TABLES `snmpagent_cache_textual_conventions` WRITE;
/*!40000 ALTER TABLE `snmpagent_cache_textual_conventions` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_cache_textual_conventions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_managers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='snmp notification receivers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_managers`
--

LOCK TABLES `snmpagent_managers` WRITE;
/*!40000 ALTER TABLE `snmpagent_managers` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_managers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_managers_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmpagent_managers_notifications` (
  `manager_id` int(8) NOT NULL,
  `notification` varchar(180) NOT NULL,
  `mib` varchar(191) NOT NULL,
  KEY `mib` (`mib`),
  KEY `manager_id_notification` (`manager_id`,`notification`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='snmp notifications to receivers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_managers_notifications`
--

LOCK TABLES `snmpagent_managers_notifications` WRITE;
/*!40000 ALTER TABLE `snmpagent_managers_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_managers_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_mibs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `snmpagent_mibs` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Registered MIB files';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_mibs`
--

LOCK TABLES `snmpagent_mibs` WRITE;
/*!40000 ALTER TABLE `snmpagent_mibs` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_mibs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snmpagent_notifications_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `manager_id_notification` (`manager_id`,`notification`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='logs snmp notifications to receivers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snmpagent_notifications_log`
--

LOCK TABLES `snmpagent_notifications_log` WRITE;
/*!40000 ALTER TABLE `snmpagent_notifications_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `snmpagent_notifications_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL DEFAULT '0',
  `password` varchar(2048) NOT NULL DEFAULT '',
  `realm` mediumint(8) NOT NULL DEFAULT '0',
  `full_name` varchar(100) DEFAULT '0',
  `email_address` varchar(128) DEFAULT NULL,
  `must_change_password` char(2) DEFAULT NULL,
  `password_change` char(2) DEFAULT 'on',
  `show_tree` char(2) DEFAULT 'on',
  `show_list` char(2) DEFAULT 'on',
  `show_preview` char(2) NOT NULL DEFAULT 'on',
  `graph_settings` char(2) DEFAULT NULL,
  `login_opts` tinyint(1) NOT NULL DEFAULT '1',
  `policy_graphs` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_trees` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_hosts` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `policy_graph_templates` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  `lastchange` int(12) NOT NULL DEFAULT '-1',
  `lastlogin` int(12) NOT NULL DEFAULT '-1',
  `password_history` varchar(4096) NOT NULL DEFAULT '-1',
  `locked` varchar(3) NOT NULL DEFAULT '',
  `failed_attempts` int(5) NOT NULL DEFAULT '0',
  `lastfail` int(12) NOT NULL DEFAULT '0',
  `reset_perms` int(12) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `realm` (`realm`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth`
--

LOCK TABLES `user_auth` WRITE;
/*!40000 ALTER TABLE `user_auth` DISABLE KEYS */;
INSERT INTO `user_auth` (`id`, `username`, `password`, `realm`, `full_name`, `email_address`, `must_change_password`, `password_change`, `show_tree`, `show_list`, `show_preview`, `graph_settings`, `login_opts`, `policy_graphs`, `policy_trees`, `policy_hosts`, `policy_graph_templates`, `enabled`, `lastchange`, `lastlogin`, `password_history`, `locked`, `failed_attempts`, `lastfail`, `reset_perms`) VALUES (1,'admin','21232f297a57a5a743894a0e4a801fc3',0,'Administrator','','on','on','on','on','on','',2,1,1,1,1,'on',-1,-1,'-1','',0,0,0);
INSERT INTO `user_auth` (`id`, `username`, `password`, `realm`, `full_name`, `email_address`, `must_change_password`, `password_change`, `show_tree`, `show_list`, `show_preview`, `graph_settings`, `login_opts`, `policy_graphs`, `policy_trees`, `policy_hosts`, `policy_graph_templates`, `enabled`, `lastchange`, `lastlogin`, `password_history`, `locked`, `failed_attempts`, `lastfail`, `reset_perms`) VALUES (3,'guest','43e9a4ab75570f5b',0,'Guest Account','','on','on','on','on','on','3',1,1,1,1,1,'',-1,-1,'-1','',0,0,0);
/*!40000 ALTER TABLE `user_auth` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Caches Remember Me Details';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_cache`
--

LOCK TABLES `user_auth_cache` WRITE;
/*!40000 ALTER TABLE `user_auth_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_group`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table that Contains User Groups';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_group`
--

LOCK TABLES `user_auth_group` WRITE;
/*!40000 ALTER TABLE `user_auth_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_group_members`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_group_members` (
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`user_id`),
  KEY `realm_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table that Contains User Group Members';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_group_members`
--

LOCK TABLES `user_auth_group_members` WRITE;
/*!40000 ALTER TABLE `user_auth_group_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_group_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_group_perms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_group_perms` (
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`,`item_id`,`type`),
  KEY `group_id` (`group_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table that Contains User Group Permissions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_group_perms`
--

LOCK TABLES `user_auth_group_perms` WRITE;
/*!40000 ALTER TABLE `user_auth_group_perms` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_group_perms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_group_realm`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_group_realm` (
  `group_id` int(10) unsigned NOT NULL,
  `realm_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`realm_id`),
  KEY `realm_id` (`realm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table that Contains User Group Realm Permissions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_group_realm`
--

LOCK TABLES `user_auth_group_realm` WRITE;
/*!40000 ALTER TABLE `user_auth_group_realm` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_group_realm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_perms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_perms` (
  `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`item_id`,`type`),
  KEY `user_id` (`user_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_perms`
--

LOCK TABLES `user_auth_perms` WRITE;
/*!40000 ALTER TABLE `user_auth_perms` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_auth_perms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_auth_realm`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth_realm` (
  `realm_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`realm_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_auth_realm`
--

LOCK TABLES `user_auth_realm` WRITE;
/*!40000 ALTER TABLE `user_auth_realm` DISABLE KEYS */;
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (1,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (2,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (3,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (4,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (5,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (7,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (8,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (9,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (10,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (11,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (12,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (13,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (14,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (15,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (16,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (17,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (18,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (20,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (21,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (22,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (23,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (101,1);
INSERT INTO `user_auth_realm` (`realm_id`, `user_id`) VALUES (7,3);
/*!40000 ALTER TABLE `user_auth_realm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_domains`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_domains` (
  `domain_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(20) NOT NULL,
  `type` int(10) unsigned NOT NULL DEFAULT '0',
  `enabled` char(2) NOT NULL DEFAULT 'on',
  `defdomain` tinyint(3) NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table to Hold Login Domains';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_domains`
--

LOCK TABLES `user_domains` WRITE;
/*!40000 ALTER TABLE `user_domains` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_domains_ldap`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table to Hold Login Domains for LDAP';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_domains_ldap`
--

LOCK TABLES `user_domains_ldap` WRITE;
/*!40000 ALTER TABLE `user_domains_ldap` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_domains_ldap` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_log` (
  `username` varchar(50) NOT NULL DEFAULT '0',
  `user_id` mediumint(8) NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `result` tinyint(1) NOT NULL DEFAULT '0',
  `ip` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`username`,`user_id`,`time`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_log`
--

LOCK TABLES `user_log` WRITE;
/*!40000 ALTER TABLE `user_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vdef`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vdef` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COMMENT='vdef';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vdef`
--

LOCK TABLES `vdef` WRITE;
/*!40000 ALTER TABLE `vdef` DISABLE KEYS */;
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (1,'e06ed529238448773038601afb3cf278','Maximum');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (2,'e4872dda82092393d6459c831a50dc3b','Minimum');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (3,'5ce1061a46bb62f36840c80412d2e629','Average');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (4,'06bd3cbe802da6a0745ea5ba93af554a','Last (Current)');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (5,'631c1b9086f3979d6dcf5c7a6946f104','First');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (6,'6b5335843630b66f858ce6b7c61fc493','Total: Current Data Source');
INSERT INTO `vdef` (`id`, `hash`, `name`) VALUES (7,'c80d12b0f030af3574da68b28826cd39','95th Percentage: Current Data Source');
/*!40000 ALTER TABLE `vdef` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vdef_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vdef_items` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `vdef_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `sequence` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(2) NOT NULL DEFAULT '0',
  `value` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `vdef_id_sequence` (`vdef_id`,`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COMMENT='vdef items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vdef_items`
--

LOCK TABLES `vdef_items` WRITE;
/*!40000 ALTER TABLE `vdef_items` DISABLE KEYS */;
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (1,'88d33bf9271ac2bdf490cf1784a342c1',1,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (2,'a307afab0c9b1779580039e3f7c4f6e5',1,2,1,'1');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (3,'0945a96068bb57c80bfbd726cf1afa02',2,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (4,'95a8df2eac60a89e8a8ca3ea3d019c44',2,2,1,'2');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (5,'cc2e1c47ec0b4f02eb13708cf6dac585',3,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (6,'a2fd796335b87d9ba54af6a855689507',3,2,1,'3');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (7,'a1d7974ee6018083a2053e0d0f7cb901',4,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (8,'26fccba1c215439616bc1b83637ae7f3',4,2,1,'5');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (9,'a8993b265f4c5398f4a47c44b5b37a07',5,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (10,'5a380d469d611719057c3695ce1e4eee',5,2,1,'6');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (11,'65cfe546b17175fad41fcca98c057feb',6,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (12,'f330b5633c3517d7c62762cef091cc9e',6,2,1,'7');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (13,'f1bf2ecf54ca0565cf39c9c3f7e5394b',7,1,4,'CURRENT_DATA_SOURCE');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (14,'11a26f18feba3919be3af426670cba95',7,2,6,'95');
INSERT INTO `vdef_items` (`id`, `hash`, `vdef_id`, `sequence`, `type`, `value`) VALUES (15,'e7ae90275bc1efada07c19ca3472d9db',7,3,1,'8');
/*!40000 ALTER TABLE `vdef_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `version`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `version` (
  `cacti` char(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`cacti`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `version`
--

LOCK TABLES `version` WRITE;
/*!40000 ALTER TABLE `version` DISABLE KEYS */;
INSERT INTO `version` (`cacti`) VALUES ('new_install');
/*!40000 ALTER TABLE `version` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-04-28  9:52:47
