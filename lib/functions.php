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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use PHPMailer\PHPMailer\Exception;

/**
 * title_trim - takes a string of text, truncates it to $max_length and appends
 * three periods onto the end
 *
 * @param string  $text        the string to evaluate
 * @param integer $max_length  the maximum number of characters the string can contain
 *                             before it is truncated
 *
 * @return string the truncated string if len($text) is greater than $max_length, else
 *   the original string
 */
function title_trim(string $text, int $max_length): string {
	if (strlen($text) > $max_length) {
		return mb_substr($text, 0, $max_length) . '...';
	} else {
		return $text;
	}
}

/**
 * filter_value - a quick way to highlight text in a table from general filtering
 *
 * @param $text - the string to filter
 * @param $filter - the search term to filter for
 * @param $href - the href if you wish to have an anchor returned
 *
 * @return null|string the filtered string
 */
function filter_value(?string $value, string $filter, string $href = ''): ?string {
	static $charset;

	if ($charset == '') {
		$charset = ini_get('default_charset');
	}

	if ($charset == '') {
		$charset = 'UTF-8';
	}

	if (empty($value)) {
		return $value;
	}

	$value =  htmlspecialchars($value, ENT_QUOTES, $charset, false);
	// Grave Accent character can lead to xss
	$value = str_replace('`', '&#96;', $value);

	if ($filter != '') {
		$value = preg_replace('#(' . preg_quote($filter) . ')#i', "<span class='filteredValue'>\\1</span>", $value);
	}

	if ($href != '') {
		$value = '<a class="linkEditMain" href="' . htmlspecialchars($href, ENT_QUOTES, $charset, false) . '">' . $value  . '</a>';
	}

	return $value;
}

/**
 * Wrapper to set_user_setting().
 *
 * @deprecated v1.0
 *
 * @param string $config_name - the name of the configuration setting as specified $settings array
 * @param mixed  $value       - the values to be saved
 * @param int    $user        - the user id, otherwise the session user
 *
 * @return void
 */
function set_graph_config_option(string $config_name, mixed $value, int $user = null) {
	set_user_setting($config_name, $value, $user);
}

/**
 * Wrapper to user_setting_exists
 *
 * @deprecated v1.0
 *
 * @param string $config_name   the name of the configuration setting as
 *                              specified $settings_user array in
 *                              'include/global_settings.php'
 *
 * @param int    $user_id       the id of the user to check the configuration
 *                              value for
 *
 * @return bool  true if a value exists, false if a value does not exist
 */
function graph_config_value_exists(string $config_name, int $user_id) {
	return user_setting_exists($config_name, $user_id);
}

/**
 * Wrapper to read_default_user_setting
 *
 * @deprecated v1.0
 *
 * @param string $config_name   the name of the configuration setting as
 *                              specified $settings_user array in
 *                              'include/global_settings.php'
 *
 * @return bool  the default value of the configuration option
 */
function read_default_graph_config_option($config_name) {
	return read_default_user_setting($config_name);
}

/**
 * Wrapper to read_user_setting
 *
 * @deprecated v1.0
 *
 * @param string $config_name   the name of the configuration setting as
 *                              specified $settings_user array in
 *                              'include/global_settings.php'
 * @param mixed $force
 *
 * @return bool  the default value of the configuration option
 */
function read_graph_config_option($config_name, $force = false) {
	return read_user_setting($config_name, false, $force);
}

/**
 * Sets/updates aLL user settings
 *
 * @param  integer|null $user  the user id, otherwise the session user
 *
 * @return void
 */
function save_user_settings(?int $user = null):void {
	global $settings_user;

	// Passed user id, or session id, or else 0
	$user = $user ?? ($_SESSION[SESS_USER_ID] ?? 0);

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		foreach ($tab_fields as $field_name => $field_array) {
			/* Check every field with a numeric default value and reset it to default if the inputted value is not numeric  */
			if (isset($field_array['default']) && is_numeric($field_array['default']) && !is_numeric(get_nfilter_request_var($field_name))) {
				set_request_var($field_name, $field_array['default']);
			}

			if (isset($field_array['method'])) {
				if ($field_array['method'] == 'checkbox') {
					set_user_setting($field_name, (isset_request_var($field_name) ? 'on' : ''), $user);
				} elseif ($field_array['method'] == 'checkbox_group') {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						set_user_setting($sub_field_name, (isset_request_var($sub_field_name) ? 'on' : ''), $user);
					}
				} elseif ($field_array['method'] == 'textbox_password') {
					if (get_nfilter_request_var($field_name) != get_nfilter_request_var($field_name.'_confirm')) {
						$_SESSION[SESS_ERROR_FIELDS][$field_name] = $field_name;
						$_SESSION[SESS_FIELD_VALUES][$field_name] = get_nfilter_request_var($field_name);

						// Set error 4
						$errors[4]  = 4;
					} elseif (isset_request_var($field_name)) {
						set_user_setting($field_name, get_nfilter_request_var($field_name), $user);
					}
				} elseif ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						if (isset_request_var($sub_field_name)) {
							set_user_setting($sub_field_name, get_nfilter_request_var($sub_field_name), $user);
						}
					}
				} elseif (isset_request_var($field_name)) {
					set_user_setting($field_name, get_nfilter_request_var($field_name), $user);
				}
			}
		}
	}
}

/**
 * set_user_setting - sets/updates a user setting with the given value.
 *
 * @param string   $config_name - the name of the configuration setting as specified $settings array
 * @param mixed    $value       - the values to be saved
 * @param null|int $user        - the user id, otherwise the session user
 *
 * @return void
 */
function set_user_setting(string $config_name, mixed $value, ?int $user = null):void {
	global $settings_user;

	// Passed user id, or session id, or else 0
	$user = $user ?? ($_SESSION[SESS_USER_ID] ?? 0);

	if ($user == 0) {
		$mode = isset($_SESSION[SESS_USER_ID]) ? 'WEBUI' : 'POLLER';
		cacti_log('NOTE: Attempt to set user setting \'' . $config_name . '\', with no valid user id: ' . cacti_debug_backtrace('', false, false, 0, 1), false, $mode, POLLER_VERBOSITY_MEDIUM);
	} elseif (db_table_exists('settings_user')) {
		db_execute_prepared('REPLACE INTO settings_user
			SET user_id = ?,
			name = ?,
			value = ?',
			array($user, $config_name, $value));

		unset($_SESSION[OPTIONS_USER]);
		$settings_user[$config_name]['value'] = $value;
	}
}

/**
 * Determines if a value exists for the current user/setting specified
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @param  integer|null $user_id      the id of the user to check the
 *                                    configuration value for
 *
 * @return boolean
 */
function user_setting_exists(string $config_name, ?int $user_id):bool {
	static $exists_user_setting = array();

	// We use isset instead of array_key_exists so that
	// if we never had a value, we can see if we got on
	// but once we know we have one, assume we always
	// will
	if (!isset($config_name[$exists_user_setting])) {
		$value = 0;

		if (db_table_exists('settings_user')) {
			$value = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM settings_user
				WHERE name = ?
				AND user_id = ?',
				array($config_name, $user_id));
		}

		$exists_user_setting[$config_name] = ($value !== false && $value > 0);
	}

	return $exists_user_setting[$config_name];
}

/**
 * If a value exists for the current user/setting specified, removes it
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @param  integer|null $user_id      the id of the user to check the
 *                                    configuration value for
 * @param mixed $user
 *
 * @return void
 */
function clear_user_setting(string $config_name, ?int $user = null):void {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */
	$effective_uid = $user ?? ($_SESSION[SESS_USER_ID] ?? 0);

	if (db_table_exists('settings_user')) {
		db_execute_prepared('DELETE FROM settings_user
			WHERE name = ?
			AND user_id = ?',
			array($config_name, $effective_uid));
	}

	unset($_SESSION[OPTIONS_USER]);
}

/**
 * Finds the default value of a user configuration setting
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @return ?mixed the default value of the configuration option
 */
function read_default_user_setting(string $config_name): mixed {
	global $settings_user;

	$result = '';

	foreach ($settings_user as $tab_array) {
		if (isset($tab_array[$config_name]['default'])) {
			$result = $tab_array[$config_name]['default'];

			break;
		}

		foreach ($tab_array as $field_array) {
			if (isset($field_array['items'][$config_name]['default'])) {
				$result = $field_array['items'][$config_name]['default'];

				break;
			}
		}
	}

	return $result;
}

/**
 * Finds the current value of a users configuration setting
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @param  mixed        $default      the default value is none is set
 *
 * @param  bool         $force        pull the data from the database if true ignoring session
 *
 * @param  integer|null $user_id      the id of the user to read the setting for
 *
 * @return mixed the current value of the user setting
 */
function read_user_setting(string $config_name, mixed $default = false, bool $force = false, ?int $user = 0):mixed {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */
	$effective_uid = $user ?? ($_SESSION[SESS_USER_ID] ?? 0);

	if (!$force) {
		if (isset($_SESSION[OPTIONS_USER])) {
			$user_config_array = $_SESSION[OPTIONS_USER];
		}
	}

	// We use isset instead of array_key_exists so that
	// if we never had a value, we can see if we got on
	// but once we know we have one, assume we always
	// will
	if (!isset($user_config_array[$config_name])) {
		$db_setting = false;

		if (db_table_exists('settings_user')) {
			$db_setting = db_fetch_row_prepared('SELECT value
				FROM settings_user
				WHERE name = ?
				AND user_id = ?',
				array($config_name, $effective_uid));
		}

		if (cacti_sizeof($db_setting)) {
			$user_config_array[$config_name] = $db_setting['value'];
		} elseif ($default !== false) {
			$user_config_array[$config_name] = $default;
		} else {
			$user_config_array[$config_name] = read_default_user_setting($config_name);
		}

		$set_var = $config['is_web'] ? '_SESSION' : 'config';
		$set_key = $config['is_web'] ? OPTIONS_USER : 'config_user_options_array';

		$$set_var[$set_key] = $user_config_array;
	}

	return $user_config_array[$config_name];
}

/**
 * Determines of a Cacti setting should be maintained
 * on the Remote Data Collector separate from the Main cacti server
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @return bool - true if the setting should be saved locally
 */
function is_remote_path_setting(string $config_name):bool {
	global $config;

	if ($config['poller_id'] > 1 && (strpos($config_name, 'path_') !== false || strpos($config_name, '_path') !== false)) {
		return true;
	} else {
		return false;
	}
}

/**
 * Sets/updates a cacti config option with the given value.
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings array in
 *                                    'include/global_settings.php'
 *
 * @param  mixed        $value        the values to be saved
 *
 * @param  bool         $remote       push the setting to the remote with
 *                                    the exception of path variables
 *
 * @return void
 */
function set_config_option(string $config_name, mixed $value, bool $remote = false):void {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	db_execute_prepared('REPLACE INTO settings
		SET name = ?, value = ?',
		array($config_name, $value));

	if ($remote && !is_remote_path_setting($config_name)) {
		$gone_time = read_config_option('poller_interval') * 2;

		$pollers = array_rekey(
			db_fetch_assoc('SELECT
				id,
				UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) AS last_polled
				FROM poller
				WHERE id > 1
				AND disabled=""'),
			'id', 'last_polled'
		);

		$sql = 'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)';

		foreach ($pollers as $p => $t) {
			if ($t > $gone_time) {
				raise_message('poller_' . $p, __('Settings save to Data Collector %d skipped due to heartbeat.', $p), MESSAGE_LEVEL_WARN);
			} else {
				$rcnn_id = poller_connect_to_remote($p);

				if ($rcnn_id) {
					if (db_execute_prepared($sql, array($config_name, $value), false, $rcnn_id) === false) {
						$rcnn_id = false;
					}
				}

				// check if we still have rcnn_id, if it's now become false, we had a problem
				if (!$rcnn_id) {
					raise_message('poller_' . $p, __('Settings save to Data Collector %d Failed.', $p), MESSAGE_LEVEL_ERROR);
				}
			}
		}
	}

	$set_var = $config['is_web'] ? '_SESSION' : 'config';
	$set_key = $config['is_web'] ? OPTIONS_WEB : OPTIONS_CLI;

	// Store whatever value we have in the array
	if (!isset($$set_var[$set_key]) || !is_array($$set_var[$set_key])) {
		$$set_var[$set_key] = array();
	}

	$$set_var[$set_key][$config_name] = $value;

	if (!empty($config['DEBUG_SET_CONFIG_OPTION'])) {
		file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() . cacti_debug_backtrace($config_name, false, false, 0, 1) . "\n", FILE_APPEND);
	}
}

/**
 * Determines if a value exists for the current user/setting specified
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings array in
 *                                    'include/global_settings.php'
 *
 * @return mixed true if a value exists, false if a value does not exist
 */
function config_value_exists(string $config_name):bool {
	static $exists_config_value = array();

	// We use isset instead of array_key_exists so that
	// if we never had a value, we can see if we got on
	// but once we know we have one, assume we always
	// will
	if (!isset($exists_config_value[$config_name])) {
		$value = db_fetch_cell_prepared('SELECT COUNT(*) FROM settings WHERE name = ?', array($config_name));

		$exists_config_value[$config_name] = ($value > 0);
	}

	return $exists_config_value[$config_name];
}

/**
 * Finds the default value of a Cacti configuration setting
 *
 * @param  string       $config_name  the name of the configuration setting as
 *                                    specified $settings_user array in
 *                                    'include/global_settings.php'
 *
 * @return mixed the default value of the configuration option
 */
function read_default_config_option(string $config_name):mixed {
	global $settings;

	if (isset($settings) && is_array($settings)) {
		foreach ($settings as $tab_array) {
			if (isset($tab_array[$config_name]) && isset($tab_array[$config_name]['default'])) {
				return $tab_array[$config_name]['default'];
			} else {
				foreach ($tab_array as $field_array) {
					if (isset($field_array['items']) && isset($field_array['items'][$config_name]) && isset($field_array['items'][$config_name]['default'])) {
						return $field_array['items'][$config_name]['default'];
					}
				}
			}
		}
	}

	return null;
}

/**
 * Cache the common configuration settings
 *
 * @return array
 */
function cache_common_config_settings():array {
	global $config;

	//$start = microtime(true);

	$common_settings = array(
		'auth_method',
		'auth_cache_enabled',
		'path_cactilog',
		'rrdtool_version',
		'log_verbosity',
		'log_destination',
		'default_image_format',
		'default_graph_width',
		'default_graph_height',
		'default_datechar',
		'default_date_format',
		'default_poller',
		'default_site',
		'i18n_language_support',
		'i18n_default_language',
		'reports_allow_ln',

		// Common page rendering
		'selective_debug',
		'selected_theme',
		'min_tree_width',
		'max_tree_width',
	);

	if ($config['is_web']) {
		$extra_settings = array(
			// Common all pages
			'force_https',
			'content_security_policy_script',
			'content_security_alternate_sources',
			'deletion_verification',

			// Common graphing
			'rrdtool_watermark',
			'realtime_cache_path',
			'path_rrdtool',
			'hide_disabled',
			'graph_watermark',
			'graph_dateformat',
			'font_method',
			'date',
			'boost_rrd_update_system_enable',
			'boost_rrd_update_max_records_per_select',
			'boost_rrd_update_enable',
			'boost_png_cache_enable',
			'remote_storage_method',
		);
	} else {
		$extra_settings = array(
			// Common polling
			'poller_interval',
			'snmp_version',
			'snmp_username',
			'snmp_timeout',
			'snmp_community',
			'snmp_auth_protocol',
			'snmp_security_level',
			'snmp_priv_protocol',
			'snmp_priv_passphrase',
			'snmp_port',
			'snmp_password',
			'snmp_retries',
			'device_threads',
			'max_get_size',
			'availability_method',
			'ping_method',
			'ping_port',
			'ping_retries',
			'ping_timeout',
			'path_snmpbulkwalk',
			'path_snmpwalk',
			'path_snmpget',
			'path_spine',

			// Common API
			'default_template',
			'delete_verification',

			// Thold
			'alert_bl_trigger',
			'alert_deadnotify',
			'alert_email',
			'alert_exempt',
			'alert_repeat',
			'alert_trigger',
			'base_url',
			'thold_alert_snmp_warning',
			'thold_alert_snmp_normal',
			'thold_alert_snmp',
			'thold_daemon_debug',
			'thold_disable_all',
			'thold_log_debug',
			'thold_send_text_only',
			'thold_show_datasource',
		);
	}

	$common_settings = array_merge($common_settings, $extra_settings);

	$settings = array_rekey(
		db_fetch_assoc_prepared('SELECT name, value
			FROM settings
			WHERE name IN (' . trim(str_repeat('?, ', cacti_sizeof($common_settings)),', ') . ')',
			$common_settings),
		'name', 'value'
	);

	if (cacti_sizeof($settings)) {
		$set_var = $config['is_web'] ? '_SESSION' : 'config';
		$set_key = $config['is_web'] ? OPTIONS_WEB : OPTIONS_CLI;

		// Store whatever value we have in the array
		if (!isset($$set_var[$set_key]) || !is_array($$set_var[$set_key])) {
			$$set_var[$set_key] = array();
		}

		foreach ($settings as $name => $value) {
			$$set_var[$set_key][$name] = $value;
		}

		return $$set_var;
	}

	return array();
}

/**
 * Finds the current value of a Cacti configuration setting
 *
 * @param  string       $config_name  The name of the configuration setting as
 *                                    specified $settings array in
 *                                    'include/global_settings.php'
 *
 * @return string|false               The current value of the configuration option
 */
function read_config_option(string $config_name, bool $force = false):string|false|false {
	global $config, $database_hostname, $database_default, $database_port, $database_sessions;

	$loaded = false;

	$set_var = $config['is_web'] ? '_SESSION' : 'config';
	$set_key = $config['is_web'] ? OPTIONS_WEB : OPTIONS_CLI;

	// Store whatever value we have in the array
	if (!isset($$set_var[$set_key]) || !is_array($$set_var[$set_key])) {
		$$set_var[$set_key] = array();
	}

	$loaded = isset($$set_var[$set_key][$config_name]);

	if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
		file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() . cacti_debug_backtrace($config_name, false, false, 0, 1) . "\n", FILE_APPEND);
	}

	// Do we have a value already stored in the array, or
	// do we want to make sure we have the latest value
	// from the database?
	if (!$loaded || $force) {
		// We need to check against the DB, but lets assume default value
		// unless we can actually read the DB
		$value = read_default_config_option($config_name);

		if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
			file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() .
				" $config_name: " .
				' dh: ' . isset($database_hostname) .
				' dp: ' . isset($database_port) .
				' dd: ' . isset($database_default) .
				' ds: ' . isset($database_sessions["$database_hostname:$database_port:$database_default"]) .
				"\n", FILE_APPEND);

			if (isset($database_hostname) && isset($database_port) && isset($database_default)) {
				file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() .
					" $config_name: [$database_hostname:$database_port:$database_default]\n", FILE_APPEND);
			}
		}

		// Are the database variables set, and do we have a connection??
		// If we don't, we'll only use the default value without storing
		// so that we can read the database version later.
		if (isset($database_hostname) && isset($database_port) && isset($database_default) &&
			isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			// Get the database setting
			$db_result = db_fetch_row_prepared('SELECT value FROM settings WHERE name = ?', array($config_name));

			if (cacti_sizeof($db_result)) {
				$value = $db_result['value'];
			}
		}

		// Store whatever value we have in the array
		$$set_var[$set_key][$config_name] = $value;
	}

	$value = $$set_var[$set_key][$config_name];

	return $value;
}

/**
 * get_selected_theme - checks the user settings and if the user selected
 * theme is set, returns it otherwise returns the system default.
 *
 * @return mixed the theme name
 */
function get_selected_theme():mixed {
	global $config, $themes;

	// shortcut if theme is set in session
	if (isset($_SESSION['selected_theme']) && isset($_SESSION[SESS_USER_ID])) {
		if (file_exists(CACTI_PATH_INCLUDE . '/themes/' . $_SESSION['selected_theme'] . '/main.css')) {
			return $_SESSION['selected_theme'];
		}
	}

	// default to system selected theme
	$theme = read_config_option('selected_theme');

	// check for a pre-1.x cacti being upgraded
	if ($theme == '' && !db_table_exists('settings_user')) {
		return 'modern';
	}

	// figure out user defined theme
	if (isset($_SESSION[SESS_USER_ID])) {
		// fetch user defined theme
		$user_theme = db_fetch_cell_prepared("SELECT value
			FROM settings_user
			WHERE name='selected_theme'
			AND user_id = ?",
			array($_SESSION[SESS_USER_ID]), '', false);

		// user has a theme
		if (!empty($user_theme)) {
			$theme = $user_theme;
		}
	}

	if (!file_exists(CACTI_PATH_INCLUDE . '/themes/' . $theme . '/main.css')) {
		foreach ($themes as $t => $name) {
			if (file_exists(CACTI_PATH_INCLUDE . '/themes/' . $t . '/main.css')) {
				$theme = $t;

				db_execute_prepared('UPDATE settings_user
					SET value = ?
					WHERE user_id = ?
					AND name="selected_theme"',
					array($theme, $_SESSION[SESS_USER_ID]));

				break;
			}
		}
	}

	if (is_valid_theme($theme, true) && isset($_SESSION[SESS_USER_ID])) {
		// update session
		$_SESSION['selected_theme'] = $theme;
	}

	return $theme;
}

/**
 * Returns true if a theme is valid
 *
 * @param  string|null $theme
 * @param  integer     $set_user
 *
 * @return boolean
 */
function is_valid_theme(?string &$theme, int $set_user = 0):bool {
	global $themes, $config;
	$valid = true;

	if ($theme == null || !file_exists(CACTI_PATH_INCLUDE . '/themes/' . $theme . '/main.css')) {
		$valid      = false;
		$user_table = db_table_exists('settings_user');

		foreach ($themes as $t => $name) {
			if (file_exists(CACTI_PATH_INCLUDE . '/themes/' . $t . '/main.css')) {
				$theme = $t;
				$valid = true;

				if ($user_table && $set_user && isset($_SESSION[SESS_USER_ID])) {
					db_execute_prepared('UPDATE settings_user
						SET value = ?
						WHERE user_id = ?
						AND name="selected_theme"',
						array($theme, $_SESSION[SESS_USER_ID]));
				}

				break;
			}
		}
	}

	// update session
	return $valid;
}

/**
 * form_input_validate - validates the value of a form field and Takes the appropriate action if the input
 * is not valid
 *
 * @param $field_value - the value of the form field
 * @param $field_name - the name of the $_POST field as specified in the HTML
 * @param $regexp_match - (optionally) enter a regular expression to match the value against
 * @param $allow_nulls - (bool) whether to allow an empty string as a value or not
 * @param $custom_message - (int) the ID of the message to raise upon an error which is defined in the
 *   $messages array in 'include/global_arrays.php'
 * @param mixed $message_id
 *
 * @return mixed the original $field_value
 */
function form_input_validate($field_value, $field_name, $regexp_match, $allow_nulls, $message_id = 3) {
	global $messages;

	/* write current values to the "field_values" array so we can retain them */
	$_SESSION[SESS_FIELD_VALUES][$field_name] = $field_value;

	if (($allow_nulls == true) && ($field_value == '')) {
		return $field_value;
	}

	$report_message = null;

	if ($allow_nulls == false && $field_value == '') {
		$report_message = __("Form Validation Failed: Variable '%s' does not allow nulls and variable is null", $field_name);
	} elseif ($regexp_match != '' && !preg_match('/' . $regexp_match . '/', $field_value)) {
		$report_message = __("Form Validation Failed: Variable '%s' with Value '%s' Failed REGEX '%s'", $field_name, $field_value, $regexp_match);
	}

	if ($report_message !== null) {
		$custom_message = null;

		if (read_config_option('log_validation')) {
			cacti_log($report_message, false, 'ERROR:');

			if (read_config_option('log_validation') == INPUT_VALIDATION_FULL && $message_id == 3) {
				$message_id     = $field_name;
				$custom_message = $report_message;
			}
		}

		$_SESSION[SESS_ERROR_FIELDS][$field_name] = $custom_message ?? $message_id;
		raise_message($message_id, $custom_message, MESSAGE_LEVEL_ERROR);
	}

	return $field_value;
}

/**
 * check_changed - determines if a request variable has changed between page loads
 *
 * @return mixed true if the value changed between loads
 * @param mixed $request
 * @param mixed $session
 */
function check_changed($request, $session) {
	if ((isset_request_var($request)) && (isset($_SESSION[$session]))) {
		if (get_nfilter_request_var($request) != $_SESSION[$session]) {
			return 1;
		}
	}
}

/**
 * is_error_message - finds whether an error message has been raised and has not been outputted to the
 * user
 *
 * @return mixed whether the messages array contains an error or not
 */
function is_error_message() {
	global $config, $messages;

	if (isset($_SESSION[SESS_ERROR_FIELDS]) && cacti_sizeof($_SESSION[SESS_ERROR_FIELDS])) {
		return true;
	} else {
		return false;
	}
}
/**
 * Get the level for the current message
 *
 * @param  array $current_message
 *
 * @return int
 */
function get_message_level(array $current_message):int {
	$current_level = MESSAGE_LEVEL_NONE;

	if (isset($current_message['level'])) {
		$current_level = $current_message['level'];
	} elseif (isset($current_message['type'])) {
		switch ($current_message['type']) {
			case 'error':
				$current_level = MESSAGE_LEVEL_ERROR;

				break;
			case 'info':
				$current_level = MESSAGE_LEVEL_INFO;

				break;
		}
	}

	return $current_level;
}

/**
 * Get the title for the current message
 *
 * @param  array       $current_message
 *
 * @return string|null
 */
function get_message_title(array $current_message):?string {
	$current_title = null;

	if (isset($current_message['title'])) {
		$current_title = $current_message['title'];
	} elseif (isset($current_message['type'])) {
		switch ($current_message['type']) {
			case 'error':
				$current_title = __('Error');

				break;
			case 'info':
				$current_title = __('Information');

				break;
		}
	}

	return $current_title;
}

/**
 * get_format_message_instance - finds the level of the current message instance
 *
 * @param message array the message instance
 * @param mixed $current_message
 *
 * @return mixed a formatted message
 */
function get_format_message_instance($current_message): string {
	if (is_array($current_message) && isset($current_message['message'])) {
		$fmessage = $current_message['message'];
		$level    = get_message_level($current_message);
	} elseif (is_array($current_message)) {
		$fmessage =  __esc('Message Not Found.');
		$level    = MESSAGE_LEVEL_ERROR;
	} else {
		$fmessage = $current_message;
		$level    = MESSAGE_LEVEL_INFO;
	}

	switch ($level) {
		case MESSAGE_LEVEL_NONE:
			$message = '<span>' . $fmessage . '</span>';

			break;
		case MESSAGE_LEVEL_INFO:
			$message = '<span class="deviceUp">' . $fmessage . '</span>';

			break;
		case MESSAGE_LEVEL_WARN:
			$message = '<span class="deviceWarning">' . $fmessage . '</span>';

			break;
		case MESSAGE_LEVEL_ERROR:
			$message = '<span class="deviceDown">' . $fmessage . '</span>';

			break;
		case MESSAGE_LEVEL_CSRF:
			$message = '<span class="deviceDown">' . $fmessage . '</span>';

			break;

		default:
			$message = '<span class="deviceUnknown">' . $fmessage . '</span>';

			break;
	}

	return $message;
}

/**
 * get_message_max_type - finds the message and returns its type
 *
 * @return mixed the message type 'info', 'warn', 'error' or 'csrf'
 */
function get_message_max_type(?array $output_messages = null) {
	global $messages;

	$level = MESSAGE_LEVEL_NONE;

	if ($output_messages === null && isset($_SESSION[SESS_MESSAGES])) {
		$ouptut_messages = $_SESSION[SESS_MESSAGES];
	}

	if (is_array($output_messages)) {
		foreach ($output_messages as $current_message_id => $current_message) {
			$current_level = get_message_level($current_message);

			if ($current_level == MESSAGE_LEVEL_NONE && isset($messages[$current_message_id])) {
				$current_level = get_message_level($messages[$current_message_id]);
			}

			if ($current_level != $level && $level != MESSAGE_LEVEL_NONE) {
				$level = MESSAGE_LEVEL_MIXED;
			} else {
				$level = $current_level;
			}
		}
	}

	return $level;
}

/**
 * Message to be displayed to the user once display_output_messages() is called
 *
 * @param string|int  $message_id     ID of the message as defined in $messages in 'include/global_arrays.php'
 * @param string      $message        Text of the message to be displayed
 * @param int         $message_level  Level of the message to be displayed
 * @param string|null $message_title  Title of the message to be displayed
 */
function raise_message(string|int $message_id, string $message = '', int $message_level = MESSAGE_LEVEL_NONE, ?string $message_title = null) {
	global $config, $messages, $no_http_headers;

	// This function should always exist, if not its an invalid install
	if (function_exists('session_status')) {
		$need_session = (session_status() == PHP_SESSION_NONE) && (!isset($no_http_headers));
	} else {
		return false;
	}

	if (empty($message)) {
		if (array_key_exists($message_id, $messages)) {
			$predefined = $messages[$message_id];

			if (isset($predefined['message'])) {
				$message = $predefined['message'];
			} else {
				$message = $predefined;
			}

			if ($message_level == MESSAGE_LEVEL_NONE) {
				$message_level = get_message_level($predefined);
			}

			$message_title = get_message_title($predefined);
		} else {
			if (isset($_SESSION[$message_id])) {
				/*
				 * A message was set at the session level
				 * but rather than just assume it's an error
				 * lets see if it is
				 */
				$message_level = MESSAGE_LEVEL_ERROR;
				$sessMessage   = $_SESSION[$message_id];

				/* Is the message an array ? */
				if (is_array($sessMessage)) {
					/* Do we have the message element to set the text ? */
					if (!empty($sessMessage['message'])) {
						$message = $sessMessage['message'];
					}

					/* Do we have the level element to set the level ? */
					if (!empty($sessMessage['level'])) {
						$message_level = $sessMessage['level'];
					}
				} else {
					$message = $sessMessage;
				}
			}

			/* The message is still empty? */
			if (empty($message)) {
				$message       = __('Message Not Found.');
				$message_level = MESSAGE_LEVEL_ERROR;
			}
		}
	}

	if ($need_session) {
		cacti_session_start();
	}

	if (!isset($_SESSION[SESS_MESSAGES])) {
		$_SESSION[SESS_MESSAGES] = array();
	}

	$final_message                        = array('message' => $message, 'level' => $message_level, 'title' => $message_title);
	$final_message['title']               = get_message_title($final_message);
	$_SESSION[SESS_MESSAGES][$message_id] = $final_message;

	if ($need_session) {
		cacti_session_close();
	}
}

