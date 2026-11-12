<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\SecretFieldSave\Assert {
	class NotBlank {
	}
	class Regex {
		public function __construct($pattern) {
		}
	}
}
namespace Cacti\Test\SecretFieldSave {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
	$root = dirname(__DIR__, 3);

	define('SESS_ERROR_FIELDS', 'sess_error_fields');
	define('SESS_FIELD_VALUES', 'sess_field_values');
	define('POLLER_ID', 1);
	define('MESSAGE_LEVEL_WARN', 2);
	define('MESSAGE_LEVEL_ERROR', 3);
	// save_settings() requires the validator; the stub class below stands in for it.
	$library = sys_get_temp_dir() . '/cacti-secret-field-' . bin2hex(random_bytes(6));
	mkdir($library);
	file_put_contents($library . '/CactiSettings.php', '<?php');
	define('CACTI_PATH_LIBRARY', $library);

	class CactiSettings {
		public static function validate($snapshot, $settings) {
			return [];
		}
	}
	class CactiValidator {
		public static function validateInput($value, $name, $constraints, $level = 0) {
			return $value;
		}
	}
	function isrv($key) {
		return isset($GLOBALS['request'][$key]);
	}
	function gnrv($key) {
		return $GLOBALS['request'][$key] ?? '';
	}
	function grv($key) {
		return gnrv($key);
	}
	function gfrv($key) {
		return gnrv($key);
	}
	function htmle($value) {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
	function __($text, ...$args) {
		return vsprintf($text, $args);
	}
	function __esc($text, ...$args) {
		return htmle(vsprintf($text, $args));
	}
	function db_qstr($value) {
		return "'" . addslashes((string) $value) . "'";
	}
	function db_fetch_cell_prepared($sql, $params = []) {
		$GLOBALS['reads'][] = $params;

		return $GLOBALS['stored'][$params[0]] ?? false;
	}
	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['writes'][$params[0]] = $params[1];

		return true;
	}
	function db_execute($sql) {
		return true;
	}
	function db_fetch_assoc($sql) {
		return [];
	}
	function array_rekey($array, $key, $value) {
		return [];
	}
	function cacti_sizeof($value) {
		return is_array($value) ? count($value) : 0;
	}
	function read_config_option($name) {
		return 300;
	}
	function raise_message($id, ...$args) {
		$GLOBALS['messages'][] = $id;
	}
	function header($value) {
		$GLOBALS['redirect'] = $value;
	}
	function kill_session_var($name) {
	}
	function snmpagent_global_settings_update() {
	}
	function api_plugin_hook_function($name, $data = null) {
		return $data;
	}
	function is_remote_path_setting($name) {
		return false;
	}
	function poller_check_duplicate_poller_id($id, $hostname, $column) {
		return false;
	}
	function poller_host_duplicate($id, $hostname) {
		return false;
	}
	function is_error_message() {
		return false;
	}
	function sql_save($save, $table) {
		$GLOBALS['saved'] = $save;

		return (int) $save['id'] > 0 ? (int) $save['id'] : 9;
	}
	function db_connect_real(...$args) {
		$GLOBALS['connected'] = $args;

		return false;
	}
	function db_close($connection) {
	}

