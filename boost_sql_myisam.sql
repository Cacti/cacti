DROP TABLE IF EXISTS `poller_output_boost`;
CREATE TABLE  `poller_output_boost` (
  `local_data_id` mediumint(8) unsigned NOT NULL default '0',
  `rrd_name` varchar(19) NOT NULL default '',
  `time` datetime NOT NULL default '0000-00-00 00:00:00',
  `output` varchar(512) NOT NULL,
  PRIMARY KEY USING BTREE (`local_data_id`,`rrd_name`,`time`)
) ENGINE=MyISAM ROW_FORMAT=FIXED;

DROP TABLE IF EXISTS `poller_output_boost_processes`;
CREATE TABLE  `poller_output_boost_processes` (
  `sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
  `status` varchar(255) default NULL,
  PRIMARY KEY  (`sock_int_value`)
) ENGINE=MEMORY;
