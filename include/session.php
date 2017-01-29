<?php

// Don't run from the database if using the command line
if (isset($_SERVER['argv'][0]) || (!isset($_SERVER['REQUEST_METHOD']) && !isset($_SERVER['REMOTE_ADDR']))) {
	return;
}

function _open() {
	// Base cacti database function
	return true;
} 

function _close() {
	// Base cacti database function
	return true;
}

function _read($id) {
	db_execute_prepared('UPDATE IGNORE sessions SET access = ? WHERE id = ?', array(time(), $id));
    return db_fetch_cell_prepared('SELECT data FROM sessions WHERE id = ?', array($id));     
}

function _write($id, $data) {
    $access = time();
	return db_execute_prepared('REPLACE INTO sessions VALUES (?, ?, ?, ?)', array($id, $_SERVER['REMOTE_ADDR'], $access, $data));
}

function _destroy($id) {
	return db_execute_prepared('DELETE FROM sessions WHERE id = ?', array($id));
}

function _clean($max) {
	$old = time() - $max;
	return db_execute_prepared('DELETE FROM sessions WHERE access < ?', array($old));
}

session_set_save_handler('_open', '_close', '_read', '_write', '_destroy', '_clean');
register_shutdown_function('session_write_close');