	\cacti_fixture_source_function($root . '/lib/html_form.php', 'draw_edit_control', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_text_box', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_checkbox', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_secret_is_stored', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_secret_value', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/settings.php', 'save_settings', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'poller_stored_dbpass', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'test_database_connection', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'form_save', __NAMESPACE__);

	$GLOBALS['cacti_fixture_contract'] = ['name' => 'secret-field-save', 'complete' => false, 'assertions' => 0, 'producers' => ['tests/fixtures/Core/SecretFieldSaveMatrix.php']];
	$GLOBALS['settings']               = ['mail' => [
		'settings_smtp_header'   => ['method' => 'spacer'],
		'settings_smtp_password' => ['method' => 'textbox_password', 'max_length' => 255],
	]];
	$GLOBALS['database_password'] = 'main-db-secret';
	$count                        = 0;

	$check = static function (bool $result, string $case) use (&$count) : void {
		if (!$result) {
			throw new \RuntimeException('Secret field mismatch: ' . $case);
		}
		$count++;
	};

	// Rendering: neither the stored value nor the new-record default reaches the page.
	foreach ([['stored-smtp-secret', '', 1], ['', 'main-db-secret', 0]] as [$value, $default, $id]) {
		$_SESSION = [];
		$field    = ['method' => 'textbox_password', 'value' => $value, 'default' => $default, 'max_length' => 64, 'form_id' => $id, 'secret' => true];
		ob_start();
		draw_edit_control('secret_field', $field);
		$html = (string) ob_get_clean();
		$check(!str_contains($html, 'secret-') && !str_contains($html, '-secret') && str_contains($html, "name='secret_field_clear'"), 'render ' . $id);
	}

	// settings.php: blank keeps, typed value replaces, clear blanks, a mismatch saves nothing.
	foreach ([
		'blank keeps'    => [['settings_smtp_password' => '', 'settings_smtp_password_confirm' => ''], 'stored-smtp-secret', 'stored-smtp-secret'],
		'change'         => [['settings_smtp_password' => 'new-secret', 'settings_smtp_password_confirm' => 'new-secret'], 'stored-smtp-secret', 'new-secret'],
		'clear'          => [['settings_smtp_password' => '', 'settings_smtp_password_confirm' => '', 'settings_smtp_password_clear' => 'on'], 'stored-smtp-secret', ''],
		'nothing stored' => [['settings_smtp_password' => ''], false, null],
		'mismatch'       => [['settings_smtp_password' => 'one', 'settings_smtp_password_confirm' => 'two'], 'stored-smtp-secret', null],
	] as $case => [$request, $stored, $expected]) {
		$GLOBALS['request'] = ['tab' => 'mail'] + $request;
		$GLOBALS['stored']  = ['settings_smtp_password' => $stored];
		$GLOBALS['writes']  = [];
		$_SESSION           = [];
		save_settings();
		$check(($GLOBALS['writes']['settings_smtp_password'] ?? null) === $expected, 'settings ' . $case);
	}

	// pollers.php: the save and the connection test use the stored password when left blank.
	foreach ([
		'existing blank keeps' => [['id' => '2', 'dbpass' => ''], 'remote-secret'],
		'existing change'      => [['id' => '2', 'dbpass' => 'new-remote'], 'new-remote'],
		'existing clear'       => [['id' => '2', 'dbpass' => '', 'dbpass_clear' => 'on'], ''],
		'new uses default'     => [['id' => '0', 'dbpass' => ''], 'main-db-secret'],
	] as $case => [$request, $expected]) {
		$GLOBALS['request'] = ['save_component_poller' => 'on', 'name' => 'Remote', 'hostname' => 'remote.example', 'log_level' => '1', 'timezone' => 'UTC', 'notes' => '', 'processes' => '1', 'threads' => '1', 'sync_interval' => '7200', 'dbdefault' => 'cacti', 'dbhost' => 'db.example', 'dbuser' => 'cacti', 'dbport' => '3306', 'dbretries' => '2', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '', 'dbsslcapath' => ''] + $request;
		$GLOBALS['stored']  = ['2' => 'remote-secret'];
		form_save();
		$check($GLOBALS['saved']['dbpass'] === $expected, 'poller save ' . $case);

		ob_start();
		test_database_connection();
		ob_end_clean();
		$check($GLOBALS['connected'][2] === $expected, 'poller ping ' . $case);
	}

	unlink($library . '/CactiSettings.php');
	rmdir($library);
	$GLOBALS['cacti_fixture_contract']['complete']   = true;
	$GLOBALS['cacti_fixture_contract']['assertions'] = $count;
	print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
}
