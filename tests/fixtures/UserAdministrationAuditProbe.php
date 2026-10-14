<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiUserAuditFixture;
use PDO;
use RuntimeException;
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
// Run the real save handler and audit helper with an isolated SQLite save boundary.
$scenario = $argv[1] ?? 'edit';
$root     = dirname(__DIR__, 2);
require $root . '/include/global_constants.php';
$pdo      = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE user_auth (id INTEGER PRIMARY KEY, username TEXT, password TEXT, enabled TEXT, locked TEXT)');
$old_hash  = password_hash('old password', PASSWORD_DEFAULT);
$statement = $pdo->prepare('INSERT INTO user_auth VALUES (10, ?, ?, ?, ?)');
$statement->execute(['target', $old_hash, 'on', '']);
$logs     = [];
$messages = [];
$_SESSION = ['sess_user_id' => 1];
$request  = ['save_component_user' => '1', 'id' => $scenario === 'new' ? 0 : 10, 'username' => 'target', 'realm' => 0, 'enabled' => 'on', 'locked' => '', 'password' => '', 'password_confirm' => ''];

if ($scenario === 'enable' || str_starts_with($scenario, 'bulk-enable')) {
	$pdo->exec("UPDATE user_auth SET enabled = '' WHERE id = 10");
} elseif ($scenario === 'disable') {
	$request['enabled'] = '';
} elseif ($scenario === 'lock') {
	$request['locked'] = 'on';
} elseif ($scenario === 'unlock') {
	$pdo->exec("UPDATE user_auth SET locked = 'on' WHERE id = 10");
}

if (in_array($scenario, ['password', 'failed-save', 'failed-save-id', 'save-read-failure', 'save-missing', 'mismatch'], true)) {
	$request['password']         = 'new password';
	$request['password_confirm'] = $scenario === 'mismatch' ? 'different password' : 'new password';
}

