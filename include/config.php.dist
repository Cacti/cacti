<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* make sure these values reflect your actual database/host/user/password */

$database_type     = 'mysql';
$database_default  = 'cacti';
$database_hostname = 'localhost';
$database_username = 'cactiuser';
$database_password = 'cactiuser';
$database_port     = '3306';
$database_ssl      = false;

/* when the cacti server is a remote poller, then these entries point to
 * the main cacti server.  otherwise, these variables have no use. 
 * and must remain commented out. */

#$rdatabase_type     = 'mysql';
#$rdatabase_default  = 'cacti';
#$rdatabase_hostname = 'localhost';
#$rdatabase_username = 'cactiuser';
#$rdatabase_password = 'cactiuser';
#$rdatabase_port     = '3306';
#$rdatabase_ssl      = false;

/* the poller_id of this system.  set to '1' for the main cacti
 * web server.  otherwise, you this value should be the poller_id
 * for the remote poller. */

$poller_id = 1;

/* set the $url_path to point to the default URL of your cacti 
 * install ex: if your cacti install as at 
 * http://serverip/cacti/ this would be set to /cacti/.
*/

$url_path = '/cacti/';

/* default session name - session name must contain alpha characters */

$cacti_session_name = 'Cacti';

/* save sessions to a database for load balancing */

$cacti_db_session = false;

/* optional parameters to define scripts and resource paths.  these
 * variables become important when using remote poller installs
 * when the scripts and resource files are not in the main cacti
 * web server path. */

//$scripts_path = '/var/www/html/cacti/scripts';
//$resource_path = '/var/www/html/cacti/resource/';