/**
 * raise_message_javascript - raises a message that will appear in the UI
 * as the result of an server side error that can not be captured
 * normally.
 *
 * Note, this function assumes strings are already escaped when being
 * called.
 *
 * @param string The title for the dialog title bar
 * @param string Header section for the message
 * @param string The actual error message to display
 * @param int    The level to be displayed at
 *
 * @return void
 */
function raise_message_javascript(string $title, string $header, string $message, int $level = MESSAGE_LEVEL_MIXED) {
	?>
	<script type='text/javascript'>
	$(function() {
		raiseMessage('<?= $title?>', '<?= $header?>', '<?= $message ?>', <?= $level?>)
	});
	</script>
	<?php

	exit;
}

/**
 * display_output_messages - displays all of the cached messages from the raise_message() function and clears
 * the message cache
 */
function display_output_messages() {
	$debug_message   = debug_log_return('new_graphs');
	$output_messages = array();
	$final_messages  = array();

	if (isset($_SESSION[SESS_MESSAGES])) {
		if (!is_array($_SESSION[SESS_MESSAGES])) {
			$output_messages = array(
				'custom_error' => array(
					'level'   => MESSAGE_LEVEL_ERROR,
					'message' => $_SESSION[SESS_MESSAGES]
				)
			);
		} else {
			$output_messages = $_SESSION[SESS_MESSAGES];
		}

		clear_messages();
	}

	if ($debug_message != '') {
		$output_messages['debug_message'] = array(
			'level'   => MESSAGE_LEVEL_NONE,
			'message' => $debug_message,
		);

		debug_log_clear('new_graphs');
	}

	if (!empty($output_messages)) {
		foreach ($output_messages as $current_message_id => $current_message) {
			if (!is_array($current_message)) {
				$current_message = array(
					'level'   => MESSAGE_LEVEL_ERROR,
					'message' => $_SESSION[SESS_MESSAGES],
					'title'   => null,
				);
			}

			if (!empty($current_message['message'])) {
				$current_message['title']   = get_message_title($current_message);
				$final_messages[]           = array(
					'id'      => $current_message_id,
					'level'   => $current_message['level'],
					'message' => $current_message['message'],
					'title'   => get_message_title($current_message),
				);
			} else {
				cacti_log("ERROR: Cacti Error Message Id '$current_message_id' Not Defined", false, 'WEBUI');
			}
		}
	}

	return json_encode($final_messages);
}

function display_custom_error_message($message) {
	raise_message('custom_error', $message);
}

/**
 * clear_messages - clears the message cache
 */
function clear_messages() {
	// This function should always exist, if not its an invalid install
	if (function_exists('session_status')) {
		$need_session = (session_status() == PHP_SESSION_NONE) && (!isset($no_http_headers));
	} else {
		return false;
	}

	if ($need_session) {
		cacti_session_start();
	}

	kill_session_var(SESS_ERROR_FIELDS);
	kill_session_var(SESS_MESSAGES);

	if ($need_session) {
		cacti_session_close();
	}
}

/**
 * kill_session_var - kills a session variable using unset()
 * @param mixed $var_name
 */
function kill_session_var($var_name) {
	/* register_global = on: reset local settings cache so the user sees the new settings */
	unset($_SESSION[$var_name]);
	unset($var_name);
}

/**
 * force_session_data - forces session data into the session if the session was closed for some reason
 */
function force_session_data() {
	global $config;
	// This function should always exist, if not its an invalid install
	if (!function_exists('session_status') || !$config['is_web']) {
		return false;
	}

	if (session_status() == PHP_SESSION_NONE) {
		$data = $_SESSION;

		cacti_session_start();

		$_SESSION = $data;

		cacti_session_close();
	}
}

/**
 * array_rekey - changes an array in the form:
 *
 * '$arr[0] = array('id' => 23, 'name' => 'blah')' to the form
 * '$arr = array(23 => 'blah')'
 *
 * @param array  $array		The original array to manipulate
 * @param string $key		The name of the key
 * @param string $key_value	The name of the key value
 *
 * @return array the modified array
 */
function array_rekey(array $array, string $key, mixed $key_value): array {
	$ret_array = array();

	if (is_array($array)) {
		foreach ($array as $item) {
			$item_key = $item[$key];

			if (!is_array($key_value)) {
				$key_value = [$key_value];
			}

			foreach ($key_value as $value) {
				$ret_array[$item_key][$value] = $item[$value];
			}
		}
	}

	return $ret_array;
}

/**
 */

/**
 * cacti_log_file - returns the log filename
 *
 * @return string
 */
function cacti_log_file():string {
	$logfile        = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = CACTI_PATH_LOG . '/cacti.log';
	}

	return $logfile;
}

/**
 * Gets the selective log level for the current script
 *
 * Note that the results of this function are cached
 * internally so do not refresh if called again after
 * updating the value.
 *
 * @return null|int
 */
function get_selective_log_level(): ?int {
	static $force_level = null;

	if ($force_level !== null) {
		return $force_level;
	}

	if (isset($_SERVER['PHP_SELF'])) {
		$current_file = basename($_SERVER['PHP_SELF']);
		$dir_name     = dirname($_SERVER['PHP_SELF']);
	} elseif (isset($_SERVER['SCRIPT_NAME'])) {
		$current_file = basename($_SERVER['SCRIPT_NAME']);
		$dir_name     = dirname($_SERVER['SCRIPT_NAME']);
	} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
		$current_file = basename($_SERVER['SCRIPT_FILENAME']);
		$dir_name     = dirname($_SERVER['SCRIPT_FILENAME']);
	} else {
		$current_file = basename(__FILE__);
		$dir_name     = __DIR__;
	}

	/* initialize the force level to unset */
	$force_level = -1;

	/* see if any Cacti files are in debug level, and set the level */
	$debug_files = read_config_option('selective_debug');

	if ($debug_files != '') {
		$files = explode(',', $debug_files);

		if (array_search($current_file, $files, true) !== false) {
			$force_level = POLLER_VERBOSITY_DEBUG;
		}
	}

	/* Check for Plugin files in debug next */
	if (strpos($dir_name, 'plugins') !== false) {
		$debug_plugins = read_config_option('selective_plugin_debug');

		if ($debug_plugins != '') {
			$debug_plugins = explode(',', $debug_plugins);

			foreach ($debug_plugins as $myplugin) {
				if (strpos($dir_name, DIRECTORY_SEPARATOR . $myplugin) !== false) {
					$force_level = POLLER_VERBOSITY_DEBUG;

					break;
				}
			}
		}
	}

	return $force_level;
}

/**
 * cacti_log - logs a string to Cacti's log file or optionally to the browser
 *
 * @param $string - the string to append to the log file
 * @param $output - (bool) whether to output the log line to the browser using print() or not
 * @param $environ - (string) tells from where the script was called from
 * @param $level - (int) only log if above the specified log level
 */
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	global $config, $database_log;

	static $start = null;

	if ($start == null) {
		$start = microtime(true);
	}

	if (!isset($database_log)) {
		$database_log = false;
	}

	$last_log     = $database_log;
	$database_log = false;
	$force_level  = get_selective_log_level();
	$oprefix      = '';
	$omessage     = '';

	if (defined('POLLER_LOG_LEVEL') && POLLER_LOG_LEVEL != -1) {
		$level = POLLER_LOG_LEVEL;
	}

	/* only log if the specific level is reached, developer debug is special low + specific devdbg calls */
	if ($force_level == -1) {
		if ($level != '') {
			$logVerbosity = read_config_option('log_verbosity');

			if ($logVerbosity == POLLER_VERBOSITY_DEVDBG) {
				if ($level != POLLER_VERBOSITY_DEVDBG) {
					if ($level > POLLER_VERBOSITY_LOW) {
						$database_log = $last_log;

						return;
					}
				}
			} elseif ($level > $logVerbosity) {
				$database_log = $last_log;

				return;
			}
		}
	}

	cacti_system_zone_set();

	/* fill in the current date for printing in the log */
	if (defined('CACTI_DATE_TIME_FORMAT')) {
		$date = date(CACTI_DATE_TIME_FORMAT);
	} else {
		$date = date('Y-m-d H:i:s');
	}

	cacti_browser_zone_set();

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = cacti_log_file();

	/* format the message */
	if ($environ == 'POLLER') {
		$prefix = "$date - " . ($environ != '' ? "$environ: ":'') . 'Poller[' . $config['poller_id'] . '] PID[' . getmypid() . '] ';

		if ($output) {
			$oprefix = sprintf('Total[%3.4f] ', microtime(true) - $start);
		}
	} else {
		$prefix  = "$date - " . ($environ != '' ? $environ . ' ':'');

		if ($output) {
			$oprefix = $prefix;
		}
	}

	/* Log to Logfile */
	$message = clean_up_lines($string) . PHP_EOL;

	if ($output) {
		$omessage = $oprefix . $message;
	}

	if (($logdestination == 1 || $logdestination == 2) && read_config_option('log_verbosity') != POLLER_VERBOSITY_NONE) {
		/* print the data to the log (append) */
		$fp = @fopen($logfile, 'a');

		if ($fp) {
			$message = $prefix . $message;
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if ($logdestination == 2 || $logdestination == 3) {
		$log_type = '';

		if (strpos($string, 'ERROR:') !== false) {
			$log_type = 'err';
		} elseif (strpos($string, 'WARNING:') !== false) {
			$log_type = 'warn';
		} elseif (strpos($string, 'STATS:') !== false) {
			$log_type = 'stat';
		} elseif (strpos($string, 'NOTICE:') !== false) {
			$log_type = 'note';
		}

		if ($log_type != '') {
			if ($config['cacti_server_os'] == 'win32') {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_USER);
			} else {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_SYSLOG);
			}

			if ($log_type == 'err' && read_config_option('log_perror')) {
				syslog(LOG_CRIT, ($environ != '' ? $environ . ': ':'') . $string);
			} elseif ($log_type == 'warn' && read_config_option('log_pwarn')) {
				syslog(LOG_WARNING, ($environ != '' ? $environ . ': ':'') . $string);
			} elseif (($log_type == 'stat' || $log_type == 'note') && read_config_option('log_pstats')) {
				syslog(LOG_INFO, ($environ != '' ? $environ . ': ':'') . $string);
			}

			closelog();
		}
	}

	/* print output to standard out if required */
	if ($output == true && isset($_SERVER['argv'][0])) {
		print $omessage;
	}

	$database_log = $last_log;
}

/**
 * tail_file - Emulates the tail function with PHP native functions.
 * It is used in 0.8.6 to speed the viewing of the Cacti log file, which
 * can be problematic in the 0.8.6 branch.
 *
 * @param $file_name    - (char constant) the name of the file to tail
 * @param $line_cnt     - (int constant)  the number of lines to count
 * @param $message_type - (int constant) the type of message to return
 * @param $filter       - (char) the filtering expression to search for
 * @param $page_nr      - (int) the page we want to show rows for
 * @param $total_rows   - (int) the total number of rows in the logfile
 */
function tail_file(string $file_name, int $number_of_lines, ?int $message_type = -1, ?string $filter = '', ?int &$page_nr = 1, ?int &$total_rows = 0, ?bool $expand_text = false): array {
	if (!file_exists($file_name)) {
		touch($file_name);

		return array();
	}

	if (!is_readable($file_name)) {
		return array(__('Error %s is not readable', $file_name));
	}

	$filter = strtolower($filter);

	$fp = fopen($file_name, 'r');

	/* Count all lines in the logfile */
	$total_rows    = 0;
	$line_no       = 0;
	$display_line  = array();
	$should_expand = read_config_option('log_expand') == LOG_EXPAND_FULL;

	if ($should_expand) {
		$should_expand = !empty($filter) && !empty($expand_text);
	}

	while (($line = fgets($fp)) !== false) {
		$display = (determine_display_log_entry($message_type, $line, $filter));

		if ($should_expand && !$display) {
			$expanded = text_substitute($line, isHtml: false);

			if ($expanded != $line) {
				// expand line different so lets see if we want it now after all
				$display = determine_display_log_entry($message_type, $expanded, $filter);
			}
		}

		$display_line[$line_no] = $display;

		$line_no++;

		if ($display) {
			$total_rows++;
		}
	}

	// Reset the page count to 1 if the number of lines is exceeded
	if (($page_nr - 1) * $number_of_lines > $total_rows) {
		set_request_var('page', 1);
		$page_nr = 1;
	}

	/* rewind file pointer, to start all over */
	rewind($fp);

	$start = $total_rows - ($page_nr * $number_of_lines);
	$end   = $start + $number_of_lines;

	if ($start < 0) {
		$start = 0;
	}

	force_session_data();

	/* load up the lines into an array */
	$file_array = array();
	$i          = 0;
	$line_no    = 0;

	while (($line = fgets($fp)) !== false) {
		if (!isset($display_line[$line_no])) {
			$line_no++;

			continue;
		}

		$display = $display_line[$line_no];

		$line_no++;

		if ($display === false) {
			continue;
		}

		if ($i < $start) {
			$i++;

			continue;
		}

		if ($i >= $end) {
			break;
		}

		$i++;

		$file_array[$i] = $line;
	}

	fclose($fp);

	return $file_array;
}

/**
 * determine_display_log_entry - function to determine if we display the line
 *
 * @param $message_type
 * @param $line
 * @param $filter
 *
 * @return mixed should the entry be displayed
 */
function determine_display_log_entry($message_type, $line, $filter) {
	static $thold_enabled = null;

	if ($thold_enabled == null) {
		$thold_enabled = api_plugin_is_enabled('thold');
	}

	/* determine if we are to display the line */
	switch ($message_type) {
		case 1: /* stats only */
			$display = (strpos($line, 'STATS') !== false);

			break;
		case 2: /* warnings only */
			$display = (strpos($line, 'WARN') !== false);

			break;
		case 3: /* warnings + */
			$display = (strpos($line, 'WARN') !== false);

			if (!$display) {
				$display = (strpos($line, 'ERROR') !== false);
			}

			if (!$display) {
				$display = (strpos($line, 'DEBUG') !== false);
			}

			if (!$display) {
				$display = (strpos($line, ' SQL') !== false);
			}

			break;
		case 4: /* errors only */
			$display = (strpos($line, 'ERROR') !== false);

			break;
		case 5: /* errors + */
			$display = (strpos($line, 'ERROR') !== false);

			if (!$display) {
				$display = (strpos($line, 'DEBUG') !== false);
			}

			if (!$display) {
				$display = (strpos($line, ' SQL') !== false);
			}

			break;
		case 6: /* debug only */
			$display = (strpos($line, 'DEBUG') !== false && strpos($line, ' SQL ') === false);

			break;
		case 7: /* sql calls only */
			$display = (strpos($line, ' SQL ') !== false);

			break;
		case 8: /* AutoM8 Only */
			$display = (strpos($line, 'AUTOM8') !== false);

			break;
		case 9: /* Non Stats */
			$display = (strpos($line, 'STATS') === false);

			break;
		case 10: /* Boost Only*/
			$display = (strpos($line, 'BOOST') !== false);

			break;
		case 11: /* device events + */
			$display = (strpos($line, 'HOST EVENT') !== false);

			if (!$display) {
				$display = (strpos($line, '] is recovering!') !== false);
			}

			if (!$display) {
				$display = (strpos($line, '] is down!') !== false);
			}

			break;
		case 12: /* Assertions */
			$display = (strpos($line, 'ASSERT FAILED') !== false);

			if (!$display) {
				$display = (strpos($line, 'Recache Event') !== false);
			}

			break;
		case -1: /* all */
			$display = true;

			break;

		default: /* all other lines */
			if ($thold_enabled) {
				if ($message_type == 99) {
					$display = (strpos($line, 'THOLD: Threshold') !== false);
				}
			} else {
				$display = true;
			}
	}

	/* match any lines that match the search string */
	if ($display === true && $filter != '') {
		if (stripos($line, $filter) !== false) {
			return $line;
		}

		if (validate_is_regex($filter) && preg_match('/' . $filter . '/i', $line)) {
			return $line;
		}

		return false;
	}

	return $display;
}

/**
 * update_host_status - updates the host table with information about its status.
 * It will also output to the appropriate log file when an event occurs.
 *
 * @param $status - (int constant) the status of the host (Up/Down)
 * @param $host_id - (int) the host ID for the results
 * @param $ping - (class array) results of the ping command.
 */
function update_host_status(int $status, int $host_id, Net_Ping &$ping, int $ping_availability, bool $print_data_to_stdout) {
	$issue_log_message   = false;
	$ping_failure_count  = read_config_option('ping_failure_count');
	$ping_recovery_count = read_config_option('ping_recovery_count');

	$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	/* initialize fail and recovery dates correctly */
	if ($host['status_fail_date'] == '') {
		$host['status_fail_date'] = strtotime('0000-00-00 00:00:00');
	}

	if ($host['status_rec_date'] == '') {
		$host['status_rec_date'] = strtotime('0000-00-00 00:00:00');
	}

	if ($status == HOST_DOWN) {
		/* Set initial date down. BUGFIX */
		if (empty($host['status_fail_date'])) {
			$host['status_fail_date'] = time();
		}

		/* update total polls, failed polls and availability */
		$host['failed_polls']++;
		$host['total_polls']++;
		$host['availability'] = 100 * ($host['total_polls'] - $host['failed_polls']) / $host['total_polls'];

		/* determine the error message to display */
		if (($ping_availability == AVAIL_SNMP_AND_PING) || ($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($host['snmp_community'] == '') && ($host['snmp_version'] != 3)) {
				/* snmp version 1/2 without community string assume SNMP test to be successful
				   due to backward compatibility issues */
				$host['status_last_error'] = $ping->ping_response;
			} else {
				$host['status_last_error'] = $ping->snmp_response . ', ' . $ping->ping_response;
			}
		} elseif ($ping_availability == AVAIL_SNMP) {
			if (($host['snmp_community'] == '') && ($host['snmp_version'] != 3)) {
				$host['status_last_error'] = 'Device does not require SNMP';
			} else {
				$host['status_last_error'] = $ping->snmp_response;
			}
		} else {
			$host['status_last_error'] = $ping->ping_response;
		}

		/* determine if to send an alert and update remainder of statistics */
		if ($host['status'] == HOST_UP) {
			/* increment the event failure count */
			$host['status_event_count']++;

			/* if it's time to issue an error message, indicate so */
			if ($host['status_event_count'] >= $ping_failure_count) {
				/* host is now down, flag it that way */
				$host['status'] = HOST_DOWN;

				$issue_log_message = true;

				$host['status_fail_date'] = time();

				$host['status_event_count'] = 0;
			}
		} elseif ($host['status'] == HOST_RECOVERING) {
			/* host is recovering, put back in failed state */
			$host['status_event_count'] = 1;
			$host['status']             = HOST_DOWN;
		} elseif ($host['status'] == HOST_UNKNOWN) {
			/* host was unknown and now is down */
			$host['status']             = HOST_DOWN;
			$host['status_event_count'] = 0;
		} else {
			$host['status_event_count']++;
		}
	} else {
		/* host is up!  Update total polls and availability */
		$host['total_polls']++;
		$host['availability'] = 100 * ($host['total_polls'] - $host['failed_polls']) / $host['total_polls'];

		if ((($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING) ||
			($ping_availability == AVAIL_SNMP)) &&
			(!is_numeric($ping->snmp_status))) {
			$ping->snmp_status = 0.000;
		}

		if ((($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING) ||
			($ping_availability == AVAIL_PING)) &&
			(!is_numeric($ping->ping_status))) {
			$ping->ping_status = 0.000;
		}

		/* determine the ping statistic to set and do so */
		if (($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($host['snmp_community'] == '') && ($host['snmp_version'] != 3)) {
				$ping_time = 0.000;
			} else {
				/* calculate the average of the two times */
				$ping_time = ($ping->snmp_status + $ping->ping_status) / 2;
			}
		} elseif ($ping_availability == AVAIL_SNMP) {
			if (($host['snmp_community'] == '') && ($host['snmp_version'] != 3)) {
				$ping_time = 0.000;
			} else {
				$ping_time = $ping->snmp_status;
			}
		} elseif ($ping_availability == AVAIL_NONE) {
			$ping_time = 0.000;
		} else {
			$ping_time = $ping->ping_status;
		}

		/* update times as required */
		if (is_numeric($ping_time)) {
			$host['cur_time'] = $ping_time;

			/* maximum time */
			if ($ping_time > $host['max_time']) {
				$host['max_time'] = $ping_time;
			}

			/* minimum time */
			if ($ping_time < $host['min_time']) {
				$host['min_time'] = $ping_time;
			}

			/* average time */
			$host['avg_time'] = (($host['total_polls'] - 1 - $host['failed_polls'])
				* $host['avg_time'] + $ping_time) / ($host['total_polls'] - $host['failed_polls']);
		}

		/* the host was down, now it's recovering */
		if (($host['status'] == HOST_DOWN) || ($host['status'] == HOST_RECOVERING)) {
			/* just up, change to recovering */
			if ($host['status'] == HOST_DOWN) {
				$host['status']             = HOST_RECOVERING;
				$host['status_event_count'] = 1;
			} else {
				$host['status_event_count']++;
			}

			/* if it's time to issue a recovery message, indicate so */
			if ($host['status_event_count'] >= $ping_recovery_count) {
				/* host is up, flag it that way */
				$host['status'] = HOST_UP;

				$issue_log_message = true;

				$host['status_rec_date']    = time();
				$host['status_event_count'] = 0;
			}
		} else {
			/* host was unknown and now is up */
			$host['status'] = HOST_UP;

			$host['status_event_count'] = 0;
		}
	}

	/* if the user wants a flood of information then flood them */
	if ($host['status'] == HOST_UP || $host['status'] == HOST_RECOVERING) {
		/* log ping result if we are to use a ping for reachability testing */
		if ($ping_availability == AVAIL_SNMP_AND_PING) {
			cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
			cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		} elseif ($ping_availability == AVAIL_SNMP) {
			if ($host['snmp_community'] == '' && $host['snmp_version'] != 3) {
				cacti_log("Device[$host_id] SNMP: Device does not require SNMP", $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
			} else {
				cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
			}
		} else {
			cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		}
	} else {
		if ($ping_availability == AVAIL_SNMP_AND_PING) {
			cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
			cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		} elseif ($ping_availability == AVAIL_SNMP) {
			cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		} else {
			cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		}
	}

	/* if there is supposed to be an event generated, do it */
	if ($issue_log_message) {
		if ($host['status'] == HOST_DOWN) {
			cacti_log("Device[$host_id] ERROR: HOST EVENT: Device is DOWN Message: " . $host['status_last_error'], $print_data_to_stdout);
		} else {
			cacti_log("Device[$host_id] NOTICE: HOST EVENT: Device Returned FROM DOWN State: ", $print_data_to_stdout);
		}
	}

	db_execute_prepared('UPDATE host SET
		status = ?,
		status_event_count = ?,
		status_fail_date = FROM_UNIXTIME(?),
		status_rec_date = FROM_UNIXTIME(?),
		status_last_error = ?,
		min_time = ?,
		max_time = ?,
		cur_time = ?,
		avg_time = ?,
		total_polls = ?,
		failed_polls = ?,
		availability = ?
		WHERE hostname = ?
		AND deleted = ""',
		array(
			$host['status'],
			$host['status_event_count'],
			$host['status_fail_date'],
			$host['status_rec_date'],
			$host['status_last_error'],
			$host['min_time'],
			$host['max_time'],
			$host['cur_time'],
			$host['avg_time'],
			$host['total_polls'],
			$host['failed_polls'],
			$host['availability'],
			$host['hostname']
		)
	);
}

/**
 * is_hexadecimal - test whether a string represents a hexadecimal number,
 * ignoring space and tab, and case insensitive.
 *
 * @param $result - the string to test
 * @param 1 if the argument is hex, 0 otherwise, and false on error
 */
function is_hexadecimal($result) {
	$hexstr = str_replace(array(' ', '-'), ':', trim($result));

	$parts = explode(':', $hexstr);

	foreach ($parts as $part) {
		if (strlen($part) != 2) {
			return false;
		}

		if (ctype_xdigit($part) == false) {
			return false;
		}
	}

	return true;
}

/**
 * strip_domain - removes the domain from a hostname
 *
 * @param $hostname - the hostname for a device
 *
 * @return mixed the stripped hostname
 */
function strip_domain($hostname) {
	if (is_ipaddress($hostname)) {
		return $hostname;
	}

	if (read_config_option('strip_domain') == 'on') {
		$parts = explode('.', $hostname);

		return $parts[0];
	} else {
		return $hostname;
	}
}

/**
 * is_mac_address - determines if the result value is a mac address
 *
 * @param $result - some string to be evaluated
 *
 * @return mixed either to result is a mac address of not
 */
function is_mac_address($result) {
	if (!defined('FILTER_VALIDATE_MAC')) {
		if (preg_match('/^([0-9a-f]{1,2}[\.:-]) {5}([0-9a-f]{1,2})$/i', $result)) {
			return true;
		} else {
			return false;
		}
	} else {
		return filter_var($result, FILTER_VALIDATE_MAC);
	}
}

/**
 * Determines if string is a hex value
 *
 * WARNING: The passed parameter may be altered by
 * this function
 *
 * @param  string  $result
 *
 * @return boolean
 */
function is_hex_string(&$result) {
	if ($result == '') {
		return false;
	}

	$compare = strtolower($result);

	/* strip off the 'Hex:, Hex-, and Hex-STRING:'
	 * Hex- is considered due to the stripping of 'String:' in
	 * lib/snmp.php
	 */
	if (substr($compare, 0, 4) == 'hex-') {
		$check = trim(str_ireplace('hex-', '', $result));
	} elseif (substr($compare, 0, 11) == 'hex-string:') {
		$check = trim(str_ireplace('hex-string:', '', $result));
	} else {
		return false;
	}

	$parts = explode(' ', $check);

	/* assume if something is a hex string
	   it will have a length > 1 */
	if (cacti_sizeof($parts) == 1) {
		return false;
	}

	foreach ($parts as $part) {
		if (strlen($part) != 2) {
			return false;
		}

		if (ctype_xdigit($part) == false) {
			return false;
		}
	}

	$result = $check;

	return true;
}

/**
 * prepare_validate_result - determines if the result value is valid or not.  If not valid returns a "U"
 *
 * @param $result - the result from the poll, the result can be modified in the call
 *
 * @return mixed either to result is valid or not
 */
function prepare_validate_result(&$result) {
	/* first trim the string */
	$result = trim($result, "'\"\n\r");

	/* clean off ugly non-numeric data */
	if (is_numeric($result)) {
		dsv_log('prepare_validate_result', 'data is numeric', POLLER_VERBOSITY_MEDIUM);

		return true;
	}

	if ($result == 'U') {
		dsv_log('prepare_validate_result', 'data is U', POLLER_VERBOSITY_MEDIUM);

		return true;
	}

	if (is_hexadecimal($result)) {
		dsv_log('prepare_validate_result', 'data is hex', POLLER_VERBOSITY_MEDIUM);

		return hexdec($result);
	}

	if (substr_count($result, ':') || substr_count($result, '!')) {
		/* looking for name value pairs */
		if (substr_count($result, ' ') == 0) {
			dsv_log('prepare_validate_result', 'data has no spaces', POLLER_VERBOSITY_MEDIUM);

			return true;
		} else {
			$delim_cnt = 0;

			if (substr_count($result, ':')) {
				$delim_cnt = substr_count($result, ':');
			} elseif (strstr($result, '!')) {
				$delim_cnt = substr_count($result, '!');
			}

			$space_cnt = substr_count(trim($result), ' ');

			dsv_log('prepare_validate_result', "data has $space_cnt spaces and $delim_cnt fields which is " . (($space_cnt + 1 == $delim_cnt) ? '' : 'NOT') . ' okay', POLLER_VERBOSITY_MEDIUM);

			return ($space_cnt + 1 == $delim_cnt);
		}
	} else {
		$result = strip_alpha($result);

		if ($result === false) {
			$result = 'U';

			return false;
		} else {
			return true;
		}
	}
}

/**
 * strip_alpha - remove non-numeric data from a string and return the numeric part
 *
 * @param $string - the string to be evaluated
 *
 * @return mixed either the numeric value or false if not numeric
 */
function strip_alpha($string) {
	/* strip all non numeric data */
	$string = trim(preg_replace('/[^0-9,.+-]/', '', $string));

	/* check the easy cases first */
	/* it has no delimiters, and no space, therefore, must be numeric */
	if (is_numeric($string) || is_float($string)) {
		return $string;
	} else {
		return false;
	}
}

/**
 * is_valid_pathname - takes a pathname are verifies it matches file name rules
 *
 * @param $path - the pathname to be tested
 *
 * @return mixed either true or false
 */
function is_valid_pathname($path) {
	if (preg_match('/^([a-zA-Z0-9\_\.\-\\\:\/]+)$/', trim($path))) {
		return true;
	} else {
		return false;
	}
}

/**
 * dsv_log - provides debug logging when tracing Graph/Data Source creation
 *
 * @param $message - the message to output to the log
 * @param $data  - the data to be carried with the message
 */
function dsv_log($message, $data = null, $level = POLLER_VERBOSITY_LOW) {
	if (read_config_option('data_source_trace') == 'on') {
		cacti_log(($message . ' = ') . (is_array($data) ? json_encode($data) : ($data === null ? 'NULL' : $data)), false, 'DSTRACE', $level);
	}
}

/**
 * test_data_sources
 *
 * Tests all data sources to confirm that it returns valid data.  This
 * function is used by automation to prevent the creation of graphs
 * that will never generate data.
 *
 * @param $graph_template_id - The Graph Template to test
 * @param $host_id - The Host to test
 * @param mixed $snmp_query_id
 * @param mixed $snmp_index
 * @param mixed $values
 *
 * @return boolean true or false
 */
function test_data_sources($graph_template_id, $host_id, $snmp_query_id = 0, $snmp_index = '', $values = array()) {
	$data_template_ids = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT data_template_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			WHERE gti.hash != ""
			AND gti.local_graph_id = 0
			AND dtr.local_data_id = 0
			AND gti.graph_template_id = ?',
			array($graph_template_id)),
		'data_template_id', 'data_template_id'
	);

	$test_source = db_fetch_cell_prepared('SELECT test_source
		FROM graph_templates
		WHERE id = ?',
		array($graph_template_id));

	if (cacti_sizeof($data_template_ids) && $test_source == 'on') {
		foreach ($data_template_ids as $dt) {
			dsv_log('test_data_source', array( 'dt' => $dt, 'host_id' => $host_id, 'snmp_query_id' => $snmp_query_id, 'snmp_index' => $snmp_index, 'values' => $values));

			if (!test_data_source($dt, $host_id, $snmp_query_id, $snmp_index, $values)) {
				return false;
			}
		}
	}

	return true;
}