function load_audit_function($file, $name) {
	cacti_fixture_source_function($file, $name, __NAMESPACE__);
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
	return $GLOBALS['scenario'] === 'delete-template';
}
function get_guest_account() {
	return $GLOBALS['scenario'] === 'delete-guest' ? 10 : 0;
}
function input_validate_input_number(...$args) {
}
function cacti_count($value) {
	return count($value);
}
function sanitize_unserialize_selected_items($value) {
	return unserialize($value, ['allowed_classes' => false]);
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
	if ($GLOBALS['scenario'] === 'delete-read-failure' && str_contains($sql, 'SELECT id')) {
		return false;
	}

	if (str_contains($sql, 'SELECT id, username, password, enabled, locked')) {
		if (in_array($GLOBALS['scenario'], ['save-read-failure', 'bulk-enable-read-failure', 'bulk-disable-read-failure'], true)) {
			return false;
		}

		if ($GLOBALS['scenario'] === 'save-missing') {
			return [];
		}
	}

	if (str_starts_with($sql, 'SELECT username, enabled') && !empty($GLOBALS['bulk_mutated']) && str_ends_with($GLOBALS['scenario'], '-read-failure')) {
		return false;
	}

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
function db_execute_prepared($sql, $params = []) {
	if (str_starts_with($sql, 'UPDATE user_auth SET enabled')) {
		$GLOBALS['bulk_mutated'] = true;

		if (str_ends_with($GLOBALS['scenario'], '-failure') && !str_ends_with($GLOBALS['scenario'], '-read-failure')) {
			return false;
		}
		$statement = $GLOBALS['pdo']->prepare($sql);

		return $statement->execute($params);
	}

	if (str_starts_with($sql, 'DELETE FROM user_auth WHERE')) {
		if ($GLOBALS['scenario'] === 'delete-failure') {
			return false;
		}
		$statement = $GLOBALS['pdo']->prepare($sql);

		return $statement->execute($params);
	}

	return true;
}
function api_plugin_hook_function($hook, $save) {
	return $save;
}
function sql_save($save, $table) {
	if ($GLOBALS['scenario'] === 'failed-save') {
		return false;
	}

	// The legacy production helper could return the existing ID on write
	// failure. Keep that case to verify the handler also confirms persistence.
	if ($GLOBALS['scenario'] === 'failed-save-id') {
		return $save['id'];
	}
	$id        = $save['id'] ?: 11;
	$statement = $GLOBALS['pdo']->prepare('INSERT OR REPLACE INTO user_auth VALUES (?, ?, ?, ?, ?)');
	$statement->execute([$id, $save['username'], $save['password'], $save['enabled'], $save['locked']]);

	return $id;
}
function get_username($id) {
	if ($id === 1) {
		return $GLOBALS['scenario'] === 'audit-controls' ? "admin\r\n\x00\x1b\x7f" : 'admin';
	}

	return db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', [$id]);
}
function cacti_log($message, ...$args) {
	$GLOBALS['logs'][] = $message;

	return true;
}
function reset_user_perms($id) {
}

load_audit_function($root . '/user_admin.php', 'form_save');
load_audit_function($root . '/lib/functions.php', 'log_user_action');
load_audit_function($root . '/lib/functions.php', 'get_execution_user');

if (str_starts_with($scenario, 'audit-') && $scenario !== 'audit-controls') {
	if ($scenario === 'audit-invalid') {
		foreach ([[[], 'edited'], ['target', []], ["\r\n", 'edited'], ['target', "\x00"]] as [$user, $action]) {
			if (log_user_action($user, $action) !== false) {
				throw new RuntimeException('Invalid audit input was logged');
			}
		}
	} else {
		$_SESSION = $scenario === 'audit-cli' ? [] : ['sess_user_id' => 404];
		log_user_action('target', 'edited');
	}
	print json_encode(['logs' => $logs], JSON_THROW_ON_ERROR) . "\n";

	exit;
}

if ($scenario === 'audit-controls') {
	log_user_action("target\r\n\x00\x1b\x7f", "edited\r\n\x00\x1b\x7f");
	print json_encode(['logs' => $logs], JSON_THROW_ON_ERROR) . "\n";

	exit;
}

if (str_starts_with($scenario, 'delete') || str_starts_with($scenario, 'bulk-')) {
	load_audit_function($root . '/user_admin.php', 'form_actions');
	load_audit_function($root . '/lib/auth.php', 'user_remove');
	load_audit_function($root . '/lib/auth.php', 'user_enable');
	load_audit_function($root . '/lib/auth.php', 'user_disable');
	$request = ['selected_items' => serialize([10]), 'drp_action' => str_starts_with($scenario, 'bulk-enable') ? '3' : (str_starts_with($scenario, 'bulk-disable') ? '4' : '1')];

	if ($scenario === 'delete-missing') {
		$pdo->exec('DELETE FROM user_auth WHERE id = 10');
	}

	if (str_ends_with($scenario, '-missing')) {
		$pdo->exec('DELETE FROM user_auth WHERE id = 10');
	}

	if ($scenario === 'bulk-enable-noop') {
		$pdo->exec("UPDATE user_auth SET enabled = 'on' WHERE id = 10");
	} elseif ($scenario === 'bulk-disable-noop') {
		$pdo->exec("UPDATE user_auth SET enabled = '' WHERE id = 10");
	}
	register_shutdown_function(static function () use ($pdo, &$logs, &$messages): void {
		print json_encode(['logs' => $logs, 'remaining' => (int) $pdo->query('SELECT COUNT(*) FROM user_auth WHERE id = 10')->fetchColumn(), 'enabled' => $pdo->query('SELECT enabled FROM user_auth WHERE id = 10')->fetchColumn(), 'errors' => $messages], JSON_THROW_ON_ERROR) . "\n";
	});
	form_actions();
}
form_save();
$saved = $pdo->query('SELECT password FROM user_auth WHERE id = ' . ($scenario === 'new' ? 11 : 10))->fetchColumn();
print json_encode(['logs' => $logs, 'password_changed' => $saved !== $old_hash, 'new_password_matches' => password_verify('new password', $saved), 'errors' => $messages], JSON_THROW_ON_ERROR) . "\n";
