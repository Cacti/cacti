<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

/**
 * title_trim - takes a string of text, truncates it to $max_length and appends
 * three periods onto the end
 *
 * @param $text - the string to evaluate
 *
 * @param $max_length - the maximum number of characters the string can contain
 * before it is truncated
 *
 * @return - the truncated string if len($text) is greater than $max_length, else the original string
 */
function title_trim(string $text, int $max_length): string {
	if (strlen($text) > $max_length) {
		return mb_substr($text, 0, $max_length) . '...';
	}

	return $text;
}

/**
 * filter_value - a quick way to highlight text in a table from general filtering
 *
 * @param $text - the string to filter
 * @param $filter - the search term to filter for
 * @param $href - the href if you wish to have an anchor returned
 *
 * @return - the filtered string
 */
function filter_value(string $value, string $filter, string $href = ''): string {
	static $charset;

	if ($charset == '') {
		$charset = ini_get('default_charset');
	}

	if ($charset == '') {
		$charset = 'UTF-8';
	}

	$value =  htmlspecialchars($value, ENT_QUOTES, $charset, false);

	if ($filter != '') {
		$value = preg_replace('#(' . $filter . ')#i', "<span class='filteredValue'>\\1</span>", $value);
	}

	if ($href != '') {
		$value = '<a class="linkEditMain" href="' . htmlspecialchars($href, ENT_QUOTES, $charset, false) . '">' . $value  . '</a>';
	}

	return $value;
}

/**
 * set_graph_config_option - deprecated - wrapper to set_user_setting().
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * @param $value       - the values to be saved
 * @param $user        - the user id, otherwise the session user
 *
 * @return          - void
 */
function set_graph_config_option(string $config_name, $value, int $user = -1): void {
	set_user_setting($config_name, $value, $user);
}

/**
 * graph_config_value_exists - deprecated - wrapper to user_setting_exists
 *
 * @param $config_name - the name of the configuration setting as specified $settings_user array
 * in 'include/global_settings.php'
 * @param $user_id - the id of the user to check the configuration value for
 *
 * @return (bool) - true if a value exists, false if a value does not exist
 */
function graph_config_value_exists(string $config_name, int $user_id): bool {
	return user_setting_exists($config_name, $user_id);
}

/**
 * read_default_graph_config_option - deprecated - wrapper to read_default_user_setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * in 'include/global_settings.php'
 *
 * @return - the default value of the configuration option
 */
function read_default_graph_config_option(string $config_name) {
	return read_default_user_setting($config_name);
}

/**
 * read_graph_config_option - deprecated - finds the current value of a graph configuration setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings_user array
 * in 'include/global_settings.php'
 *
 * @return - the current value of the graph configuration option
 */
function read_graph_config_option(string $config_name, bool $force = false) {
	return read_user_setting($config_name, false, $force);
}

/**
 * save_user_setting - sets/updates aLL user settings
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * @param $value       - the values to be saved
 * @param $user        - the user id, otherwise the session user
 *
 * @return          - void
 */
function save_user_settings(int $user = -1): void {
	global $settings_user;

	if ($user == -1 || empty($user)) {
		$user = $_SESSION['sess_user_id'];
	}

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
						$_SESSION['sess_error_fields'][$field_name] = $field_name;
						$_SESSION['sess_field_values'][$field_name] = get_nfilter_request_var($field_name);

						$errors[4] = 4;
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
 * @param $config_name - the name of the configuration setting as specified $settings array
 * @param $value       - the values to be saved
 * @param $user        - the user id, otherwise the session user
 *
 * @return          - void
 */
function set_user_setting(string $config_name, $value, int $user = -1): void {
	global $settings_user;

	if ($user == -1 && isset($_SESSION['sess_user_id'])) {
		$user = $_SESSION['sess_user_id'];
	}

	if ($user == -1) {
		if (isset($_SESSION['sess_user_id'])) {
			$mode = 'WEBUI';
		} else {
			$mode = 'POLLER';
		}

		cacti_log('NOTE: Attempt to set user setting \'' . $config_name . '\', with no user id: ' . cacti_debug_backtrace('', false, false, 0, 1), false, $mode, POLLER_VERBOSITY_MEDIUM);
	} elseif (db_table_exists('settings_user')) {
		db_execute_prepared('REPLACE INTO settings_user
			SET user_id = ?,
			name = ?,
			value = ?',
			array($user, $config_name, $value));

		unset($_SESSION['sess_user_config_array']);
		$settings_user[$config_name]['value'] = $value;
	}
}

/**
 * user_setting_exists - determines if a value exists for the current user/setting specified
 *
 * @param $config_name - the name of the configuration setting as specified $settings_user array
 * in 'include/global_settings.php'
 *
 * @param $user_id - the id of the user to check the configuration value for
 *
 * @return (bool) - true if a value exists, false if a value does not exist
 */
function user_setting_exists(string $config_name, int $user_id): bool {
	static $user_setting_values = array();

	if (!isset($user_setting_values[$config_name])) {
		$value = 0;

		if (db_table_exists('settings_user')) {
			$value = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM settings_user
				WHERE name = ?
				AND user_id = ?',
				array($config_name, $user_id));
		}

		if ($value !== false && $value > 0) {
			$user_setting_values[$config_name] = true;
		} else {
			$user_setting_values[$config_name] = false;
		}
	}

	return $user_setting_values[$config_name];
}

/**
 * clear_user_setting - if a value exists for the current user/setting specified, removes it
 *
 * @param $config_name - the name of the configuration setting as specified $settings_user array
 * in 'include/global_settings.php'
 *
 * @param $user_id - the id of the user to remove the configuration value for
 */
function clear_user_setting(string $config_name, int $user = -1): void {
	global $settings_user;

	if ($user == -1) {
		$user = $_SESSION['sess_user_id'];
	}

	if (db_table_exists('settings_user')) {
		db_execute_prepared('DELETE FROM settings_user
			WHERE name = ?
			AND user_id = ?',
			array($config_name, $user));
	}

	unset($_SESSION['sess_user_config_array']);
}

/**
 * read_default_user_setting - finds the default value of a user configuration setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * in 'include/global_settings.php'
 *
 * @return - the default value of the configuration option
 */
function read_default_user_setting(string $config_name) {
	global $config, $settings_user;

	foreach ($settings_user as $tab_array) {
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

/**
 * read_user_setting - finds the current value of a graph configuration setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings_user array
 * in 'include/global_settings.php'
 *
 * @param $default - the default value is none is set
 * @param $force - pull the data from the database if true ignoring session
 * @param $user - assume this user's identity
 *
 * @return - the current value of the user setting
 */
function read_user_setting(string $config_name, $default = false, bool $force = false, int $user = 0) {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */
	if ($user == 0 && isset($_SESSION['sess_user_id'])) {
		$effective_uid = $_SESSION['sess_user_id'];
	} elseif (read_config_option('auth_method') == 0 || $user > 0) {
		/* first attempt to get the db setting for guest */
		if ($user == 0) {
			$effective_uid = db_fetch_cell("SELECT user_auth.id
				FROM settings
				INNER JOIN user_auth
				ON user_auth.username = settings.value
				WHERE settings.name = 'guest_user'");

			if ($effective_uid == '') {
				$effective_uid = 0;
			}
		} else {
			$effective_uid = $user;
		}

		$db_setting = false;

		if (db_table_exists('settings_user')) {
			$db_setting = db_fetch_row_prepared('SELECT value
				FROM settings_user
				WHERE name = ?
				AND user_id = ?',
				array($config_name, $effective_uid));
		}

		if (cacti_sizeof($db_setting)) {
			return $db_setting['value'];
		}

		if ($default !== false) {
			return $default;
		} else {
			return read_default_user_setting($config_name);
		}
	} else {
		$effective_uid = 0;
	}

	if (!$force) {
		if (isset($_SESSION['sess_user_config_array'])) {
			$user_config_array = $_SESSION['sess_user_config_array'];
		}
	}

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

		if (isset($_SESSION)) {
			$_SESSION['sess_user_config_array'] = $user_config_array;
		} else {
			$config['config_user_settings_array'] = $user_config_array;
		}
	}

	return $user_config_array[$config_name];
}

/**
 * set_config_option - sets/updates a cacti config option with the given value.
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * @param $value       - the values to be saved
 *
 * @return          - void
 */
function set_config_option(string $config_name, $value): void {
	global $config;

	db_execute_prepared('REPLACE INTO settings
		SET name = ?, value = ?',
		array($config_name, $value));

	$config_array = array();

	if (isset($_SESSION['sess_config_array'])) {
		$config_array = $_SESSION['sess_config_array'];
	} elseif (isset($config['config_options_array'])) {
		$config_array = $config['config_options_array'];
	}

	$config_array[$config_name] = $value;

	// Store the array back for later retrieval
	if (isset($_SESSION)) {
		$_SESSION['sess_config_array']  = $config_array;
	} else {
		$config['config_options_array'] = $config_array;
	}

	if (!empty($config['DEBUG_SET_CONFIG_OPTION'])) {
		file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() . cacti_debug_backtrace($config_name, false, false, 0, 1) . "\n", FILE_APPEND);
	}
}

/**
 * config_value_exists - determines if a value exists for the current user/setting specified
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * in 'include/global_settings.php'
 *
 * @return (bool) - true if a value exists, false if a value does not exist
 */
function config_value_exists($config_name): bool {
	static $config_values = array();

	if (!isset($config_values[$config_name])) {
		$value = db_fetch_cell_prepared('SELECT COUNT(*) FROM settings WHERE name = ?', array($config_name));

		if ($value > 0) {
			$config_values[$config_name] = true;
		} else {
			$config_values[$config_name] = false;
		}
	}

	return $config_values[$config_name];
}

/**
 * read_default_config_option - finds the default value of a Cacti configuration setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * in 'include/global_settings.php'
 *
 * @return - the default value of the configuration option
 */
function read_default_config_option(string $config_name) {
	global $config, $settings;

	if (isset($settings)) {
		if (is_array($settings)) {
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
	}

	return null;
}

/**
 * read_config_option - finds the current value of a Cacti configuration setting
 *
 * @param $config_name - the name of the configuration setting as specified $settings array
 * in 'include/global_settings.php'
 *
 * @return - the current value of the configuration option
 */
function read_config_option(string $config_name, bool $force = false) {
	global $config, $database_hostname, $database_default, $database_port, $database_sessions;

	$config_array = array();

	if (isset($_SESSION['sess_config_array'])) {
		$config_array = $_SESSION['sess_config_array'];
	} elseif (isset($config['config_options_array'])) {
		$config_array = $config['config_options_array'];
	}

	if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
		file_put_contents(sys_get_temp_dir() . '/cacti-option.log', get_debug_prefix() . cacti_debug_backtrace($config_name, false, false, 0, 1) . "\n", FILE_APPEND);
	}

	// Do we have a value already stored in the array, or
	// do we want to make sure we have the latest value
	// from the database?
	if (!array_key_exists($config_name, $config_array) || ($force)) {
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
			$db_setting = db_fetch_row_prepared('SELECT value FROM settings WHERE name = ?', array($config_name), false);

			// Does the settings exist in the database?
			if (isset($db_setting['value'])) {
				// It does? lets use it
				$value = $db_setting['value'];
			}

			// Store whatever value we have in the array
			$config_array[$config_name] = $value;

			// Store the array back for later retrieval
			if (isset($_SESSION)) {
				$_SESSION['sess_config_array']  = $config_array;
			} else {
				$config['config_options_array'] = $config_array;
			}
		}
	} else {
		// We already have the value stored in the array and
		// we don't want to force a db read, so use the cached
		// version
		$value = $config_array[$config_name];
	}

	return $value;
}

/**
 * get_selected_theme - checks the user settings and if the user selected
 * theme is set, returns it otherwise returns the system default.
 *
 * @return - the theme name
 */
function get_selected_theme(string $theme = 'cacti'): string {
	global $config, $themes;

	// shortcut if theme is set in session
	if (isset($_SESSION['selected_theme'])) {
		$theme = $_SESSION['selected_theme'];
	} else {
		// default to system selected theme
		$theme = read_config_option('selected_theme');

		// check for a pre-1.x cacti being upgraded
		if ($theme == '') {
			$theme = 'cacti';
		}

		// figure out user defined theme
		if (isset($_SESSION['sess_user_id']) && db_table_exists('settings_user')) {
			// fetch user defined theme
			$user_theme = db_fetch_cell_prepared("SELECT value
				FROM settings_user
				WHERE name='selected_theme'
				AND user_id = ?",
				array($_SESSION['sess_user_id']));

			// user has a theme
			if (! empty($user_theme)) {
				$theme = $user_theme;
			}
		}
	}

	if (is_valid_theme($theme, true)) {
		$_SESSION['selected_theme'] = $theme;
	}

	return $theme;
}

/**
 * is_valid_theme
 *
 * Insert description here
 *
 * @param type $theme
 * @param 0 $set_user
 *
 * @return type
 */