/**
 * test_data_source
 *
 * Tests a single data source to confirm that it returns valid data.  This
 * function is used by automation to prevent the creation of graphs
 * that will never generate data.
 *
 * @param $graph_template_id - The Graph Template to test
 * @param $host_id - The Host to test
 * @param mixed $data_template_id
 * @param mixed $snmp_query_id
 * @param mixed $snmp_index
 * @param mixed $suggested_vals
 *
 * @return boolean true or false
 */
function test_data_source($data_template_id, $host_id, $snmp_query_id = 0, $snmp_index = '', $suggested_vals = array()) {
	global $called_by_script_server;

	$called_by_script_server = true;

	dsv_log('test_data_source', array('data_template_id' => $data_template_id, 'host_id' => $host_id, 'snmp_query_id' => $snmp_query_id, 'snmp_index' => $snmp_index, 'suggested_vals' => $suggested_vals));

	$data_input = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		di.id, di.type_id, dtd.id AS data_template_data_id,
		dtd.data_template_id, dtd.active, dtd.rrd_step, di.name
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id=di.id
		WHERE dtd.local_data_id = 0
		AND dtd.data_template_id = ?',
		array($data_template_id));

	dsv_log('data_input', $data_input);

	$host = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host
		WHERE id = ?',
		array($host_id));

	dsv_log('host', $host);

	$data_template_data_id = 0;

	if (cacti_sizeof($data_input) && $data_input['active'] == 'on') {
		$data_template_data_id = $data_input['data_template_data_id'];
		/* we have to perform some additional sql queries if this is a 'query' */
		if (($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)) {
			$field = data_query_field_list($data_template_data_id);

			dsv_log('query field', $field);

			$params   = array();
			$params[] = $data_input['data_template_id'];

			if ($field['output_type'] != '') {
				$output_type_sql = ' AND sqgr.snmp_query_graph_id = ?';
				$params[]        = $field['output_type'];
			} else {
				$output_type_sql = '';
			}

			$outputs_sql = 'SELECT DISTINCT ' . SQL_NO_CACHE . "
				sqgr.snmp_field_name, dtr.id as data_template_rrd_id
				FROM snmp_query_graph_rrd AS sqgr
				INNER JOIN data_template_rrd AS dtr
				ON sqgr.data_template_rrd_id = dtr.id
				WHERE sqgr.data_template_id = ?
				AND dtr.local_data_id = 0
				$output_type_sql
				ORDER BY dtr.id";

			dsv_log('outputs_sql', $outputs_sql);
			dsv_log('outputs_params', $params);

			$outputs = db_fetch_assoc_prepared($outputs_sql, $params);

			dsv_log('outputs', $outputs);
		}

		if (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER)) {
			if ($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER) {
				$action = POLLER_ACTION_SCRIPT_PHP;
			} else {
				$action = POLLER_ACTION_SCRIPT;
			}

			$script_path = get_full_test_script_path($data_template_id, $host_id);

			dsv_log('script_path', $script_path);

			$num_output_fields_sql = 'SELECT ' . SQL_NO_CACHE . ' id
				FROM data_input_fields
				WHERE data_input_id = ?
				AND input_output = "out"
				AND update_rra="on"';

			dsv_log('num_output_fields_sql', $num_output_fields_sql);

			$num_output_fields = cacti_sizeof(db_fetch_assoc_prepared($num_output_fields_sql, array($data_input['id'])));

			dsv_log('num_output_fields', $num_output_fields);

			if ($num_output_fields == 1) {
				$data_template_rrd_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' id
					FROM data_template_rrd
					WHERE local_data_id = 0
					AND hash != ""
					AND data_template_id = ?',
					array($data_template_id));

				$data_source_item_name = get_data_source_item_name($data_template_rrd_id);
			} else {
				$data_source_item_name = '';
			}

			dsv_log('data_source_item_name', $data_source_item_name);

			if ($action == POLLER_ACTION_SCRIPT) {
				dsv_log('script_path', $script_path);

				$output = shell_exec($script_path);
			} else {
				// Script server is a bit more complicated
				$php   = read_config_option('path_php_binary');
				$parts = explode(' ', $script_path);

				dsv_log('parts', $parts);

				if (file_exists($parts[0])) {
					unset($parts[1]);

					$script = implode(' ', $parts);

					dsv_log('script', $script);

					$output = shell_exec("$php -q $script");

					if ($output == '' || $output == false) {
						$output = 'U';
					} elseif (strpos($output, ':U') !== false) {
						$output = 'U';
					}
				} else {
					$output = 'U';
				}
			}

			dsv_log('output', $output);

			if (!is_numeric($output)) {
				if ($output == 'U') {
					return false;
				}

				if (prepare_validate_result($output) === false) {
					return false;
				}
			}

			return true;
		}

		if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP) {
			/* get host fields first */
			$host_fields_sql = 'SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE "snmp_%" OR type_code IN("hostname","host_id"))
				AND did.data_template_data_id = ?
				AND did.value != ""';

			dsv_log('host_fields_sql', $host_fields_sql);
			dsv_log('host_fields_sql_params', array('data_template_data_id' => $data_template_data_id));

			$host_fields = array_rekey(
				db_fetch_assoc_prepared($host_fields_sql,
					array($data_template_data_id)),
				'type_code', 'value'
			);

			dsv_log('SNMP host_fields', $host_fields);

			$data_template_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id = did.data_input_field_id
				WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
				AND did.data_template_data_id = ?',
				array($data_template_data_id));

			dsv_log('SNMP data_template_data', $data_template_data);

			if (cacti_sizeof($data_template_data)) {
				foreach ($data_template_data as $field) {
					$key   = $field['type_code'];
					$value = $field['value'];

					dsv_log('SNMP field', $field);
					dsv_log('SNMP value', $value);

					if (!empty($suggested_vals['custom_data'][$data_template_id][$field['id']])) {
						$value = $suggested_vals['custom_data'][$data_template_id][$field['id']];

						dsv_log("SNMP value replace suggested $key", $value);
					}

					if (!empty($value) && !isset($host_fields[$key])) {
						$host_fields[$key] = $value;

						dsv_log("SNMP value replace template $key", $value);
					}
				}
			}

			dsv_log('SNMP [updated] host_fields', $host_fields);

			$host = array_merge($host, $host_fields);

			dsv_log('SNMP [updated] host', $host);

			$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
				$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
				$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

			$output = cacti_snmp_session_get($session, $host['snmp_oid']);

			dsv_log('SNMP output', $output);

			if (!is_numeric($output)) {
				if (prepare_validate_result($output) === false) {
					return false;
				}
			}

			return true;
		}

		if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) {
			$snmp_queries = get_data_query_array($snmp_query_id);

			/* get host fields first */
			$host_fields = array_rekey(
				db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
					FROM data_input_fields AS dif
					LEFT JOIN data_input_data AS did
					ON dif.id=did.data_input_field_id
					WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
					AND did.data_template_data_id = ?
					AND did.value != ""', array($data_template_data_id)),
				'type_code', 'value'
			);

			dsv_log('SNMP_QUERY host_fields', $host_fields);

			$data_template_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
				AND did.data_template_data_id = ?',
				array($data_template_data_id));

			dsv_log('SNMP_QUERY data_template_data', $data_template_data);

			if (cacti_sizeof($data_template_data)) {
				foreach ($data_template_data as $field) {
					$key   = $field['type_code'];
					$value = $field['value'];

					dsv_log('SNMP_QUERY field', $field);

					if (!empty($suggested_vals['custom_data'][$data_template_id][$field['id']])) {
						$value = $suggested_vals['custom_data'][$data_template_id][$field['id']];

						dsv_log("SNMP_QUERY value replace suggested $key", $value);
					}

					if (!empty($value) && !isset($host_fields[$key])) {
						$host_fields[$key] = $value;

						dsv_log("SNMP_QUERY value replace template $key", $value);
					}
				}
			}

			dsv_log('SNMP_QUERY [updated] host_fields', $host_fields);

			$host = array_merge($host, $host_fields);

			dsv_log('SNMP_QUERY [updated] host', $host);

			if (cacti_sizeof($outputs) && cacti_sizeof($snmp_queries)) {
				foreach ($outputs as $output) {
					if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid'])) {
						$oid = $snmp_queries['fields'][$output['snmp_field_name']]['oid'] . '.' . $snmp_index;

						if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'])) {
							$oid .= '.' . $snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'];
						}
					}

					if (!empty($oid)) {
						$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
							$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
							$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
							$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

						$output = cacti_snmp_session_get($session, $oid);

						if (!is_numeric($output)) {
							if (prepare_validate_result($output) === false) {
								return false;
							}
						}

						return true;
					}
				}
			}
		} elseif (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)) {
			$script_queries = get_data_query_array($snmp_query_id);

			/* get host fields first */
			$host_fields = array_rekey(
				db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
					FROM data_input_fields AS dif
					LEFT JOIN data_input_data AS did
					ON dif.id=did.data_input_field_id
					WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
					AND did.data_template_data_id = ?
					AND did.value != ""', array($data_template_data_id)),
				'type_code', 'value'
			);

			dsv_log('SCRIPT host_fields', $host_fields);

			$data_template_fields = array_rekey(
				db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
					FROM data_input_fields AS dif
					LEFT JOIN data_input_data AS did
					ON dif.id=did.data_input_field_id
					WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
					AND did.data_template_data_id = ?
					AND did.value != ""', array($data_template_data_id)),
				'type_code', 'value'
			);

			$data_template_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.id, dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
				AND did.data_template_data_id = ?',
				array($data_template_data_id));

			dsv_log('SCRIPT data_template_data', $data_template_data);

			if (cacti_sizeof($data_template_data)) {
				foreach ($data_template_data as $field) {
					$key   = $field['type_code'];
					$value = $field['value'];

					dsv_log('SCRIPT field', $field);

					if (!empty($suggested_vals['custom_data'][$data_template_id][$field['id']])) {
						$value = $suggested_vals['custom_data'][$data_template_id][$field['id']];

						dsv_log("SCRIPT value replace suggested $key", $value);
					}

					if (!empty($value) && !isset($host_fields[$key])) {
						$host_fields[$key] = $value;

						dsv_log("SCRIPT value replace template $key", $value);
					}
				}
			}

			dsv_log('SCRIPT [updated] host_fields', $host_fields);

			$host = array_merge($host, $host_fields);

			dsv_log('SCRIPT [updated] host', $host);

			if (cacti_sizeof($outputs) && cacti_sizeof($script_queries)) {
				foreach ($outputs as $output) {
					if (isset($script_queries['fields'][$output['snmp_field_name']]['query_name'])) {
						$identifier = $script_queries['fields'][$output['snmp_field_name']]['query_name'];

						if ($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) {
							$action = POLLER_ACTION_SCRIPT;

							$prepend = '';

							if (isset($script_queries['arg_prepend']) && $script_queries['arg_prepend'] != '') {
								$prepend = $script_queries['arg_prepend'];
							}

							$script_path = read_config_option('path_php_binary') . ' -q ' . get_script_query_path(trim($prepend . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $snmp_index), $script_queries['script_path'], $host_id);
						} else {
							$action      = POLLER_ACTION_SCRIPT;
							$script_path = get_script_query_path(trim((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $snmp_index), $script_queries['script_path'], $host_id);
						}
					}

					if (isset($script_path)) {
						$output = shell_exec($script_path);

						if (!is_numeric($output)) {
							if (prepare_validate_result($output) === false) {
								return false;
							}
						}

						return true;
					}
				}
			}
		}
	}

	return false;
}

/**
 * Gets the full path to the script to execute to obtain data for a
 * given data template for testing. this function does not work on
 * SNMP actions, only script-based actions
 *
 * @param int $data_template_id    The ID of the data template
 * @param int $host_id             The ID of the host device
 *
 * @return string|bool the full script path or (bool) false for an error
 */
function get_full_test_script_path(int $data_template_id, int $host_id):string|false {
	global $config;

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		dtd.id,
		dtd.data_input_id,
		di.type_id,
		di.input_string
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id = di.id
		WHERE dtd.local_data_id = 0
		AND dtd.data_template_id = ?',
		array($data_template_id));

	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . " dif.data_name, did.value
		FROM data_input_fields AS dif
		LEFT JOIN data_input_data AS did
		ON dif.id = did.data_input_field_id
		WHERE dif.data_input_id  = ?
		AND did.data_template_data_id = ?
		AND dif.input_output = 'in'",
		array($data_source['data_input_id'], $data_source['id']));

	$full_path = $data_source['input_string'];

	$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	if (cacti_sizeof($data)) {
		foreach ($data as $item) {
			if (isset($host[$item['data_name']])) {
				$value = cacti_escapeshellarg($host[$item['data_name']]);
			} elseif ($item['data_name'] == 'host_id' || $item['data_name'] == 'hostid') {
				$value = cacti_escapeshellarg($host['id']);
			} else {
				$value = "'" . $item['value'] . "'";
			}

			$full_path = str_replace('<' . $item['data_name'] . '>', $value, $full_path);
		}
	}

	$search    = array('<path_cacti>', '<path_snmpget>', '<path_php_binary>');
	$replace   = array(CACTI_PATH_BASE, read_config_option('path_snmpget'), read_config_option('path_php_binary'));
	$full_path = str_replace($search, $replace, $full_path);

	/**
	 * sometimes a certain input value will not have anything entered... null out these fields
	 * in the input string so we don't mess up the script
	 */
	return preg_replace('/(<[A-Za-z0-9_]+>)+/', '', $full_path);
}

/**
 * get_full_script_path - gets the full path to the script to execute to obtain data for a
 * given data source. this function does not work on SNMP actions, only script-based actions
 *
 * @param int $local_data_id The ID of the data source
 *
 * @return string|false the full script path or (bool) false for an error
 */
function get_full_script_path(int $local_data_id):string|false {
	global $config;

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' dtd.id, dtd.data_input_id,
		di.type_id, di.input_string
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id = di.id
		WHERE dtd.local_data_id = ?',
		array($local_data_id));

	/* snmp-actions don't have paths */
	if (($data_source['type_id'] == DATA_INPUT_TYPE_SNMP) || ($data_source['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY)) {
		return false;
	}

	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . " dif.data_name, did.value
		FROM data_input_fields AS dif
		LEFT JOIN data_input_data AS did
		ON dif.id = did.data_input_field_id
		WHERE dif.data_input_id = ?
		AND did.data_template_data_id = ?
		AND dif.input_output = 'in'",
		array($data_source['data_input_id'], $data_source['id']));

	$full_path = $data_source['input_string'];

	if (cacti_sizeof($data)) {
		foreach ($data as $item) {
			$value = cacti_escapeshellarg($item['value']);

			if ($value == '') {
				$value = "''";
			}

			$full_path = str_replace('<' . $item['data_name'] . '>', $value, $full_path);
		}
	}

	$search    = array('<path_cacti>', '<path_snmpget>', '<path_php_binary>');
	$replace   = array(CACTI_PATH_BASE, read_config_option('path_snmpget'), read_config_option('path_php_binary'));
	$full_path = str_replace($search, $replace, $full_path);

	/* sometimes a certain input value will not have anything entered... null out these fields
	in the input string so we don't mess up the script */
	return preg_replace('/(<[A-Za-z0-9_]+>)+/', '', $full_path);
}

/**
 * get_data_source_item_name - gets the name of a data source item or generates a new one if one does not
 * already exist
 *
 * @param int $data_template_rrd_id - (int) the ID of the data source item
 *
 * @return string|false the name of the data source item or an empty string for an error
 */
function get_data_source_item_name(int $data_template_rrd_id):string|false {
	if (empty($data_template_rrd_id)) {
		return '';
	}

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		dtr.data_source_name, dtd.name
		FROM data_template_rrd AS dtr
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		WHERE dtr.id = ?',
		array($data_template_rrd_id)
	);

	/* use the cacti ds name by default or the user defined one, if entered */
	if (empty($data_source['data_source_name'])) {
		/* limit input to 19 characters */
		$data_source_name = clean_up_name($data_source['name']);
		$data_source_name = substr(strtolower($data_source_name), 0, (19 - strlen('' .$data_template_rrd_id))) . $data_template_rrd_id;

		return $data_source_name;
	} else {
		return $data_source['data_source_name'];
	}
}

/**
 * Gets the full path to the .rrd file associated with a given data source
 *
 * @param int  $local_data_id  The ID of the data source
 * @param bool $expand_paths   Whether to expand the <path_rra> variable into its full path or not
 *
 * @return string the full path to the data source or an empty string for an error
 */
function get_data_source_path(int $local_data_id, bool $expand_paths): string {
	static $data_source_path_cache = array();

	if (empty($local_data_id)) {
		return '';
	}

	if (isset($data_source_path_cache[$local_data_id])) {
		return $data_source_path_cache[$local_data_id];
	}

	$data_source = db_fetch_row_prepared('SELECT name, data_source_path
		FROM data_template_data AS dtd
		WHERE local_data_id = ?',
		array($local_data_id));

	if (cacti_sizeof($data_source)) {
		if (empty($data_source['data_source_path'])) {
			/* no custom path was specified */
			$data_source_path = generate_data_source_path($local_data_id);
		} elseif (!strstr($data_source['data_source_path'], '/')) {
			$data_source_path = '<path_rra>/' . $data_source['data_source_path'];
		} else {
			$data_source_path = $data_source['data_source_path'];
		}

		/* whether to show the "actual" path or the <path_rra> variable name (for edit boxes) */
		if ($expand_paths == true) {
			$data_source_path = str_replace('<path_rra>/', CACTI_PATH_RRA . '/', $data_source_path);
		}

		$data_source_path_cache[$local_data_id] = $data_source_path;

		return $data_source_path;
	}

	return '';
}

/**
 * stri_replace - a case insensitive string replace
 *
 * @param string $find - needle
 * @param string $replace - replace needle with this
 * @param string $string - haystack
 *
 * @return string the original string with '$find' replaced by '$replace'
 */
function stri_replace(string $find, string $replace, string $string):string {
	$parts = explode(strtolower($find), strtolower($string));

	$pos = 0;

	$findLength = strlen($find);

	foreach ($parts as $key => $part) {
		$partLength = strlen($part);

		$parts[$key] = substr($string, $pos, $partLength);
		$pos += $partLength + $findLength;
	}

	return (join($replace, $parts));
}

/**
 * Parses a string using a regular expression designed to remove
 * new lines and the spaces around them
 *
 * @param null|string $string the string to modify/clean
 *
 * @return null|string the modified string
 */
function clean_up_lines(?string $string): ?string {
	if ($string !== null) {
		$string = preg_replace('/\s*[\r\n]+\s*/',' ', $string);
	}

	return $string;
}

/**
 * Parses a string using a series of regular expressions designed to
 * eliminate "bad" characters
 *
 * @param null|string $string the string to modify/clean
 *
 * @return null|string the modified string
 */
function clean_up_name(?string $string): ?string {
	if ($string !== null) {
		$string = preg_replace('/[\s\.]+/', '_', $string);
		$string = preg_replace('/[^a-zA-Z0-9_]+/', '', $string);
		$string = preg_replace('/_{2,}/', '_', $string);
	}

	return $string;
}

/**
 * Parses a string using a series of regular expressions designed to
 * eliminate "bad" characters
 *
 * @param null|string $string - the string to modify/clean
 *
 * @return null|string the modified string
 */
function clean_up_file_name(?string $string): ?string {
	if ($string !== null) {
		$string = preg_replace('/[\s\.]+/', '_', $string);
		$string = preg_replace('/[^a-zA-Z0-9_-]+/', '', $string);
		$string = preg_replace('/_{2,}/', '_', $string);
	}

	return $string;
}

/**
 * Parses a string and makes sure it contains the correct directory
 * separators based on the current operating system
 *
 * @param null|string $path - the path to modify
 *
 * @return null|string the modified path
 */
function clean_up_path(?string $path): ?string {
	global $config;

	if ($path !== null) {
		if ($config['cacti_server_os'] == 'win32') {
			$path = str_replace('/', '\\', $path);
		} elseif ($config['cacti_server_os'] == 'unix' || read_config_option('using_cygwin') == 'on' || read_config_option('storage_location')) {
			$path = str_replace('\\', '/', $path);
		}
	}

	return $path;
}

/**
 * Returns the title of a data source without using the title cache
 *
 * @param int $local_data_id - (int) the ID of the data source to get a title for
 *
 * @return string the data source title
 */
function get_data_source_title(int $local_data_id):string {
	$data = db_fetch_row_prepared('SELECT
		dl.host_id, dl.snmp_query_id, dl.snmp_index, dl.data_template_id,
		dtd.name, dtd.id as template_id
		FROM data_local AS dl
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = dl.id
		WHERE dl.id = ?',
		array($local_data_id));

	$title = 'Missing Datasource ' . $local_data_id;

	if (cacti_sizeof($data)) {
		if (strstr($data['name'], '|') !== false && $data['host_id'] > 0) {
			$data['name'] = substitute_data_input_data($data['name'], '', $local_data_id);
			$title        = expand_title($data['host_id'], $data['snmp_query_id'], $data['snmp_index'], $data['name']);
		} else {
			$title = $data['name'];
		}

		// Is the data source linked to a template?  If so, make sure we have a template on the
		// LEFT JOIN since it may not find one.  Also, we can't check that they are the same ID
		// ID yet because there are two, one with a 0 local_data_id (base template) and one with
		// this source's id (instance of template).
		if ($data['data_template_id'] && !$data['template_id']) {
			$title .= ' (Bad template "' . $data['data_template_id'] . '")';
		}
	}

	return $title;
}

/**
 * Returns a cached array of titles.  Note that this
 * includes all titles found durng previous calls to
 * this function in addition to those passed
 *
 * @param  array $local_data_ids
 *
 * @return array
 */
function get_data_source_titles(array $local_data_ids) {
	static $title_cache = null;

	$local_data_ids = cacti_unique_ids($local_data_ids);

	$titles = array();

	foreach ($local_data_ids as $local_data_id) {
		if (!array_key_exists($local_data_id, $titles)) {
			if (!isset($title_cache[$local_data_id])) {
				$title_cache[$local_data_id] = get_data_source_title($local_data_id);
			}

			$titles[$local_data_id] = $title_cache[$local_data_id];
		}
	}

	return $titles;
}

/**
 * Gets the description of the device in cacti host table
 *
 * @param int $host_id the ID of the device to get a description for
 *
 * @return string|false the device name
 */
function get_device_name(int $host_id):string|false {
	return db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id));
}

/**
 * Gets the hex color value from the cacti colors table
 *
 * @param int $color_id the ID of the cacti color
 * @return string|false the hex color value
 *
 */
function get_color(int $color_id):string|false {
	return db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', array($color_id));
}

// TODO: This marker is to identifer where to resume typing and PHPDoc syntax updating

/**
 * get_graph_title_cache - returns the title of the graph using the title cache
 *
 * @param $local_graph_id - (int) the ID of the graph to get the title for
 *
 * @return mixed the graph title
 */
function get_graph_title_cache($local_graph_id) {
	return db_fetch_cell_prepared('SELECT title_cache
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($local_graph_id));
}

/**
 * Returns the title of a graph without using the title cache
 *
 * @param $local_graph_id   The ID of the graph to get a title for
 *
 * @return string|false     The graph title
 */
function get_graph_title($local_graph_id) {
	$graph = db_fetch_row_prepared('SELECT gl.host_id, gl.snmp_query_id,
		gl.snmp_index, gtg.local_graph_id, gtg.t_title, gtg.title
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id = gl.id
		WHERE gl.id = ?',
		array($local_graph_id));

	if (cacti_sizeof($graph)) {
		if (strstr($graph['title'], '|') !== false && $graph['host_id'] > 0 && empty($graph['t_title'])) {
			$graph['title'] = substitute_data_input_data($graph['title'], $graph, 0);

			return expand_title($graph['host_id'], $graph['snmp_query_id'], $graph['snmp_index'], $graph['title']);
		} else {
			return $graph['title'];
		}
	} else {
		return '';
	}
}

/**
 * get_guest_account - return the guest account as defined in the system
 *   if there is one, else return 0.
 *
 * @return (int) the guest account if greater than 0
 */
function get_guest_account() {
	$user = db_fetch_cell_prepared('SELECT id
		FROM user_auth
		WHERE username = ? OR id = ?',
		array(read_config_option('guest_user'), read_config_option('guest_user')));

	if (empty($user)) {
		return 0;
	} else {
		return $user;
	}
}

/**
 * get_template_account - return the template account given a user.
 *   if a user is not given, provide the 'default' template account.
 *   This function is hookable by third party plugins.
 *
 * @param  (int|string) either the username or user_id of the user
 * @param mixed $user
 *
 * @return (int) the template account if one exist for the user
 */
function get_template_account($user = '') {
	if ($user == '') {
		// no username or user_id passed, use default functionality
		$user = db_fetch_cell_prepared('SELECT id
			FROM user_auth
			WHERE username = ? OR id = ?',
			array(read_config_option('user_template'), read_config_option('user_template')));

		if (empty($user)) {
			return 0;
		} else {
			return $user;
		}
	} else {
		$template = api_plugin_hook_function('get_template_account', $user);

		if ($template == $user) {
			// no plugin present, use default functionality
			$user = db_fetch_cell_prepared('SELECT id
				FROM user_auth
				WHERE username = ? OR id = ?',
				array(read_config_option('user_template'), read_config_option('user_template')));

			if (empty($user)) {
				return 0;
			} else {
				return $user;
			}
		} elseif ($template > 0) {
			// plugin present and returned account
			return $template;
		} else {
			// plugin present and returned no account
			return 0;
		}
	}
}

/**
 * get_username - returns the username for the selected user
 *
 * @param $user_id - (int) the ID of the user
 *
 * @return mixed the username */
function get_username($user_id) {
	return db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id));
}

/**
 * get_execution_user - returns the username of the running process
 *
 * @return mixed the username
 */
function get_execution_user() {
	if (function_exists('posix_getpwuid')) {
		$user_info = posix_getpwuid(posix_geteuid());

		return $user_info['name'];
	} else {
		return exec('whoami');
	}
}

/**
 * generate_data_source_path - creates a new data source path from scratch using the first data source
 * item name and updates the database with the new value
 *
 * @param $local_data_id - (int) the ID of the data source to generate a new path for
 *
 * @return mixed the new generated path
 */
function generate_data_source_path($local_data_id) {
	global $config;

	static $extended_paths = false;
	static $pattern        = false;

	if ($extended_paths === false) {
		$extended_paths = read_config_option('extended_paths');
	}

	if ($pattern === false) {
		$pattern = read_config_option('extended_paths_type');
	}

	/* try any prepend the name with the host description */
	$data = db_fetch_row_prepared('SELECT dl.host_id, h.description, dl.snmp_query_id
		FROM host AS h
		INNER JOIN data_local AS dl
		ON dl.host_id = h.id
		AND dl.id = ?',
		array($local_data_id));

	if (cacti_sizeof($data)) {
		$host_name     = $data['description'];
		$host_id       = $data['host_id'];
		$data_query_id = $data['snmp_query_id'];
	} else {
		$host_name     = 'undefinedhost';
		$host_id       = 0;
		$data_query_id = 0;
	}

	/* put it all together using the local_data_id at the end */
	if ($extended_paths == 'on') {
		$maxdirs = read_config_option('extended_paths_hashes');

		if (empty($maxdirs) || $maxdirs < 0 || !is_numeric($maxdirs)) {
			$maxdirs = 100;
		}

		$hash_id = $host_id % $maxdirs;

		if ($pattern == 'device' || $pattern == '') {
			$new_path = "<path_rra>/$host_id/$local_data_id.rrd";
		} elseif ($pattern == 'device_dq') {
			$new_path = "<path_rra>/$host_id/$data_query_id/$local_data_id.rrd";
		} elseif ($pattern == 'hash_device') {
			$new_path = "<path_rra>/$hash_id/$host_id/$local_data_id.rrd";
		} elseif ($pattern == 'hash_device_dq') {
			$new_path = "<path_rra>/$hash_id/$host_id/$data_query_id/$local_data_id.rrd";
		}
	} else {
		$host_part = strtolower(clean_up_file_name($host_name)) . '_';

		/* then try and use the internal DS name to identify it */
		$data_source_rrd_name = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE local_data_id = ?
			ORDER BY id
			LIMIT 1',
			array($local_data_id)
		);

		if (!empty($data_source_rrd_name)) {
			$ds_part = strtolower(clean_up_file_name($data_source_rrd_name));
		} else {
			$ds_part = 'ds';
		}

		$new_path = "<path_rra>/$host_part$ds_part" . '_' . "$local_data_id.rrd";
	}

	/* update our changes to the db */
	db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE local_data_id = ?', array($new_path, $local_data_id));

	return $new_path;
}

/**
 * generate graph_best_cf - takes the requested consolidation function and maps against
 * the list of available consolidation functions for the consolidation functions and returns
 * the most appropriate.  Typically, this will be the requested value
 *
 * @param mixed $local_data_id
 * @param $requested_cf
 * @param $ds_step
 *
 * @return mixed the best cf to use
 */
function generate_graph_best_cf($local_data_id, $requested_cf, int $ds_step = 60): string {
	static $best_cf;

	if ($local_data_id > 0) {
		$avail_cf_functions = get_rrd_cfs($local_data_id);

		if (cacti_sizeof($avail_cf_functions)) {
			/* workaround until we have RRA presets in 0.8.8 */
			/* check through the cf's and get the best */
			/* if none was found, take the first */
			$best_cf = reset($avail_cf_functions);

			foreach ($avail_cf_functions as $cf) {
				if ($cf == $requested_cf) {
					$best_cf = $requested_cf;
				}
			}
		} else {
			$best_cf = '1';
		}
	}

	/* if you can not figure it out return average */
	return $best_cf;
}

/**
 * get_rrd_cfs - reads the RRDfile and gets the RRAs stored in it.
 *
 * @param $local_data_id
 *
 * @return mixed array of the CF functions
 */
