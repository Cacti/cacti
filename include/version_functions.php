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
	$version = db_fetch_cell("select cacti from version");
	
	if ($version == "") {
		return "0.6";
	}else{
		return $version;
	}
}

function UpdateCacti($old_version, $new_version) {
	
	/* never ever re-update */
	if ($old_version == $new_version) {
		return 2;
	}
	
	if ($old_version <= "0.6") {
		/* forced password change (all passwords set to 'admin' initially) */
		db_execute("update auth_users set mustchangepassword=\"on\" where 1=1");
		db_execute("update auth_users set password=\"43e9a4ab75570f5b\" where 1=1");
		
		/* data input update */
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/tcp_custom.pl <grepstr>\" where id=19");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/loadavg.pl <min>\" where id=9");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/users.pl <username>\" where id=6");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/memfree.pl <grepstr>\" where id=24");
		db_execute("update src set formatstrin=\"<path_snmp>/snmpget -O neEXbqfsStv <ip> <community> <oid>\" where id=13");
		db_execute("update src set formatstrin=\"php -q <path_cacti>/cmd_snmp_interface.php <inout>:<ip>:<community>:<ifdesc>:<ifnum>:<ifmac>:<ifip>\" where id=11");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/sql.pl\" where id=8");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/proc.pl\" where id=5");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/tcp.pl <mode>\" where id=7");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/webhits.pl\" where id=23");
		db_execute("update src set formatstrin=\"perl <path_cacti>/scripts/ping.pl <num> <ip>\" where id=1");
		
		/* update menu table */
		db_execute("delete from menu_items where id=8");
		db_execute("INSERT INTO menu_items VALUES ('16','4','Logout User','logout.php','8','1','','images/menu_item_logout_user.png','3')");
		
		/* create version table */
		db_execute("CREATE TABLE version (cacti char(15) default NULL)");
		
		print "<strong><font color=\"#FF0000\">PLEASE READ!</font></strong> The password function 
			has been changed in this version. All passwords have been set to 'admin', 
			you will be forced to change the password upon login.";
	}
	
	if ($old_version <= "0.6.1") {
		/* some minor table updates */
		db_execute("ALTER TABLE `rrd_graph_item` CHANGE `Name` `Value` VARCHAR(50) DEFAULT NULL");
		db_execute("ALTER TABLE `settings` ADD `PageRefresh` SMALLINT(5) DEFAULT '300'");
	}
	
	if ($old_version <= "0.6.2") {
		/* update table structures */
		db_execute("ALTER TABLE `settings` RENAME `settings_graphs`");
		db_execute("CREATE TABLE settings (Name varchar(50) NOT NULL default '',
			Value varchar(255) NOT NULL default '',
  			FriendlyName varchar(100) NOT NULL default '',
  			Description varchar(255) NOT NULL default '',
  			Method varchar(255) default NULL,
  			PRIMARY KEY  (Name),
  			UNIQUE KEY Name (Name)) TYPE=MyISAM");
		db_execute("ALTER TABLE `rrd_graph` ADD `AutoScaleOpts` TINYINT(1) DEFAULT '2', 
			ADD `Rigid` CHAR(2) DEFAULT 'on',
			ADD `BaseValue` mediumint(8) NOT NULL DEFAULT '1000'");
		db_execute("ALTER TABLE `rrd_ds` ADD `Step` SMALLINT(5) DEFAULT '300' NOT NULL");
		db_execute("ALTER TABLE `rrd_graph` CHANGE `UpperLimit` `UpperLimit` BIGINT(12) DEFAULT NULL");
		db_execute("ALTER TABLE `rrd_graph` CHANGE `LowerLimit` `LowerLimit` BIGINT(12) DEFAULT NULL");
		db_execute("ALTER TABLE `rrd_ds_cdef` ADD `Type` TINYINT(1) DEFAULT '1' NOT NULL ");
		
		/* update rra titles */
		db_execute("update rrd_rra set name=\"Daily (5 Minute Average)\" where id=1");
		db_execute("update rrd_rra set name=\"Weekly (30 Minute Average)\" where id=2");
		db_execute("update rrd_rra set name=\"Monthly (2 Hour Average)\" where id=3");
		db_execute("update rrd_rra set name=\"Yearly (1 Day Average)\" where id=4");
		
		/* update cdef titles */
		db_execute("update rrd_ds_cdef set name=\"Turn Bytes into Bits\" where id=2");
		db_execute("INSERT INTO rrd_ds_cdef VALUES (0, 'Total All Data Sources', '2')");
		
		/* insert some settings; the rest will be done by environment.php */
		db_execute("INSERT INTO settings VALUES ('path_webcacti', '<DEFAULT>', 'cacti Web Root', 'the path, under your webroot where cacti lyes, would be \'/cacti\'  in most cases if you are accessing cacti by: http://yourhost.com/cacti/.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_webroot', '<DEFAULT>', 'Apache Web Root', 'Your apache web root, is \'/var/www/html\' or \'/home/httpd/html\' in most cases.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_snmpget', '<DEFAULT>', 'snmpget Path', 'The path to your snmpget binary.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_snmpwalk', '<DEFAULT>', 'snmpwalk Path', 'The path to your snmpwalk binary.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_rrdtool', '<DEFAULT>', 'rrdtool Binary Path', 'Path to the rrdtool binary', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('log', '', 'Log File', 'What cacti should put in its log.', 'group:log_graph:log_create:log_update:log_snmp')");
		db_execute("INSERT INTO settings VALUES ('log_graph', '', '', 'Graph', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('log_create', 'on', '', 'Create', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('log_update', 'on', '', 'Update', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('log_snmp', 'on', '', 'SNMP', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('vis_main_column_bold', 'on', '', 'Make the Main Column in Forms Bold', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('vis', '', 'Visual', 'Various visual settings in cacti', 'group:vis_main_column_bold')");
		db_execute("INSERT INTO settings VALUES ('global_auth', 'on', '', 'Use cacti\'s Builtin Authentication', 'checkbox:group')");
		db_execute("INSERT INTO settings VALUES ('global', '', 'Global Settings', 'Settings that control how cacti works', 'group:global_auth')");
		
		/* redo all data in menu_items (all columns have changed */
		db_execute("delete from menu_items");
		db_execute("INSERT INTO menu_items VALUES (3, 2, 'Data Sources', 'ds.php', 3, 1, '', 'images/menu_item_data_sources.gif', 1)");
		db_execute("INSERT INTO menu_items VALUES (4, 2, 'Round Robin Archives', 'rra.php', 9, 1, '', 'images/menu_item_round_robin_archives.gif', 3)");
		db_execute("INSERT INTO menu_items VALUES (6, 2, 'SNMP Interfaces', 'snmp.php', 3, 1, '', 'images/menu_item_snmp_interfaces.gif', 5)");
		db_execute("INSERT INTO menu_items VALUES (7, 3, 'Cron Printout', 'cron.php', 2, 1, '', 'images/menu_item_cron_printout.gif', 1)");
		db_execute("INSERT INTO menu_items VALUES (2, 1, 'Colors', 'color.php', 5, 1, '', 'images/menu_item_colors.gif', 3)");
		db_execute("INSERT INTO menu_items VALUES (5, 2, 'Data Input', 'data.php', 2, 1, '', 'images/menu_item_data_input.gif', 4)");
		db_execute("INSERT INTO menu_items VALUES (16, 4, 'Logout User', 'logout.php', 8, 1, '', 'images/menu_item_logout_user.gif', 4)");
		db_execute("INSERT INTO menu_items VALUES (12, 1, 'Graph Hierarchy', 'tree.php', 5, 1, '', 'images/menu_item_graph_hierarchy.gif', 2)");
		db_execute("INSERT INTO menu_items VALUES (18, 3, 'cacti Settings', 'settings.php', 1, 1, '', 'images/menu_item_cacti_settings.gif', 2)");
		db_execute("INSERT INTO menu_items VALUES (17, 4, 'User Administration', 'user_admin.php', 1, 1, '', 'images/menu_item_user_administration.gif', 3)");
		db_execute("INSERT INTO menu_items VALUES (14, 2, 'CDEF\'s', 'cdef.php', 3, 1, '', 'images/menu_item_cdef.gif', 2)");
		db_execute("INSERT INTO menu_items VALUES (1, 1, 'Graphs', 'graphs.php', 5, 1, '', 'images/menu_item_graphs.gif', 1)");
		
		db_execute("delete from menu_category");
		db_execute("INSERT INTO menu_category VALUES (1, 1, 'Graph Setup', 'images/menu_header_graph_setup.gif', 1)");
		db_execute("INSERT INTO menu_category VALUES (2, 1, 'Data Gathering', 'images/menu_header_data_gathering.gif', 2)");
		db_execute("INSERT INTO menu_category VALUES (3, 1, 'Configuration', 'images/menu_header_configuration.gif', 3)");
		db_execute("INSERT INTO menu_category VALUES (4, 1, 'Utilities', 'images/menu_header_utilities.gif', 4)");
		
		db_execute("update src set formatstrin=\"php -q <path_cacti>/scripts/sql.php\" where id=8");
		db_execute("update src set formatstrin=\"<path_snmpget> -O neEXbqfsStv <ip> <community> <oid>\" where id=13");
	}
	
	if ($old_version <= "0.6.3") {
		db_execute("ALTER TABLE `graph_hierarchy` RENAME `graph_hierarchy_items`");
		db_execute("ALTER TABLE `graph_hierarchy_items` ADD `TreeID` SMALLINT(5) NOT NULL AFTER `ID`");
		db_execute("CREATE TABLE graph_hierarchy (ID smallint(5) NOT NULL auto_increment, Name varchar(255) NOT NULL default '', PRIMARY KEY  (ID), UNIQUE KEY ID (ID))");
		db_execute("ALTER TABLE `settings_graphs` ADD `TreeID` SMALLINT(5) NOT NULL AFTER `RRAID`");
		db_execute("CREATE TABLE settings_tree (UserID smallint(5) NOT NULL default '0', TreeItemID smallint(5) NOT NULL default '0', Status tinyint(1) NOT NULL default '0')");
		
		db_execute("INSERT INTO settings VALUES ('path_php_binary', '<DEFAULT>', 'PHP Binary Path', 'The path to your PHP binary file (may require a php recompile to get this file).', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_html_export', '', 'HTML Export Path', 'If you want cacti to write static png\'s and html files a directory when data is gathered, specify the location here. This feature is similar to MRTG, graphs do not have to be generated on the fly this way. Leave this field blank to disable this feature.', 'textbox')");
		
		db_execute("INSERT INTO graph_hierarchy VALUES (1, 'Default Tree')");
		db_execute("UPDATE graph_hierarchy_items SET TreeID=1");
		
		db_execute("update src set formatstrin=\"<path_php_binary> -q <path_cacti>/scripts/sql.php\" where id=8");
		db_execute("update src set formatstrin=\"<path_php_binary> -q <path_cacti>/cmd_snmp_interface.php <inout>:<ip>:<community>:<ifdesc>:<ifnum>:<ifmac>:<ifip>\" where id=11");
	}
	
	if ($old_version <= "0.6.4") {
		db_execute("ALTER TABLE `src` CHANGE `Command` `Type` CHAR(20)");
		
		db_execute("UPDATE src SET type=\"snmp_net\", formatstrin=\"INTERNAL: [<ip>/<community>] Interface: [<ifnum>]\" WHERE id=11");
		db_execute("UPDATE src SET type=\"snmp\", formatstrin=\"INTERNAL: [<ip>/<community>] OID: [<oid>]\" WHERE id=13");
		
		/* write values for dsname and dspath */
		$ds_list = db_fetch_assoc("select id,name,dsname,dspath from rrd_ds");
		//$rows = mysql_num_rows($sql_id); $i = 0;
		
		include_once ('include/functions.php');
		
		foreach ($ds_list as $ds) {
			/* DSPATH: is composed of the 'name' field + '.rrd' */
			if ($ds[dspath] == "") {
				$dspath = "<path_rra>/" . $ds[name] . ".rrd";
			}else{
				$dsname = $ds[dspath];
			}
			
			/* DSPATH: is composed of the 'name' field run through a check function */
			if ($ds[dsname] == "") {
				$dsname = CheckDataSourceName($ds[name]);
			}else{
				$dsname = $ds[dsname];
			}
			
			db_execute("update rrd_ds set dsname=\"$dsname\", dspath=\"$dspath\" where 
				id=" . $ds[id]);
			
			//$i++;
		}
	}
	
	if ($old_version <= "0.6.5") {
		db_execute("ALTER TABLE `rrd_graph_item` ADD `SequenceParent` SMALLINT(5) DEFAULT '0' NOT NULL, ADD `Parent` SMALLINT(5) DEFAULT '0' NOT NULL");
		db_execute("ALTER TABLE `rrd_graph` ADD `Grouping` CHAR(2), ADD `Export` CHAR(2) DEFAULT 'on'");
		db_execute("ALTER TABLE `auth_users` DROP `AccountDisabled`, DROP `CanChangePassword`, DROP `LockoutLimit`, DROP `AccountLockedOut`, DROP `Hide`, ADD `ShowTree` CHAR(2) DEFAULT 'on', ADD `ShowList` CHAR(2) DEFAULT 'on', ADD `ShowPreview` CHAR(2) DEFAULT 'on' NOT NULL, ADD `GraphSettings` CHAR(2) DEFAULT 'on', ADD `LoginOpts` TINYINT(1) DEFAULT '1' NOT NULL");
		db_execute("ALTER TABLE `rrd_ds` ADD `SubDSID` SMALLINT(5) DEFAULT '0' NOT NULL AFTER `ID`, ADD `SubFieldID` SMALLINT(5) DEFAULT '0' NOT NULL AFTER `SubDSID`, ADD `IsParent` TINYINT(1) DEFAULT '0' NOT NULL");
		db_execute("CREATE TABLE auth_graph (UserID smallint(5) NOT NULL default '0',GraphID smallint(5) NOT NULL default '0')");
		db_execute("CREATE TABLE auth_graph_hierarchy (UserID smallint(5) NOT NULL default '0',HierarchyID smallint(5) NOT NULL default '0')");
		
		db_execute("INSERT INTO settings VALUES ('guest_user', 'guest', 'Guest User', 'The name of the guest user for viewing graphs; is \"guest\" by default.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_html_export_skip', '1', 'Export Every x Times', 'If you don\'t want cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.', 'textbox')");
		db_execute("INSERT INTO settings VALUES ('path_html_export_ctr', '', '', '', 'internal')");
	}
	
	if ($old_version <= "0.6.6") {
		db_execute("ALTER TABLE `auth_users` ADD `GraphPolicy` TINYINT(1) DEFAULT '1' NOT NULL");
	}
	
	if ($old_version <= "0.6.7") {
		db_execute("ALTER TABLE `rrd_graph` ADD `UnitValue` VARCHAR(20), ADD `UnitExponentValue` SMALLINT(5) DEFAULT '0' NOT NULL, ADD `AutoScaleLog` CHAR(2)");
		db_execute("ALTER TABLE `rrd_graph_item` ADD `GprintOpts` TINYINT(1) DEFAULT '1' NOT NULL, ADD `GprintCustom` VARCHAR(100)");
		db_execute("ALTER TABLE `auth_log` DROP `AttemptedPass`");
		db_execute("ALTER TABLE `settings_graphs` ADD `DefaultViewMode` TINYINT(1) DEFAULT '1' NOT NULL");
		db_execute("INSERT INTO settings VALUES ('remove_verification', 'on', 'Remove Verification', 'Confirm Before the User Removes an Item', 'checkbox:group')");
		db_execute("UPDATE settings set method='group:global_auth:remove_verification' where name='global'");
	    
	    ##  Added 05-20-2002 at 11:32
	        db_execute("ALTER TABLE rrd_graph ADD order_key VARCHAR(60) NOT NULL AFTER ID, ADD INDEX (order_key)");
	    
	    ##  Added 05-21-2002 at 12:00
	        db_execute("ALTER TABLE settings_tree RENAME settings_viewing_tree;");
	    db_execute("CREATE TABLE settings_ds_tree (UserID smallint(5) NOT NULL default '0',
						       TreeItemID smallint(5) NOT NULL default '0', 
						       Status tinyint(1) NOT NULL default '0'
						       ) TYPE=MyISAM;");
	    
	    db_execute("CREATE TABLE settings_graph_tree (UserID smallint(5) NOT NULL default '0',
							  TreeItemID smallint(5) NOT NULL default '0',
							  Status tinyint(1) NOT NULL default '0'
							  ) TYPE=MyISAM;");
	    ##  Added 05-22-2002 at 15:17
	    db_execute("CREATE TABLE polling_tree (ptree_id bigint(20) NOT NULL auto_increment,
						   order_key varchar(60) NOT NULL default '',
						   host_id bigint(20) NOT NULL default '0',
						   name varchar(30) default NULL,
						   PRIMARY KEY  (ptree_id)
						   ) TYPE=MyISAM;");
	    
	    db_execute("CREATE TABLE polling_hosts (host_id bigint(20) NOT NULL auto_increment,
						    hostname varchar(50) NOT NULL default '',
						    domain varchar(250) NOT NULL default '',
						    descrip varchar(255) default NULL,
						    mgmt_ip varchar(15) NOT NULL default '',
						    snmp_ver tinyint(1) NOT NULL default '0',
						    snmp_string varchar(255) default NULL,
						    snmp_user varchar(50) default NULL,
						    snmp_pass varchar(50) default NULL,
						    PRIMARY KEY  (host_id)
						    ) TYPE=MyISAM COMMENT='Hosts that we''ll present data for.';");
	    
	    db_execute("CREATE TABLE polling_tasks (task_id bigint(20) NOT NULL auto_increment,
						    host_id bigint(20) NOT NULL default '0',
						    name varchar(50) NOT NULL default '',
						    descrip varchar(200) NOT NULL default '',
						    polling_interval int(10) unsigned NOT NULL default '0',
						    to_be_polled tinyint(1) unsigned NOT NULL default '0',
						    PRIMARY KEY  (task_id)
						    ) TYPE=MyISAM;");
	    
	    db_execute("CREATE TABLE polling_items (item_id bigint(20) unsigned NOT NULL auto_increment,
						    task_id bigint(20) unsigned NOT NULL default '0',
						    descrip varchar(150) NOT NULL default '',
						    heartbeat int(10) unsigned NOT NULL default '0',
						    min_value bigint(20) NOT NULL default '0',
						    max_value bigint(20) NOT NULL default '0',
						    snmp_oid varchar(100) NOT NULL default '',
						    script_arg_num tinyint(3) NOT NULL default '0',
						    PRIMARY KEY  (item_id)
						    ) TYPE=MyISAM COMMENT='The actual pieces of data that each polling task will gather';");
	    
	    #################################################################################################################
	    ## 
	    ##  Need some *nasty* magic in here to rip their old data sources and snmp stuff into the new table structure.
	    ## 
	    #################################################################################################################
	    
	    db_execute("ALTER TABLE rrd_graph_tree ADD order_key VARCHAR(60) NOT NULL AFTER ID, ADD graph_id bigint NOT NULL, ADD INDEX (order_key)");
	    db_execute("ALTER TABLE rrd_graph DROP COLUMN order_key, DROP INDEX(order_key);");
	    
	    ##  Added 06-07-2002 at 13:37
	    db_execute("INSERT INTO settings VALUES ('use_polling_zones', 'off', 'Use Polling
						     Zones', 'If you want to do distributed polling you can set up \'polling zones\'
						     which correspond to each of your polling machines.  Polling Hosts are then associated with a particular polling zone.', 'checkbox')");

	    db_execute("CREATE TABLE polling_zones (
					pz_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					zone_name VARCHAR(100) NOT NULL
					);");
	    
	    db_execute("INSERT INTO polling_zones (pz_id, zone_name) VALUES ('0', 'Default Polling Zone');");
	    
	    ##  Added 06-07-2002 at 15:31
	    db_execute("ALTER TABLE polling_hosts CHANGE COLUMN is_profile profile_id INT(11)");
	    db_execute("CREATE TABLE polling_profiles (
						       profile_id int(11) NOT NULL auto_increment,
						       profile_name varchar(100) NOT NULL default '',
						       PRIMARY KEY  (profile_id)
						       ) TYPE=MyISAM;");
	}
    
    return 0;
}

?>
