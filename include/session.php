<?php

/*
 * Have Cacti use the database for PHP session storage.
 * This allows for easier distrubution of Web UI.
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

	if ($user_id > 0) {
		db_execute_prepared('INSERT INTO sessions
			(id, remote_addr, access, data, user_id)
			VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				data = VALUES(data),
				access = VALUES(access),
				transactions = transactions + 1',
			array($id, $client_addr, $access, $data, $user_id));
	} elseif (strpos($data, 'ses_user_id') !== false) {
		db_execute_prepared('INSERT INTO sessions
			(id, remote_addr, access, data)
			VALUES (?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				data = VALUES(data),
				access = VALUES(access),
				transactions = transactions + 1',
			array($id, $client_addr, $access, $data));
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