function get_rrd_cfs($local_data_id) {
	global $consolidation_functions;
	static $rrd_cfs = array();

	if (array_key_exists($local_data_id, $rrd_cfs)) {
		return $rrd_cfs[$local_data_id];
	}

	$cfs = array();

	$rrdfile = get_data_source_path($local_data_id, true);

	$output = rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT);

	/* search for
	 * 		rra[0].cf = 'LAST'
	 * or similar
	 */
	if ($output != '') {
		$output = explode("\n", $output);

		if (cacti_sizeof($output)) {
			foreach ($output as $line) {
				if (substr_count($line, '.cf')) {
					$values = explode('=',$line);

					if (!in_array(trim($values[1], '" '), $cfs, true)) {
						$cfs[] = trim($values[1], '" ');
					}
				}
			}
		}
	}

	$new_cfs = array();

	if (cacti_sizeof($cfs)) {
		foreach ($cfs as $cf) {
			switch($cf) {
				case 'AVG':
				case 'AVERAGE':
					$new_cfs[1] = array_search('AVERAGE', $consolidation_functions, true);

					break;
				case 'MIN':
					$new_cfs[2] = array_search('MIN', $consolidation_functions, true);

					break;
				case 'MAX':
					$new_cfs[3] = array_search('MAX', $consolidation_functions, true);

					break;
				case 'LAST':
					$new_cfs[4] = array_search('LAST', $consolidation_functions, true);

					break;
			}
		}
	}

	$rrd_cfs[$local_data_id] = $new_cfs;

	return $new_cfs;
}

/**
 * generate_graph_def_name - takes a number and turns each digit into its letter-based
 * counterpart for RRDtool DEF names (ex 1 -> a, 2 -> b, etc)
 *
 * @param $graph_item_id - (int) the ID to generate a letter-based representation of
 *
 * @return mixed a letter-based representation of the input argument
 */
function generate_graph_def_name($graph_item_id) {
	$lookup_table = array('a','b','c','d','e','f','g','h','i','j');

	$result    = '';
	$strValGII = strval($graph_item_id);

	for ($i=0; $i < strlen($strValGII); $i++) {
		$result .= $lookup_table[substr($strValGII, $i, 1)];
	}

	if (preg_match('/^(cf|cdef|def)$/', $result)) {
		return 'zz' . $result;
	} else {
		return $result;
	}
}

/**
 * generate_data_input_field_sequences - re-numbers the sequences of each field associated
 * with a particular data input method based on its position within the input string
 *
 * @param $string - the input string that contains the field variables in a certain order
 * @param $data_input_id - (int) the ID of the data input method
 */
function generate_data_input_field_sequences($string, $data_input_id) {
	global $config, $registered_cacti_names;

	if (preg_match_all('/<([_a-zA-Z0-9]+)>/', $string, $matches)) {
		$j = 0;

		for ($i=0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names, true) == false) {
				$j++;

				db_execute_prepared("UPDATE data_input_fields
					SET sequence = ?
					WHERE data_input_id = ?
					AND input_output IN ('in')
					AND data_name = ?",
					array($j, $data_input_id, $matches[1][$i]));
			}
		}

		update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
	}
}

/**
 * move_graph_group - takes a graph group (parent+children) and swaps it with another graph
 * group
 *
 * @param $graph_template_item_id - (int) the ID of the (parent) graph item that was clicked
 * @param $graph_group_array - (array) an array containing the graph group to be moved
 * @param $target_id - (int) the ID of the (parent) graph item of the target group
 * @param $direction - ('next' or 'previous') whether the graph group is to be swapped with
 *   group above or below the current group
 */
function move_graph_group($graph_template_item_id, $graph_group_array, $target_id, $direction) {
	$graph_item = db_fetch_row_prepared('SELECT local_graph_id, graph_template_id
		FROM graph_templates_item
		WHERE id = ?',
		array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	} else {
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	/* get a list of parent+children of our target group */
	$target_graph_group_array = get_graph_group($target_id);

	/* if this "parent" item has no children, then treat it like a regular gprint */
	if (cacti_sizeof($target_graph_group_array) == 0) {
		if ($direction == 'next') {
			move_item_down('graph_templates_item', $graph_template_item_id, $sql_where);
		} elseif ($direction == 'previous') {
			move_item_up('graph_templates_item', $graph_template_item_id, $sql_where);
		}

		return;
	}

	/* start the sequence at '1' */
	$sequence_counter = 1;

	$graph_items = db_fetch_assoc_prepared("SELECT id, sequence
		FROM graph_templates_item
		WHERE $sql_where
		ORDER BY sequence");

	if (cacti_sizeof($graph_items)) {
		foreach ($graph_items as $item) {
			/* check to see if we are at the "target" spot in the loop; if we are, update the sequences and move on */
			if ($target_id == $item['id']) {
				if ($direction == 'next') {
					$group_array1 = $target_graph_group_array;
					$group_array2 = $graph_group_array;
				} elseif ($direction == 'previous') {
					$group_array1 = $graph_group_array;
					$group_array2 = $target_graph_group_array;
				}

				foreach ($group_array1 as $graph_template_item_id) {
					db_execute_prepared('UPDATE graph_templates_item
						SET sequence = ?
						WHERE id = ?',
						array($sequence_counter, $graph_template_item_id));

					/* propagate to ALL graphs using this template */
					if (empty($graph_item['local_graph_id'])) {
						db_execute_prepared('UPDATE graph_templates_item
							SET sequence = ?
							WHERE local_graph_template_item_id = ?',
							array($sequence_counter, $graph_template_item_id));
					}

					$sequence_counter++;
				}

				foreach ($group_array2 as $graph_template_item_id) {
					db_execute_prepared('UPDATE graph_templates_item
						SET sequence = ?
						WHERE id = ?',
						array($sequence_counter, $graph_template_item_id));

					/* propagate to ALL graphs using this template */
					if (empty($graph_item['local_graph_id'])) {
						db_execute_prepared('UPDATE graph_templates_item
							SET sequence = ?
							WHERE local_graph_template_item_id = ?',
							array($sequence_counter, $graph_template_item_id));
					}

					$sequence_counter++;
				}
			}

			/* make sure to "ignore" the items that we handled above */
			if ((!isset($graph_group_array[$item['id']])) && (!isset($target_graph_group_array[$item['id']]))) {
				db_execute_prepared('UPDATE graph_templates_item
					SET sequence = ?
					WHERE id = ?',
					array($sequence_counter, $item['id']));

				$sequence_counter++;
			}
		}
	}
}

/**
 * get_graph_group - returns an array containing each item in the graph group given a single
 * graph item in that group
 *
 * @param $graph_template_item_id - (int) the ID of the graph item to return the group of
 *
 * @return mixed (array) an array containing each item in the graph group
 */
function get_graph_group($graph_template_item_id) {
	global $graph_item_types;

	$graph_item = db_fetch_row_prepared('SELECT graph_type_id, sequence, local_graph_id, graph_template_id
		FROM graph_templates_item
		WHERE id = ?',
		array($graph_template_item_id));

	$params[] = $graph_item['sequence'];

	if (empty($graph_item['local_graph_id'])) {
		$params[]  = $graph_item['graph_template_id'];
		$sql_where = 'graph_template_id = ? AND local_graph_id = 0';
	} else {
		$params[]  = $graph_item['sequence'];
		$sql_where = 'local_graph_id = ?';
	}

	/* parents are LINE%, AREA%, and STACK%. If not return */
	if (!preg_match('/(LINE|AREA|STACK)/', $graph_item_types[$graph_item['graph_type_id']])) {
		return array();
	}

	$graph_item_children_array = array();

	/* put the parent item in the array as well */
	$graph_item_children_array[$graph_template_item_id] = $graph_template_item_id;

	$graph_items = db_fetch_assoc_prepared("SELECT id, graph_type_id, text_format, hard_return
		FROM graph_templates_item
		WHERE sequence > ?
		AND $sql_where
		ORDER BY sequence",
		$params);

	$is_hard = false;

	if (cacti_sizeof($graph_items)) {
		foreach ($graph_items as $item) {
			if ($is_hard) {
				return $graph_item_children_array;
			}

			if (strstr($graph_item_types[$item['graph_type_id']], 'GPRINT') !== false) {
				/* a child must be a GPRINT */
				$graph_item_children_array[$item['id']] = $item['id'];

				if ($item['hard_return'] == 'on') {
					$is_hard = true;
				}
			} elseif (strstr($graph_item_types[$item['graph_type_id']], 'COMMENT') !== false) {
				if (preg_match_all('/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate):(\d)?\|/', $item['text_format'], $matches, PREG_SET_ORDER)) {
					$graph_item_children_array[$item['id']] = $item['id'];
				} elseif (preg_match_all('/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/', $item['text_format'], $matches, PREG_SET_ORDER)) {
					$graph_item_children_array[$item['id']] = $item['id'];
				} else {
					/* if not a GPRINT or special COMMENT then get out */
					return $graph_item_children_array;
				}
			} else {
				/* if not a GPRINT or special COMMENT then get out */
				return $graph_item_children_array;
			}
		}
	}

	return $graph_item_children_array;
}

/**
 * get_graph_parent - returns the ID of the next or previous parent graph item id
 *
 * @param $graph_template_item_id - the ID of the current graph item
 * @param $direction - ('next' or 'previous') whether to find the next or previous parent
 *
 * @return mixed the ID of the next or previous parent graph item id
 */
function get_graph_parent($graph_template_item_id, $direction) {
	$graph_item = db_fetch_row_prepared('SELECT sequence, local_graph_id, graph_template_id
		FROM graph_templates_item
		WHERE id = ?',
		array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	} else {
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	if ($direction == 'next') {
		$sql_operator = '>';
		$sql_order    = 'ASC';
	} elseif ($direction == 'previous') {
		$sql_operator = '<';
		$sql_order    = 'DESC';
	}

	$next_parent_id = db_fetch_cell("SELECT id
		FROM graph_templates_item
		WHERE sequence $sql_operator " . $graph_item['sequence'] . "
		AND graph_type_id IN (4, 5, 6, 7, 8, 20)
		AND $sql_where
		ORDER BY sequence $sql_order
		LIMIT 1");

	if (empty($next_parent_id)) {
		return 0;
	} else {
		return $next_parent_id;
	}
}

/**
 * get_item - returns the ID of the next or previous item id
 *
 * @param $tblname - the table name that contains the target id
 * @param $field - the field name that contains the target id
 * @param $startid - (int) the current id
 * @param $lmt_query - an SQL "where" clause to limit the query
 * @param $direction - ('next' or 'previous') whether to find the next or previous item id
 *
 * @return mixed (int) the ID of the next or previous item id
 */
function get_item($tblname, $field, $startid, $lmt_query, $direction) {
	if ($direction == 'next') {
		$sql_operator = '>';
		$sql_order    = 'ASC';
	} elseif ($direction == 'previous') {
		$sql_operator = '<';
		$sql_order    = 'DESC';
	}

	$current_sequence = db_fetch_cell_prepared("SELECT $field
		FROM $tblname
		WHERE id = ?",
		array($startid));

	$new_item_id = db_fetch_cell("SELECT id
		FROM $tblname
		WHERE $field $sql_operator $current_sequence " . ($lmt_query != '' ? " AND $lmt_query":'') . "
		ORDER BY $field $sql_order
		LIMIT 1");

	if (empty($new_item_id)) {
		return $startid;
	} else {
		return $new_item_id;
	}
}

/**
 * get_sequence - returns the next available sequence id
 *
 * @param int|null $id - (int) the current id
 * @param string   $field - the field name that contains the target id
 * @param string   $table_name - the table name that contains the target id
 * @param string   $group_query - an SQL "where" clause to limit the query
 *
 * @return int the next available sequence id
 */
function get_sequence(?int $id, string $field, string $table_name, string $group_query): int {
	if (empty($id)) {
		$data = db_fetch_row("SELECT max($field)+1 AS seq
			FROM $table_name
			WHERE $group_query");

		if ($data['seq'] == '') {
			return 1;
		} else {
			return $data['seq'];
		}
	} else {
		$data = db_fetch_row_prepared("SELECT $field
			FROM $table_name
			WHERE id = ?",
			array($id));

		return $data[$field];
	}
}

/**
 * move_item_down - moves an item down by swapping it with the item below it
 *
 * @param $table_name - the table name that contains the target id
 * @param $current_id - (int) the current id
 * @param $group_query - an SQL "where" clause to limit the query
 */
function move_item_down($table_name, $current_id, $group_query = '') {
	$next_item = get_item($table_name, 'sequence', $current_id, $group_query, 'next');

	$sequence = db_fetch_cell_prepared("SELECT sequence
		FROM $table_name
		WHERE id = ?",
		array($current_id));

	$sequence_next = db_fetch_cell_prepared("SELECT sequence
		FROM $table_name
		WHERE id = ?",
		array($next_item));

	db_execute_prepared("UPDATE $table_name
		SET sequence = ?
		WHERE id = ?",
		array($sequence_next, $current_id));

	db_execute_prepared("UPDATE $table_name
		SET sequence = ?
		WHERE id = ?",
		array($sequence, $next_item));
}

/**
 * move_item_up - moves an item down by swapping it with the item above it
 *
 * @param $table_name - the table name that contains the target id
 * @param $current_id - (int) the current id
 * @param $group_query - an SQL "where" clause to limit the query
 */
function move_item_up($table_name, $current_id, $group_query = '') {
	$last_item = get_item($table_name, 'sequence', $current_id, $group_query, 'previous');

	$sequence = db_fetch_cell_prepared("SELECT sequence
		FROM $table_name
		WHERE id = ?",
		array($current_id));

	$sequence_last = db_fetch_cell_prepared("SELECT sequence
		FROM $table_name
		WHERE id = ?",
		array($last_item));

	db_execute_prepared("UPDATE $table_name
		SET sequence = ?
		WHERE id = ?",
		array($sequence_last, $current_id));

	db_execute_prepared("UPDATE $table_name
		SET sequence = ?
		WHERE id = ?",
		array($sequence, $last_item));
}

/**
 * exec_into_array - executes a command and puts each line of its output into
 * an array
 *
 * @param $command_line - the command to execute
 *
 * @return mixed (array) an array containing the command output
 */
function exec_into_array($command_line) {
	$out = array();
	$err = 0;
	exec($command_line,$out,$err);

	return array_values($out);
}

/**
 * get_web_browser - determines the current web browser in use by the client
 *
 * @return mixed ('ie' or 'moz' or 'other')
 */
function get_web_browser() {
	if (!empty($_SERVER['HTTP_USER_AGENT'])) {
		if (stristr($_SERVER['HTTP_USER_AGENT'], 'Mozilla') && (!(stristr($_SERVER['HTTP_USER_AGENT'], 'compatible')))) {
			return 'moz';
		}

		if (stristr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
			return 'ie';
		} else {
			return 'other';
		}
	} else {
		return 'other';
	}
}

/**
 * draw_login_status - provides a consistent login status page for all pages that use it
 * @param mixed $using_guest_account
 */
function draw_login_status($using_guest_account = false) {
	global $config;

	$guest_account = get_guest_account();
	$auth_method   = read_config_option('auth_method');

	if (isset($_SESSION[SESS_USER_ID]) && $_SESSION[SESS_USER_ID] === $guest_account) {
		api_plugin_hook('nav_login_before');

		print __('Logged in as') . " <span id='user' class='user usermenuup'>". __('guest') . "</span></div><div><ul class='menuoptions' style='display:none;'>" . ($auth_method != AUTH_METHOD_BASIC ? "<li><a href='" . CACTI_PATH_URL . "index.php?login=true'>" . __('Login as Regular User') . '</a></li>':"<li><a href='#'>" . __('Logged in a Guest') . '</a></li>');

		print "<li class='menuHr'><hr class='menu'></li>";
		print "<li id='userCommunity'><a href='https://forums.cacti.net' target='_blank' rel='noopener'>" . __('User Community') . '</a></li>';
		print "<li id='userDocumentation'><a href='https://github.com/Cacti/documentation/blob/develop/README.md' target='_blank' rel='noopener'>" . __('Documentation') . '</a></li>';
		print '</ul>';

		api_plugin_hook('nav_login_after');
	} elseif (isset($_SESSION[SESS_USER_ID]) && $using_guest_account == false) {
		$user = db_fetch_row_prepared('SELECT username, password_change, realm
			FROM user_auth
			WHERE id = ?',
			array($_SESSION[SESS_USER_ID]));

		api_plugin_hook('nav_login_before');

		print __('Logged in as') . " <span id='user' class='user usermenuup'>" . html_escape($user['username']) .
			"</span></div><div><ul class='menuoptions' style='display:none;'>";

		print "<li><a href='#' class='loggedInAs' style='display:none;'>" . __esc('Logged in as %s', $user['username']) . "</a></li><hr class='menu'>";

		print(is_realm_allowed(20) ? "<li><a href='" . html_escape(CACTI_PATH_URL . 'auth_profile.php?action=edit') . "'>" . __('Edit Profile') . '</a></li>':'');
		print($user['password_change'] == 'on' && $user['realm'] == 0 ? "<li><a href='" . html_escape(CACTI_PATH_URL . 'auth_changepassword.php') . "'>" . __('Change Password') . '</a></li>':'');
		print((is_realm_allowed(20) || ($user['password_change'] == 'on' && $user['realm'] == 0)) ? "<li class='menuHr'><hr class='menu'></li>":'');

		if (is_realm_allowed(28)) {
			print "<li id='userCommunity'><a href='https://forums.cacti.net' target='_blank' rel='noopener'>" . __('User Community') . '</a></li>';
			print "<li id='userDocumentation'><a href='https://github.com/Cacti/documentation/blob/develop/README.md' target='_blank' rel='noopener'>" . __('Documentation') . '</a></li>';
			print "<li class='menuHr'><hr class='menu'></li>";
		}

		print($auth_method > AUTH_METHOD_NONE && $auth_method != AUTH_METHOD_BASIC ? "<li><a href='" . html_escape(CACTI_PATH_URL . 'logout.php') . "'>" . __('Logout') . '</a></li>':'');
		print '</ul>';

		api_plugin_hook('nav_login_after');
	}
}

/**
 * draw_navigation_text - determines the top header navigation text for the current page and displays it to
 *
 * @param $type - Either 'url' or 'title'
 *
 * @return mixed Either the navigation text or title
 */
function draw_navigation_text($type = 'url') {
	global $config, $navigation;

	$navigation      = api_plugin_hook_function('draw_navigation_text', $navigation);
	$current_page    = get_current_page();

	if (!isempty_request_var('action')) {
		get_filter_request_var('action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([-a-zA-Z0-9_\s]+)$/')));
	}

	$current_action = (isset_request_var('action') ? get_request_var('action') : '');

	// find the current page in the big array
	if (isset($navigation[$current_page . ':' . $current_action])) {
		$current_array = $navigation[$current_page . ':' . $current_action];
	} else {
		// If it's not set in the array, then default to a generic title
		$current_array = array(
			'mapping' => 'index.php:',
			'title'   => ucwords(str_replace('_', ' ', basename(get_current_page(), '.php'))),
			'level'   => 0
		);
	}

	// Extract the full breadcrumb path from the current_array
	if (isset($current_array['mapping'])) {
		$current_mappings = explode(',', $current_array['mapping']);
	} else {
		$current_mappings = array();
	}

	$current_nav = "<ul id='breadcrumbs'>";
	$title       = '';
	$nav_count   = 0;

	// resolve all mappings to build the navigation string
	// this process is more simple than you might think
	// we don't care about history as the breadcrumb is
	// always based upon it's parent.
	foreach ($current_mappings as $i => $breadcrumb) {
		$url = '';

		if (empty($breadcrumb)) {
			continue;
		}

		if ($i == 0) {
			// Always use the default for level == 0
			$url = $navigation[basename($breadcrumb)]['url'];

			if (basename($url) == 'graph_view.php') {
				continue;
			}
		} elseif (isset($current_array['url']) && $current_array['url'] != '') {
			// Where the user specified a non-blank URL
			$url = $current_array['url'];
		} else {
			// No 'url' was specified, so parse the breadcrumb path and use it
			$parts = explode(':', $breadcrumb);
			$url   = $parts[0] . (isset($parts[1]) && $parts[1] != '' ? '?action=' . $parts[1]:'');
		}

		// Construct the list item and anchor from the 'url' if there was one.  There should always
		// be one.
		$current_nav .= "<li><a id='nav_$i' href='" . (empty($url) ? '#':html_escape($url)) . "'>";
		$current_nav .= html_escape(resolve_navigation_variables($navigation[basename($breadcrumb)]['title']));
		$current_nav .= '</a>' . (get_selected_theme() == 'classic' ? ' > ':'') . '</li>';
		$title .= html_escape(resolve_navigation_variables($navigation[basename($breadcrumb)]['title'])) . ' > ';

		$nav_count++;
	}

	// Add a title for the current level
	if ($nav_count) {
		// We've already appended the full path, not the end bit
		if (isset($current_array['title'])) {
			$current_nav .= "<li><a id='nav_$i' href='#'>" . html_escape(resolve_navigation_variables($current_array['title'])) . '</a></li>';
		}
	} else {
		// No breadcrumb was found for the current path, make one up
		$current_array = $navigation[$current_page . ':' . $current_action];
		$url           = (isset($current_array['url']) ? $current_array['url']:'');

		if (isset($current_array['title'])) {
			$current_nav .= "<li><a id='nav_$i' href='$url'>" . html_escape(resolve_navigation_variables($current_array['title'])) . '</a></li>';
		}
	}

	// Handle Special Navigation Cases of Tree's and External Links
	if (isset_request_var('action') || get_nfilter_request_var('action') == 'tree_content') {
		$tree_id = 0;
		$leaf_id = 0;

		if (isset_request_var('node')) {
			$parts = explode('-', get_request_var('node'));

			// Check for tree anchor
			if (strpos(get_request_var('node'), 'tree_anchor') !== false) {
				$tree_id = $parts[1];
				$leaf_id = 0;
			} elseif (strpos(get_request_var('node'), 'tbranch') !== false) {
				// Check for branch
				$leaf_id = $parts[1];
				$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
					FROM graph_tree_items
					WHERE id = ?',
					array($leaf_id));
			}
		}

		if ($leaf_id > 0) {
			$leaf = db_fetch_row_prepared('SELECT host_id, title, graph_tree_id
				FROM graph_tree_items
				WHERE id = ?',
				array($leaf_id));

			if (cacti_sizeof($leaf)) {
				if ($leaf['host_id'] > 0) {
					$leaf_name = db_fetch_cell_prepared('SELECT description
						FROM host
						WHERE id = ?',
						array($leaf['host_id']));
				} else {
					$leaf_name = $leaf['title'];
				}

				$tree_name = db_fetch_cell_prepared('SELECT name
					FROM graph_tree
					WHERE id = ?',
					array($leaf['graph_tree_id']));
			} else {
				$leaf_name = __('Leaf');
				$tree_name = '';
			}

			if (isset_request_var('hgd') && get_nfilter_request_var('hgd') != '') {
				$parts = explode(':', get_nfilter_request_var('hgd'));
				input_validate_input_number($parts[1], 'hgd[1]');

				if ($parts[0] == 'gt') {
					$leaf_sub = db_fetch_cell_prepared('SELECT name
						FROM graph_templates
						WHERE id = ?',
						array($parts[1]));
				} else {
					if ($parts[1] > 0) {
						$leaf_sub = db_fetch_cell_prepared('SELECT name
							FROM snmp_query
							WHERE id = ?',
							array($parts[1]));
					} else {
						$leaf_sub = __('Non Query Based');
					}
				}
			} else {
				$leaf_sub = '';
			}
		} else {
			$leaf_name = '';
			$leaf_sub  = '';

			if ($tree_id > 0) {
				$tree_name = db_fetch_cell_prepared('SELECT name
					FROM graph_tree
					WHERE id = ?',
					array($tree_id));
			} else {
				$tree_name = '';
			}
		}

		$tree_title = $tree_name . ($leaf_name != '' ? ' (' . trim($leaf_name):'') . ($leaf_sub != '' ? ':' . trim($leaf_sub) . ')':($leaf_name != '' ? ')':''));

		if ($tree_title != '') {
			$current_nav .= "<li><a id='nav_title' href='#'>" . html_escape($tree_title) . '</a></li>';
		}
	} elseif (preg_match('#link.php\?id=(\d+)#', $_SERVER['REQUEST_URI'], $matches)) {
		$externalLinks = db_fetch_row_prepared('SELECT title, style FROM external_links WHERE id = ?', array($matches[1]));
		$title         = $externalLinks['title'];
		$style         = $externalLinks['style'];

		if ($style == 'CONSOLE') {
			$current_nav = "<ul id='breadcrumbs'>
				<li>
					<a id='nav_0' href='" . CACTI_PATH_URL . "index.php'>" . __('Console') . '</a>' . (get_selected_theme() == 'classic' ? ' > ':'') .
				'</li>';

			$current_nav .= "<li><a id='nav_1' href='#'>" . __('Link %s', html_escape($title)) . '</a></li>';
		} else {
			$current_nav = "<ul id='breadcrumbs'><li><a id='nav_0'>" . html_escape($title) . '</a></li>';
		}

		$tree_title = '';
	} else {
		$tree_title = '';
	}

	// Finally create a navigation title
	if (isset($current_array['title'])) {
		$title .= html_escape(resolve_navigation_variables($current_array['title']) . ' ' . $tree_title);
	}

	/*
	$hasNavError = false;
	if (is_array($current_page)) {
		cacti_log('WARNING: Navigation item suppressed - current page is not a string: ' . var_export($current_page,true));
		$hasNavError = true;
	}

	if (is_array($current_action)) {
		cacti_log('WARNING: Navigation item suppressed - current action is not a string: '. var_export($current_action,true));
		$hasNavError = true;
	}

	if (is_array($current_array['level'])) {
		cacti_log('WARNING: Navigation item suppressed - current level is not a string: ' . var_export($current_array['level'],true));
		$hasNavError = true;
	}

	if (!$hasNavError) {
		$nav_level_cache[$current_array['level']] = array(
			'id' => $current_page . ':' . $current_action,
			'url' => get_browser_query_string()
		);
	}
	*/
	$current_nav .= '</ul>';

	//$_SESSION['sess_nav_level_cache'] = $nav_level_cache;

	if ($type == 'url') {
		return $current_nav;
	} else {
		return $title;
	}
}

/**
 * resolve_navigation_variables - substitute any variables contained in the navigation text
 *
 * @param $text - the text to substitute in
 *
 * @return mixed the original navigation text with all substitutions made
 */
function resolve_navigation_variables(string $text): string {
	if (isset_request_var('local_graph_id') && get_filter_request_var('local_graph_id') > 0) {
		$graphTitle = get_graph_title(get_request_var('local_graph_id'));

		if (preg_match_all("/\|([a-zA-Z0-9_]+)\|/", $text, $matches)) {
			for ($i=0; $i < cacti_count($matches[1]); $i++) {
				switch ($matches[1][$i]) {
					case 'current_graph_title':
						$text = str_replace('|' . $matches[1][$i] . '|', $graphTitle, $text);

						break;
				}
			}
		}
	}

	return $text;
}

/**
 * get_associated_rras - returns a list of all RRAs referenced by a particular graph
 *
 * @param $local_graph_id - (int) the ID of the graph to retrieve a list of RRAs for
 * @param mixed $sql_where
 *
 * @return mixed (array) an array containing the name and id of each RRA found
 */
function get_associated_rras($local_graph_id, $sql_where = '') {
	return db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . "
		dspr.id, dsp.step, dspr.steps, dspr.rows, dspr.name, dtd.rrd_step, dspr.timespan
		FROM graph_templates_item AS gti
		LEFT JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		LEFT JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		AND dtd.local_data_id != 0
		WHERE gti.local_graph_id = ?
		$sql_where
		ORDER BY dspr.steps",
		array($local_graph_id)
	);
}

/**
 * get_nearest_timespan - returns the nearest defined timespan.  Used for adding a default
 * graph timespan for data source profile rras.
 *
 * @param $timespan - (int) the timespan to fine a default for
 *
 * @return mixed (int) the timespan to apply for the data source profile rra value
 */
function get_nearest_timespan($timespan) {
	global $timespans;

	$last = end($timespans);

	foreach ($timespans as $index => $name) {
		if ($timespan > $index) {
			$last = $index;

			continue;
		}

		if ($timespan == $index) {
			return $index;
		} else {
			return $last;
		}
	}

	return $last;
}

/**
 * get_browser_query_string - returns the full url, including args requested by the browser
 *
 * @return mixed the url requested by the browser
 */
function get_browser_query_string() {
	if (!empty($_SERVER['REQUEST_URI'])) {
		return sanitize_uri($_SERVER['REQUEST_URI']);
	} else {
		return sanitize_uri(get_current_page() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
	}
}

/**
 * Returns the basename of the current page in a web server friendly way
 *
 * @param  bool $basename   Whether to return only the filename
 *
 * @return string|false     The basename of the current script file
 */
function get_current_page(bool $basename = true) {
	if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] != '') {
		if ($basename) {
			return basename($_SERVER['SCRIPT_NAME']);
		} else {
			return $_SERVER['SCRIPT_NAME'];
		}
	} elseif (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] != '') {
		if ($basename) {
			return basename($_SERVER['SCRIPT_FILENAME']);
		} else {
			return $_SERVER['SCRIPT_FILENAME'];
		}
	} else {
		cacti_log('ERROR: unable to determine current_page');
	}

	return false;
}

