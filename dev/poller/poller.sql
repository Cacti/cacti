CREATE TABLE targets (
  id tinyint(4) NOT NULL auto_increment,
  host varchar(64) NOT NULL default '',
  comm varchar(64) NOT NULL default '',
  oid varchar(64) NOT NULL default '',
  rrd varchar(64) NOT NULL default '',
  UNIQUE KEY id (id)
) TYPE=MyISAM;
