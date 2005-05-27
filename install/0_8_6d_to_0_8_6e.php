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

function upgrade_to_0_8_6e() {
	/* changes for logarithmic rrd files */
	db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` CHANGE `rrd_minimum` `rrd_minimum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` CHANGE `rrd_maximum` `rrd_maximum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` CHANGE `upper_limit` `upper_limit` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` CHANGE `lower_limit` `lower_limit` VARCHAR( 20 ) NOT NULL;");}
?>