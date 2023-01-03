#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');

if (empty($_SERVER['argv'][1]) ){
	display_help();
	exit(1);
} else {
	switch($_SERVER['argv'][1]) {
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit(0);
	}
}

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

$template_user = $_SERVER['argv'][1];
$new_user      = $_SERVER['argv'][2];

print 'Template User: ' . $template_user . PHP_EOL;
print 'New User:      ' . $new_user . PHP_EOL;

/* Check that user exists */
$user_auth = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . $template_user . "' AND realm = 0");
if (! isset($user_auth)) {
	die("Error: Template user does not exist!" . PHP_EOL . PHP_EOL);
}

print PHP_EOL . 'Copying User...' . PHP_EOL;

if (user_copy($template_user, $new_user) === false) {
	die('Error: User not copied!' . PHP_EOL . PHP_EOL);
}

$user_auth = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . $new_user . "' AND realm = 0");
if (! isset($user_auth)) {
	die('Error: User not copied!' . PHP_EOL . PHP_EOL);
}

print "User copied..." . PHP_EOL;

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Copy User Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print 'usage: copy_user.php <template user> <new user>' . PHP_EOL . PHP_EOL;
	print 'A utility to copy on local Cacti user and their settings to a new one.' . PHP_EOL . PHP_EOL;
	print 'NOTE: It is highly recommended that you use the web interface to copy users as' . PHP_EOL;
	print 'this script will only copy Local Cacti users.' . PHP_EOL . PHP_EOL;
}
