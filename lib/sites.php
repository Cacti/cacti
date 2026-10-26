<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

require_once __DIR__ . '/Sites/DatabaseSiteRepository.php';
require_once __DIR__ . '/Sites/SiteService.php';

/** Assemble the legacy database, notification, and timestamp dependencies. */
function site_service() : \Cacti\Sites\SiteService {
	return new \Cacti\Sites\SiteService(new \Cacti\Sites\DatabaseSiteRepository(), static function () : void {
		set_config_option('time_last_change_site', time());
		set_config_option('time_last_change_site_device', time());
	}, static function (int $id) : void {
		raise_message($id);
	});
}