function is_valid_theme(string &$theme, int $set_user = 0) {
	global $themes, $config;
	$valid = true;

	if (!file_exists($config['base_path'] . '/include/themes/' . $theme . '/main.css')) {
		$valid      = false;
		$user_table = db_table_exists('settings_user');

		foreach ($themes as $t => $name) {
			if (file_exists($config['base_path'] . '/include/themes/' . $t . '/main.css')) {
				$theme = $t;
				$valid = true;

				if ($user_table && $set_user && isset($_SESSION['sess_user_id'])) {
					db_execute_prepared('UPDATE settings_user
						SET value = ?
						WHERE user_id = ?
						AND name="selected_theme"',
						array($theme, $_SESSION['sess_user_id']));
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
 * $messages array in 'include/global_arrays.php'
 *
 * @return - the original $field_value
 */
function form_input_validate($field_value, string $field_name, string $regexp_match, bool $allow_nulls, string $custom_message = '3') {
	global $messages;

	/* write current values to the "field_values" array so we can retain them */
	$_SESSION['sess_field_values'][$field_name] = $field_value;

	if (($allow_nulls == true) && ($field_value == '')) {
		return $field_value;
	}

	if ($allow_nulls == false && $field_value == '') {
		if (read_config_option('log_validation') == 'on') {
			cacti_log("Form Validation Failed: Variable '$field_name' does not allow nulls and variable is null", false);
		}

		raise_message($custom_message);

		$_SESSION['sess_error_fields'][$field_name] = $field_name;
	} elseif ($regexp_match != '' && !preg_match('/' . $regexp_match . '/', $field_value)) {
		if (read_config_option('log_validation') == 'on') {
			cacti_log("Form Validation Failed: Variable '$field_name' with Value '$field_value' Failed REGEX '$regexp_match'", false);
		}

		raise_message($custom_message);

		$_SESSION['sess_error_fields'][$field_name] = $field_name;
	}

	return $field_value;
}

/**
 * check_changed - determines if a request variable has changed between page loads
 *
 * @return - (bool) true if the value changed between loads
 */
function check_changed(string $request, string $session): bool {
	if ((isset_request_var($request)) && (isset($_SESSION[$session]))) {
		if (get_nfilter_request_var($request) != $_SESSION[$session]) {
			return true;
		}
	}

	return false;
}

/**
 * is_error_message - finds whether an error message has been raised and has not been
 * output to the user
 *
 * @return - (bool) whether the messages array contains an error or not
 */
function is_error_message(): bool {
	global $config, $messages;

	if (isset($_SESSION['sess_error_fields']) && cacti_sizeof($_SESSION['sess_error_fields'])) {
		return true;
	} else {
		return false;
	}
}

/**
 * get_message_level
 *
 * Insert description here
 *
 * @param type $current_message
 *
 * @return type
 */
function get_message_level(array $current_message): int {
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
 * get_format_message_instance - finds the level of the current message instance
 *
 * @param message mixed the message instance
 * @param mixed $current_message
 *
 * @return - (string) a formatted message
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
 * @return - (string) the message type 'info', 'warn', 'error' or 'csrf'
 */
function get_message_max_type():int {
	global $messages;

	$level = MESSAGE_LEVEL_NONE;

	if (isset($_SESSION['sess_messages'])) {
		if (is_array($_SESSION['sess_messages'])) {
			foreach ($_SESSION['sess_messages'] as $current_message_id => $current_message) {
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
	}

	return $level;
}

/**
 * raise_message - mark a message to be displayed to the user once display_output_messages() is called
 *
 * @param $message_id - the ID of the message to raise as defined in $messages in 'include/global_arrays.php'
 */
function raise_message($message_id, string $message = '', int $message_level = MESSAGE_LEVEL_NONE): void {
	global $config, $messages, $no_http_headers;

	// This function should always exist, if not its an invalid install
	if (function_exists('session_status')) {
		$need_session = (session_status() == PHP_SESSION_NONE) && (!isset($no_http_headers));
	} else {
		return;
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
		} elseif (isset($_SESSION[$message_id])) {
			$message       = $_SESSION[$message_id];
			$message_level = MESSAGE_LEVEL_ERROR;
		} else {
			$message       = __('Message Not Found.');
			$message_level = MESSAGE_LEVEL_ERROR;
		}
	}

	if ($need_session) {
		cacti_session_start();
	}

	if (!isset($_SESSION['sess_messages'])) {
		$_SESSION['sess_messages'] = array();
	}

	$_SESSION['sess_messages'][$message_id] = array('message' => $message, 'level' => $message_level);

	if ($need_session) {
		cacti_session_close();
	}
}

/**
 * display_output_messages - displays all of the cached messages from the raise_message() function and clears
 * the message cache
 */
function display_output_messages(): string {
	global $messages;

	$omessage      = array();
	$debug_message = debug_log_return('new_graphs');

	if ($debug_message != '') {
		$omessage['level']    = MESSAGE_LEVEL_NONE;
		$omessage['message']  = $debug_message;

		debug_log_clear('new_graphs');
	} elseif (isset($_SESSION['sess_messages'])) {
		if (!is_array($_SESSION['sess_messages'])) {
			$_SESSION['sess_messages'] = array('custom_error' => array('level' => 3, 'message' => $_SESSION['sess_messages']));
		}

		$omessage['level'] = get_message_max_type();

		foreach ($_SESSION['sess_messages'] as $current_message_id => $current_message) {
			$message = get_format_message_instance($current_message);

			if (!empty($message)) {
				$omessage['message'] = (isset($omessage['message']) && $omessage['message'] != '' ? $omessage['message'] . '<br>':'') . $message;
			} else {
				cacti_log("ERROR: Cacti Error Message Id '$current_message_id' Not Defined", false, 'WEBUI');
			}
		}
	}

	clear_messages();

	return json_encode($omessage);
}

/**
 * display_custom_error_message
 *
 * Insert description here
 *
 * @param type $message
 */
function display_custom_error_message(string $message): void {
	raise_message('custom_error', $message);
}

/**
 * clear_messages - clears the message cache
 */
function clear_messages(): void {
	// This function should always exist, if not its an invalid install
	if (function_exists('session_status')) {
		$need_session = (session_status() == PHP_SESSION_NONE) && (!isset($no_http_headers));

		if ($need_session) {
			cacti_session_start();
		}

		kill_session_var('sess_messages');

		if ($need_session) {
			cacti_session_close();
		}
	}
}

/**
 * kill_session_var - kills a session variable using unset()
 */
function kill_session_var(string $var_name): void {
	/* register_global = on: reset local settings cache so the user sees the new settings */
	unset($_SESSION[$var_name]);

	/* register_global = off: reset local settings cache so the user sees the new settings */
	/* session_unregister is deprecated in PHP 5.3.0, unset is sufficient */
	if (version_compare(PHP_VERSION, '5.3.0', '<')) {
		session_unregister($var_name);
	} else {
		unset($var_name);
	}
}

/**
 * force_session_data - forces session data into the session if the session was closed for some reason
 */
function force_session_data(): void {
	// This function should always exist, if not its an invalid install
	if (function_exists('session_status') && (session_status() == PHP_SESSION_NONE)) {
		$data = $_SESSION;

		cacti_session_start();

		$_SESSION = $data;

		cacti_session_close();
	}
}

/**
 * array_rekey - changes an array in the form:
 *
 * '$arr[0] = array('id' => 23, 'name' => 'blah')'
 *
 * to the form
 *
 * '$arr = array(23 => 'blah')'
 *
 * @param $array - (array) the original array to manipulate
 * @param $key - the name of the key
 * @param mixed $key_value - the name of the key value
 *
 * @return - the modified array
 */
function array_rekey(array $array, string $key, $key_value): array {
	$ret_array = array();

	if (is_array($array)) {
		foreach ($array as $item) {
			$item_key = $item[$key];

			if (is_array($key_value)) {
				foreach ($key_value as $value) {
					$ret_array[$item_key][$value] = $item[$value];
				}
			} else {
				$ret_array[$item_key] = $item[$key_value];
			}
		}
	}

	return $ret_array;
}

/**
 * cacti_log_file - returns the log filename
 */
function cacti_log_file(): string {
	global $config;
	$logfile        = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}
	$config['log_path'] = $logfile;

	return $logfile;
}

/**
 * cacti_log - logs a string to Cacti's log file or optionally to the browser
 *
 * @param $string - the string to append to the log file
 * @param $output - (bool) whether to output the log line to the browser using print() or not
 * @param $environ - (string) tells from where the script was called from
 * @param $level - (int) only log if above the specified log level
 */
function cacti_log(string $string, bool $output = false, string $environ = 'CMDPHP', int $level = 0): void {
	global $config, $database_log;

	if (!isset($database_log)) {
		$database_log = false;
	}

	$last_log     = $database_log;
	$database_log = false;

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

	$force_level = '';
	$debug_files = read_config_option('selective_debug');

	if ($debug_files != '') {
		$files = explode(',', $debug_files);

		if (array_search($current_file, $files, true) !== false) {
			$force_level = POLLER_VERBOSITY_DEBUG;
		}
	}

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

	/* only log if the specific level is reached, developer debug is special low + specific devdbg calls */
	if ($force_level == '') {
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

	/* fill in the current date for printing in the log */
	if (defined('CACTI_DATE_TIME_FORMAT')) {
		$date = date(CACTI_DATE_TIME_FORMAT);
	} else {
		$date = date('Y-m-d H:i:s');
	}

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = cacti_log_file();

	/* format the message */
	if ($environ == 'POLLER') {
		$prefix = "$date - " . $environ . ': Poller[' . $config['poller_id'] . '] ';
	} else {
		$prefix = "$date - " . $environ . ' ';
	}

	/* Log to Logfile */
	$message = clean_up_lines($string) . PHP_EOL;

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
				syslog(LOG_CRIT, $environ . ': ' . $string);
			} elseif ($log_type == 'warn' && read_config_option('log_pwarn')) {
				syslog(LOG_WARNING, $environ . ': ' . $string);
			} elseif (($log_type == 'stat' || $log_type == 'note') && read_config_option('log_pstats')) {
				syslog(LOG_INFO, $environ . ': ' . $string);
			}

			closelog();
		}
	}

	/* print output to standard out if required */
	if ($output == true && isset($_SERVER['argv'][0])) {
		print $message;
	}

	$database_log = $last_log;
}

/**
 * tail_file - Emulates the tail function with PHP native functions.
 *
 * It is used in 0.8.6 to speed the viewing of the Cacti log file, which
 * can be problematic in the 0.8.6 branch.
 *
 * @param $file_name    - (char constant) the name of the file to tail
 *   $line_cnt     - (int constant)  the number of lines to count
 *   $message_type - (int constant) the type of message to return
 *   $filter       - (char) the filtering expression to search for
 *   $page_nr      - (int) the page we want to show rows for
 *   $total_rows   - (int) the total number of rows in the logfile
 */
function tail_file(string $file_name, int $number_of_lines, int $message_type = -1, string $filter = '', int &$page_nr = 1, int &$total_rows = 0, array $field_map = array()): array {
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
		$should_expand = !empty($filter) && !empty($field_map) && !empty($field_map['data']) && !empty($field_map['func']);
	}

	while (($line = fgets($fp)) !== false) {
		$display = (determine_display_log_entry($message_type, $line, $filter));

		if ($should_expand && !$display) {
			$expanded = preg_replace_callback($field_map['data'],$field_map['func'],$line);

			if ($expanded != $line) {
				// expand line different so lets see if we want it now after all
				$display = determine_display_log_entry($message_type, $expanded, $filter);
			}
		}

		$display_line[$line_no++] = $display;

		if ($display) {
			++$total_rows;
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
		$display = $display_line[$line_no++];

		if ($display === false) {
			continue;
		}

		if ($i < $start) {
			++$i;

			continue;
		}

		if ($i >= $end) {
			break;
		}

		++$i;
		$file_array[$i] = $line;
	}

	fclose($fp);

	return $file_array;
}

/**
 * Function to determine if we display the line
 *
 * @param int $message_type
 * @param string $line
 * @param string $filter
 *
 * @return bool
 */
function determine_display_log_entry(int $message_type, string $line, string $filter): bool {
	/* determine if we are to display the line */
	switch ($message_type) {
		case 1: /* stats */
			$display = (strpos($line, 'STATS') !== false);

			break;
		case 2: /* warnings */
			$display = (strpos($line, 'WARN') !== false);

			break;
		case 3: /* errors */
			$display = (strpos($line, 'ERROR') !== false);

			break;
		case 4: /* debug */
			$display = (strpos($line, 'DEBUG') !== false && strpos($line, ' SQL ') === false);

			break;
		case 5: /* sql calls */
			$display = (strpos($line, ' SQL ') !== false);

			break;

		default: /* all other lines */
		case -1: /* all */
			$display = true;

			break;
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
 *
 * It will also output to the appropriate log file when an event occurs.
 *
 * @param $status - (int constant) the status of the host (Up/Down)
 * @param $host_id - (int) the host ID for the results
 * @param $hosts - (array) a memory resident host table for speed
 * @param $ping - (class array) results of the ping command.
 */
function update_host_status(int $status, int $host_id, array &$hosts, array &$ping, int $ping_availability, bool $print_data_to_stdout) {
	$issue_log_message   = false;
	$ping_failure_count  = read_config_option('ping_failure_count');
	$ping_recovery_count = read_config_option('ping_recovery_count');
	/* initialize fail and recovery dates correctly */
	if ($hosts[$host_id]['status_fail_date'] == '') {
		$hosts[$host_id]['status_fail_date'] = '0000-00-00 00:00:00';
	}

	if ($hosts[$host_id]['status_rec_date'] == '') {
		$hosts[$host_id]['status_rec_date'] = '0000-00-00 00:00:00';
	}

	if ($status == HOST_DOWN) {
		/* Set initial date down. BUGFIX */
		if (empty($hosts[$host_id]['status_fail_date'])) {
			$hosts[$host_id]['status_fail_date'] = date('Y-m-d H:i:s');
		}

		/* update total polls, failed polls and availability */
		$hosts[$host_id]['failed_polls']++;
		$hosts[$host_id]['total_polls']++;
		$hosts[$host_id]['availability'] = 100 * ($hosts[$host_id]['total_polls'] - $hosts[$host_id]['failed_polls']) / $hosts[$host_id]['total_polls'];

		/* determine the error message to display */
		if (($ping_availability == AVAIL_SNMP_AND_PING) || ($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				/* snmp version 1/2 without community string assume SNMP test to be successful
				   due to backward compatibility issues */
				$hosts[$host_id]['status_last_error'] = $ping->ping_response;
			} else {
				$hosts[$host_id]['status_last_error'] = $ping->snmp_response . ', ' . $ping->ping_response;
			}
		} elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				$hosts[$host_id]['status_last_error'] = 'Device does not require SNMP';
			} else {
				$hosts[$host_id]['status_last_error'] = $ping->snmp_response;
			}
		} else {
			$hosts[$host_id]['status_last_error'] = $ping->ping_response;
		}

		/* determine if to send an alert and update remainder of statistics */
		if ($hosts[$host_id]['status'] == HOST_UP) {
			/* increment the event failure count */
			$hosts[$host_id]['status_event_count']++;

			/* if it's time to issue an error message, indicate so */
			if ($hosts[$host_id]['status_event_count'] >= $ping_failure_count) {
				/* host is now down, flag it that way */
				$hosts[$host_id]['status'] = HOST_DOWN;

				$issue_log_message = true;

				/* update the failure date only if the failure count is 1 */
				if ($hosts[$host_id]['status_event_count'] == $ping_failure_count) {
					$hosts[$host_id]['status_fail_date'] = date('Y-m-d H:i:s');
				}
			/* host is down, but not ready to issue log message */
			} else {
				/* host down for the first time, set event date */
				if ($hosts[$host_id]['status_event_count'] == $ping_failure_count) {
					$hosts[$host_id]['status_fail_date'] = date('Y-m-d H:i:s');
				}
			}
		/* host is recovering, put back in failed state */
		} elseif ($hosts[$host_id]['status'] == HOST_RECOVERING) {
			$hosts[$host_id]['status'] = HOST_DOWN;

			$hosts[$host_id]['status_event_count'] = 1;
		/* host was unknown and now is down */
		} elseif ($hosts[$host_id]['status'] == HOST_UNKNOWN) {
			$hosts[$host_id]['status'] = HOST_DOWN;

			$hosts[$host_id]['status_event_count'] = 0;
		} else {
			$hosts[$host_id]['status_event_count']++;
		}
	/* host is up!! */
	} else {
		/* update total polls and availability */
		$hosts[$host_id]['total_polls']++;
		$hosts[$host_id]['availability'] = 100 * ($hosts[$host_id]['total_polls'] - $hosts[$host_id]['failed_polls']) / $hosts[$host_id]['total_polls'];

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
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				$ping_time = 0.000;
			} else {
				/* calculate the average of the two times */
				$ping_time = ($ping->snmp_status + $ping->ping_status) / 2;
			}
		} elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
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
			$hosts[$host_id]['cur_time'] = $ping_time;

			/* maximum time */
			if ($ping_time > $hosts[$host_id]['max_time']) {
				$hosts[$host_id]['max_time'] = $ping_time;
			}

			/* minimum time */
			if ($ping_time < $hosts[$host_id]['min_time']) {
				$hosts[$host_id]['min_time'] = $ping_time;
			}

			/* average time */
			$hosts[$host_id]['avg_time'] = (($hosts[$host_id]['total_polls'] - 1 - $hosts[$host_id]['failed_polls'])
				* $hosts[$host_id]['avg_time'] + $ping_time) / ($hosts[$host_id]['total_polls'] - $hosts[$host_id]['failed_polls']);
		}

		/* the host was down, now it's recovering */
		if (($hosts[$host_id]['status'] == HOST_DOWN) || ($hosts[$host_id]['status'] == HOST_RECOVERING)) {
			/* just up, change to recovering */
			if ($hosts[$host_id]['status'] == HOST_DOWN) {
				$hosts[$host_id]['status'] = HOST_RECOVERING;

				$hosts[$host_id]['status_event_count'] = 1;
			} else {
				$hosts[$host_id]['status_event_count']++;
			}

			/* if it's time to issue a recovery message, indicate so */
			if ($hosts[$host_id]['status_event_count'] >= $ping_recovery_count) {
				/* host is up, flag it that way */
				$hosts[$host_id]['status'] = HOST_UP;

				$issue_log_message = true;

				/* update the recovery date only if the recovery count is 1 */
				if ($hosts[$host_id]['status_event_count'] == $ping_recovery_count) {
					$hosts[$host_id]['status_rec_date'] = date('Y-m-d H:i:s');
				}

				/* reset the event counter */
				$hosts[$host_id]['status_event_count'] = 0;
			/* host is recovering, but not ready to issue log message */
			} else {
				/* host recovering for the first time, set event date */
				if ($hosts[$host_id]['status_event_count'] == $ping_recovery_count) {
					$hosts[$host_id]['status_rec_date'] = date('Y-m-d H:i:s');
				}
			}
		} else {
		/* host was unknown and now is up */
			$hosts[$host_id]['status'] = HOST_UP;

			$hosts[$host_id]['status_event_count'] = 0;
		}
	}
	/* if the user wants a flood of information then flood them */
	if (($hosts[$host_id]['status'] == HOST_UP) || ($hosts[$host_id]['status'] == HOST_RECOVERING)) {
		/* log ping result if we are to use a ping for reachability testing */
		if ($ping_availability == AVAIL_SNMP_AND_PING) {
			cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
			cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout, 'PING', POLLER_VERBOSITY_HIGH);
		} elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
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
		if ($hosts[$host_id]['status'] == HOST_DOWN) {
			cacti_log("Device[$host_id] ERROR: HOST EVENT: Device is DOWN Message: " . $hosts[$host_id]['status_last_error'], $print_data_to_stdout);
		} else {
			cacti_log("Device[$host_id] NOTICE: HOST EVENT: Device Returned FROM DOWN State: ", $print_data_to_stdout);
		}
	}

	db_execute_prepared('UPDATE host SET
		status = ?,
		status_event_count = ?,
		status_fail_date = ?,
		status_rec_date = ?,
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
			$hosts[$host_id]['status'],
			$hosts[$host_id]['status_event_count'],
			$hosts[$host_id]['status_fail_date'],
			$hosts[$host_id]['status_rec_date'],
			$hosts[$host_id]['status_last_error'],
			$hosts[$host_id]['min_time'],
			$hosts[$host_id]['max_time'],
			$hosts[$host_id]['cur_time'],
			$hosts[$host_id]['avg_time'],
			$hosts[$host_id]['total_polls'],
			$hosts[$host_id]['failed_polls'],
			$hosts[$host_id]['availability'],
			$hosts[$host_id]['hostname']
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
function is_hexadecimal(string $result): bool {
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
 * @param $hostname - (string) the hostname for a device
 *
 * @return - (string) the stripped hostname
 */
function strip_domain(string $hostname): string {
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
 * @param $result - (string) some string to be evaluated
 *
 * @return - (bool) either to result is a mac address of not
 */
function is_mac_address(string $result): bool {
	if (!defined('FILTER_VALIDATE_MAC')) {
		if (preg_match('/^([0-9a-f]{1,2}[\.:-]) {5}([0-9a-f]{1,2})$/i', $result)) {
			return true;
		}

		return false;
	}

	return filter_var($result, FILTER_VALIDATE_MAC);
}

/**
 * is_hex_string
 *
 * Insert description here
 *
 * @param type $result
 *
 * @return type
 */
function is_hex_string(string &$result): bool {
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
 * @param $result - (string) the result from the poll, the result can be modified in the call
 *
 * @return - (bool) either to result is valid or not
 */
function prepare_validate_result(string &$result): bool {
	/* first trim the string */
	$result = trim($result, "'\"\n\r");

	/* clean off ugly non-numeric data */
	if (is_numeric($result)) {
		return true;
	}

	if ($result == 'U') {
		return true;
	}

	if (is_hexadecimal($result)) {
		return hexdec($result);
	}

	if (substr_count($result, ':') || substr_count($result, '!')) {
		/* looking for name value pairs */
		if (substr_count($result, ' ') == 0) {
			return true;
		} else {
			$delim_cnt = 0;

			if (substr_count($result, ':')) {
				$delim_cnt = substr_count($result, ':');
			} elseif (strstr($result, '!')) {
				$delim_cnt = substr_count($result, '!');
			}

			$space_cnt = substr_count($result, ' ');

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
 * @param $string - (char) the string to be evaluated
 * @return - either the numeric value or false if not numeric
 * @param mixed $string
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
 * @param $path - (char) the pathname to be tested
 *
 * @return - either true or false
 */
function is_valid_pathname(string $path): bool {
	if (preg_match('/^([a-zA-Z0-9\_\.\-\\\:\/]+)$/', trim($path))) {
		return true;
	} else {
		return false;
	}
}

/**
 * get_full_script_path - gets the full path to the script to execute to obtain data for a
 * given data source. this function does not work on SNMP actions, only script-based actions
 *
 * @param $local_data_id - (int) the ID of the data source
 *
 * @return - the full script path or (bool) false for an error
 */
function get_full_script_path(int $local_data_id): string {
	global $config;

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		data_template_data.id,
		data_template_data.data_input_id,
		data_input.type_id,
		data_input.input_string
		FROM (data_template_data, data_input)
		WHERE data_template_data.data_input_id = data_input.id
		AND data_template_data.local_data_id = ?',
		array($local_data_id));

	/* snmp-actions don't have paths */
	if (($data_source['type_id'] == DATA_INPUT_TYPE_SNMP) || ($data_source['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY)) {
		return false;
	}

	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . "
		data_input_fields.data_name,
		data_input_data.value
		FROM data_input_fields
		LEFT JOIN data_input_data
		ON (data_input_fields.id = data_input_data.data_input_field_id)
		WHERE data_input_fields.data_input_id  = ?
		AND data_input_data.data_template_data_id = ?
		AND data_input_fields.input_output = 'in'",
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
	$replace   = array($config['base_path'], read_config_option('path_snmpget'), read_config_option('path_php_binary'));
	$full_path = str_replace($search, $replace, $full_path);

	/* sometimes a certain input value will not have anything entered... null out these fields
	in the input string so we don't mess up the script */
	return preg_replace('/(<[A-Za-z0-9_]+>)+/', '', $full_path);
}

/**
 * get_data_source_item_name - gets the name of a data source item or generates a new one if one does not
 * already exist
 *
 * @param $data_template_rrd_id - (int) the ID of the data source item
 *
 * @return - the name of the data source item or an empty string for an error
 */
function get_data_source_item_name(int $data_template_rrd_id): string {
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
		$data_source_name = substr(strtolower($data_source_name), 0, (19 - strlen($data_template_rrd_id))) . $data_template_rrd_id;

		return $data_source_name;
	} else {
		return $data_source['data_source_name'];
	}
}

/**
 * get_data_source_path - gets the full path to the .rrd file associated with a given data source
 *
 * @param $local_data_id - (int) the ID of the data source
 * @param $expand_paths - (bool) whether to expand the <path_rra> variable into its full path or not
 *
 * @return - the full path to the data source or an empty string for an error
 */
function get_data_source_path(int $local_data_id, bool $expand_paths): string {
	global $config;
	static $data_source_path_cache = array();

	if (empty($local_data_id)) {
		return '';
	}

	if (isset($data_source_path_cache[$local_data_id])) {
		return $data_source_path_cache[$local_data_id];
	}

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' name, data_source_path FROM data_template_data WHERE local_data_id = ?', array($local_data_id));

	if (cacti_sizeof($data_source) > 0) {
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
			$data_source_path = str_replace('<path_rra>/', $config['rra_path'] . '/', $data_source_path);
		}

		$data_source_path_cache[$local_data_id] = $data_source_path;

		return $data_source_path;
	}
}

/**
 * stri_replace - a case insensitive string replace
 *
 * @param $find - needle
 * @param $replace - replace needle with this
 * @param $string - haystack
 *
 * @return - the original string with '$find' replaced by '$replace'
 */
function stri_replace(string $find, string $replace, string $string): string {
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
 * clean_up_lines - runs a string through a regular expression designed to remove
 * new lines and the spaces around them
 *
 * @param $string - the string to modify/clean
 *
 * @return - the modified string
 */
function clean_up_lines(?string $string): ?string {
	if ($string != null) {
		$string = preg_replace('/\s*[\r\n]+\s*/',' ', $string);
	}

	return $string;
}

/**
 * clean_up_name - runs a string through a series of regular expressions designed to
 * eliminate "bad" characters
 *
 * @param $string - the string to modify/clean
 *
 * @return - the modified string
 */
function clean_up_name(?string $string): ?string {
	if ($string != null) {
		$string = preg_replace('/[\s\.]+/', '_', $string);
		$string = preg_replace('/[^a-zA-Z0-9_]+/', '', $string);
		$string = preg_replace('/_{2,}/', '_', $string);
	}

	return $string;
}

/**
 * clean_up_file name - runs a string through a series of regular expressions designed to
 * eliminate "bad" characters
 *
 * @param $string - the string to modify/clean
 *
 * @return - the modified string
 */
function clean_up_file_name(?string $string): ?string {
	if ($string != null) {
		$string = preg_replace('/[\s\.]+/', '_', $string);
		$string = preg_replace('/[^a-zA-Z0-9_-]+/', '', $string);
		$string = preg_replace('/_{2,}/', '_', $string);
	}

	return $string;
}

/**
 * clean_up_path - takes any path and makes sure it contains the correct directory
 * separators based on the current operating system
 *
 * @param $path - the path to modify
 *
 * @return - the modified path
 */
function clean_up_path(?string $path): ?string {
	global $config;

	if ($path != null) {
		if ($config['cacti_server_os'] == 'win32') {
			$path = str_replace('/', '\\', $path);
		} elseif ($config['cacti_server_os'] == 'unix' || read_config_option('using_cygwin') == 'on' || read_config_option('storage_location')) {
			$path = str_replace('\\', '/', $path);
		}
	}

	return $path;
}

/**
 * get_data_source_title - returns the title of a data source without using the title cache
 *
 * @param $local_data_id - (int) the ID of the data source to get a title for
 *
 * @return - the data source title
 */
function get_data_source_title(int $local_data_id): string {
	$data = db_fetch_row_prepared('SELECT dl.host_id, dl.snmp_query_id, dl.snmp_index, dtd.name
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dtd.local_data_id = dl.id
		WHERE dl.id = ?',
		array($local_data_id));

	if (cacti_sizeof($data)) {
		if (strstr($data['name'], '|') !== false && $data['host_id'] > 0) {
			$data['name'] = substitute_data_input_data($data['name'], '', $local_data_id);

			return expand_title($data['host_id'], $data['snmp_query_id'], $data['snmp_index'], $data['name']);
		} else {
			return $data['name'];
		}
	} else {
		return 'Missing Datasource ' . $local_data_id;
	}
}

/**
 * get_device_name - returns the description of the device in cacti host table
 *
 * @param $host_id - (int) the ID of the device to get a description for
 *
 * @return - the device name
 */
function get_device_name(int $host_id): string {
	return db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id));
}

/**
 * get_color - returns the hex color value from the cacti colors table
 *
 * @param $color_id - (int) the ID of the cacti color
 *
 * @return - the hex color value
 */
function get_color(int $color_id): string {
	return db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', array($color_id));
}

/**
 * get_graph_title - returns the title of a graph without using the title cache
 *
 * @param $local_graph_id - (int) the ID of the graph to get a title for
 * @return - the graph title
 */
function get_graph_title(int $local_graph_id): string {
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
	}

	return '';
}

/**
 * get_username - returns the username for the selected user
 *
 * @param $user_id - (int) the ID of the user
 *
 * @return - the username
 */
function get_username(int $user_id): string {
	return db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id));
}

/**
 * get_execution_user - returns the username of the running process
 *
 * @return - the username
 */
function get_execution_user(): string {
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
 * @return - the new generated path
 */
function generate_data_source_path(int $local_data_id): string {
	global $config;

	/* try any prepend the name with the host description */
	$host = db_fetch_row_prepared('SELECT host.id, host.description
		FROM (host, data_local)
		WHERE data_local.host_id = host.id
		AND data_local.id = ?
		LIMIT 1', array($local_data_id));

	$host_name = $host['description'];
	$host_id   = $host['id'];

	/* put it all together using the local_data_id at the end */
	if (read_config_option('extended_paths') == 'on') {
		$new_path = "<path_rra>/$host_id/$local_data_id.rrd";
	} else {
		if (!empty($host_name)) {
			$host_part = strtolower(clean_up_file_name($host_name)) . '_';
		}

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
 * @param $data_template_id
 * @param $requested_cf
 * @param $ds_step
 * @param mixed $local_data_id
 *
 * @return - the best cf to use
 */
function generate_graph_best_cf($local_data_id, $requested_cf, int $ds_step = 60): string {
	static $best_cf;

	if ($local_data_id > 0) {
		$avail_cf_functions = get_rrd_cfs($local_data_id);

		if (cacti_sizeof($avail_cf_functions)) {
			/* workaround until we have RRA presets in 0.8.8 */
			/* check through the cf's and get the best */
			/* if none was found, take the first */
			$best_cf = $avail_cf_functions[1];

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
 * @return - array of the CF functions
 */
function get_rrd_cfs(int $local_data_id): array {
	global $consolidation_functions;
	static $rrd_cfs = array();

	if (array_key_exists($local_data_id, $rrd_cfs)) {
		return $rrd_cfs[$local_data_id];
	}

	$cfs = array();

	$rrdfile = get_data_source_path($local_data_id, true);

	$output = @rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT);

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
 * @return - a letter-based representation of the input argument
 */
function generate_graph_def_name(int $graph_item_id): string {
	$lookup_table = array('a','b','c','d','e','f','g','h','i','j');

	$result    = '';
	$strValGII = strval($graph_item_id);

	for ($i=0; $i < strlen($strValGII); $i++) {
		$result .= $lookup_table[substr($strValGII, $i, 1)];
	}

	if (preg_match('/^(cf|cdef|def)$/', $result)) {
		return 'zz' . $result;
	}

	return $result;
}

/**
 * generate_data_input_field_sequences - re-numbers the sequences of each field associated
 * with a particular data input method based on its position within the input string
 *
 * @param $string - the input string that contains the field variables in a certain order
 * @param $data_input_id - (int) the ID of the data input method
 */
function generate_data_input_field_sequences(string $string, int $data_input_id): void {
	global $config, $registered_cacti_names;

	if (preg_match_all('/<([_a-zA-Z0-9]+)>/', $string, $matches)) {
		$j = 0;

		for ($i=0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names, true) === false) {
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
 * move_graph_group - takes a graph group (parent+children) and swaps it with another graph group
 *
 * @param $graph_template_item_id - (int) the ID of the (parent) graph item that was clicked
 * @param $graph_group_array - (array) an array containing the graph group to be moved
 * @param $target_id - (int) the ID of the (parent) graph item of the target group
 * @param $direction - ('next' or 'previous') whether the graph group is to be swapped with
 * group above or below the current group
 */
function move_graph_group(int $graph_template_item_id, array $graph_group_array, int $target_id, string $direction): void {
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
 * @return - (array) an array containing each item in the graph group
 */
function get_graph_group(int $graph_template_item_id): array {
	global $graph_item_types;

	$graph_item = db_fetch_row_prepared('SELECT graph_type_id, sequence, local_graph_id, graph_template_id
		FROM graph_templates_item
		WHERE id = ?',
		array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	} else {
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	/* parents are LINE%, AREA%, and STACK%. If not return */
	if (!preg_match('/(LINE|AREA|STACK)/', $graph_item_types[$graph_item['graph_type_id']])) {
		return array();
	}

	$graph_item_children_array = array();

	/* put the parent item in the array as well */
	$graph_item_children_array[$graph_template_item_id] = $graph_template_item_id;

	$graph_items = db_fetch_assoc('SELECT id, graph_type_id, text_format, hard_return
		FROM graph_templates_item
		WHERE sequence > ' . $graph_item['sequence'] . "
		AND $sql_where
		ORDER BY sequence");

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
 * @param $graph_template_item_id - (int) the ID of the current graph item
 * @param $direction - ('next' or 'previous') whether to find the next or previous parent
 *
 * @return - (int) the ID of the next or previous parent graph item id
 */
function get_graph_parent(int $graph_template_item_id, string $direction): int {
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
 * @return - (int) the ID of the next or previous item id
 */
function get_item(string $tblname, string $field, int $startid, string$lmt_query, string $direction): int {
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
 * @param $id - (int) the current id
 * @param $field - the field name that contains the target id
 * @param $table_name - the table name that contains the target id
 * @param $group_query - an SQL "where" clause to limit the query
 *
 * @return - (int) the next available sequence id
 */
function get_sequence(int $id, string $field, string $table_name, string $group_query): int {
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
function move_item_down(string $table_name, int $current_id, string $group_query = ''): void {
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
function move_item_up(string $table_name, int $current_id, string $group_query = ''): void {
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
 * exec_into_array - executes a command and puts each line of its output into an array
 *
 * @param $command_line - the command to execute
 *
 * @return - (array) an array containing the command output
 */
function exec_into_array(string $command_line): array {
	$out = array();
	$err = 0;
	exec($command_line,$out,$err);

	return array_values($out);
}

/**
 * get_web_browser - determines the current web browser in use by the client
 *
 * @return - ('ie' or 'moz' or 'other')
 */
function get_web_browser(): string {
	if (!empty($_SERVER['HTTP_USER_AGENT'])) {
		if (stristr($_SERVER['HTTP_USER_AGENT'], 'Mozilla') && (!(stristr($_SERVER['HTTP_USER_AGENT'], 'compatible')))) {
			return 'moz';
		}

		if (stristr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
			return 'ie';
		} else {
			return 'other';
		}
	}

	return 'other';
}

/**
 * get_guest_account
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_guest_account(): int {
	$id = db_fetch_cell_prepared('SELECT id
		FROM user_auth
		WHERE username = ? OR id = ?',
		array(read_config_option('guest_user'), read_config_option('guest_user')));

	return intval($id);
}

/**
 * get_template_account
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_template_account(): int {
	$id = db_fetch_cell_prepared('SELECT id
		FROM user_auth
		WHERE username = ? OR id = ?',
		array(read_config_option('user_template'), read_config_option('user_template')));

	return intval($id);
}

/**
 * draw_login_status - provides a consistent login status page for all pages that use it
 */
function draw_login_status(bool $using_guest_account = false): void {
	global $config;

	$guest_account = get_guest_account();
	$auth_method   = read_config_option('auth_method');

	if (isset($_SESSION['sess_user_id']) && $_SESSION['sess_user_id'] == $guest_account) {
		api_plugin_hook('nav_login_before');
		print __('Logged in as') . " <span id='user' class='user usermenuup'>". __('guest') . "</span></div><div><ul class='menuoptions' style='display:none;'><li><a href='" . $config['url_path'] . "index.php?login=true'>" . __('Login as Regular User') . "</a></li>\n";
		print "<li class='menuHr'><hr class='menu'></li>";
		print "<li id='userCommunity'><a href='https://forums.cacti.net' target='_blank' rel='noopener'>" . __('User Community') . '</a></li>';
		print "<li id='userDocumentation'><a href='https://github.com/Cacti/documentation/blob/develop/README.md' target='_blank' rel='noopener'>" . __('Documentation') . '</a></li>';
		print '</ul>';

		api_plugin_hook('nav_login_after');
	} elseif (isset($_SESSION['sess_user_id']) && $using_guest_account == false) {
		$user = db_fetch_row_prepared('SELECT username, password_change, realm FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
		api_plugin_hook('nav_login_before');

		print __('Logged in as') . " <span id='user' class='user usermenuup'>" . html_escape($user['username']) .
			"</span></div><div><ul class='menuoptions' style='display:none;'>";

		print "<li><a href='#' class='loggedInAs' style='display:none;'>" . __esc('Logged in as %s', $user['username']) . "</a></li><hr class='menu'>";

		print (is_realm_allowed(20) ? "<li><a href='" . html_escape($config['url_path'] . 'auth_profile.php?action=edit') . "'>" . __('Edit Profile') . '</a></li>':'');
		print ($user['password_change'] == 'on' && $user['realm'] == 0 ? "<li><a href='" . html_escape($config['url_path'] . 'auth_changepassword.php') . "'>" . __('Change Password') . '</a></li>':'');
		print "<li class='menuHr'><hr class='menu'></li>";
		print "<li id='userCommunity'><a href='https://forums.cacti.net' target='_blank' rel='noopener'>" . __('User Community') . '</a></li>';
		print "<li id='userDocumentation'><a href='https://github.com/Cacti/documentation/blob/develop/README.md' target='_blank' rel='noopener'>" . __('Documentation') . '</a></li>';
		print "<li class='menuHr'><hr class='menu'></li>";
		print ($auth_method > 0 ? "<li><a href='" . html_escape($config['url_path'] . 'logout.php') . "'>" . __('Logout') . '</a></li>':'');
		print '</ul>';

		api_plugin_hook('nav_login_after');
	}
}

/**
 * draw_navigation_text - determines the top header navigation text for the current page and displays it to
 *
 * @param $type - (string) Either 'url' or 'title'
 *
 * @return (string) Either the navigation text or title
 */
function draw_navigation_text(string $type = 'url'): string {
	global $config, $navigation;

	$nav_level_cache = (isset($_SESSION['sess_nav_level_cache']) ? $_SESSION['sess_nav_level_cache'] : array());
	$navigation      = api_plugin_hook_function('draw_navigation_text', $navigation);
	$current_page    = get_current_page();

	// Do an error check here for bad plugins manipulating the cache
	if (!is_array($nav_level_cache)) {
		$nav_level_cache = array();
	}

	if (!isempty_request_var('action')) {
		get_filter_request_var('action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([-a-zA-Z0-9_\s]+)$/')));
	}

	$current_action = (isset_request_var('action') ? get_request_var('action') : '');

	// find the current page in the big array
	if (isset($navigation[$current_page . ':' . $current_action])) {
		$current_array = $navigation[$current_page . ':' . $current_action];
	} else {
		$current_array = array(
			'mapping' => 'index.php:',
			'title'   => ucwords(str_replace('_', ' ', basename(get_current_page(), '.php'))),
			'level'   => 1
		);
	}

	if (isset($current_array['mapping'])) {
		$current_mappings = explode(',', $current_array['mapping']);
	} else {
		$current_mappings = array();
	}

	$current_nav = "<ul id='breadcrumbs'>";
	$title       = '';
	$nav_count   = 0;

	// resolve all mappings to build the navigation string
	for ($i=0; ($i < cacti_count($current_mappings)); $i++) {
		if (empty($current_mappings[$i])) {
			continue;
		}

		if ($i == 0) {
			// always use the default for level == 0
			$url = $navigation[basename($current_mappings[$i])]['url'];

			if (basename($url) == 'graph_view.php') continue;
		} elseif (isset($nav_level_cache[$i]) && !empty($nav_level_cache[$i]['url'])) {
			// found a match in the url cache for this level
			$url = $nav_level_cache[$i]['url'];
		} elseif (isset($current_array['url'])) {
			// found a default url in the above array
			$url = $current_array['url'];
		} else {
			// default to no url
			$url = '';
		}

		if ($current_mappings[$i] == '?') {
			// '?' tells us to pull title from the cache at this level
			if (isset($nav_level_cache[$i])) {
				$current_nav .= (empty($url) ? '' : "<li><a id='nav_$i' href='" . html_escape($url) . "'>");
				$current_nav .= html_escape(resolve_navigation_variables($navigation[$nav_level_cache[$i]['id']]['title']));
				$current_nav .= (empty($url) ? '' : '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>');
				$title .= html_escape(resolve_navigation_variables($navigation[$nav_level_cache[$i]['id']]['title'])) . ' -> ';
			}
		} else {
			// there is no '?' - pull from the above array
			$current_nav .= (empty($url) ? '' : "<li><a id='nav_$i' href='" . html_escape($url) . "'>");
			$current_nav .= html_escape(resolve_navigation_variables($navigation[basename($current_mappings[$i])]['title']));
			$current_nav .= (empty($url) ? '' : '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>');
			$title .= html_escape(resolve_navigation_variables($navigation[basename($current_mappings[$i])]['title'])) . ' -> ';
		}

		$nav_count++;
	}

	if ($nav_count) {
		if (isset($current_array['title'])) {
			$current_nav .= "<li><a id='nav_$i' href=#>" . html_escape(resolve_navigation_variables($current_array['title'])) . '</a></li>';
		}
	} else {
		$current_array = $navigation[$current_page . ':' . $current_action];
		$url           = (isset($current_array['url']) ? $current_array['url']:'');

		if (isset($current_array['title'])) {
			$current_nav .= "<li><a id='nav_$i' href='$url'>" . html_escape(resolve_navigation_variables($current_array['title'])) . '</a></li>';
		}
	}

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
				input_validate_input_number($parts[1]);

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
			$current_nav .= "<li><a id='nav_title' href=#>" . html_escape($tree_title) . '</a></li>';
		}
	} elseif (preg_match('#link.php\?id=(\d+)#', $_SERVER['REQUEST_URI'], $matches)) {
		$externalLinks = db_fetch_row_prepared('SELECT title, style FROM external_links WHERE id = ?', array($matches[1]));
		$title         = $externalLinks['title'];
		$style         = $externalLinks['style'];

		if ($style == 'CONSOLE') {
			$current_nav = "<ul id='breadcrumbs'><li><a id='nav_0' href='" . $config['url_path'] .
				"index.php'>" . __('Console') . '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>';
			$current_nav .= "<li><a id='nav_1' href='#'>" . __('Link %s', html_escape($title)) . '</a></li>';
		} else {
			$current_nav = "<ul id='breadcrumbs'><li><a id='nav_0'>" . html_escape($title) . '</a></li>';
		}
		$tree_title = '';
	} else {
		$tree_title = '';
	}

	if (isset($current_array['title'])) {
		$title .= html_escape(resolve_navigation_variables($current_array['title']) . ' ' . $tree_title);
	}

	// keep a cache for each level we encounter
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
			'id'  => $current_page . ':' . $current_action,
			'url' => get_browser_query_string()
		);
	}
	$current_nav .= '</ul>';

	$_SESSION['sess_nav_level_cache'] = $nav_level_cache;

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
 * @return - the original navigation text with all substitutions made
 */
function resolve_navigation_variables(string $text): string {
	if (isset_request_var('local_graph_id')) {
		$graphTitle = get_graph_title(get_filter_request_var('local_graph_id'));
	} else {
		$graphTitle = '';
	}

	if (preg_match_all("/\|([a-zA-Z0-9_]+)\|/", $text, $matches)) {
		for ($i=0; $i < cacti_count($matches[1]); $i++) {
			switch ($matches[1][$i]) {
				case 'current_graph_title':
					$text = str_replace('|' . $matches[1][$i] . '|', $graphTitle, $text);

					break;
			}
		}
	}

	return $text;
}

/**
 * get_associated_rras - returns a list of all RRAs referenced by a particular graph
 *
 * @param $local_graph_id - (int) the ID of the graph to retrieve a list of RRAs for
 * @return - (array) an array containing the name and id of each RRA found
 */
function get_associated_rras(int $local_graph_id, string $sql_where = ''): array {
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
 * @return - (int) the timespan to apply for the data source profile rra value
 */
function get_nearest_timespan(int $timespan): int {
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
 * @return - the url requested by the browser
 */
function get_browser_query_string(): string {
	if (!empty($_SERVER['REQUEST_URI'])) {
		return sanitize_uri($_SERVER['REQUEST_URI']);
	} else {
		return sanitize_uri(get_current_page() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
	}
}

/**
 * get_current_page - returns the basename of the current page in a web server friendly way
 *
 * @return - the basename of the current script file
 */
function get_current_page(bool $basename = true): string {
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

	return '';
}

/**
 * get_hash_graph_template - returns the current unique hash for a graph template
 *
 * @param $graph_template_id - (int) the ID of the graph template to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_graph_template(int $graph_template_id, string $sub_type = 'graph_template'): string {
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
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_data_template(int $data_template_id, string $sub_type = 'data_template'): string {
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
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_data_input($data_input_id, $sub_type = 'data_input_method'): string {
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
	}

	return generate_hash();
}

/**
 * get_hash_cdef - returns the current unique hash for a cdef
 *
 * @param $graph_template_id - (int) the ID of the cdef to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_cdef(int $cdef_id, string $sub_type = 'cdef'): string {
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
	}

	return generate_hash();
}

/**
 * get_hash_gprint - returns the current unique hash for a gprint preset
 *
 * @param $graph_template_id - (int) the ID of the gprint preset to return a hash for
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_gprint(int $gprint_id): string {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates_gprint WHERE id = ?', array($gprint_id));

	if (strlen($hash) == 32 && ctype_xdigit($hash)) {
		return $hash;
	}

	return generate_hash();
}

/**
 * returns the current unique hash for a vdef
 *
 * @param $graph_template_id - (int) the ID of the vdef to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_vdef(int $vdef_id, string $sub_type = 'vdef'): string {
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
 * returns the current unique hash for a vdef
 *
 * @param $data_source_profile_id - (int) the ID of the data_source_profile to return a hash for
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_data_source_profile(int $data_source_profile_id): string {
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
 * @param $host_template_id - (int) the ID of the host template to return a hash for
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_host_template(int $host_template_id): string {
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
 * @param $graph_template_id - (int) the ID of the data query to return a hash for
 * @param $sub_type (optional) return the hash for a particular subtype of this type
 *
 * @return - a 128-bit, hexadecimal hash
 */
function get_hash_data_query(int $data_query_id, string $sub_type = 'data_query') {
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
 * 'data_input_method','cdef','vdef','gprint_preset','data_query','host_template')
 *
 * @return - a 24-bit hexadecimal hash (8-bits for type, 16-bits for version)
 */
function get_hash_version(string $type): string {
	global $hash_type_codes, $cacti_version_codes, $config;

	return $hash_type_codes[$type] . $cacti_version_codes[CACTI_VERSION];
}

/**
 * generate_hash - generates a new unique hash
 *
 * @return - a 128-bit, hexadecimal hash
 */
function generate_hash(): string {
	return md5(session_id() . microtime() . rand(0,1000));
}

/**
 * debug_log_insert_section_start - creates a header item for breaking down the debug log
 *
 * @param $type - the 'category' or type of debug message
 * @param $text - section header
 */
function debug_log_insert_section_start(string $type, string $text, bool $allowcopy = false): void {
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
function debug_log_insert_section_end(string $type): void {
	debug_log_insert($type, '</div></td></tr></table></td></tr></td></table>');
}

/**
 * debug_log_insert - inserts a line of text into the debug log
 *
 * @param $type - the 'category' or type of debug message
 * @param $text - the actual debug message
 */
function debug_log_insert(string $type, string $text): void {
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
 * implies all categories
 */
function debug_log_clear(string $type = ''): void {
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
 * @return - the full debug log for a particular category
 */
function debug_log_return(string $type): string {
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
 * @return - the sanitized search string
 */
function sanitize_search_string(string $string): string {
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
function sanitize_uri(string $uri): string {
	static $drop_char_match   = array('^', '$', '<', '>', '`', "'", '"', '|', '+', '[', ']', '{', '}', ';', '!', '(', ')');
	static $drop_char_replace = array('', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '',  '');

	if (strpos($uri, 'graph_view.php')) {
		if (!strpos($uri, 'action=')) {
			$uri = $uri . (strpos($uri, '?') ? '&':'?') . 'action=' . get_request_var('action');
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
function is_base64_encoded(string $data): bool {
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
function sanitize_cdef(string $cdef): string {
	static $drop_char_match   = array('^', '$', '<', '>', '`', '\'', '"', '|', '[', ']', '{', '}', ';', '!');
	static $drop_char_replace = array('', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '');

	return str_replace($drop_char_match, $drop_char_replace, $cdef);
}

/**
 * verifies all selected items are numeric to guard against injection
 *
 * @param array $items   - an array of serialized items from a post
 *
 * @return array      - the sanitized selected items array
 */
function sanitize_unserialize_selected_items(array $items): array {
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

/**
 * cacti_escapeshellcmd
 *
 * Insert description here
 *
 * @param type $string
 *
 * @return type
 */
function cacti_escapeshellcmd(string $string): string {
	global $config;

	if ($config['cacti_server_os'] == 'unix') {
		return escapeshellcmd($string);
	} else {
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
 * @return			- the escaped [quoted|unquoted] string
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
function set_page_refresh(array $refresh): void {
	if (isset($refresh['seconds'])) {
		$_SESSION['refresh']['seconds'] = $refresh['seconds'];
	}

	if (isset($refresh['logout'])) {
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

/**
 * bottom_footer
 *
 * Insert description here
 *
 */
function bottom_footer(): void {
	global $config, $no_session_write;

	include_once($config['base_path'] . '/include/global_session.php');
	include_once($config['base_path'] . '/include/bottom_footer.php');

	/* we use this session var to store field values for when a save fails,
	   this way we can restore the field's previous values. we reset it here, because
	   they only need to be stored for a single page
	*/
	kill_session_var('sess_field_values');

	/* make sure the debug log doesn't get too big */
	debug_log_clear();

	/* close the session */
	if (array_search(get_current_page(), $no_session_write, true) === false) {
		cacti_session_close();
	}

	/* close the database connection */
	db_close();
}

/**
 * top_header
 *
 * Insert description here
 *
 */
function top_header(): void {
	global $config;

	include_once($config['base_path'] . '/include/top_header.php');
}

/**
 * top_graph_header
 *
 * Insert description here
 *
 */
function top_graph_header(): void {
	global $config;

	include_once($config['base_path'] . '/include/top_graph_header.php');
}

/**
 * general_header
 *
 * Insert description here
 *
 */
function general_header(): void {
	global $config;

	include_once($config['base_path'] . '/include/top_general_header.php');
}

/**
 * admin_email
 *
 * Insert description here
 *
 * @param type $subject
 * @param type $message
 */
function admin_email(string $subject, string $message): void {
	if (read_config_option('admin_user')) {
		if (read_config_option('notify_admin')) {
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

					send_mail($to, $from, $subject, $message, '', '', true);
				} else {
					cacti_log('WARNING: Primary Admin account does not have an email address!  Unable to send administrative Email.', false, 'SYSTEM');
				}
			} else {
				cacti_log('WARNING: Primary Admin account set to an invalid user!  Unable to send administrative Email.', false, 'SYSTEM');
			}
		} else {
			cacti_log('WARNING: Primary Admin account notifications disabled!  Unable to send administrative Email.', false, 'SYSTEM');
		}
	} else {
		cacti_log('WARNING: Primary Admin account not set!  Unable to send administrative Email.', false, 'SYSTEM');
	}
}

/**
 * send_mail
 *
 * Insert description here
 *
 * @param type $to
 * @param type $from
 * @param type $subject
 * @param type $body
 * @param array $attachments
 * @param array $headers
 * @param false $html
 *
 * @return type
 */
function send_mail($to, $from, string $subject, string $body, array $attachments = array(), array $headers = array(), bool $html = false): string {
	$fromname = '';

	if (is_array($from)) {
		$fromname = $from[1];
		$from     = $from[0];
	}

	if ($from == '') {
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

	return mailer($from, $to, '', '', '', $subject, $body, '', $attachments, $headers, $html);
}

/**
 * mailer - function to send mails to users
 *
 * For contact parameters, they can accept arrays containing zero or more values in the forms of:
 *     'email@email.com,email2@email.com,email3@email.com'
 *     array('email1@email.com' => 'My email', 'email2@email.com' => 'Your email', 'email3@email.com' => 'Whose email')
 *     array(array('email' => 'email1@email.com', 'name' => 'My email'), array('email' => 'email2@email.com',
 *         'name' => 'Your email'), array('email' => 'email3@email.com', 'name' => 'Whose email'))
 *
 *  The $from field will only use the first contact specified.  If no contact is provided for $replyto
 *  then $from is used for that too. If $from is empty, it will default to cacti@<server> or if no server name can
 *  be found, it will use cacti@cacti.net
 *
 *  The $attachments parameter may either be a single string, or a list of attachments
 *  either as strings or an array.  The array can have the following keys:
 *
 *  filename    : name of the file to attach (display name for graphs)
 *  display     : displayed name of the attachment
 *  mime_type   : MIME type to be set against the attachment.  If blank or missing mailer will attempt to auto detect
 *  attachment  : String containing attachment for image-based attachments (<GRAPH> or <GRAPH:#> activates graph mode
 *                and requires $body parameter is HTML containing one of those values)
 *  inline      : Whether to attach 'inline' (default for graph mode) or as 'attachment' (default for all others)
 *  encoding    : Encoding type, normally base64
 *
 * @param $from        - single contact (see below)
 * @param $to          - single or multiple contacts (see below)
 * @param $cc          - none, single or multiple contacts (see below)
 * @param $bcc         - none, single or multiple contacts (see below)
 * @param $replyto     - none, single or multiple contacts (see below)
 *                      note that this value is used when hitting reply (overriding the default of using from)
 * @param $subject     - the email subject
 * @param $body        - the email body, in HTML format.  If content_text is not set, the function will attempt to extract
 *                      from the HTML format.
 * @param $body_text   - the email body in TEXT format.  If set, it will override the stripping tags method
 * @param $attachments - the emails attachments as an array
 * @param $headers     - an array of name value pairs representing custom headers.
 * @param $html        - if set to true, html is the default, otherwise text format will be used
 * @param mixed $from
 * @param mixed $to
 * @param mixed $cc
 * @param mixed $bcc
 * @param mixed $replyto
 */
function mailer($from, $to, $cc, $bcc, $replyto, string $subject, string $body, string $body_text = '', array $attachments = array(), array $headers = array(), bool $html = true): string {
	global $config, $cacti_locale, $mail_methods;

	require_once($config['include_path'] . '/vendor/phpmailer/src/Exception.php');
	require_once($config['include_path'] . '/vendor/phpmailer/src/PHPMailer.php');
	require_once($config['include_path'] . '/vendor/phpmailer/src/SMTP.php');

	$start_time = microtime(true);

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

	if (file_exists($config['include_path'] . '/vendor/phpmailer/language/phpmailer.lang-' . $langparts[0] . '.php')) {
		$mail->setLanguage($langparts[0], $config['include_path'] . '/vendor/phpmailer/language/');
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

	$body = str_replace('<SUBJECT>', $subject,   $body);
	$body = str_replace('<TO>',      $toText,    $body);
	$body = str_replace('<CC>',      $ccText,    $body);
	$body = str_replace('<FROM>',    $fromText,  $body);
	$body = str_replace('<REPLYTO>', $replyText, $body);

	$body_text = str_replace('<SUBJECT>', $subject,   $body_text);
	$body_text = str_replace('<TO>',      $toText,    $body_text);
	$body_text = str_replace('<CC>',      $ccText,    $body_text);
	$body_text = str_replace('<FROM>',    $fromText,  $body_text);
	$body_text = str_replace('<REPLYTO>', $replyText, $body_text);

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

/**
 * record_mailer_error
 *
 * Insert description here
 *
 * @param type $retError
 * @param type $mailError
 *
 * @return type
 */
function record_mailer_error(string $retError, string $mailError) : string {
	$errorInfo = empty($retError) ? $mailError : $retError;
	cacti_log('ERROR: ' . $errorInfo, false, 'CMDPHP MAILER');
	cacti_debug_backtrace('MAILER ERROR');

	return $errorInfo;
}

/**
 * add_email_details
 *
 * Insert description here
 *
 * @param type $emails
 * @param type $result
 * @param type $addFunc
 *
 * @return type
 */
function add_email_details(array $emails, array &$result, callable $addFunc): string {
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

/**
 * parse_email_details
 *
 * Insert description here
 *
 * @param type $emails
 * @param 0 $max_records
 * @param array $details
 *
 * @return type
 */
function parse_email_details(array $emails, int $max_records = 0, array $details = array()): array {
	if (!is_array($emails)) {
		$emails = array($emails);
	}

	$update = array();

	foreach ($emails as $check_email) {
		if (!empty($check_email)) {
			if (!is_array($check_email)) {
				$emails = explode(',', $check_email);

				foreach ($emails as $email) {
					$email_array = split_emaildetail($email);
					$details[]   = $email_array;
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

				$details[] = array('name' => trim($name), 'email' => trim($email));
			}
		}
	}

	if ($max_records == 1) {
		$results = count($details) ? $details[0] : array();
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

/**
 * split_emaildetail
 *
 * Insert description here
 *
 * @param type $email
 *
 * @return type
 */
function split_emaildetail(string $email): array {
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

	return array('name' => $rname, 'email' => $rmail);
}

/**
 * create_emailtext
 *
 * Insert description here
 *
 * @param type $e
 *
 * @return type
 */
function create_emailtext(array $e) : string {
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

/**
 * ping_mail_server
 *
 * Insert description here
 *
 * @param type $host
 * @param type $port
 * @param type $user
 * @param type $password
 * @param 10 $timeout
 * @param 'none' $secure
 *
 * @return type
 */
function ping_mail_server(string $host, int $port, int $user, string $password, int $timeout = 10, string $secure = 'none') {
	global $config;

	require_once($config['include_path'] . '/vendor/phpmailer/src/Exception.php');
	require_once($config['include_path'] . '/vendor/phpmailer/src/PHPMailer.php');
	require_once($config['include_path'] . '/vendor/phpmailer/src/SMTP.php');

	//Create a new SMTP instance
	$smtp = new PHPMailer\PHPMailer\SMTP;

	if (!empty($secure) && $secure != 'none') {
		$smtp->SMTPSecure = $secure;

		if (substr_count($host, ':') == 0) {
			$host = $secure . '://' . $host;
		}
	} else {
		$smtp->SMTPAutoTLS = false;
		$smtp->SMTPSecure  = false;
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

/**
 * email_test
 *
 * Insert description here
 *
 */
function email_test(): void {
	global $config;

	$message =  __('This is a test message generated from Cacti.  This message was sent to test the configuration of your Mail Settings.') . '<br><br>';
	$message .= __('Your email settings are currently set as follows') . '<br><br>';
	$message .= '<b>' . __('Method') . '</b>: ';

	print __('Checking Configuration...<br>');

	$ping_results = true;
	$how          = read_config_option('settings_how');

	if ($how < 0 || $how > 2)
		$how = 0;

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

		$errors = send_mail($global_alert_address, '', __('Cacti Test Message'), $message, '', '', true);

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
 */
function get_dns_from_ip(string $ip, string $dns, int $timeout = 1000): string {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode('.', $ip);

	/* perform a quick error check */
	if (cacti_count($octets) != 4) return 'ERROR';
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
	if ($response == '') { return $ip; }

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

/**
 * poller_maintenance
 *
 * Insert description here
 *
 */
function poller_maintenance(): void {
	global $config;

	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . cacti_escapeshellarg($config['base_path'] . '/poller_maintenance.php');

	exec_background($command_string, $extra_args);
}

/**
 * clog_admin
 *
 * Insert description here
 *
 *
 * @return type
 */
function clog_admin(): bool {
	if (!isset($_SESSION['sess_clog_level'])) {
		clog_authorized();
	}

	if ($_SESSION['sess_clog_level'] == CLOG_PERM_ADMIN) {
		return true;
	} else {
		return false;
	}
}

/**
 * clog_authorized
 *
 * Insert description here
 *
 *
 * @return type
 */
function clog_authorized(): bool {
	if (!isset($_SESSION['sess_clog_level'])) {
		if (isset($_SESSION['sess_user_id'])) {
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

/**
 * update_system_mibs
 *
 * Insert description here
 *
 * @param type $host_id
 */
function update_system_mibs(int $host_id): void {
	global $sessions;

	$system_mibs = array(
		'snmp_sysDescr'          => '.1.3.6.1.2.1.1.1.0',
		'snmp_sysObjectID'       => '.1.3.6.1.2.1.1.2.0',
		'snmp_sysUpTimeInstance' => '.1.3.6.1.2.1.1.3.0',
		'snmp_sysContact'        => '.1.3.6.1.2.1.1.4.0',
		'snmp_sysName'           => '.1.3.6.1.2.1.1.5.0',
		'snmp_sysLocation'       => '.1.3.6.1.2.1.1.6.0'
	);

	$h = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	if (cacti_sizeof($h)) {
		open_snmp_session($host_id, $h);

		if (isset($sessions[$host_id . '_' . $h['snmp_version'] . '_' . $h['snmp_port']])) {
			foreach ($system_mibs as $name => $oid) {
				$value = cacti_snmp_session_get($sessions[$host_id . '_' . $h['snmp_version'] . '_' . $h['snmp_port']], $oid);

				if (!empty($value)) {
					db_execute_prepared("UPDATE host SET $name = ? WHERE deleted = '' AND id = ?",
						array($value, $host_id));
				}
			}
		} else {
			cacti_log("WARNING: Unable to open session for System Mib collection for Device[$host_id]", false, 'POLLER');
		}
	}
}

/**
 * cacti_debug_backtrace
 *
 * Insert description here
 *
 * @param string $entry
 * @param false $html
 * @param true $record
 * @param 0 $limit
 * @param 0 $skip
 *
 * @return type
 */
function cacti_debug_backtrace(string $entry = '', bool $html = false, bool $record = true, int $limit = 0, int $skip = 0): string {
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
			$file = str_replace($config['base_path'], '', $c['file']) . $line;
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
		if ($html && !defined('CACTI_CLI_ONLY')) {
			print "<table style='width:100%;text-align:center;'><tr><td>xxx$s</td></tr></table>\n";
		}

		cacti_log(trim("$entry Backtrace: " . clean_up_lines($s)), false);
	} else {
		if (!empty($entry)) {
			return trim("$entry Backtrace: " . clean_up_lines($s));
		} else {
			return trim(clean_up_lines($s));
		}
	}

	return '';
}

/**
 * calculate_percentiles - Given and array of numbers, calculate the Nth percentile,
 * optionally, return an array of numbers containing elements required for
 * a whisker chart.
 *
 * @param $data         - an array of data
 * @param $percentile   - the Nth percentile to calculate.  By default 95th.
 * @param $arg $whisker - if whisker is true, an array of values will be returned
 *                        including 25th, median, 75th, and 90th percentiles.
 *
 * @return - either the Nth percentile, the elements for a whisker chart,
 *            or false if there is insufficient data to determine.
 */
function calculate_percentiles(array $data, int $percentile = 95, bool $whisker = false): ?array {
	if ($percentile > 0 && $percentile < 1) {
		$p = $percentile;
	} elseif ($percentile > 1 && $percentile <= 100) {
		$p = $percentile * .01;
	} else {
		return null;
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
		$intvalindex = intval($allindex);
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

/**
 * get_timeinstate
 *
 * Insert description here
 *
 * @param type $host
 *
 * @return type
 */
function get_timeinstate(array $host): string {
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

	return ($time > 0) ? get_daysfromtime($time) : __('N/A');
}

/**
 * get_uptime
 *
 * Insert description here
 *
 * @param type $host
 *
 * @return type
 */
function get_uptime(array $host): string {
	return ($host['snmp_sysUpTimeInstance'] > 0) ? get_daysfromtime($host['snmp_sysUpTimeInstance'] / 100) : __('N/A');
}

/**
 * get_daysfromtime
 *
 * Insert description here
 *
 * @param type $time
 * @param false $secs
 * @param string $pad
 * @param DAYS_FORMAT_SHORT $format
 * @param false $all
 *
 * @return type
 */
function get_daysfromtime(int $time, bool $secs = false, string $pad = '', int $format = DAYS_FORMAT_SHORT, bool $all = false): int {
	global $days_from_time_settings;

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
				if ($mod < 1) {
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

	return (int) trim($result, $text['suffix']);
}

/**
 * padleft
 *
 * Insert description here
 *
 * @param string $pad
 * @param string $value
 * @param 2 $min
 *
 * @return type
 */
function padleft(string $pad = '', $value = '', int $min = 2): string {
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

/**
 * get_classic_tabimage
 *
 * Insert description here
 *
 * @param type $text
 * @param false $down
 *
 * @return type
 */
function get_classic_tabimage(string $text, bool $down = false): string {
	global $config;

	$images = array(
		false => 'tab_template_blue.gif',
		true  => 'tab_template_red.gif'
	);

	if ($text == '') {
		return '';
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

	if (file_exists($config['base_path'] . '/images/' . $images[$down])) {
		$originalpath = getenv('GDFONTPATH');
		putenv('GDFONTPATH=' . $config['base_path'] . '/include/fonts/');

		$template = imagecreatefromgif($config['base_path'] . '/images/' . $images[$down]);

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
//					$lines[] = array(substr($text,0,$maxtext).'.'.$w.'.'.$maxtext.'.'.$fontw, $fontid, 0, $realx, $realy);
				} else {
					break;
				}
			}
		}

		foreach ($lines as $line) {
			if ($ttf_functions) {
				imagettftext($tab, $line[2], 0, $line[3], $line[4], $white, $line[1], $line[0]);
			} else {
				imagestring($tab, $line[1], $line[3], $line[4], $line[0], $white);
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
	}

	return '';
}

/**
 * cacti_oid_numeric_format
 *
 * Insert description here
 *
 */
function cacti_oid_numeric_format(): void {
	if (function_exists('snmp_set_oid_output_format')) {
		snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
	} elseif (function_exists('snmp_set_oid_numeric_print')) {
		snmp_set_oid_numeric_print(true);
	}
}

/**
 * IgnoreErrorHandler
 *
 * Insert description here
 *
 * @param type $message
 *
 * @return type
 */
function IgnoreErrorHandler(string $message): bool {
	global $snmp_error;

	$snmp_ignore = array(
		'No response from',
		'noSuchName',
		'No Such Object',
		'Error in packet',
		'This name does not exist',
		'End of MIB',
		'Timeout',
		'Unknown host',
		'Invalid object identifier',
		'Name or service not known'
	);

	foreach ($snmp_ignore as $i) {
		if (strpos($message, $i)) {
			$snmp_error = trim($message, "\\\n\t ");

			return true;
		}
	}

	$ignore = array(
		'unable to read from socket'  # ping.php line 387 socket refusal
	);

	foreach ($ignore as $i) {
		if (strpos($message, $i)) {
			return true;
		}
	}

	return false;
}

/**
 * CactiErrorHandler
 *
 * Insert description here
 *
 * @param type $level
 * @param type $message
 * @param type $file
 * @param type $line
 * @param array $context
 *
 * @return type
 */
function CactiErrorHandler(int $level, string $message, string $file, int $line, array $context = array()): bool {
	global $phperrors;

	if (defined('IN_CACTI_INSTALL')) {
		return true;
	}

	if (IgnoreErrorHandler($message)) {
		return true;
	}

	if (error_reporting() == 0) {
		return true;
	}

	preg_match("/.*\/plugins\/([\w-]*)\/.*/", $file, $output_array);

	$plugin = (isset($output_array[1]) ? $output_array[1] : '');
	$error  = 'PHP ' . $phperrors[$level] . ($plugin != '' ? " in  Plugin '$plugin'" : '') . ": $message in file: $file  on line: $line";

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

/**
 * CactiShutdownHandler
 *
 * Insert description here
 *
 *
 * @return type
 */
function CactiShutdownHandler(): bool {
	global $phperrors;
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

					$message = 'PHP ' . $phperrors[$error['type']] .
						($plugin != '' ? " in  Plugin '$plugin'" : '') . ': ' . $error['message'] .
						' in file: ' .  $error['file'] . ' on line: ' . $error['line'];

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

	return false;
}

/**
 * enable_device_debug - Enables device debug for a device
 * if it is disabled.
 *
 * @param $host_id - the device id to search for
 *
 * @return - void
 */
function enable_device_debug(int $host_id): void {
	$device_debug = read_config_option('selective_device_debug', true);

	if ($device_debug != '') {
		$devices = explode(',', $device_debug);

		if (array_search($host_id, $devices, false) === false) {
			set_config_option('selective_device_debug', $device_debug . ',' . $host_id);
		}
	} else {
		set_config_option('selective_device_debug', $host_id);
	}
}

/**
 * disable_device_debug - Disables device debug for a device
 * if it is enabled.
 *
 * @param $host_id - the device id to search for
 *
 * @return - void
 */
function disable_device_debug(int $host_id): void {
	$device_debug = read_config_option('selective_device_debug', true);

	if ($device_debug != '') {
		$devices = explode(',', $device_debug);

		foreach ($devices as $key => $device) {
			if ($device == $host_id) {
				unset($devices[$key]);

				break;
			}
		}
		set_config_option('selective_device_debug', implode(',', $devices));
	}
}

/**
 * is_device_debug_enabled - Determines if device debug is enabled
 * for a device.
 *
 * @param $host_id - the device id to search for
 *
 * @return - boolean true or false
 */
function is_device_debug_enabled(int $host_id): bool {
	$device_debug = read_config_option('selective_device_debug', true);

	if ($device_debug != '') {
		$devices = explode(',', $device_debug);

		if (array_search($host_id, $devices, false) !== false) {
			return true;
		}
	}

	return false;
}

/**
 * get_url_type - Determines if remote communications are over
 * http or https for remote services.
 *
 * @return - http or https
 */
function get_url_type(): string {
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
 * @return - an array of stream context options or false
 */
function get_default_contextoption(int $timeout = 0): array {
	$fgc_contextoption = false;

	if ($timeout == 0) {
		$timeout = read_config_option('remote_agent_timeout');
	}

	if (!is_numeric($timeout)) {
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
 * @return - null
 */
function repair_system_data_input_methods(string $step = 'import'): void {
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
	/**
	 * posix_kill
	 *
	 * Insert description here
	 *
	 * @param type $pid
	 * @param SIGTERM $signal
	 *
	 * @return type
	 */
	function posix_kill(int $pid, int $signal = SIGTERM) {
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

/**
 * is_ipaddress
 *
 * Insert description here
 *
 * @param string $ip_address
 *
 * @return type
 */
function is_ipaddress(string $ip_address = ''): bool {
	/* check for ipv4/v6 */
	if (function_exists('filter_var')) {
		if (filter_var($ip_address, FILTER_VALIDATE_IP) !== false) {
			return true;
		} else {
			return false;
		}
	} elseif (inet_pton($ip_address) !== false) {
		return true;
	}

	return false;
}

/**
 * date_time_format create a format string for date/time
 *
 * @return string returns	date time format
 */
function date_time_format(): string {
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

/**
 * get_source_timestamp
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_source_timestamp(): array {
	global $config;
	$timestamp = 0;
	$git_path  = realpath(__DIR__ . '/../.git/');

	if (file_exists($git_path)) {
		$old_path = getcwd();
		chdir($git_path);
		$shell = @shell_exec('git log -1 --pretty=format:%ct.%h');
		$parts = explode('.', $shell);
		chdir($old_path);
	} else {
		$parts =  array(0 => -1, 1 => 'UNKNOWN');
	}

	return $parts;
}

/**
 * format_cacti_version
 *
 * Insert description here
 *
 * @param type $version
 * @param CACTI_VERSION_FORMAT_FULL $format
 *
 * @return type
 */
function format_cacti_version(string $version, int $format = CACTI_VERSION_FORMAT_FULL) {
	if ($version == 'new_install') {
		$version = ($format == CACTI_VERSION_FORMAT_FULL) ? CACTI_VERSION_FULL : CACTI_VERSION;
	}

	$parts = explode('.', $version);

	if (count($parts) > 3) {
		if ($parts[3] == '-1') {
			$source   = get_source_timestamp();
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

/**
 * format_cacti_version_text
 *
 * Insert description here
 *
 * @param type $version
 *
 * @return type
 */
function format_cacti_version_text($version) {
	$version = format_cacti_version($version);

	$parts = explode('.', $version);

	while (count($parts) < 6) {
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
 * get_cacti_version
 *
 * Generic function to get the cacti version (depreciated)
 */
function get_cacti_version(bool $force = false): string {
	cacti_log('WARNING: get_cacti_version() called, consider replacing with CACTI_VERSION', false, 'DEPRECIATED');
	cacti_debug_backtrace('CACTI WARNING', false, true, 0, 1);

	return CACTI_VERSION;
}

/**
 * get_cacti_db_version
 *
 * Generic function to get the cacti version from the db
 */
function get_cacti_db_version(bool $force = false): string {
	static $version = '';

	if ($version == '' || $force) {
		$version = trim(format_cacti_version(get_cacti_db_version_raw($force)));
	}

	return $version;
}

/**
 * get_cacti_db_version_raw
 *
 * Generic function to get the cacti version from the db
 */
function get_cacti_db_version_raw(bool $force = false): string {
	static $version = '';

	if ($version == '' || $force) {
		$version = trim(db_fetch_cell('SELECT cacti FROM version LIMIT 1'));
	}

	return $version;
}

/**
 * get_cacti_version_text
 *
 * Return the cacti version text including beta moniker
 */
function get_cacti_version_text(bool $include_version = true, string $version = CACTI_VERSION_FULL) {
	$version_text = format_cacti_version_text($version);

	if ($include_version) {
		return trim(__('Version %s %s', $version_text, ''));
	} else {
		return $version_text;
	}
}

/**
 * get_cacti_cli_version() {
 */
function get_cacti_cli_version(bool $include_db = true, string $version = CACTI_VERSION_FULL) {
	$version = get_cacti_version_text(false, $version);

	$dbversion = '';

	if ($include_db) {
		$dbversion = ' (DB: v' . get_cacti_db_version() . ')';
	}

	return $version . $dbversion;
}

/**
 * cacti_version_compare - Compare Cacti version numbers
 */
function cacti_version_compare(string $version1, string $version2, string $operator = '>'): bool {
	if ($version1 == 'new_install') {
		$version1 = CACTI_VERSION;
	}

	$length   = max(cacti_sizeof(explode('.', $version1)), cacti_sizeof(explode('.', $version2)));
	$version1 = version_to_decimal($version1, $length);
	$version2 = version_to_decimal($version2, $length);

	switch ($operator) {
		case '<':
			if ($version1 < $version2) {
				return true;
			}

			break;
		case '<=':
			if ($version1 <= $version2) {
				return true;
			}

			break;
		case '>=':
			if ($version1 >= $version2) {
				return true;
			}

			break;
		case '>':
			if ($version1 > $version2) {
				return true;
			}

			break;
		case '==':
			if ($version1 == $version2) {
				return true;
			}

			break;
		case '<>':
		case '!=':
			if ($version1 != $version2) {
				return true;
			}

			break;

		default:
			return version_compare($version1, $version2, $operator);
	}

	return false;
}

/**
 * is_install_needed
 *
 * Insert description here
 *
 * @param null $version
 *
 * @return type
 */
function is_install_needed(?string $version = null): bool {
	$mode = '==';
	$db   = get_cacti_db_version();

	if ($version === null) {
		$version = CACTI_VERSION_FULL;
	}

	if (is_cacti_develop($version)) {
		$version = CACTI_DEV_VERSION;
		$mode    = '<';
	}

	$result = (cacti_version_compare($db, $version, $mode));

	if (function_exists('log_install_medium')) {
		log_install_medium('step', "$result = (cacti_version_compare($db, $version, $mode)");
	}

	return $result;
}

/**
 * is_cacti_develop
 *
 * Insert description here
 *
 * @param null $version
 *
 * @return type
 */
function is_cacti_develop(?string $version = null): bool {
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

/**
 * is_cacti_release
 *
 * Insert description here
 *
 * @param null $version
 *
 * @return type
 */
function is_cacti_release(?string $version = null): bool {
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
 * version_to_decimal - convert version string to decimal
 */
function version_to_decimal(string $version, int $length = 8, bool $hex = true): int {
	$newver = '';
	$minor  = '';

	$parts   = explode('.', $version);
	$section = 0;

	while ($section < $length) {
		$prefix  = '00';

		if ($section >= cacti_sizeof($parts)) {
			if ($section >= 3 && $section < 5) {
				$prefix = '99';
			}
			$parts[$section] = $prefix;
		}

		$part   = $parts[$section];
		$extras = false;

		if ($part == 'x') {
			$part = '0';
		} elseif (is_numeric($part)) {
			if ($part > 100000) {
				$extras = array();

				while ($part > '') {
					$sub = substr('00' . $part, -2);
					array_unshift($extras, $sub);
					$part = (strlen($part) >= 2) ? substr($part,0, strlen($part) - 2) : '';
				}
			}
		} elseif ($part != 'x') {
			//printf ("sub: %s, ", $part);
			$minor  = ord(strtoupper(substr($part, -1))) - ord('0') + 1;
			$major  = substr($part, 0, strlen($part) - 1);
			$extras = array($major,99,99,$minor);
		}

		if (is_array($extras)) {
			$sub = 0;

			foreach ($extras as $extra) {
				//printf ("extra: %s, ", $extra);
				$parts[$section + $sub] = $extra;
				$sub++;
			}

			$part = $parts[$section];
		}

		$part    = substr($prefix . $part, -2);
		$newver .= $part;

		$section++;
	}

	if ($minor != '') {
		$int = ord($minor);
	} else {
		$int = 0;
	}

	return @hexdec($newver) * 1000 + $int;
}

/**
 * cacti_gethostinfo - obtains the dns information for a host
 */
function cacti_gethostinfo(string $hostname, int $type = DNS_ALL): string {
	return dns_get_record($hostname, $type);
}

/**
 * cacti_gethostbyname - a ip/ipv6 replacement for php's gethostbyname function
 */
function cacti_gethostbyname(string $hostname, int $type = 0): string {
	if ($type == 0) {
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

/**
 * get_nonsystem_data_input
 *
 * Insert description here
 *
 * @param type $data_input_id
 *
 * @return type
 */
function get_nonsystem_data_input(int $data_input_id): int {
	global $hash_system_data_inputs;

	$diid = db_fetch_cell_prepared('SELECT id FROM data_input
		WHERE hash NOT IN ("' . implode('","', $hash_system_data_inputs) . '")
		AND id = ?',
		array($data_input_id));

	return intval($diid);
}

/**
 * get_rrdtool_version
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_rrdtool_version(): string {
	return str_replace('rrd-', '', str_replace('.x', '.0', read_config_option('rrdtool_version', true)));
}

/**
 * get_installed_rrdtool_version
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_installed_rrdtool_version(): string {
	global $config, $rrdtool_versions;

	$rrdtool = read_config_option('path_rrdtool');
	$version = false;

	if ($rrdtool != '') {
		if ($config['cacti_server_os'] == 'win32') {
			$shell = shell_exec(cacti_escapeshellcmd($rrdtool) . ' -v');
		} else {
			$shell = shell_exec(cacti_escapeshellcmd($rrdtool) . ' -v 2>&1');
		}

		if (preg_match('/^RRDtool ([0-9.]+) /', $shell, $matches)) {
			foreach ($rrdtool_versions as $rrdtool_version => $rrdtool_version_text) {
				if (cacti_version_compare($rrdtool_version, $matches[1], '<=')) {
					$version = $rrdtool_version;
				}
			}
		}
	}

	return $version;
}

/**
 * get_md5_hash
 *
 * Insert description here
 *
 * @param type $path
 *
 * @return type
 */
function get_md5_hash($path): string {
	$md5 = 0;

	if (db_table_exists('poller_resource_cache')) {
		$md5 = db_fetch_cell_prepared('SELECT md5sum
			FROM poller_resource_cache
			WHERE `path` = ?',
			array($path));
	}

	if (empty($md5)) {
		if (file_exists($path)) {
			$md5 = md5_file($path);
		} else {
			$md5 = md5_file(__DIR__ . '/../' . $path);
		}
	}

	return $md5;
}

/**
 * get_md5_include_js
 *
 * Insert description here
 *
 * @param type $path
 * @param false $async
 *
 * @return type
 */
function get_md5_include_js(string $path, bool $async = false): string {
	global $config;

	if (file_exists($path)) {
		$npath = str_replace($config['base_path'] . '/', '', $path);
	} else {
		$npath = $path;
	}

	if ($async) {
		return '<script type=\'text/javascript\' src=\'' . $config['url_path'] . $npath . '?' . get_md5_hash($path) . '\' async></script>' . PHP_EOL;
	} else {
		return '<script type=\'text/javascript\' src=\'' . $config['url_path'] . $npath . '?' . get_md5_hash($path) . '\'></script>' . PHP_EOL;
	}
}

/**
 * get_md5_include_css
 *
 * Insert description here
 *
 * @param type $path
 *
 * @return type
 */
function get_md5_include_css(string $path): string {
	global $config;

	return '<link href=\''. $config['url_path'] . $path . '?' . get_md5_hash($path) . '\' type=\'text/css\' rel=\'stylesheet\'>' . PHP_EOL;
}

/**
 * is_resource_writable
 *
 * Insert description here
 *
 * @param type $path
 *
 * @return type
 */
function is_resource_writable(string $path): bool {
	if (empty($path)) {
		return false;
	}

	if ($path[strlen($path) - 1] == '/') {
		return is_resource_writable($path . uniqid(mt_rand()) . '.tmp');
	}

	if (file_exists($path)) {
		if (($f = @fopen($path, 'a'))) {
			fclose($f);

			return true;
		}

		return false;
	}

	if (($f = @fopen($path, 'w'))) {
		fclose($f);
		unlink($path);

		return true;
	}

	return false;
}

/**
 * get_validated_theme
 *
 * Insert description here
 *
 * @param type $theme
 * @param type $defaultTheme
 *
 * @return type
 */
function get_validated_theme(string $theme, string $defaultTheme): string {
	global $config;

	if (isset($theme) && strlen($theme)) {
		$themePath = $config['base_path'] . '/include/themes/' . $theme . '/main.css';

		if (file_exists($themePath)) {
			return $theme;
		}
	}

	return $defaultTheme;
}

/**
 * get_validated_language
 *
 * Insert description here
 *
 * @param type $language
 * @param type $defaultLanguage
 *
 * @return type
 */
function get_validated_language(string $language, string $defaultLanguage): string {
	if (isset($language) && strlen($language)) {
		return $language;
	}

	return $defaultLanguage;
}

/**
 * get_running_user
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_running_user(): string {
	global $config;

	static $tmp_user = '';

	if (empty($tmp_user)) {
		if (function_exists('posix_geteuid')) {
			$tmp_user = posix_getpwuid(posix_geteuid())['name'];
		}
	}

	if (empty($tmp_user)) {
		$tmp_file = tempnam(sys_get_temp_dir(), 'uid'); $f_owner = '';

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
			$f_source = 'posix';
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

		/*** Code left here for future development, don't think it is right ***
		 *
		if (empty($tmp_user) && !empty($f_owner) && is_readable('/etc/passwd'))
		{
			exec(sprintf('grep :%s: /etc/passwd | cut -d: -f1', (int) $uid), $o, $r);
			if ($r == 0) {
				$tmp_user = 'passwd-' . trim($o['0']);
			}
		}
		 */

		// Easy way first
		if (empty($tmp_user)) {
			$user = get_current_user();

			if ($user != '') {
				$tmp_user = $user;
			}
		}

		// Falback method
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
 * get_debug_prefix
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_debug_prefix(): string {
	$dateTime = new DateTime('NOW');
	$dateTime = $dateTime->format('Y-m-d H:i:s.u');

	return sprintf('<[ %s | %7d ]> -- ', $dateTime, getmypid());
}

/**
 * get_client_addr
 *
 * Insert description here
 *
 *
 * @return type
 */
function get_client_addr(): string {
	$http_addr_headers = array(
		'X-Forwarded-For',
		'X-Client-IP',
		'X-Real-IP',
		'X-ProxyUser-Ip',
		'CF-Connecting-IP',
		'True-Client-IP',
		'HTTP_X_FORWARDED',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
	);

	$client_addr = '';

	foreach ($http_addr_headers as $header) {
		if (!empty($_SERVER[$header])) {
			$header_ips = explode(',', $_SERVER[$header]);

			foreach ($header_ips as $header_ip) {
				if (!empty($header_ip)) {
					if (!filter_var($header_ip, FILTER_VALIDATE_IP)) {
						cacti_log('ERROR: Invalid remote client IP Address found in header (' . $header . ').', false, 'AUTH', POLLER_VERBOSITY_DEBUG);
					} else {
						$client_addr = $header_ip;
						cacti_log('DEBUG: Using remote client IP Address found in header (' . $header . '): ' . $client_addr . ' (' . $_SERVER[$header] . ')', false, 'AUTH', POLLER_VERBOSITY_DEBUG);

						break;
					}
				}
			}
		}
	}

	return $client_addr;
}

/**
 * cacti_pton
 *
 * Insert description here
 *
 * @param type $ipaddr
 *
 * @return type
 */
function cacti_pton(string $ipaddr): ?array {
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
		return null;
	}

	// Maximum netmask length = same as packed address
	$len = 8 * strlen($addr);

	if (!empty($subnet)) {
		if (!is_numeric($subnet)) {
			return null;
		}

		if ($subnet > $len) {
			return null;
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

/**
 * cacti_ntop
 *
 * Insert description here
 *
 * @param type $addr
 *
 * @return type
 */
function cacti_ntop($addr): int {
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

/**
 * cacti_ntoc
 *
 * Insert description here
 *
 * @param type $subnet
 * @param false $ipv6
 *
 * @return type
 */
function cacti_ntoc($subnet, bool $ipv6 = false): int {
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

/**
 * cacti_ptoa
 *
 * Insert description here
 *
 * @param type $title
 * @param type $addr
 */
function cacti_ptoa(string $title, $addr): void {
	// Let's display it as hexadecimal format
	foreach (str_split($addr) as $char) {
		print str_pad(dechex(ord($char)),2,'0',STR_PAD_LEFT);
	}
}

/**
 * cacti_sizeof
 *
 * Insert description here
 *
 * @param type $array
 *
 * @return type
 */
function cacti_sizeof($array): int {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

/**
 * cacti_count
 *
 * Insert description here
 *
 * @param type $array
 *
 * @return type
 */
function cacti_count($array): int {
	return ($array === false || !is_array($array)) ? 0 : count($array);
}

/**
 * is_function_enabled
 *
 * Insert description here
 *
 * @param type $name
 *
 * @return type
 */
function is_function_enabled(string $name): bool {
	return function_exists($name) &&
		!in_array($name, array_map('trim', explode(', ', ini_get('disable_functions'))), true) &&
		strtolower(ini_get('safe_mode')) != 1;
}

/**
 * is_page_ajax
 *
 * Insert description here
 *
 *
 * @return type
 */
function is_page_ajax(): bool {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		return true;
	}

	return false;
}

/**
 * raise_ajax_permission_denied
 *
 * Insert description here
 *
 */
function raise_ajax_permission_denied(): bool {
	if (is_page_ajax()) {
		header('HTTP/1.1 401 ' . __('Permission Denied'));
		print __('You are not permitted to access this section of Cacti.') . '  ' . __('If you feel that this is an error. Please contact your Cacti Administrator.');

		exit;
	}
}

/**
 * cacti_session_start - Create a Cacti session from the settings set by the administrator
 *
 * @return - null
 */
function cacti_session_start(): void {
	global $config;

	/* initialize php session */
	if (!function_exists('session_name')) {
		die('PHP Session Management is missing, please install PHP Session module');
	}

	session_name($config['cacti_session_name']);

	$session_restart = '';

	if (session_status() === PHP_SESSION_NONE) {
		$session_result = session_start($config['cookie_options']);
	} else {
		$session_restart = 're';
		$session_result  = session_start();
	}

	if (!$session_result) {
		cacti_log('Session "' . session_id() . '" ' . $session_restart . 'start failed! ' . cacti_debug_backtrace('', false, false, 0, 1), false, 'WARNING:');
	}
}

/**
 * cacti_session_close - Closes the open Cacti session if it is open
 * it can be re-opened afterwards in the case after a long running query
 *
 * @return - null
 */
function cacti_session_close(): void {
	session_write_close();
}

/**
 * cacti_session_destroy - Destroys the login current session
 *
 * @return - null
 */
function cacti_session_destroy(): void {
	session_unset();
	session_destroy();
}

/**
 * cacti_cookie_set - Allows for settings an arbitry cookie name and value
 * used for CSRF protection.
 *
 * @return - null
 */
function cacti_cookie_set(string $session, string $val): void {
	global $config;

	if (isset($config['cookie_options']['cookie_domain'])) {
		$domain = $config['cookie_options']['cookie_domain'];
	} else {
		$domain = '';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		setcookie($session, $val, time() + 3600, $config['url_path'], $domain, true, true);
	} else {
		setcookie($session, $val, time() + 3600, $config['url_path'], $domain, false, true);
	}
}

/**
 * cacti_cookie_logout - Clears the Cacti and the 'keep me logged in' cookies
 *
 * @return - null
 */
function cacti_cookie_logout(): void {
	global $config;

	if (isset($config['cookie_options']['cookie_domain'])) {
		$domain = $config['cookie_options']['cookie_domain'];
	} else {
		$domain = '';
	}

	setcookie(session_name(), '', time() - 3600, $config['url_path'], $domain);
	setcookie('cacti_remembers', '', time() - 3600, $config['url_path'], $domain);

	unset($_COOKIE[$config['cacti_session_name']]);
}

/**
 * cacti_cookie_session_set - Sets the cacti 'keep me logged in' cookie
 *
 * @return - null
 */
function cacti_cookie_session_set(string $user, string $nssecret): void {
	global $config;

	if (isset($config['cookie_options']['cookie_domain'])) {
		$domain = $config['cookie_options']['cookie_domain'];
	} else {
		$domain = '';
	}

	$_SESSION['cacti_remembers'] = true;

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		setcookie('cacti_remembers', $user . ',' . $nssecret, time() + (86400 * 30), $config['url_path'], $domain, true, true);
	} else {
		setcookie('cacti_remembers', $user . ',' . $nssecret, time() + (86400 * 30), $config['url_path'], $domain, false, true);
	}
}

/**
 * cacti_cookie_session_logout - Logs out of Cacti and the remember me session
 *
 * @return - null
 */
function cacti_cookie_session_logout(): void {
	global $config;

	if (isset($config['cookie_options']['cookie_domain'])) {
		$domain = $config['cookie_options']['cookie_domain'];
	} else {
		$domain = '';
	}

	setcookie('cacti_remembers', '', time() - 3600, $config['url_path'], $domain);
}