/**
 * get_hash_graph_template - returns the current unique hash for a graph template
 *
 * @param $graph_template_id - (int) the ID of the graph template to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_graph_template($graph_template_id, $sub_type = 'graph_template') {
	switch ($sub_type) {
		case 'graph_template':
			$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates WHERE id = ?', array($graph_template_id));

			break;
		case 'graph_template_item':
			$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates_item WHERE id = ?', array($graph_template_id));

			break;
		case 'graph_template_input':
			$hash = db_fetch_cell_prepared('SELECT hash FROM graph_template_input WHERE id = ?', array($graph_template_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_data_template - returns the current unique hash for a data template
 *
 * @param $graph_template_id - (int) the ID of the data template to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 * @param mixed $data_template_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_data_template($data_template_id, $sub_type = 'data_template') {
	switch ($sub_type) {
		case 'data_template':
			$hash = db_fetch_cell_prepared('SELECT hash FROM data_template WHERE id = ?', array($data_template_id));

			break;
		case 'data_template_item':
			$hash = db_fetch_cell_prepared('SELECT hash FROM data_template_rrd WHERE id = ?', array($data_template_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_data_input - returns the current unique hash for a data input method
 *
 * @param $graph_template_id - (int) the ID of the data input method to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 * @param mixed $data_input_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_data_input($data_input_id, $sub_type = 'data_input_method') {
	switch ($sub_type) {
		case 'data_input_method':
			$hash = db_fetch_cell_prepared('SELECT hash FROM data_input WHERE id = ?', array($data_input_id));

			break;
		case 'data_input_field':
			$hash = db_fetch_cell_prepared('SELECT hash FROM data_input_fields WHERE id = ?', array($data_input_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_cdef - returns the current unique hash for a cdef
 *
 * @param $graph_template_id - (int) the ID of the cdef to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 * @param mixed $cdef_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_cdef($cdef_id, $sub_type = 'cdef') {
	if (!is_numeric($cdef_id)) {
		return generate_hash();
	}

	switch ($sub_type) {
		case 'cdef':
			$hash = db_fetch_cell_prepared('SELECT hash FROM cdef WHERE id = ?', array($cdef_id));

			break;
		case 'cdef_item':
			$hash = db_fetch_cell_prepared('SELECT hash FROM cdef_items WHERE id = ?', array($cdef_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_gprint - returns the current unique hash for a gprint preset
 *
 * @param $graph_template_id - (int) the ID of the gprint preset to return a hash for
 * @param mixed $gprint_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_gprint($gprint_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates_gprint WHERE id = ?', array($gprint_id));

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_automation - returns the current unique hash for a automation objects
 *
 * @param $unique_id - (int) the ID of the gprint preset to return a hash for
 * @param $table - (string) the table we are capturing for
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_automation($unique_id, $table) {
	$hash = db_fetch_cell_prepared("SELECT hash
		FROM $table
		WHERE id = ?",
		array($unique_id));

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * returns the current unique hash for a vdef
 *
 * @param $graph_template_id - the ID of the vdef to return a hash for
 * @param $sub_type          - return the hash for a particular subtype of this type
 * @param mixed $vdef_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_vdef($vdef_id, $sub_type = 'vdef') {
	switch ($sub_type) {
		case 'vdef':
			$hash = db_fetch_cell_prepared('SELECT hash FROM vdef WHERE id = ?', array($vdef_id));

			break;
		case 'vdef_item':
			$hash = db_fetch_cell_prepared('SELECT hash FROM vdef_items WHERE id = ?', array($vdef_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_data_source_profile - returns the current unique hash for a vdef
 *
 * @param $data_source_profile_id - the ID of the data_source_profile to return a hash for
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_data_source_profile($data_source_profile_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM data_source_profiles WHERE id = ?', array($data_source_profile_id));

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_host_template - returns the current unique hash for a gprint preset
 *
 * @param $host_template_id - the ID of the host template to return a hash for
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_host_template($host_template_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM host_template WHERE id = ?', array($host_template_id));

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_data_query - returns the current unique hash for a data query
 *
 * @param $graph_template_id - the ID of the data query to return a hash for
 * @param $sub_type return the hash for a particular subtype of this type
 * @param mixed $data_query_id
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function get_hash_data_query($data_query_id, $sub_type = 'data_query') {
	switch ($sub_type) {
		case 'data_query':
			$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query WHERE id = ?', array($data_query_id));

			break;
		case 'data_query_graph':
			$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph WHERE id = ?', array($data_query_id));

			break;
		case 'data_query_sv_data_source':
			$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph_rrd_sv WHERE id = ?', array($data_query_id));

			break;
		case 'data_query_sv_graph':
			$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph_sv WHERE id = ?', array($data_query_id));

			break;

		default:
			return generate_hash();

			break;
	}

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

/**
 * get_hash_version - returns the item type and cacti version in a hash format
 *
 * @param $type - the type of item to represent ('graph_template','data_template',
 *   'data_input_method','cdef','vdef','gprint_preset','data_query','host_template')
 *
 * @return mixed a 24-bit hexadecimal hash (8-bits for type, 16-bits for version)
 */
function get_hash_version(string $type): string {
	global $hash_type_codes, $cacti_version_codes, $config;

	return $hash_type_codes[$type] . $cacti_version_codes[CACTI_VERSION];
}

/**
 * generate_hash - generates a new unique hash
 *
 * @return mixed a 128-bit, hexadecimal hash
 */
function generate_hash() {
	return md5(session_id() . microtime() . rand(0,1000));
}

/**
 * debug_log_insert_section_start - creates a header item for breaking down the debug log
 *
 * @param $type - the 'category' or type of debug message
 * @param $text - section header
 * @param mixed $allowcopy
 */
function debug_log_insert_section_start($type, $text, $allowcopy = false) {
	$copy_prefix = '';
	$copy_dataid = '';

	if ($allowcopy) {
		$uid           = generate_hash();
		$copy_prefix   = '<div class=\'cactiTableButton debug\'><span><a class=\'linkCopyDark cactiTableCopy\' id=\'copyToClipboard' . $uid . '\'>' . __esc('Copy') . '</a></span></div>';
		$copy_dataid   = ' id=\'clipboardData'.$uid.'\'';
		$copy_headerid = ' id=\'clipboardHeader'.$uid.'\'';
	}

	debug_log_insert($type, '<table class=\'cactiTable debug\'' . $copy_headerid . '><tr class=\'tableHeader\'><td>' . html_escape($text) . $copy_prefix . '</td></tr><tr><td style=\'padding:0px;\'><table style=\'display:none;\'' . $copy_dataid . '><tr><td><div style=\'font-family: monospace;\'>');
}

/**
 * debug_log_insert_section_end - finalizes the header started with the start function
 *
 * @param $type - the 'category' or type of debug message
 */
function debug_log_insert_section_end($type) {
	debug_log_insert($type, '</div></td></tr></table></td></tr></td></table>');
}

/**
 * debug_log_insert - inserts a line of text into the debug log
 *
 * @param $type - the 'category' or type of debug message
 * @param $text - the actual debug message
 */
function debug_log_insert($type, $text) {
	global $config;

	if ($config['poller_id'] == 1 || isset($_SESSION)) {
		if (!isset($_SESSION['debug_log'][$type])) {
			$_SESSION['debug_log'][$type] = array();
		}

		array_push($_SESSION['debug_log'][$type], $text);
	} else {
		if (!isset($config['debug_log'][$type])) {
			$config['debug_log'][$type] = array();
		}

		array_push($config['debug_log'][$type], $text);
	}
}

/**
 * debug_log_clear - clears the debug log for a particular category
 *
 * @param $type - the 'category' to clear the debug log for. omitting this argument
 *   implies all categories
 */
function debug_log_clear($type = '') {
	if ($type == '') {
		kill_session_var('debug_log');
	} else {
		if (isset($_SESSION['debug_log'])) {
			unset($_SESSION['debug_log'][$type]);
		}
	}
}

/**
 * debug_log_return - returns the debug log for a particular category
 *
 * @param $type - the 'category' to return the debug log for.
 *
 * @return mixed the full debug log for a particular category
 */
function debug_log_return($type) {
	$log_text = '';

	if ($type == 'new_graphs') {
		if (isset($_SESSION['debug_log'][$type])) {
			$log_text .= "<table style='width:100%;'>";

			for ($i=0; $i < cacti_count($_SESSION['debug_log'][$type]); $i++) {
				$log_text .= '<tr><td>' . $_SESSION['debug_log'][$type][$i] . '</td></tr>';
			}
			$log_text .= '</table>';
		}
	} else {
		if (isset($_SESSION['debug_log'][$type])) {
			$log_text .= "<table style='width:100%;'>";

			foreach ($_SESSION['debug_log'][$type] as $key => $val) {
				$log_text .= "<tr><td>$val</td></tr>\n";
				unset($_SESSION['debug_log'][$type][$key]);
			}
			$log_text .= '</table>';
		}
	}

	return $log_text;
}

/**
 * sanitize_search_string - cleans up a search string submitted by the user to be passed
 * to the database. NOTE: some of the code for this function came from the phpBB project.
 *
 * @param $string - the original raw search string
 *
 * @return mixed the sanitized search string
 */
function sanitize_search_string($string) {
	static $drop_char_match   = array('(',')','^', '$', '<', '>', '`', '\'', '"', '|', ',', '?', '+', '[', ']', '{', '}', '#', ';', '!', '=', '*');
	static $drop_char_replace = array('','',' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ');

	/* Replace line endings by a space */
	$string = preg_replace('/[\n\r]/is', ' ', $string);

	/* HTML entities like &nbsp; */
	$string = preg_replace('/\b&[a-z]+;\b/', ' ', $string);

	/* Remove URL's */
	$string = preg_replace('/\b[a-z0-9]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/]+)?/', ' ', $string);

	/* Filter out strange characters like ^, $, &, change "it's" to "its" */
	for ($i = 0; $i < cacti_count($drop_char_match); $i++) {
		$string =  str_replace($drop_char_match[$i], $drop_char_replace[$i], $string);
	}

	return $string;
}

/**
 * cleans up a URI, e.g. from REQUEST_URI and/or QUERY_STRING
 * in case of XSS attack, expect the result to be broken
 * we do NOT sanitize in a way, that attacks are converted to valid HTML
 * it is ok, when the result is broken but the application stays alive
 *
 * @param string $uri   - the uri to be sanitized
 *
 * @return string    - the sanitized uri
 */
function sanitize_uri($uri) {
	static $drop_char_match   =   array('^', '$', '<', '>', '`', "'", '"', '|', '+', '[', ']', '{', '}', ';', '!', '(', ')');
	static $drop_char_replace = array( '', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '',  '');

	if (strpos($uri, 'graph_view.php')) {
		if (!strpos($uri, 'action=')) {
			$uri = $uri . (strpos($uri, '?') ? '&':'?') . 'action=' . get_nfilter_request_var('action');
		}
	}

	return str_replace($drop_char_match, $drop_char_replace, strip_tags(urldecode($uri)));
}

/**
 * Checks to see if a string is base64 encoded
 *
 * @param string $data   - the string to be validated
 *
 * @return boolean    - true is the string is base64 otherwise false
 */
function is_base64_encoded($data) {
	// Perform a simple check first
	if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data)) {
		return false;
	}

	// Now test with the built-in function
	$ndata = base64_decode($data, true);

	if ($ndata === false) {
		return false;
	}

	// Do a re-encode test and compare
	if (base64_encode($ndata) != $data) {
		return false;
	}

	return true;
}

/**
 * cleans up a CDEF/VDEF string
 * the CDEF/VDEF must have passed all magic string replacements beforehand
 *
 * @param string $cdef   - the CDEF/VDEF to be sanitized
 *
 * @return string    - the sanitized CDEF/VDEF
 */
function sanitize_cdef($cdef) {
	static $drop_char_match   =   array('^', '$', '<', '>', '`', '\'', '"', '|', '[', ']', '{', '}', ';', '!');
	static $drop_char_replace = array( '', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '');

	return str_replace($drop_char_match, $drop_char_replace, $cdef);
}

/**
 * verifies all selected items are numeric to guard against injection
 *
 * @param null|string $items   An array of serialized items from a post
 *
 * @return array               The sanitized selected items array
 */
function sanitize_unserialize_selected_items(?string $items): array {
	$return_items = false;

	if (!empty($items) && is_string($items)) {
		$unstripped = stripslashes($items);

		// validate that sanitized string is correctly formatted
		if (preg_match('/^a:[0-9]+:{/', $unstripped) && !preg_match('/(^|;|{|})O:\+?[0-9]+:"/', $unstripped)) {
			$items = unserialize($unstripped);

			if (is_array($items)) {
				$return_items = $items;

				foreach ($items as $item) {
					if (is_array($item)) {
						$return_items = false;

						break;
					}

					if (!is_numeric($item) && ($item != '')) {
						$return_items = false;

						break;
					}
				}
			}
		}
	}

	return $return_items;
}

function cacti_escapeshellcmd($string) {
	global $config;

	if ($string == '') {
		return $string;
	}

	if ($config['cacti_server_os'] == 'unix') {
		return escapeshellcmd($string);
	}

	if (!empty($string)) {
		$replacements = '#&;`|*?<>^()[]{}$\\';

		for ($i=0; $i < strlen($replacements); $i++) {
			$string = str_replace($replacements[$i], ' ', $string);
		}

		return $string;
	}
}

/**
 * mimics escapeshellarg, even for windows
 *
 * @param $string 	- the string to be escaped
 * @param $quote 	- true: do NOT remove quotes from result; false: do remove quotes
 *
 * @return	string	- the escaped [quoted|unquoted] string
 */
function cacti_escapeshellarg(string $string, bool $quote = true): string {
	global $config;

	if ($string == '') {
		return $string;
	}

	/* we must use an apostrophe to escape community names under Unix in case the user uses
	characters that the shell might interpret. the ucd-snmp binaries on Windows flip out when
	you do this, but are perfectly happy with a quotation mark. */
	if ($config['cacti_server_os'] == 'unix') {
		$string = escapeshellarg($string);

		if ($quote) {
			return $string;
		} else {
			# remove first and last char
			return substr($string, 1, (strlen($string) - 2));
		}
	} else {
		/* escapeshellarg takes care of different quotation for both linux and windows,
		 * but unfortunately, it blanks out percent signs
		 * we want to keep them, e.g. for GPRINT format strings
		 * so we need to create our own escapeshellarg
		 * on windows, command injection requires to close any open quotation first
		 * so we have to escape any quotation here */
		if (substr_count($string, CACTI_ESCAPE_CHARACTER)) {
			$string = str_replace(CACTI_ESCAPE_CHARACTER, '\\' . CACTI_ESCAPE_CHARACTER, $string);
		}

		/* ... before we add our own quotation */
		if ($quote) {
			return CACTI_ESCAPE_CHARACTER . $string . CACTI_ESCAPE_CHARACTER;
		} else {
			return $string;
		}
	}
}

/**
 * set a page refresh in Cacti through a callback
 *
 * @param $refresh - an array containing the page, seconds, and logout
 *
 * @return         - nill
 */
function set_page_refresh($refresh) {
	if (isset($refresh['seconds'])) {
		$_SESSION['refresh']['seconds'] = $refresh['seconds'];
	}

	if (read_config_option('auth_cache_enabled') == 'on' && isset($_SESSION['cacti_remembers']) && $_SESSION['cacti_remembers'] == true) {
		$_SESSION['refresh']['logout'] = 'false';
	} elseif (isset($refresh['logout'])) {
		if ($refresh['logout'] == 'true' || $refresh['logout'] === true) {
			$_SESSION['refresh']['logout'] = 'true';
		} else {
			$_SESSION['refresh']['logout'] = 'false';
		}
	} else {
		$_SESSION['refresh']['logout'] = 'true';
	}

	if (isset($refresh['page'])) {
		$_SESSION['refresh']['page'] = $refresh['page'];
	}
}

function bottom_footer() {
	global $config, $no_session_write;

	include_once(CACTI_PATH_INCLUDE . '/global_session.php');
	include_once(CACTI_PATH_INCLUDE . '/bottom_footer.php');

	/* we use this session var to store field values for when a save fails,
	   this way we can restore the field's previous values. we reset it here, because
	   they only need to be stored for a single page
	*/
	kill_session_var(SESS_FIELD_VALUES);

	/* make sure the debug log doesn't get too big */
	debug_log_clear();

	/* close the session */
	if (array_search(get_current_page(), $no_session_write, true) === false) {
		cacti_session_close();
	}

	/* close the database connection */
	db_close();
}

function top_header() {
	global $config;

	include_once(CACTI_PATH_INCLUDE . '/top_header.php');
}

function top_graph_header() {
	global $config;

	include_once(CACTI_PATH_INCLUDE . '/top_graph_header.php');
}

function general_header() {
	global $config;

	include_once(CACTI_PATH_INCLUDE . '/top_general_header.php');
}

function admin_email(string $subject, string $message) : bool {
	$result = false;

	if (read_config_option('admin_user') > 0) {
		if (read_config_option('notify_admin') == 'on') {
			$admin_details = db_fetch_row_prepared('SELECT full_name, email_address
				FROM user_auth
				WHERE id = ?',
				array(read_config_option('admin_user')));

			if (cacti_sizeof($admin_details)) {
				$email = read_config_option('settings_from_email');
				$name  = read_config_option('settings_from_name');

				if ($name != '') {
					$from = '"' . $name . '" <' . $email . '>';
				} else {
					$from = $email;
				}

				if ($admin_details['email_address'] != '') {
					if ($admin_details['full_name'] != '') {
						$to = '"' . $admin_details['full_name'] . '" <' . $admin_details['email_address'] . '>';
					} else {
						$to = $admin_details['email_address'];
					}

					// If we get any message back then we have failed
					$result = empty(send_mail($to, $from, $subject, $message, html: true, expandIds: true));
				}
			}
		}
	}

	if (!$result) {
		cacti_log('WARNING: Primary Admin account not set!  Unable to send administrative Email.', false, 'SYSTEM');
	}

	return $result;
}

function send_mail(array|string $to, string|array|null $from = null, string $subject = null, string $body = null, ?array $attachments = array(), ?array $headers = array(), bool $html = false, $expandIds = false): string {
	$fromname = '';

	if (is_array($from)) {
		$fromname = $from[1];
		$from     = $from[0];
	}

	if ($from === null || $from === '') {
		$from     = read_config_option('settings_from_email');
		$fromname = read_config_option('settings_from_name');
	} elseif ($fromname == '') {
		$full_name = db_fetch_cell_prepared('SELECT full_name
			FROM user_auth
			WHERE email_address = ?',
			array($from));

		if (empty($full_name)) {
			$fromname = $from;
		} else {
			$fromname = $full_name;
		}
	}

	$from = array(0 => $from, 1 => $fromname);

	return mailer($from, $to, subject: $subject, body: $body, attachments: $attachments, headers: $headers, html: $html, expandIds: $expandIds);
}

/**
 * function to send mails to users
 *
 * For contact parameters, they can accept arrays containing zero or more values in the forms of:
 *     'email@email.com,email2@email.com,email3@email.com'
 *     array('email1@email.com' => 'My email', 'email2@email.com' => 'Your email', 'email3@email.com' => 'Whose email')
 *     array(array('email' => 'email1@email.com', 'name' => 'My email'), array('email' => 'email2@email.com',
 *         'name' => 'Your email'), array('email' => 'email3@email.com', 'name' => 'Whose email'))
 *
 * The $from field will only use the first contact specified.  If no contact is provided for $replyto
 * then $from is used for that too. If $from is empty, it will default to cacti@<server> or if no server name can
 * be found, it will use cacti@cacti.net
 *
 * The $attachments parameter may either be a single string, or a list of attachments
 * either as strings or an array.  The array can have the following keys:
 *
 * filename    : name of the file to attach (display name for graphs)
 * display     : displayed name of the attachment
 * mime_type   : MIME type to be set against the attachment.  If blank or missing mailer will attempt to auto detect
 * attachment  : String containing attachment for image-based attachments (<GRAPH> or <GRAPH:#> activates graph mode
 *               and requires $body parameter is HTML containing one of those values)
 * inline      : Whether to attach 'inline' (default for graph mode) or as 'attachment' (default for all others)
 * encoding    : Encoding type, normally base64
 *
 * @param  array|string           $from                Email/name to send form
 * @param  array|string           $to                  single or multiple contacts in array or string form
 * @param  null|array|string      $cc                  none, single or multiple contacts
 * @param  null|array|string      $bcc                 none, single or multiple contacts
 * @param  null|array|string      $replyto             none, single or multiple contacts
 * @param  null|string            $subject             Messgae subject
 * @param  null|string            $body                Message body, in HTML format
 * @param  null|string            $body_text           Messgae body, in TEXT format
 * @param  null|array             $attachments         Attachments to send
 * @param  null|array             $headers             Custom headers
 * @param  boolean                $html                Assume HTML format
 * @param  boolean                $expandIds           Find log style xxx[nn] and expand to full names
 *
 * @return string
 *
 */
function mailer(array|string $from, array|string $to, null|array|string $cc = null, null|array|string $bcc = null, null|array|string $replyto = null, null|string $subject = null, null|string $body = null, null|string $body_text = null, null|array $attachments = array(), null|array $headers = array(), bool $html = true, bool $expandIds = false): string {
	global $cacti_locale, $mail_methods;

	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/Exception.php');
	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/PHPMailer.php');
	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/SMTP.php');

	$start_time = microtime(true);

	$subject = $subject ?? '';
	$body    = $body ?? '';

	// Create the PHPMailer instance
	$mail = new PHPMailer\PHPMailer\PHPMailer;

	// Set a reasonable timeout of 5 seconds
	$timeout = read_config_option('settings_smtp_timeout');

	if (empty($timeout) || $timeout < 0 || $timeout > 300) {
		$mail->Timeout = 5;
	} else {
		$mail->Timeout = $timeout;
	}

	$langparts = explode('-', $cacti_locale);

	if (file_exists(CACTI_PATH_INCLUDE . '/vendor/phpmailer/language/phpmailer.lang-' . $langparts[0] . '.php')) {
		$mail->setLanguage($langparts[0], CACTI_PATH_INCLUDE . '/vendor/phpmailer/language/');
	}

	$how = read_config_option('settings_how');

	if ($how < 0 || $how > 2) {
		$how = 0;
	}

	if ($how == 0) {
		$mail->isMail();
	} elseif ($how == 1) {
		$mail->Sendmail = read_config_option('settings_sendmail_path');
		$mail->isSendmail();
	} elseif ($how == 2) {
		$mail->isSMTP();
		$mail->Host     = read_config_option('settings_smtp_host');
		$mail->Port     = read_config_option('settings_smtp_port');

		if (read_config_option('settings_smtp_username') != '') {
			$mail->SMTPAuth = true;
			$mail->Username = read_config_option('settings_smtp_username');

			if (read_config_option('settings_smtp_password') != '') {
				$mail->Password = read_config_option('settings_smtp_password');
			}
		} else {
			$mail->SMTPAuth = false;
		}

		$secure  = read_config_option('settings_smtp_secure');

		if (!empty($secure) && $secure != 'none') {
			$mail->SMTPSecure = true;

			if (substr_count($mail->Host, ':') == 0) {
				$mail->Host = $secure . '://' . $mail->Host;
			}
		} else {
			$mail->SMTPAutoTLS = false;
			$mail->SMTPSecure  = false;
		}
	}

	/* perform data substitution */
	if (strpos($subject, '|date_time|') !== false) {
		$date = read_config_option('date');

		if (!empty($date)) {
			$time = strtotime($date);
		} else {
			$time = time();
		}

		$subject = str_replace('|date_time|', date(CACTI_DATE_TIME_FORMAT, $time), $subject);
	}

	/*
	 * Set the from details using the variable passed in
	 * - if name is blank, use setting's name
	 * - if email is blank, use setting's email, otherwise default to
	 *   cacti@<server> or cacti@cacti.net if no known server name
	 */
	$from = parse_email_details($from, 1);

	// from name was empty, use value in settings
	if (empty($from['name'])) {
		$from['name'] = read_config_option('settings_from_name');
	}

	// from email was empty, use email in settings
	if (empty($from['email'])) {
		$from['email'] = read_config_option('settings_from_email');
	}

	if (empty($from['email'])) {
		if (isset($_SERVER['HOSTNAME'])) {
			$from['email'] = 'Cacti@' . $_SERVER['HOSTNAME'];
		} else {
			$from['email'] = 'Cacti@cacti.net';
		}

		if (empty($from['name'])) {
			$from['name'] = 'Cacti';
		}
	}

	// Sanity test the from email
	if (!filter_var($from['email'], FILTER_VALIDATE_EMAIL)) {
		return 'Bad email address format. Invalid from email address ' . $from['email'];
	}

	$result    = false;
	$fromText  = add_email_details(array($from), $result, array($mail, 'setFrom'));

	if ($result == false) {
		return record_mailer_error($fromText, $mail->ErrorInfo);
	}

	// Convert $to variable to proper array structure
	$to        = parse_email_details($to);
	$toText    = add_email_details($to, $result, array($mail, 'addAddress'));

	if ($result == false) {
		return record_mailer_error($toText, $mail->ErrorInfo);
	}

	$cc        = parse_email_details($cc);
	$ccText    = add_email_details($cc, $result, array($mail, 'addCC'));

	if ($result == false) {
		return record_mailer_error($ccText, $mail->ErrorInfo);
	}

	$bcc       = parse_email_details($bcc);
	$bccText   = add_email_details($bcc, $result, array($mail, 'addBCC'));

	if ($result == false) {
		return record_mailer_error($bccText, $mail->ErrorInfo);
	}

	// This is a failsafe, should never happen now
	if (!(cacti_sizeof($to) || cacti_sizeof($cc) || cacti_sizeof($bcc))) {
		cacti_log('ERROR: No recipient address set!!', false, 'MAILER');
		cacti_debug_backtrace('MAILER ERROR');

		return __('Mailer Error: No recipient address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.');
	}

	$replyto   = parse_email_details($replyto);
	$replyText = add_email_details($replyto, $result, array($mail, 'addReplyTo'));

	if ($result == false) {
		return record_mailer_error($replyText, $mail->ErrorInfo);
	}

	$conversion_array = array(
		'<SUBJECT>' => $subject ?? '',
		'<TO>'      => $toText ?? '',
		'<CC>'      => $ccText ?? '',
		'<FROM>'    => $fromText ?? '',
		'<REPLYTO>' => $replyText ?? '',
	);

	$body      = text_substitute($body, true, $expandIds, $conversion_array);
	$body_text = text_substitute($body_text, false, $expandIds, $conversion_array);

	// Set the subject
	$mail->Subject = $subject;

	// Support i18n
	$mail->CharSet  = 'UTF-8';
	$mail->Encoding = 'base64';

	// Set the wordwrap limits
	$wordwrap = read_config_option('settings_wordwrap');

	if ($wordwrap == '') {
		$wordwrap = 76;
	} elseif ($wordwrap > 9999) {
		$wordwrap = 9999;
	} elseif ($wordwrap < 0) {
		$wordwrap = 76;
	}

	$mail->WordWrap = $wordwrap;
	$mail->setWordWrap();

	if (!$html) {
		$mail->ContentType = 'text/plain';
	} else {
		$mail->ContentType = 'text/html';
	}

	$i = 0;

	// Handle Graph Attachments
	if (!empty($attachments) && !is_array($attachments)) {
		$attachments = array('attachment' => $attachments);
	}

	if (is_array($attachments) && cacti_sizeof($attachments)) {
		$graph_mode = (substr_count($body, '<GRAPH>') > 0);
		$graph_ids  = (substr_count($body, '<GRAPH:') > 0);

		$default_opts = array(
			// MIME type to be set against the attachment
			'mime_type'  => '',
			// Display name of the attachment
			'filename'    => '',
			// String containing attachment for image-based attachments
			'attachment' => '',
			// Whether to attach inline or as attachment
			'inline'     => ($graph_mode || $graph_ids) ? 'inline' : 'attachment',
			// Encoding type, normally base64
			'encoding'   => 'base64',
		);

		foreach ($attachments as $attachment) {
			if (!is_array($attachment)) {
				$attachment = array('attachment' => $attachment);
			}

			foreach ($default_opts as $opt_name => $opt_default) {
				if (!array_key_exists($opt_name, $attachment)) {
					$attachment[$opt_name] = $opt_default;
				}
			}

			if (!empty($attachment['attachment'])) {
				/* get content id and create attachment */
				$cid = getmypid() . '_' . $i . '@' . 'localhost';

				if (empty($attachment['filename']) && file_exists($attachment['attachment'])) {
					$attachment['filename'] = $attachment['attachment'];
				}

				/* attempt to attach */
				if (!($graph_mode || $graph_ids)) {
					if (!empty($attachment['attachment']) && @file_exists($attachment['attachment'])) {
						$result = $mail->addAttachment($attachment['attachment'], $attachment['filename'], $attachment['encoding'], $attachment['mime_type'], $attachment['inline']);
					} else {
						$result = $mail->addStringAttachment($attachment['attachment'], $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']);
					}
				} else {
					if (!empty($attachment['attachment']) && @file_exists($attachment['attachment'])) {
						$result = $mail->addEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], $attachment['encoding'], $attachment['mime_type'], $attachment['inline']);
					} else {
						$result = $mail->addStringEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']);
					}
				}

				if ($result == false) {
					cacti_log('ERROR: ' . $mail->ErrorInfo, false, 'MAILER');

					return $mail->ErrorInfo;
				}

				$i++;

				if ($graph_mode) {
					$body = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $body);
				} elseif ($graph_ids) {
					/* handle the body text */
					switch ($attachment['inline']) {
						case 'inline':
							$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', "<img src='cid:$cid' >", $body);

							break;
						case 'attachment':
							$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', '', $body);

							break;
					}
				}
			}
		}
	}

	/* process custom headers */
	if (is_array($headers) && cacti_sizeof($headers)) {
		foreach ($headers as $name => $value) {
			$mail->addCustomHeader($name, $value);
		}
	}

	// Set both html and non-html bodies
	$brs = array('<br>', '<br />', '</br>');

	if ($html) {
		$body  = $body . '<br>';
	}

	if ($body_text == '') {
		$body_text = strip_tags(str_ireplace($brs, "\n", $body));
	}

	$mail->isHTML($html);
	$mail->Body = ($html ? $body : $body_text);

	if ($html && $body_text != '') {
		$mail->AltBody = $body_text;
	}

	$result   = $mail->send();
	$error    = $mail->ErrorInfo; //$result ? '' : $mail->ErrorInfo;
	$method   = $mail_methods[intval(read_config_option('settings_how'))];
	$rtype    = $result ? 'INFO' : 'WARNING';
	$rmsg     = $result ? 'successfully sent' : 'failed';
	$end_time = microtime(true);

	if ($error != '') {
		$message = sprintf("%s: Mail %s via %s from '%s', to '%s', cc '%s', and took %2.2f seconds, Subject '%s'%s",
			$rtype, $rmsg, $method, $fromText, $toText, $ccText, ($end_time - $start_time), $subject,
			", Error: $error");
	} else {
		$message = sprintf("%s: Mail %s via %s from '%s', to '%s', cc '%s', and took %2.2f seconds, Subject '%s'",
			$rtype, $rmsg, $method, $fromText, $toText, $ccText, ($end_time - $start_time), $subject);
	}

	cacti_log($message, false, 'MAILER');

	if ($result == false) {
		cacti_log(cacti_debug_backtrace($rtype), false, 'MAILER');
	}

	return $error;
}

function record_mailer_error($retError, $mailError) {
	$errorInfo = empty($retError) ? $mailError : $retError;
	cacti_log('ERROR: ' . $errorInfo, false, 'CMDPHP MAILER');
	cacti_debug_backtrace('MAILER ERROR');

	return $errorInfo;
}

function add_email_details(array $emails, bool &$result, callable $addFunc): string {
	$arrText = array();

	foreach ($emails as $e) {
		if (!empty($e['email'])) {
			//if (is_callable($addFunc)) {
			if (!empty($addFunc)) {
				$result = $addFunc($e['email'], $e['name']);

				if (!$result) {
					return '';
				}
			}

			$arrText[] = create_emailtext($e);
		} elseif (!empty($e['name'])) {
			$result = false;

			return 'Bad email format, name but no address: ' . $e['name'];
		}
	}

	$text = implode(',', $arrText);
	//print "add_email_sw_details(): $text\n";
	return $text;
}

