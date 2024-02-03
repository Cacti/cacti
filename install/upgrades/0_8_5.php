<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_5() {
	/* bug#109 */
	db_install_execute("UPDATE host_snmp_cache set field_name='ifDescr' where field_name='ifDesc' and snmp_query_id=1;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,'ifDesc','ifDescr') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4 or snmp_query_graph_id=20 or snmp_query_graph_id=21 or snmp_query_graph_id=22);");
	db_install_execute("UPDATE snmp_query_graph_sv set text = REPLACE(text,'ifDesc','ifDescr') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4 or snmp_query_graph_id=20 or snmp_query_graph_id=21 or snmp_query_graph_id=22);");

	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=1;");
	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=2;");
	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=38;");
	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=39;");
	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=40;");
	db_install_execute("UPDATE data_template_data set name = REPLACE(name,'ifDesc','ifDescr') where data_template_id=41;");

	$data_templates_results = db_install_fetch_assoc("select id from data_template_data where (data_template_id=1 or data_template_id=2 or data_template_id=38 or data_template_id=39 or data_template_id=40 or data_template_id=41);");
	$data_templates         = $data_templates_results['data'];

	if (cacti_sizeof($data_templates) > 0) {
		foreach ($data_templates as $item) {
			db_install_execute("UPDATE data_input_data set value='ifDescr' where value='ifDesc' and data_template_data_id=?",array($item["id"]));
		}
	}

	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=22;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=24;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=1;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=2;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=31;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=32;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=25;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=33;");
	db_install_execute("UPDATE graph_templates_graph set title = REPLACE(title,'ifDesc','ifDescr') where graph_template_id=23;");

	if (!db_table_exists('host_graph')) {
		db_install_execute("CREATE TABLE `host_graph` (`host_id` mediumint(8) unsigned NOT NULL default '0', `graph_template_id` mediumint(8) unsigned NOT NULL default '0', PRIMARY KEY  (`host_id`,`graph_template_id`))");
	}

	/* typo */
	db_install_execute("UPDATE settings set name='snmp_version' where name='smnp_version';");

	/* allow 'Unit Exponent Value' = 0 */
	db_install_execute("ALTER TABLE `graph_templates_graph` CHANGE `unit_exponent_value` `unit_exponent_value` VARCHAR( 5 ) NOT NULL;");
	db_install_execute("UPDATE graph_templates_graph set unit_exponent_value='' where unit_exponent_value='0';");

	/* allow larger rrd steps */
	db_install_execute("ALTER TABLE `data_template_data` CHANGE `rrd_step` `rrd_step` MEDIUMINT( 8 ) UNSIGNED DEFAULT '0' NOT NULL;");
}
