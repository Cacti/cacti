--
-- Table structure for table `cdef`
--

CREATE TABLE cdef (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(255) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `cdef`
--

INSERT INTO cdef VALUES (3,'3d352eed9fa8f7b2791205b3273708c7','Make Stack Negative');
INSERT INTO cdef VALUES (4,'e961cc8ec04fda6ed4981cf5ad501aa5','Make Per 5 Minutes');
INSERT INTO cdef VALUES (12,'f1ac79f05f255c02f914c920f1038c54','Total All Data Sources');
INSERT INTO cdef VALUES (2,'73f95f8b77b5508157d64047342c421e','Turn Bytes into Bits');
INSERT INTO cdef VALUES (14,'634a23af5e78af0964e8d33b1a4ed26b','Multiply by 1024');
INSERT INTO cdef VALUES (15,'068984b5ccdfd2048869efae5166f722','Total All Data Sources, Multiply by 1024');

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
  PRIMARY KEY  (id),
  KEY cdef_id (cdef_id)
) ENGINE=MyISAM;

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

--
-- Table structure for table `colors`
--

CREATE TABLE colors (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hex varchar(6) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `colors`
--

INSERT INTO colors VALUES (1,'000000');
INSERT INTO colors VALUES (2,'FFFFFF');
INSERT INTO colors VALUES (4,'FAFD9E');
INSERT INTO colors VALUES (5,'C0C0C0');
INSERT INTO colors VALUES (6,'74C366');
INSERT INTO colors VALUES (7,'6DC8FE');
INSERT INTO colors VALUES (8,'EA8F00');
INSERT INTO colors VALUES (9,'FF0000');
INSERT INTO colors VALUES (10,'4444FF');
INSERT INTO colors VALUES (11,'FF00FF');
INSERT INTO colors VALUES (12,'00FF00');
INSERT INTO colors VALUES (13,'8D85F3');
INSERT INTO colors VALUES (14,'AD3B6E');
INSERT INTO colors VALUES (15,'EACC00');
INSERT INTO colors VALUES (16,'12B3B5');
INSERT INTO colors VALUES (17,'157419');
INSERT INTO colors VALUES (18,'C4FD3D');
INSERT INTO colors VALUES (19,'817C4E');
INSERT INTO colors VALUES (20,'002A97');
INSERT INTO colors VALUES (21,'0000FF');
INSERT INTO colors VALUES (22,'00CF00');
INSERT INTO colors VALUES (24,'F9FD5F');
INSERT INTO colors VALUES (25,'FFF200');
INSERT INTO colors VALUES (26,'CCBB00');
INSERT INTO colors VALUES (27,'837C04');
INSERT INTO colors VALUES (28,'EAAF00');
INSERT INTO colors VALUES (29,'FFD660');
INSERT INTO colors VALUES (30,'FFC73B');
INSERT INTO colors VALUES (31,'FFAB00');
INSERT INTO colors VALUES (33,'FF7D00');
INSERT INTO colors VALUES (34,'ED7600');
INSERT INTO colors VALUES (35,'FF5700');
INSERT INTO colors VALUES (36,'EE5019');
INSERT INTO colors VALUES (37,'B1441E');
INSERT INTO colors VALUES (38,'FFC3C0');
INSERT INTO colors VALUES (39,'FF897C');
INSERT INTO colors VALUES (40,'FF6044');
INSERT INTO colors VALUES (41,'FF4105');
INSERT INTO colors VALUES (42,'DA4725');
INSERT INTO colors VALUES (43,'942D0C');
INSERT INTO colors VALUES (44,'FF3932');
INSERT INTO colors VALUES (45,'862F2F');
INSERT INTO colors VALUES (46,'FF5576');
INSERT INTO colors VALUES (47,'562B29');
INSERT INTO colors VALUES (48,'F51D30');
INSERT INTO colors VALUES (49,'DE0056');
INSERT INTO colors VALUES (50,'ED5394');
INSERT INTO colors VALUES (51,'B90054');
INSERT INTO colors VALUES (52,'8F005C');
INSERT INTO colors VALUES (53,'F24AC8');
INSERT INTO colors VALUES (54,'E8CDEF');
INSERT INTO colors VALUES (55,'D8ACE0');
INSERT INTO colors VALUES (56,'A150AA');
INSERT INTO colors VALUES (57,'750F7D');
INSERT INTO colors VALUES (58,'8D00BA');
INSERT INTO colors VALUES (59,'623465');
INSERT INTO colors VALUES (60,'55009D');
INSERT INTO colors VALUES (61,'3D168B');
INSERT INTO colors VALUES (62,'311F4E');
INSERT INTO colors VALUES (63,'D2D8F9');
INSERT INTO colors VALUES (64,'9FA4EE');
INSERT INTO colors VALUES (65,'6557D0');
INSERT INTO colors VALUES (66,'4123A1');
INSERT INTO colors VALUES (67,'4668E4');
INSERT INTO colors VALUES (68,'0D006A');
INSERT INTO colors VALUES (69,'00004D');
INSERT INTO colors VALUES (70,'001D61');
INSERT INTO colors VALUES (71,'00234B');
INSERT INTO colors VALUES (72,'002A8F');
INSERT INTO colors VALUES (73,'2175D9');
INSERT INTO colors VALUES (74,'7CB3F1');
INSERT INTO colors VALUES (75,'005199');
INSERT INTO colors VALUES (76,'004359');
INSERT INTO colors VALUES (77,'00A0C1');
INSERT INTO colors VALUES (78,'007283');
INSERT INTO colors VALUES (79,'00BED9');
INSERT INTO colors VALUES (80,'AFECED');
INSERT INTO colors VALUES (81,'55D6D3');
INSERT INTO colors VALUES (82,'00BBB4');
INSERT INTO colors VALUES (83,'009485');
INSERT INTO colors VALUES (84,'005D57');
INSERT INTO colors VALUES (85,'008A77');
INSERT INTO colors VALUES (86,'008A6D');
INSERT INTO colors VALUES (87,'00B99B');
INSERT INTO colors VALUES (88,'009F67');
INSERT INTO colors VALUES (89,'00694A');
INSERT INTO colors VALUES (90,'00A348');
INSERT INTO colors VALUES (91,'00BF47');
INSERT INTO colors VALUES (92,'96E78A');
INSERT INTO colors VALUES (93,'00BD27');
INSERT INTO colors VALUES (94,'35962B');
INSERT INTO colors VALUES (95,'7EE600');
INSERT INTO colors VALUES (96,'6EA100');
INSERT INTO colors VALUES (97,'CAF100');
INSERT INTO colors VALUES (98,'F5F800');
INSERT INTO colors VALUES (99,'CDCFC4');
INSERT INTO colors VALUES (100,'BCBEB3');
INSERT INTO colors VALUES (101,'AAABA1');
INSERT INTO colors VALUES (102,'8F9286');
INSERT INTO colors VALUES (103,'797C6E');
INSERT INTO colors VALUES (104,'2E3127');

--
-- Table structure for table `data_input`
--

CREATE TABLE data_input (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(200) NOT NULL default '',
  input_string varchar(255) default NULL,
  type_id tinyint(2) NOT NULL default '0',
  PRIMARY KEY (id),
  KEY name (name)
) ENGINE=MyISAM;

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
) ENGINE=MyISAM;

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
  PRIMARY KEY  (id),
  KEY data_input_id (data_input_id),
  KEY type_code (type_code)
) ENGINE=MyISAM;

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
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `data_local`
--

INSERT INTO data_local VALUES (3,13,1,0,'');
INSERT INTO data_local VALUES (4,15,1,0,'');
INSERT INTO data_local VALUES (5,11,1,0,'');
INSERT INTO data_local VALUES (6,17,1,0,'');
INSERT INTO data_local VALUES (7,16,1,0,'');

--
-- Table structure for table `data_template`
--

CREATE TABLE data_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(150) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `data_template`
--

