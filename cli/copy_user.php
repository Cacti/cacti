#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

$parms = $_SERVER['argv'];
array_shift($parms);

$data = array('tfa_enabled' => '', 'tfa_secret' => '');
$salt = '';
$new_user = '';
$template_user = '';
if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch($arg) {
			case '-f':
			case '-F':
				$data['full_name'] = validate_field('Full Name', $value);
				break;

			case '-e':
			case '-E':
				$data['email_address'] = validate_field('Email', $value);
				break;

			case '-l':
			case '-L':
				$data['locked'] = validate_boolean('Locked', $value);
				break;

			case '-p':
			case '-P':
				$salt = $value;
				break;

			case '-r':
			case '-R':
				$data['must_change_password'] = validate_boolean('Require Change', $value);
				break;

			case '-t':
			case '-T':
				$data['tfa_enabled'] = validate_boolean('Two-Factor Enabled', 'on');
				$data['tfa_secret']  = validate_field('Two-Factor Secret', $value);
				break;

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

			default:
				if (empty($template_user)) {
					$template_user = $arg;
				} elseif (empty($new_user)) {
					$new_user = $arg;
				} else {
					print "ERROR: Invalid Argument: ($arg)\n\n";
					display_help();
					exit(1);
				}
		}

	}
}

if (empty($template_user) || empty($new_user)) {
	display_help();
	exit(1);
}

print 'Template User..: ' . $template_user . PHP_EOL;
print 'New User.......: ' . $new_user . PHP_EOL;

if (empty($salt)) {
	$salt = substr(sha1(microtime()),-8);
}

$data['password'] = compat_password_hash($salt . '_' . $new_user, PASSWORD_DEFAULT);

/* Check that user exists */
$user_auth = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . $template_user . "' AND realm = 0");
if (! isset($user_auth)) {
	die(PHP_EOL . "Error: Template user does not exist!" . PHP_EOL . PHP_EOL);
}

print PHP_EOL . 'Copying User...: ' . (cacti_sizeof($data) - 3) . ' extra option(s)';
print PHP_EOL . '        Salt...: ' . $salt . PHP_EOL;

if (user_copy($template_user, $new_user, 0, 0, false, $data) === false) {
	die(PHP_EOL . 'Error: User not copied!' . PHP_EOL . PHP_EOL);
}

$user_auth = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . $new_user . "' AND realm = 0");
if (! isset($user_auth)) {
	die(PHP_EOL . 'Error: User missing!' . PHP_EOL . PHP_EOL);
}
print PHP_EOL . 'User copied successfully' . PHP_EOL;

function validate_field($field, $value) {
	if (empty($value)) {
		print "ERROR: Value for '$field' cannot be blank" . PHP_EOL . PHP_EOL;
		display_help();
		exit(1);
	}
	return $value;
}

function validate_boolean($field, $value) {
	$value = empty($value) ? '' : strtolower($value);
	if ($value == 'on' || $value == 'yes') {
		$result = true;
	} elseif ($value == 'off' || $value == 'no') {
		$result = false;
	} else {
		print PHP_EOL . "ERROR: Value for '$field' must be yes/no or on/off" . PHP_EOL . PHP_EOL;
		display_help();
		exit(1);
	}

	return $value;
}
/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Copy User Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print 'usage: copy_user.php [-h|-v] [-e=<email>] [-f=<full name>] [-l=<yes/no>] [-p=<salt>] ' . PHP_EOL;
	print '                     [-r=<yes/no>] [-t=<secret>] <template user> <new user>' . PHP_EOL . PHP_EOL;
	print 'A utility to copy on local Cacti user and their settings to a new one.' . PHP_EOL . PHP_EOL;
	print 'NOTE: It is highly recommended that you use the web interface to copy users as' . PHP_EOL;
	print 'this script will only copy Local Cacti users.' . PHP_EOL . PHP_EOL;
	print 'Required options:' . PHP_EOL;
	print '     <template user>        the user id to copy' . PHP_EOL;
	print '     <new user>             the user id to create' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '     -e=<email address>     the email address to set' . PHP_EOL;
	print '     -f=<full name>         the full name to set' . PHP_EOL;
	print '     -l=<yes/no>            whether new user should be locked'  . PHP_EOL;
	print '     -p=<salt>              the salt to prefix to hashed username' . PHP_EOL;
	print '     -r=<yes/no>            whether new password must be changed' . PHP_EOL;
	print '     -t=<secret>            the secret to use for TFA' . PHP_EOL . PHP_EOL;
	print 'NOTE: When copying a user using this script, TFA is automatically disabled' . PHP_EOL . PHP_EOL;
}
