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
namespace Cacti\Auth {
	// The login provider classes resolve these helpers in their own namespace first.
	function gnrv($key) {
		return \Cacti\Test\SecretFieldSave\gnrv($key);
	}
	function gfrv($key) {
		return gnrv($key);
	}
	function form_input_validate($value, $name, $regex, $allow_nulls, $error) {
		return $value;
	}
	function form_secret_value($name, $stored) {
		return \Cacti\Test\SecretFieldSave\form_secret_value($name, $stored);
	}
	function db_fetch_cell_prepared($sql, $params = []) {
		return json_encode(['specific_password' => 'stored-bind', 'client_secret' => 'stored-client']);
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
	define('POLLER_DELETE', 1);
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
	function db_fetch_row_prepared($sql, $params = []) {
		return $GLOBALS['rows'][$params[0]] ?? [];
	}
	function db_fetch_cell($sql) {
		return 1;
	}
	function srv($key, $value) {
		$GLOBALS['request'][$key] = $value;
	}
	function htmlerv($key) {
		return htmle(gnrv($key));
	}
	function ierv($key) {
		return empty($GLOBALS['request'][$key]);
	}
	function config_value_exists($name) {
		return isset($GLOBALS['stored'][$name]);
	}
	function cacti_js_encode($value) {
		return json_encode($value);
	}
	function csrf_get_tokens() {
		return 'token';
	}
	function display_tooltip($text) {
		return '';
	}
	function inject_form_variables($fields, $values) {
		return $fields;
	}
	function draw_edit_form($form) {
		foreach ($form['fields'] as $name => $field) {
			if (($field['method'] ?? '') == 'textbox_password') {
				draw_edit_control($name, $field);
			}
		}
	}
	function top_header() {
	}
	function bottom_footer() {
	}
	function validate_settings_filter() {
	}
	function set_config_option($name, $value) {
	}
	function html_start_box(...$args) {
	}
	function html_end_box(...$args) {
	}
	function form_start(...$args) {
	}
	function form_hidden_box(...$args) {
	}
	function form_save_button(...$args) {
	}
	function form_save_buttons(...$args) {
	}
	function api_plugin_hook($name) {
	}

	\cacti_fixture_source_function($root . '/lib/html_form.php', 'draw_edit_control', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_text_box', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_checkbox', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_secret_is_stored', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_form.php', 'form_secret_value', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/settings.php', 'display_settings', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/settings.php', 'save_settings', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'poller_stored_dbpass', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'poller_saved_test_dbpass', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'test_database_connection', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/pollers.php', 'poller_edit', __NAMESPACE__);
	// login_providers.php only marks its password fields; loading its save handler instruments the file.
	\cacti_fixture_source_function($root . '/login_providers.php', 'form_save', __NAMESPACE__ . '\\LoginProviders');
	require_once $root . '/include/vendor/autoload.php';
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

	// settings.php: the page shows that a value is stored, never the value itself.
	$GLOBALS['tabs']    = ['mail' => 'Mail'];
	$GLOBALS['request'] = ['tab' => 'mail'];
	$GLOBALS['stored']  = ['settings_smtp_password' => 'stored-smtp-secret'];
	$_SESSION           = [];
	ob_start();
	display_settings();
	$html = (string) ob_get_clean();
	$check(!str_contains($html, 'stored-smtp-secret') && str_contains($html, 'A value is stored.') && str_contains($html, "name='settings_smtp_password_clear'"), 'settings display');

	// pollers.php: a blank save keeps the stored password, a new collector uses the default.
	$base = ['name' => 'Remote', 'hostname' => 'remote.example', 'log_level' => '1', 'timezone' => 'UTC', 'notes' => '', 'processes' => '1', 'threads' => '1', 'sync_interval' => '7200', 'dbdefault' => 'cacti', 'dbhost' => 'db.example', 'dbuser' => 'cacti', 'dbport' => '3306', 'dbretries' => '2', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '', 'dbsslcapath' => ''];

	foreach ([
		'existing blank keeps' => [['id' => '2', 'dbpass' => ''], 'remote-secret'],
		'existing change'      => [['id' => '2', 'dbpass' => 'new-remote'], 'new-remote'],
		'existing clear'       => [['id' => '2', 'dbpass' => '', 'dbpass_clear' => 'on'], ''],
		'new uses default'     => [['id' => '0', 'dbpass' => ''], 'main-db-secret'],
	] as $case => [$request, $expected]) {
		$GLOBALS['request'] = ['save_component_poller' => 'on'] + $request + $base;
		$GLOBALS['stored']  = ['2' => 'remote-secret'];
		form_save();
		$check($GLOBALS['saved']['dbpass'] === $expected, 'poller save ' . $case);
	}

	// pollers.php connection test: a blank password only reuses the saved one for the saved target.
	$GLOBALS['rows'] = [2 => ['dbhost' => 'db.example', 'dbport' => '3306', 'dbuser' => 'cacti', 'dbpass' => 'remote-secret', 'dbssl' => '', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '', 'dbsslcapath' => '', 'dbsslverifyservercert' => '']];

	foreach ([
		'blank, saved target'      => [['id' => '2', 'dbpass' => ''], 'remote-secret'],
		'blank, changed host'      => [['id' => '2', 'dbpass' => '', 'dbhost' => 'other.example'], null],
		'blank, changed port'      => [['id' => '2', 'dbpass' => '', 'dbport' => '3307'], null],
		'blank, changed user'      => [['id' => '2', 'dbpass' => '', 'dbuser' => 'other'], null],
		'blank, changed SSL'       => [['id' => '2', 'dbpass' => '', 'dbssl' => 'on'], null],
		'blank, no saved record'   => [['id' => '3', 'dbpass' => ''], null],
		'typed, changed host'      => [['id' => '2', 'dbpass' => 'typed-secret', 'dbhost' => 'other.example'], 'typed-secret'],
	] as $case => [$request, $expected]) {
		$GLOBALS['request']   = $request + $base;
		$GLOBALS['connected'] = null;
		ob_start();
		$result = test_database_connection();
		$output = (string) ob_get_clean();

		if ($expected === null) {
			$check($result === false && $GLOBALS['connected'] === null && str_contains($output, 'Enter the database password'), 'poller ping ' . $case);
		} else {
			$check($result === true && $GLOBALS['connected'][2] === $expected, 'poller ping ' . $case);
		}
	}

	// pollers.php edit page: the connection test posts the record id, and the password is not rendered.
	$GLOBALS['fields_poller_edit'] = ['dbpass' => ['method' => 'textbox_password', 'value' => 'remote-secret', 'max_length' => 64, 'form_id' => 2, 'secret' => true]];
	$GLOBALS['request']            = ['id' => '2'];
	$GLOBALS['rows']               = [2 => ['id' => 2, 'name' => 'Remote', 'timezone' => 'UTC']];
	$_SESSION                      = [];
	ob_start();
	poller_edit();
	$html = (string) ob_get_clean();
	$check(str_contains($html, 'id: 2,') && !str_contains($html, 'remote-secret'), 'poller edit');

	// Login providers: the LDAP bind password and OpenID client secret follow the same rules.
	foreach ([
		'ldap blank keeps'   => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', [], 'stored-bind'],
		'ldap change'        => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', ['specific_password' => 'new-bind'], 'new-bind'],
		'ldap clear'         => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', ['specific_password_clear' => 'on'], ''],
		'openid blank keeps' => [\Cacti\Auth\OpenIdLoginProvider::class, 'client_secret', [], 'stored-client'],
		'openid clear'       => [\Cacti\Auth\OpenIdLoginProvider::class, 'client_secret', ['client_secret_clear' => 'on'], ''],
	] as $case => [$class, $key, $request, $expected]) {
		$GLOBALS['request'] = ['id' => '4', 'server' => 'ldap.example'] + $request;
		$check($class::collectParameters()[$key] === $expected, 'login provider ' . $case);
	}

	unlink($library . '/CactiSettings.php');
	rmdir($library);
	$GLOBALS['cacti_fixture_contract']['complete']   = true;
	$GLOBALS['cacti_fixture_contract']['assertions'] = $count;
	print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
}
