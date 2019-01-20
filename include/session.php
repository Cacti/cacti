<?php

/*
 * Have Cacti use the database for PHP session storage.
 * This allows for easier distrubution of Web UI.
 */

// Don't run from the database if using the command line
if (php_sapi_name() == 'cli') {
	return;
}

function cacti_session_open() {
	// Cacti database is already active
	return true;
}

function cacti_session_close() {
	// Cacti database is not closed by sessions
	return true;
}

function cacti_session_read($id) {
	db_execute_prepared('UPDATE IGNORE sessions SET access = ? WHERE id = ?', array(time(), $id));
	$session = db_fetch_cell_prepared('SELECT data FROM sessions WHERE id = ?', array($id));

	// work with PHP 7.1
	if (empty($session)) {
		$session = '';
	}
	return $session;
}

function cacti_session_write($id, $data) {
	$access = time();
	return db_execute_prepared('REPLACE INTO sessions VALUES (?, ?, ?, ?)', array($id, $_SERVER['REMOTE_ADDR'], $access, $data));
}

function cacti_session_destroy($id) {
	return db_execute_prepared('DELETE FROM sessions WHERE id = ?', array($id));
}

function cacti_session_clean($max) {
	$old = time() - $max;
	return db_execute_prepared('DELETE FROM sessions WHERE access < ?', array($old));
}

// register database session handling
session_set_save_handler('cacti_session_open', 'cacti_session_close', 'cacti_session_read', 'cacti_session_write', 'cacti_session_destroy', 'cacti_session_clean');
register_shutdown_function('session_write_close');

