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

error_reporting(0);

/* get access to the database and open a session */
include(dirname(__FILE__) . '/../include/cli_check.php');

$user_logins_valid        = 'U';
$user_logins_invalid      = 'U';
$user_counter_active      = 'U';
$user_counter_sleeping    = 'U';
$session_counter_active   = 'U';
$session_counter_sleeping = 'U';
$session_counter_garbage  = 'U';

$active_interval = 600; // Ten minutes

/* determine the number of invalid/valid user logins during the last poller interval */
$user_logins_valid = db_fetch_cell("SELECT COUNT(*) AS user_logins
	FROM user_log
	WHERE UNIX_TIMESTAMP(`time`) BETWEEN UNIX_TIMESTAMP() - $active_interval AND UNIX_TIMESTAMP()
	AND user_id > 0
	AND `result` IN (1, 2)");

$user_logins_invalid = db_fetch_cell("SELECT COUNT(*) AS user_logins
	FROM user_log
	WHERE UNIX_TIMESTAMP(`time`) BETWEEN UNIX_TIMESTAMP() - $active_interval AND UNIX_TIMESTAMP()
	AND user_id = 0
	AND `result` IN (0, 3)");

$active_interval_end      = time();
$active_interval_begin    = $active_interval_end - $active_interval;

/* determine the number of active/valid sessions during the last poller interval */
if ($cacti_db_session == true) {
	$session_counter_active = db_fetch_cell_prepared('SELECT count(*)
		FROM sessions
		WHERE access >= ?',
		array($active_interval_begin));

	$session_counter_sleeping = db_fetch_cell_prepared('SELECT count(*)
		FROM sessions
		WHERE access < ?',
		array($active_interval_begin));

	// These stats have little use when using the database session
	$user_counter_active     = $session_counter_active;
	$user_counter_sleeping   = $session_counter_sleeping;

	// There is no garbage users when using database sessions
	$session_counter_garbage = 0;
} else {
	$session_save_path   = get_session_save_path();
	$session_dir_handle  = @opendir($session_save_path);
	$session_maxlifetime = ini_get("session.gc_maxlifetime");

	if ($session_dir_handle) {
		$user_ids_active          = array();
		$user_ids_sleeping        = array();
		$session_counter_active   = 0;
		$session_counter_sleeping = 0;
		$session_counter_garbage  = 0;

		while (false !== ($filename = readdir($session_dir_handle))) {
			/* a real user session should be greater than 400 Bytes */
			if (strpos($filename, 'sess_') !== false && filesize($session_save_path . '/' . $filename)> 400) {
				$session = @file_get_contents($session_save_path . '/' . $filename);

				/* first off check if we are allowed to read the session
				 * file. Then we are only interested in sessions of
				 * authenticated Cacti users
				 */
				if ($session !== false && strpos($session, 'cacti_cwd') !== false && preg_match('/sess_user_id\|s:[0-9]*:\"[0-9]*\"/', $session, $match)) {
					$session_user_id = substr($match[0], strpos($match[0], ':"')+2, -1);
					/* due to the fact that ATIME could be unsupported/disabled we have to use MTIME instead */
					$mtime = filemtime($session_save_path . '/' . $filename);

					/* determine active user sessions
					 * Sessions with a MTIME higher than the end of the
					 * poller interval have to be counted too or we
					 * won't see real active users.
					 */
					if ($mtime >= $active_interval_begin) {
						/* incease active session counter */
						$session_counter_active++;

						/* count all active users */
						if (false === ($key = array_search($session_user_id, $user_ids_active))) {
							$user_ids_active[] = $session_user_id;
						}

						/* if the same user has more than one session and this one is active then the user is not sleeping */
						if (false !== ($key = array_search($session_user_id, $user_ids_sleeping))) {
							unset($user_ids_sleeping[$key]);
						}

						continue;
					}

					/* determine user sessions which are sleeping */
					if ($mtime >= ($active_interval_begin - $session_maxlifetime) & $mtime < $active_interval_begin) {
						/* increase sleeping session counter */
						$session_counter_sleeping++;

						/* count all sleeping users if they have no active sessions */
						if (!in_array($session_user_id, $user_ids_active) & !in_array($session_user_id, $user_ids_sleeping)) {
							$user_ids_sleeping[] = $session_user_id;
						}

						continue;
					}

					/* count all user session declared as garbage */
					if ($mtime < ($active_interval_begin - $session_maxlifetime)) {
						$session_counter_garbage++;
					}
				}
			}
		}

		$user_counter_active   = cacti_count($user_ids_active);
		$user_counter_sleeping = cacti_count($user_ids_sleeping);
	}

	/* close directory handle and destroy this session */
	@closedir($session_dir_handle);
	@session_destroy();
}

print
	'valid:'      . $user_logins_valid .
	' invalid:'   . $user_logins_invalid .
	' active:'    . $session_counter_active .
	' sleeping:'  . $session_counter_sleeping .
	' garbage:'   . $session_counter_garbage .
	' uactive:'   . $user_counter_active .
	' usleeping:' . $user_counter_sleeping;

function get_session_save_path() {
	if (session_save_path() !== '') {
		/* if default temp path is not in use */
		return realpath(session_save_path());
	} elseif (function_exists('sys_get_temp_dir')) {
		/* this requires PHP > 5.2.1 */
		return realpath(sys_get_temp_dir());
	} elseif ($temp=getenv('TMP') | $temp=getenv('TEMP') | $temp=getenv('TMPDIR')) {
		/* try to use environment variables */
		return $temp;
	} else {
		/* try to create a temp file */
		$temp=tempnam(__FILE__,'');

		if (file_exists($temp)) {
			unlink($temp);
			return dirname($temp);
		}

		return false;
	}
}

