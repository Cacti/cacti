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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

/*
 * Have Cacti use the database for PHP session storage.
 * This allows for easier distribution of Web UI.
 */

// Don't run from the database if using the command line
if (php_sapi_name() == 'cli') {
	return;
}

function cacti_db_session_check() {
	if (!db_column_exists('sessions', 'user_id')) {
		db_execute('ALTER TABLE sessions
			ADD COLUMN user_id int unsigned NOT NULL default "0",
			ADD COLUMN start_time timestamp NOT NULL default current_timestamp,
			ADD COLUMN transactions int unsigned NOT NULL default "1"');
	}

	if (!db_column_exists('sessions', 'user_agent')) {
		db_execute('ALTER TABLE sessions
			ADD COLUMN user_agent VARCHAR(128) NOT NULL default "" AFTER user_id');
	}
}

function cacti_db_session_open($savePath = '', $sessionName = '') {
	// Cacti database is already active
	cacti_db_session_check();

	return true;
}

function cacti_db_session_close() {
	// Cacti database is not closed by sessions
	return true;
}

function cacti_db_session_read($id) {
	db_execute_prepared('UPDATE IGNORE sessions
		SET access = ?
		WHERE id = ?',
		array(time(), $id));

	$session = db_fetch_cell_prepared('SELECT data
		FROM sessions
		WHERE id = ?',
		array($id));

	// work with PHP 7.1
	if (empty($session)) {
		$session = '';
	}

	return $session;
}

function cacti_db_session_write($id, $data) {
	$access = time();

	cacti_db_session_check();

	if (!isset($_SESSION['sess_user_id'])) {
		session_decode($data);
	}

	if (isset($_SESSION['sess_user_id'])) {
		$user_id = $_SESSION['sess_user_id'];
	} else {
		$user_id = 0;
	}

	$client_addr = get_client_addr();
	$user_agent  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT']:'Unknown';

	if ($user_id > 0) {
		db_execute_prepared('INSERT INTO sessions
			(id, remote_addr, access, data, user_id, user_agent)
			VALUES (?, ?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				data = VALUES(data),
				access = VALUES(access),
				user_agent = VALUES(user_agent),
				transactions = transactions + 1',
			array($id, $client_addr, $access, $data, $user_id, $user_agent));
	} elseif (strpos($data, 'ses_user_id') !== false) {
		db_execute_prepared('INSERT INTO sessions
			(id, remote_addr, access, data, user_agent)
			VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				data = VALUES(data),
				access = VALUES(access),
				user_agent = VALUES(user_agent),
				transactions = transactions + 1',
			array($id, $client_addr, $access, $data, $user_agent));
	}

	return true;
}

function cacti_db_session_destroy($id) {
	db_execute_prepared('DELETE FROM sessions
		WHERE id = ?',
		array($id));

	return true;
}

function cacti_db_session_clean($max) {
	$old = time() - $max;

	db_execute_prepared('DELETE FROM sessions
		WHERE access < ?',
		array($old));

	return true;
}

// register database session handling
session_set_save_handler(
	'cacti_db_session_open',
	'cacti_db_session_close',
	'cacti_db_session_read',
	'cacti_db_session_write',
	'cacti_db_session_destroy',
	'cacti_db_session_clean'
);

register_shutdown_function('session_write_close');

