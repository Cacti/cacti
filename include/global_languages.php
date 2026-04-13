<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

// default localization of Cacti
$cacti_locale  = 'en-US';
$cacti_country = 'us';

// an array that will contains all textdomains being in use.
$cacti_textdomains = [];

global $path2calendar, $path2timepicker, $path2colorpicker, $path2ms, $path2msfilter, $lang2locale;

// get a list of locale settings
$lang2locale = get_list_of_locales();

// use a fallback if i18n is disabled (default)
if (!read_config_option('i18n_language_support') && read_config_option('i18n_language_support') != '') {
	i18n_debug('load_fallback_procedure(1)');
	load_fallback_procedure();

	return;
}

// Repair legacy language support
if (!empty($config['i18n_force_language'])) {
	set_request_var('language', $config['i18n_force_language']);
}

if (!isempty_request_var('language')) {
	set_request_var('language', repair_locale(grv('language')));
}

// determine whether or not we can support the language
$user_locale = '';

if (!isempty_request_var('language') && !empty($lang2locale[grv('language')])) {
	// user requests another language
	$user_locale = apply_locale(grv('language'));
	unset($_SESSION['sess_current_date1']);
	unset($_SESSION['sess_current_date2']);

	// save customized language setting (authenticated users only)
	set_user_setting('language', $user_locale);
} elseif (!empty($_SESSION['sess_user_language']) && !empty($lang2locale[$_SESSION['sess_user_language']])) {
	// language definition stored in the SESSION
	$user_locale = apply_locale($_SESSION[SESS_USER_LANGUAGE]);
} else {
	// look up for user customized language setting stored in Cacti DB
	$user_locale = apply_locale(read_user_i18n_setting('user_language'));
}

// allow RRDtool to display i18n
setlocale(LC_CTYPE, str_replace('-', '_', $user_locale) . '.UTF-8');

if ($user_locale !== false && $user_locale !== '') {
	$_SESSION[SESS_USER_LANGUAGE] = $user_locale;
}

// define the path to the language file
$path2catalogue = get_mo_language_file([$cacti_locale, $lang2locale[$cacti_locale]['filename']]);
$catalogue      = $path2catalogue;

// define the path to the language file of the DHTML calendar
$path2timepicker  = '';
$path2calendar    = '';
$path2ms          = '';
$path2msfiler     = '';
$path2colorpicker = '';

if ($cacti_locale != '') {
	$lang_parts = explode('-', $cacti_locale);
	$lang_names = [$cacti_locale, $lang_parts[0]];

	// Detect the calendar path
	$path2calendar    = get_js_language_file($lang_names, 'jquery-ui-datepicker-');
	$path2timepicker  = get_js_language_file($lang_names, 'jquery-ui-timepicker-');
	$path2colorpicker = get_js_language_file($lang_names, 'jquery-ui-colorpicker-');
	$path2ms          = get_js_language_file($lang_names, 'jquery-ui-multiselect-');
	$path2msfilter    = get_js_language_file($lang_names, 'jquery-ui-multiselect-filter-');
}

// use fallback procedure if requested language is not available
if (file_exists($path2catalogue)) {
	$cacti_textdomains['cacti']['path2catalogue'] = $path2catalogue;
} else {
	i18n_debug('load_fallback_procedure(2): ' . $path2catalogue);
	load_fallback_procedure();

	return;
}

