<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_5() {
	/*
	ALTER TABLE `graph_tree_items` ADD `host_grouping_type` TINYINT( 3 ) UNSIGNED DEFAULT '1' NOT NULL ,
ADD `sort_children_type` TINYINT( 3 ) UNSIGNED DEFAULT '1' NOT NULL;
	UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,' (In)','') where snmp_query_graph_id = 2;
	ALTER TABLE `host_snmp_query` ADD `sort_field` VARCHAR( 50 ) NOT NULL ,
ADD `title_format` VARCHAR( 50 ) NOT NULL ;
	DROP TABLE `snmp_query_field`;
	ALTER TABLE `graph_tree_items` CHANGE `order_key` `order_key` VARCHAR( 100 ) DEFAULT '0' NOT NULL;
	ALTER TABLE `host_snmp_query` ADD `reindex_method` TINYINT( 3 ) UNSIGNED NOT NULL;


CREATE TABLE `poller` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `hostname` varchar(250) NOT NULL default '',
  `ip_address` int(11) unsigned NOT NULL default '0',
  `last_update` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) TYPE=MyISAM;
CREATE TABLE `poller_field` (
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `data_input_field_name` varchar(100) NOT NULL default '',
  `rrd_data_source_name` varchar(19) NOT NULL default '',
  `output` text NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_data_source_name`)
) TYPE=MyISAM;
CREATE TABLE `poller_item` (
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `poller_id` smallint(5) unsigned NOT NULL default '0',
  `host_id` mediumint(8) NOT NULL default '0',
  `action` tinyint(2) unsigned NOT NULL default '1',
  `hostname` varchar(250) NOT NULL default '',
  `snmp_community` varchar(100) NOT NULL default '',
  `snmp_version` tinyint(1) unsigned NOT NULL default '0',
  `snmp_username` varchar(50) NOT NULL default '',
  `snmp_password` varchar(50) NOT NULL default '',
  `snmp_port` mediumint(5) unsigned NOT NULL default '161',
  `snmp_timeout` mediumint(8) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `rrd_path` varchar(255) NOT NULL default '',
  `rrd_num` tinyint(2) unsigned NOT NULL default '0',
  `arg1` varchar(255) default NULL,
  `arg2` varchar(255) default NULL,
  `arg3` varchar(255) default NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`),
  KEY `local_data_id` (`local_data_id`)
) TYPE=MyISAM;
CREATE TABLE `poller_output` (
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `time` datetime NOT NULL default '0000-00-00 00:00:00',
  `output` text NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`,`time`)
) TYPE=MyISAM;
CREATE TABLE `poller_time` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `poller_id` smallint(5) unsigned NOT NULL default '0',
  `start_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `end_time` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) TYPE=MyISAM;
CREATE TABLE `poller_command` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `poller_id` smallint(5) unsigned NOT NULL default '0',
  `time` datetime NOT NULL default '0000-00-00 00:00:00',
  `action` tinyint(3) unsigned NOT NULL default '0',
  `command` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;


	*/
}

?>
