<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

global $plugin_hooks, $plugins_integrated, $plugins;
$plugin_hooks       = array();
$plugins            = array();
$plugins_integrated = array('snmpagent', 'clog', 'settings', 'boost', 'dsstats', 'watermark', 'ssl', 'ugroup', 'domains', 'jqueryskin', 'secpass', 'logrotate', 'realtime', 'rrdclean', 'nectar', 'aggregate', 'autom8', 'discovery', 'spikekill', 'superlinks', 'debug');