// search the correct textdomains for all plugins being installed
$plugins = db_fetch_assoc('SELECT `directory`
	FROM `plugin_config`
	ORDER BY id');

if ($plugins && cacti_sizeof($plugins)) {
	$lang_names = [$cacti_locale, $lang2locale[$cacti_locale]['filename']];

	foreach ($plugins as $plugin) {
		$plugin = $plugin['directory'];

		$path2catalogue = get_mo_language_file($lang_names, null, CACTI_PATH_PLUGINS . '/' . $plugin);

		if (!empty($path2catalogue) && file_exists($path2catalogue)) {
			$cacti_textdomains[$plugin]['path2catalogue'] = $path2catalogue;
		}
	}

	// if i18n support is set to strict mode then check if all plugins support the requested language
	if (read_config_option('i18n_language_support') == 2) {
		if (cacti_sizeof($plugins) != (cacti_sizeof($cacti_textdomains) - 1)) {
			i18n_debug('load_fallback_procedure(3)');
			load_fallback_procedure();

			return;
		}
	}
}

i18n_debug('Attempt to find the handler');

// load php-gettext class if present
$i18n = [];

// Is the handler defined in the db?
$i18n_handler = read_config_option('i18n_language_handler');

// Is the handler defined in the config but not the db?
if (empty($i18n_handler) && !empty($config['i18n_language_handler'])) {
	i18n_debug('Handler: specified in config, not in settings');
	$i18n_handler = $config['i18n_language_handler'];
}

i18n_debug("require(1): Defined handler $i18n_handler");

$i18n_provider = null;

if (!empty($i18n_handler)) {
	$i18n_provider = get_src_language_files($i18n_handler);
}

if ($i18n_provider === null) {
	$i18n_provider = get_src_language_files(null);
}

$i18n_handler = CACTI_LANGUAGE_HANDLER_DEFAULT;

if ($i18n_provider !== null) {
	$i18n_handler = $i18n_provider['handler'];
	i18n_debug("require(1): Selected handler $i18n_handler");

	foreach ($i18n_provider['paths'] as $providerPath) {
		foreach ($i18n_provider['files'] as $providerFile) {
			$providerFull = $providerPath . $providerFile;
			i18n_debug("require(1): Requiring $providerFull");
			require_once($providerFull);
		}
	}
}

set_language_constants([
	'HANDLER' => $i18n_handler
]);

i18n_debug('require(2): Final handler ' . CACTI_LANGUAGE_HANDLER);

if (CACTI_LANGUAGE_HANDLER != CACTI_LANGUAGE_HANDLER_DEFAULT) {
	/* prefetch all language files to work in memory only,
	   die if one of the language files is corrupted */

	foreach ($cacti_textdomains as $domain => $paths) {
		i18n_debug("load_language($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

		switch (CACTI_LANGUAGE_HANDLER) {
			case CACTI_LANGUAGE_HANDLER_PHPGETTEXT:
				$i18n[$domain] = load_gettext_original($domain);

				break;
			case CACTI_LANGUAGE_HANDLER_MOTRANSLATOR:
				$i18n[$domain] = load_gettext_motranslator($domain);

				break;
		}

		if (empty($i18n[$domain])) {
			die('Invalid language support or corrupt/missing file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
		}
	}
	unset($input);
}

// load standard wrappers
set_language_constants([
	'LOCALE'   => $cacti_locale,
	'COUNTRY'  => $cacti_country,
	'LANGUAGE' => $lang2locale[$cacti_locale]['language'],
	'FILE'     => $catalogue,
]);

/**
 * Generates the path to a JavaScript language file.
 *
 * @param array       $names     An array of language file names.
 * @param string|null $prefix    An optional prefix for the language file.
 * @param string|null $base_path An optional base path for the language file. Defaults to CACTI_PATH_INCLUDE/js/LC_MESSAGES/.
 * @param string|null $extension An optional file extension for the language file. Defaults to 'js'.
 *
 * @return string The path to the JavaScript language file.
 */
function get_js_language_file(array $names, string|null $prefix = null, string|null $base_path = null, string|null $extension = null) : string {
	global $config;

	$extension = empty($extension) ? 'js' : $extension;
	$prefix    = empty($prefix) ? '' : $prefix;
	$base_path = (empty($base_path) ? CACTI_PATH_INCLUDE : $base_path) . '/js/LC_MESSAGES/';

	i18n_debug('get_js_language_file("' . $prefix . '", "' . $base_path . '", "' . $extension . '")');

	return get_language_file($extension, $prefix, $names, $base_path);
}

/**
 * Retrieves the path to a .mo language file based on the provided parameters.
 *
 * @param array       $names     An array of language names to search for.
 * @param string|null $prefix    A prefix to prepend to the language file name. Default is null.
 * @param string|null $base_path The base path where the language files are located. Default is null.
 * @param string|null $extension The file extension of the language file. Default is 'mo'.
 *
 * @return string The path to the .mo language file.
 */
function get_mo_language_file(array $names, string|null $prefix = null, string|null $base_path = null, string|null $extension = null) : string {
	global $config;

	$extension = empty($extension) ? 'mo' : $extension;
	$prefix    = empty($prefix) ? '' : $prefix;
	$base_path = (empty($base_path) ? CACTI_PATH_BASE : $base_path) . '/locales/LC_MESSAGES/';

	i18n_debug('get_mo_language_file("' . $prefix . '", "' . $base_path . '", "' . $extension . '")');

	return get_language_file($extension, $prefix, $names, $base_path);
}

/**
 * Retrieves the appropriate language file based on the provided parameters.
 *
 * @param string      $extension The file extension to append to the language file name.
 * @param string      $prefix    The prefix to prepend to the language file name.
 * @param array       $names     An array of potential language file names to search for.
 * @param string|null $base_path The base path where the language files are located. Defaults to CACTI_PATH_BASE if not provided.
 *
 * @return string The path to the found language file, or an empty string if no file is found.
 */
function get_language_file(string $extension, string $prefix, array $names, string|null $base_path = null) : string {
	global $config;

	if (empty($extension)) {
		$extension = '';
	} else {
		$extension = '.' . ltrim($extension, '. ');
	}

	$base_path = empty($base_path) ? CACTI_PATH_BASE : $base_path;
	$base_path = rtrim($base_path, '/') . '/';

	foreach ($names as $name) {
		$file   = $base_path . $prefix . $name . $extension;
		$exists = file_exists($file);

		i18n_debug('get_language_file("' . $extension . '", "' . $prefix . '", "' . $base_path . '"): ' . ($exists ? 'Yes' : 'No') . ' - "' . $file . '"');

		if ($exists) {
			return $file;
		}
	}

	return '';
}

/**
 * Retrieves the source language files based on the specified internationalization handler.
 *
 * @param string|null $i18n_handler The internationalization handler to use. If null or empty, defaults to checking both PHPGETTEXT and MOTRANSLATOR handlers.
 *
 * @return array|null An array containing the handler, paths, and files for the selected internationalization provider, or null if no valid provider is found.
 */
function get_src_language_files(string|null $i18n_handler) : array|null {
	global $config;

	$i18n_providers = [];

	if (empty($i18n_handler) || $i18n_handler === CACTI_LANGUAGE_HANDLER_PHPGETTEXT) {
		$i18n_providers[] = [
			'handler' => CACTI_LANGUAGE_HANDLER_PHPGETTEXT,
			'paths'   => [ CACTI_PATH_INCLUDE . '/vendor/phpgettext/' ],
			'files'   => ['streams.php', 'gettext.php'],
		];
	}

	if (empty($i18n_handler) || $i18n_handler === CACTI_LANGUAGE_HANDLER_MOTRANSLATOR) {
		$i18n_providers[] = [
			'handler' => CACTI_LANGUAGE_HANDLER_MOTRANSLATOR,
			'paths'   => [
				CACTI_PATH_INCLUDE . '/vendor/MoTranslator/',
				CACTI_PATH_INCLUDE . '/vendor/motranslator/',
				CACTI_PATH_INCLUDE . '/vendor/motranslator/src/',
			],
			/*
			 * This was 'files' => ['Translator.php', 'StringReader.php' ],
			 * but has been replaced with autoload.php to support Debian
			 * bullseye which has an updated version of MoTranslator
			 */
			'files' => ['autoload.php'],
		];
	}

	$i18n_handler_text = $i18n_handler ?? 'null';

	foreach ($i18n_providers as $i18n_provider) {
		$found = true;
		$all   = false;

		foreach ($i18n_provider['paths'] as $path) {
			if ($all == false) {
				$found = true;
			}

			foreach ($i18n_provider['files'] as $file) {
				$fullPath   = $path . $file;
				$fullExists = file_exists($fullPath);
				$fullYesNo  = $fullExists ? 'Yes' : 'No ';

				i18n_debug("get_src_language_provider($i18n_handler_text) : {$i18n_provider['handler']} - $fullYesNo - $fullPath");

				if (!$fullExists) {
					$found = false;

					break;
				}

				if ($all == false) {
					break;
				}
			}

			if ($all && $found == false) {
				i18n_debug("get_src_language_provider($i18n_handler_text) : {$i18n_provider['handler']} - Requires all locations for all files, but missing one, skipped");

				break;
			}

			if ($found) {
				$i18n_return = $i18n_provider;

				if ($all == false) {
					$i18n_return['paths'] = [ $path ];
				}

				i18n_debug("get_src_language_provider($i18n_handler_text) : {$i18n_provider['handler']} - Selecting with " . count($i18n_return['paths']) . ' paths');

				return $i18n_return;
			}
		}
	}

	return null;
}

/**
 * Loads the gettext translation for the specified domain.
 *
 * This function reads the translation file for the given domain and returns a gettext_reader object.
 * It also handles deprecation errors for PHP 8 and logs the loading process for debugging purposes.
 *
 * @param string $domain The domain for which the translation should be loaded.
 *
 * @return \gettext_reader The gettext_reader object for the specified domain.
 *
 * @throws Exception If the translation file cannot be read or is invalid.
 */
function load_gettext_original(string $domain) : \gettext_reader {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_original($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	if (class_exists('FileReader')) {
		$input = new FileReader($cacti_textdomains[$domain]['path2catalogue']);
	} else {
		$input = false;
	}

	if ($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	$i18n_domain = new gettext_reader($input);

	if ($i18n_domain === false) {
		die('Invalid language file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $i18n_domain;
}

/**
 * Loads a gettext MO translator for the specified domain.
 *
 * @param string $domain The domain for which to load the translator.
 *
 * @return mixed - The initialized translator object.
 *
 * @throws Exception If the translation file cannot be read.
 */
function load_gettext_motranslator(string $domain) : mixed {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_mostranslator($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	if (class_exists('PhpMyAdmin\MoTranslator\Translator')) {
		$input = new PhpMyAdmin\MoTranslator\Translator($cacti_textdomains[$domain]['path2catalogue']);
	} else {
		$input = false;
	}

	if ($input === false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $input;
}

/**
 * Loads and returns a Gettext\Translator instance for the specified domain.
 *
 * This function reads a .mo file for the given domain, creates a Gettext\Translator
 * instance, and loads the translations from the .mo file into the translator.
 *
 * @param string $domain The domain for which to load the translations.
 *
 * @return mixed - The translator instance loaded with the domain's translations.
 *
 * @throws Exception If the .mo file cannot be read or is invalid.
 */
function load_gettext_oscarotero(string $domain) : mixed {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_oscarotero($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	$input = false;

	if (class_exists('Gettext\Translations')) {
		$input = Gettext\Translations::fromMoFile($cacti_textdomains[$domain]['path2catalogue']);
	}

	if ($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	if (class_exists('Gettext\Translator')) {
		$i18n_domain = new Gettext\Translator();
		$i18n_domain->loadTranslations($input);
	} else {
		$i18n_domain = false;
		die('WARNING: The Oscaro Tero Translated not installed' . PHP_EOL);
	}

	if ($i18n_domain === false) {
		die('Invalid language file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $i18n_domain;
}

/**
 * Applies the locale based on the provided language or autodetects it from the browser settings.
 *
 * @param string $language - The language code to apply.
 *
 * @return mixed - The applied locale if successful, or false if no valid locale could be set.
 */
function apply_locale(string $language) : mixed {
	global $cacti_locale, $cacti_country, $lang2locale;

	$locale_set = false;

	if ($language != '') {
		$language   = repair_locale($language);
		$locale_set = isset($lang2locale[$language]);
	}

	// If the users has not elected a language and autodetect is on
	if (!$locale_set && (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && (read_config_option('i18n_auto_detection') == '' || read_config_option('i18n_auto_detection') == '1'))) {
		// detect browser settings if auto detection is enabled
		$accepted = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$accepted = $accepted[0];

		$language   = repair_locale($accepted);
		$locale_set = isset($lang2locale[$language]);
	}

	if (!$locale_set) {
		$language = repair_locale(read_config_option('i18n_default_language') ?? '');

		if ($language === null || $language == '') {
			$language = repair_locale(read_default_config_option('i18n_default_language'));
		}

		$locale_set = isset($lang2locale[$language]);
	}

	if ($locale_set) {
		$cacti_locale  = $language;
		$cacti_country = $lang2locale[$cacti_locale]['country'];

		return $cacti_locale;
	}

	return false;
}

// best effort function to repair locale
function repair_locale(mixed $language) : string {
	global $lang2locale;

	// Repair legacy language support
	if ($language != '' && $language != null) {
		$found_locale = '';
		$locale       = str_replace('_','-', $language);

		if (array_key_exists($locale, $lang2locale)) {
			$language = $locale;
		} else {
			$wanted_locale = substr($language, 0, 2);
			$language      = '';

			foreach ($lang2locale as $locale => $data) {
				if (substr($locale, 0, 2) == $wanted_locale) {
					$language = $locale;

					break;
				}
			}
		}
	} else {
		$language = 'en-US';
	}

	return $language;
}

/**
 * Universal escaping wrappers
 */
function __esc() : string {
	return htmlspecialchars(call_user_func_array('__', func_get_args()), ENT_QUOTES);
}

function __esc_n() : string {
	return htmlspecialchars(call_user_func_array('__n', func_get_args()), ENT_QUOTES);
}

function __esc_x() : string {
	return htmlspecialchars(call_user_func_array('__x', func_get_args()), ENT_QUOTES);
}

function __esc_xn() : string {
	return htmlspecialchars(call_user_func_array('__xn', func_get_args()), ENT_QUOTES);
}

/**
 * load_fallback_procedure - loads wrapper package if native language (English) has to be used
 *
 * @return void
 */
function load_fallback_procedure() : void {
	global $cacti_textdomains, $cacti_locale, $cacti_country, $lang2locale;

	// reset variables
	$_SESSION[SESS_USER_LANGUAGE] = '';

	$cacti_textdomains = [];
	set_language_constants([
		'LOCALE'   => 'en-US',
		'COUNTRY'  => 'us',
		'LANGUAGE' => 'English',
		'FILE'     => 'english_usa',
		'HANDLER'  => CACTI_LANGUAGE_HANDLER_DEFAULT,
	]);
}

/**
 * Sets language-related constants based on the provided associative array.
 *
 * @param array $constants An associative array of constants to set, where the
 *                         key is the constant name and the value is the constant value.
 *
 * @return void
 */
function set_language_constants(array $constants) : void {
	foreach ($constants as $key => $value) {
		$upperKey = cacti_strtoupper($key);

		switch ($upperKey) {
			case 'LOCALE':
				define('CACTI_LOCALE', $value);

				break;
			case 'COUNTRY':
				define('CACTI_COUNTRY', $value);

				break;
			case 'LANGUAGE':
				define('CACTI_LANGUAGE', $value);

				break;
			case 'FILE':
				define('CACTI_LANGUAGE_FILE', $value);

				break;
			case 'HANDLER':
				define('CACTI_LANGUAGE_HANDLER', $value);

				break;
		}
	}
}

/**
 * Translates a given text string using the specified domain.
 *
 * @param  string|null $text   The text string to be translated. If null, an empty string is used.
 * @param  string      $domain The domain to use for translation. Defaults to 'cacti'.
 * @return string      The translated text, or the original text if translation is not available.
 */
function __gettext(string|null $text, string $domain = 'cacti') : string {
	global $i18n;

	$text ??= '';

	// Assume translation fails or is not defined
	if (isset($i18n[$domain])) {
		switch (CACTI_LANGUAGE_HANDLER) {
			case CACTI_LANGUAGE_HANDLER_PHPGETTEXT:
				$translated = $i18n[$domain]->translate($text);

				break;
			case CACTI_LANGUAGE_HANDLER_MOTRANSLATOR:
				$translated = $i18n[$domain]->gettext($text);

				break;
		}
	}

	if (!isset($translated)) {
		$translated = $text;
	} else {
		i18n_text_debug("__gettext($domain):\n	Original: $text\n	Translated: $translated", FILE_APPEND);
	}

	return __uf($translated);
}

/**
 * Translates and pluralizes a given string based on the provided number.
 *
 * @param string|null $singular The singular form of the string.
 * @param string|null $plural   The plural form of the string.
 * @param int         $number   The number to determine singular or plural form.
 * @param string      $domain   The translation domain to use (default is 'cacti').
 *
 * @return string - The translated and pluralized string.
 */
function __n(string|null $singular, string|null $plural, int $number, string $domain = 'cacti') : string {
	global $i18n;

	$singular ??= '';
	$plural ??= '';

	if (isset($i18n[$domain])) {
		return __uf($i18n[$domain]->ngettext($singular, $plural, $number));
	} else {
		return ($number == 1) ? __uf($singular) : __uf($plural);
	}
}

/**
 * Replaces double percent signs (%%) with a single percent sign (%) in the given text.
 *
 * @param string|null $text The input text which may contain double percent signs.
 *                          If null, an empty string will be used.
 *
 * @return string - The processed text with double percent signs replaced by single percent signs.
 */
function __uf(string|null $text) : string {
	return str_replace('%%', '%', $text ?? '');
}

/**
 * Translates and formats a string based on the provided arguments.
 *
 * This function uses gettext for translation and sprintf for formatting.
 * It supports different text domains and various formatting options.
 *
 * @return string - Returns the translated and formatted string, or false if no arguments are provided.
 */
function __() : string {
	global $i18n;

	$args = func_get_args();
	$num  = func_num_args();

	// this should not happen
	if ($num < 1) {
		return '';
	}

	if ($num == 1) {
		return __gettext($args[0]);
		// convert pure text strings by using a different textdomain
	}

	/* only the last argument is allowed to initiate
	   the use of a different textdomain */

	// get gettext string
	if (isset($i18n[(string) $args[$num - 1]]) && $args[$num - 1] != 'cacti') {
		$args[0] = __gettext($args[0], $args[$num - 1]);
	} else {
		$args[0] = __gettext($args[0]);
	}

	$regex_num = '%([-]{0,1}[0-9]+([.][0-9]+){0,1}){0,1}';
	$regex_str = '%([-]{0,1}[0-9]+){0,1}';

	$array_str = [
		'b', // Binary
		'o', // Integer as Octal
		's', // String
		'u', // Integer as Unsigned Decimal
		'x', // Integer as hex (lowercase)
		'X', // Integer as hex (uppercase)
	];

	$array_num = [
		'd', // Decimal
		'e', // Scientific notation (lowercase)
		'E', // Scientific notation (uppercase)
		'f', // Floating point (locale aware)
		'F', // Floating point (non-locale aware)
		'g', // General format (uses E and f styling if precision involved)
		'G', // General format (docs say same as g but uses E and f, yet it already does???)
		'h', // General format (like g but uses F)
		'H', // General format (like g but uses E and F)
	];

	$valid_args = [
		'%%', // Escaped percentage (literal)
		'%c', // Single Character
		$regex_num . '[' . implode('', $array_num) . ']',
		$regex_str . '[' . implode('', $array_str) . ']',
	];

	$valid_regexp = '/(' . implode(')|(', $valid_args) . ')/';

	if (preg_match($valid_regexp, $args[0])) {
		// process return string against input arguments
		return __uf(call_user_func_array('sprintf', $args));
	} else {
		return $args[0];
	}
}

/**
 * Translates and pluralizes a string based on the given context and number.
 *
 * @param string $context  The context for the translation.
 * @param string $singular The singular form of the string to be translated.
 * @param string $plural   The plural form of the string to be translated.
 * @param int    $number   The number to determine singular or plural form.
 * @param string $domain   The text domain for the translation. Default is 'cacti'.
 *
 * @return string The translated and correctly pluralized string.
 */
function __xn(string $context, string $singular, string $plural, int $number, string $domain = 'cacti') : string {
	$xsingular = $context . chr(4) . $singular;
	$xplural   = $context . chr(4) . $plural;

	$msgstr = __n($xsingular, $xplural, $number, $domain);

	if ($number == 1) {
		return ($msgstr == $xsingular) ? __uf($singular) : __uf($msgstr);
	} else {
		return ($msgstr == $xplural) ? __uf($plural) : __uf($msgstr);
	}
}

/**
 * Translates a message with context using gettext and formats it with the provided arguments.
 *
 * @return false|string The translated and formatted message string, or false if the number of arguments is less than 2.
 */
function __x() : false|string {
	global $i18n;

	$args = func_get_args();
	$num  = func_num_args();

	// this should never happen
	if ($num < 2) {
		return false;
	} else {
		$context = array_shift($args);
		$num--;

		$msgid  = reset($args);
		$xmsgid = $context . chr(4) . $msgid;

		$args[0] = $xmsgid;

		if ($num == 1) {
			// pure text string without placeholders and a change of the default textdomain
			$msgstr = __gettext($args[0]);
		} else {
			// get gettext string
			$msgstr = isset($i18n[(string) $args[$num - 1]]) && $args[$num - 1] != 'cacti' ?
			__gettext($args[0], $args[$num - 1]) : __gettext($args[0]);
		}

		// use the raw message id if language catalogue does not contain a context specific message string
		$args[0] = ($msgstr == $xmsgid) ? $msgid : $msgstr;

		// process return string against input arguments
		return __uf(call_user_func_array('sprintf', $args));
	}
}

/**
 * Formats a given timestamp according to a specified format and translates date components.
 *
 * @param string    $format    The format string to use for formatting the date.
 * @param int|false $timestamp The timestamp to format. If false, the current time is used. Default is false.
 * @param string    $domain    The translation domain to use for translating date components. Default is 'cacti'.
 *
 * @return string The formatted and translated date string.
 */
function __date(string $format, int|false $timestamp = false, string $domain = 'cacti') : string {
	global $i18n_date_placeholders;

	if (!$timestamp) {
		$timestamp = time();
	}

	// placeholders will allow to fill in the translated weekdays, month and so on..
	$i18n_date_placeholders = [
		'#1' => __(date('D', $timestamp), $domain),
		'#2' => __(date('M', $timestamp), $domain),
		'#3' => __(date('F', $timestamp), $domain),
		'#4' => __(date('l', $timestamp), $domain)
	];

	// if defined exchange the format string for the configured locale
	$format = __gettext($format, $domain);

	// replace special date chars by placeholders
	$format = str_replace(['D', 'M', 'F', 'l'], ['#1', '#2', '#3', '#4'], $format);

	// get date string included placeholders
	$date = date($format, $timestamp);

	// fill in specific translations
	$date = str_replace(array_keys($i18n_date_placeholders), array_values($i18n_date_placeholders), $date);

	return __uf($date);
}

/**
 * get_list_of_locales - returns the default settings being used for i18n
 *
 * @return array - a multi-dimensional array with the locale code as main key
 */
function get_list_of_locales() : array {
	$lang2locale = [
		'sq-AL' => ['language' => 'Albanian',            'direction' => 'ltr', 'country' => 'al', 'filename' => 'albanian_albania'],
		'ar-SA' => ['language' => 'Arabic',              'direction' => 'rtl', 'country' => 'sa', 'filename' => 'arabic_saudi_arabia'],
		'hy-AM' => ['language' => 'Armenian',            'direction' => 'ltr', 'country' => 'am', 'filename' => 'armenian_armenia'],
		'be-BY' => ['language' => 'Belarusian',          'direction' => 'ltr', 'country' => 'by', 'filename' => 'belarusian_belarus'],
		'bg-BG' => ['language' => 'Bulgarian',           'direction' => 'ltr', 'country' => 'bg', 'filename' => 'bulgarian_bulgaria'],
		'zh-CN' => ['language' => 'Chinese (China)',     'direction' => 'ltr', 'country' => 'cn', 'filename' => 'chinese_china_simplified'],
		'zh-HK' => ['language' => 'Chinese (Hong Kong)', 'direction' => 'ltr', 'country' => 'hk', 'filename' => 'chinese_hong_kong'],
		'zh-SG' => ['language' => 'Chinese (Singapore)', 'direction' => 'ltr', 'country' => 'sg', 'filename' => 'chinese_singapore'],
		'zh-TW' => ['language' => 'Chinese (Taiwan)',    'direction' => 'ltr', 'country' => 'tw', 'filename' => 'chinese_taiwan'],
		'hr-HR' => ['language' => 'Croatian',            'direction' => 'ltr', 'country' => 'hr', 'filename' => 'croatian_croatia'],
		'cs-GZ' => ['language' => 'Czech',               'direction' => 'ltr', 'country' => 'cz', 'filename' => 'czech_czech_republic'],
		'da-DK' => ['language' => 'Danish',              'direction' => 'ltr', 'country' => 'dk', 'filename' => 'danish_denmark'],
		'nl-NL' => ['language' => 'Dutch',               'direction' => 'ltr', 'country' => 'nl', 'filename' => 'dutch_netherlands'],
		'en-US' => ['language' => 'English',             'direction' => 'ltr', 'country' => 'us', 'filename' => 'english_usa'],
		'en-GB' => ['language' => 'English (Britain)',   'direction' => 'ltr', 'country' => 'gb', 'filename' => 'english_gb'],
		'et-EE' => ['language' => 'Estonian',            'direction' => 'ltr', 'country' => 'ee', 'filename' => 'estonian_estonia'],
		'fi-FI' => ['language' => 'Finnish',             'direction' => 'ltr', 'country' => 'fi', 'filename' => 'finnish_finland'],
		'fr-FR' => ['language' => 'French',              'direction' => 'ltr', 'country' => 'fr', 'filename' => 'french_france'],
		'ka-GE' => ['language' => 'Georgian',            'direction' => 'ltr', 'country' => 'ge', 'filename' => 'georgian_georgia'],
		'de-DE' => ['language' => 'German',              'direction' => 'ltr', 'country' => 'de', 'filename' => 'german_germany'],
		'el-GR' => ['language' => 'Greek',               'direction' => 'ltr', 'country' => 'gr', 'filename' => 'greek_greece'],
		'he-IL' => ['language' => 'Hebrew',              'direction' => 'rtl', 'country' => 'il', 'filename' => 'hebrew_israel'],
		'hi-IN' => ['language' => 'Hindi',               'direction' => 'ltr', 'country' => 'in', 'filename' => 'hindi_india'],
		'hu-HU' => ['language' => 'Hungarian',           'direction' => 'ltr', 'country' => 'hu', 'filename' => 'hungarian_hungary'],
		'is-IS' => ['language' => 'Icelandic',           'direction' => 'ltr', 'country' => 'is', 'filename' => 'icelandic_iceland'],
		'id-ID' => ['language' => 'Indonesian',          'direction' => 'ltr', 'country' => 'id', 'filename' => 'indonesian_indonesia'],
		'ga-IE' => ['language' => 'Irish',               'direction' => 'ltr', 'country' => 'ie', 'filename' => 'irish_ireland'],
		'it-IT' => ['language' => 'Italian',             'direction' => 'ltr', 'country' => 'it', 'filename' => 'italian_italy'],
		'ja-JP' => ['language' => 'Japanese',            'direction' => 'ltr', 'country' => 'jp', 'filename' => 'japanese_japan'],
		'ko-KR' => ['language' => 'Korean',              'direction' => 'ltr', 'country' => 'kr', 'filename' => 'korean_korea'],
		'lv-LV' => ['language' => 'Latvian',             'direction' => 'ltr', 'country' => 'lv', 'filename' => 'latvian_latvia'],
		'lt-LT' => ['language' => 'Lithuanian',          'direction' => 'ltr', 'country' => 'lt', 'filename' => 'lithuanian_lithuania'],
		'mk-MK' => ['language' => 'Macedonian',          'direction' => 'ltr', 'country' => 'mk', 'filename' => 'macedonian_macedonia'],
		'ms-MY' => ['language' => 'Malay',               'direction' => 'ltr', 'country' => 'my', 'filename' => 'malay_malaysia'],
		'mt-LT' => ['language' => 'Maltese',             'direction' => 'ltr', 'country' => 'lt', 'filename' => 'maltese_malta'],
		'no-NO' => ['language' => 'Norwegian',           'direction' => 'ltr', 'country' => 'no', 'filename' => 'norwegian_norway'],
		'pl-PL' => ['language' => 'Polish',              'direction' => 'ltr', 'country' => 'pl', 'filename' => 'polish_poland'],
		'pt-PT' => ['language' => 'Portuguese',          'direction' => 'ltr', 'country' => 'pt', 'filename' => 'portuguese_portugal'],
		'pt-BR' => ['language' => 'Portuguese (Brazil)', 'direction' => 'ltr', 'country' => 'br', 'filename' => 'portuguese_brazil'],
		'ro-RO' => ['language' => 'Romanian',            'direction' => 'ltr', 'country' => 'ro', 'filename' => 'romanian_romania'],
		'ru-RU' => ['language' => 'Russian',             'direction' => 'ltr', 'country' => 'ru', 'filename' => 'russian_russia'],
		'sr-RS' => ['language' => 'Serbian',             'direction' => 'ltr', 'country' => 'rs', 'filename' => 'serbian_serbia'],
		'sk-SK' => ['language' => 'Slovak',              'direction' => 'ltr', 'country' => 'sk', 'filename' => 'slovak_slovakia'],
		'sl-SI' => ['language' => 'Slovenian',           'direction' => 'ltr', 'country' => 'si', 'filename' => 'slovenian_slovenia'],
		'es-ES' => ['language' => 'Spanish',             'direction' => 'ltr', 'country' => 'es', 'filename' => 'spanish_spain'],
		'sv-SE' => ['language' => 'Swedish',             'direction' => 'ltr', 'country' => 'se', 'filename' => 'swedish_sweden'],
		'th-TH' => ['language' => 'Thai',                'direction' => 'ltr', 'country' => 'th', 'filename' => 'thai_thailand'],
		'tr-TR' => ['language' => 'Turkish',             'direction' => 'ltr', 'country' => 'tr', 'filename' => 'turkish_turkey'],
		'uk-UA' => ['language' => 'Ukrainian',           'direction' => 'ltr', 'country' => 'ua', 'filename' => 'ukrainian_ukraine'],
		'vi-VN' => ['language' => 'Vietnamese',          'direction' => 'ltr', 'country' => 'vn', 'filename' => 'vietnamese_vietnam']
	];

	return $lang2locale;
}

/**
 * get_installed_locales - finds all installed locales
 *
 * @return array - an associative array of all installed locales (e.g. 'en' => 'English')
 */
function get_installed_locales() {
	global $config, $lang2locale;

	$locations                    = [];
	$supported_languages['en-US'] = $lang2locale['en-US']['language'];

	foreach ($lang2locale as $locale => $properties) {
		$locations[$properties['filename'] . '.mo'] = [
			'locale'   => $locale,
			'language' => $properties['language']
		];
		$locations[$locale . '.mo'] = [
			'locale'   => $locale,
			'language' => $properties['language']
		];
	}

	// create a list of all languages this Cacti system supports ...
	$dhandle = opendir(CACTI_PATH_LOCALES . '/LC_MESSAGES');

	if (is_resource($dhandle)) {
		while (false !== ($filename = readdir($dhandle))) {
			if (isset($locations[$filename]['language'])) {
				$supported_languages[$locations[$filename]['locale']] = $locations[$filename]['language'];
			}
		}
	}

	asort($supported_languages);

	return $supported_languages;
}

/**
 * read_user_i18n_setting - finds the current value of a i18n configuration setting
 *
 * @param string $config_name The name of the configuration setting to retrieve.
 *
 * @return mixed - The value of the configuration setting if found, or false if not found.
 */
function read_user_i18n_setting(string $config_name) : mixed {
	global $config;

	// users must have cacti user auth turned on to use this, or the guest account must be active
	if (isset($_SESSION[SESS_USER_ID])) {
		$effective_uid = $_SESSION[SESS_USER_ID];
	} else {
		$effective_uid = 0;
	}

	if (db_table_exists('settings_user')) {
		$db_setting = db_fetch_row_prepared('SELECT value
			FROM settings_user
			WHERE name = ?
			AND user_id = ?',
			[$config_name, $effective_uid]);
	}

	if (isset($db_setting['value'])) {
		return $db_setting['value'];
	} else {
		return false;
	}
}

/**
 * Formats a number according to the current locale settings.
 *
 * This function attempts to use the `NumberFormatter` class if available,
 * otherwise it falls back to using PHP's `number_format` function with locale settings.
 *
 * @param mixed $number   The number to format.
 * @param mixed $decimals The number of decimal points. If null, defaults to 0.
 * @param mixed $baseu    The base unit for formatting large numbers (default is 1024).
 *
 * @return string - The formatted number.
 */
function number_format_i18n(mixed $number, mixed $decimals = null, mixed $baseu = 1024) : string {
	global $cacti_locale, $cacti_country;

	if (is_null($number)) {
		return '0';
	}

	if (!is_numeric($number)) {
		return '0';
	}

	$country = cacti_strtoupper($cacti_country);

	if (function_exists('numfmt_create')) {
		$fmt_key = $cacti_locale . '_' . $country;
		$fmt     = numfmt_create($fmt_key, NumberFormatter::DECIMAL);

		if ($decimals == null) {
			$decimals = 0;
		}

		if ($fmt !== null) {
			numfmt_set_attribute($fmt, NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

			return numfmt_format($fmt, $number);
		}

		cacti_log('DEBUG: Number format \'' . $fmt_key . '\' was unavailable, using older methods', false, 'i18n', POLLER_VERBOSITY_HIGH);
	}

	$origlocales = explode(';', setlocale(LC_ALL, null));
	setlocale(LC_ALL, $cacti_locale);
	$locale = localeconv();

	if (!isset($locale['decimal_point']) || $locale['decimal_point'] == '') {
		$locale['decimal_point'] = '.';
	}

	if (!isset($locale['thousands_sep']) || $locale['thousands_sep'] == '') {
		$locale['thousands_sep'] = ',';
	}

	if ($decimals == -1 || $decimals == null) {
		$number = number_format($number, 0, $locale['decimal_point'], $locale['thousands_sep']);
	} elseif ($number >= $baseu ** 4) {
		$number = number_format($number / $baseu ** 4, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' T');
	} elseif ($number >= $baseu ** 3) {
		$number = number_format($number / $baseu ** 3, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' G');
	} elseif ($number >= $baseu ** 2) {
		$number = number_format($number / $baseu ** 2, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' M');
	} elseif ($number >= $baseu) {
		$number = number_format($number / $baseu, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' K');
	} else {
		$number = number_format($number, $decimals, $locale['decimal_point'], $locale['thousands_sep']);
	}

	foreach ($origlocales as $locale_setting) {
		if (str_contains($locale_setting, '=')) {
			[$category, $locale] = explode('=', $locale_setting);
		} else {
			$category = LC_ALL;
			$locale   = $locale_setting;
		}

		switch($category) {
			case 'LC_ALL':
			case 'LC_COLLATE':
			case 'LC_CTYPE':
			case 'LC_MONETARY':
			case 'LC_NUMERIC':
			case 'LC_TIME':
				if (defined($category)) {
					setlocale(constant($category), $locale);
				}
		}
	}

	return $number;
}

/**
 * Retrieves the default language for a new user.
 *
 * @return string The default language code for a new user.
 */
function get_new_user_default_language() : string {
	$accepted = repair_locale(read_config_option('i18n_default_language'));

	if ($accepted == '') {
		$accepted = repair_locale(read_default_config_option('i18n_default_language'));
	}

	return $accepted;
}

/**
 * Logs internationalization (i18n) debug messages to a specified log file.
 *
 * @param string $text The debug message to log.
 * @param int    $mode The file append mode. Default is FILE_APPEND.
 * @param string $eol  The end-of-line character(s) to use. Default is PHP_EOL.
 *
 * @return void
 */
function i18n_debug(string $text, int $mode = FILE_APPEND, string $eol = PHP_EOL) : void {
	global $config;

	if (!empty($config['i18n_log']) && is_writable($config['i18n_log'])) {
		file_put_contents($config['i18n_log'], $text . $eol, $mode);
	}
}

/**
 * Logs internationalization text for debugging purposes.
 *
 * @param string $text The text to be logged.
 * @param int    $mode The file append mode. Default is FILE_APPEND.
 * @param string $eol  The end-of-line character(s) to append to the text. Default is PHP_EOL.
 *
 * @return void
 */
function i18n_text_debug(string $text, int $mode = FILE_APPEND, string $eol = PHP_EOL) : void {
	global $config;

	if (!empty($config['i18n_text_log']) && is_writable($config['i18n_log'])) {
		file_put_contents($config['i18n_text_log'], $text . $eol, $mode);
	}
}