function parse_email_details($emails, int $max_records = 0, array $details = array()): array {
	if (!is_array($emails)) {
		$emails = array($emails);
	}

	$update = array();

	foreach ($emails as $check_email) {
		if (!empty($check_email)) {
			if (!is_array($check_email)) {
				$emails = explode(',', $check_email);

				foreach ($emails as $email) {
					$email_array                    = split_emaildetail($email);
					$details[$email_array['email']] = $email_array;
				}
			} else {
				$has_name  = array_key_exists('name', $check_email);
				$has_email = array_key_exists('email', $check_email);

				if ($has_name || $has_email) {
					$name  = $has_name  ? $check_email['name']  : '';
					$email = $has_email ? $check_email['email'] : '';
				} else {
					$name  = array_key_exists(1, $check_email) ? $check_email[1] : '';
					$email = array_key_exists(0, $check_email) ? $check_email[0] : '';
				}

				$details[trim(strtolower($email))] = array('name' => trim($name), 'email' => trim(strtolower($email)));
			}
		}
	}

	if ($max_records == 1) {
		$detail  = reset($details);
		$results = is_array($detail) ? $detail : array();
	} elseif ($max_records != 0 && $max_records < count($details)) {
		$results = array();

		foreach ($details as $d) {
			$results[] = $d;
			$max_records--;

			if ($max_records == 0) {
				break;
			}
		}
	} else {
		$results = $details;
	}

	return $results;
}

function split_emaildetail($email) {
	$rname  = '';
	$rmail  = '';

	if (!is_array($email)) {
		$email = trim($email);

		$sPattern = '/(?:"?([^"]*)"?\s)?(?:<?(.+@[^>]+)>?)/i';
		preg_match($sPattern, $email, $aMatch);

		if (isset($aMatch[1])) {
			$rname = trim($aMatch[1]);
		}

		if (isset($aMatch[2])) {
			$rmail = trim($aMatch[2]);
		}
	} else {
		$rmail = $email[0];
		$rname = $email[1];
	}

	return array('name' => $rname, 'email' => strtolower($rmail));
}

function create_emailtext($e) {
	if (empty($e['email'])) {
		$text = '';
	} else {
		if (empty($e['name'])) {
			$text = $e['email'];
		} else {
			$text = $e['name'] . ' <' . $e['email'] . '>';
		}
	}

	return $text;
}

function ping_mail_server($host, $port, $user, $password, $timeout = 10, $secure = 'none') {
	global $config;

	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/Exception.php');
	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/PHPMailer.php');
	require_once(CACTI_PATH_INCLUDE . '/vendor/phpmailer/src/SMTP.php');

	//Create a new SMTP instance
	$smtp = new PHPMailer\PHPMailer\SMTP;

	if (!empty($secure) && $secure != 'none') {
		if (substr_count($host, ':') == 0) {
			$host = $secure . '://' . $host;
		}
	}

	//Enable connection-level debug output
	$smtp->do_debug = 0;
	//$smtp->do_debug = SMTP::DEBUG_LOWLEVEL;

	$results = true;

	try {
		//Connect to an SMTP server
		if ($smtp->connect($host, $port, $timeout)) {
			//Say hello
			if ($smtp->hello(gethostbyname(gethostname()))) { //Put your host name in here
				//Authenticate
				if ($user != '') {
					if ($smtp->authenticate($user, $password)) {
						$results = true;
					} else {
						throw new Exception(__('Authentication failed: %s', $smtp->getLastReply()));
					}
				}
			} else {
				throw new Exception(__('HELO failed: %s', $smtp->getLastReply()));
			}
		} else {
			throw new Exception(__('Connect failed: %s', $smtp->getLastReply()));
		}
	} catch (Exception $e) {
		$results = __('SMTP error: ') . $e->getMessage();
		cacti_log($results);
	}

	//Whatever happened, close the connection.
	$smtp->quit(true);

	return $results;
}

function email_test() {
	global $config;

	$message =  __('This is a test message generated from Cacti.  This message was sent to test the configuration of your Mail Settings.') . '<br><br>';
	$message .= __('Your email settings are currently set as follows') . '<br><br>';
	$message .= '<b>' . __('Method') . '</b>: ';

	print __('Checking Configuration...<br>');

	$ping_results = true;
	$how          = read_config_option('settings_how');

	if ($how < 0 || $how > 2) {
		$how = 0;
	}

	if ($how == 0) {
		$mail = __('PHP\'s Mailer Class');
	} elseif ($how == 1) {
		$mail     = __('Sendmail') . '<br><b>' . __('Sendmail Path'). '</b>: ';
		$sendmail = read_config_option('settings_sendmail_path');
		$mail .= $sendmail;
	} elseif ($how == 2) {
		print __('Method: SMTP') . '<br>';
		$mail          = __('SMTP') . '<br>';
		$smtp_host     = read_config_option('settings_smtp_host');
		$smtp_port     = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');
		$smtp_secure   = read_config_option('settings_smtp_secure');
		$smtp_timeout  = read_config_option('settings_smtp_timeout');

		$mail .= '<b>' . __('Device') . "</b>: $smtp_host<br>";
		$mail .= '<b>' . __('Port') . "</b>: $smtp_port<br>";

		if ($smtp_username != '' && $smtp_password != '') {
			$mail .= '<b>' . __('Authentication') . '</b>: true<br>';
			$mail .= '<b>' . __('Username') . "</b>: $smtp_username<br>";
			$mail .= '<b>' . __('Password') . '</b>: (' . __('Not Shown for Security Reasons') . ')<br>';
			$mail .= '<b>' . __('Security') . "</b>: $smtp_secure<br>";
		} else {
			$mail .= '<b>' . __('Authentication') . '</b>: false<br>';
		}

		if (read_config_option('settings_ping_mail') == 0) {
			$ping_results = ping_mail_server($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_timeout, $smtp_secure);

			print __('Ping Results:') . ' ' . ($ping_results == 1 ? __('Success'):$ping_results) . '<br>';

			if ($ping_results != 1) {
				$mail .= '<b>' . __('Ping Results') . '</b>: ' . $ping_results . '<br>';
			} else {
				$mail .= '<b>' . __('Ping Results') . '</b>: ' . __('Success') . '<br>';
			}
		} else {
			$ping_results = 1;
			$mail .= '<b>' . __('Ping Results') . '</b>: ' . __('Bypassed') . '<br>';
		}
	}

	$message .= $mail;
	$message .= '<br>';

	$errors = '';

	if ($ping_results == 1) {
		print __('Creating Message Text...') . '<br><br>';
		print '<center><table><tr><td>';
		print "<table style='width:100%;'><tr><td>$message</td><tr></table></table></center><br>";
		print __('Sending Message...') . '<br><br>';

		$global_alert_address = read_config_option('settings_test_email');

		$errors = send_mail($global_alert_address, subject: __('Cacti Test Message'), body: $message, html: true);

		if ($errors == '') {
			$errors = __('Success!');
		}
	} else {
		print __('Message Not Sent due to ping failure.'). '<br><br>';
	}

	print '<center><table><tr><td>';
	print "<table><tr><td>$errors</td><tr></table></table></center>";
}

/**
 * gethostbyaddr_wtimeout - This function provides a good method of performing
 * a rapid lookup of a DNS entry for a host so long as you don't have to look far.
 * @param mixed $ip
 * @param mixed $dns
 * @param mixed $timeout
 */
