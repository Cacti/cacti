<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?

function GetCurrentVersion() {
    $do_not_read_config = true; 
    include ('include/config.php');
	
    $cacti = db_fetch_cell("select * from version");
	
    if (!$cacti > 0) {
		return "0.6";
    }else{
		return $cacti;
    }
}

function UpdateCacti($old_version, $new_version, $database_connection_id) {
	
	/* never ever re-update */
	if ($old_version == $new_version) {
		return 2;
	}
	
	if ($old_version <= "0.6") {
		/* forced password change (all passwords set to 'admin' initially) */
		$sql_id = mysql_query("update auth_users set mustchangepassword=\"on\" where 1=1",$database_connection_id);
		$sql_id = mysql_query("update auth_users set password=\"43e9a4ab75570f5b\" where 1=1",$database_connection_id);
		
		/* data input update */
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/tcp_custom.pl <grepstr>\" where id=19",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/loadavg.pl <min>\" where id=9",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/users.pl <username>\" where id=6",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/memfree.pl <grepstr>\" where id=24",$database_connection_id);
		mysql_query("update src set formatstrin=\"<path_snmp>/snmpget -O neEXbqfsStv <ip> <community> <oid>\" where id=13",$database_connection_id);
		mysql_query("update src set formatstrin=\"php -q <path_cacti>/cmd_snmp_interface.php <inout>:<ip>:<community>:<ifdesc>:<ifnum>:<ifmac>:<ifip>\" where id=11",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/sql.pl\" where id=8",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/proc.pl\" where id=5",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/tcp.pl <mode>\" where id=7",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/webhits.pl\" where id=23",$database_connection_id);
		mysql_query("update src set formatstrin=\"perl <path_cacti>/scripts/ping.pl <num> <ip>\" where id=1",$database_connection_id);
		
		/* update menu table */
		mysql_query("delete from menu_items where id=8",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES ('16','4','Logout User','logout.php','8','1','','images/menu_item_logout_user.png','3')",$database_connection_id);
		
		/* create version table */
		mysql_query("CREATE TABLE version (cacti char(15) default NULL)",$database_connection_id);
		
		print "<strong><font color=\"#FF0000\">PLEASE READ!</font></strong> The password function 
			has been changed in this version. All passwords have been set to 'admin', 
			you will be forced to change the password upon login.";
	}
	
	if ($old_version <= "0.6.1") {
		/* some minor table updates */
		mysql_query("ALTER TABLE `rrd_graph_item` CHANGE `Name` `Value` VARCHAR(50) DEFAULT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `settings` ADD `PageRefresh` SMALLINT(5) DEFAULT '300'",$database_connection_id);
	}
	
	if ($old_version <= "0.6.2") {
		/* update table structures */
		mysql_query("ALTER TABLE `settings` RENAME `settings_graphs`",$database_connection_id);
		mysql_query("CREATE TABLE settings (Name varchar(50) NOT NULL default '',
			Value varchar(255) NOT NULL default '',
  			FriendlyName varchar(100) NOT NULL default '',
  			Description varchar(255) NOT NULL default '',
  			Method varchar(255) default NULL,
  			PRIMARY KEY  (Name),
  			UNIQUE KEY Name (Name)) TYPE=MyISAM",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_graph` ADD `AutoScaleOpts` TINYINT(1) DEFAULT '2', 
			ADD `Rigid` CHAR(2) DEFAULT 'on',
			ADD `BaseValue` mediumint(8) NOT NULL DEFAULT '1000'",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_ds` ADD `Step` SMALLINT(5) DEFAULT '300' NOT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_graph` CHANGE `UpperLimit` `UpperLimit` BIGINT(12) DEFAULT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_graph` CHANGE `LowerLimit` `LowerLimit` BIGINT(12) DEFAULT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_ds_cdef` ADD `Type` TINYINT(1) DEFAULT '1' NOT NULL ",$database_connection_id);
		
		/* update rra titles */
		mysql_query("update rrd_rra set name=\"Daily (5 Minute Average)\" where id=1",$database_connection_id);
		mysql_query("update rrd_rra set name=\"Weekly (30 Minute Average)\" where id=2",$database_connection_id);
		mysql_query("update rrd_rra set name=\"Monthly (2 Hour Average)\" where id=3",$database_connection_id);
		mysql_query("update rrd_rra set name=\"Yearly (1 Day Average)\" where id=4",$database_connection_id);
		
		/* update cdef titles */
		mysql_query("update rrd_ds_cdef set name=\"Turn Bytes into Bits\" where id=2",$database_connection_id);
		mysql_query("INSERT INTO rrd_ds_cdef VALUES (0, 'Total All Data Sources', '2')",$database_connection_id);
		
		/* insert some settings; the rest will be done by environment.php */
		mysql_query("INSERT INTO settings VALUES ('path_webcacti', '<DEFAULT>', 'cacti Web Root', 'the path, under your webroot where cacti lyes, would be \'/cacti\'  in most cases if you are accessing cacti by: http://yourhost.com/cacti/.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_webroot', '<DEFAULT>', 'Apache Web Root', 'Your apache web root, is \'/var/www/html\' or \'/home/httpd/html\' in most cases.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_snmpget', '<DEFAULT>', 'snmpget Path', 'The path to your snmpget binary.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_snmpwalk', '<DEFAULT>', 'snmpwalk Path', 'The path to your snmpwalk binary.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_rrdtool', '<DEFAULT>', 'rrdtool Binary Path', 'Path to the rrdtool binary', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('log', '', 'Log File', 'What cacti should put in its log.', 'group:log_graph:log_create:log_update:log_snmp')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('log_graph', '', '', 'Graph', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('log_create', 'on', '', 'Create', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('log_update', 'on', '', 'Update', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('log_snmp', 'on', '', 'SNMP', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('vis_main_column_bold', 'on', '', 'Make the Main Column in Forms Bold', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('vis', '', 'Visual', 'Various visual settings in cacti', 'group:vis_main_column_bold')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('global_auth', 'on', '', 'Use cacti\'s Builtin Authentication', 'checkbox:group')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('global', '', 'Global Settings', 'Settings that control how cacti works', 'group:global_auth')",$database_connection_id);
		
		/* redo all data in menu_items (all columns have changed */
		mysql_query("delete from menu_items",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (3, 2, 'Data Sources', 'ds.php', 3, 1, '', 'images/menu_item_data_sources.gif', 1)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (4, 2, 'Round Robin Archives', 'rra.php', 9, 1, '', 'images/menu_item_round_robin_archives.gif', 3)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (6, 2, 'SNMP Interfaces', 'snmp.php', 3, 1, '', 'images/menu_item_snmp_interfaces.gif', 5)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (7, 3, 'Cron Printout', 'cron.php', 2, 1, '', 'images/menu_item_cron_printout.gif', 1)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (2, 1, 'Colors', 'color.php', 5, 1, '', 'images/menu_item_colors.gif', 3)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (5, 2, 'Data Input', 'data.php', 2, 1, '', 'images/menu_item_data_input.gif', 4)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (16, 4, 'Logout User', 'logout.php', 8, 1, '', 'images/menu_item_logout_user.gif', 4)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (12, 1, 'Graph Hierarchy', 'tree.php', 5, 1, '', 'images/menu_item_graph_hierarchy.gif', 2)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (18, 3, 'cacti Settings', 'settings.php', 1, 1, '', 'images/menu_item_cacti_settings.gif', 2)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (17, 4, 'User Administration', 'user_admin.php', 1, 1, '', 'images/menu_item_user_administration.gif', 3)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (14, 2, 'CDEF\'s', 'cdef.php', 3, 1, '', 'images/menu_item_cdef.gif', 2)",$database_connection_id);
		mysql_query("INSERT INTO menu_items VALUES (1, 1, 'Graphs', 'graphs.php', 5, 1, '', 'images/menu_item_graphs.gif', 1)",$database_connection_id);
		
		mysql_query("delete from menu_category",$database_connection_id);
		mysql_query("INSERT INTO menu_category VALUES (1, 1, 'Graph Setup', 'images/menu_header_graph_setup.gif', 1)",$database_connection_id);
		mysql_query("INSERT INTO menu_category VALUES (2, 1, 'Data Gathering', 'images/menu_header_data_gathering.gif', 2)",$database_connection_id);
		mysql_query("INSERT INTO menu_category VALUES (3, 1, 'Configuration', 'images/menu_header_configuration.gif', 3)",$database_connection_id);
		mysql_query("INSERT INTO menu_category VALUES (4, 1, 'Utilities', 'images/menu_header_utilities.gif', 4)",$database_connection_id);
		
		mysql_query("update src set formatstrin=\"php -q <path_cacti>/scripts/sql.php\" where id=8",$database_connection_id);
		mysql_query("update src set formatstrin=\"<path_snmpget> -O neEXbqfsStv <ip> <community> <oid>\" where id=13",$database_connection_id);
	}
	
	if ($old_version <= "0.6.3") {
		mysql_query("ALTER TABLE `graph_hierarchy` RENAME `graph_hierarchy_items`",$database_connection_id);
		mysql_query("ALTER TABLE `graph_hierarchy_items` ADD `TreeID` SMALLINT(5) NOT NULL AFTER `ID`",$database_connection_id);
		mysql_query("CREATE TABLE graph_hierarchy (ID smallint(5) NOT NULL auto_increment, Name varchar(255) NOT NULL default '', PRIMARY KEY  (ID), UNIQUE KEY ID (ID))",$database_connection_id);
		mysql_query("ALTER TABLE `settings_graphs` ADD `TreeID` SMALLINT(5) NOT NULL AFTER `RRAID`",$database_connection_id);
		mysql_query("CREATE TABLE settings_tree (UserID smallint(5) NOT NULL default '0', TreeItemID smallint(5) NOT NULL default '0', Status tinyint(1) NOT NULL default '0')",$database_connection_id);
		
		mysql_query("INSERT INTO settings VALUES ('path_php_binary', '<DEFAULT>', 'PHP Binary Path', 'The path to your PHP binary file (may require a php recompile to get this file).', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_html_export', '', 'HTML Export Path', 'If you want cacti to write static png\'s and html files a directory when data is gathered, specify the location here. This feature is similar to MRTG, graphs do not have to be generated on the fly this way. Leave this field blank to disable this feature.', 'textbox')",$database_connection_id);
		
		mysql_query("INSERT INTO graph_hierarchy VALUES (1, 'Default Tree')",$database_connection_id);
		mysql_query("UPDATE graph_hierarchy_items SET TreeID=1",$database_connection_id);
		
		mysql_query("update src set formatstrin=\"<path_php_binary> -q <path_cacti>/scripts/sql.php\" where id=8",$database_connection_id);
		mysql_query("update src set formatstrin=\"<path_php_binary> -q <path_cacti>/cmd_snmp_interface.php <inout>:<ip>:<community>:<ifdesc>:<ifnum>:<ifmac>:<ifip>\" where id=11",$database_connection_id);
	}
	
	if ($old_version <= "0.6.4") {
		mysql_query("ALTER TABLE `src` CHANGE `Command` `Type` CHAR(20)",$database_connection_id);
		
		mysql_query("UPDATE src SET type=\"snmp_net\", formatstrin=\"INTERNAL: [<ip>/<community>] Interface: [<ifnum>]\" WHERE id=11",$database_connection_id);
		mysql_query("UPDATE src SET type=\"snmp\", formatstrin=\"INTERNAL: [<ip>/<community>] OID: [<oid>]\" WHERE id=13",$database_connection_id);
		
		/* write values for dsname and dspath */
		$sql_id = mysql_query("select id,name,dsname,dspath from rrd_ds", $database_connection_id);
		$rows = mysql_num_rows($sql_id); $i = 0;
		
		include_once ('include/functions.php');
		
		while ($i < $rows) {
			/* DSPATH: is composed of the 'name' field + '.rrd' */
			if (mysql_result($sql_id, $i, "dspath") == "") {
				$dspath = "<path_rra>/" . mysql_result($sql_id, $i, "name") . ".rrd";
			}else{
				$dsname = mysql_result($sql_id, $i, "dspath");
			}
			
			/* DSPATH: is composed of the 'name' field run through a check function */
			if (mysql_result($sql_id, $i, "dsname") == "") {
				$dsname = CheckDataSourceName(mysql_result($sql_id, $i, "name"));
			}else{
				$dsname = mysql_result($sql_id, $i, "dsname");
			}
			
			mysql_query("update rrd_ds set dsname=\"$dsname\", dspath=\"$dspath\" where 
				id=" . mysql_result($sql_id, $i, "id"), $database_connection_id);
			
			$i++;
		}
	}
	
	if ($old_version <= "0.6.5") {
		mysql_query("ALTER TABLE `rrd_graph_item` ADD `SequenceParent` SMALLINT(5) DEFAULT '0' NOT NULL, ADD `Parent` SMALLINT(5) DEFAULT '0' NOT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_graph` ADD `Grouping` CHAR(2), ADD `Export` CHAR(2) DEFAULT 'on'",$database_connection_id);
		mysql_query("ALTER TABLE `auth_users` DROP `AccountDisabled`, DROP `CanChangePassword`, DROP `LockoutLimit`, DROP `AccountLockedOut`, DROP `Hide`, ADD `ShowTree` CHAR(2) DEFAULT 'on', ADD `ShowList` CHAR(2) DEFAULT 'on', ADD `ShowPreview` CHAR(2) DEFAULT 'on' NOT NULL, ADD `GraphSettings` CHAR(2) DEFAULT 'on', ADD `LoginOpts` TINYINT(1) DEFAULT '1' NOT NULL",$database_connection_id);
		mysql_query("ALTER TABLE `rrd_ds` ADD `SubDSID` SMALLINT(5) DEFAULT '0' NOT NULL AFTER `ID`, ADD `SubFieldID` SMALLINT(5) DEFAULT '0' NOT NULL AFTER `SubDSID`, ADD `IsParent` TINYINT(1) DEFAULT '0' NOT NULL",$database_connection_id);
		mysql_query("CREATE TABLE auth_graph (UserID smallint(5) NOT NULL default '0',GraphID smallint(5) NOT NULL default '0')",$database_connection_id);
		mysql_query("CREATE TABLE auth_graph_hierarchy (UserID smallint(5) NOT NULL default '0',HierarchyID smallint(5) NOT NULL default '0')",$database_connection_id);
		
		mysql_query("INSERT INTO settings VALUES ('guest_user', 'guest', 'Guest User', 'The name of the guest user for viewing graphs; is \"guest\" by default.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_html_export_skip', '1', 'Export Every x Times', 'If you don\'t want cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.', 'textbox')",$database_connection_id);
		mysql_query("INSERT INTO settings VALUES ('path_html_export_ctr', '', '', '', 'internal')",$database_connection_id);
	}
	
	if ($old_version <= "0.6.6") {
		mysql_query("ALTER TABLE `auth_users` ADD `GraphPolicy` TINYINT(1) DEFAULT '1' NOT NULL",$database_connection_id);
	}
	
	return 0;
}

?>
