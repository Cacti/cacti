#
# Table structure for table `auth_acl`
#

CREATE TABLE auth_acl (
  SectionID smallint(4) NOT NULL default '0',
  UserID smallint(4) NOT NULL default '0'
) TYPE=MyISAM;

#
# Dumping data for table `auth_acl`
#

INSERT INTO auth_acl VALUES (7, 1);
INSERT INTO auth_acl VALUES (1, 1);
INSERT INTO auth_acl VALUES (2, 1);
INSERT INTO auth_acl VALUES (8, 1);
INSERT INTO auth_acl VALUES (9, 1);
INSERT INTO auth_acl VALUES (5, 1);
INSERT INTO auth_acl VALUES (3, 1);
INSERT INTO auth_acl VALUES (7, 3);
# --------------------------------------------------------

#
# Table structure for table `auth_areas`
#

CREATE TABLE auth_areas (
  ID smallint(4) NOT NULL auto_increment,
  Name varchar(50) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `auth_areas`
#

INSERT INTO auth_areas VALUES (1, 'cacti');
# --------------------------------------------------------

#
# Table structure for table `auth_graph`
#

CREATE TABLE auth_graph (
  UserID smallint(5) NOT NULL default '0',
  GraphID smallint(5) NOT NULL default '0'
) TYPE=MyISAM;

#
# Dumping data for table `auth_graph`
#

# --------------------------------------------------------

#
# Table structure for table `auth_graph_hierarchy`
#

CREATE TABLE auth_graph_hierarchy (
  UserID smallint(5) NOT NULL default '0',
  HierarchyID smallint(5) NOT NULL default '0'
) TYPE=MyISAM;

#
# Dumping data for table `auth_graph_hierarchy`
#

# --------------------------------------------------------

#
# Table structure for table `auth_hosts`
#

CREATE TABLE auth_hosts (
  ID tinyint(4) NOT NULL auto_increment,
  Hostname char(50) default NULL,
  UserID smallint(4) NOT NULL default '0',
  Type tinyint(1) default NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `auth_hosts`
#

# --------------------------------------------------------

#
# Table structure for table `auth_log`
#

CREATE TABLE auth_log (
  ID smallint(5) NOT NULL auto_increment,
  Username char(50) NOT NULL default '0',
  Time timestamp(14) NOT NULL,
  Success tinyint(4) NOT NULL default '0',
  AttemptedPass char(255) default NULL,
  IP char(50) default NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `auth_log`
#

# --------------------------------------------------------

#
# Table structure for table `auth_sections`
#

CREATE TABLE auth_sections (
  ID smallint(4) NOT NULL auto_increment,
  Section char(50) NOT NULL default '',
  AreaID smallint(4) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `auth_sections`
#

INSERT INTO auth_sections VALUES (1, 'User Administration', 1);
INSERT INTO auth_sections VALUES (2, 'Data Input Methods', 1);
INSERT INTO auth_sections VALUES (3, 'Add/Edit Data Sources', 1);
INSERT INTO auth_sections VALUES (5, 'Add/Edit Graphs', 1);
INSERT INTO auth_sections VALUES (7, 'View Graphs', 1);
INSERT INTO auth_sections VALUES (8, 'Console Access', 1);
INSERT INTO auth_sections VALUES (9, 'Add/Edit Round Robin Archives', 1);
# --------------------------------------------------------

#
# Table structure for table `auth_users`
#

CREATE TABLE auth_users (
  ID smallint(4) NOT NULL auto_increment,
  Username char(50) NOT NULL default '0',
  Password char(50) NOT NULL default '0',
  FullName char(100) default '0',
  MustChangePassword char(2) default NULL,
  ShowTree char(2) default 'on',
  ShowList char(2) default 'on',
  ShowPreview char(2) NOT NULL default 'on',
  GraphSettings char(2) default NULL,
  LoginOpts tinyint(1) NOT NULL default '1',
  GraphPolicy tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `auth_users`
#

INSERT INTO auth_users VALUES (1, 'admin', '43e9a4ab75570f5b', 'Administrator', 'on', 'on', 'on', 'on', 'on', '1', '1');
INSERT INTO auth_users VALUES (3, 'guest', '43e9a4ab75570f5b', 'Guest Account', 'on', 'on', 'on', 'on', '', '3', '2');
# --------------------------------------------------------

#
# Table structure for table `def_cdef`
#

CREATE TABLE def_cdef (
  ID smallint(5) NOT NULL auto_increment,
  Name char(100) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_cdef`
#

INSERT INTO def_cdef VALUES (1, 'SIN');
INSERT INTO def_cdef VALUES (2, 'COS');
INSERT INTO def_cdef VALUES (3, 'LOG');
INSERT INTO def_cdef VALUES (4, 'EXP');
INSERT INTO def_cdef VALUES (5, 'FLOOR');
INSERT INTO def_cdef VALUES (6, 'CEIL');
INSERT INTO def_cdef VALUES (7, 'LT');
INSERT INTO def_cdef VALUES (8, 'LE');
INSERT INTO def_cdef VALUES (9, 'GT');
INSERT INTO def_cdef VALUES (10, 'GE');
INSERT INTO def_cdef VALUES (11, 'EQ');
INSERT INTO def_cdef VALUES (12, 'IF');
INSERT INTO def_cdef VALUES (13, 'MIN');
INSERT INTO def_cdef VALUES (14, 'MAX');
INSERT INTO def_cdef VALUES (15, 'LIMIT');
INSERT INTO def_cdef VALUES (16, 'DUP');
INSERT INTO def_cdef VALUES (17, 'EXC');
INSERT INTO def_cdef VALUES (18, 'POP');
INSERT INTO def_cdef VALUES (19, 'UN');
INSERT INTO def_cdef VALUES (20, 'UNKN');
INSERT INTO def_cdef VALUES (21, 'PREV');
INSERT INTO def_cdef VALUES (22, 'INF');
INSERT INTO def_cdef VALUES (23, 'NEGINF');
INSERT INTO def_cdef VALUES (24, 'NOW');
INSERT INTO def_cdef VALUES (25, 'TIME');
INSERT INTO def_cdef VALUES (26, 'LTIME');
INSERT INTO def_cdef VALUES (27, '+');
INSERT INTO def_cdef VALUES (28, '-');
INSERT INTO def_cdef VALUES (29, '*');
INSERT INTO def_cdef VALUES (30, '/');
INSERT INTO def_cdef VALUES (31, '%');
# --------------------------------------------------------

#
# Table structure for table `def_cf`
#

CREATE TABLE def_cf (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(50) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_cf`
#

INSERT INTO def_cf VALUES (1, 'AVERAGE');
INSERT INTO def_cf VALUES (2, 'MIN');
INSERT INTO def_cf VALUES (3, 'MAX');
INSERT INTO def_cf VALUES (4, 'LAST');
# --------------------------------------------------------

#
# Table structure for table `def_colors`
#

CREATE TABLE def_colors (
  ID smallint(5) NOT NULL auto_increment,
  Hex varchar(6) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_colors`
#

INSERT INTO def_colors VALUES (1, '000000');
INSERT INTO def_colors VALUES (2, 'FFFFFF');
INSERT INTO def_colors VALUES (4, 'FAFD9E');
INSERT INTO def_colors VALUES (5, 'C0C0C0');
INSERT INTO def_colors VALUES (6, '74C366');
INSERT INTO def_colors VALUES (7, '6DC8FE');
INSERT INTO def_colors VALUES (8, 'EA8F00');
INSERT INTO def_colors VALUES (9, 'FF0000');
INSERT INTO def_colors VALUES (10, '4444FF');
INSERT INTO def_colors VALUES (11, 'FF00FF');
INSERT INTO def_colors VALUES (12, '00FF00');
INSERT INTO def_colors VALUES (13, '8D85F3');
INSERT INTO def_colors VALUES (14, 'AD3B6E');
INSERT INTO def_colors VALUES (15, 'EACC00');
INSERT INTO def_colors VALUES (16, '12B3B5');
INSERT INTO def_colors VALUES (17, '157419');
INSERT INTO def_colors VALUES (18, 'C4FD3D');
INSERT INTO def_colors VALUES (19, '817C4E');
INSERT INTO def_colors VALUES (20, '002A97');
INSERT INTO def_colors VALUES (21, '0000FF');
INSERT INTO def_colors VALUES (22, '00CF00');
INSERT INTO def_colors VALUES (24, 'F9FD5F');
INSERT INTO def_colors VALUES (25, 'FFF200');
INSERT INTO def_colors VALUES (26, 'CCBB00');
INSERT INTO def_colors VALUES (27, '837C04');
INSERT INTO def_colors VALUES (28, 'EAAF00');
INSERT INTO def_colors VALUES (29, 'FFD660');
INSERT INTO def_colors VALUES (30, 'FFC73B');
INSERT INTO def_colors VALUES (31, 'FFAB00');
INSERT INTO def_colors VALUES (33, 'FF7D00');
INSERT INTO def_colors VALUES (34, 'ED7600');
INSERT INTO def_colors VALUES (35, 'FF5700');
INSERT INTO def_colors VALUES (36, 'EE5019');
INSERT INTO def_colors VALUES (37, 'B1441E');
INSERT INTO def_colors VALUES (38, 'FFC3C0');
INSERT INTO def_colors VALUES (39, 'FF897C');
INSERT INTO def_colors VALUES (40, 'FF6044');
INSERT INTO def_colors VALUES (41, 'FF4105');
INSERT INTO def_colors VALUES (42, 'DA4725');
INSERT INTO def_colors VALUES (43, '942D0C');
INSERT INTO def_colors VALUES (44, 'FF3932');
INSERT INTO def_colors VALUES (45, '862F2F');
INSERT INTO def_colors VALUES (46, 'FF5576');
INSERT INTO def_colors VALUES (47, '562B29');
INSERT INTO def_colors VALUES (48, 'F51D30');
INSERT INTO def_colors VALUES (49, 'DE0056');
INSERT INTO def_colors VALUES (50, 'ED5394');
INSERT INTO def_colors VALUES (51, 'B90054');
INSERT INTO def_colors VALUES (52, '8F005C');
INSERT INTO def_colors VALUES (53, 'F24AC8');
INSERT INTO def_colors VALUES (54, 'E8CDEF');
INSERT INTO def_colors VALUES (55, 'D8ACE0');
INSERT INTO def_colors VALUES (56, 'A150AA');
INSERT INTO def_colors VALUES (57, '750F7D');
INSERT INTO def_colors VALUES (58, '8D00BA');
INSERT INTO def_colors VALUES (59, '623465');
INSERT INTO def_colors VALUES (60, '55009D');
INSERT INTO def_colors VALUES (61, '3D168B');
INSERT INTO def_colors VALUES (62, '311F4E');
INSERT INTO def_colors VALUES (63, 'D2D8F9');
INSERT INTO def_colors VALUES (64, '9FA4EE');
INSERT INTO def_colors VALUES (65, '6557D0');
INSERT INTO def_colors VALUES (66, '4123A1');
INSERT INTO def_colors VALUES (67, '4668E4');
INSERT INTO def_colors VALUES (68, '0D006A');
INSERT INTO def_colors VALUES (69, '00004D');
INSERT INTO def_colors VALUES (70, '001D61');
INSERT INTO def_colors VALUES (71, '00234B');
INSERT INTO def_colors VALUES (72, '002A8F');
INSERT INTO def_colors VALUES (73, '2175D9');
INSERT INTO def_colors VALUES (74, '7CB3F1');
INSERT INTO def_colors VALUES (75, '005199');
INSERT INTO def_colors VALUES (76, '004359');
INSERT INTO def_colors VALUES (77, '00A0C1');
INSERT INTO def_colors VALUES (78, '007283');
INSERT INTO def_colors VALUES (79, '00BED9');
INSERT INTO def_colors VALUES (80, 'AFECED');
INSERT INTO def_colors VALUES (81, '55D6D3');
INSERT INTO def_colors VALUES (82, '00BBB4');
INSERT INTO def_colors VALUES (83, '009485');
INSERT INTO def_colors VALUES (84, '005D57');
INSERT INTO def_colors VALUES (85, '008A77');
INSERT INTO def_colors VALUES (86, '008A6D');
INSERT INTO def_colors VALUES (87, '00B99B');
INSERT INTO def_colors VALUES (88, '009F67');
INSERT INTO def_colors VALUES (89, '00694A');
INSERT INTO def_colors VALUES (90, '00A348');
INSERT INTO def_colors VALUES (91, '00BF47');
INSERT INTO def_colors VALUES (92, '96E78A');
INSERT INTO def_colors VALUES (93, '00BD27');
INSERT INTO def_colors VALUES (94, '35962B');
INSERT INTO def_colors VALUES (95, '7EE600');
INSERT INTO def_colors VALUES (96, '6EA100');
INSERT INTO def_colors VALUES (97, 'CAF100');
INSERT INTO def_colors VALUES (98, 'F5F800');
INSERT INTO def_colors VALUES (99, 'CDCFC4');
INSERT INTO def_colors VALUES (100, 'BCBEB3');
INSERT INTO def_colors VALUES (101, 'AAABA1');
INSERT INTO def_colors VALUES (102, '8F9286');
INSERT INTO def_colors VALUES (103, '797C6E');
INSERT INTO def_colors VALUES (104, '2E3127');
# --------------------------------------------------------

#
# Table structure for table `def_ds`
#

CREATE TABLE def_ds (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(50) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_ds`
#

INSERT INTO def_ds VALUES (1, 'GAUGE');
INSERT INTO def_ds VALUES (2, 'COUNTER');
INSERT INTO def_ds VALUES (3, 'DERIVE');
INSERT INTO def_ds VALUES (4, 'ABSOLUTE');
# --------------------------------------------------------

#
# Table structure for table `def_graph_type`
#

CREATE TABLE def_graph_type (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(50) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_graph_type`
#

INSERT INTO def_graph_type VALUES (1, 'COMMENT');
INSERT INTO def_graph_type VALUES (2, 'HRULE');
INSERT INTO def_graph_type VALUES (3, 'VRULE');
INSERT INTO def_graph_type VALUES (4, 'LINE1');
INSERT INTO def_graph_type VALUES (5, 'LINE2');
INSERT INTO def_graph_type VALUES (6, 'LINE3');
INSERT INTO def_graph_type VALUES (7, 'AREA');
INSERT INTO def_graph_type VALUES (8, 'STACK');
INSERT INTO def_graph_type VALUES (9, 'GPRINT');
# --------------------------------------------------------

#
# Table structure for table `def_image_type`
#

CREATE TABLE def_image_type (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(10) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `def_image_type`
#

INSERT INTO def_image_type VALUES (1, 'PNG');
INSERT INTO def_image_type VALUES (2, 'GIF');
# --------------------------------------------------------

#
# Table structure for table `graph_hierarchy`
#

CREATE TABLE graph_hierarchy (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(255) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `graph_hierarchy`
#

INSERT INTO graph_hierarchy VALUES (3, 'Default Tree');
# --------------------------------------------------------

#
# Table structure for table `graph_hierarchy_items`
#

CREATE TABLE graph_hierarchy_items (
  ID smallint(5) NOT NULL auto_increment,
  TreeID smallint(5) NOT NULL default '0',
  GraphID smallint(5) default NULL,
  RRAID smallint(5) default NULL,
  Title varchar(255) default NULL,
  Type varchar(10) NOT NULL default '0',
  Parent smallint(5) NOT NULL default '0',
  Sequence smallint(5) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `graph_hierarchy_items`
#

INSERT INTO graph_hierarchy_items VALUES (99, 3, 0, 1, 'Network Graphs', 'Heading', 0, 2);
INSERT INTO graph_hierarchy_items VALUES (97, 3, 6, 1, '', 'Graph', 89, 8);
INSERT INTO graph_hierarchy_items VALUES (96, 3, 10, 1, '', 'Graph', 89, 7);
INSERT INTO graph_hierarchy_items VALUES (95, 3, 56, 1, '', 'Graph', 89, 6);
INSERT INTO graph_hierarchy_items VALUES (94, 3, 55, 1, '', 'Graph', 89, 5);
INSERT INTO graph_hierarchy_items VALUES (93, 3, 7, 1, '', 'Graph', 89, 4);
INSERT INTO graph_hierarchy_items VALUES (92, 3, 13, 1, '', 'Graph', 89, 3);
INSERT INTO graph_hierarchy_items VALUES (91, 3, 53, 1, '', 'Graph', 89, 2);
INSERT INTO graph_hierarchy_items VALUES (90, 3, 57, 1, '', 'Graph', 89, 1);
INSERT INTO graph_hierarchy_items VALUES (89, 3, 0, 1, 'System Graphs', 'Heading', 0, 1);
INSERT INTO graph_hierarchy_items VALUES (100, 3, 0, 1, 'Ping Graphs', 'Heading', 99, 1);
INSERT INTO graph_hierarchy_items VALUES (101, 3, 8, 1, '', 'Graph', 100, 1);
# --------------------------------------------------------

#
# Table structure for table `lnk_ds_rra`
#

CREATE TABLE lnk_ds_rra (
  DSID smallint(5) NOT NULL default '0',
  RRAID smallint(5) NOT NULL default '0'
) TYPE=MyISAM;

#
# Dumping data for table `lnk_ds_rra`
#

INSERT INTO lnk_ds_rra VALUES (30, 4);
INSERT INTO lnk_ds_rra VALUES (30, 3);
INSERT INTO lnk_ds_rra VALUES (30, 2);
INSERT INTO lnk_ds_rra VALUES (30, 1);
INSERT INTO lnk_ds_rra VALUES (31, 4);
INSERT INTO lnk_ds_rra VALUES (31, 3);
INSERT INTO lnk_ds_rra VALUES (31, 2);
INSERT INTO lnk_ds_rra VALUES (31, 1);
INSERT INTO lnk_ds_rra VALUES (36, 4);
INSERT INTO lnk_ds_rra VALUES (36, 3);
INSERT INTO lnk_ds_rra VALUES (36, 2);
INSERT INTO lnk_ds_rra VALUES (36, 1);
INSERT INTO lnk_ds_rra VALUES (38, 4);
INSERT INTO lnk_ds_rra VALUES (38, 3);
INSERT INTO lnk_ds_rra VALUES (38, 2);
INSERT INTO lnk_ds_rra VALUES (38, 1);
INSERT INTO lnk_ds_rra VALUES (375, 4);
INSERT INTO lnk_ds_rra VALUES (375, 3);
INSERT INTO lnk_ds_rra VALUES (375, 2);
INSERT INTO lnk_ds_rra VALUES (375, 1);
INSERT INTO lnk_ds_rra VALUES (127, 4);
INSERT INTO lnk_ds_rra VALUES (127, 3);
INSERT INTO lnk_ds_rra VALUES (127, 2);
INSERT INTO lnk_ds_rra VALUES (127, 1);
INSERT INTO lnk_ds_rra VALUES (35, 4);
INSERT INTO lnk_ds_rra VALUES (35, 3);
INSERT INTO lnk_ds_rra VALUES (35, 2);
INSERT INTO lnk_ds_rra VALUES (35, 1);
INSERT INTO lnk_ds_rra VALUES (0, 4);
INSERT INTO lnk_ds_rra VALUES (0, 3);
INSERT INTO lnk_ds_rra VALUES (0, 2);
INSERT INTO lnk_ds_rra VALUES (0, 1);
INSERT INTO lnk_ds_rra VALUES (126, 4);
INSERT INTO lnk_ds_rra VALUES (126, 3);
INSERT INTO lnk_ds_rra VALUES (126, 2);
INSERT INTO lnk_ds_rra VALUES (126, 1);
INSERT INTO lnk_ds_rra VALUES (128, 4);
INSERT INTO lnk_ds_rra VALUES (128, 3);
INSERT INTO lnk_ds_rra VALUES (128, 2);
INSERT INTO lnk_ds_rra VALUES (128, 1);
INSERT INTO lnk_ds_rra VALUES (132, 4);
INSERT INTO lnk_ds_rra VALUES (132, 3);
INSERT INTO lnk_ds_rra VALUES (132, 2);
INSERT INTO lnk_ds_rra VALUES (132, 1);
INSERT INTO lnk_ds_rra VALUES (131, 4);
INSERT INTO lnk_ds_rra VALUES (131, 3);
INSERT INTO lnk_ds_rra VALUES (131, 2);
INSERT INTO lnk_ds_rra VALUES (131, 1);
INSERT INTO lnk_ds_rra VALUES (130, 4);
INSERT INTO lnk_ds_rra VALUES (130, 3);
INSERT INTO lnk_ds_rra VALUES (130, 2);
INSERT INTO lnk_ds_rra VALUES (130, 1);
INSERT INTO lnk_ds_rra VALUES (245, 4);
INSERT INTO lnk_ds_rra VALUES (245, 3);
INSERT INTO lnk_ds_rra VALUES (245, 2);
INSERT INTO lnk_ds_rra VALUES (245, 1);
# --------------------------------------------------------

#
# Table structure for table `lnk_rra_cf`
#

CREATE TABLE lnk_rra_cf (
  RRAID smallint(5) NOT NULL default '0',
  ConsolidationFunctionID smallint(5) NOT NULL default '0'
) TYPE=MyISAM;

#
# Dumping data for table `lnk_rra_cf`
#

INSERT INTO lnk_rra_cf VALUES (1, 3);
INSERT INTO lnk_rra_cf VALUES (1, 1);
INSERT INTO lnk_rra_cf VALUES (2, 3);
INSERT INTO lnk_rra_cf VALUES (4, 3);
INSERT INTO lnk_rra_cf VALUES (4, 1);
INSERT INTO lnk_rra_cf VALUES (3, 3);
INSERT INTO lnk_rra_cf VALUES (3, 1);
INSERT INTO lnk_rra_cf VALUES (2, 1);
INSERT INTO lnk_rra_cf VALUES (6, 2);
INSERT INTO lnk_rra_cf VALUES (6, 3);
INSERT INTO lnk_rra_cf VALUES (7, 1);
INSERT INTO lnk_rra_cf VALUES (7, 2);
INSERT INTO lnk_rra_cf VALUES (7, 3);
INSERT INTO lnk_rra_cf VALUES (7, 4);
# --------------------------------------------------------

#
# Table structure for table `menu`
#

CREATE TABLE menu (
  ID smallint(4) NOT NULL auto_increment,
  Name varchar(50) NOT NULL default '',
  ItemOrder tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `menu`
#

INSERT INTO menu VALUES (1, 'cacti Menu', '2');
# --------------------------------------------------------

#
# Table structure for table `menu_category`
#

CREATE TABLE menu_category (
  ID smallint(4) NOT NULL auto_increment,
  MenuID smallint(4) NOT NULL default '0',
  Name varchar(50) NOT NULL default '',
  ImagePath varchar(255) default NULL,
  Sequence smallint(5) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `menu_category`
#

INSERT INTO menu_category VALUES (1, 1, 'Graph Setup', 'images/menu_header_graph_setup.gif', 1);
INSERT INTO menu_category VALUES (2, 1, 'Data Gathering', 'images/menu_header_data_gathering.gif', 2);
INSERT INTO menu_category VALUES (3, 1, 'Configuration', 'images/menu_header_configuration.gif', 3);
INSERT INTO menu_category VALUES (4, 1, 'Utilities', 'images/menu_header_utilities.gif', 4);
# --------------------------------------------------------

#
# Table structure for table `menu_items`
#

CREATE TABLE menu_items (
  ID smallint(4) NOT NULL auto_increment,
  CategoryID smallint(4) NOT NULL default '0',
  Name varchar(50) NOT NULL default '',
  URL varchar(200) default NULL,
  SectionID smallint(4) NOT NULL default '0',
  MenuID smallint(4) NOT NULL default '0',
  Parent varchar(30) default NULL,
  ImagePath varchar(255) default NULL,
  Sequence smallint(5) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `menu_items`
#

INSERT INTO menu_items VALUES (3, 2, 'Data Sources', 'ds.php', 3, 1, '', 'images/menu_item_data_sources.gif', 1);
INSERT INTO menu_items VALUES (4, 1, 'Available RRA\'s', 'rra.php', 9, 1, '', 'images/menu_item_round_robin_archives.gif', 3);
INSERT INTO menu_items VALUES (6, 2, 'SNMP Interfaces', 'snmp.php', 3, 1, '', 'images/menu_item_snmp_interfaces.gif', 5);
INSERT INTO menu_items VALUES (7, 3, 'Cron Printout', 'cron.php', 2, 1, '', 'images/menu_item_cron_printout.gif', 1);
INSERT INTO menu_items VALUES (2, 1, 'Colors', 'color.php', 5, 1, '', 'images/menu_item_colors.gif', 3);
INSERT INTO menu_items VALUES (5, 2, 'Data Input Methods', 'data.php', 2, 1, '', 'images/menu_item_data_input.gif', 4);
INSERT INTO menu_items VALUES (16, 4, 'Logout User', 'logout.php', 8, 1, '', 'images/menu_item_logout_user.gif', 4);
INSERT INTO menu_items VALUES (12, 1, 'Graph Hierarchy', 'tree.php', 5, 1, '', 'images/menu_item_graph_hierarchy.gif', 2);
INSERT INTO menu_items VALUES (18, 3, 'cacti Settings', 'settings.php', 1, 1, '', 'images/menu_item_cacti_settings.gif', 2);
INSERT INTO menu_items VALUES (17, 4, 'User Administration', 'user_admin.php', 1, 1, '', 'images/menu_item_user_administration.gif', 3);
INSERT INTO menu_items VALUES (14, 2, 'CDEF\'s', 'cdef.php', 3, 1, '', 'images/menu_item_cdef.gif', 2);
INSERT INTO menu_items VALUES (1, 1, 'Graphs', 'graphs.php', 5, 1, '', 'images/menu_item_graphs.gif', 1);
# --------------------------------------------------------

#
# Table structure for table 'polling_zones'
#
CREATE TABLE polling_zones (				  
  pz_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  zone_name VARCHAR(100) NOT NULL
) TYPE=MyISAM;
 
INSERT INTO polling_zones (pz_id, zone_name) VALUES ('0', 'Default Polling Zone');



#
# Table structure for table 'polling_tree'

CREATE TABLE polling_tree (
  ptree_id bigint(20) NOT NULL auto_increment,
  order_key varchar(60) NOT NULL default '',
  host_id bigint(20) NOT NULL default '0',
  title varchar(30) default NULL,
  PRIMARY KEY  (ptree_id)
) TYPE=MyISAM;
	  
	      	      
#
# Table structure for table 'polling_hosts'

CREATE TABLE polling_hosts (
  host_id bigint(20) NOT NULL auto_increment,
  hostname varchar(50) NOT NULL default '',
  domain varchar(250) NOT NULL default '',
  descrip varchar(255) default NULL,
  mgmt_ip varchar(15) NOT NULL default '',
  snmp_ver tinyint(1) NOT NULL default '0',
  snmp_string varchar(255) default NULL,
  snmp_user varchar(50) default NULL,
  snmp_pass varchar(50) default NULL,
  PRIMARY KEY  (host_id)
) TYPE=MyISAM COMMENT='Hosts that we''ll present data for.';


#
# Table structure for table 'polling_tasks'

CREATE TABLE polling_tasks (
  task_id bigint(20) NOT NULL auto_increment,
  host_id bigint(20) NOT NULL default '0',
  name varchar(50) NOT NULL default '',
  descrip varchar(200) NOT NULL default '',
  polling_interval int(10) unsigned NOT NULL default '0',
  to_be_polled tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (task_id) 
) TYPE=MyISAM;
	      	          
		  
#
# Table structure for table 'polling_items'

CREATE TABLE polling_items (
  item_id bigint(20) unsigned NOT NULL auto_increment,
  task_id bigint(20) unsigned NOT NULL default '0',
  descrip varchar(150) NOT NULL default '',
  heartbeat int(10) unsigned NOT NULL default '0',
  min_value bigint(20) NOT NULL default '0',
  max_value bigint(20) NOT NULL default '0',
  snmp_oid varchar(100) NOT NULL default '',
  script_arg_num tinyint(3) NOT NULL default '0',
  PRIMARY KEY  (item_id)
) TYPE=MyISAM COMMENT='The actual pieces of data that each polling task will gather';

    
    
    
			

#
# Table structure for table `rrd_ds`
#

CREATE TABLE rrd_ds (
  ID smallint(5) NOT NULL auto_increment,
  SubDSID smallint(5) NOT NULL default '0',
  SubFieldID smallint(5) NOT NULL default '0',
  Name varchar(50) NOT NULL default '',
  DataSourceTypeID smallint(5) NOT NULL default '0',
  Heartbeat mediumint(8) default '600',
  MinValue mediumint(8) default '0',
  MaxValue bigint(12) default '1',
  SrcID smallint(5) NOT NULL default '0',
  Active char(3) default '1',
  DSName varchar(19) default NULL,
  DSPath varchar(150) default NULL,
  Step smallint(5) NOT NULL default '300',
  IsParent tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_ds`
#

INSERT INTO rrd_ds VALUES (31, 0, 0, 'system_users', 1, 600, 0, 20, 6, 'on', 'system_users', '<path_rra>/system_users.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (35, 0, 0, 'ping_uunet_phi', 1, 600, 0, 2000, 1, 'on', 'ping_uunet_phi', '<path_rra>/ping_uunet_phi.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (38, 0, 0, 'server_mysql', 2, 600, 0, 5000, 8, 'on', 'server_mysql', '<path_rra>/server_mysql.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (378, 375, 85, 'system_loadavg_5min', 1, 600, 0, 200, 0, 'on', '5min', '<path_rra>/system_loadavg.rrd', 0, '0');
INSERT INTO rrd_ds VALUES (377, 375, 86, 'system_loadavg_10min', 1, 600, 0, 200, 0, 'on', '10min', '<path_rra>/system_loadavg.rrd', 0, '0');
INSERT INTO rrd_ds VALUES (126, 0, 0, 'system_samba', 1, 600, 0, 10000, 22, 'on', 'system_samba', '<path_rra>/system_samba.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (130, 0, 0, 'system_mem_cached', 1, 600, 0, 1000000, 24, 'on', 'system_mem_cached', '<path_rra>/system_mem_cached.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (131, 0, 0, 'system_mem_swap', 1, 600, 0, 1000000, 24, 'on', 'system_mem_swap', '<path_rra>/system_mem_swap.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (132, 0, 0, 'system_mem_total', 1, 600, 0, 1000000, 24, 'on', 'system_mem_total', '<path_rra>/system_mem_total.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (375, 0, 0, 'system_loadavg', 1, 600, 0, 200, 9, 'on', 'system_loadavg', '<path_rra>/system_loadavg.rrd', 300, '1');
INSERT INTO rrd_ds VALUES (376, 375, 84, 'system_loadavg_1min', 1, 600, 0, 200, 0, 'on', '1min', '<path_rra>/system_loadavg.rrd', 0, '0');
INSERT INTO rrd_ds VALUES (30, 0, 0, 'system_proc', 1, 600, 0, 500, 5, 'on', 'system_proc', '<path_rra>/system_proc.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (245, 0, 0, 'system_mem_buffers', 1, 600, 0, 10000000, 24, 'on', 'system_mem_buffers', '<path_rra>/system_mem_buffers.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (127, 0, 0, 'system_webhits', 2, 600, 0, 100000, 23, 'on', 'system_webhits', '<path_rra>/system_webhits.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (128, 0, 0, 'system_mem_free', 1, 600, 0, 10000000, 24, 'on', 'system_mem_free', '<path_rra>/system_mem_free.rrd', 300, '0');
INSERT INTO rrd_ds VALUES (36, 0, 0, 'system_tcp', 1, 600, 0, 200, 7, 'on', 'system_tcp', '<path_rra>/system_tcp.rrd', 300, '0');
# --------------------------------------------------------

#
# Table structure for table `rrd_ds_cdef`
#

CREATE TABLE rrd_ds_cdef (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(255) NOT NULL default '',
  Type tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_ds_cdef`
#

INSERT INTO rrd_ds_cdef VALUES (2, 'Turn Bytes into Bits', '1');
INSERT INTO rrd_ds_cdef VALUES (3, 'Make Stack Negative', '1');
INSERT INTO rrd_ds_cdef VALUES (4, 'Make Per 5 Minutes', '1');
INSERT INTO rrd_ds_cdef VALUES (7, 'Total All Data Sources', '2');
INSERT INTO rrd_ds_cdef VALUES (11, 'Staggered Total of Data Sources on a Graph', '3');
INSERT INTO rrd_ds_cdef VALUES (12, 'Average of All Data Sources on a Graph', '4');
INSERT INTO rrd_ds_cdef VALUES (13, 'Staggered Average of Data Sources on a Graph', '5');
# --------------------------------------------------------

#
# Table structure for table `rrd_ds_cdef_item`
#

CREATE TABLE rrd_ds_cdef_item (
  ID smallint(5) NOT NULL auto_increment,
  DSID smallint(5) default NULL,
  CDEFID smallint(5) default NULL,
  Custom varchar(255) default NULL,
  CurrentDS char(2) default NULL,
  CDEFFunctionID smallint(5) default NULL,
  Type varchar(30) NOT NULL default '0',
  Sequence smallint(5) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_ds_cdef_item`
#

INSERT INTO rrd_ds_cdef_item VALUES (8, 54, 2, '8', '', 23, 'Custom Entry', 2);
INSERT INTO rrd_ds_cdef_item VALUES (7, 54, 2, '', 'on', 31, 'Data Source', 1);
INSERT INTO rrd_ds_cdef_item VALUES (9, 54, 2, '', '', 29, 'CDEF Function', 3);
INSERT INTO rrd_ds_cdef_item VALUES (10, 54, 4, '', 'on', 31, 'Data Source', 1);
INSERT INTO rrd_ds_cdef_item VALUES (11, 54, 4, '300', '', 31, 'Custom Entry', 2);
INSERT INTO rrd_ds_cdef_item VALUES (12, 54, 4, '', '', 29, 'CDEF Function', 3);
INSERT INTO rrd_ds_cdef_item VALUES (13, 54, 3, '', 'on', 31, 'Data Source', 1);
INSERT INTO rrd_ds_cdef_item VALUES (14, 54, 3, '-1', '', 31, 'Custom Entry', 2);
INSERT INTO rrd_ds_cdef_item VALUES (15, 54, 3, '', '', 29, 'CDEF Function', 3);
# --------------------------------------------------------

#
# Table structure for table `rrd_graph`
#

CREATE TABLE rrd_graph (
  ID smallint(5) NOT NULL auto_increment,
  order_key varchar(60) NOT NULL,
  ImageFormatID smallint(4) NOT NULL default '0',
  Title varchar(200) default NULL,
  Height smallint(5) NOT NULL default '0',
  Width smallint(5) NOT NULL default '0',
  UpperLimit bigint(12) default NULL,
  LowerLimit bigint(12) default NULL,
  VerticalLabel varchar(200) default NULL,
  AutoScale char(2) default NULL,
  AutoPadding char(2) default NULL,
  AutoScaleOpts tinyint(1) default '2',
  Rigid char(2) default 'on',
  BaseValue mediumint(8) NOT NULL default '1000',
  Grouping char(2) default NULL,
  Export char(2) default 'on',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID),
  UNIQUE KEY order_key (order_key)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_graph`
#

INSERT INTO rrd_graph VALUES (1, '01000000000000000000000000000000000000000000000000000000000', 1, 'Localhost', 0, 0, 0, 0, '', '', '', '', '', 0, 'on', '');
INSERT INTO rrd_graph VALUES (2, '01010000000000000000000000000000000000000000000000000000000', 1, 'Vitals', 0, 0, 0, 0, '', '', '', '', '', 0, 'on', '');
INSERT INTO rrd_graph VALUES (6, '01010100000000000000000000000000000000000000000000000000000', 1, 'System Processes', 150, 500, 100, 0, 'Active Processes', '', 'on', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (7, '01010200000000000000000000000000000000000000000000000000000', 1, 'Logged In Users', 150, 500, 5, 0, 'Users Logged In', 'on', '', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (55,'01010300000000000000000000000000000000000000000000000000000', 1, 'Memory Usage #1', 150, 500, 0, 0, 'Memory (kB)', 'on', '', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (56,'01010400000000000000000000000000000000000000000000000000000', 1, 'Memory Usage #2', 150, 500, 0, 0, 'Memory (kB)', 'on', '', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (13,'01010500000000000000000000000000000000000000000000000000000', 1, 'Load Average', 150, 500, 4, 0, 'Average Load', 'on', '', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (3, '01020000000000000000000000000000000000000000000000000000000', 1, 'Services', 0, 0, 0, 0, '', '', '', '', '', 0, 'on', '');
INSERT INTO rrd_graph VALUES (10,'01020100000000000000000000000000000000000000000000000000000', 1, 'MySQL Usage', 150, 500, 300, 0, 'SQL Questions', 'on', '', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (53,'01020200000000000000000000000000000000000000000000000000000', 1, 'Apache/Samba/MySQL', 120, 500, 0, 0, 'Connections', 'on', 'on', '2', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (57,'01020300000000000000000000000000000000000000000000000000000', 1, 'Apache Web Hits', 150, 500, 0, 0, 'Hits', 'on', '', '1', 'on', 1000, 'on', 'on');
INSERT INTO rrd_graph VALUES (8, '01030000000000000000000000000000000000000000000000000000000', 1, 'WAN Links (Ping Times)', 150, 500, 1000, 0, 'Milliseconds', 'on', 'on', '2', 'on', 1000, 'on', 'on');
# --------------------------------------------------------

#
# Table structure for table `rrd_graph_item`
#

CREATE TABLE rrd_graph_item (
  ID smallint(5) NOT NULL auto_increment,
  DSID smallint(5) NOT NULL default '0',
  ColorID smallint(5) default '0',
  TextFormat mediumblob,
  Value varchar(50) default NULL,
  Sequence smallint(5) NOT NULL default '0',
  GraphID smallint(5) NOT NULL default '0',
  GraphTypeID smallint(5) NOT NULL default '0',
  ConsolidationFunction smallint(5) NOT NULL default '1',
  HardReturn char(2) default NULL,
  CDEFID smallint(5) default '0',
  SequenceParent smallint(5) NOT NULL default '0',
  Parent smallint(5) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID),
  KEY DSID (DSID),
  KEY GraphID (GraphID)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_graph_item`
#

INSERT INTO rrd_graph_item VALUES (417, 127, 0, 'Maximum:', '', 4, 57, 9, 3, 'on', 4, 1, 414);
INSERT INTO rrd_graph_item VALUES (416, 127, 0, 'Average:', '', 3, 57, 9, 1, '', 4, 1, 414);
INSERT INTO rrd_graph_item VALUES (414, 127, 95, 'Web Hits', '', 0, 57, 7, 1, '', 4, 1, 414);
INSERT INTO rrd_graph_item VALUES (407, 245, 35, 'Memory Buffers: <mem> kB', '', 0, 55, 8, 1, 'on', 0, 2, 407);
INSERT INTO rrd_graph_item VALUES (411, 131, 28, 'Total Swap Memory: <mem> kB',  NULL, 0, 56, 7, 1, 'on', 0, 1, 411);
INSERT INTO rrd_graph_item VALUES (412, 130, 46, 'Total Memory Cache: <mem> kB',  NULL, 0, 56, 8, 1, 'on', 0, 2, 412);
INSERT INTO rrd_graph_item VALUES (48, 31, 10, 'Users Logged In', '', 0, 7, 7, 1, '', 0, 1, 48);
INSERT INTO rrd_graph_item VALUES (55, 38, 16, 'MySQL Questions/sec', '', 0, 10, 7, 1, '', 0, 1, 55);
INSERT INTO rrd_graph_item VALUES (69, 376, 9, '1 Minute: <1min>', '', 0, 13, 8, 1, 'on', 0, 3, 69);
INSERT INTO rrd_graph_item VALUES (67, 377, 15, '10 Minute: <10min>', '', 0, 13, 7, 1, 'on', 0, 1, 67);
INSERT INTO rrd_graph_item VALUES (68, 378, 8, '5 Minute: <5min>', '', 0, 13, 8, 1, 'on', 0, 2, 68);
INSERT INTO rrd_graph_item VALUES (89, 35, 90, '<ip>', '', 0, 8, 7, 1, '', 0, 1, 89);
INSERT INTO rrd_graph_item VALUES (609, 0, 1, 'Total', '', 0, 13, 4, 1, '', 7, 4, 609);
INSERT INTO rrd_graph_item VALUES (406, 128, 52, 'Memory Free: <mem> kB',  NULL, 0, 55, 7, 1, 'on', 0, 1, 406);
INSERT INTO rrd_graph_item VALUES (405, 127, 0, 'Maximum:',  NULL, 4, 53, 9, 3, 'on', 0, 3, 402);
INSERT INTO rrd_graph_item VALUES (404, 127, 0, 'Average:',  NULL, 3, 53, 9, 1, '', 0, 3, 402);
INSERT INTO rrd_graph_item VALUES (403, 127, 0, 'Current:', '', 2, 53, 9, 4, '', 0, 3, 402);
INSERT INTO rrd_graph_item VALUES (402, 127, 75, 'Web Hits',  NULL, 0, 53, 8, 1, '', 4, 3, 402);
INSERT INTO rrd_graph_item VALUES (401, 38, 0, 'Maximum:',  NULL, 4, 53, 9, 3, 'on', 0, 2, 398);
INSERT INTO rrd_graph_item VALUES (400, 38, 0, 'Average:',  NULL, 3, 53, 9, 1, '', 0, 2, 398);
INSERT INTO rrd_graph_item VALUES (399, 38, 0, 'Current:',  NULL, 2, 53, 9, 4, '', 0, 2, 398);
INSERT INTO rrd_graph_item VALUES (398, 38, 74, 'MySQL Queries',  NULL, 0, 53, 8, 1, '', 0, 2, 398);
INSERT INTO rrd_graph_item VALUES (393, 35, 0, 'Maximum:',  NULL, 4, 8, 9, 3, 'on', 0, 1, 89);
INSERT INTO rrd_graph_item VALUES (392, 35, 0, 'Average:',  NULL, 3, 8, 9, 1, '', 0, 1, 89);
INSERT INTO rrd_graph_item VALUES (391, 35, 0, 'Current:',  NULL, 2, 8, 9, 4, '', 0, 1, 89);
INSERT INTO rrd_graph_item VALUES (381, 126, 73, 'Samba Files Opened',  NULL, 0, 53, 7, 1, '', 0, 1, 381);
INSERT INTO rrd_graph_item VALUES (395, 126, 0, 'Current:', '', 2, 53, 9, 4, '', 0, 1, 381);
INSERT INTO rrd_graph_item VALUES (396, 126, 0, 'Average:',  NULL, 3, 53, 9, 1, '', 0, 1, 381);
INSERT INTO rrd_graph_item VALUES (397, 126, 0, 'Maximum:',  NULL, 4, 53, 9, 3, 'on', 0, 1, 381);
INSERT INTO rrd_graph_item VALUES (413, 132, 69, 'Total Physical Memory: <mem> kB', '<mem>', 1, 56, 2, 1, 'on', 0, 2, 412);
INSERT INTO rrd_graph_item VALUES (415, 127, 0, 'Current:', '', 2, 57, 9, 4, '', 4, 1, 414);
INSERT INTO rrd_graph_item VALUES (870, 38, 0, 'Current:', '', 2, 10, 9, 4, '', 0, 1, 55);
INSERT INTO rrd_graph_item VALUES (871, 38, 0, 'Average:', '', 3, 10, 9, 1, '', 0, 1, 55);
INSERT INTO rrd_graph_item VALUES (872, 38, 0, 'Maximum:', '', 4, 10, 9, 3, 'on', 0, 1, 55);
INSERT INTO rrd_graph_item VALUES (1125, 30, 9, 'Processes', '', 0, 6, 7, 1, '', 0, 1, 1125);
# --------------------------------------------------------

#
# Table structure for table `rrd_rra`
#

CREATE TABLE rrd_rra (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(200) NOT NULL default '',
  XFilesFactor double default NULL,
  Steps mediumint(8) default '1',
  Rows mediumint(8) default '600',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `rrd_rra`
#

INSERT INTO rrd_rra VALUES (1, 'Daily (5 Minute Average)', '0.5', 1, 600);
INSERT INTO rrd_rra VALUES (2, 'Weekly (30 Minute Average)', '0.5', 6, 700);
INSERT INTO rrd_rra VALUES (4, 'Yearly (1 Day Average)', '0.5', 288, 797);
INSERT INTO rrd_rra VALUES (3, 'Monthly (2 Hour Average)', '0.5', 24, 775);
# --------------------------------------------------------

#
# Table structure for table `settings`
#

CREATE TABLE settings (
  Name varchar(50) NOT NULL default '',
  Value varchar(255) NOT NULL default '',
  FriendlyName varchar(100) NOT NULL default '',
  Description varchar(255) NOT NULL default '',
  Method varchar(255) default NULL,
  PRIMARY KEY  (Name),
  UNIQUE KEY Name (Name)
) TYPE=MyISAM;

#
# Dumping data for table `settings`
#

INSERT INTO settings VALUES ('path_webcacti', '', 'cacti Web Root', 'the path, under your webroot where cacti lyes, would be \'/cacti\'  in most cases if you are accessing cacti by: http://yourhost.com/cacti/.', 'textbox');
INSERT INTO settings VALUES ('path_webroot', '', 'Apache Web Root', 'Your apache web root, is \'/var/www/html\' or \'/home/httpd/html\' in most cases.', 'textbox');
INSERT INTO settings VALUES ('path_snmpwalk', '', 'snmpwalk Path', 'The path to your snmpwalk binary.', 'textbox');
INSERT INTO settings VALUES ('path_rrdtool', '', 'rrdtool Binary Path', 'Path to the rrdtool binary', 'textbox');
INSERT INTO settings VALUES ('log', '', 'Log File', 'What cacti should put in its log.', 'group:log_graph:log_create:log_update:log_snmp');
INSERT INTO settings VALUES ('log_graph', '', '', 'Graph', 'checkbox:group');
INSERT INTO settings VALUES ('log_create', 'on', '', 'Create', 'checkbox:group');
INSERT INTO settings VALUES ('log_update', '', '', 'Update', 'checkbox:group');
INSERT INTO settings VALUES ('log_snmp', 'on', '', 'SNMP', 'checkbox:group');
INSERT INTO settings VALUES ('vis_main_column_bold', 'on', '', 'Make the Main Column in Forms Bold', 'checkbox:group');
INSERT INTO settings VALUES ('vis', '', 'Visual', 'Various visual settings in cacti', 'group:vis_main_column_bold');
INSERT INTO settings VALUES ('global_auth', 'on', '', 'Use cacti\'s Builtin Authentication', 'checkbox:group');
INSERT INTO settings VALUES ('global', '', 'Global Settings', 'Settings that control how cacti works', 'group:global_auth');
INSERT INTO settings VALUES ('path_php_binary', '', 'PHP Binary Path', 'The path to your PHP binary file (may require a php recompile to get this file).', 'textbox');
INSERT INTO settings VALUES ('path_snmpget', '', 'snmpget Path', 'The path to your snmpget binary.', 'textbox');
INSERT INTO settings VALUES ('path_html_export', '', 'HTML Export Path', 'If you want cacti to write static png\'s and html files a directory when data is gathered, specify the location here. This feature is similar to MRTG, graphs do not have to be generated on the fly this way. Leave this field blank to disable this feature.', 'textbox');
INSERT INTO settings VALUES ('guest_user', 'guest', 'Guest User', 'The name of the guest user for viewing graphs; is "guest" by default.', 'textbox');
INSERT INTO settings VALUES ('path_html_export_skip', '1', 'Export Every x Times', 'If you don\'t want cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.', 'textbox');
INSERT INTO settings VALUES ('path_html_export_ctr', '', '', '', 'internal');
INSERT INTO settings VALUES ('use_polling_zones', 'off', 'Use Polling 
Zones', 'If you want to do distributed polling you can set up \'polling zones\' 
which correspond to each of your polling machines.  Polling Hosts are then associated with a particular polling zone.', 'internal');
# --------------------------------------------------------

#
# Table structure for table `settings_graphs`
#

CREATE TABLE settings_graphs (
  ID smallint(5) NOT NULL auto_increment,
  UserID smallint(5) default NULL,
  RRAID smallint(5) NOT NULL default '0',
  TreeID smallint(5) NOT NULL default '0',
  Height smallint(8) NOT NULL default '100',
  Width smallint(8) NOT NULL default '300',
  Timespan mediumint(12) NOT NULL default '60000',
  ColumnNumber tinyint(3) NOT NULL default '2',
  ViewType tinyint(1) NOT NULL default '0',
  ListViewType tinyint(1) NOT NULL default '0',
  PageRefresh smallint(5) default '300',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `settings_graphs`
#

# --------------------------------------------------------

#
# Table structure for table `settings_viewing_tree`
#

CREATE TABLE settings_viewing_tree (
  UserID smallint(5) NOT NULL default '0',
  TreeItemID smallint(5) NOT NULL default '0',
  Status tinyint(1) NOT NULL default '0'
) TYPE=MyISAM;

# --------------------------------------------------------
# --------------------------------------------------------

#
# Table structure for table `settings_graph_tree`
#

CREATE TABLE settings_graph_tree (
  UserID smallint(5) NOT NULL default '0',
  TreeItemID smallint(5) NOT NULL default '0',
  Status tinyint(1) NOT NULL default '0'
) TYPE=MyISAM;

# --------------------------------------------------------
# --------------------------------------------------------

#
# Table structure for table `settings_ds_tree`
#

CREATE TABLE settings_ds_tree (
  UserID smallint(5) NOT NULL default '0',
  TreeItemID smallint(5) NOT NULL default '0',
  Status tinyint(1) NOT NULL default '0'
) TYPE=MyISAM;

# --------------------------------------------------------

#
# Table structure for table `snmp_hosts`
#

CREATE TABLE snmp_hosts (
  ID smallint(5) NOT NULL auto_increment,
  Hostname varchar(100) NOT NULL default '',
  Community varchar(100) default 'public',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `snmp_hosts`
#

# --------------------------------------------------------

#
# Table structure for table `snmp_hosts_interfaces`
#

CREATE TABLE snmp_hosts_interfaces (
  ID smallint(5) NOT NULL auto_increment,
  Description char(255) default NULL,
  Type char(50) NOT NULL default '',
  Speed bigint(15) NOT NULL default '0',
  InterfaceNumber bigint(12) NOT NULL default '0',
  HostID smallint(5) NOT NULL default '0',
  HardwareAddress char(50) default NULL,
  AdminStatus char(10) default NULL,
  IPAddress char(50) default NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `snmp_hosts_interfaces`
#

# --------------------------------------------------------

#
# Table structure for table `src`
#

CREATE TABLE src (
  ID smallint(5) NOT NULL auto_increment,
  Name varchar(200) NOT NULL default '',
  FormatStrIn mediumblob,
  FormatStrOut mediumblob,
  Type varchar(20) default NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID)
) TYPE=MyISAM;

#
# Dumping data for table `src`
#

INSERT INTO src VALUES (6, 'Get Logged In Users', 'perl <path_cacti>/scripts/users.pl <username>', '<users>',  NULL);
INSERT INTO src VALUES (7, 'Get TCP Connections', 'perl <path_cacti>/scripts/tcp.pl <mode>', '<connections>',  NULL);
INSERT INTO src VALUES (9, 'Get Load Average', 'perl <path_cacti>/scripts/loadavg_multi.pl', '<1min> <5min> <10min>', '');
INSERT INTO src VALUES (5, 'Get System Processes', 'perl <path_cacti>/scripts/proc.pl', '<proc>',  NULL);
INSERT INTO src VALUES (1, 'Ping Host', 'perl <path_cacti>/scripts/ping.pl <num> <ip>', '<out_ms>',  NULL);
INSERT INTO src VALUES (11, 'Get SNMP Network Data', 'INTERNAL: [<ip>/<community>] Interface: [<ifnum>]', '<octets>', 'snmp_net');
INSERT INTO src VALUES (31, 'Get Free Disk Space', 'perl <path_cacti>/scripts/diskfree.pl <partition>', '<megabytes>:<percent>', '');
INSERT INTO src VALUES (19, 'Get Custom TCP Connections', 'perl <path_cacti>/scripts/tcp_custom.pl <grepstr>', '<connections>', '');
INSERT INTO src VALUES (27, 'Get TCP Connections (SNMP)', 'perl <path_cacti>/scripts/tcp_custom_snmp.pl <ip> <community> <mode>', '<connections>',  NULL);
INSERT INTO src VALUES (22, 'Get Number of Open Samba Files', '/usr/bin/smbstatus | grep -c "_"', '<openfiles>',  NULL);
INSERT INTO src VALUES (23, 'Get Web Hits', 'perl <path_cacti>/scripts/webhits.pl <log_path>', '<webhits>', '');
INSERT INTO src VALUES (24, 'Get Memory Usage', 'perl <path_cacti>/scripts/memfree.pl <grepstr>', '<mem>',  NULL);
INSERT INTO src VALUES (8, 'Get SQL Connections', '<path_php_binary> -q <path_cacti>/scripts/sql.php', '<sql_connections>',  NULL);
INSERT INTO src VALUES (13, 'Get SNMP Data', 'INTERNAL: [<ip>/<community>] OID: [<oid>]', '<octets>', 'snmp');
# --------------------------------------------------------

#
# Table structure for table `src_data`
#

CREATE TABLE src_data (
  ID smallint(5) NOT NULL auto_increment,
  FieldID smallint(5) NOT NULL default '0',
  DSID smallint(5) NOT NULL default '0',
  Value mediumblob,
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID),
  KEY DSID (DSID),
  KEY FieldID (FieldID)
) TYPE=MyISAM;

#
# Dumping data for table `src_data`
#

INSERT INTO src_data VALUES (1, 1, 35, 'loopback0.gw4.phl1.alter.net');
INSERT INTO src_data VALUES (2, 2, 35, '2');
INSERT INTO src_data VALUES (98, 60, 132, '191136');
INSERT INTO src_data VALUES (6, 61, 245, 'Buffers:');
INSERT INTO src_data VALUES (7, 61, 130, '^Cached:');
INSERT INTO src_data VALUES (8, 61, 128, 'MemFree:');
INSERT INTO src_data VALUES (9, 61, 131, 'SwapFree:');
INSERT INTO src_data VALUES (10, 61, 132, 'MemTotal:');
INSERT INTO src_data VALUES (11, 14, 36, 'tcp');
INSERT INTO src_data VALUES (12, 58, 31, '');
INSERT INTO src_data VALUES (100, 86, 377, '0.11');
INSERT INTO src_data VALUES (101, 85, 378, '0.13');
INSERT INTO src_data VALUES (107, 87, 127, '/var/log/httpd/access_log');
INSERT INTO src_data VALUES (106, 15, 36, '7');
INSERT INTO src_data VALUES (105, 60, 128, '4576');
INSERT INTO src_data VALUES (102, 12, 30, '80\n');
INSERT INTO src_data VALUES (103, 60, 245, '55712');
INSERT INTO src_data VALUES (104, 59, 127, '2651');
INSERT INTO src_data VALUES (99, 84, 376, '0.06');
INSERT INTO src_data VALUES (97, 60, 131, '264992');
INSERT INTO src_data VALUES (92, 13, 31, '2');
INSERT INTO src_data VALUES (93, 3, 35, '29.933');
INSERT INTO src_data VALUES (94, 16, 38, '11885');
INSERT INTO src_data VALUES (95, 57, 126, '1\n');
INSERT INTO src_data VALUES (96, 60, 130, '80332');
INSERT INTO src_data VALUES (288, 84, 412, '0.28');
INSERT INTO src_data VALUES (289, 85, 413, '0.13');
INSERT INTO src_data VALUES (290, 86, 414, '0.03');
# --------------------------------------------------------

#
# Table structure for table `src_fields`
#

CREATE TABLE src_fields (
  ID smallint(5) NOT NULL auto_increment,
  SrcID smallint(5) NOT NULL default '0',
  Name varchar(200) NOT NULL default '',
  DataName varchar(50) NOT NULL default '',
  InputOutput char(3) NOT NULL default '',
  UpdateRRA char(2) NOT NULL default '0',
  PRIMARY KEY  (ID),
  UNIQUE KEY ID (ID),
  KEY SrcID (SrcID)
) TYPE=MyISAM;

#
# Dumping data for table `src_fields`
#

INSERT INTO src_fields VALUES (1, 1, 'IP', 'ip', 'in', '');
INSERT INTO src_fields VALUES (2, 1, 'Times', 'num', 'in', '');
INSERT INTO src_fields VALUES (3, 1, 'Ping Time', 'out_ms', 'out', 'on');
INSERT INTO src_fields VALUES (65, 27, 'IP Address', 'ip', 'in', '');
INSERT INTO src_fields VALUES (46, 19, 'Connections', 'connections', 'out', 'on');
INSERT INTO src_fields VALUES (64, 27, 'SNMP Community', 'community', 'in', '');
INSERT INTO src_fields VALUES (13, 6, 'Logged In Users', 'users', 'out', 'on');
INSERT INTO src_fields VALUES (14, 7, 'Type of Connection', 'mode', 'in', '');
INSERT INTO src_fields VALUES (15, 7, 'TCP Connections', 'connections', 'out', 'on');
INSERT INTO src_fields VALUES (16, 8, 'Total Connections', 'sql_connections', 'out', 'on');
INSERT INTO src_fields VALUES (84, 9, '1 Minute Average', '1min', 'out', 'on');
INSERT INTO src_fields VALUES (45, 19, 'Grep String', 'grepstr', 'in', '');
INSERT INTO src_fields VALUES (21, 11, 'IP Address', 'ip', 'in', '');
INSERT INTO src_fields VALUES (22, 11, 'SNMP Community', 'community', 'in', '');
INSERT INTO src_fields VALUES (23, 11, 'Interface Description (Optional)', 'ifdesc', 'in', '');
INSERT INTO src_fields VALUES (24, 11, 'Interface Number (Optional)', 'ifnum', 'in', '');
INSERT INTO src_fields VALUES (25, 11, 'Octets', 'octets', 'out', 'on');
INSERT INTO src_fields VALUES (56, 11, 'Interface IP Address (Optional)', 'ifip', 'in', '');
INSERT INTO src_fields VALUES (44, 13, 'Octets', 'octets', 'out', 'on');
INSERT INTO src_fields VALUES (28, 11, 'In/Out Data (in or out)', 'inout', 'in', '');
INSERT INTO src_fields VALUES (43, 13, 'SNMP OID', 'oid', 'in', '');
INSERT INTO src_fields VALUES (42, 13, 'SNMP Community', 'community', 'in', '');
INSERT INTO src_fields VALUES (41, 13, 'IP Address', 'ip', 'in', '');
INSERT INTO src_fields VALUES (66, 27, 'TCP Connections', 'connections', 'out', 'on');
INSERT INTO src_fields VALUES (12, 5, 'Processes', 'proc', 'out', 'on');
INSERT INTO src_fields VALUES (53, 11, 'Interface MAC Address (Optional)', 'ifmac', 'in', '');
INSERT INTO src_fields VALUES (57, 22, 'Open Samba Files', 'openfiles', 'out', 'on');
INSERT INTO src_fields VALUES (58, 6, 'Username (Optional)', 'username', 'in', '');
INSERT INTO src_fields VALUES (59, 23, 'Web Hits', 'webhits', 'out', 'on');
INSERT INTO src_fields VALUES (60, 24, 'Memory Free (MB)', 'mem', 'out', 'on');
INSERT INTO src_fields VALUES (61, 24, 'Grep String', 'grepstr', 'in', '');
INSERT INTO src_fields VALUES (67, 27, 'Grep String', 'mode', 'in', '');
INSERT INTO src_fields VALUES (68, 28, 'IP Address', 'ip', 'in', '');
INSERT INTO src_fields VALUES (69, 28, 'Octets', 'octets', 'out', 'on');
INSERT INTO src_fields VALUES (70, 28, 'SNMP Community', 'community', 'in', '');
INSERT INTO src_fields VALUES (71, 28, 'SNMP OID', 'oid', 'in', '');
INSERT INTO src_fields VALUES (72, 29, 'In/Out Data (in or out)', 'inout', 'in', '');
INSERT INTO src_fields VALUES (73, 29, 'Interface Description (Optional)', 'ifdesc', 'in', '');
INSERT INTO src_fields VALUES (74, 29, 'Interface IP Address (Optional)', 'ifip', 'in', '');
INSERT INTO src_fields VALUES (75, 29, 'Interface MAC Address (Optional)', 'ifmac', 'in', '');
INSERT INTO src_fields VALUES (76, 29, 'Interface Number (Optional)', 'ifnum', 'in', '');
INSERT INTO src_fields VALUES (78, 29, 'Octets', 'octets', 'out', 'on');
INSERT INTO src_fields VALUES (77, 29, 'SNMP IP Address', 'ip', 'in', '');
INSERT INTO src_fields VALUES (79, 29, 'SNMP Community', 'community', 'in', '');
INSERT INTO src_fields VALUES (86, 9, '10 Minute Average', '10min', 'out', 'on');
INSERT INTO src_fields VALUES (85, 9, '5 Minute Average', '5min', 'out', 'on');
INSERT INTO src_fields VALUES (87, 23, '(Optional) Log Path', 'log_path', 'in', '');
INSERT INTO src_fields VALUES (88, 31, 'Disk Partition', 'partition', 'in', '');
INSERT INTO src_fields VALUES (89, 31, 'Megabytes Free', 'megabytes', 'out', 'on');
INSERT INTO src_fields VALUES (90, 31, 'Percent Free', 'percent', 'out', '');
# --------------------------------------------------------

#
# Table structure for table `version`
#

CREATE TABLE version (
  cacti char(15) default NULL
) TYPE=MyISAM;

#
# Dumping data for table `version`
#

INSERT INTO version VALUES ('new_install');
