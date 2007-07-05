#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__)."/../include/global.php");

if ($_SERVER["argc"] == 1) {
	usage();
	return 1;
}else{
	$groupName = '';
	$userId    = 0;

	$itemTypes = array('graph' => 1, 'tree' => 2, 'host' => 3, 'graph_template' => 4);

	$itemType  = 0;
	$itemId = 0;

	for ($i = 1; $i < $_SERVER["argc"]; $i++) {
		switch ($_SERVER["argv"][$i]) {
		case "--user-id":
			$i++;
			$userId = $_SERVER["argv"][$i];

			break;
		case "--group-name":
			$i++;
			$groupName = $_SERVER["argv"][$i];

			break;
		case "--item-type":
			$i++;
			$itemType = $itemTypes[$_SERVER["argv"][$i]];

			break;
		case "--item-id":
			$i++;
			$itemId = $_SERVER["argv"][$i];

			break;
		default:
			usage();
			return 1;
		}
	}

	if ($itemType == 0 || $itemId == 0) {
		usage();
		return 1;
	}

	$userIds = array();

	if (isset($groupName) && $groupName != '') {
		$users = db_fetch_assoc("select id from user_auth where oss_group = \"$groupName\"");

		foreach ($users as $u) {
			array_push($userIds, $u["id"]);
		}
	}elseif(isset($userId) && $userId > 0) {
		array_push($userIds, $userId);
	}

	foreach ($userIds as $id) {
		db_execute("replace into user_auth_perms (user_id, item_id, type) values ($id, $itemId, $itemType)");
	}
}

function usage() {
	echo "Usage:\n";
	echo "add_perms.php [ --group-name [Group Name] | --user-id [ID] ] --item-type --item-id\n\n";
	echo "Where item-type is one of: graph, tree, host or graph_template\n";
}

?>
