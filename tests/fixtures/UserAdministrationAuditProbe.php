<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Run the real save handler and audit helper with an isolated SQLite save boundary.
$scenario = $argv[1] ?? 'edit';
$root     = dirname(__DIR__, 2);
$pdo      = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE user_auth (id INTEGER PRIMARY KEY, username TEXT, password TEXT, enabled TEXT, locked TEXT)');
$old_hash  = password_hash('old password', PASSWORD_DEFAULT);
$statement = $pdo->prepare('INSERT INTO user_auth VALUES (10, ?, ?, ?, ?)');
$statement->execute(['target', $old_hash, 'on', '']);
$logs     = [];
$messages = [];
$_SESSION = ['sess_user_id' => 1];
$request  = ['save_component_user' => '1', 'id' => $scenario === 'new' ? 0 : 10, 'username' => 'target', 'realm' => 0, 'enabled' => 'on', 'locked' => '', 'password' => '', 'password_confirm' => ''];

if (in_array($scenario, ['password', 'failed-save', 'mismatch'], true)) {
	$request['password']         = 'new password';
	$request['password_confirm'] = $scenario === 'mismatch' ? 'different password' : 'new password';
}

function load_audit_function($file, $name) {
	$source = file_get_contents($file);
	$start  = strpos($source, 'function ' . $name . '(');
	$end    = strpos($source, "\nfunction ", $start + 1);
	eval(substr($source, $start, $end === false ? null : $end - $start));
}
function gnrv($key, $default = '') {
	return $GLOBALS['request'][$key] ?? $default;
}
function grv($key, $default = '') {
	return gnrv($key, $default);
}
function gfrv($key, $default = 0) {
	return (int) gnrv($key, $default);
}
function get_nfilter_request_var($key) {
	return gnrv($key);
}
function isrv($key) {
	return isset($GLOBALS['request'][$key]);
}
function is_error_message() {
	return $GLOBALS['messages'] !== [];
}
function raise_message($message, ...$args) {
	if ($message !== 1) {
		$GLOBALS['messages'][] = $message;
	}
}
function read_config_option($key) {
	return $key === 'admin_user' ? 1 : '';
}
function is_template_account($id) {
	return false;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function compat_password_hash($password, $algorithm) {
	return password_hash($password, $algorithm);
}
function form_input_validate($value, ...$args) {
	return $value;
}
function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'realm = ?')) {
		return [];
	}
	$statement = $GLOBALS['pdo']->prepare('SELECT * FROM user_auth WHERE id = ?');
	$statement->execute([$params[0]]);

	return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}
function db_fetch_cell_prepared($sql, $params = []) {
	if (str_contains($sql, 'password_history')) {
		return '';
	}
	$row = db_fetch_row_prepared($sql, $params);

	return $row[str_contains($sql, 'SELECT password') ? 'password' : 'username'] ?? '';
}
function db_execute_prepared(...$args) {
	return true;
}
function api_plugin_hook_function($hook, $save) {
	return $save;
}
function sql_save($save, $table) {
	if ($GLOBALS['scenario'] === 'failed-save') {
		return false;
	}
	$id        = $save['id'] ?: 11;
	$statement = $GLOBALS['pdo']->prepare('INSERT OR REPLACE INTO user_auth VALUES (?, ?, ?, ?, ?)');
	$statement->execute([$id, $save['username'], $save['password'], $save['enabled'], $save['locked']]);

	return $id;
}
function get_username($id) {
	return 'admin';
}
function cacti_log($message, ...$args) {
	$GLOBALS['logs'][] = $message;

	return true;
}

load_audit_function($root . '/user_admin.php', 'form_save');
load_audit_function($root . '/lib/functions.php', 'log_user_action');
form_save();
$saved = $pdo->query('SELECT password FROM user_auth WHERE id = ' . ($scenario === 'new' ? 11 : 10))->fetchColumn();
print json_encode(['logs' => $logs, 'password_changed' => $saved !== $old_hash, 'new_password_matches' => password_verify('new password', $saved), 'errors' => $messages], JSON_THROW_ON_ERROR) . "\n";
