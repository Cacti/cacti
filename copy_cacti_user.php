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

if (empty($_SERVER["argv"][2])) {
	die("\nSyntax:\n php copy_cacti_user.php <template user> <new user>\n\n");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/include/config.php");
include_once($config["base_path"] . "/lib/auth.php");

$template_user = $_SERVER["argv"][1];
$new_user = $_SERVER["argv"][2];

print "Cacti User Copy Utility\n";
print "Template User: " . $template_user . "\n";
print "New User: " . $new_user . "\n";

/* Check that user exists */
$user_auth = db_fetch_row("select * from user_auth where username = '$template_user'");
if (! isset($user_auth)) {
	die("Error: Template user does not exist!\n\n");
}

print "\nCopying User...\n";

@user_copy($template_user, $new_user);

$user_auth = db_fetch_row("select * from user_auth where username = '$new_user'");
if (! isset($user_auth)) {
	die("Error: User not copied!\n\n");
}

print "User copied...\n";




?>
