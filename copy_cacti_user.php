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

/* do NOT run this script through a web browser */
if (! isset($_SERVER["argv"][0])) {
	die("This script is only meant to run at the command line.\n");
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
