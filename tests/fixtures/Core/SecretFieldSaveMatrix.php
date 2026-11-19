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
	function draw_edit_form($form) {
		foreach ($form['fields'] as $name => $field) {
			if (in_array($field['method'] ?? '', ['textbox', 'textbox_password'], true)) {
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
	function get_url_type() {
		return 'https';
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
	\cacti_fixture_source_function($root . '/lib/html_utility.php', 'inject_form_variables', __NAMESPACE__);
	// login_providers.php only marks its password fields; loading its save handler instruments the file.
	\cacti_fixture_source_function($root . '/login_providers.php', 'form_save', __NAMESPACE__ . '\\LoginProviders');
	require_once $root . '/include/vendor/autoload.php';
	\cacti_fixture_source_function($root . '/pollers.php', 'form_save', __NAMESPACE__);

	$GLOBALS['cacti_fixture_contract'] = ['name' => 'secret-field-save', 'complete' => false, 'assertions' => 0, 'producers' => ['tests/fixtures/Core/SecretFieldSaveMatrix.php']];
	// The Mail tab fields come from the real include/global_settings.php definitions.
	$settings_file = $root . '/include/global_settings.php';
	$source        = (string) file_get_contents($settings_file);

	if (!preg_match('/^\$settings\[\'mail\'\] = \[.*?^\];/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new \RuntimeException('Cannot locate $settings[\'mail\']');
	}
	$mail_settings = sys_get_temp_dir() . '/cacti-secret-field-mail-' . bin2hex(random_bytes(6));
	$padding       = str_repeat("\n", substr_count($source, "\n", 0, $match[0][1]));
	file_put_contents($mail_settings, '<?php namespace ' . __NAMESPACE__ . '; use Symfony\\Component\\Validator\\Constraints as Assert; ' . $padding . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$mail_settings] = $settings_file;

	foreach (['CACTI_MAIL_SMTP' => 1, 'POLLER_VERBOSITY_LOW' => 2, 'REPORTS_DEFAULT_MAX_SIZE' => 10485760, 'REPORTS_TYPE_INLINE_PNG' => 1, 'CACTI_PATH_URL' => '/cacti/'] as $constant => $constant_value) {
		if (!defined($constant)) {
			define($constant, $constant_value);
		}
	}
	$mail_methods      = [];
	$attach_types      = [];
	$attachment_sizes  = [];
	$logfile_verbosity = [];
	$settings          = [];
	require $mail_settings;
	unlink($mail_settings);
	$mail_fields                  = ['settings_smtp_header', 'settings_smtp_password', 'settings_oauth2_client_id', 'settings_oauth2_client_secret', 'settings_oauth2_refresh_token'];
	$GLOBALS['settings']          = ['mail' => array_intersect_key($settings['mail'], array_flip($mail_fields))];
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

	// settings.php: the OAuth2 client secret and refresh token follow the same rules.
	foreach (['settings_oauth2_client_secret', 'settings_oauth2_refresh_token'] as $oauth_field) {
		foreach ([
			'blank keeps'    => [[$oauth_field => ''], 'stored-oauth-value', 'stored-oauth-value'],
			'missing keeps'  => [[], 'stored-oauth-value', 'stored-oauth-value'],
			'change'         => [[$oauth_field => 'new-oauth-value'], 'stored-oauth-value', 'new-oauth-value'],
			'clear'          => [[$oauth_field => '', $oauth_field . '_clear' => 'on'], 'stored-oauth-value', ''],
			'nothing stored' => [[$oauth_field => ''], false, null],
		] as $case => [$request, $stored, $expected]) {
			$GLOBALS['request'] = ['tab' => 'mail'] + $request;
			$GLOBALS['stored']  = [$oauth_field => $stored];
			$GLOBALS['writes']  = [];
			$_SESSION           = [];
			save_settings();
			$check(($GLOBALS['writes'][$oauth_field] ?? null) === $expected, $oauth_field . ' ' . $case);
		}
	}

	// settings.php: the page shows that a value is stored, never the value itself.
	$GLOBALS['tabs']    = ['mail' => 'Mail'];
	$GLOBALS['request'] = ['tab' => 'mail'];
	$GLOBALS['stored']  = ['settings_smtp_password' => 'stored-smtp-secret', 'settings_oauth2_client_id' => 'visible-client-id', 'settings_oauth2_client_secret' => 'stored-client-secret', 'settings_oauth2_refresh_token' => 'stored-refresh-token'];
	$_SESSION           = [];
	ob_start();
	display_settings();
	$html = (string) ob_get_clean();
	$check(!str_contains($html, 'stored-smtp-secret') && str_contains($html, 'A value is stored.') && str_contains($html, "name='settings_smtp_password_clear'"), 'settings display');
	$check(!str_contains($html, 'stored-client-secret') && !str_contains($html, 'stored-refresh-token')
		&& str_contains($html, "name='settings_oauth2_client_secret_clear'") && str_contains($html, "name='settings_oauth2_refresh_token_clear'")
		&& !str_contains($html, 'settings_oauth2_client_secret_confirm') && !str_contains($html, 'settings_oauth2_refresh_token_confirm')
		&& str_contains($html, "value='visible-client-id'"), 'settings display oauth2');

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

	// pollers.php edit page, drawn from the real field list: the connection test posts the record id,
	// the password is not rendered, and a collector saved without one does not claim a stored value.
	$source = (string) file_get_contents($root . '/pollers.php');

	if (!preg_match('/^\$fields_poller_edit = \[.*?^\];/ms', $source, $match)) {
		throw new \RuntimeException('Cannot locate $fields_poller_edit');
	}
	$poller_fields = sys_get_temp_dir() . '/cacti-secret-field-poller-' . bin2hex(random_bytes(6));
	file_put_contents($poller_fields, '<?php namespace ' . __NAMESPACE__ . '; ' . $match[0]);
	$database_username               = 'cacti';
	$database_default                = 'cacti';
	$database_port                   = '3306';
	$database_retries                = '2';
	$database_ssl                    = false;
	$database_ssl_key                = '';
	$database_ssl_cert               = '';
	$database_ssl_ca                 = '';
	$database_ssl_capath             = '';
	$database_ssl_verify_server_cert = false;
	$logfile_verbosity               = [];
	$poller_sync_intervals           = [];
	require $poller_fields;
	unlink($poller_fields);
	// inject_form_variables() rewrites the field list in place, so each page draw starts from a copy.
	$poller_field_list = $fields_poller_edit;

	foreach (['remote-secret' => true, '' => false] as $stored => $claims_stored) {
		$GLOBALS['fields_poller_edit'] = $poller_field_list;
		$GLOBALS['request']            = ['id' => '2'];
		$GLOBALS['rows']               = [2 => ['id' => 2, 'name' => 'Remote', 'timezone' => 'UTC', 'dbpass' => (string) $stored]];
		$_SESSION                      = [];
		ob_start();
		poller_edit();
		$html = (string) ob_get_clean();
		$check(str_contains($html, 'id: 2,') && !str_contains($html, 'remote-secret') && !str_contains($html, 'main-db-secret')
			&& str_contains($html, "name='dbpass_clear'") === $claims_stored && str_contains($html, 'A value is stored.') === $claims_stored, 'poller edit ' . ($claims_stored ? 'stored' : 'empty'));
	}

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
