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

function upgrade_to_1_1_6() {
	db_install_execute('ALTER TABLE `data_input`
		MODIFY COLUMN `input_string` varchar(512) default NULL'
	);

	db_install_add_key('data_input', 'key', 'name_type_id', array('name', 'type_id'));
	db_install_add_key('snmp_query_graph', 'key', 'graph_template_id_name', array('graph_template_id', 'name'));

	if (!db_index_exists('graph_templates', 'multiple_name')) {
		db_install_execute(
			"ALTER TABLE `graph_templates`
				ADD `multiple` CHAR(2) NOT NULL DEFAULT '' AFTER `name`,
				ADD KEY `multiple_name` (`multiple`, `name`(171))"
		);
	}

	db_install_execute("UPDATE graph_templates
		SET multiple = 'on'
		WHERE hash = '010b90500e1fc6a05abfd542940584d0'"
	);

	db_install_execute("ALTER TABLE poller_output
		MODIFY COLUMN output VARCHAR(512) NOT NULL default '',
		ENGINE=MEMORY"
	);

	db_install_add_key('graph_templates_gprint', 'key', 'name', array('name'));

	db_install_add_key('data_source_profiles', 'key', 'name', array('name'));
	db_install_add_key('cdef', 'key', 'name', array('name'));
	db_install_add_key('vdef', 'key', 'name', array('name'));
	db_install_add_key('poller', 'key', 'name', array('name'));
	db_install_add_key('host_template', 'key', 'name', array('name'));
	db_install_add_key('data_template', 'key', 'name', array('name'));
	db_install_add_key('automation_tree_rules', 'key', 'name', array('name'));
	db_install_add_key('automation_graph_rules', 'key', 'name', array('name'));
	db_install_add_key('graph_templates', 'key', 'name', array('name'));
	db_install_add_key('graph_tree', 'key', 'name', array('name'));
	db_install_add_key('snmp_query_graph', 'key', 'snmp_query_id_name', array('snmp_query_id', 'name'));

	db_install_execute("REPLACE INTO settings (name, value) VALUES ('max_display_rows', '1000')");
}