function get_dns_from_ip($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode('.', $ip);

	/* perform a quick error check */
	if (cacti_count($octets) != 4) {
		return 'ERROR';
	}
	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x >= 0; $x--) {
		switch (strlen($octets[$x])) {
			case 1: // 1 byte long segment
				$data .= "\1";

				break;
			case 2: // 2 byte long segment
				$data .= "\2";

				break;
			case 3: // 3 byte long segment
				$data .= "\3";

				break;

			default: // segment is too big, invalid IP
				return 'ERROR';
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout / 1000), ($timeout * 1000) % 1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info['timed_out']) {
		return 'timed_out';
	}

	/* more error handling */
	if ($response == '') {
		return $ip;
	}

	/* parse the response and find the response type */
	$type = @unpack('s', substr($response, $requestsize + 2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = '';
		$len  = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack('c', substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return strtoupper(substr($host, 0, strlen($host) - 1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position + 1, $len[1]) . '.';

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return strtoupper($ip);
	}

	/* error - return the hostname */
	return strtoupper($ip);
}

function poller_maintenance() {
	global $config;

	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . cacti_escapeshellarg(CACTI_PATH_BASE . '/poller_maintenance.php');

	exec_background($command_string, $extra_args);
}

function clog_admin() {
	if (!isset($_SESSION['sess_clog_level'])) {
		clog_authorized();
	}

	if ($_SESSION['sess_clog_level'] == CLOG_PERM_ADMIN) {
		return true;
	} else {
		return false;
	}
}

function clog_authorized() {
	if (!isset($_SESSION['sess_clog_level'])) {
		if (isset($_SESSION[SESS_USER_ID])) {
			if (is_realm_allowed(18)) {
				$_SESSION['sess_clog_level'] = CLOG_PERM_ADMIN;
			} else {
				if (is_realm_allowed(19)) {
					$_SESSION['sess_clog_level'] = CLOG_PERM_USER;
				} else {
					$_SESSION['sess_clog_level'] = CLOG_PERM_NONE;
				}
			}
		} else {
			$_SESSION['sess_clog_level'] = CLOG_PERM_NONE;
		}
	}

	if ($_SESSION['sess_clog_level'] == CLOG_PERM_USER) {
		return true;
	}

	if ($_SESSION['sess_clog_level'] == CLOG_PERM_ADMIN) {
		return true;
	} else {
		return false;
	}
}

function cacti_debug_backtrace($entry = '', $html = false, $record = true, $limit = 0, $skip = 0) {
	global $config;

	$skip  = $skip >= 0 ? $skip : 1;
	$limit = $limit > 0 ? ($limit + $skip) : 0;

	$callers = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

	while ($skip > 0) {
		array_shift($callers);
		$skip--;
	}

	$s='';

	foreach ($callers as $c) {
		if (isset($c['line'])) {
			$line = '[' . $c['line'] . ']';
		} else {
			$line = '';
		}

		if (isset($c['file'])) {
			$file = str_replace(CACTI_PATH_BASE, '', $c['file']) . $line;
		} else {
			$file = $line;
		}

		$func = $c['function'].'()';

		if (isset($c['class'])) {
			$func = $c['class'] . $c['type'] . $func;
		}

		$s = ($file != '' ? $file . ':':'') . "$func" . (empty($s) ? '' : ', ') . $s;
	}

	if (!empty($s)) {
		$s = ' (' . $s . ')';
	}

	if ($record) {
		if ($html && CACTI_WEB) {
			print "<table style='width:100%;text-align:center;'><tr><td>$s</td></tr></table>\n";
		}

		cacti_log(trim("$entry Backtrace: " . clean_up_lines($s)), false, '');
	} else {
		if (!empty($entry)) {
			return trim("$entry Backtrace: " . clean_up_lines($s));
		} else {
			return trim(clean_up_lines($s));
		}
	}
}

/**
 * calculate_percentiles - Given and array of numbers, calculate the Nth percentile,
 * optionally, return an array of numbers containing elements required for
 * a whisker chart.
 *
 * @param $data       - an array of data
 * @param $percentile - the Nth percentile to calculate.  By default 95th.
 * @param $whisker    - if whisker is true, an array of values will be returned
 *                      including 25th, median, 75th, and 90th percentiles.
 *
 * @return mixed either the Nth percentile, the elements for a whisker chart,
 *            or false if there is insufficient data to determine.
 */
function calculate_percentiles($data, $percentile = 95, $whisker = false) {
	if ($percentile > 0 && $percentile < 1) {
		$p = $percentile;
	} elseif ($percentile > 1 && $percentile <= 100) {
		$p = $percentile * .01;
	} else {
		return false;
	}

	if ($whisker) {
		$tiles = array(
			'25th' => 0.25,
			'50th' => 0.50,
			'75th' => 0.75,
			'90th' => 0.90,
			'95th' => 0.95,
		);
	} else {
		$tiles = array(
			'custom' => $p
		);
	}

	$results  = array();
	$elements = cacti_sizeof($data);

	/* sort the array to return */
	sort($data);

	foreach ($tiles as $index => $p) {
		/* calculate offsets into the array */
		$allindex    = ($elements - 1) * $p;
		$intvalindex = floor($allindex);
		$floatval    = $allindex - $intvalindex;

		if (!is_float($floatval)) {
			$ptile = $data[$intvalindex];
		} else {
			if ($elements > $intvalindex + 1) {
				$ptile = $floatval * ($data[$intvalindex + 1] - $data[$intvalindex]) + $data[$intvalindex];
			} else {
				$ptile = $data[$intvalindex];
			}
		}

		if ($index == 'custom') {
			return $ptile;
		} else {
			$results[$index] = $ptile;
		}
	}

	return $results;
}

function get_timeinstate(array $host, bool $return_as_date = false): string {
	$interval = read_config_option('poller_interval');

	if ($host['availability_method'] == 0) {
		$time = 0;
	} elseif (isset($host['instate'])) {
		$time = $host['instate'];
	} elseif ($host['status_event_count'] > 0 && ($host['status'] == 1 || $host['status'] == 2 || $host['status'] == 5)) {
		$time = $host['status_event_count'] * $interval;
	} elseif (strtotime($host['status_rec_date']) < 943916400 && ($host['status'] == 0 || $host['status'] == 3)) {
		$time = $host['total_polls'] * $interval;
	} elseif (strtotime($host['status_rec_date']) > 943916400) {
		$time = time() - strtotime($host['status_rec_date']);
	} elseif ($host['snmp_sysUpTimeInstance'] > 0) {
		$time = $host['snmp_sysUpTimeInstance'] / 100;
	} else {
		$time = 0;
	}

	if ($time > 2E13) {
		$time = 0;
	}

	if (!$return_as_date) {
		return ($time > 0) ? get_daysfromtime($time) : __('N/A');
	} elseif ($time == 0) {
		return __('Since Install');
	} else {
		if (defined('CACTI_DATE_TIME_FORMAT')) {
			return date(CACTI_DATE_TIME_FORMAT, time() - $time);
		} else {
			return date('Y-m-d H:i:s', time() - $time);
		}
	}
}

function get_uptime(array $host, bool $return_as_date = false): string {
	if (!$return_as_date) {
		return ($host['snmp_sysUpTimeInstance'] > 0) ? get_daysfromtime(intval($host['snmp_sysUpTimeInstance'] / 100)) : __('N/A');
	} elseif ($host['snmp_sysUpTimeInstance'] == 0) {
		return __('Unknown');
	} else {
		if (defined('CACTI_DATE_TIME_FORMAT')) {
			return date(CACTI_DATE_TIME_FORMAT, time() - intval($host['snmp_sysUpTimeInstance'] / 100));
		} else {
			return date('Y-m-d H:i:s', time() - intval($host['snmp_sysUpTimeInstance'] / 100));
		}
	}
}

function get_daysfromtime($time, $secs = false, $pad = '', $format = DAYS_FORMAT_SHORT, $all = false) {
	global $days_from_time_settings;

	// Work around stricter typing in PHP 8.1.2+
	if (is_float($time)) {
		$time = intval(ceil($time));
	}

	// Ensure we use an existing format or we'll end up with no text at all
	if (!isset($days_from_time_settings['text'][$format])) {
		$format = DAYS_FORMAT_SHORT;
	}

	$mods = $days_from_time_settings['mods'];
	$text = $days_from_time_settings['text'][$format];

	$result = '';

	foreach ($mods as $index => $mod) {
		if ($mod > 0 || $secs) {
			if ($time >= $mod) {
				if ($mod < 1 || !is_numeric($mod)) {
					$mod = 1;
				}

				$val   = floor($time / $mod);
				$time %= $mod;
			} else {
				$val   = 0;
			}

			if ($all || $val > 0) {
				$result .= padleft($pad, $val, 2) . $text['prefix'] . $text[$index] . $text['suffix'];
				$all = true;
			}
		}
	}

	return trim($result, $text['suffix']);
}

function padleft($pad = '', $value = '', $min = 2) {
	$result = "$value";

	if (strlen($result) < $min && $pad != '') {
		$padded = $pad . $result;

		while ($padded != $result && strlen($result) < $min) {
			$padded = $pad . $result;
		}
		$result = $padded;
	}

	return $result;
}

function get_classic_tabimage($text, $down = false) {
	global $config, $dejavu_paths;

	$images = array(
		false => 'tab_template_blue.gif',
		true  => 'tab_template_red.gif'
	);

	if ($text == '') {
		return false;
	}
	$text = strtolower($text);

	$possibles = array(
		array('DejaVuSans-Bold.ttf', 9, true),
		array('DejaVuSansCondensed-Bold.ttf', 9, false),
		array('DejaVuSans-Bold.ttf', 9, false),
		array('DejaVuSansCondensed-Bold.ttf', 9, false),
		array('DejaVuSans-Bold.ttf', 8, false),
		array('DejaVuSansCondensed-Bold.ttf', 8, false),
		array('DejaVuSans-Bold.ttf', 7, false),
		array('DejaVuSansCondensed-Bold.ttf', 7, true),
	);

	$y        = 30;
	$x        = 44;
	$wlimit   = 72;
	$wrapsize = 12;

	if (file_exists(CACTI_PATH_IMAGES . '/' . $images[$down])) {
		foreach ($dejavu_paths as $dejavupath) {
			if (file_exists($dejavupath)) {
				$font_path = $dejavupath;
			}
		}

		$originalpath = getenv('GDFONTPATH');
		putenv('GDFONTPATH=' . $font_path);

		$template = imagecreatefromgif(CACTI_PATH_IMAGES . '/' . $images[$down]);

		$w = imagesx($template);
		$h = imagesy($template);

		$tab = imagecreatetruecolor($w, $h);
		imagecopy($tab, $template, 0, 0, 0, 0, $w, $h);

		$txcol = imagecolorat($tab, 0, 0);
		imagecolortransparent($tab,$txcol);

		$white         = imagecolorallocate($tab, 255, 255, 255);
		$ttf_functions = function_exists('imagettftext') && function_exists('imagettfbbox');

		if ($ttf_functions) {
			foreach ($possibles as $variation) {
				$font     = $variation[0];
				$fontsize = $variation[1];

				$lines = array();

				// if no wrapping is requested, or no wrapping is possible...
				if ((!$variation[2]) || ($variation[2] && strpos($text,' ') === false)) {
					$bounds  = imagettfbbox($fontsize, 0, $font, $text);
					$w       = $bounds[4] - $bounds[0];
					$h       = $bounds[1] - $bounds[5];
					$realx   = $x - $w / 2 - 1;
					$lines[] = array($text, $font, $fontsize, $realx, $y);
					$maxw    = $w;
				} else {
					$texts = explode("\n", wordwrap($text, $wrapsize), 2);
					$line  = 1;
					$maxw  = 0;

					foreach ($texts as $txt) {
						$bounds  = imagettfbbox($fontsize, 0, $font, $txt);
						$w       = $bounds[4] - $bounds[0];
						$h       = $bounds[1] - $bounds[5];
						$realx   = $x - $w / 2 - 1;
						$realy   = $y - $h * $line + 3;
						$lines[] = array($txt, $font, $fontsize, $realx, $realy);

						if ($maxw < $w) {
							$maxw = $w;
						}

						$line--;
					}
				}

				if ($maxw < $wlimit) {
					break;
				}
			}
		} else {
			while ($text > '') {
				for ($fontid = 5; $fontid > 0; $fontid--) {
					$fontw = imagefontwidth($fontid);
					$fonth = imagefontheight($fontid);
					$realx = ($w - ($fontw * strlen($text))) / 2;
					$realy = ($h - $fonth - 5);

					// Since we can't use FreeType, lets use a fixed location
					$lines   = array();
					$lines[] = array($text, $fontid, 0, $realx, $realy);

					if ($realx > 10 && $realy > 0) {
						break;
					}
				}

				if ($fontid == 0) {
					$spacer = strrpos($text,' ');

					if ($spacer === false) {
						$spacer = strlen($text) - 1;
					}
					$text = substr($text,0,$spacer);
				} else {
					break;
				}
			}
		}

		foreach ($lines as $line) {
			if ($ttf_functions) {
				imagettftext($tab, $line[2], 0, intval($line[3]), intval($line[4]), $white, $line[1], $line[0]);
			} else {
				imagestring($tab, $line[1], intval($line[3]), intval($line[4]), $line[0], $white);
			}
		}

		putenv('GDFONTPATH=' . $originalpath);

		imagetruecolortopalette($tab, true, 256);

		// generate the image an return the data directly
		ob_start();
		imagegif($tab);
		$image = ob_get_contents();
		ob_end_clean();

		return ('data:image/gif;base64,' . base64_encode($image));
	} else {
		return false;
	}
}

function cacti_oid_numeric_format() {
	if (function_exists('snmp_set_oid_output_format')) {
		snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
	} elseif (function_exists('snmp_set_oid_numeric_print')) {
		snmp_set_oid_numeric_print(true);
	}
}

function IgnoreErrorHandler($message, $file = '', $line = null) {
	global $snmp_error;

	$log_ignored_errors = read_config_option('log_ignored_errors');

	$snmp_ignore = array(
		'No response from',
		'noSuchName',
		'No Such Object',
		'Error in packet',
		'This name does not exist',
		'End of MIB',
		'Timeout',
		'Unknown host',
		'Connection timed out',
		'Invalid object identifier',
		'Name or service not known',
		'USM generic error in file',
	);

	foreach ($snmp_ignore as $i) {
		if (stripos($message, $i) !== false) {
			$snmp_error = trim($message, "\\\n\t ");

			return true;
		}
	}

	$general_ignore = array(
		'unable to read from socket',        // ping.php socket refusal
		'No route to host',                  // fsocketopen
		'A temporary server error occurred', // dns_get_record
		'Maximum execution time of',
		'transport read',
	);

	foreach ($general_ignore as $i) {
		if (stripos($message, $i) !== false) {
			$message = trim($message, "\\\n\t ");

			if ($log_ignored_errors) {
				cacti_log("ERROR: '$message' in $file:$line", false, 'IGNORE');
				cacti_debug_backtrace('IGNORE ERROR', false, true, 0, 1);
			}

			return true;
		}
	}

	return false;
}

function CactiErrorHandler($level, $message, $file, $line, $context = array()) {
	global $phperrors;

	if (defined('IN_CACTI_INSTALL')) {
		return true;
	}

	if (IgnoreErrorHandler($message, $file, $line)) {
		return true;
	}

	if (error_reporting() == 0) {
		return true;
	}

	preg_match("/.*\/plugins\/([\w-]*)\/.*/", $file, $output_array);

	$plugin = (is_array($output_array) && isset($output_array[1]) ? $output_array[1] : '');
	$error  = 'Unknown error occurred';

	if ($level !== null && isset($phperrors[$level])) {
		$error  = 'PHP ' . $phperrors[$level] . ($plugin != '' ? " in  Plugin '$plugin'" : '') . ": $message in file: $file  on line: $line";
	} else {
		$error  = 'PHP Unknown Error' . ($plugin != '' ? " in  Plugin '$plugin'" : '') . ": $message in file: $file  on line: $line";
	}

	switch ($level) {
		case E_COMPILE_ERROR:
		case E_CORE_ERROR:
		case E_ERROR:
		case E_PARSE:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR PARSE', false, true, 0, 1);

			if ($plugin != '') {
				api_plugin_disable_all($plugin);
				cacti_log("ERRORS DETECTED - DISABLING PLUGIN '$plugin'");
				admin_email(__('Cacti System Warning'), __('Cacti disabled plugin %s due to the following error: %s!  See the Cacti logfile for more details.', $plugin, $error));
			}

			break;
		case E_RECOVERABLE_ERROR:
		case E_USER_ERROR:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR', false, true, 0, 1);

			break;
		case E_COMPILE_WARNING:
		case E_CORE_WARNING:
		case E_USER_WARNING:
		case E_WARNING:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR WARNING', false, true, 0, 1);

			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR NOTICE', false, true, 0, 1);

			break;
		case E_STRICT:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR STRICT', false, true, 0, 1);

			break;

		default:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR', false, true, 0, 1);
	}

	return false;
}

function CactiShutdownHandler() {
	global $phperrors;

	$phperrors = $phperrors ?? array(
		E_ERROR              => 'ERROR',
		E_WARNING            => 'WARNING',
		E_PARSE              => 'PARSE',
		E_NOTICE             => 'NOTICE',
		E_CORE_ERROR         => 'CORE_ERROR',
		E_CORE_WARNING       => 'CORE_WARNING',
		E_COMPILE_ERROR      => 'COMPILE_ERROR',
		E_COMPILE_WARNING    => 'COMPILE_WARNING',
		E_USER_ERROR         => 'USER_ERROR',
		E_USER_WARNING       => 'USER_WARNING',
		E_USER_NOTICE        => 'USER_NOTICE',
		E_STRICT             => 'STRICT',
		E_RECOVERABLE_ERROR  => 'RECOVERABLE_ERROR',
		E_DEPRECATED         => 'DEPRECATED',
		E_USER_DEPRECATED    => 'USER_DEPRECATED',
		E_ALL                => 'ALL'
	);

	$error = error_get_last();

	if (is_array($error)) {
		if (isset($error['message']) && IgnoreErrorHandler($error['message'])) {
			return true;
		}

		if (isset($error['type'])) {
			switch ($error['type']) {
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_PARSE:
					preg_match('/.*\/plugins\/([\w-]*)\/.*/', $error['file'], $output_array);

					$plugin = (isset($output_array[1]) ? $output_array[1] : '');

					if ($error['type'] !== null && isset($phperrors[$error['type']])) {
						$message = 'PHP ' . $phperrors[$error['type']] .
							($plugin != '' ? " in  Plugin '$plugin'" : '') . ': ' . $error['message'] .
							' in file: ' .  $error['file'] . ' on line: ' . $error['line'];
					} else {
						$message = 'PHP Unknown Error' .
							($plugin != '' ? " in  Plugin '$plugin'" : '') . ': ' . $error['message'] .
							' in file: ' .  $error['file'] . ' on line: ' . $error['line'];
					}

					cacti_log($message, false, 'ERROR');
					cacti_debug_backtrace('PHP ERROR', false, true, 0, 1);

					if ($plugin != '') {
						api_plugin_disable_all($plugin);
						cacti_log("ERRORS DETECTED - DISABLING PLUGIN '$plugin'");
						admin_email(__('Cacti System Warning'), __('Cacti disabled plugin %s due to the following error: %s!  See the Cacti logfile for more details.', $plugin, $message));
					}
			}
		}
	}
}

/**
 * enable_device_debug - Enables device debug for a device
 * if it is disabled.
 *
 * @param int $host_id - the device id to search for
 *
 * @return string
 */
function enable_device_debug(int $host_id): string {
	$device_debug = read_config_option('selective_device_debug', true);
	$devices      = explode(',', $device_debug ?? '');

	if (array_search($host_id, $devices, true) === false) {
		$devices[]    = $host_id;
		$device_debug = implode(',', $devices);
		set_config_option('selective_device_debug', $device_debug, true);
	}

	return $device_debug ?? '';
}

/**
 * disable_device_debug - Disables device debug for a device
 * if it is enabled.
 *
 * @param int $host_id - the device id to search for
 *
 * @return string
 */
function disable_device_debug(int $host_id): string {
	$device_debug = read_config_option('selective_device_debug', true);
	$devices      = explode(',', $device_debug ?? '');
	$device_index = array_search($host_id, $devices, true);

	if ($device_index !== false) {
		unset($devices[$device_index]);
		$device_debug = implode(',', $devices);
		set_config_option('selective_device_debug', $device_debug, true);
	}

	return $device_debug ?? '';
}

/**
 * is_device_debug_enabled - Determines if device debug is enabled
 * for a device.
 *
 * @param int $host_id - the device id to search for
 *
 * @return bool
 */
function is_device_debug_enabled(int $host_id): bool {
	$device_debug = read_config_option('selective_device_debug', true);
	$devices      = explode(',', $device_debug);

	return (array_search($host_id, $devices, true) !== false);
}

/**
 * get_url_type - Determines if remote communications are over
 * http or https for remote services.
 *
 * @return mixed http or https
 */
function get_url_type() {
	if (read_config_option('force_https') == 'on') {
		return 'https';
	} else {
		return 'http';
	}
}

/**
 * get_default_contextoption - Sets default context options for self-signed SSL
 * related protocols if necessary. Allows plugins to add additional header information
 * to fulfill system setup related requirements like the usage of Web Single Login
 * cookies for example.
 *
 * @param  (int|bool) A numeric timeout value, or null if not set
 * @param mixed $timeout
 *
 * @return (array)    An array to a context
 */
function get_default_contextoption($timeout = false) {
	$fgc_contextoption = array();

	if ($timeout === false) {
		$timeout = read_config_option('remote_agent_timeout');
	}

	if (!is_numeric($timeout) || empty($timeout) || $timeout <= 0) {
		$timeout = 5;
	}

	$protocol = get_url_type();

	if (in_array($protocol, array('ssl', 'https', 'ftps'), true)) {
		$fgc_contextoption = array(
			'ssl' => array(
				'verify_peer'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true,
			)
		);
	}

	if ($protocol == 'https') {
		$fgc_contextoption['https'] = array(
			'timeout'       => $timeout,
			'ignore_errors' => true
		);
	} elseif ($protocol == 'http') {
		$fgc_contextoption['http'] = array(
			'timeout'       => $timeout,
			'ignore_errors' => true
		);
	}

	$fgc_contextoption = api_plugin_hook_function('fgc_contextoption', $fgc_contextoption);

	return $fgc_contextoption;
}

/**
 * repair_system_data_input_methods - This utility will repair
 * system data input methods when they are detected on the system
 *
 * @return mixed null
 * @param mixed $step
 */
function repair_system_data_input_methods($step = 'import') {
	$system_hashes = array(
		'3eb92bb845b9660a7445cf9740726522', // Get SNMP Data
		'bf566c869ac6443b0c75d1c32b5a350e', // Get SNMP Data (Indexed)
		'80e9e4c4191a5da189ae26d0e237f015', // Get Script Data (Indexed)
		'332111d8b54ac8ce939af87a7eac0c06', // Get Script Server Data (Indexed)
	);

	$good_field_hashes = array(
		'3eb92bb845b9660a7445cf9740726522' => array( // Get SNMP Data (1)
			'92f5906c8dc0f964b41f4253df582c38', // IP Address
			'012ccb1d3687d3edb29c002ea66e72da', // SNMP Version
			'32285d5bf16e56c478f5e83f32cda9ef', // SNMP Community
			'fc64b99742ec417cc424dbf8c7692d36', // SNMP Port
			'ad14ac90641aed388139f6ba86a2e48b', // SNMP Username
			'9c55a74bd571b4f00a96fd4b793278c6', // SNMP Password
			'20832ce12f099c8e54140793a091af90', // SNMP Authentication Protocol
			'c60c9aac1e1b3555ea0620b8bbfd82cb', // SNMP Privacy Passphrase
			'feda162701240101bc74148415ef415a', // SNMP Privacy Protocol
			'4276a5ec6e3fe33995129041b1909762'  // SNMP OID
		),
		'bf566c869ac6443b0c75d1c32b5a350e' => array( // Get SNMP Data (Indexed) (2)
			'617cdc8a230615e59f06f361ef6e7728', // IP Address
			'b5c23f246559df38662c255f4aa21d6b', // SNMP Version
			'acb449d1451e8a2a655c2c99d31142c7', // SNMP Community
			'c1f36ee60c3dc98945556d57f26e475b', // SNMP Port
			'f4facc5e2ca7ebee621f09bc6d9fc792', // SNMP Username
			'1cc1493a6781af2c478fa4de971531cf', // SNMP Password
			'2cf7129ad3ff819a7a7ac189bee48ce8', // SNMP Authentication Protocol
			'6b13ac0a0194e171d241d4b06f913158', // SNMP Privacy Passphrase
			'3a33d4fc65b8329ab2ac46a36da26b72', // SNMP Privacy Protocol
			'6027a919c7c7731fbe095b6f53ab127b', // Index Type
			'cbbe5c1ddfb264a6e5d509ce1c78c95f', // Index Value
			'e6deda7be0f391399c5130e7c4a48b28'  // Output Type ID
		),
		'80e9e4c4191a5da189ae26d0e237f015' => array( // Get Script Data (Indexed) 11
			'd39556ecad6166701bfb0e28c5a11108', // Index Type
			'3b7caa46eb809fc238de6ef18b6e10d5', // Index Value
			'74af2e42dc12956c4817c2ef5d9983f9', // Output Type ID
			'8ae57f09f787656bf4ac541e8bd12537'  // Output Value
		),
		'332111d8b54ac8ce939af87a7eac0c06' => array( // Get Script Server Data (Indexed) 12
			'172b4b0eacee4948c6479f587b62e512', // Index Type
			'30fb5d5bcf3d66bb5abe88596f357c26', // Index Value
			'31112c85ae4ff821d3b288336288818c', // Output Type ID
			'5be8fa85472d89c621790b43510b5043'  // Output Value
		)
	);

	foreach ($good_field_hashes as $hash => $field_hashes) {
		$data_input_id = db_fetch_cell_prepared('SELECT id FROM data_input WHERE hash = ?', array($hash));

		if (!empty($data_input_id)) {
			$bad_hashes = db_fetch_assoc_prepared('SELECT *
				FROM data_input_fields
				WHERE hash NOT IN ("' . implode('","', $field_hashes) . '")
				AND hash != ""
				AND data_input_id = ?',
				array($data_input_id));

			if (cacti_sizeof($bad_hashes)) {
				cacti_log(strtoupper($step) . ' NOTE: Repairing ' . cacti_sizeof($bad_hashes) . ' Damaged data_input_fields', false);

				foreach ($bad_hashes as $bhash) {
					$good_field_id = db_fetch_cell_prepared('SELECT id
						FROM data_input_fields
						WHERE hash != ?
						AND data_input_id = ?
						AND data_name = ?',
						array($bhash['hash'], $data_input_id, $bhash['data_name']));

					if (!empty($good_field_id)) {
						cacti_log("Data Input ID $data_input_id Bad Field ID is " . $bhash['id'] . ', Good Field ID: ' . $good_field_id, false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

						cacti_log('Executing Data Input Data Check', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

						// Data Input Data
						$bad_mappings = db_fetch_assoc_prepared('SELECT *
							FROM data_input_data
							WHERE data_input_field_id = ?',
							array($bhash['id']));

						if (cacti_sizeof($bad_mappings)) {
							cacti_log(strtoupper($step) . ' NOTE: Found ' . cacti_sizeof($bad_mappings) . ' Damaged data_input_fields', false);

							foreach ($bad_mappings as $mfid) {
								$good_found = db_fetch_cell_prepared('SELECT COUNT(*)
									FROM data_input_data
									WHERE data_input_field_id = ?
									AND data_template_data_id = ?',
									array($good_field_id, $mfid['data_template_data_id']));

								if ($good_found > 0) {
									cacti_log('Good Found for ' . $mfid['data_input_field_id'] . ', Fixing', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

									db_execute_prepared('DELETE FROM data_input_data
										WHERE data_input_field_id = ?
										AND data_template_data_id = ?',
										array($mfid['data_input_field_id'], $mfid['data_template_data_id']));
								} else {
									cacti_log('Good NOT Found for ' . $mfid['data_input_field_id'] . ', Fixing', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

									db_execute_prepared('UPDATE data_input_data
										SET data_input_field_id = ?
										WHERE data_input_field_id = ?
										AND data_template_data_id = ?',
										array($good_field_id, $mfid['data_input_field_id'], $mfid['data_template_data_id']));
								}
							}
						} else {
							cacti_log('No Bad Data Input Data Records', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);
						}

						// Data Template RRD
						cacti_log('Executing Data Template RRD Check', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

						$bad_mappings = db_fetch_assoc_prepared('SELECT *
							FROM data_template_rrd
							WHERE data_input_field_id = ?',
							array($bhash['id']));

						if (cacti_sizeof($bad_mappings)) {
							cacti_log(strtoupper($step) . ' NOTE: Found ' . cacti_sizeof($bad_mappings) . ' Damaged data_template_rrd', false);

							foreach ($bad_mappings as $mfid) {
								$good_found = db_fetch_cell_prepared('SELECT COUNT(*)
									FROM data_template_rrd
									WHERE data_input_field_id = ?
									AND id = ?',
									array($good_field_id, $mfid['id']));

								if ($good_found > 0) {
									cacti_log('Good Found for ' . $mfid['data_input_field_id'] . ', Fixing', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

									db_execute_prepared('DELETE FROM data_template_rrd
										WHERE data_input_field_id = ?
										AND id = ?',
										array($mfid['data_input_field_id'], $mfid['id']));
								} else {
									cacti_log('Good NOT Found for ' . $mfid['data_input_field_id'] . ', Fixing', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);

									db_execute_prepared('UPDATE data_template_rrd
										SET data_input_field_id = ?
										WHERE data_input_field_id = ?
										AND id = ?',
										array($good_field_id, $mfid['data_input_field_id'], $mfid['id']));
								}
							}
						} else {
							cacti_log('No Bad Data Template RRD Records', false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);
						}

						db_execute_prepared('DELETE FROM data_input_fields WHERE hash = ?', array($bhash['hash']));
					} else {
						cacti_log('WARNING: Could not find Cacti default matching hash for unknown system hash "' . $bhash['hash'] . '" for ' . $data_input_id . '.  No repair performed.');
					}
				}
			}
		} else {
			cacti_log("Could not find hash '" . $hash . "' for Data Input", false, 'WEBUI', POLLER_VERBOSITY_DEVDBG);
		}
	}
}

if (isset($config['cacti_server_os']) && $config['cacti_server_os'] == 'win32' && !function_exists('posix_kill')) {
	function posix_kill($pid, $signal = SIGTERM) {
		$wmi   = new COM('winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2');
		$procs = $wmi->ExecQuery("SELECT ProcessId FROM Win32_Process WHERE ProcessId='" . $pid . "'");

		if (cacti_sizeof($procs)) {
			if ($signal == SIGTERM) {
				foreach ($procs as $proc) {
					$proc->Terminate();
				}
			} else {
				return true;
			}
		} else {
			return false;
		}
	}
}

function is_ipaddress($ip_address = '') {
	/* check for ipv4/v6 */
	if (function_exists('filter_var')) {
		if (filter_var($ip_address, FILTER_VALIDATE_IP) !== false) {
			return true;
		} else {
			return false;
		}
	} elseif (inet_pton($ip_address) !== false) {
		return true;
	} else {
		return false;
	}
}

/**
 * date_time_format		create a format string for date/time
 *
 * @return string returns	date time format
 */
function date_time_format() {
	$datechar = array(
		GDC_HYPHEN => '-',
		GDC_SLASH  => '/',
		GDC_DOT    => '.'
	);

	/* setup date format */
	$date_fmt        = read_config_option('default_date_format');
	$dateCharSetting = read_config_option('default_datechar');

	if (!isset($datechar[$dateCharSetting])) {
		$dateCharSetting = GDC_SLASH;
	}

	$datecharacter = $datechar[$dateCharSetting];

	switch ($date_fmt) {
		case GD_MO_D_Y:
			return 'm' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
		case GD_MN_D_Y:
			return 'M' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
		case GD_D_MO_Y:
			return 'd' . $datecharacter . 'm' . $datecharacter . 'Y H:i:s';
		case GD_D_MN_Y:
			return 'd' . $datecharacter . 'M' . $datecharacter . 'Y H:i:s';
		case GD_Y_MO_D:
			return 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
		case GD_Y_MN_D:
			return 'Y' . $datecharacter . 'M' . $datecharacter . 'd H:i:s';

		default:
			return 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
	}
}

function get_last_line(string $file) {
	$line   = '';
	$cursor = -1;

	$f = fopen($file, 'r');
	fseek($f, $cursor, SEEK_END);

	$char = fgetc($f);
	//Trim trailing newline characters in the file
	while ($char === '' || $char === "\r" || $char === "\n") {
		fseek($f, $cursor--, SEEK_END);
		$char = fgetc($f);
	}

	//Read until the next line of the file begins or the first newline char
	while ($char !== false && $char !== '' && $char !== "\r" && $char !== "\n") {
		//Prepend the new character
		$line = $char . $line;
		fseek($f, $cursor--, SEEK_END);
		$char = fgetc($f);
	}

	return $line;
}

function get_source_timestamp() {
	static $git_status = null;

	$parts     = $git_status;

	if ($git_status === null) {
		$git_path = realpath(__DIR__ . '/../.git/logs/HEAD');

		if (file_exists($git_path)) {
			$line = get_last_line($git_path);

			if (preg_match('/([0-9a-z]{40}) ([0-9a-z]{40}) .* ([0-9]{10})/', $line, $matches)) {
				$parts = array(
					intval($matches[3]),
					substr($matches[2], 0, 8),
				);
			}
		}
	}

	if ($parts === null) {
		$parts      = array(0, 'UNKNOWN');
	}

	if ($git_status === null) {
		$git_status = $parts;
	}

	return $parts;
}

function format_cacti_version($version, $format = CACTI_VERSION_FORMAT_FULL) {
	if ($version == 'new_install') {
		$version = CACTI_VERSION . ($format == CACTI_VERSION_FORMAT_FULL ? '.0.0' : ''); //($format == CACTI_VERSION_FORMAT_FULL) ? CACTI_VERSION_FULL : CACTI_VERSION;
	}

	$parts = explode('.', $version);

	if (count($parts) > 3) {
		if ($parts[3] == '-1') {
			$source = get_source_timestamp();
			cacti_log('Source: ' . json_encode($source ?? '<null>'), false, 'DEBUG');
			$parts[3] = 99;
			$parts[4] = $source[0];
			$parts[5] = $source[1];
		}
	}

	if ($format != CACTI_VERSION_FORMAT_FULL) {
		$parts = array_slice($parts, 0, 3);
	}

	return implode('.', $parts);
}

function format_cacti_version_text($version) {
	$version = format_cacti_version($version);

	$parts = explode('.', $version);

	while (count($parts) < 5) {
		$parts[] = '0';
	}

	$mode = '';

	if ($parts[3] == '99') {
		$stamp = '';

		if ($parts[4] > 0) {
			$dateTime = new DateTime();
			$dateTime->setTimestamp($parts[4]);
			$dateTime = $dateTime->format('Y-m-d H:i');

			if ($parts[5]) {
				$stamp = $parts[5] . ' @ ';
			}
			$stamp .= $dateTime;
		}
		$mode = __('- Dev %s', $stamp);
	} elseif ($parts[3] > '0') {
		$mode = __('- Beta %s', $parts[3]);
	}

	return trim(sprintf('%s.%s.%s %s', $parts[0], $parts[1], $parts[2], $mode));
}

/**
 * get_cacti_version       Generic function to get the cacti version
 * (depreciated)
 * @param mixed $force
 */
function get_cacti_version($force = false) {
	cacti_log('WARNING: get_cacti_version() called, consider replacing with CACTI_VERSION', false, 'DEPRECIATED');
	cacti_debug_backtrace('CACTI WARNING', false, true, 0, 1);

	return CACTI_VERSION;
}

/**
 * get_cacti_db_version    Generic function to get the cacti version from the db
 * @param mixed $force
 */
function get_cacti_db_version($force = false) {
	static $version = '';

	if ($version == '' || $force) {
		$version = trim(format_cacti_version(get_cacti_db_version_raw($force)));
	}

	return $version;
}

/**
 * get_cacti_db_version_raw    Generic function to get the cacti version from the db
 * @param mixed $force
 */
function get_cacti_db_version_raw($force = false) {
	static $version = '';

	if ($version == '' || $force) {
		$version = trim(db_fetch_cell('SELECT cacti FROM version LIMIT 1'));
	}

	return $version;
}

/**
 * get_cacti_version_text    Return the cacti version text including beta moniker
 * @param mixed $include_version
 * @param mixed $version
 */
function get_cacti_version_text($include_version = true, $version = CACTI_VERSION_FULL) {
	$version_text = format_cacti_version_text($version);

	if ($include_version) {
		return trim(__('Version %s %s', $version_text, ''));
	} else {
		return $version_text;
	}
}

/**
 * get_cacti_cli_version() {
 * @param mixed $include_db
 * @param mixed $version
 */
function get_cacti_cli_version($include_db = true, $version = CACTI_VERSION_FULL) {
	$version = get_cacti_version_text(false, $version);

	$dbversion = '';

	if ($include_db) {
		$dbversion = ' (DB: v' . get_cacti_db_version() . ')';
	}

	return $version . $dbversion;
}

/**
 * cacti_version_compare - Compare Cacti version numbers
 * @param mixed $version1
 * @param mixed $version2
 * @param mixed $operator
 */
function cacti_version_compare($version1, $version2, $operator = '>') {
	if ($version1 == 'new_install') {
		$version1 = CACTI_VERSION;
	}

	$decVersion1 = version_to_bits($version1);
	$decVersion2 = version_to_bits($version2);

	switch ($operator) {
		case '<':
			if ($decVersion1 < $decVersion2) {
				return true;
			}

			break;
		case '<=':
			if ($decVersion1 <= $decVersion2) {
				return true;
			}

			break;
		case '>=':
			if ($decVersion1 >= $decVersion2) {
				return true;
			}

			break;
		case '>':
			if ($decVersion1 > $decVersion2) {
				return true;
			}

			break;
		case '=':
		case '==':
			if ($decVersion1 == $decVersion2) {
				return true;
			}

			break;
		case '<>':
		case '!=':
			if ($decVersion1 != $decVersion2) {
				return true;
			}

			break;

		default:
			return version_compare($version1, $version2, $operator);
	}

	return false;
}

function is_install_needed($version = null) {
	$mode = '==';
	$db   = get_cacti_db_version();

	if ($version === null) {
		if (is_cacti_develop(CACTI_VERSION_FULL)) {
			$version = CACTI_DEV_VERSION;
			$mode    = '<';
		} else {
			$version = CACTI_VERSION_FULL;
		}
	}

	$version = format_cacti_version($version);
	$result  = (cacti_version_compare($db, $version, $mode));

	if (function_exists('log_install_medium')) {
		log_install_medium('step', "$result = (cacti_version_compare($db, $version, $mode)");
	}

	if ($mode == '==') {
		return !$result;
	} else {
		return $result;
	}
}

function is_cacti_develop($version = null) {
	static $isStaticRelease = null;

	if ($isStaticRelease === null || $version !== null) {
		if ($version === null) {
			$version = CACTI_VERSION_FULL;
		} else {
			$version = format_cacti_version($version);
		}

		$parts     = explode('.', $version);
		$isRelease = (count($parts) > 3 && $parts[3] == '99');

		if ($version === null) {
			$isStaticRelease = $isRelease;
		}
	} else {
		$isRelease = $isStaticRelease;
	}

	return $isRelease;
}

function is_cacti_release($version = null) {
	static $isStaticRelease = null;

	if ($isStaticRelease === null || $version !== null) {
		if ($version === null) {
			$version = CACTI_VERSION_FULL;
		} else {
			$version = format_cacti_version($version);
		}

		$parts     = explode('.', $version);
		$isRelease = ((count($parts) < 4) || ($parts[3] == '0'));

		if ($version === null) {
			$isStaticRelease = $isRelease;
		}
	} else {
		$isRelease = $isStaticRelease;
	}

	return $isRelease;
}

/**
 * Converts version to decimal
 *
 * @deprecated 1.3.0 Use version_to_bits instead
 *
 * @param  string  $version  Version to convert
 * @param  integer $length   Length of output
 * @param  boolean $hex      Convert to hex
 *
 * @return integer
 */
function version_to_decimal(string $version, int $length = 9, bool $hex = true): int {
	return version_to_bits($version, $hex);
}

/**
 * Converts a version string to a decimal using
 * bit shifting to compress data
 *
 * This function calls `format_cacti_version()`
 *
 * @param  string  $version		The version to be converted
 * @param  boolean $hex			Return the final decimal as hex
 *
 * @return integer
 *
 * @depends format_cacti_version
 */
function version_to_bits(string $version, $hex = false): int {
	/***************************************************
	 * Bits is how many bits to shift that section of a
	 * version to the left wihtin the integer.
	 *
	 * vMajor.Minor.Reversion.Patch.Timestamp
	 *
	 * Major     -  1 of  7  = 3 bits
	 * Minor     -  8 of 15  = 5 bits
	 * Reversion - 38 of 63  =
	 */
	static $bits = array(0,4,6,15,32);

	// Assume no version
	$newver = 0;

	// Do we have a version that isn't 'unknown'
	if ($version !== 'Unknown') {
		// Format the verison and explode it
		// and find the total number of parts
		$txtVersion = format_cacti_version($version);
		$parts      = explode('.', $txtVersion);
		$length     = cacti_sizeof($bits);

		// If we have no version, lets assume
		// we are starting with the DEV one
		if (cacti_sizeof($parts) == 0) {
			explode(',', CACTI_DEV_VERSION);
		}

		// If we have too many parts, then we
		// need to trim the fat down
		if (cacti_sizeof($parts) > $length) {
			$parts = array_slice($parts, 0, $length);
		}

		// If we have too few parts, then we
		// need to add in missing parts which
		// consist of the number 9 repeated
		// by the section
		while (cacti_sizeof($parts) < $length) {
			$part_count  = cacti_sizeof($parts);
			$part_prefix = ($part_count < 3) ? '0':'9';
			$part_length = ($part_count < 3) ? 0 : $part_count;

			$parts[] = str_repeat($part_prefix, $part_length + 1);
		}

		// Set the starting section to 0
		$section = 0;

		// Loop through the sections
		while ($section < $length) {
			// obtain the current part of the version string
			$part = $parts[$section];

			if ($part == 'x') {
				// If the part is an x then assume its really a zero
				$part = 0;
			} elseif (is_numeric($part)) {
				// If the part is numeric, then we can just use that
				$part = intval($part);
			} elseif ($part != 'x' && $section == 2) {
				// If the part isn't numeric and we are on the 3rd
				// section, try and break it up as it might be a hex
				// string
				$major  = strlen($part) > 0?char_to_dec($part[0]):'0';
				$minor  = strlen($part) > 1?char_to_dec($part[1]):'0';

				if (empty($part[3])) {
					// If patch (fourth section) has no number, then we
					// can use the second char of this number
					$parts[3] = intval(str_repeat('9', 4)) + $minor;
				}
				$part = $major;
			}

			// Used by debug output below
			// $oldver = $newver;

			// Get the number of bits to shift by
			$bitwise = $bits[$section];

			// Shift the new version along by that
			// many bits before we add the current
			// part
			$shifted = ($newver << $bitwise);
			$newver  = $shifted + intval($part);

			// Increase the section to look at
			$section++;

			// Debug output, for testing purposes.
			// Leaving this here just now
			//
			//printf("  %s:  %20s = %10s + %20s (%10s << %2s Max %s)\n", $section, $newver, $part, $shifted, $oldver, $bitwise, pow(2, $bitwise) - 1);
		}
	}

	// Do we have a valid hex value? If not, lets note it
	if (!ctype_xdigit("$newver")) {
		cacti_log('Invalid hex passed - ' . $newver . ' - ' . cacti_debug_backtrace('', false, false, 0, 1), false, 'WARNING');
	}

	return $hex ? @dechex($newver) : $newver;
}

function char_to_dec($part) {
	if (strlen($part) > 1) {
		$part = substr($part, -1);
	}

	return ord(strtoupper($part)) - ord('0');
}

/**
 * cacti_gethostinfo - obtains the dns information for a host
 * @param mixed $hostname
 * @param mixed $type
 */
function cacti_gethostinfo($hostname, $type = DNS_ALL) {
	if ($hostname != '') {
		return dns_get_record($hostname, $type);
	} else {
		return false;
	}
}

/**
 * cacti_gethostbyname - a ip/ipv6 replacement for php's gethostbyname function
 * @param mixed $hostname
 * @param mixed $type
 */
function cacti_gethostbyname($hostname, $type = '') {
	if ($type == '') {
		$type = DNS_A + DNS_AAAA;
	}

	if ($type != DNS_AAAA) {
		$host = gethostbyname($hostname);

		if ($host !== $hostname) {
			return $host;
		}
	}

	$return = cacti_gethostinfo($hostname, $type);

	if (cacti_sizeof($return)) {
		foreach ($return as $record) {
			switch($record['type']) {
				case 'A':
					return $record['ip'];

					break;
				case 'AAAA':
					return $record['ipv6'];

					break;
			}
		}
	}

	return $hostname;
}

function get_nonsystem_data_input($data_input_id) {
	global $hash_system_data_inputs;

	$diid = db_fetch_cell_prepared('SELECT id FROM data_input
		WHERE hash NOT IN ("' . implode('","', $hash_system_data_inputs) . '")
		AND id = ?',
		array($data_input_id));

	return $diid;
}

function get_rrdtool_version() {
	static $version = '';

	if ($version == '') {
		$version = str_replace('rrd-', '', str_replace('.x', '.0', read_config_option('rrdtool_version') ?: read_default_config_option('rrdtool_version') ?: '1.4.0'));
	}

	return $version;
}

function get_installed_rrdtool_version() {
	global $config, $rrdtool_versions;
	static $version = '';

	if ($version == '') {
		$rrdtool = read_config_option('path_rrdtool');

		if (!empty($rrdtool)) {
			if ($config['cacti_server_os'] == 'win32') {
				$shell = shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' -v');
			} else {
				$shell = shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' -v 2>&1');
			}

			$version = false;

			if (preg_match('/^RRDtool ([0-9.]+) /', $shell ?? '', $matches)) {
				foreach ($rrdtool_versions as $rrdtool_version => $rrdtool_version_text) {
					if (cacti_version_compare($rrdtool_version, $matches[1], '<=')) {
						$version = $rrdtool_version;
					}
				}
			}
		}
	}

	return $version;
}

function get_md5_hash($path) {
	$md5 = 0;

	if (db_table_exists('poller_resource_cache')) {
		$md5 = db_fetch_cell_prepared('SELECT md5sum
			FROM poller_resource_cache
			WHERE `path` = ?',
			array($path));
	}

	if (empty($md5)) {
		foreach (array($path, __DIR__ . '/../' . $path) as $file) {
			if (file_exists($file)) {
				$md5 = md5_file($file);

				break;
			}
		}

		if (empty($md5)) {
			cacti_log('Missing include file, unable to hash: ' . $path, false, 'WARN', POLLER_VERBOSITY_DEVDBG);
		}
	}

	return $md5;
}

function get_include_relpath(string $path, $basePath = null) {
	if ($basePath === null) {
		$basePath = CACTI_PATH_BASE;
	}
	$basePath = rtrim($basePath,'/') . '/';

	$npath = '';

	if (!empty($path)) {
		if (file_exists($path)) {
			$npath = str_replace($basePath, '', $path);
		} elseif (file_exists($basePath . $path)) {
			$npath = $path;
		}
	}

	return $npath;
}

/**
 * Returns a formatted output based on found paths.  If no paths are found
 * output will be blank
 *
 * @param  string      $format       The format to be used when a path/file exists
 * @param  string      $path         The base path to be used. Must include file if theme and file are not set
 * @param  null|string $theme        The theme to be used. If not set, current theme is assumed
 * @param  null|string $file         The file to be used.  If not set, extracted as last element of path
 * @param  bool        $pathFirst    Whether the `path` + `file` should be included first or last
 * @param  mixed       $args         Extra arguments to be passed when applying format
 * @return string
 * @throws Exception
 */
function get_theme_paths(string $format, string $path, ?string $theme = null, ?string $file = null, bool $pathFirst = false, ... $args) {
	$output = array();
	$paths  = array();

	$noFile = ($file === null);

	if ($noFile) {
		// Split the path up into elements
		$parts = explode('/', $path);

		// Pop the last element as that will be the file
		$file  = array_pop($parts);

		// Combine the remaining parts
		$path  = implode('/', $parts);
	}

	$path = rtrim($path, '/') . '/';

	if ($pathFirst) {
		// Add the base path first;
		$paths[] = $path;
	}

	if (!$noFile) {
		if (empty($theme)) {
			// We were passed a file but no theme, so get the current theme
			$theme = get_selected_theme();
		}

		if (!empty($theme)) {
			$themePath = rtrim($theme, '/') . '/';
			// Add path + theme to see if there is a themed version
			$paths[] = $path . $themePath;

			// If we aren't expliciting include themes, check them
			if ($path !== 'include/themes/') {
				// Add default theme include location
				$paths[] = 'include/themes/';

				// Add include + theme + path to see if there is a themed version
				$paths[] = 'include/themes/' . $themePath . $path;

				// Add include + theme to see if there is a themed version
				$paths[] = 'include/themes/' . $themePath;
			}
		}
	}

	if (!$pathFirst) {
		// Add the base path last;
		$paths[] = $path;
	}

	foreach ($paths as $index => $srcPath) {
		$srcFile = $srcPath . $file;
		$relFile = get_include_relpath($srcFile);

		if (!empty($relFile)) {
			$output[] = sprintf($format, CACTI_PATH_URL . $relFile  . '?' . get_md5_hash($relFile), ...$args);
		}
	}

	if (empty($output) && debounce_run_notification('missing: ' . $file)) {
		foreach ($paths as &$srcpath) {
			$srcpath = CACTI_PATH_BASE . str_replace(CACTI_PATH_BASE, '', $srcpath);
		}

		$npath = implode('", "', $paths);
		$ntext = sprintf('WARNING: Key Cacti Include File "%s" missing.  Please locate and replace this file as we checked in "%s"', $file, $npath);
		$itext = __('WARNING: Key Cacti Include File "%s" missing.  Please locate and replace this file as we checked in "%s"', $file, $npath);

		cacti_log($ntext, false, 'WEBUI');
		admin_email(__('Cacti System Warning'), $itext);
	}

	return implode(PHP_EOL, $output);
}

/**
 * Formatted output of javascript include with MD5 hash for uniqueness
 *
 * @param  string      $path    Path to include
 * @param  boolean     $async   Load asynchronously
 * @param  string|null $theme   Theme to use
 * @param  string|null $file    File to inlcude
 *
 * @return void
 */
function get_md5_include_js(string $path, bool $async = false, ?string $theme = null, ?string $file = null) {
	$format = '<script type=\'text/javascript\' src=\'%s\'%s></script>';

	return get_theme_paths($format, $path, $theme, $file, true, $async ? ' async' : '');
}

/**
 * Formatted output of stylesheet include with MD5 hash for uniqueness
 *
 * @param  string      $path    Path to include
 * @param  boolean     $async   Load asynchronously
 * @param  string|null $theme   Theme to use
 * @param  string|null $file    File to inlcude
 *
 * @return void
 */
function get_md5_include_css(string $path, bool $async = false, ?string $theme = null, ?string $file = null) {
	$format = '<link href=\'%s\' type=\'text/css\' rel=\'stylesheet\'%s>';

	return get_theme_paths($format, $path, $theme, $file, true, $async ? ' media=\'print\' online="this.media=\'all\'"' : '');
}

/**
 * Formatted output of images include with MD5 hash for uniqueness
 *
 * @param  string      $path    Path to include
 * @param  boolean     $async   Load asynchronously
 * @param  string|null $theme   Theme to use
 * @param  string|null $file    File to inlcude
 * @param  string|null $rel     Rel type output when not null (eg, icon, shortcut icon)
 * @param  string|null $sizes   Sizes output when not null (eg, 96x96)
 *
 * @return void
 */
function get_md5_include_icon(string $path, bool $async = false, ?string $theme = null, ?string $file = null, ?string $rel = null, ?string $sizes = null) {
	$format = '<link href=\'%s\' type=\'text/css\' %s%s>';

	if (!empty($rel)) {
		$rel = " rel='" . htmlspecialchars($rel) . "'";
	}

	if (!empty($sizes)) {
		$sizes = " sizes='" . htmlspecialchars($sizes) . "'";
	}

	return get_theme_paths($format, $path, $theme, $file, true, $rel, $sizes);
}

/**
 * Is the resource available to be written to?
 *
 * @param  string  $path
 *
 * @return boolean
 */
function is_resource_writable(string $path) {
	if (empty($path)) {
		return false;
	}

	if ($path[strlen($path) - 1] == '/') {
		return is_resource_writable($path . uniqid(mt_rand()) . '.tmp');
	}

	if (file_exists($path)) {
		if (is_writable($path)) {
			return true;
		} elseif ($f = @fopen($path, 'a')) {
			fclose($f);

			return true;
		}

		return false;
	} else {
		if (is_writable(dirname($path))) {
			return true;
		} elseif ($f = @fopen($path, 'w')) {
			fclose($f);
			unlink($path);

			return true;
		}
	}

	return false;
}

/**
 * Recursively change ownership of files/directories.  It should be
 * noted that this function exits on the first failure that it finds
 * and returns false.
 *
 * @param  string         $path   Path to be updated
 * @param  string|integer $uid    String or integer of user to set
 * @param  string|integer $gid    String or integer of group to set
 *
 * @return boolean
 */
function recursive_chown(string $path, string|int $uid, string|int $gid): bool {
	$d = opendir($path);

	while (($file = readdir($d)) !== false) {
		if ($file != '.' && $file != '..') {
			$fullpath = $path . '/' . $file;

			if (filetype($fullpath) == 'dir') {
				return recursive_chown($fullpath, $uid, $gid);
			}

			$success = chown($fullpath, $uid);

			if ($success) {
				$success = chgrp($fullpath, $gid);
			}
		}

		if (!$success) {
			return false;
		}
	}

	return true;
}

/**
 * Verifies that a theme exists.  If the theme does exist
 * return its name, otherwise returns the default theme.
 *
 * @param  string|null $theme
 * @param  string      $defaultTheme
 *
 * @return void
 */
function get_validated_theme(?string $theme, string $defaultTheme) {
	global $config;

	if (isset($theme) && strlen($theme)) {
		$themePath = CACTI_PATH_INCLUDE . '/themes/' . $theme . '/main.css';

		if (file_exists($themePath)) {
			return $theme;
		}
	}

	return $defaultTheme;
}

/**
 * Verifies that a language exists.  If the language
 * does exist, returns its name, otherwise returns
 * the default language.
 *
 * @param  [type] $language
 * @param  [type] $defaultLanguage
 *
 * @return void
 */
function get_validated_language($language, $defaultLanguage) {
	if (isset($language) && strlen($language)) {
		return $language;
	}

	return $defaultLanguage;
}

/**
 * Attempts to return the currently running user
 * via a number of methods.
 *
 * @return string
 */
function get_running_user():string {
	static $tmp_user = '';

	if (empty($tmp_user)) {
		if (function_exists('posix_geteuid')) {
			$tmp_user = posix_getpwuid(posix_geteuid())['name'];
		}
	}

	if (empty($tmp_user)) {
		$tmp_file = tempnam(sys_get_temp_dir(), 'uid');
		$f_owner  = '';

		if (is_resource_writable($tmp_file)) {
			if (file_exists($tmp_file)) {
				unlink($tmp_file);
			}

			file_put_contents($tmp_file, 'cacti');

			$f_owner  = fileowner($tmp_file);
			$f_source = 'file';

			if (file_exists($tmp_file)) {
				unlink($tmp_file);
			}
		}

		if (empty($f_owner) && function_exists('posix_getuid')) {
			$f_owner  = posix_getuid();
		}

		if (!empty($f_owner) && function_exists('posix_getpwuid1')) {
			$f_array = posix_getpwuid($f_owner);

			if (isset($f_array['name'])) {
				$tmp_user = $f_array['name'];
			}
		}

		if (empty($tmp_user)) {
			exec('id -nu', $o, $r);

			if ($r == 0) {
				$tmp_user = trim($o['0']);
			}
		}

		// Easy way first
		if (empty($tmp_user)) {
			$user = get_current_user();

			if ($user != '') {
				$tmp_user = $user;
			}
		}

		// Fallback method
		if (empty($tmp_user)) {
			$user = getenv('USERNAME');

			if ($user != '') {
				$tmp_user = $user;
			}

			if (empty($tmp_user)) {
				$user = getenv('USER');

				if ($user != '') {
					$tmp_user = $user;
				}
			}
		}
	}

	return (empty($tmp_user) ? 'apache' : $tmp_user);
}

/**
 * Returns a string for debugging purposes
 *
 * @return string
 */
function get_debug_prefix():string {
	$dateTime = new DateTime('NOW');
	$dateTime = $dateTime->format('Y-m-d H:i:s.u');

	return sprintf('<[ %s | %7d ]> -- ', $dateTime, getmypid());
}

/**
 * Gets the current client addr
 *
 * This function relies on an administrator to set
 * the appropriate proxy headers that are allowed
 * in the `config.php` include.
 *
 * @return string|false
 */
function get_client_addr():string|false {
	global $config, $allowed_proxy_headers;

	$proxy_headers = $config['proxy_headers'] ?? null;

	if ($proxy_headers === null) {
		$last_time = read_config_option('proxy_alert');

		if (empty($last_time)) {
			$last_time = date('Y-m-d');
		}

		$last_date = new DateTime($last_time);
		$this_date = new Datetime();

		$this_diff = $this_date->diff($last_date);
		$this_days = $this_diff->format('%a');

		if ($this_days) {
			cacti_log('WARNING: Configuration option "proxy_headers" will be automatically false in future releases.  Please set if you require proxy IPs to be used', false, 'AUTH');
			set_config_option('proxy_alert', date('Y-m-d'));
		}

		$proxy_headers = true;
	}

	/* If proxy_headers is true, allow all known headers -- NOT advised
	 * If proxy_headers is false, allow only REMOTE_ADDR
	 * IF proxy_headers is an array, filter by known headers
	 */
	if ($proxy_headers === true) {
		$proxy_headers = $allowed_proxy_headers;
	} elseif (is_array($proxy_headers) && is_array($allowed_proxy_headers)) {
		$proxy_headers = array_intersect($proxy_headers, $allowed_proxy_headers);
	}

	if (!is_array($proxy_headers)) {
		$proxy_headers = array();
	}

	if (!in_array('REMOTE_ADDR', $proxy_headers, true)) {
		$proxy_headers[] = 'REMOTE_ADDR';
	}

	$client_addr = false;

	foreach ($proxy_headers as $header) {
		if (!empty($_SERVER[$header])) {
			$header_ips = explode(',', $_SERVER[$header]);

			foreach ($header_ips as $header_ip) {
				if (!empty($header_ip)) {
					if (!filter_var($header_ip, FILTER_VALIDATE_IP)) {
						cacti_log('ERROR: Invalid remote client IP Address found in header (' . $header . ').', false, 'AUTH', POLLER_VERBOSITY_DEBUG);
					} else {
						$client_addr = $header_ip;
						cacti_log('DEBUG: Using remote client IP Address found in header (' . $header . '): ' . $client_addr . ' (' . $_SERVER[$header] . ')', false, 'AUTH', POLLER_VERBOSITY_DEBUG);

						break 2;
					}
				}
			}
		}
	}

	return $client_addr;
}

function cacti_pton($ipaddr) {
	// Strip out the netmask, if there is one.
	$subnet_pos = strpos($ipaddr, '/');

	if ($subnet_pos) {
		$subnet = substr($ipaddr, $subnet_pos + 1);
		$ipaddr = substr($ipaddr, 0, $subnet_pos);
	} else {
		$subnet = null; // No netmask present
	}

	// Convert address to packed format
	$addr = @inet_pton($ipaddr);

	if ($addr === false) {
		return false;
	}

	// Maximum netmask length = same as packed address
	$len = 8 * strlen($addr);

	if (!empty($subnet)) {
		if (!is_numeric($subnet)) {
			return false;
		}

		if ($subnet > $len) {
			return false;
		}
	}

	if (!is_numeric($subnet)) {
		$subnet=$len;
	} else {
		$subnet=(int)$subnet;
	}

	// Create a hex expression of the subnet mask
	$mask=str_repeat('f',$subnet >> 2);

	switch($subnet & 3) {
		case 3:
			$mask .= 'e';

			break;
		case 2:
			$mask .= 'c';

			break;
		case 1:
			$mask .= '8';

			break;
	}
	$mask=str_pad($mask,$len >> 2,'0');

	// Packed representation of netmask
	$mask=pack('H*',$mask);

	$result = array('ip' => $addr, 'subnet' => $mask);

	return $result;
}

function cacti_ntop($addr) {
	if (empty($addr)) {
		return false;
	}

	if (is_array($addr)) {
		foreach ($addr as $ip) {
			$addr = $ip;

			break;
		}
	}

	return @inet_ntop($addr);
}

function cacti_ntoc($subnet, $ipv6 = false) {
	$result = false;
	$count  = 0;

	foreach (str_split($subnet) as $char) {
		$i = ord($char);

		while (($i & 128) == 128) {
			$count++;
			$i = ($i << 1) % 256;
		}
	}

	return $count;
}

function cacti_ptoa($title, $addr) {
	// Let's display it as hexadecimal format
	foreach (str_split($addr) as $char) {
		print str_pad(dechex(ord($char)),2,'0',STR_PAD_LEFT);
	}
}

function cacti_sizeof($array) {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

function cacti_count($array) {
	return ($array === false || !is_array($array)) ? 0 : count($array);
}

function is_function_enabled($name) {
	return function_exists($name) &&
		!in_array($name, array_map('trim', explode(', ', ini_get('disable_functions'))), true) &&
		strtolower(ini_get('safe_mode')) != 1;
}

function is_page_ajax() {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		return true;
	}

	return false;
}

function raise_ajax_permission_denied() {
	if (is_page_ajax()) {
		header('HTTP/1.1 401 ' . __('Permission Denied'));
		print __('You are not permitted to access this section of Cacti.') . '  ' . __('If you feel that this is an error. Please contact your Cacti Administrator.');

		exit;
	}
}

/**
 * cacti_session_start - Create a Cacti session from the settings set by the administrator
 *
 * @return void
 */
function cacti_session_start() {
	global $config;

	/* initialize php session */
	if (!function_exists('session_name')) {
		die('PHP Session Management is missing, please install PHP Session module');
	}

	session_name($config[CACTI_SESSION_NAME]);

	if (session_status() === PHP_SESSION_NONE) {
		$session_restart = '';
	} else {
		$session_restart = 're';
	}

	/** @var array */
	$session_options = $config[COOKIE_OPTIONS];
	$session_result = session_start($session_options);

	if (!$session_result) {
		cacti_log('Session "' . session_id() . '" ' . $session_restart . 'start failed! ' . cacti_debug_backtrace('', false, false, 0, 1), false, 'WARNING:');
	}
}

/**
 * cacti_session_close - Closes the open Cacti session if it is open
 * it can be re-opened afterwards in the case after a long running query
 *
 * @return mixed null
 */
function cacti_session_close() {
	session_write_close();
}

/**
 * cacti_session_destroy - Destroys the login current session
 *
 * @return mixed null
 */
function cacti_session_destroy() {
	session_unset();
	session_destroy();
}

/**
 * cacti_cookie_set - Allows for settings an arbitry cookie name and value
 * used for CSRF protection.
 *
 * @return mixed null
 * @param mixed $session
 * @param mixed $val
 * @param null|mixed $timeout
 */
function cacti_cookie_set($session, $val, $timeout = null) {
	global $config;

	if (isset($config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN])) {
		$domain = $config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN];
	} else {
		$domain = '';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$secure = true;
	} else {
		$secure = false;
	}

	if (version_compare(PHP_VERSION, '7.3', '>=')) {
		$options = array(
			'path'     => CACTI_PATH_URL,
			'expires'  => $timeout ?? (time() + 3600),
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict'
		);

		setcookie($session, $val, $options);
	} else {
		setcookie($session, $val, time() + 3600, CACTI_PATH_URL, $domain, $secure, true);
	}
}

/**
 * cacti_cookie_logout - Clears the Cacti and the 'keep me logged in' cookies
 *
 * @return mixed null
 */
function cacti_cookie_logout() {
	global $config;

	if (isset($config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN])) {
		$domain = $config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN];
	} else {
		$domain = '';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$secure = true;
	} else {
		$secure = false;
	}

	$cookies = array(session_name(), session_name() . '_opt', 'cacti_rembers');

	if (version_compare(PHP_VERSION, '7.3', '>=')) {
		$options = array(
			'path'     => CACTI_PATH_URL,
			'expires'  => time() - 3600,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict'
		);

		foreach ($cookies as $cookie) {
			setcookie($cookie, '', $options);
		}
	} else {
		foreach ($cookies as $cookie) {
			setcookie($cookie, '', time() - 3600, CACTI_PATH_URL, $domain, $secure, true);
		}
	}

	unset($_COOKIE[$config[CACTI_SESSION_NAME]]);
}

/**
 * cacti_cookie_session_set - Sets the cacti 'keep me logged in' cookie
 *
 * @return mixed null
 * @param mixed $user
 * @param mixed $realm
 * @param mixed $nssecret
 */
function cacti_cookie_session_set($user, $realm, $nssecret) {
	global $config;

	if (isset($config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN])) {
		$domain = $config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN];
	} else {
		$domain = '';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$secure = true;
	} else {
		$secure = false;
	}

	$_SESSION['cacti_remembers'] = true;

	if (version_compare(PHP_VERSION, '7.3', '>=')) {
		$options = array(
			'path'     => CACTI_PATH_URL,
			'expires'  => time() + (86400 * 30),
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict'
		);

		setcookie('cacti_remembers', $user . ',' . $realm . ',' . $nssecret, $options);
	} else {
		setcookie('cacti_remembers', $user . ',' . $realm . ',' . $nssecret, time() + (86400 * 30), CACTI_PATH_URL, $domain, $secure, true);
	}
}

