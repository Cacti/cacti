<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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

function upgrade_to_0_8_4() {
	/*
	ROP TABLE `user_realm` 
	DROP TABLE `user_realm_filename` 
	update host set hostname=management_ip;
	ALTER TABLE `data_input_data_cache` CHANGE `management_ip` `hostname` VARCHAR( 250 ) NOT NULL 
	ALTER TABLE `host` ADD `snmp_port` MEDIUMINT( 5 ) UNSIGNED DEFAULT '161' NOT NULL AFTER `snmp_password` ;
	ALTER TABLE `host` ADD `snmp_timeout` MEDIUMINT( 8 ) UNSIGNED DEFAULT '500' NOT NULL AFTER `snmp_port` ;
	ALTER TABLE `data_input_data_cache` ADD `snmp_timeout` MEDIUMINT( 8 ) UNSIGNED NOT NULL AFTER `snmp_port` ;
	*/
}

?>