INSERT INTO data_template VALUES (3,'c8a8f50f5f4a465368222594c5709ede','ucd/net - Hard Drive Space');
INSERT INTO data_template VALUES (4,'cdfed2d401723d2f41fc239d4ce249c7','ucd/net - CPU Usage - System');
INSERT INTO data_template VALUES (5,'a27e816377d2ac6434a87c494559c726','ucd/net - CPU Usage - User');
INSERT INTO data_template VALUES (6,'c06c3d20eccb9598939dc597701ff574','ucd/net - CPU Usage - Nice');
INSERT INTO data_template VALUES (7,'a14f2d6f233b05e64263ff03a5b0b386','Karlnet - Noise Level');
INSERT INTO data_template VALUES (8,'def1a9019d888ed2ad2e106aa9595ede','Karlnet - Signal Level');
INSERT INTO data_template VALUES (9,'513a99ae3c9c4413609c1534ffc36eab','Karlnet - Wireless Transmits');
INSERT INTO data_template VALUES (10,'77404ae93c9cc410f1c2c717e7117378','Karlnet - Wireless Re-Transmits');
INSERT INTO data_template VALUES (11,'9e72511e127de200733eb502eb818e1d','Unix - Load Average');
INSERT INTO data_template VALUES (13,'dc33aa9a8e71fb7c61ec0e7a6da074aa','Linux - Memory - Free');
INSERT INTO data_template VALUES (15,'41f55087d067142d702dd3c73c98f020','Linux - Memory - Free Swap');
INSERT INTO data_template VALUES (16,'9b8c92d3c32703900ff7dd653bfc9cd8','Unix - Processes');
INSERT INTO data_template VALUES (17,'c221c2164c585b6da378013a7a6a2c13','Unix - Logged in Users');
INSERT INTO data_template VALUES (18,'a30a81cb1de65b52b7da542c8df3f188','Unix - Ping Host');
INSERT INTO data_template VALUES (19,'0de466a1b81dfe581d44ac014b86553a','Netware - Total Users');
INSERT INTO data_template VALUES (20,'bbe2da0708103029fbf949817d3a4537','Netware - Total Logins');
INSERT INTO data_template VALUES (22,'e4ac5d5fe73e3c773671c6d0498a8d9d','Netware - File System Reads');
INSERT INTO data_template VALUES (23,'f29f8c998425eedd249be1e7caf90ceb','Netware - File System Writes');
INSERT INTO data_template VALUES (24,'7a6216a113e19881e35565312db8a371','Netware - Cache Checks');
INSERT INTO data_template VALUES (25,'1dbd1251c8e94b334c0e6aeae5ca4b8d','Netware - Cache Hits');
INSERT INTO data_template VALUES (26,'1a4c5264eb27b5e57acd3160af770a61','Netware - Open Files');
INSERT INTO data_template VALUES (27,'e9def3a0e409f517cb804dfeba4ccd90','Cisco Router - 5 Minute CPU');
INSERT INTO data_template VALUES (30,'9b82d44eb563027659683765f92c9757','ucd/net - Load Average - 1 Minute');
INSERT INTO data_template VALUES (31,'87847714d19f405ff3c74f3341b3f940','ucd/net - Load Average - 5 Minute');
INSERT INTO data_template VALUES (32,'308ac157f24e2763f8cd828a80b3e5ff','ucd/net - Load Average - 15 Minute');
INSERT INTO data_template VALUES (33,'797a3e92b0039841b52e441a2823a6fb','ucd/net - Memory - Buffers');
INSERT INTO data_template VALUES (34,'fa15932d3cab0da2ab94c69b1a9f5ca7','ucd/net - Memory - Free');
INSERT INTO data_template VALUES (35,'6ce4ab04378f9f3b03ee0623abb6479f','Netware - Volumes');
INSERT INTO data_template VALUES (36,'03060555fab086b8412bbf9951179cd9','Netware - Directory Entries');
INSERT INTO data_template VALUES (37,'e4ac6919d4f6f21ec5b281a1d6ac4d4e','Unix - Hard Drive Space');
INSERT INTO data_template VALUES (38,'36335cd98633963a575b70639cd2fdad','Interface - Errors/Discards');
INSERT INTO data_template VALUES (39,'2f654f7d69ac71a5d56b1db8543ccad3','Interface - Unicast Packets');
INSERT INTO data_template VALUES (40,'c84e511401a747409053c90ba910d0fe','Interface - Non-Unicast Packets');
INSERT INTO data_template VALUES (41,'6632e1e0b58a565c135d7ff90440c335','Interface - Traffic');
INSERT INTO data_template VALUES (42,'1d17325f416b262921a0b55fe5f7e31d','Netware - CPU Utilization');
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
  data_source_path varchar(255) default NULL,
  t_active char(2) default NULL,
  active char(2) default NULL,
  t_rrd_step char(2) default NULL,
  rrd_step mediumint(8) unsigned NOT NULL default '0',
  t_rra_id char(2) default NULL,
  PRIMARY KEY  (id),
  KEY local_data_id (local_data_id),
  KEY data_template_id (data_template_id),
  KEY data_input_id (data_input_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `data_template_data`
--

INSERT INTO data_template_data VALUES (3,0,0,3,2,'on','|host_description| - Hard Drive Space','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (4,0,0,4,1,'','|host_description| - CPU Usage - System','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (5,0,0,5,1,'','|host_description| - CPU Usage - User','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (6,0,0,6,1,'','|host_description| - CPU Usage - Nice','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (7,0,0,7,2,'on','|host_description| - Noise Level','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (8,0,0,8,2,'on','|host_description| - Signal Level','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (9,0,0,9,2,'on','|host_description| - Wireless Transmits','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (10,0,0,10,2,'on','|host_description| - Wireless Re-Transmits','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (11,0,0,11,4,'','|host_description| - Load Average','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (13,0,0,13,6,'','|host_description| - Memory - Free','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (15,0,0,15,6,'','|host_description| - Memory - Free Swap','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (16,0,0,16,7,'','|host_description| - Processes','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (17,0,0,17,5,'','|host_description| - Logged in Users','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (18,0,0,18,10,'','|host_description| - Ping Host','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (19,0,0,19,1,'','|host_description| - Total Users','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (20,0,0,20,1,'','|host_description| - Total Logins','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (22,0,0,22,1,'','|host_description| - File System Reads','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (23,0,0,23,1,'','|host_description| - File System Writes','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (24,0,0,24,1,'','|host_description| - Cache Checks','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (25,0,0,25,1,'','|host_description| - Cache Hits','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (26,0,0,26,1,'','|host_description| - Open Files','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (27,0,0,27,1,'','|host_description| - 5 Minute CPU','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (30,0,0,30,1,'','|host_description| - Load Average - 1 Minute','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (31,0,0,31,1,'','|host_description| - Load Average - 5 Minute','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (32,0,0,32,1,'','|host_description| - Load Average - 15 Minute','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (33,0,0,33,1,'','|host_description| - Memory - Buffers','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (34,0,0,34,1,'','|host_description| - Memory - Free','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (35,0,0,35,2,'on','|host_description| - Volumes','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (36,0,0,36,2,'on','|host_description| - Directory Entries','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (37,0,0,37,11,'on','|host_description| - Hard Drive Space','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (38,0,0,38,2,'on','|host_description| - Errors/Discards','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (39,0,0,39,2,'on','|host_description| - Unicast Packets','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (40,0,0,40,2,'on','|host_description| - Non-Unicast Packets','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (41,0,0,41,2,'on','|host_description| - Traffic','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (55,0,0,42,2,'','|host_description| - CPU Utilization','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (56,0,0,43,12,'','|host_description| - Hard Drive Space','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (57,0,0,44,12,'','|host_description| - CPU Utilization','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (58,0,0,45,1,'','|host_description| - Processes','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (59,0,0,46,1,'','|host_description| - Logged in Users','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (62,13,3,13,6,NULL,'|host_description| - Memory - Free','Localhost - Memory - Free','<path_rra>/localhost_mem_buffers_3.rrd',NULL,'on',NULL,300,NULL);
INSERT INTO data_template_data VALUES (63,15,4,15,6,NULL,'|host_description| - Memory - Free Swap','Localhost - Memory - Free Swap','<path_rra>/localhost_mem_swap_4.rrd',NULL,'on',NULL,300,NULL);
INSERT INTO data_template_data VALUES (64,11,5,11,4,NULL,'|host_description| - Load Average','Localhost - Load Average','<path_rra>/localhost_load_1min_5.rrd',NULL,'on',NULL,300,NULL);
INSERT INTO data_template_data VALUES (65,17,6,17,5,NULL,'|host_description| - Logged in Users','Localhost - Logged in Users','<path_rra>/localhost_users_6.rrd',NULL,'on',NULL,300,NULL);
INSERT INTO data_template_data VALUES (66,16,7,16,7,NULL,'|host_description| - Processes','Localhost - Processes','<path_rra>/localhost_proc_7.rrd',NULL,'on',NULL,300,NULL);
INSERT INTO data_template_data VALUES (68,0,0,47,1,'','|host_description| - Memory - Cache','',NULL,'','on','',300,'');
INSERT INTO data_template_data VALUES (69,0,0,48,1,'on','|host_description| -','',NULL,'','on','',300,'');

--
-- Table structure for table `data_template_data_rra`
--

CREATE TABLE data_template_data_rra (
  data_template_data_id mediumint(8) unsigned NOT NULL default '0',
  rra_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (data_template_data_id,rra_id),
  KEY data_template_data_id (data_template_data_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `data_template_data_rra`
--

INSERT INTO data_template_data_rra VALUES (3,1);
INSERT INTO data_template_data_rra VALUES (3,2);
INSERT INTO data_template_data_rra VALUES (3,3);
INSERT INTO data_template_data_rra VALUES (3,4);
INSERT INTO data_template_data_rra VALUES (4,1);
INSERT INTO data_template_data_rra VALUES (4,2);
INSERT INTO data_template_data_rra VALUES (4,3);
INSERT INTO data_template_data_rra VALUES (4,4);
INSERT INTO data_template_data_rra VALUES (5,1);
INSERT INTO data_template_data_rra VALUES (5,2);
INSERT INTO data_template_data_rra VALUES (5,3);
INSERT INTO data_template_data_rra VALUES (5,4);
INSERT INTO data_template_data_rra VALUES (6,1);
INSERT INTO data_template_data_rra VALUES (6,2);
INSERT INTO data_template_data_rra VALUES (6,3);
INSERT INTO data_template_data_rra VALUES (6,4);
INSERT INTO data_template_data_rra VALUES (7,1);
INSERT INTO data_template_data_rra VALUES (7,2);
INSERT INTO data_template_data_rra VALUES (7,3);
INSERT INTO data_template_data_rra VALUES (7,4);
INSERT INTO data_template_data_rra VALUES (8,1);
INSERT INTO data_template_data_rra VALUES (8,2);
INSERT INTO data_template_data_rra VALUES (8,3);
INSERT INTO data_template_data_rra VALUES (8,4);
INSERT INTO data_template_data_rra VALUES (9,1);
INSERT INTO data_template_data_rra VALUES (9,2);
INSERT INTO data_template_data_rra VALUES (9,3);
INSERT INTO data_template_data_rra VALUES (9,4);
INSERT INTO data_template_data_rra VALUES (10,1);
INSERT INTO data_template_data_rra VALUES (10,2);
INSERT INTO data_template_data_rra VALUES (10,3);
INSERT INTO data_template_data_rra VALUES (10,4);
INSERT INTO data_template_data_rra VALUES (11,1);
INSERT INTO data_template_data_rra VALUES (11,2);
INSERT INTO data_template_data_rra VALUES (11,3);
INSERT INTO data_template_data_rra VALUES (11,4);
INSERT INTO data_template_data_rra VALUES (13,1);
INSERT INTO data_template_data_rra VALUES (13,2);
INSERT INTO data_template_data_rra VALUES (13,3);
INSERT INTO data_template_data_rra VALUES (13,4);
INSERT INTO data_template_data_rra VALUES (15,1);
INSERT INTO data_template_data_rra VALUES (15,2);
INSERT INTO data_template_data_rra VALUES (15,3);
INSERT INTO data_template_data_rra VALUES (15,4);
INSERT INTO data_template_data_rra VALUES (16,1);
INSERT INTO data_template_data_rra VALUES (16,2);
INSERT INTO data_template_data_rra VALUES (16,3);
INSERT INTO data_template_data_rra VALUES (16,4);
INSERT INTO data_template_data_rra VALUES (17,1);
INSERT INTO data_template_data_rra VALUES (17,2);
INSERT INTO data_template_data_rra VALUES (17,3);
INSERT INTO data_template_data_rra VALUES (17,4);
INSERT INTO data_template_data_rra VALUES (18,1);
INSERT INTO data_template_data_rra VALUES (18,2);
INSERT INTO data_template_data_rra VALUES (18,3);
INSERT INTO data_template_data_rra VALUES (18,4);
INSERT INTO data_template_data_rra VALUES (19,1);
INSERT INTO data_template_data_rra VALUES (19,2);
INSERT INTO data_template_data_rra VALUES (19,3);
INSERT INTO data_template_data_rra VALUES (19,4);
INSERT INTO data_template_data_rra VALUES (20,1);
INSERT INTO data_template_data_rra VALUES (20,2);
INSERT INTO data_template_data_rra VALUES (20,3);
INSERT INTO data_template_data_rra VALUES (20,4);
INSERT INTO data_template_data_rra VALUES (22,1);
INSERT INTO data_template_data_rra VALUES (22,2);
INSERT INTO data_template_data_rra VALUES (22,3);
INSERT INTO data_template_data_rra VALUES (22,4);
INSERT INTO data_template_data_rra VALUES (23,1);
INSERT INTO data_template_data_rra VALUES (23,2);
INSERT INTO data_template_data_rra VALUES (23,3);
INSERT INTO data_template_data_rra VALUES (23,4);
INSERT INTO data_template_data_rra VALUES (24,1);
INSERT INTO data_template_data_rra VALUES (24,2);
INSERT INTO data_template_data_rra VALUES (24,3);
INSERT INTO data_template_data_rra VALUES (24,4);
INSERT INTO data_template_data_rra VALUES (25,1);
INSERT INTO data_template_data_rra VALUES (25,2);
INSERT INTO data_template_data_rra VALUES (25,3);
INSERT INTO data_template_data_rra VALUES (25,4);
INSERT INTO data_template_data_rra VALUES (26,1);
INSERT INTO data_template_data_rra VALUES (26,2);
INSERT INTO data_template_data_rra VALUES (26,3);
INSERT INTO data_template_data_rra VALUES (26,4);
INSERT INTO data_template_data_rra VALUES (27,1);
INSERT INTO data_template_data_rra VALUES (27,2);
INSERT INTO data_template_data_rra VALUES (27,3);
INSERT INTO data_template_data_rra VALUES (27,4);
INSERT INTO data_template_data_rra VALUES (30,1);
INSERT INTO data_template_data_rra VALUES (30,2);
INSERT INTO data_template_data_rra VALUES (30,3);
INSERT INTO data_template_data_rra VALUES (30,4);
INSERT INTO data_template_data_rra VALUES (31,1);
INSERT INTO data_template_data_rra VALUES (31,2);
INSERT INTO data_template_data_rra VALUES (31,3);
INSERT INTO data_template_data_rra VALUES (31,4);
INSERT INTO data_template_data_rra VALUES (32,1);
INSERT INTO data_template_data_rra VALUES (32,2);
INSERT INTO data_template_data_rra VALUES (32,3);
INSERT INTO data_template_data_rra VALUES (32,4);
INSERT INTO data_template_data_rra VALUES (33,1);
INSERT INTO data_template_data_rra VALUES (33,2);
INSERT INTO data_template_data_rra VALUES (33,3);
INSERT INTO data_template_data_rra VALUES (33,4);
INSERT INTO data_template_data_rra VALUES (34,1);
INSERT INTO data_template_data_rra VALUES (34,2);
INSERT INTO data_template_data_rra VALUES (34,3);
INSERT INTO data_template_data_rra VALUES (34,4);
INSERT INTO data_template_data_rra VALUES (35,1);
INSERT INTO data_template_data_rra VALUES (35,2);
INSERT INTO data_template_data_rra VALUES (35,3);
INSERT INTO data_template_data_rra VALUES (35,4);
INSERT INTO data_template_data_rra VALUES (36,1);
INSERT INTO data_template_data_rra VALUES (36,2);
INSERT INTO data_template_data_rra VALUES (36,3);
INSERT INTO data_template_data_rra VALUES (36,4);
INSERT INTO data_template_data_rra VALUES (37,1);
INSERT INTO data_template_data_rra VALUES (37,2);
INSERT INTO data_template_data_rra VALUES (37,3);
INSERT INTO data_template_data_rra VALUES (37,4);
INSERT INTO data_template_data_rra VALUES (38,1);
INSERT INTO data_template_data_rra VALUES (38,2);
INSERT INTO data_template_data_rra VALUES (38,3);
INSERT INTO data_template_data_rra VALUES (38,4);
INSERT INTO data_template_data_rra VALUES (39,1);
INSERT INTO data_template_data_rra VALUES (39,2);
INSERT INTO data_template_data_rra VALUES (39,3);
INSERT INTO data_template_data_rra VALUES (39,4);
INSERT INTO data_template_data_rra VALUES (40,1);
INSERT INTO data_template_data_rra VALUES (40,2);
INSERT INTO data_template_data_rra VALUES (40,3);
INSERT INTO data_template_data_rra VALUES (40,4);
INSERT INTO data_template_data_rra VALUES (41,1);
INSERT INTO data_template_data_rra VALUES (41,2);
INSERT INTO data_template_data_rra VALUES (41,3);
INSERT INTO data_template_data_rra VALUES (41,4);
INSERT INTO data_template_data_rra VALUES (55,1);
INSERT INTO data_template_data_rra VALUES (55,2);
INSERT INTO data_template_data_rra VALUES (55,3);
INSERT INTO data_template_data_rra VALUES (55,4);
INSERT INTO data_template_data_rra VALUES (56,1);
INSERT INTO data_template_data_rra VALUES (56,2);
INSERT INTO data_template_data_rra VALUES (56,3);
INSERT INTO data_template_data_rra VALUES (56,4);
INSERT INTO data_template_data_rra VALUES (57,1);
INSERT INTO data_template_data_rra VALUES (57,2);
INSERT INTO data_template_data_rra VALUES (57,3);
INSERT INTO data_template_data_rra VALUES (57,4);
INSERT INTO data_template_data_rra VALUES (58,1);
INSERT INTO data_template_data_rra VALUES (58,2);
INSERT INTO data_template_data_rra VALUES (58,3);
INSERT INTO data_template_data_rra VALUES (58,4);
INSERT INTO data_template_data_rra VALUES (59,1);
INSERT INTO data_template_data_rra VALUES (59,2);
INSERT INTO data_template_data_rra VALUES (59,3);
INSERT INTO data_template_data_rra VALUES (59,4);
INSERT INTO data_template_data_rra VALUES (62,1);
INSERT INTO data_template_data_rra VALUES (62,2);
INSERT INTO data_template_data_rra VALUES (62,3);
INSERT INTO data_template_data_rra VALUES (62,4);
INSERT INTO data_template_data_rra VALUES (63,1);
INSERT INTO data_template_data_rra VALUES (63,2);
INSERT INTO data_template_data_rra VALUES (63,3);
INSERT INTO data_template_data_rra VALUES (63,4);
INSERT INTO data_template_data_rra VALUES (64,1);
INSERT INTO data_template_data_rra VALUES (64,2);
INSERT INTO data_template_data_rra VALUES (64,3);
INSERT INTO data_template_data_rra VALUES (64,4);
INSERT INTO data_template_data_rra VALUES (65,1);
INSERT INTO data_template_data_rra VALUES (65,2);
INSERT INTO data_template_data_rra VALUES (65,3);
INSERT INTO data_template_data_rra VALUES (65,4);
INSERT INTO data_template_data_rra VALUES (66,1);
INSERT INTO data_template_data_rra VALUES (66,2);
INSERT INTO data_template_data_rra VALUES (66,3);
INSERT INTO data_template_data_rra VALUES (66,4);
INSERT INTO data_template_data_rra VALUES (68,1);
INSERT INTO data_template_data_rra VALUES (68,2);
INSERT INTO data_template_data_rra VALUES (68,3);
INSERT INTO data_template_data_rra VALUES (68,4);
INSERT INTO data_template_data_rra VALUES (69,1);
INSERT INTO data_template_data_rra VALUES (69,2);
INSERT INTO data_template_data_rra VALUES (69,3);
INSERT INTO data_template_data_rra VALUES (69,4);

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
  PRIMARY KEY  (id),
  UNIQUE KEY `duplicate_dsname_contraint` (`local_data_id`,`data_source_name`,`data_template_id`),
  KEY local_data_id (local_data_id),
  KEY data_template_id (data_template_id),
  KEY local_data_template_rrd_id (local_data_template_rrd_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `data_template_rrd`
--

INSERT INTO data_template_rrd VALUES (3,'2d53f9c76767a2ae8909f4152fd473a4',0,0,3,'','0','','0','',600,'',1,'','hdd_free','',0);
INSERT INTO data_template_rrd VALUES (4,'93d91aa7a3cc5473e7b195d5d6e6e675',0,0,3,'','0','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (5,'7bee7987bbf30a3bc429d2a67c6b2595',0,0,4,'','100','','0','',600,'',2,'','cpu_system','',0);
INSERT INTO data_template_rrd VALUES (6,'ddccd7fbdece499da0235b4098b87f9e',0,0,5,'','100','','0','',600,'',2,'','cpu_user','',0);
INSERT INTO data_template_rrd VALUES (7,'122ab2097f8c6403b7b90cde7b9e2bc2',0,0,6,'','100','','0','',600,'',2,'','cpu_nice','',0);
INSERT INTO data_template_rrd VALUES (8,'34f50c820092ea0fecba25b4b94a7946',0,0,7,'','100','','0','',600,'',1,'','wrls_noise','',0);
INSERT INTO data_template_rrd VALUES (9,'830b811d1834e5ba0e2af93bd92db057',0,0,8,'','100','','0','',600,'',1,'','wrls_signal','',0);
INSERT INTO data_template_rrd VALUES (10,'2f1b016a2465eef3f7369f6313cd4a94',0,0,9,'','1000000','','0','',600,'',2,'','wrls_transmits','',0);
INSERT INTO data_template_rrd VALUES (11,'28ffcecaf8b50e49f676f2d4a822685d',0,0,10,'','1000000','','0','',600,'',2,'','wrls_retransmits','',0);
INSERT INTO data_template_rrd VALUES (12,'8175ca431c8fe50efff5a1d3ae51b55d',0,0,11,'','500','','0','',600,'',1,'','load_1min','',17);
INSERT INTO data_template_rrd VALUES (13,'a2eeb8acd6ea01cd0e3ac852965c0eb6',0,0,11,'','500','','0','',600,'',1,'','load_5min','',18);
INSERT INTO data_template_rrd VALUES (14,'9f951b7fb3b19285a411aebb5254a831',0,0,11,'','500','','0','',600,'',1,'','load_15min','',19);
INSERT INTO data_template_rrd VALUES (16,'a4df3de5238d3beabee1a2fe140d3d80',0,0,13,'','0','','0','',600,'',1,'','mem_buffers','',23);
INSERT INTO data_template_rrd VALUES (18,'7fea6acc9b1a19484b4cb4cef2b6c5da',0,0,15,'','0','','0','',600,'',1,'','mem_swap','',23);
INSERT INTO data_template_rrd VALUES (19,'f1ba3a5b17b95825021241398bb0f277',0,0,16,'','1000','','0','',600,'',1,'','proc','',24);
INSERT INTO data_template_rrd VALUES (20,'46a5afe8e6c0419172c76421dc9e304a',0,0,17,'','500','','0','',600,'',1,'','users','',21);
INSERT INTO data_template_rrd VALUES (21,'962fd1994fe9cae87fb36436bdb8a742',0,0,18,'','5000','','0','',600,'',1,'','ping','',30);
INSERT INTO data_template_rrd VALUES (22,'7a8dd1111a8624369906bf2cd6ea9ca9',0,0,19,'','100000','','0','',600,'',1,'','total_users','',0);
INSERT INTO data_template_rrd VALUES (23,'ddb6e74d34d2f1969ce85f809dbac23d',0,0,20,'','100000','','0','',600,'',1,'','total_logins','',0);
INSERT INTO data_template_rrd VALUES (25,'289311d10336941d33d9a1c48a7b11ee',0,0,22,'','10000000','','0','',600,'',2,'','fs_reads','',0);
INSERT INTO data_template_rrd VALUES (26,'02216f036cca04655ee2f67fedb6f4f0',0,0,23,'','10000000','','0','',600,'',2,'','fs_writes','',0);
INSERT INTO data_template_rrd VALUES (27,'9e402c0f29131ef7139c20bd500b4e8a',0,0,24,'','10000000','','0','',600,'',2,'','cache_checks','',0);
INSERT INTO data_template_rrd VALUES (28,'46717dfe3c8c030d8b5ec0874f9dbdca',0,0,25,'','1000000','','0','',600,'',2,'','cache_hits','',0);
INSERT INTO data_template_rrd VALUES (29,'7a88a60729af62561812c43bde61dfc1',0,0,26,'','100000','','0','',600,'',1,'','open_files','',0);
INSERT INTO data_template_rrd VALUES (30,'3c0fd1a188b64a662dfbfa985648397b',0,0,27,'','100','','0','',600,'',1,'','5min_cpu','',0);
INSERT INTO data_template_rrd VALUES (33,'ed44c2438ef7e46e2aeed2b6c580815c',0,0,30,'','500','','0','',600,'',1,'','load_1min','',0);
INSERT INTO data_template_rrd VALUES (34,'9b3a00c9e3530d9e58895ac38271361e',0,0,31,'','500','','0','',600,'',1,'','load_5min','',0);
INSERT INTO data_template_rrd VALUES (35,'6746c2ed836ecc68a71bbddf06b0e5d9',0,0,32,'','500','','0','',600,'',1,'','load_15min','',0);
INSERT INTO data_template_rrd VALUES (36,'9835d9e1a8c78aa2475d752e8fa74812',0,0,33,'','10000000','','0','',600,'',1,'','mem_buffers','',0);
INSERT INTO data_template_rrd VALUES (37,'9c78dc1981bcea841b8c827c6dc0d26c',0,0,34,'','10000000','','0','',600,'',1,'','mem_free','',0);
INSERT INTO data_template_rrd VALUES (38,'62a56dc76fe4cd8566a31b5df0274cc3',0,0,35,'','0','','0','',600,'',1,'','vol_total','',0);
INSERT INTO data_template_rrd VALUES (39,'2e366ab49d0e0238fb4e3141ea5a88c3',0,0,35,'','0','','0','',600,'',1,'','vol_free','',0);
INSERT INTO data_template_rrd VALUES (40,'dceedc84718dd93a5affe4b190bca810',0,0,35,'','0','','0','',600,'',1,'','vol_freeable','',0);
INSERT INTO data_template_rrd VALUES (42,'93330503f1cf67db00d8fe636035e545',0,0,36,'','100000000000','','0','',600,'',1,'','dir_total','',0);
INSERT INTO data_template_rrd VALUES (43,'6b0fe4aa6aaf22ef9cfbbe96d87fa0d7',0,0,36,'','100000000000','','0','',600,'',1,'','dir_used','',0);
INSERT INTO data_template_rrd VALUES (44,'4c82df790325d789d304e6ee5cd4ab7d',0,0,37,'','0','','0','',600,'',1,'','hdd_free','',0);
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
INSERT INTO data_template_rrd VALUES (56,'07175541991def89bd02d28a215f6fcc',0,0,37,'','0','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (76,'07492e5cace6d74e7db3cb1fc005a3f3',0,0,42,'','100','','0','',600,'',1,'','cpu','',0);
INSERT INTO data_template_rrd VALUES (78,'0ee6bb54957f6795a5369a29f818d860',0,0,43,'','0','','0','',600,'',1,'','hdd_used','',0);
INSERT INTO data_template_rrd VALUES (79,'9825aaf7c0bdf1554c5b4b86680ac2c0',0,0,44,'','100','','0','',600,'',1,'','cpu','',0);
INSERT INTO data_template_rrd VALUES (80,'50ccbe193c6c7fc29fb9f726cd6c48ee',0,0,45,'','1000','','0','',600,'',1,'','proc','',0);
INSERT INTO data_template_rrd VALUES (81,'9464c91bcff47f23085ae5adae6ab987',0,0,46,'','5000','','0','',600,'',1,'','users','',0);
INSERT INTO data_template_rrd VALUES (84,'',16,3,13,NULL,'0',NULL,'0',NULL,600,NULL,1,NULL,'mem_buffers',NULL,23);
INSERT INTO data_template_rrd VALUES (85,'',18,4,15,NULL,'0',NULL,'0',NULL,600,NULL,1,NULL,'mem_swap',NULL,23);
INSERT INTO data_template_rrd VALUES (86,'',12,5,11,NULL,'500',NULL,'0',NULL,600,NULL,1,NULL,'load_1min',NULL,17);
INSERT INTO data_template_rrd VALUES (87,'',13,5,11,NULL,'500',NULL,'0',NULL,600,NULL,1,NULL,'load_5min',NULL,18);
INSERT INTO data_template_rrd VALUES (88,'',14,5,11,NULL,'500',NULL,'0',NULL,600,NULL,1,NULL,'load_15min',NULL,19);
INSERT INTO data_template_rrd VALUES (89,'',20,6,17,NULL,'500',NULL,'0',NULL,600,NULL,1,NULL,'users',NULL,21);
INSERT INTO data_template_rrd VALUES (90,'',19,7,16,NULL,'1000',NULL,'0',NULL,600,NULL,1,NULL,'proc',NULL,24);
INSERT INTO data_template_rrd VALUES (92,'165a0da5f461561c85d092dfe96b9551',0,0,43,'','0','','0','',600,'',1,'','hdd_total','',0);
INSERT INTO data_template_rrd VALUES (95,'7a6ca455bbeff99ca891371bc77d5cf9',0,0,47,'','10000000','','0','',600,'',1,'','mem_cache','',0);
INSERT INTO data_template_rrd VALUES (96,'224b83ea73f55f8a861bcf4c9bea0472',0,0,48,'on','100','','0','',600,'on',1,'','snmp_oid','',0);

--
-- Table structure for table `graph_local`
--

CREATE TABLE graph_local (
  id mediumint(8) unsigned NOT NULL auto_increment,
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) NOT NULL default '0',
  snmp_index varchar(255) NOT NULL default '',
  PRIMARY KEY  (id),
  KEY host_id (host_id),
  KEY graph_template_id (graph_template_id),
  KEY snmp_query_id (snmp_query_id),
  KEY snmp_index (snmp_index)
) ENGINE=MyISAM COMMENT='Creates a relationship for each item in a custom graph.';

--
-- Dumping data for table `graph_local`
--

INSERT INTO graph_local VALUES (1,12,1,0,'');
INSERT INTO graph_local VALUES (2,9,1,0,'');
INSERT INTO graph_local VALUES (3,10,1,0,'');
INSERT INTO graph_local VALUES (4,8,1,0,'');

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
  PRIMARY KEY  (id)
) ENGINE=MyISAM COMMENT='Stores the names for graph item input groups.';

--
-- Dumping data for table `graph_template_input`
--

INSERT INTO graph_template_input VALUES (3,'e9d4191277fdfd7d54171f153da57fb0',2,'Inbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (4,'7b361722a11a03238ee8ab7ce44a1037',2,'Outbound Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (5,'b33eb27833614056e06ee5952c3e0724',3,'Available Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (6,'ef8799e63ee00e8904bcc4228015784a',3,'Used Disk Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (7,'2662ef4fbb0bf92317ffd42c7515af37',5,'Signal Level Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (8,'a6edef6624c796d3a6055305e2e3d4bf',5,'Noise Level Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (9,'b0e902db1875e392a9d7d69bfbb13515',5,'Signal Level Color','','color_id');
INSERT INTO graph_template_input VALUES (10,'24632b1d4a561e937225d0a5fbe65e41',5,'Noise Level Color','','color_id');
INSERT INTO graph_template_input VALUES (11,'6d078f1d58b70ad154a89eb80fe6ab75',6,'Transmissions Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (12,'878241872dd81c68d78e6ff94871d97d',6,'Re-Transmissions Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (13,'f8fcdc3a3f0e8ead33bd9751895a3462',6,'Transmissions Color','','color_id');
INSERT INTO graph_template_input VALUES (14,'394ab4713a34198dddb5175aa40a2b4a',6,'Re-Transmissions Color','','color_id');
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
INSERT INTO graph_template_input VALUES (37,'03d11dce695963be30bd744bd6cbac69',14,'Cache Hits Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (38,'9cbc515234779af4bf6cdf71a81c556a',14,'Cache Checks Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (39,'2c4d561ee8132a8dda6de1104336a6ec',15,'CPU Utilization Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (40,'6e1cf7addc0cc419aa903552e3eedbea',16,'File System Reads Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (41,'7ea2aa0656f7064d25a36135dd0e9082',16,'File System Writes Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (42,'63480bca78a38435f24a5b5d5ed050d7',17,'Current Logins Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (44,'31fed1f9e139d4897d0460b10fb7be94',15,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (45,'bb9d83a02261583bc1f92d9e66ea705d',18,'CPU Usage Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (46,'51196222ed37b44236d9958116028980',18,'Legend Color','','color_id');
INSERT INTO graph_template_input VALUES (47,'fd26b0f437b75715d6dff983e7efa710',19,'Free Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (48,'a463dd46862605c90ea60ccad74188db',19,'Total Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (49,'9977dd7a41bcf0f0c02872b442c7492e',19,'Freeable Space Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (51,'a7a69bbdf6890d6e6eaa7de16e815ec6',20,'Used Directory Entries Data Source','','task_item_id');
INSERT INTO graph_template_input VALUES (52,'0072b613a33f1fae5ce3e5903dec8fdb',20,'Available Directory Entries Data Source','','task_item_id');
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
INSERT INTO graph_template_input VALUES (73,'69ad68fc53af03565aef501ed5f04744',30,'Open Files Data Source','','task_item_id');
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
  PRIMARY KEY  (graph_template_input_id,graph_template_item_id),
  KEY graph_template_input_id (graph_template_input_id)
) ENGINE=MyISAM COMMENT='Stores the relationship for what graph iitems are associated';

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
INSERT INTO graph_template_input_defs VALUES (7,45);
INSERT INTO graph_template_input_defs VALUES (7,46);
INSERT INTO graph_template_input_defs VALUES (7,47);
INSERT INTO graph_template_input_defs VALUES (7,48);
INSERT INTO graph_template_input_defs VALUES (8,49);
INSERT INTO graph_template_input_defs VALUES (8,50);
INSERT INTO graph_template_input_defs VALUES (8,51);
INSERT INTO graph_template_input_defs VALUES (8,52);
INSERT INTO graph_template_input_defs VALUES (9,45);
INSERT INTO graph_template_input_defs VALUES (10,49);
INSERT INTO graph_template_input_defs VALUES (11,53);
INSERT INTO graph_template_input_defs VALUES (11,54);
INSERT INTO graph_template_input_defs VALUES (11,55);
INSERT INTO graph_template_input_defs VALUES (11,56);
INSERT INTO graph_template_input_defs VALUES (12,57);
INSERT INTO graph_template_input_defs VALUES (12,58);
INSERT INTO graph_template_input_defs VALUES (12,59);
INSERT INTO graph_template_input_defs VALUES (12,60);
INSERT INTO graph_template_input_defs VALUES (13,53);
INSERT INTO graph_template_input_defs VALUES (14,57);
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
INSERT INTO graph_template_input_defs VALUES (37,111);
INSERT INTO graph_template_input_defs VALUES (37,112);
INSERT INTO graph_template_input_defs VALUES (37,113);
INSERT INTO graph_template_input_defs VALUES (37,114);
INSERT INTO graph_template_input_defs VALUES (38,115);
INSERT INTO graph_template_input_defs VALUES (38,116);
INSERT INTO graph_template_input_defs VALUES (38,117);
INSERT INTO graph_template_input_defs VALUES (38,118);
INSERT INTO graph_template_input_defs VALUES (39,119);
INSERT INTO graph_template_input_defs VALUES (39,120);
INSERT INTO graph_template_input_defs VALUES (39,121);
INSERT INTO graph_template_input_defs VALUES (39,122);
INSERT INTO graph_template_input_defs VALUES (40,123);
INSERT INTO graph_template_input_defs VALUES (40,124);
INSERT INTO graph_template_input_defs VALUES (40,125);
INSERT INTO graph_template_input_defs VALUES (40,126);
INSERT INTO graph_template_input_defs VALUES (41,127);
INSERT INTO graph_template_input_defs VALUES (41,128);
INSERT INTO graph_template_input_defs VALUES (41,129);
INSERT INTO graph_template_input_defs VALUES (41,130);
INSERT INTO graph_template_input_defs VALUES (42,131);
INSERT INTO graph_template_input_defs VALUES (42,132);
INSERT INTO graph_template_input_defs VALUES (42,133);
INSERT INTO graph_template_input_defs VALUES (42,134);
INSERT INTO graph_template_input_defs VALUES (44,119);
INSERT INTO graph_template_input_defs VALUES (45,139);
INSERT INTO graph_template_input_defs VALUES (45,140);
INSERT INTO graph_template_input_defs VALUES (45,141);
INSERT INTO graph_template_input_defs VALUES (45,142);
INSERT INTO graph_template_input_defs VALUES (46,139);
INSERT INTO graph_template_input_defs VALUES (47,143);
INSERT INTO graph_template_input_defs VALUES (47,144);
INSERT INTO graph_template_input_defs VALUES (47,145);
INSERT INTO graph_template_input_defs VALUES (47,146);
INSERT INTO graph_template_input_defs VALUES (48,147);
INSERT INTO graph_template_input_defs VALUES (48,148);
INSERT INTO graph_template_input_defs VALUES (48,149);
INSERT INTO graph_template_input_defs VALUES (48,150);
INSERT INTO graph_template_input_defs VALUES (49,151);
INSERT INTO graph_template_input_defs VALUES (49,152);
INSERT INTO graph_template_input_defs VALUES (49,153);
INSERT INTO graph_template_input_defs VALUES (49,154);
INSERT INTO graph_template_input_defs VALUES (51,159);
INSERT INTO graph_template_input_defs VALUES (51,160);
INSERT INTO graph_template_input_defs VALUES (51,161);
INSERT INTO graph_template_input_defs VALUES (51,162);
INSERT INTO graph_template_input_defs VALUES (52,163);
INSERT INTO graph_template_input_defs VALUES (52,164);
INSERT INTO graph_template_input_defs VALUES (52,165);
INSERT INTO graph_template_input_defs VALUES (52,166);
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
INSERT INTO graph_template_input_defs VALUES (73,358);
INSERT INTO graph_template_input_defs VALUES (73,359);
INSERT INTO graph_template_input_defs VALUES (73,360);
INSERT INTO graph_template_input_defs VALUES (73,361);
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
  PRIMARY KEY  (id),
  KEY name (name)
) ENGINE=MyISAM COMMENT='Contains each graph template name.';

--
-- Dumping data for table `graph_templates`
--

INSERT INTO graph_templates VALUES (34,'010b90500e1fc6a05abfd542940584d0','SNMP - Generic OID Template');
INSERT INTO graph_templates VALUES (2,'5deb0d66c81262843dce5f3861be9966','Interface - Traffic (bits/sec)');
INSERT INTO graph_templates VALUES (3,'abb5e813c9f1e8cd6fc1e393092ef8cb','ucd/net - Available Disk Space');
INSERT INTO graph_templates VALUES (4,'e334bdcf821cd27270a4cc945e80915e','ucd/net - CPU Usage');
INSERT INTO graph_templates VALUES (5,'280e38336d77acde4672879a7db823f3','Karlnet - Wireless Levels');
INSERT INTO graph_templates VALUES (6,'3109d88e6806d2ce50c025541b542499','Karlnet - Wireless Transmissions');
INSERT INTO graph_templates VALUES (7,'cf96dfb22b58e08bf101ca825377fa4b','Unix - Ping Latency');
INSERT INTO graph_templates VALUES (8,'9fe8b4da353689d376b99b2ea526cc6b','Unix - Processes');
INSERT INTO graph_templates VALUES (9,'fe5edd777a76d48fc48c11aded5211ef','Unix - Load Average');
INSERT INTO graph_templates VALUES (10,'63610139d44d52b195cc375636653ebd','Unix - Logged in Users');
INSERT INTO graph_templates VALUES (11,'5107ec0206562e77d965ce6b852ef9d4','ucd/net - Load Average');
INSERT INTO graph_templates VALUES (12,'6992ed4df4b44f3d5595386b8298f0ec','Linux - Memory Usage');
INSERT INTO graph_templates VALUES (13,'be275639d5680e94c72c0ebb4e19056d','ucd/net - Memory Usage');
INSERT INTO graph_templates VALUES (14,'f17e4a77b8496725dc924b8c35b60036','Netware - File System Cache');
INSERT INTO graph_templates VALUES (15,'46bb77f4c0c69671980e3c60d3f22fa9','Netware - CPU Utilization');
INSERT INTO graph_templates VALUES (16,'8e77a3036312fd0fda32eaea2b5f141b','Netware - File System Activity');
INSERT INTO graph_templates VALUES (17,'5892c822b1bb2d38589b6c27934b9936','Netware - Logged In Users');
INSERT INTO graph_templates VALUES (18,'9a5e6d7781cc1bd6cf24f64dd6ffb423','Cisco - CPU Usage');
INSERT INTO graph_templates VALUES (19,'0dd0438d5e6cad6776f79ecaa96fb708','Netware - Volume Information');
INSERT INTO graph_templates VALUES (20,'b18a3742ebea48c6198412b392d757fc','Netware - Directory Information');
INSERT INTO graph_templates VALUES (21,'8e7c8a511652fe4a8e65c69f3d34779d','Unix - Available Disk Space');
INSERT INTO graph_templates VALUES (22,'06621cd4a9289417cadcb8f9b5cfba80','Interface - Errors/Discards');
INSERT INTO graph_templates VALUES (23,'e0d1625a1f4776a5294583659d5cee15','Interface - Unicast Packets');
INSERT INTO graph_templates VALUES (24,'10ca5530554da7b73dc69d291bf55d38','Interface - Non-Unicast Packets');
INSERT INTO graph_templates VALUES (25,'df244b337547b434b486662c3c5c7472','Interface - Traffic (bytes/sec)');
INSERT INTO graph_templates VALUES (26,'7489e44466abee8a7d8636cb2cb14a1a','Host MIB - Available Disk Space');
INSERT INTO graph_templates VALUES (27,'c6bb62bedec4ab97f9db9fd780bd85a6','Host MIB - CPU Utilization');
INSERT INTO graph_templates VALUES (28,'e8462bbe094e4e9e814d4e681671ea82','Host MIB - Logged in Users');
INSERT INTO graph_templates VALUES (29,'62205afbd4066e5c4700338841e3901e','Host MIB - Processes');
INSERT INTO graph_templates VALUES (30,'e3780a13b0f7a3f85a44b70cd4d2fd36','Netware - Open Files');
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
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

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
  t_image_format_id char(2) default '0',
  image_format_id tinyint(1) NOT NULL default '0',
  t_title char(2) default '0',
  title varchar(255) NOT NULL default '',
  title_cache varchar(255) NOT NULL default '',
  t_height char(2) default '0',
  height mediumint(8) NOT NULL default '0',
  t_width char(2) default '0',
  width mediumint(8) NOT NULL default '0',
  t_upper_limit char(2) default '0',
  upper_limit varchar(20) NOT NULL default '0',
  t_lower_limit char(2) default '0',
  lower_limit varchar(20) NOT NULL default '0',
  t_vertical_label char(2) default '0',
  vertical_label varchar(200) default NULL,
  t_slope_mode char(2) default '0',
  slope_mode char(2) default 'on',
  t_auto_scale char(2) default '0',
  auto_scale char(2) default NULL,
  t_auto_scale_opts char(2) default '0',
  auto_scale_opts tinyint(1) NOT NULL default '0',
  t_auto_scale_log char(2) default '0',
  auto_scale_log char(2) default NULL,
  t_scale_log_units char(2) default '0',
  scale_log_units char(2) default NULL,
  t_auto_scale_rigid char(2) default '0',
  auto_scale_rigid char(2) default NULL,
  t_auto_padding char(2) default '0',
  auto_padding char(2) default NULL,
  t_base_value char(2) default '0',
  base_value mediumint(8) NOT NULL default '0',
  t_grouping char(2) default '0',
  grouping char(2) NOT NULL default '',
  t_export char(2) default '0',
  export char(2) default NULL,
  t_unit_value char(2) default '0',
  unit_value varchar(20) default NULL,
  t_unit_exponent_value char(2) default '0',
  unit_exponent_value varchar(5) NOT NULL default '',
  PRIMARY KEY  (id),
  KEY local_graph_id (local_graph_id),
  KEY graph_template_id (graph_template_id),
  KEY title_cache (title_cache)
) ENGINE=MyISAM COMMENT='Stores the actual graph data.';

--
-- Dumping data for table `graph_templates_graph`
--

INSERT INTO graph_templates_graph VALUES (2,0,0,2,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bits per second','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (3,0,0,3,'',1,'on','|host_description| - Hard Drive Space','','',120,'',500,'','100','','0','','bytes','0','on','','on','',2,'','','0','','','on','','on','',1024,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (4,0,0,4,'',1,'','|host_description| - CPU Usage','','',120,'',500,'','100','','0','','percent','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (5,0,0,5,'',1,'on','|host_description| - Wireless Levels','','',120,'',500,'','100','','0','','percent','0','on','','','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (6,0,0,6,'',1,'on','|host_description| - Wireless Transmissions','','',120,'',500,'','100','','0','','transmissions','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (7,0,0,7,'',1,'','|host_description| - Ping Latency','','',120,'',500,'','100','','0','','milliseconds','0','on','','on','',2,'','','0','','','','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (8,0,0,8,'',1,'','|host_description| - Processes','','',120,'',500,'','100','','0','','processes','0','on','','on','',2,'','','0','','','','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (9,0,0,9,'',1,'','|host_description| - Load Average','','',120,'',500,'','100','','0','','processes in the run queue','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','0');
INSERT INTO graph_templates_graph VALUES (10,0,0,10,'',1,'','|host_description| - Logged in Users','','',120,'',500,'','100','','0','','users','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (11,0,0,11,'',1,'','|host_description| - Load Average','','',120,'',500,'','100','','0','','processes in the run queue','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','0');
INSERT INTO graph_templates_graph VALUES (12,0,0,12,'',1,'','|host_description| - Memory Usage','','',120,'',500,'','100','','0','','kilobytes','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (13,0,0,13,'',1,'','|host_description| - Memory Usage','','',120,'',500,'','100','','0','','bytes','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (14,0,0,14,'',1,'','|host_description| - File System Cache','','',120,'',500,'','100','','0','','cache checks/hits','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (15,0,0,15,'',1,'','|host_description| - CPU Utilization','','',120,'',500,'','100','','0','','percent','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (16,0,0,16,'',1,'','|host_description| - File System Activity','','',120,'',500,'','100','','0','','reads/writes per sec','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (17,0,0,17,'',1,'','|host_description| - Logged In Users','','',120,'',500,'','100','','0','','users','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (18,0,0,18,'',1,'','|host_description| - CPU Usage','','',120,'',500,'','100','','0','','percent','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (19,0,0,19,'',1,'on','|host_description| - Volume Information','','',120,'',500,'','100','','0','','bytes','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (20,0,0,20,'',1,'','|host_description| - Directory Information','','',120,'',500,'','100','','0','','directory entries','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (21,0,0,21,'',1,'on','|host_description| - Available Disk Space','','',120,'',500,'','100','','0','','bytes','0','on','','on','',2,'','','0','','','on','','on','',1024,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (22,0,0,22,'',1,'on','|host_description| - Errors/Discards','','',120,'',500,'','100','','0','','errors/sec','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (23,0,0,23,'',1,'on','|host_description| - Unicast Packets','','',120,'',500,'','100','','0','','packets/sec','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (24,0,0,24,'',1,'on','|host_description| - Non-Unicast Packets','','',120,'',500,'','100','','0','','packets/sec','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (25,0,0,25,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bytes per second','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (34,0,0,26,'',1,'on','|host_description| - Available Disk Space','','',120,'',500,'','100','','0','','bytes','0','on','','on','',2,'','','0','','','on','','on','',1024,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (35,0,0,27,'',1,'on','|host_description| - CPU Utilization','','',120,'',500,'','100','','0','','percent','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (36,0,0,28,'',1,'','|host_description| - Logged in Users','','',120,'',500,'','100','','0','','users','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (37,0,0,29,'',1,'','|host_description| - Processes','','',120,'',500,'','100','','0','','processes','0','on','','on','',2,'','','0','','','','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (38,12,1,12,'0',1,'0','|host_description| - Memory Usage','Localhost - Memory Usage','0',120,'0',500,'0','100','0','0','0','kilobytes','0','on','0','on','0',2,'0','','0','','0','on','0','on','0',1000,'0','','0','on','0','','0','');
INSERT INTO graph_templates_graph VALUES (39,9,2,9,'0',1,'0','|host_description| - Load Average','Localhost - Load Average','0',120,'0',500,'0','100','0','0','0','processes in the run queue','0','on','0','on','0',2,'0','','0','','0','on','0','on','0',1000,'0','','0','on','0','','0','0');
INSERT INTO graph_templates_graph VALUES (40,10,3,10,'0',1,'0','|host_description| - Logged in Users','Localhost - Logged in Users','0',120,'0',500,'0','100','0','0','0','users','0','on','0','on','0',2,'0','','0','','0','on','0','on','0',1000,'0','','0','on','0','','0','');
INSERT INTO graph_templates_graph VALUES (41,8,4,8,'0',1,'0','|host_description| - Processes','Localhost - Processes','0',120,'0',500,'0','100','0','0','0','processes','0','on','0','on','0',2,'0','','0','','0','','0','on','0',1000,'0','','0','on','0','','0','');
INSERT INTO graph_templates_graph VALUES (42,0,0,30,'',1,'','|host_description| - Open Files','','',120,'',500,'','100','','0','','files','0','on','','on','',2,'','','0','','','','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (43,0,0,31,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bits per second','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (44,0,0,32,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bits per second','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (45,0,0,33,'',1,'on','|host_description| - Traffic','','',120,'',500,'','100','','0','','bytes per second','0','on','','on','',2,'','','0','','','on','','on','',1000,'0','','','on','','','','');
INSERT INTO graph_templates_graph VALUES (47,0,0,34,'',1,'on','|host_description| -','','',120,'',500,'','100','','0','on','','0','on','','on','',2,'','','0','','','','','on','',1000,'0','','','on','','','','');

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
  cdef_id mediumint(8) unsigned NOT NULL default '0',
  consolidation_function_id tinyint(2) NOT NULL default '0',
  text_format varchar(255) default NULL,
  value varchar(255) default NULL,
  hard_return char(2) default NULL,
  gprint_id mediumint(8) unsigned NOT NULL default '0',
  sequence mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (id),
  KEY graph_template_id (graph_template_id),
  KEY local_graph_id (local_graph_id),
  KEY task_item_id (task_item_id)
) ENGINE=MyISAM COMMENT='Stores the actual graph item data.';

--
-- Dumping data for table `graph_templates_item`
--

INSERT INTO graph_templates_item VALUES (9,'0470b2427dbfadb6b8346e10a71268fa',0,0,2,54,22,'FF',7,2,1,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (10,'84a5fe0db518550266309823f994ce9c',0,0,2,54,0,'FF',9,2,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (11,'2f222f28084085cd06a1f46e4449c793',0,0,2,54,0,'FF',9,2,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (12,'55acbcc33f46ee6d754e8e81d1b54808',0,0,2,54,0,'FF',9,2,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (13,'fdaf2321fc890e355711c2bffc07d036',0,0,2,55,20,'FF',4,2,1,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (14,'768318f42819217ed81196d2179d3e1b',0,0,2,55,0,'FF',9,2,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (15,'cb3aa6256dcb3acd50d4517b77a1a5c3',0,0,2,55,0,'FF',9,2,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (16,'671e989be7cbf12c623b4e79d91c7bed',0,0,2,55,0,'FF',9,2,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (17,'b561ed15b3ba66d277e6d7c1640b86f7',0,0,3,4,48,'FF',7,14,1,'Used','','',2,1);
INSERT INTO graph_templates_item VALUES (18,'99ef051057fa6adfa6834a7632e9d8a2',0,0,3,4,0,'FF',9,14,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (19,'3986695132d3f4716872df4c6fbccb65',0,0,3,4,0,'FF',9,14,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (20,'0444300017b368e6257f010dca8bbd0d',0,0,3,4,0,'FF',9,14,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (21,'4d6a0b9063124ca60e2d1702b3e15e41',0,0,3,3,20,'FF',8,14,1,'Available','','',2,5);
INSERT INTO graph_templates_item VALUES (22,'181b08325e4d00cd50b8cdc8f8ae8e77',0,0,3,3,0,'FF',9,14,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (23,'bba0a9ff1357c990df50429d64314340',0,0,3,3,0,'FF',9,14,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (24,'d4a67883d53bc1df8aead21c97c0bc52',0,0,3,3,0,'FF',9,14,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (25,'253c9ec2d66905245149c1c2dc8e536e',0,0,3,0,1,'FF',5,15,1,'Total','','',2,9);
INSERT INTO graph_templates_item VALUES (26,'ea9ea883383f4eb462fec6aa309ba7b5',0,0,3,0,0,'FF',9,15,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (27,'83b746bcaba029eeca170a9f77ec4864',0,0,3,0,0,'FF',9,15,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (28,'82e01dd92fd37887c0696192efe7af65',0,0,3,0,0,'FF',9,15,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (29,'ff0a6125acbb029b814ed1f271ad2d38',0,0,4,5,9,'FF',7,0,1,'System','','',2,1);
INSERT INTO graph_templates_item VALUES (30,'f0776f7d6638bba76c2c27f75a424f0f',0,0,4,5,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (31,'39f4e021aa3fed9207b5f45a82122b21',0,0,4,5,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (32,'800f0b067c06f4ec9c2316711ea83c1e',0,0,4,5,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (33,'9419dd5dbf549ba4c5dc1462da6ee321',0,0,4,6,21,'FF',8,0,1,'User','','',2,5);
INSERT INTO graph_templates_item VALUES (34,'e461dd263ae47657ea2bf3fd82bec096',0,0,4,6,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (35,'f2d1fbb8078a424ffc8a6c9d44d8caa0',0,0,4,6,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (36,'e70a5de639df5ba1705b5883da7fccfc',0,0,4,6,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (37,'85fefb25ce9fd0317da2706a5463fc42',0,0,4,7,12,'FF',8,0,1,'Nice','','',2,9);
INSERT INTO graph_templates_item VALUES (38,'a1cb26878776999db16f1de7577b3c2a',0,0,4,7,0,'FF',9,0,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (39,'7d0f9bf64a0898a0095f099674754273',0,0,4,7,0,'FF',9,0,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (40,'b2879248a522d9679333e1f29e9a87c3',0,0,4,7,0,'FF',9,0,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (41,'d800aa59eee45383b3d6d35a11cdc864',0,0,4,0,1,'FF',4,12,1,'Total','','',2,13);
INSERT INTO graph_templates_item VALUES (42,'cab4ae79a546826288e273ca1411c867',0,0,4,0,0,'FF',9,12,4,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (43,'d44306ae85622fec971507460be63f5c',0,0,4,0,0,'FF',9,12,1,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (44,'aa5c2118035bb83be497d4e099afcc0d',0,0,4,0,0,'FF',9,12,3,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (45,'4aa34ea1b7542b770ace48e8bc395a22',0,0,5,9,48,'FF',7,0,1,'Signal Level','','',2,1);
INSERT INTO graph_templates_item VALUES (46,'22f118a9d81d0a9c8d922efbbc8a9cc1',0,0,5,9,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (47,'229de0c4b490de9d20d8f8d41059f933',0,0,5,9,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (48,'cd17feb30c02fd8f21e4d4dcde04e024',0,0,5,9,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (49,'8723600cfd0f8a7b3f7dc1361981aabd',0,0,5,8,25,'FF',5,0,1,'Noise Level','','',2,5);
INSERT INTO graph_templates_item VALUES (50,'cb06be2601b5abfb7a42fc07586de1c2',0,0,5,8,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (51,'55a2ee0fd511e5210ed85759171de58f',0,0,5,8,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (52,'704459564c84e42462e106eef20db169',0,0,5,8,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (53,'aaebb19ec522497eaaf8c87a631b7919',0,0,6,10,48,'FF',7,0,1,'Transmissions','','',2,1);
INSERT INTO graph_templates_item VALUES (54,'8b54843ac9d41bce2fcedd023560ed64',0,0,6,10,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (55,'05927dc83e07c7d9cffef387d68f35c9',0,0,6,10,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (56,'d11e62225a7e7a0cdce89242002ca547',0,0,6,10,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (57,'6397b92032486c476b0e13a35b727041',0,0,6,11,25,'FF',5,0,1,'Re-Transmissions','','',2,5);
INSERT INTO graph_templates_item VALUES (58,'cdfa5f8f82f4c479ff7f6f54160703f6',0,0,6,11,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (59,'ce2a309fb9ef64f83f471895069a6f07',0,0,6,11,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (60,'9cbfbf57ebde435b27887f27c7d3caea',0,0,6,11,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (61,'80e0aa956f50c261e5143273da58b8a3',0,0,7,21,25,'FF',7,0,1,'','','',2,1);
INSERT INTO graph_templates_item VALUES (62,'48fdcae893a7b7496e1a61efc3453599',0,0,7,21,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (63,'22f43e5fa20f2716666ba9ed9a7d1727',0,0,7,21,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (64,'3e86d497bcded7af7ab8408e4908e0d8',0,0,7,21,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (65,'ba00ecd28b9774348322ff70a96f2826',0,0,8,19,48,'FF',7,0,1,'Running Processes','','',2,1);
INSERT INTO graph_templates_item VALUES (66,'8d76de808efd73c51e9a9cbd70579512',0,0,8,19,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (67,'304244ca63d5b09e62a94c8ec6fbda8d',0,0,8,19,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (68,'da1ba71a93d2ed4a2a00d54592b14157',0,0,8,19,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (69,'93ad2f2803b5edace85d86896620b9da',0,0,9,12,15,'FF',7,0,1,'1 Minute Average','','',2,1);
INSERT INTO graph_templates_item VALUES (70,'e28736bf63d3a3bda03ea9f1e6ecb0f1',0,0,9,12,0,'FF',9,0,4,'Current:','','on',4,2);
INSERT INTO graph_templates_item VALUES (71,'bbdfa13adc00398eed132b1ccb4337d2',0,0,9,13,8,'FF',8,0,1,'5 Minute Average','','',2,3);
INSERT INTO graph_templates_item VALUES (72,'2c14062c7d67712f16adde06132675d6',0,0,9,13,0,'FF',9,0,4,'Current:','','on',4,4);
INSERT INTO graph_templates_item VALUES (73,'9cf6ed48a6a54b9644a1de8c9929bd4e',0,0,9,14,9,'FF',8,0,1,'15 Minute Average','','',2,5);
INSERT INTO graph_templates_item VALUES (74,'c9824064305b797f38feaeed2352e0e5',0,0,9,14,0,'FF',9,0,4,'Current:','','on',4,6);
INSERT INTO graph_templates_item VALUES (75,'fa1bc4eff128c4da70f5247d55b8a444',0,0,9,0,1,'FF',4,12,1,'','','on',2,7);
INSERT INTO graph_templates_item VALUES (76,'5c94ac24bc0d6d2712cc028fa7d4c7d2',0,0,10,20,67,'FF',7,0,1,'Users','','',2,1);
INSERT INTO graph_templates_item VALUES (77,'8bc7f905526f62df7d5c2d8c27c143c1',0,0,10,20,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (78,'cd074cd2b920aab70d480c020276d45b',0,0,10,20,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (79,'415630f25f5384ba0c82adbdb05fe98b',0,0,10,20,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (80,'d77d2050be357ab067666a9485426e6b',0,0,11,33,15,'FF',7,0,1,'1 Minute Average','','',2,1);
INSERT INTO graph_templates_item VALUES (81,'13d22f5a0eac6d97bf6c97d7966f0a00',0,0,11,33,0,'FF',9,0,4,'Current:','','on',4,2);
INSERT INTO graph_templates_item VALUES (82,'8580230d31d2851ec667c296a665cbf9',0,0,11,34,8,'FF',8,0,1,'5 Minute Average','','',2,3);
INSERT INTO graph_templates_item VALUES (83,'b5b7d9b64e7640aa51dbf58c69b86d15',0,0,11,34,0,'FF',9,0,4,'Current:','','on',4,4);
INSERT INTO graph_templates_item VALUES (84,'2ec10edf4bfaa866b7efd544d4c3f446',0,0,11,35,9,'FF',8,0,1,'15 Minute Average','','',2,5);
INSERT INTO graph_templates_item VALUES (85,'b65666f0506c0c70966f493c19607b93',0,0,11,35,0,'FF',9,0,4,'Current:','','on',4,6);
INSERT INTO graph_templates_item VALUES (86,'6c73575c74506cfc75b89c4276ef3455',0,0,11,0,1,'FF',4,12,1,'Total','','on',2,7);
INSERT INTO graph_templates_item VALUES (95,'5fa7c2317f19440b757ab2ea1cae6abc',0,0,12,16,41,'FF',7,14,1,'Free','','',2,9);
INSERT INTO graph_templates_item VALUES (96,'b1d18060bfd3f68e812c508ff4ac94ed',0,0,12,16,0,'FF',9,14,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (97,'780b6f0850aaf9431d1c246c55143061',0,0,12,16,0,'FF',9,14,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (98,'2d54a7e7bb45e6c52d97a09e24b7fba7',0,0,12,16,0,'FF',9,14,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (99,'40206367a3c192b836539f49801a0b15',0,0,12,18,30,'FF',8,14,1,'Swap','','',2,13);
INSERT INTO graph_templates_item VALUES (100,'7ee72e2bb3722d4f8a7f9c564e0dd0d0',0,0,12,18,0,'FF',9,14,4,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (101,'c8af33b949e8f47133ee25e63c91d4d0',0,0,12,18,0,'FF',9,14,1,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (102,'568128a16723d1195ce6a234d353ce00',0,0,12,18,0,'FF',9,14,3,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (103,'7517a40d478e28ed88ba2b2a65e16b57',0,0,13,37,52,'FF',7,14,1,'Memory Free','','',2,1);
INSERT INTO graph_templates_item VALUES (104,'df0c8b353d26c334cb909dc6243957c5',0,0,13,37,0,'FF',9,14,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (105,'c41a4cf6fefaf756a24f0a9510580724',0,0,13,37,0,'FF',9,14,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (106,'9efa8f01c6ed11364a21710ff170f422',0,0,13,37,0,'FF',9,14,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (107,'95d6e4e5110b456f34324f7941d08318',0,0,13,36,35,'FF',8,14,1,'Memory Buffers','','',2,5);
INSERT INTO graph_templates_item VALUES (108,'0c631bfc0785a9cca68489ea87a6c3da',0,0,13,36,0,'FF',9,14,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (109,'3468579d3b671dfb788696df7dcc1ec9',0,0,13,36,0,'FF',9,14,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (110,'c3ddfdaa65449f99b7f1a735307f9abe',0,0,13,36,0,'FF',9,14,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (111,'4c64d5c1ce8b5d8b94129c23b46a5fd6',0,0,14,28,41,'FF',7,0,1,'Cache Hits','','',2,1);
INSERT INTO graph_templates_item VALUES (112,'5c1845c9bd1af684a3c0ad843df69e3e',0,0,14,28,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (113,'e5169563f3f361701902a8da3ac0c77f',0,0,14,28,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (114,'35e87262efa521edbb1fd27f09c036f5',0,0,14,28,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (115,'53069d7dba4c31b338f609bea4cd16f3',0,0,14,27,66,'FF',8,0,1,'Cache Checks','','',2,5);
INSERT INTO graph_templates_item VALUES (116,'d9c102579839c5575806334d342b50de',0,0,14,27,0,'FF',9,0,4,'Current:','','',3,6);
INSERT INTO graph_templates_item VALUES (117,'dc1897c3249dbabe269af49cee92f8c0',0,0,14,27,0,'FF',9,0,1,'Average:','','',3,7);
INSERT INTO graph_templates_item VALUES (118,'ccd21fe0b5a8c24057f1eff4a6b66391',0,0,14,27,0,'FF',9,0,3,'Maximum:','','on',3,8);
INSERT INTO graph_templates_item VALUES (119,'ab09d41c358f6b8a9d0cad4eccc25529',0,0,15,76,9,'FF',7,0,1,'CPU Utilization','','',2,1);
INSERT INTO graph_templates_item VALUES (120,'5d5b8d8fbe751dc9c86ee86f85d7433b',0,0,15,76,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (121,'4822a98464c6da2afff10c6d12df1831',0,0,15,76,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (122,'fc6fbf2a964bea0b3c88ed0f18616aa7',0,0,15,76,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (123,'e4094625d5443b4c87f9a87ba616a469',0,0,16,25,67,'FF',7,0,1,'File System Reads','','',2,1);
INSERT INTO graph_templates_item VALUES (124,'ae68425cd10e8a6623076b2e6859a6aa',0,0,16,25,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (125,'40b8e14c6568b3f6be6a5d89d6a9f061',0,0,16,25,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (126,'4afbdc3851c03e206672930746b1a5e2',0,0,16,25,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (127,'ea47d2b5516e334bc5f6ce1698a3ae76',0,0,16,26,93,'FF',8,0,1,'File System Writes','','',2,5);
INSERT INTO graph_templates_item VALUES (128,'899c48a2f79ea3ad4629aff130d0f371',0,0,16,26,0,'FF',9,0,4,'Current:','','',3,6);
INSERT INTO graph_templates_item VALUES (129,'ab474d7da77e9ec1f6a1d45c602580cd',0,0,16,26,0,'FF',9,0,1,'Average:','','',3,7);
INSERT INTO graph_templates_item VALUES (130,'e143f8b4c6d4eeb6a28b052e6b8ce5a9',0,0,16,26,0,'FF',9,0,3,'Maximum:','','on',3,8);
INSERT INTO graph_templates_item VALUES (131,'facfeeb6fc2255ba2985b2d2f695d78a',0,0,17,23,30,'FF',7,0,1,'Current Logins','','',2,1);
INSERT INTO graph_templates_item VALUES (132,'2470e43034a5560260d79084432ed14f',0,0,17,23,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (133,'e9e645f07bde92b52d93a7a1f65efb30',0,0,17,23,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (134,'bdfe0d66103211cfdaa267a44a98b092',0,0,17,23,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (139,'098b10c13a5701ddb7d4d1d2e2b0fdb7',0,0,18,30,9,'FF',7,0,1,'CPU Usage','','',2,1);
INSERT INTO graph_templates_item VALUES (140,'1dbda412a9926b0ee5c025aa08f3b230',0,0,18,30,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (141,'725c45917146807b6a4257fc351f2bae',0,0,18,30,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (142,'4e336fdfeb84ce65f81ded0e0159a5e0',0,0,18,30,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (143,'7dab7a3ceae2addd1cebddee6c483e7c',0,0,19,39,25,'FF',7,14,1,'Free Space','','',2,5);
INSERT INTO graph_templates_item VALUES (144,'aea239f3ceea8c63d02e453e536190b8',0,0,19,39,0,'FF',9,14,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (145,'a0efae92968a6d4ae099b676e0f1430e',0,0,19,39,0,'FF',9,14,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (146,'4fd5ba88be16e3d513c9231b78ccf0e1',0,0,19,39,0,'FF',9,14,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (147,'d2e98e51189e1d9be8888c3d5c5a4029',0,0,19,38,69,'FF',7,14,1,'Total Space','','',2,1);
INSERT INTO graph_templates_item VALUES (148,'12829294ee3958f4a31a58a61228e027',0,0,19,38,0,'FF',9,14,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (149,'4b7e8755b0f2253723c1e9fb21fd37b1',0,0,19,38,0,'FF',9,14,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (150,'cbb19ffd7a0ead2bf61512e86d51ee8e',0,0,19,38,0,'FF',9,14,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (151,'37b4cbed68f9b77e49149343069843b4',0,0,19,40,95,'FF',5,14,1,'Freeable Space','','',2,9);
INSERT INTO graph_templates_item VALUES (152,'5eb7532200f2b5cc93e13743a7db027c',0,0,19,40,0,'FF',9,14,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (153,'b0f9f602fbeaaff090ea3f930b46c1c7',0,0,19,40,0,'FF',9,14,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (154,'06477f7ea46c63272cee7253e7cd8760',0,0,19,40,0,'FF',9,14,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (171,'a751838f87068e073b95be9555c57bde',0,0,21,56,0,'FF',9,14,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (170,'3b13eb2e542fe006c9bf86947a6854fa',0,0,21,56,0,'FF',9,14,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (169,'8ef3e7fb7ce962183f489725939ea40f',0,0,21,56,0,'FF',9,14,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (167,'6ca2161c37b0118786dbdb46ad767e5d',0,0,21,56,48,'FF',7,14,1,'Used','','',2,1);
INSERT INTO graph_templates_item VALUES (159,'6877a2a5362a9390565758b08b9b37f7',0,0,20,43,77,'FF',7,0,1,'Used Directory Entries','','',2,1);
INSERT INTO graph_templates_item VALUES (160,'a978834f3d02d833d3d2def243503bf2',0,0,20,43,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (161,'7422d87bc82de20a4333bd2f6460b2d4',0,0,20,43,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (162,'4d52762859a3fec297ebda0e7fd760d9',0,0,20,43,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (163,'999d4ed1128ff03edf8ea47e56d361dd',0,0,20,42,1,'FF',5,0,1,'Available Directory Entries','','',2,5);
INSERT INTO graph_templates_item VALUES (164,'3dfcd7f8c7a760ac89d34398af79b979',0,0,20,42,0,'FF',9,0,4,'Current:','','',3,6);
INSERT INTO graph_templates_item VALUES (165,'217be75e28505c8f8148dec6b71b9b63',0,0,20,42,0,'FF',9,0,1,'Average:','','',3,7);
INSERT INTO graph_templates_item VALUES (166,'69b89e1c5d6fc6182c93285b967f970a',0,0,20,42,0,'FF',9,0,3,'Maximum:','','on',3,8);
INSERT INTO graph_templates_item VALUES (172,'5d6dff9c14c71dc1ebf83e87f1c25695',0,0,21,44,20,'FF',8,14,1,'Available','','',2,5);
INSERT INTO graph_templates_item VALUES (173,'b27cb9a158187d29d17abddc6fdf0f15',0,0,21,44,0,'FF',9,14,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (174,'6c0555013bb9b964e51d22f108dae9b0',0,0,21,44,0,'FF',9,14,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (175,'42ce58ec17ef5199145fbf9c6ee39869',0,0,21,44,0,'FF',9,14,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (176,'9bdff98f2394f666deea028cbca685f3',0,0,21,0,1,'FF',5,15,1,'Total','','',2,9);
INSERT INTO graph_templates_item VALUES (177,'fb831fefcf602bc31d9d24e8e456c2e6',0,0,21,0,0,'FF',9,15,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (178,'5a958d56785a606c08200ef8dbf8deef',0,0,21,0,0,'FF',9,15,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (179,'5ce67a658cec37f526dc84ac9e08d6e7',0,0,21,0,0,'FF',9,15,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (180,'7e04a041721df1f8828381a9ea2f2154',0,0,22,47,31,'FF',4,0,1,'Discards In','','',2,1);
INSERT INTO graph_templates_item VALUES (181,'afc8bca6b1b3030a6d71818272336c6c',0,0,22,47,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (182,'6ac169785f5aeaf1cc5cdfd38dfcfb6c',0,0,22,47,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (183,'178c0a0ce001d36a663ff6f213c07505',0,0,22,47,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (184,'8e3268c0abde7550616bff719f10ee2f',0,0,22,46,48,'FF',4,0,1,'Errors In','','',2,5);
INSERT INTO graph_templates_item VALUES (185,'18891392b149de63b62c4258a68d75f8',0,0,22,46,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (186,'dfc9d23de0182c9967ae3dabdfa55a16',0,0,22,46,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (187,'c47ba64e2e5ea8bf84aceec644513176',0,0,22,46,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (188,'9d052e7d632c479737fbfaced0821f79',0,0,23,49,71,'FF',4,0,1,'Unicast Packets Out','','',2,5);
INSERT INTO graph_templates_item VALUES (189,'9b9fa6268571b6a04fa4411d8e08c730',0,0,23,49,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (190,'8e8f2fbeb624029cbda1d2a6ddd991ba',0,0,23,49,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (191,'c76495beb1ed01f0799838eb8a893124',0,0,23,49,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (192,'d4e5f253f01c3ea77182c5a46418fc44',0,0,23,48,25,'FF',7,0,1,'Unicast Packets In','','',2,1);
INSERT INTO graph_templates_item VALUES (193,'526a96add143da021c5f00d8764a6c12',0,0,23,48,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (194,'81eeb46f451212f00fd7caee42a81c0b',0,0,23,48,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (195,'089e4d1c3faeb00fd5dcc9622b06d656',0,0,23,48,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (196,'fe66cb973966d22250de073405664200',0,0,24,53,25,'FF',7,0,1,'Non-Unicast Packets In','','',2,1);
INSERT INTO graph_templates_item VALUES (197,'1ba3fc3466ad32fdd2669cac6cad6faa',0,0,24,53,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (198,'f810154d3a934c723c21659e66199cdf',0,0,24,53,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (199,'98a161df359b01304346657ff1a9d787',0,0,24,53,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (200,'d5e55eaf617ad1f0516f6343b3f07c5e',0,0,24,52,71,'FF',4,0,1,'Non-Unicast Packets Out','','',2,5);
INSERT INTO graph_templates_item VALUES (201,'9fde6b8c84089b9f9044e681162e7567',0,0,24,52,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (202,'9a3510727c3d9fa7e2e7a015783a99b3',0,0,24,52,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (203,'451afd23f2cb59ab9b975fd6e2735815',0,0,24,52,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (204,'617d10dff9bbc3edd9d733d9c254da76',0,0,22,50,18,'FF',4,0,1,'Discards Out','','',2,9);
INSERT INTO graph_templates_item VALUES (205,'9269a66502c34d00ac3c8b1fcc329ac6',0,0,22,50,0,'FF',9,0,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (206,'d45deed7e1ad8350f3b46b537ae0a933',0,0,22,50,0,'FF',9,0,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (207,'2f64cf47dc156e8c800ae03c3b893e3c',0,0,22,50,0,'FF',9,0,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (208,'57434bef8cb21283c1a73f055b0ada19',0,0,22,51,89,'FF',4,0,1,'Errors Out','','',2,13);
INSERT INTO graph_templates_item VALUES (209,'660a1b9365ccbba356fd142faaec9f04',0,0,22,51,0,'FF',9,0,4,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (210,'28c5297bdaedcca29acf245ef4bbed9e',0,0,22,51,0,'FF',9,0,1,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (211,'99098604fd0c78fd7dabac8f40f1fb29',0,0,22,51,0,'FF',9,0,3,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (212,'de3eefd6d6c58afabdabcaf6c0168378',0,0,25,54,22,'FF',7,0,1,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (213,'1a80fa108f5c46eecb03090c65bc9a12',0,0,25,54,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (214,'fe458892e7faa9d232e343d911e845f3',0,0,25,54,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (215,'175c0a68689bebc38aad2fbc271047b3',0,0,25,54,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (216,'1bf2283106510491ddf3b9c1376c0b31',0,0,25,55,20,'FF',4,0,1,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (217,'c5202f1690ffe45600c0d31a4a804f67',0,0,25,55,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (218,'eb9794e3fdafc2b74f0819269569ed40',0,0,25,55,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (219,'6bcedd61e3ccf7518ca431940c93c439',0,0,25,55,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (303,'b7b381d47972f836785d338a3bef6661',0,0,26,78,0,'FF',9,0,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (304,'36fa8063df3b07cece878d54443db727',0,0,26,78,0,'FF',9,0,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (305,'2c35b5cae64c5f146a55fcb416dd14b5',0,0,26,78,0,'FF',9,0,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (306,'16d6a9a7f608762ad65b0841e5ef4e9c',0,0,26,78,48,'FF',7,0,1,'Used','','',2,5);
INSERT INTO graph_templates_item VALUES (307,'d80e4a4901ab86ee39c9cc613e13532f',0,0,26,92,20,'FF',7,0,1,'Total','','',2,1);
INSERT INTO graph_templates_item VALUES (308,'567c2214ee4753aa712c3d101ea49a5d',0,0,26,92,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (309,'ba0b6a9e316ef9be66abba68b80f7587',0,0,26,92,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (310,'4b8e4a6bf2757f04c3e3a088338a2f7a',0,0,26,92,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (317,'8536e034ab5268a61473f1ff2f6bd88f',0,0,27,79,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (316,'d478a76de1df9edf896c9ce51506c483',0,0,27,79,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (315,'42537599b5fb8ea852240b58a58633de',0,0,27,79,9,'FF',7,0,1,'CPU Utilization','','',2,1);
INSERT INTO graph_templates_item VALUES (318,'87e10f9942b625aa323a0f39b60058e7',0,0,27,79,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (319,'38f6891b0db92aa8950b4ce7ae902741',0,0,28,81,67,'FF',7,0,1,'Users','','',2,1);
INSERT INTO graph_templates_item VALUES (320,'af13152956a20aa894ef4a4067b88f63',0,0,28,81,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (321,'1b2388bbede4459930c57dc93645284e',0,0,28,81,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (322,'6407dc226db1d03be9730f4d6f3eeccf',0,0,28,81,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (323,'fca6a530c8f37476b9004a90b42ee988',0,0,29,80,48,'FF',7,0,1,'Running Processes','','',2,1);
INSERT INTO graph_templates_item VALUES (324,'5acebbde3dc65e02f8fda03955852fbe',0,0,29,80,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (325,'311079ffffac75efaab2837df8123122',0,0,29,80,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (326,'724d27007ebf31016cfa5530fee1b867',0,0,29,80,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (373,'1995d8c23e7d8e1efa2b2c55daf3c5a7',0,0,32,54,22,'FF',7,2,1,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (335,'',95,1,12,84,41,'FF',7,0,1,'Free','','',2,9);
INSERT INTO graph_templates_item VALUES (336,'',96,1,12,84,0,'FF',9,0,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (337,'',97,1,12,84,0,'FF',9,0,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (338,'',98,1,12,84,0,'FF',9,0,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (339,'',99,1,12,85,30,'FF',8,0,1,'Swap','','',2,13);
INSERT INTO graph_templates_item VALUES (340,'',100,1,12,85,0,'FF',9,0,4,'Current:','','',2,14);
INSERT INTO graph_templates_item VALUES (341,'',101,1,12,85,0,'FF',9,0,1,'Average:','','',2,15);
INSERT INTO graph_templates_item VALUES (342,'',102,1,12,85,0,'FF',9,0,3,'Maximum:','','on',2,16);
INSERT INTO graph_templates_item VALUES (343,'',69,2,9,86,15,'FF',7,0,1,'1 Minute Average','','',2,1);
INSERT INTO graph_templates_item VALUES (344,'',70,2,9,86,0,'FF',9,0,4,'Current:','','on',4,2);
INSERT INTO graph_templates_item VALUES (345,'',71,2,9,87,8,'FF',8,0,1,'5 Minute Average','','',2,3);
INSERT INTO graph_templates_item VALUES (346,'',72,2,9,87,0,'FF',9,0,4,'Current:','','on',4,4);
INSERT INTO graph_templates_item VALUES (347,'',73,2,9,88,9,'FF',8,0,1,'15 Minute Average','','',2,5);
INSERT INTO graph_templates_item VALUES (348,'',74,2,9,88,0,'FF',9,0,4,'Current:','','on',4,6);
INSERT INTO graph_templates_item VALUES (349,'',75,2,9,0,1,'FF',4,12,1,'','','',2,7);
INSERT INTO graph_templates_item VALUES (350,'',76,3,10,89,67,'FF',7,0,1,'Users','','',2,1);
INSERT INTO graph_templates_item VALUES (351,'',77,3,10,89,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (352,'',78,3,10,89,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (353,'',79,3,10,89,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (354,'',65,4,8,90,48,'FF',7,0,1,'Running Processes','','',2,1);
INSERT INTO graph_templates_item VALUES (355,'',66,4,8,90,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (356,'',67,4,8,90,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (357,'',68,4,8,90,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (358,'803b96bcaec33148901b4b562d9d2344',0,0,30,29,89,'FF',7,0,1,'Open Files','','',2,1);
INSERT INTO graph_templates_item VALUES (359,'da26dd92666cb840f8a70e2ec5e90c07',0,0,30,29,0,'FF',9,0,4,'Current:','','',3,2);
INSERT INTO graph_templates_item VALUES (360,'5258970186e4407ed31cca2782650c45',0,0,30,29,0,'FF',9,0,1,'Average:','','',3,3);
INSERT INTO graph_templates_item VALUES (361,'7d08b996bde9cdc7efa650c7031137b4',0,0,30,29,0,'FF',9,0,3,'Maximum:','','on',3,4);
INSERT INTO graph_templates_item VALUES (362,'918e6e7d41bb4bae0ea2937b461742a4',0,0,31,54,22,'FF',7,2,1,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (363,'f19fbd06c989ea85acd6b4f926e4a456',0,0,31,54,0,'FF',9,2,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (364,'fc150a15e20c57e11e8d05feca557ef9',0,0,31,54,0,'FF',9,2,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (365,'ccbd86e03ccf07483b4d29e63612fb18',0,0,31,54,0,'FF',9,2,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (366,'964c5c30cd05eaf5a49c0377d173de86',0,0,31,55,20,'FF',4,2,1,'Outbound','','',2,5);
INSERT INTO graph_templates_item VALUES (367,'b1a6fb775cf62e79e1c4bc4933c7e4ce',0,0,31,55,0,'FF',9,2,4,'Current:','','',2,6);
INSERT INTO graph_templates_item VALUES (368,'721038182a872ab266b5cf1bf7f7755c',0,0,31,55,0,'FF',9,2,1,'Average:','','',2,7);
INSERT INTO graph_templates_item VALUES (369,'2302f80c2c70b897d12182a1fc11ecd6',0,0,31,55,0,'FF',9,2,3,'Maximum:','','on',2,8);
INSERT INTO graph_templates_item VALUES (370,'4ffc7af8533d103748316752b70f8e3c',0,0,31,0,0,'FF',1,0,1,'','','',2,9);
INSERT INTO graph_templates_item VALUES (371,'64527c4b6eeeaf627acc5117ff2180fd',0,0,31,55,9,'FF',2,0,1,'95th Percentile','|95:bits:0:max:2|','',2,10);
INSERT INTO graph_templates_item VALUES (372,'d5bbcbdbf83ae858862611ac6de8fc62',0,0,31,55,0,'FF',1,0,1,'(|95:bits:6:max:2| mbit in+out)','','on',2,11);
INSERT INTO graph_templates_item VALUES (374,'55083351cd728b82cc4dde68eb935700',0,0,32,54,0,'FF',9,2,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (375,'54782f71929e7d1734ed5ad4b8dda50d',0,0,32,54,0,'FF',9,2,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (376,'88d3094d5dc2164cbf2f974aeb92f051',0,0,32,54,0,'FF',9,2,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (377,'4a381a8e87d4db1ac99cf8d9078266d3',0,0,32,55,20,'FF',4,2,1,'Outbound','','',2,6);
INSERT INTO graph_templates_item VALUES (378,'5bff63207c7bf076d76ff3036b5dad54',0,0,32,55,0,'FF',9,2,4,'Current:','','',2,7);
INSERT INTO graph_templates_item VALUES (379,'979fff9d691ca35e3f4b3383d9cae43f',0,0,32,55,0,'FF',9,2,1,'Average:','','',2,8);
INSERT INTO graph_templates_item VALUES (380,'0e715933830112c23c15f7e3463f77b6',0,0,32,55,0,'FF',9,2,3,'Maximum:','','on',2,11);
INSERT INTO graph_templates_item VALUES (383,'5b43e4102600ad75379c5afd235099c4',0,0,32,54,0,'FF',1,0,1,'Total In:  |sum:auto:current:2:auto|','','on',2,5);
INSERT INTO graph_templates_item VALUES (384,'db7c15d253ca666601b3296f2574edc9',0,0,32,55,0,'FF',1,0,1,'Total Out: |sum:auto:current:2:auto|','','on',2,12);
INSERT INTO graph_templates_item VALUES (385,'fdaec5b9227522c758ad55882c483a83',0,0,33,55,0,'FF',9,0,3,'Maximum:','','on',2,11);
INSERT INTO graph_templates_item VALUES (386,'6824d29c3f13fe1e849f1dbb8377d3f1',0,0,33,55,0,'FF',9,0,1,'Average:','','',2,8);
INSERT INTO graph_templates_item VALUES (387,'54e3971b3dd751dd2509f62721c12b41',0,0,33,55,0,'FF',9,0,4,'Current:','','',2,7);
INSERT INTO graph_templates_item VALUES (388,'cf8c9f69878f0f595d583eac109a9be1',0,0,33,55,20,'FF',4,0,1,'Outbound','','',2,6);
INSERT INTO graph_templates_item VALUES (389,'de265acbbfa99eb4b3e9f7e90c7feeda',0,0,33,54,0,'FF',9,0,3,'Maximum:','','on',2,4);
INSERT INTO graph_templates_item VALUES (390,'777aa88fb0a79b60d081e0e3759f1cf7',0,0,33,54,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (391,'66bfdb701c8eeadffe55e926d6e77e71',0,0,33,54,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (392,'3ff8dba1ca6279692b3fcabed0bc2631',0,0,33,54,22,'FF',7,0,1,'Inbound','','',2,1);
INSERT INTO graph_templates_item VALUES (393,'d6041d14f9c8fb9b7ddcf3556f763c03',0,0,33,55,0,'FF',1,0,1,'Total Out: |sum:auto:current:2:auto|','','on',2,12);
INSERT INTO graph_templates_item VALUES (394,'76ae747365553a02313a2d8a0dd55c8a',0,0,33,54,0,'FF',1,0,1,'Total In:  |sum:auto:current:2:auto|','','on',2,5);
INSERT INTO graph_templates_item VALUES (403,'8a1b44ab97d3b56207d0e9e77a035d25',0,0,13,95,30,'FF',8,14,1,'Cache Memory','','',2,9);
INSERT INTO graph_templates_item VALUES (404,'6db3f439e9764941ff43fbaae348f5dc',0,0,13,95,0,'FF',9,14,4,'Current:','','',2,10);
INSERT INTO graph_templates_item VALUES (405,'cc9b2fe7acf0820caa61c1519193f65e',0,0,13,95,0,'FF',9,14,1,'Average:','','',2,11);
INSERT INTO graph_templates_item VALUES (406,'9eea140bdfeaa40d50c5cdcd1f23f72d',0,0,13,95,0,'FF',9,14,3,'Maximum:','','on',2,12);
INSERT INTO graph_templates_item VALUES (407,'41316670b1a36171de2bda91a0cc2364',0,0,34,96,98,'FF',7,0,1,'','','',2,1);
INSERT INTO graph_templates_item VALUES (408,'c9e8cbdca0215b434c902e68755903ea',0,0,34,96,0,'FF',9,0,4,'Current:','','',2,2);
INSERT INTO graph_templates_item VALUES (409,'dab91d7093e720841393feea5bdcba85',0,0,34,96,0,'FF',9,0,1,'Average:','','',2,3);
INSERT INTO graph_templates_item VALUES (410,'03e5bd2151fea3c90843eb1130b84458',0,0,34,96,0,'FF',9,0,3,'Maximum:','','on',2,4);

--
-- Table structure for table `graph_tree`
--

CREATE TABLE graph_tree (
  id smallint(5) unsigned NOT NULL auto_increment,
  sort_type tinyint(3) unsigned NOT NULL default '1',
  name varchar(255) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `graph_tree`
--

INSERT INTO graph_tree VALUES (1,1,'Default Tree');

--
-- Table structure for table `graph_tree_items`
--

CREATE TABLE graph_tree_items (
  id mediumint(8) unsigned NOT NULL auto_increment,
  graph_tree_id smallint(5) unsigned NOT NULL default '0',
  local_graph_id mediumint(8) unsigned NOT NULL default '0',
  rra_id smallint(8) unsigned NOT NULL default '0',
  title varchar(255) default NULL,
  host_id mediumint(8) unsigned NOT NULL default '0',
  order_key varchar(100) NOT NULL default '0',
  host_grouping_type tinyint(3) unsigned NOT NULL default '1',
  sort_children_type tinyint(3) unsigned NOT NULL default '1',
  PRIMARY KEY  (id),
  KEY graph_tree_id (graph_tree_id),
  KEY host_id (host_id),
  KEY local_graph_id (local_graph_id),
  KEY order_key (order_key)
) ENGINE=MyISAM;

--
-- Dumping data for table `graph_tree_items`
--

INSERT INTO graph_tree_items VALUES (7,1,0,0,'',1,'001000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',1,1);

--
-- Table structure for table `host`
--

CREATE TABLE host (
  id mediumint(8) unsigned NOT NULL auto_increment,
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  description varchar(150) NOT NULL default '',
  hostname varchar(250) default NULL,
  notes text,
  snmp_community varchar(100) default NULL,
  snmp_version tinyint(1) unsigned NOT NULL default '1',
  snmp_username varchar(50) default NULL,
  snmp_password varchar(50) default NULL,
  snmp_auth_protocol char(5) default '',
  snmp_priv_passphrase varchar(200) default '',
  snmp_priv_protocol char(6) default '',
  snmp_context varchar(64) default '',
  snmp_port mediumint(5) unsigned NOT NULL default '161',
  snmp_timeout mediumint(8) unsigned NOT NULL default '500',
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
  status_fail_date datetime NOT NULL default '0000-00-00 00:00:00',
  status_rec_date datetime NOT NULL default '0000-00-00 00:00:00',
  status_last_error varchar(255) default '',
  min_time decimal(10,5) default '9.99999',
  max_time decimal(10,5) default '0.00000',
  cur_time decimal(10,5) default '0.00000',
  avg_time decimal(10,5) default '0.00000',
  total_polls int(12) unsigned default '0',
  failed_polls int(12) unsigned default '0',
  availability decimal(8,5) NOT NULL default '100.00000',
  PRIMARY KEY  (id),
  KEY disabled (disabled)
) ENGINE=MyISAM;

--
-- Dumping data for table `host`
--

INSERT INTO `host` VALUES (1, 8, 'Localhost', '127.0.0.1', '', 'public', 0, '', '', 'MD5', '', 'DES', '', 161, 500, 3, 2, 23, 400, 1, 10, 1, '', 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', 9.99999, 0.00000, 0.00000, 0.00000, 0, 0, 100.00000);

--
-- Table structure for table `host_graph`
--

CREATE TABLE host_graph (
  host_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (host_id,graph_template_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `host_graph`
--

INSERT INTO host_graph VALUES (1,8);
INSERT INTO host_graph VALUES (1,9);
INSERT INTO host_graph VALUES (1,10);
INSERT INTO host_graph VALUES (1,12);

--
-- Table structure for table `host_snmp_cache`
--

CREATE TABLE host_snmp_cache (
  host_id mediumint(8) unsigned NOT NULL default '0',
  snmp_query_id mediumint(8) unsigned NOT NULL default '0',
  field_name varchar(50) NOT NULL default '',
  field_value varchar(255) default NULL,
  snmp_index varchar(255) NOT NULL default '',
  oid TEXT NOT NULL,
  present tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY  (host_id,snmp_query_id,field_name,snmp_index),
  KEY host_id (host_id,field_name),
  KEY snmp_index (snmp_index),
  KEY field_name (field_name),
  KEY field_value (field_value),
  KEY snmp_query_id (snmp_query_id),
  KEY present (present)
) ENGINE=MyISAM;

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
  PRIMARY KEY  (host_id,snmp_query_id),
  KEY host_id (host_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `host_snmp_query`
--

INSERT INTO host_snmp_query VALUES (1,6,'dskDevice','|query_dskDevice|',0);

--
-- Table structure for table `host_template`
--

CREATE TABLE host_template (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `host_template`
--

INSERT INTO host_template VALUES (1,'4855b0e3e553085ed57219690285f91f','Generic SNMP-enabled Host');
INSERT INTO host_template VALUES (3,'07d3fe6a52915f99e642d22e27d967a4','ucd/net SNMP Host');
INSERT INTO host_template VALUES (4,'4e5dc8dd115264c2e9f3adb725c29413','Karlnet Wireless Bridge');
INSERT INTO host_template VALUES (5,'cae6a879f86edacb2471055783bec6d0','Cisco Router');
INSERT INTO host_template VALUES (6,'9ef418b4251751e09c3c416704b01b01','Netware 4/5 Server');
INSERT INTO host_template VALUES (7,'5b8300be607dce4f030b026a381b91cd','Windows 2000/XP Host');
INSERT INTO host_template VALUES (8,'2d3e47f416738c2d22c87c40218cc55e','Local Linux Machine');

--
-- Table structure for table `host_template_graph`
--

CREATE TABLE host_template_graph (
  host_template_id mediumint(8) unsigned NOT NULL default '0',
  graph_template_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (host_template_id,graph_template_id),
  KEY host_template_id (host_template_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `host_template_graph`
--

INSERT INTO host_template_graph VALUES (3,4);
INSERT INTO host_template_graph VALUES (3,11);
INSERT INTO host_template_graph VALUES (3,13);
INSERT INTO host_template_graph VALUES (5,18);
INSERT INTO host_template_graph VALUES (6,14);
INSERT INTO host_template_graph VALUES (6,16);
INSERT INTO host_template_graph VALUES (6,17);
INSERT INTO host_template_graph VALUES (6,30);
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
  PRIMARY KEY  (host_template_id,snmp_query_id),
  KEY host_template_id (host_template_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `host_template_snmp_query`
--

INSERT INTO host_template_snmp_query VALUES (1,1);
INSERT INTO host_template_snmp_query VALUES (3,1);
INSERT INTO host_template_snmp_query VALUES (3,2);
INSERT INTO host_template_snmp_query VALUES (4,1);
INSERT INTO host_template_snmp_query VALUES (4,3);
INSERT INTO host_template_snmp_query VALUES (5,1);
INSERT INTO host_template_snmp_query VALUES (6,1);
INSERT INTO host_template_snmp_query VALUES (6,4);
INSERT INTO host_template_snmp_query VALUES (6,7);
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
  PRIMARY KEY  (`id`),
  KEY `status` (`status`),
  KEY `directory` (`directory`)
) ENGINE=MyISAM;

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
  PRIMARY KEY  (`id`),
  KEY `hook` (`hook`),
  KEY `status` (`status`)
) ENGINE=MyISAM;

--
-- Table structure for table `plugin_realms`
--

CREATE TABLE `plugin_realms` (
  `id` int(8) NOT NULL auto_increment,
  `plugin` varchar(32) NOT NULL default '',
  `file` text NOT NULL,
  `display` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `plugin` (`plugin`)
) ENGINE=MyISAM;

--
-- Table structure for table `plugin_db_changes`
--

CREATE TABLE `plugin_db_changes` (
  `id` int(10) NOT NULL auto_increment,
  `plugin` varchar(16) NOT NULL default '',
  `table` varchar(64) NOT NULL default '',
  `column` varchar(64) NOT NULL,
  `method` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `plugin` (`plugin`),
  KEY `method` (`method`)
) ENGINE=MyISAM;

REPLACE INTO `plugin_realms` VALUES (1, 'internal', 'plugins.php', 'Plugin Management');
INSERT INTO `plugin_hooks` VALUES (1, 'internal', 'config_arrays', '', 'plugin_config_arrays', 1);
INSERT INTO `plugin_hooks` VALUES (2, 'internal', 'draw_navigation_text', '', 'plugin_draw_navigation_text', 1);

--
-- Table structure for table `poller`
--

CREATE TABLE poller (
  id smallint(5) unsigned NOT NULL auto_increment,
  hostname varchar(250) NOT NULL default '',
  ip_address int(11) unsigned NOT NULL default '0',
  last_update datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Table structure for table `poller_command`
--

CREATE TABLE poller_command (
  poller_id smallint(5) unsigned NOT NULL default '0',
  time datetime NOT NULL default '0000-00-00 00:00:00',
  action tinyint(3) unsigned NOT NULL default '0',
  command varchar(200) NOT NULL default '',
  PRIMARY KEY  (poller_id,action,command)
) ENGINE=MyISAM;

--
-- Table structure for table `poller_item`
--

CREATE TABLE poller_item (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  poller_id smallint(5) unsigned NOT NULL default '0',
  host_id mediumint(8) unsigned NOT NULL default '0',
  action tinyint(2) unsigned NOT NULL default '1',
  present tinyint NOT NULL DEFAULT '1',
  hostname varchar(250) NOT NULL default '',
  snmp_community varchar(100) NOT NULL default '',
  snmp_version tinyint(1) unsigned NOT NULL default '0',
  snmp_username varchar(50) NOT NULL default '',
  snmp_password varchar(50) NOT NULL default '',
  snmp_auth_protocol varchar(5) NOT NULL default '',
  snmp_priv_passphrase varchar(200) NOT NULL default '',
  snmp_priv_protocol varchar(6) NOT NULL default '',
  snmp_context varchar(64) default '',
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
  PRIMARY KEY  (local_data_id,rrd_name),
  KEY local_data_id (local_data_id),
  KEY host_id (host_id),
  KEY rrd_next_step (rrd_next_step),
  KEY action (action),
  KEY present (present)
) ENGINE=MyISAM;

--
-- Table structure for table `poller_output`
--

CREATE TABLE poller_output (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  time datetime NOT NULL default '0000-00-00 00:00:00',
  output text NOT NULL,
  PRIMARY KEY (local_data_id,rrd_name,time) /*!50060 USING BTREE */
) ENGINE=MyISAM;

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
  PRIMARY KEY  (host_id,data_query_id,arg1),
  KEY present (present)
) ENGINE=MyISAM;

--
-- Table structure for table `poller_time`
--

CREATE TABLE poller_time (
  id mediumint(8) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  poller_id smallint(5) unsigned NOT NULL default '0',
  start_time datetime NOT NULL default '0000-00-00 00:00:00',
  end_time datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Table structure for table `rra`
--

CREATE TABLE rra (
  id mediumint(8) unsigned NOT NULL auto_increment,
  hash varchar(32) NOT NULL default '',
  name varchar(100) NOT NULL default '',
  x_files_factor double NOT NULL default '0.1',
  steps mediumint(8) default '1',
  rows int(12) NOT NULL default '600',
  timespan int(12) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `rra`
--

INSERT INTO rra VALUES (1,'c21df5178e5c955013591239eb0afd46','Daily (5 Minute Average)',0.5,1,600,86400);
INSERT INTO rra VALUES (2,'0d9c0af8b8acdc7807943937b3208e29','Weekly (30 Minute Average)',0.5,6,700,604800);
INSERT INTO rra VALUES (3,'6fc2d038fb42950138b0ce3e9874cc60','Monthly (2 Hour Average)',0.5,24,775,2678400);
INSERT INTO rra VALUES (4,'e36f3adb9f152adfa5dc50fd2b23337e','Yearly (1 Day Average)',0.5,288,797,33053184);
INSERT INTO rra VALUES (5,'283ea2bf1634d92ce081ec82a634f513','Hourly (1 Minute Average)',0.5,1,500,14400);

--
-- Table structure for table `rra_cf`
--

CREATE TABLE rra_cf (
  rra_id mediumint(8) unsigned NOT NULL default '0',
  consolidation_function_id smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (rra_id,consolidation_function_id),
  KEY rra_id (rra_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `rra_cf`
--

INSERT INTO rra_cf VALUES (1,1);
INSERT INTO rra_cf VALUES (1,3);
INSERT INTO rra_cf VALUES (2,1);
INSERT INTO rra_cf VALUES (2,3);
INSERT INTO rra_cf VALUES (3,1);
INSERT INTO rra_cf VALUES (3,3);
INSERT INTO rra_cf VALUES (4,1);
INSERT INTO rra_cf VALUES (4,3);
INSERT INTO rra_cf VALUES (5,1);
INSERT INTO rra_cf VALUES (5,3);

--
-- Table structure for table `settings`
--

CREATE TABLE settings (
  name varchar(50) NOT NULL default '',
  value varchar(255) NOT NULL default '',
  PRIMARY KEY  (name)
) ENGINE=MyISAM;

--
-- Dumping data for table `settings`
--


--
-- Table structure for table `settings_graphs`
--

CREATE TABLE settings_graphs (
  user_id smallint(8) unsigned NOT NULL default '0',
  name varchar(50) NOT NULL default '',
  value varchar(255) NOT NULL default '',
  PRIMARY KEY  (user_id,name)
) ENGINE=MyISAM;

--
-- Dumping data for table `settings_graphs`
--


--
-- Table structure for table `settings_tree`
--

CREATE TABLE settings_tree (
  user_id mediumint(8) unsigned NOT NULL default '0',
  graph_tree_item_id mediumint(8) unsigned NOT NULL default '0',
  status tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (user_id,graph_tree_item_id)
) ENGINE=MyISAM;

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
  PRIMARY KEY  (id),
  KEY name (name)
) ENGINE=MyISAM;

--
-- Dumping data for table `snmp_query`
--

INSERT INTO snmp_query VALUES (1,'d75e406fdeca4fcef45b8be3a9a63cbc','<path_cacti>/resource/snmp_queries/interface.xml','SNMP - Interface Statistics','Queries a host for a list of monitorable interfaces',0,2);
INSERT INTO snmp_query VALUES (2,'3c1b27d94ad208a0090f293deadde753','<path_cacti>/resource/snmp_queries/net-snmp_disk.xml','ucd/net -  Get Monitored Partitions','Retrieves a list of monitored partitions/disks from a net-snmp enabled host.',0,2);
INSERT INTO snmp_query VALUES (3,'59aab7b0feddc7860002ed9303085ba5','<path_cacti>/resource/snmp_queries/kbridge.xml','Karlnet - Wireless Bridge Statistics','Gets information about the wireless connectivity of each station from a Karlnet bridge.',0,2);
INSERT INTO snmp_query VALUES (4,'ad06f46e22e991cb47c95c7233cfaee8','<path_cacti>/resource/snmp_queries/netware_disk.xml','Netware - Get Available Volumes','Retrieves a list of volumes from a Netware server.',0,2);
INSERT INTO snmp_query VALUES (6,'8ffa36c1864124b38bcda2ae9bd61f46','<path_cacti>/resource/script_queries/unix_disk.xml','Unix - Get Mounted Partitions','Queries a list of mounted partitions on a unix-based host with the',0,11);
INSERT INTO snmp_query VALUES (7,'30ec734bc0ae81a3d995be82c73f46c1','<path_cacti>/resource/snmp_queries/netware_cpu.xml','Netware - Get Processor Information','Gets information about running processors in a Netware server',0,2);
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
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

--
-- Dumping data for table `snmp_query_graph`
--

INSERT INTO snmp_query_graph VALUES (2,'a4b829746fb45e35e10474c36c69c0cf',1,'In/Out Errors/Discarded Packets',22);
INSERT INTO snmp_query_graph VALUES (3,'01e33224f8b15997d3d09d6b1bf83e18',1,'In/Out Non-Unicast Packets',24);
INSERT INTO snmp_query_graph VALUES (4,'1e6edee3115c42d644dbd014f0577066',1,'In/Out Unicast Packets',23);
INSERT INTO snmp_query_graph VALUES (6,'da43655bf1f641b07579256227806977',2,'Available/Used Disk Space',3);
INSERT INTO snmp_query_graph VALUES (7,'1cc468ef92a5779d37a26349e27ef3ba',3,'Wireless Levels',5);
INSERT INTO snmp_query_graph VALUES (8,'bef2dc94bc84bf91827f45424aac8d2a',3,'Wireless Transmissions',6);
INSERT INTO snmp_query_graph VALUES (9,'ab93b588c29731ab15db601ca0bc9dec',1,'In/Out Bytes (64-bit Counters)',25);
INSERT INTO snmp_query_graph VALUES (10,'5a5ce35edb4b195cbde99fd0161dfb4e',4,'Volume Information (free, freeable space)',19);
INSERT INTO snmp_query_graph VALUES (11,'c1c2cfd33eaf5064300e92e26e20bc56',4,'Directory Information (total/available entries)',20);
INSERT INTO snmp_query_graph VALUES (13,'ae34f5f385bed8c81a158bf3030f1089',1,'In/Out Bits',2);
INSERT INTO snmp_query_graph VALUES (14,'1e16a505ddefb40356221d7a50619d91',1,'In/Out Bits (64-bit Counters)',2);
INSERT INTO snmp_query_graph VALUES (15,'a0b3e7b63c2e66f9e1ea24a16ff245fc',6,'Available Disk Space',21);
INSERT INTO snmp_query_graph VALUES (16,'d1e0d9b8efd4af98d28ce2aad81a87e7',1,'In/Out Bytes',25);
INSERT INTO snmp_query_graph VALUES (17,'f6db4151aa07efa401a0af6c9b871844',7,'Get Processor Utilization',15);
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
  PRIMARY KEY  (snmp_query_graph_id,data_template_id,data_template_rrd_id),
  KEY data_template_rrd_id (data_template_rrd_id),
  KEY snmp_query_graph_id (snmp_query_graph_id)
) ENGINE=MyISAM;

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
INSERT INTO snmp_query_graph_rrd VALUES (7,7,8,'kbWirelessStationExclHellos');
INSERT INTO snmp_query_graph_rrd VALUES (7,8,9,'kbWirelessStationExclHellos');
INSERT INTO snmp_query_graph_rrd VALUES (8,10,11,'kbWirelessStationExclHellos');
INSERT INTO snmp_query_graph_rrd VALUES (8,9,10,'kbWirelessStationExclHellos');
INSERT INTO snmp_query_graph_rrd VALUES (9,41,55,'ifHCOutOctets');
INSERT INTO snmp_query_graph_rrd VALUES (9,41,54,'ifHCInOctets');
INSERT INTO snmp_query_graph_rrd VALUES (10,35,38,'nwVolSize');
INSERT INTO snmp_query_graph_rrd VALUES (10,35,40,'nwVolFreeable');
INSERT INTO snmp_query_graph_rrd VALUES (10,35,39,'nwVolFree');
INSERT INTO snmp_query_graph_rrd VALUES (11,36,42,'nwVolTotalDirEntries');
INSERT INTO snmp_query_graph_rrd VALUES (11,36,43,'nwVolUsedDirEntries');
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
INSERT INTO snmp_query_graph_rrd VALUES (17,42,76,'nwhrProcessorUtilization');
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
  PRIMARY KEY  (id),
  KEY snmp_query_graph_id (snmp_query_graph_id),
  KEY data_template_id (data_template_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `snmp_query_graph_rrd_sv`
--

INSERT INTO snmp_query_graph_rrd_sv VALUES (10,'5d3a8b2f4a454e5b0a1494e00fe7d424',6,3,1,'name','|host_description| - Partition - |query_dskDevice|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (11,'d0b49af67a83c258ef1eab3780f7b3dc',7,7,1,'name','|host_description| - Wireless Noise Level - |query_kbWirelessStationName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (12,'bf6b966dc369f3df2ea640a90845e94c',7,8,1,'name','|host_description| - Wireless Signal Level - |query_kbWirelessStationName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (13,'5c3616603a7ac9d0c1cb9556b377a74f',8,10,1,'name','|host_description| - Wireless Re-Transmissions - |query_kbWirelessStationName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (14,'080f0022f77044a512b083e3a8304e8b',8,9,1,'name','|host_description| - Wireless Transmissions - |query_kbWirelessStationName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (30,'8132fa9c446e199732f0102733cb1714',11,36,1,'name','|host_description| - Directories - |query_nwVolPhysicalName|');
INSERT INTO snmp_query_graph_rrd_sv VALUES (29,'8fc9a94a5f6ef902a3de0fa7549e7476',10,35,1,'name','|host_description| - Volumes - |query_nwVolPhysicalName|');
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
INSERT INTO snmp_query_graph_rrd_sv VALUES (92,'42277993a025f1bfd85374d6b4deeb60',17,42,1,'name','|host_description| - CPU Utilization - CPU|query_nwhrProcessorNum|');
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
  PRIMARY KEY  (id),
  KEY snmp_query_graph_id (snmp_query_graph_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `snmp_query_graph_sv`
--

INSERT INTO snmp_query_graph_sv VALUES (7,'437918b8dcd66a64625c6cee481fff61',6,1,'title','|host_description| - Disk Space - |query_dskPath|');
INSERT INTO snmp_query_graph_sv VALUES (5,'2ddc61ff4bd9634f33aedce9524b7690',7,1,'title','|host_description| - Wireless Levels (|query_kbWirelessStationName|)');
INSERT INTO snmp_query_graph_sv VALUES (6,'c72e2da7af2cdbd6b44a5eb42c5b4758',8,1,'title','|host_description| - Wireless Transmissions (|query_kbWirelessStationName|)');
INSERT INTO snmp_query_graph_sv VALUES (11,'a412c5dfa484b599ec0f570979fdbc9e',10,1,'title','|host_description| - Volume Information - |query_nwVolPhysicalName|');
INSERT INTO snmp_query_graph_sv VALUES (12,'48f4792dd49fefd7d640ec46b1d7bdb3',11,1,'title','|host_description| - Directory Information - |query_nwVolPhysicalName|');
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
INSERT INTO snmp_query_graph_sv VALUES (40,'d99f8db04fd07bcd2260d246916e03da',17,1,'title','|host_description| - CPU Utilization - CPU|query_nwhrProcessorNum|');
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
  password varchar(50) NOT NULL default '0',
  realm mediumint(8) NOT NULL default '0',
  full_name varchar(100) default '0',
  must_change_password char(2) default NULL,
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
  PRIMARY KEY  (id),
  KEY username (username),
  KEY realm (realm),
  KEY enabled (enabled)
) ENGINE=MyISAM;

--
-- Dumping data for table `user_auth`
--

INSERT INTO user_auth VALUES (1,'admin','21232f297a57a5a743894a0e4a801fc3',0,'Administrator','on','on','on','on','on',1,1,1,1,1,'on');
INSERT INTO user_auth VALUES (3,'guest','43e9a4ab75570f5b',0,'Guest Account','on','on','on','on','on',3,1,1,1,1,'');

--
-- Table structure for table `user_auth_perms`
--

CREATE TABLE user_auth_perms (
  user_id mediumint(8) unsigned NOT NULL default '0',
  item_id mediumint(8) unsigned NOT NULL default '0',
  type tinyint(2) unsigned NOT NULL default '0',
  PRIMARY KEY  (user_id,item_id,type),
  KEY user_id (user_id,type)
) ENGINE=MyISAM;

--
-- Dumping data for table `user_auth_perms`
--


--
-- Table structure for table `user_auth_realm`
--

CREATE TABLE user_auth_realm (
  realm_id mediumint(8) unsigned NOT NULL default '0',
  user_id mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (realm_id,user_id),
  KEY user_id (user_id)
) ENGINE=MyISAM;

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
INSERT INTO user_auth_realm VALUES (101,1);

--
-- Table structure for table `user_log`
--

CREATE TABLE user_log (
  username varchar(50) NOT NULL default '0',
  user_id mediumint(8) NOT NULL default '0',
  time datetime NOT NULL default '0000-00-00 00:00:00',
  result tinyint(1) NOT NULL default '0',
  ip varchar(40) NOT NULL default '',
  PRIMARY KEY  (username,user_id,time),
  KEY username (username),
  KEY user_id (user_id)
) ENGINE=MyISAM;

--
-- Dumping data for table `user_log`
--


--
-- Table structure for table `version`
--

CREATE TABLE version (
  cacti char(20) default NULL
) ENGINE=MyISAM;

--
-- Dumping data for table `version`
--

INSERT INTO version VALUES ('new_install');