/**
 * cacti_cookie_session_logout - Logs out of Cacti and the remember me session
 *
 * @return mixed null
 */
function cacti_cookie_session_logout() {
	global $config;

	if (isset($config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN])) {
		$domain = $config[COOKIE_OPTIONS][COOKIE_OPTIONS_DOMAIN];
	} else {
		$domain = '';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$secure = true;
	} else {
		$secure = false;
	}

	if (version_compare(PHP_VERSION, '7.3', '>=')) {
		$options = array(
			'path'     => CACTI_PATH_URL,
			'expires'  => time() - 3600,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict'
		);

		setcookie('cacti_remembers', '', $options);
	} else {
		setcookie('cacti_remembers', '', time() - 3600, CACTI_PATH_URL, $domain, $secure, true);
	}
}

/**
 * cacti_browser_zone_set - Set the PHP timezone to the
 * browsers timezone if enabled.
 *
 * @return mixed null
 */
function cacti_browser_zone_set() {
	if (cacti_browser_zone_enabled()) {
		if (isset($_SESSION[SESS_BROWSER_PHP_TZ])) {
			ini_set('date.timezone', $_SESSION[SESS_BROWSER_PHP_TZ]);
			putenv('TZ=' . $_SESSION[SESS_BROWSER_SYSTEM_TZ]);
		}
	}
}

/**
 * cacti_system_zone_set - Set the PHP timezone to the
 * systems timezone.
 *
 * @return mixed null
 */
function cacti_system_zone_set() {
	if (cacti_browser_zone_enabled()) {
		if (isset($_SESSION[SESS_PHP_TZ])) {
			ini_set('date.timezone', $_SESSION[SESS_PHP_TZ]);
			putenv('TZ=' . $_SESSION[SESS_SYSTEM_TZ]);
		}
	}
}

function cacti_browser_zone_enabled() {
	$system_setting = read_config_option('client_timezone_support');

	if (empty($system_setting)) {
		return false;
	} else {
		$user_setting = read_user_setting('client_timezone_support', '0');

		if (empty($user_setting)) {
			return false;
		}

		return true;
	}
}

/**
 * cacti_time_zone_set - Givin an offset in minutes, attempt
 * to set a PHP date.timezone.  There are some oddballs that
 * we have to accomodate.
 *
 * @return mixed null
 * @param mixed $gmt_offset
 */
function cacti_time_zone_set($gmt_offset) {
	if (!cacti_browser_zone_enabled()) {
		return;
	}

	$hours     = floor($gmt_offset / 60);
	$remaining = $gmt_offset % 60;

	if (!isset($_SESSION[SESS_PHP_TZ])) {
		$_SESSION[SESS_PHP_TZ]    = ini_get('date.timezone');
		$_SESSION[SESS_SYSTEM_TZ] = getenv('TZ');
	}

	$zone = timezone_name_from_abbr('', $gmt_offset);

	if ($remaining == 0) {
		putenv('TZ=GMT' . ($hours > 0 ? '-':'+') . abs($hours));

		$sys_offset = 'GMT' . ($hours > 0 ? '-':'+') . abs($hours);

		if ($zone !== false) {
			$php_offset = $zone;
			ini_set('date.timezone', $zone);
		} else {
			$php_offset = 'Etc/GMT' . ($hours > 0 ? '-':'+') . abs($hours);
			ini_set('date.timezone', 'Etc/GMT' . ($hours > 0 ? '-':'+') . abs($hours));
		}

		$_SESSION[SESS_BROWSER_SYSTEM_TZ] = $sys_offset;
		$_SESSION[SESS_BROWSER_PHP_TZ]    = $php_offset;
	} else {
		$time = ($hours > 0 ? '-':'+') . abs($hours) . ':' . substr('00' . $remaining, -2);

		if ($zone === false) {
			switch($time) {
				case '+3:30':
					$zone = 'IRST';

					break;
				case '+4:30':
					$zone = 'IRDT';

					break;
				case '+5:30':
					$zone = 'IST';

					break;
				case '+5:45':
					$zone = 'NPT';

					break;
				case '+6:30':
					$zone = 'CCT';

					break;
				case '+9:30':
					$zone = 'ACST';

					break;
				case '+10:30':
					$zone = 'ACDT';

					break;
				case '+8:45':
					$zone = 'ACWST';

					break;
				case '+12:45':
					$zone = 'CHAST';

					break;
				case '+13:45':
					$zone = 'CHADT';

					break;
				case '-3:30':
					$zone = 'NST';

					break;
				case '-2:30':
					$zone = 'NDT';

					break;
				case '-9:30':
					$zone = 'MART';

					break;
			}

			if ($zone !== false) {
				$zone = timezone_name_from_abbr($zone);
			}
		}

		$php_offset = $zone;
		$sys_offset = 'GMT' . $time;

		putenv('TZ=GMT' . $time);

		if ($zone != '') {
			ini_set('date.timezone', $zone);
		}

		$_SESSION[SESS_BROWSER_SYSTEM_TZ] = $sys_offset;
		$_SESSION[SESS_BROWSER_PHP_TZ]    = $php_offset;
	}
}

function debounce_run_notification($id, $freqnency = 1200) {
	/* debounce admin emails */
	$last = read_config_option('debounce_' . $id);
	$now  = time();

	if (empty($last) || $now - $last > 7200) {
		set_config_option('debounce_' . $id, $now);

		return true;
	}

	return false;
}

/**
 * Return an array of sorted and unique IDs
 *
 * @param  string|array $ids
 * @param  boolean      $shouldExplode
 *
 * @return array
 */
function cacti_unique_ids($ids, bool $shouldExplode = true) {
	if ($shouldExplode && is_string($ids)) {
		$ids = explode(',', str_replace(' ', '', $ids));
	}

	if (!is_array($ids)) {
		$ids = array($ids);
	}

	$ids = array_filter(array_unique($ids));
	sort($ids);

	return $ids;
}

/**
 * Record a log of depreciated use
 *
 * @param string $text text to insert
 * @return void
 */
function cacti_depreciated(string $text) {
	cacti_debug_backtrace('WARN Depreciated use of ' . $text . ' at ');
}

function substring_index($subject, $delim, $count) {
	if ($count < 0) {
		return implode($delim, array_slice(explode($delim, $subject), $count));
	} else {
		return implode($delim, array_slice(explode($delim, $subject), 0, $count));
	}
}

function text_substitute(null|array|string $text, bool $isHtml = true, bool $includeStandard = true, ?array $extraSubtitutions = null, ?array $extraMatches = null) {
	if (!empty($text)) {
		$parser = 'text_regex_parser' . ($isHtml ? '_html' : '');

		$extraSubtitutions = $extraSubtitutions ?? array();
		$extraMatches      = $extraMatches ?? array();

		/* Get parts for text substitution */
		$extra_search = array_keys($extraSubtitutions);
		$extra_values = array_values($extraSubtitutions);

		$regex_array = $includeStandard ? text_get_regex_array($extraMatches) : $extraMatches;

		if (!empty($regex_array)) {
			$regex_complete = '';

			foreach ($regex_array as $regex_key => $regex_setting) {
				$regex_text = $regex_setting['regex'] ?? '';

				if (!empty($regex_text)) {
					$regex_complete .= (strlen($regex_complete) ? ')|(' : '') . $regex_text;
				} else {
					cacti_log('WARNING: Bad regex search: ' . json_encode($regex_setting), false, 'UTIL');
				}
			}

			$regex_complete = '~(' . $regex_complete . ')~';

			if (is_array($text)) {
				foreach ($text as &$line) {
					$line = text_substitute_line($line, $regex_complete, $parser, $extra_search, $extra_values);
				}
			} else {
				$text = text_substitute_line($text, $regex_complete, $parser, $extra_search, $extra_values);
			}
		}
	}

	return $text;
}

function text_substitute_line(string $source, string $regex, string $parser, array $search, array $values) {
	$result = $source;

	if (!empty($source)) {
		if (!empty($regex)) {
			$result = preg_replace_callback($regex, $parser, $result);
		}

		if (!empty($values) && cacti_sizeof($values) == cacti_sizeof($search)) {
			$result = str_replace($search, $values, $result);
		}
	}

	return $result;
}

function text_get_regex_array(?array $extraSubtitutions = array()) {
	static $regex_array = array();
	static $regex_extra = array();

	if ($extraSubtitutions !== null) {
		$regex_extra = $extraSubtitutions;
	}

	if (!cacti_sizeof($regex_array)) {
		$regex_array = array(
			1  => array('name' => 'DS',     'regex' => '( DS\[)([, \d]+)(\])',       'func' => 'text_regex_datasource'),
			2  => array('name' => 'DQ',     'regex' => '( DQ\[)([, \d]+)(\])',       'func' => 'text_regex_dataquery'),
			3  => array('name' => 'Device', 'regex' => '( Device\[)([, \d]+)(\])',   'func' => 'text_regex_device'),
			4  => array('name' => 'Poller', 'regex' => '( Poller\[)([, \d]+)(\])',   'func' => 'text_regex_poller'),
			5  => array('name' => 'RRA',    'regex' => "([_\/])(\d+)(\.rrd&#039;)",  'func' => 'text_regex_rra'),
			6  => array('name' => 'GT',     'regex' => '( GT\[)([, \d]+)(\])',       'func' => 'text_regex_graphtemplates'),
			7  => array('name' => 'Graph',  'regex' => '( Graph\[)([, \d]+)(\])',    'func' => 'text_regex_graphs'),
			8  => array('name' => 'Graphs', 'regex' => '( Graphs\[)([, \d]+)(\])',   'func' => 'text_regex_graphs'),
			9  => array('name' => 'User',   'regex' => '( User\[)([, \d]+)(\])',     'func' => 'text_regex_users'),
			10 => array('name' => 'User',   'regex' => '( Users\[)([, \d]+)(\])',    'func' => 'text_regex_users'),
			11 => array('name' => 'Rule',   'regex' => '( Rule\[)([, \d]+)(\])',   	 'func' => 'text_regex_rule'),
		);

		// We will currently issue two hooks, one for the clog portion for backwards
		// compatibility and one for new name.  In the future, the old hook will be
		// marked depreciated and removed.
		$regex_array = api_plugin_hook_function('clog_regex_array', $regex_array);
		$regex_array = api_plugin_hook_function('text_regex_array', $regex_array);
	}

	return array_merge($regex_array, $regex_extra);
}

function text_regex_replace($id, $link, $url, $matches, $cache) {
	global $config;

	if ($link) {
		return $matches[1] . '<a href=\'' . html_escape(CACTI_PATH_URL . sprintf($url,  $id)) . '\'>' . (isset($cache[$id]) ? html_escape($cache[$id]) : $id) . '</a>' . $matches[3];
	} else {
		return $matches[1] . (isset($cache[$id]) ? $cache[$id] : $id) . $matches[3];
	}
}

function text_regex_parser_html($matches) {
	return text_regex_parser($matches, true);
}

function text_regex_parser($matches, $link = false) {
	$result = $matches[0];
	$match  = $matches[0];

	$key_match = -1;

	for ($index = 1; $index < cacti_sizeof($matches); $index++) {
		if ($match == $matches[$index]) {
			$key_match = $index;

			break;
		}
	}

	if ($key_match != -1) {
		$key_setting = ($key_match - 1) / 4;
		$regex_array = text_get_regex_array();

		if (cacti_sizeof($regex_array)) {
			if (array_key_exists($key_setting, $regex_array)) {
				$regex_setting = $regex_array[$key_setting];

				$rekey_array = array();

				for ($j = 0; $j < 4; $j++) {
					$rekey_array[$j] = $matches[$key_match + $j];
				}

				if (function_exists($regex_setting['func'])) {
					$result = call_user_func_array($regex_setting['func'], array($rekey_array, $link));
				} else {
					$result = $match;
				}
			}
		}
	}

	return $result;
}

function text_regex_device($matches, $link = false) {
	static $host_cache = null;

	if (!cacti_sizeof($host_cache)) {
		$host_cache[0] = __('System Device');
	}

	$result = $matches[0];

	$dev_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($dev_ids)) {
		$result = '';

		foreach ($dev_ids as $id) {
			if (!isset($host_cache[$id])) {
				$host_cache[$id] = db_fetch_cell_prepared(
					'SELECT description
					FROM host
		            WHERE id = ?',
					array($id)
				);
			}

			$result .= text_regex_replace($id, $link, 'host.php?action=edit&id=%s', $matches, $host_cache);
		}
	}

	return $result;
}

function text_regex_datasource($matches, $link = false) {
	static $gr_cache = null;

	$result = $matches[0];

	$ds_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($ds_ids)) {
		$result     = '';
		$graph_rows = array();

		foreach ($ds_ids as $ds) {
			if (!isset($gr_cache[$ds])) {
				$gr_cache[$ds] = array_rekey(
					db_fetch_assoc_prepared(
						'SELECT DISTINCT
						gti.local_graph_id AS id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id=dtr.id
						WHERE gti.local_graph_id > 0
						AND dtr.local_data_id = ?',
						array($ds)
					),
					'id',
					'id'
				);
			}

			$graph_rows = array_merge($graph_rows, $gr_cache[$ds]);
		}

		$graph_results = '';

		if (cacti_sizeof($graph_rows)) {
			$graph_ids   = implode(',', $graph_rows);
			$graph_array = array(0 => '', 1 => ' Graphs[', 2 => $graph_ids, 3 => ']');

			$graph_results = text_regex_graphs($graph_array, $link);
		}

		$result = $matches[1];

		$ds_titles = get_data_source_titles($ds_ids);

		if (!isset($ds_titles)) {
			$ds_titles = array();
		}

		$sep           = '';
		$ds_matches    = $matches;
		$ds_matches[1] = $ds_matches[3] = '';

		foreach ($ds_ids as $ds_id) {
			$result .= $sep . text_regex_replace($ds_id, $link, 'data_sources.php?action=ds_edit&id=%s', $ds_matches, $ds_titles);
			$sep = ', ';
		}

		$result .= $matches[3];

		if (!empty($graph_results)) {
			$result .= ', ' . $graph_results;
		}
	}

	return $result;
}

function text_regex_poller($matches, $link = false) {
	static $poller_cache = null;

	if (!cacti_sizeof($poller_cache)) {
		$poller_cache = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM poller'),
			'id',
			'name'
		);
	}

	$result = $matches[0];

	$poller_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($poller_ids)) {
		$result = '';

		foreach ($poller_ids as $poller_id) {
			$result .= text_regex_replace($poller_id, $link, 'pollers.php?action=edit&id=%s', $matches, $poller_cache);
		}
	}

	return $result;
}

function text_regex_dataquery($matches, $link = false) {
	static $query_cache = null;

	if (!cacti_sizeof($query_cache)) {
		$query_cache = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM snmp_query'),
			'id',
			'name'
		);
	}

	$result = $matches[0];

	$query_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($query_ids)) {
		$result = '';

		foreach ($query_ids as $query_id) {
			$result .= text_regex_replace($query_id, $link, 'data_queries.php?action=edit&id=%s', $matches, $query_cache);
		}
	}

	return $result;
}

function text_regex_rra($matches, $link = false) {
	$result = $matches[0];

	$local_data_ids = $matches[2];

	if (strlen($local_data_ids)) {
		$datasource_array  = array(0 => '', 1 => ' DS[', 2 => $local_data_ids, 3 => ']');
		$datasource_result = text_regex_datasource($datasource_array, $link);

		if (strlen($datasource_result)) {
			$result .= ' ' . $datasource_result;
		}
	}

	return $result;
}

function text_regex_graphs($matches, $link = false) {
	global $config;

	static $graph_cache = null;

	$result = $matches[0];

	$graph_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($graph_ids)) {
		$result    = '';
		$graph_add = CACTI_PATH_URL . 'graph_view.php?page=1&style=selective&action=preview&graph_add=';

		$title = '';
		$i     = 0;

		foreach ($graph_ids as $id) {
			if (!isset($graph_cache[$id])) {
				$graph_cache[$id] = db_fetch_cell_prepared(
					'SELECT title_cache AS title
					FROM graph_templates_graph AS gtg
					WHERE local_graph_id = ?',
					array($id)
				);
			}
		}

		$i = 0;

		foreach ($graph_ids as $id) {
			$graph_add .= ($i > 0 ? '%2C' : '') . $id;
			$title .= ($title != '' ? ', ' : '') . html_escape((isset($graph_cache[$id]) ? html_escape($graph_cache[$id]) : $id));
			$i++;
		}

		if ($link) {
			$result .= $matches[1] . "<a href='" . html_escape($graph_add) . '\'>' . $title . '</a>' . $matches[3];
		} else {
			$result .= $title . $matches[3];
		}
	}

	return $result;
}

function text_regex_graphtemplates($matches, $link = false) {
	static $templates_cache = null;

	if (!cacti_sizeof($templates_cache)) {
		$templates_cache = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM graph_templates'),
			'id',
			'name'
		);
	}

	$result = $matches[0];

	$ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($ids)) {
		$result = '';

		foreach ($ids as $id) {
			$result .= text_regex_replace($id, $link, 'graph_templates.php?action=template_edit&id=%s', $matches, $templates_cache);
		}
	}

	return $result;
}

function text_regex_users($matches, $link = false) {
	static $users_cache = null;

	$result = $matches[0];

	$user_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($user_ids)) {
		$result = '';

		foreach ($user_ids as $id) {
			if (!isset($users_cache[$id])) {
				$users_cache[$id] = db_fetch_cell_prepared(
					'SELECT username
					FROM user_auth
					WHERE id = ?',
					array($id)
				);
			}
		}

		foreach ($user_ids as $id) {
			$result .= text_regex_replace($id, $link, 'user_admin.php?action=user_edit&tab=general&id=%s', $matches, $users_cache);
		}
	}

	return $result;
}

function text_regex_rule($matches, $link = false) {
	static $rules_cache = null;

	if (!cacti_sizeof($rules_cache)) {
		$rules_cache = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM automation_graph_rules'),
			'id',
			'name'
		);
	}

	$result = $matches[0];

	$dev_ids = cacti_unique_ids($matches[2]);

	if (cacti_sizeof($dev_ids)) {
		$result = '';

		foreach ($dev_ids as $rule_id) {
			$result .= text_regex_replace($rule_id, $link, 'automation_graph_rules.php?action=edit&id=%s', $matches, $rules_cache);
		}
	}

	return $result;
}
