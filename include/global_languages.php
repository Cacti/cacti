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

/* default localization of Cacti */
$cacti_locale  = 'en-US';
$cacti_country = 'us';

/* an array that will contains all textdomains being in use. */
$cacti_textdomains = array();

global $path2calendar, $path2timepicker, $path2colorpicker, $path2ms, $path2msfilter;

/* get a list of locale settings */
$lang2locale = get_list_of_locales();

/* use a fallback if i18n is disabled (default) */
if (!read_config_option('i18n_language_support') && read_config_option('i18n_language_support') != '') {
	i18n_debug('load_fallback_procedure(1)');
	load_fallback_procedure();

	return;
}

/* Repair legacy language support */
if (isset($_REQUEST['language'])) {
	$_REQUEST['language'] = repair_locale($_REQUEST['language']);
}

/* determine whether or not we can support the language */
$user_locale = '';

if (isset($_REQUEST['language']) && isset($lang2locale[$_REQUEST['language']])) {
	/* user requests another language */
	$user_locale = apply_locale($_REQUEST['language']);
	unset($_SESSION['sess_current_date1']);
	unset($_SESSION['sess_current_date2']);

	/* save customized language setting (authenticated users only) */
	set_user_setting('language', $user_locale);
} elseif (isset($_SESSION[SESS_USER_LANGUAGE]) && isset($lang2locale[$_SESSION[SESS_USER_LANGUAGE]])) {
	/* language definition stored in the SESSION */
	$user_locale = apply_locale($_SESSION[SESS_USER_LANGUAGE]);
} else {
	/* look up for user customized language setting stored in Cacti DB */
	$user_locale = apply_locale(read_user_i18n_setting('user_language'));
}

/* allow RRDtool to display i18n */
setlocale(LC_CTYPE, str_replace('-', '_', $user_locale) . '.UTF-8');

if ($user_locale !== false && $user_locale !== '') {
	$_SESSION[SESS_USER_LANGUAGE] = $user_locale;
}

/* define the path to the language file */
i18n_debug('search(1): ' . CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $cacti_locale . '.mo');
i18n_debug('search(2): ' . CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo');

if (file_exists(CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $cacti_locale . '.mo')) {
	$path2catalogue = CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $cacti_locale . '.mo';
} elseif (file_exists(CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo')) {
	$path2catalogue = CACTI_PATH_LOCALES . '/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo';
} else {
	$path2catalogue = '';
}

$catalogue = $path2catalogue;

/* define the path to the language file of the DHTML calendar */
if ($cacti_locale != '') {
	$lang_parts = explode('-', $cacti_locale);

	// Detect the calendar path
	if (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-datepicker-' . $cacti_locale . '.js')) {
		$path2calendar = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-datepicker-' . $cacti_locale . '.js';
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-datepicker-' . $lang_parts[0] . '.js')) {
		$path2calendar = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-datepicker-' . $lang_parts[0] . '.js';
	} else {
		$path2calendar = '';
	}

	// Detect the timepicker path
	if (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-timepicker-' . $cacti_locale . '.js')) {
		$path2timepicker = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-timepicker-' . $cacti_locale . '.js';
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-timepicker-' . $lang_parts[0] . '.js')) {
		$path2timepicker = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-ui-timepicker-' . $lang_parts[0] . '.js';
	} else {
		$path2timepicker = '';
	}

	// Detect the colorpicker path
	if (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery.ui.colorpicker-' . $cacti_locale . '.js')) {
		$path2colorpicker = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery.ui.colorpicker-' . $cacti_locale . '.js';
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery.ui.colorpicker-' . $lang_parts[0] . '.js')) {
		$path2colorpicker = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery.ui.colorpicker-' . $lang_parts[0] . '.js';
	} else {
		$path2colorpicker = '';
	}

	// Detect the multiselect path
	if (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-' . $cacti_locale . '.js')) {
		$path2ms       = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-' . $cacti_locale . '.js';
		$path2msfilter = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-filter-' . $cacti_locale . '.js';
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-' . $lang_parts[0] . '.js')) {
		$path2ms       = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-' . $lang_parts[0] . '.js';
		$path2msfilter = CACTI_PATH_INCLUDE . '/js/LC_MESSAGES/jquery-multiselect-filter-' . $lang_parts[0] . '.js';
	} else {
		$path2ms       = '';
		$path2msfilter = '';
	}
} else {
	$path2timepicker  = '';
	$path2calendar    = '';
	$path2ms          = '';
	$path2msfiler     = '';
	$path2colorpicker = '';
}

/* use fallback procedure if requested language is not available */
if (file_exists($path2catalogue)) {
	$cacti_textdomains['cacti']['path2catalogue'] = $path2catalogue;
} else {
	i18n_debug('load_fallback_procedure(2): ' . $path2catalogue);
	load_fallback_procedure();

	return;
}

/* search the correct textdomains for all plugins being installed */
$plugins = db_fetch_assoc('SELECT `directory`
	FROM `plugin_config`
	ORDER BY id');

if ($plugins && cacti_sizeof($plugins)) {
	foreach ($plugins as $plugin) {
		$plugin = $plugin['directory'];

		if (file_exists(CACTI_PATH_PLUGINS . '/' . $plugin . '/locales/LC_MESSAGES/' . $cacti_locale . '.mo')) {
			$path2catalogue = CACTI_PATH_PLUGINS . '/' . $plugin . '/locales/LC_MESSAGES/' . $cacti_locale . '.mo';
		} elseif (file_exists(CACTI_PATH_PLUGINS . '/' . $plugin . '/locales/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo')) {
			$path2catalogue = CACTI_PATH_PLUGINS . '/' . $plugin . '/locales/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo';
		}

		if (file_exists($path2catalogue)) {
			$cacti_textdomains[$plugin]['path2catalogue'] = $path2catalogue;
		}
	}

	/* if i18n support is set to strict mode then check if all plugins support the requested language */
	if (read_config_option('i18n_language_support') == 2) {
		if (cacti_sizeof($plugins) != (cacti_sizeof($cacti_textdomains) - 1)) {
			i18n_debug('load_fallback_procedure(3)');
			load_fallback_procedure();

			return;
		}
	}
}

i18n_debug('require(1)');

/* load php-gettext class if present */
$i18n = array();

// Is the handler defined in the db?
$i18n_handler = read_config_option('i18n_language_handler');

// Is the handler defined in the config but not the db?
if (empty($i18n_handler) && !empty($config['i18n_language_handler'])) {
	i18n_debug('Handler: not specified in settings');
	$i18n_handler = $config['i18n_language_handler'];
}

if (empty($i18n_handler)) {
	i18n_debug('Handler: not specified in config, autodetection is now in progress');

	if (file_exists(CACTI_PATH_INCLUDE . '/vendor/gettext/src/Translator.php') && version_compare(PHP_VERSION, '8.0', '<=')) {
		$i18n_handler = CACTI_LANGUAGE_HANDLER_OSCAROTERO;
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/vendor/phpgettext/streams.php')) {
		$i18n_handler = CACTI_LANGUAGE_HANDLER_PHPGETTEXT;
	} elseif (file_exists(CACTI_PATH_INCLUDE . '/vendor/motranslator/src/Translator.php')) {
		$i18n_handler = CACTI_LANGUAGE_HANDLER_MOTRANSLATOR;
	}
}

i18n_debug("require(1): Handler $i18n_handler");

switch ($i18n_handler) {
	case CACTI_LANGUAGE_HANDLER_OSCAROTERO:
		if (file_exists(CACTI_PATH_INCLUDE . '/vendor/gettext/src/autoloader.php')) {
			require_once(CACTI_PATH_INCLUDE . '/vendor/gettext/src/autoloader.php');
			require_once(CACTI_PATH_INCLUDE . '/vendor/cldr-to-gettext-plural-rules/src/autoloader.php');
		} else {
			$i18n_handler = CACTI_LANGUAGE_HANDLER_DEFAULT;
		}

		break;
	case CACTI_LANGUAGE_HANDLER_PHPGETTEXT:
		if (file_exists(CACTI_PATH_INCLUDE . '/vendor/phpgettext/streams.php')) {
			require_once(CACTI_PATH_INCLUDE . '/vendor/phpgettext/streams.php');
			require_once(CACTI_PATH_INCLUDE . '/vendor/phpgettext/gettext.php');
		} else {
			$i18n_handler = CACTI_LANGUAGE_HANDLER_DEFAULT;
		}

		break;
	case CACTI_LANGUAGE_HANDLER_MOTRANSLATOR:
		if (file_exists(CACTI_PATH_INCLUDE . '/vendor/motranslator/src/Translator.php')) {
			require_once(CACTI_PATH_INCLUDE . '/vendor/motranslator/src/Translator.php');
			require_once(CACTI_PATH_INCLUDE . '/vendor/motranslator/src/StringReader.php');
		} else {
			$i18n_handler = CACTI_LANGUAGE_HANDLER_DEFAULT;
		}

		break;

	default:
		$i18n_handler = CACTI_LANGUAGE_HANDLER_DEFAULT;

		break;
}

define('CACTI_LANGUAGE_HANDLER', $i18n_handler);

i18n_debug('require(2): Handler ' . CACTI_LANGUAGE_HANDLER);

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
			case CACTI_LANGUAGE_HANDLER_OSCAROTERO:
				$i18n[$domain] = load_gettext_oscarotero($domain);

				break;
		}

		if (empty($i18n[$domain])) {
			die('Invalid language support or corrupt/missing file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
		}
	}
	unset($input);
}

/* load standard wrappers */
define('CACTI_LOCALE', $cacti_locale);
define('CACTI_COUNTRY', $cacti_country);
define('CACTI_LANGUAGE', $lang2locale[CACTI_LOCALE]['language']);
define('CACTI_LANGUAGE_FILE', $catalogue);

function load_gettext_original($domain) {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_original($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	$input = new FileReader($cacti_textdomains[$domain]['path2catalogue']);

	if ($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	$i18n_domain = new gettext_reader($input);

	if ($i18n_domain == false) {
		die('Invalid language file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $i18n_domain;
}

function load_gettext_motranslator($domain) {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_mostranslator($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	$input = new PhpMyAdmin\MoTranslator\Translator($cacti_textdomains[$domain]['path2catalogue']);

	if ($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $input;
}

function load_gettext_oscarotero($domain) {
	global $cacti_textdomains;

	// Hide deprecation errors for PHP 8 if using this
	// Translator
	if (version_compare(PHP_VERSION, '8.0', '>=')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}

	i18n_debug("load_gettext_oscarotero($domain): " . $cacti_textdomains[$domain]['path2catalogue']);

	$input = Gettext\Translations::fromMoFile($cacti_textdomains[$domain]['path2catalogue']);

	if ($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	$i18n_domain = new Gettext\Translator();
	$i18n_domain->loadTranslations($input);

	if ($i18n_domain == false) {
		die('Invalid language file: ' . $cacti_textdomains[$domain]['path2catalogue'] . PHP_EOL);
	}

	return $i18n_domain;
}

function apply_locale($language) {
	global $cacti_locale, $cacti_country, $lang2locale;

	$locale_set = false;

	if ($language != '') {
		$language   = repair_locale($language);
		$locale_set = isset($lang2locale[$language]);
	}

	// If the users has not elected a language and autodetect is on
	if (!$locale_set && (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && (read_config_option('i18n_auto_detection') == '' || read_config_option('i18n_auto_detection') == '1'))) {
		/* detect browser settings if auto detection is enabled */
		$accepted = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$accepted = $accepted[0];

		$language   = repair_locale($accepted);
		$locale_set = isset($lang2locale[$language]);
	}

	if (!$locale_set) {
		$language = repair_locale(read_config_option('i18n_default_language'));

		if ($language == false || $language == '') {
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

/* best effort function to repair locale */
function repair_locale($language) {
	global $lang2locale;

	/* Repair legacy language support */
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
function __esc() {
	return htmlspecialchars(call_user_func_array('__', func_get_args()), ENT_QUOTES);
}

function __esc_n() {
	return htmlspecialchars(call_user_func_array('__n', func_get_args()), ENT_QUOTES);
}

function __esc_x() {
	return htmlspecialchars(call_user_func_array('__x', func_get_args()), ENT_QUOTES);
}

function __esc_xn() {
	return htmlspecialchars(call_user_func_array('__xn', func_get_args()), ENT_QUOTES);
}

/**
 * load_fallback_procedure - loads wrapper package if native language (English) has to be used
 *
 * @return
 */
function load_fallback_procedure() {
	global $cacti_textdomains, $cacti_locale, $cacti_country, $lang2locale;

	/* reset variables */
	$_SESSION[SESS_USER_LANGUAGE] = '';

	$cacti_textdomains = array();
	define('CACTI_LOCALE', 'en-US');
	define('CACTI_COUNTRY', 'us');
	define('CACTI_LANGUAGE', 'English');
	define('CACTI_LANGUAGE_FILE', 'english_usa');
	define('CACTI_LANGUAGE_HANDLER', CACTI_LANGUAGE_HANDLER_DEFAULT);
}

function __gettext($text, $domain = 'cacti') {
	global $i18n;

	// Assume translation fails or is not defined
	if (isset($i18n[$domain])) {
		switch (CACTI_LANGUAGE_HANDLER) {
			case CACTI_LANGUAGE_HANDLER_PHPGETTEXT:
				$translated = $i18n[$domain]->translate($text);

				break;
			case CACTI_LANGUAGE_HANDLER_OSCAROTERO:
			case CACTI_LANGUAGE_HANDLER_MOTRANSLATOR:
				$translated = $i18n[$domain]->gettext($text);

				break;
		}
	}

	if (!isset($translated)) {
		$translated = $text;
	} else {
		i18n_debug("__gettext($domain):\n	Original: $text\n	Translated: $translated", FILE_APPEND);
	}

	return __uf($translated);
}

function __n($singular, $plural, $number, $domain = 'cacti') {
	global $i18n;

	if (isset($i18n[$domain])) {
		return __uf($i18n[$domain]->ngettext($singular, $plural, $number));
	} else {
		return ($number == 1) ? __uf($singular) : __uf($plural);
	}
}

function __uf($text) {
	return str_replace('%%', '%', $text);
}

function __() {
	global $i18n;

	$args = func_get_args();
	$num  = func_num_args();

	/* this should not happen */
	if ($num < 1) {
		return false;
		/* convert pure text strings */
	}

	if ($num == 1) {
		return __gettext($args[0]);
		/* convert pure text strings by using a different textdomain */
	}

	if ($num == 2 && isset($i18n[(string) $args[1]]) && $args[1] != 'cacti') {
		return __gettext($args[0], $args[1]);
		/* convert stings including one or more placeholders */
	} else {
		/* only the last argument is allowed to initiate
		   the use of a different textdomain */

		/* get gettext string */
		if (isset($i18n[(string) $args[$num - 1]]) && $args[$num - 1] != 'cacti') {
			$args[0] = __gettext($args[0], $args[$num - 1]);
		} else {
			$args[0] = __gettext($args[0]);
		}

		$valid_args = array('%%', '%b', '%c', '%d', '%e', '%E', '%f', '%F', '%g', '%G', '%h', '%H', '%o', '%s', '%u', '%x', '%X');

		if (array_search($args[0], $valid_args, true) !== false) {
			/* process return string against input arguments */

			return __uf(call_user_func_array('sprintf', $args));
		} else {
			return $args[0];
		}
	}
}

function __xn($context, $singular, $plural, $number, $domain = 'cacti') {
	$xsingular = $context . chr(4) . $singular;
	$xplural   = $context . chr(4) . $plural;

	$msgstr = __n($xsingular, $xplural, $number, $domain);

	if ($number == 1) {
		return ($msgstr == $xsingular) ? __uf($singular) : __uf($msgstr);
	} else {
		return ($msgstr == $xplural) ? __uf($plural) : __uf($msgstr);
	}
}

function __x() {
	global $i18n;

	$args = func_get_args();
	$num  = func_num_args();

	/* this should never happen */
	if ($num < 2) {
		return false;
	} else {
		$context = array_shift($args);
		$num--;

		$msgid  = reset($args);
		$xmsgid = $context . chr(4) . $msgid;

		$args[0] = $xmsgid;

		if ($num == 1) {
			/* pure text string without placeholders and a change of the default textdomain */
			$msgstr = __gettext($args[0]);
		} else {
			/* get gettext string */
			$msgstr = isset($i18n[(string) $args[$num - 1]]) && $args[$num - 1] != 'cacti' ?
			__gettext($args[0], $args[$num - 1]) : __gettext($args[0]);
		}

		/* use the raw message id if language catalogue does not contain a context specific message string */
		$args[0] = ($msgstr == $xmsgid) ? $msgid : $msgstr;

		/* process return string against input arguments */
		return __uf(call_user_func_array('sprintf', $args));
	}
}

function __date($format, $timestamp = false, $domain = 'cacti') {
	global $i18n_date_placeholders;

	if (!$timestamp) {
		$timestamp = time();
	}

	/* placeholders will allow to fill in the translated weekdays, month and so on.. */
	$i18n_date_placeholders = array(
		'#1' => __(date('D', $timestamp), $domain),
		'#2' => __(date('M', $timestamp), $domain),
		'#3' => __(date('F', $timestamp), $domain),
		'#4' => __(date('l', $timestamp), $domain)
	);

	/* if defined exchange the format string for the configured locale */
	$format = __gettext($format, $domain);

	/* replace special date chars by placeholders */
	$format = str_replace(array('D', 'M', 'F', 'l'), array('#1', '#2', '#3', '#4'), $format);

	/* get date string included placeholders */
	$date = date($format, $timestamp);

	/* fill in specific translations */
	$date = str_replace(array_keys($i18n_date_placeholders), array_values($i18n_date_placeholders), $date);

	return __uf($date);
}

/**
 * get_list_of_locales - returns the default settings being used for i18n
 *
 * @return - a multi-dimensional array with the locale code as main key
 */
function get_list_of_locales() {
	$lang2locale = array(
		'sq-AL' => array('language' => 'Albanian',            'direction' => 'ltr', 'country' => 'al', 'filename' => 'albanian_albania'),
		'ar-SA' => array('language' => 'Arabic',              'direction' => 'rtl', 'country' => 'sa', 'filename' => 'arabic_saudi_arabia'),
		'hy-AM' => array('language' => 'Armenian',            'direction' => 'ltr', 'country' => 'am', 'filename' => 'armenian_armenia'),
		'be-BY' => array('language' => 'Belarusian',          'direction' => 'ltr', 'country' => 'by', 'filename' => 'belarusian_belarus'),
		'bg-BG' => array('language' => 'Bulgarian',           'direction' => 'ltr', 'country' => 'bg', 'filename' => 'bulgarian_bulgaria'),
		'zh-CN' => array('language' => 'Chinese (China)',     'direction' => 'ltr', 'country' => 'cn', 'filename' => 'chinese_china_simplified'),
		'zh-HK' => array('language' => 'Chinese (Hong Kong)', 'direction' => 'ltr', 'country' => 'hk', 'filename' => 'chinese_hong_kong'),
		'zh-SG' => array('language' => 'Chinese (Singapore)', 'direction' => 'ltr', 'country' => 'sg', 'filename' => 'chinese_singapore'),
		'zh-TW' => array('language' => 'Chinese (Taiwan)',    'direction' => 'ltr', 'country' => 'tw', 'filename' => 'chinese_taiwan'),
		'hr-HR' => array('language' => 'Croatian',            'direction' => 'ltr', 'country' => 'hr', 'filename' => 'croatian_croatia'),
		'cs-GZ' => array('language' => 'Czech',               'direction' => 'ltr', 'country' => 'cz', 'filename' => 'czech_czech_republic'),
		'da-DK' => array('language' => 'Danish',              'direction' => 'ltr', 'country' => 'dk', 'filename' => 'danish_denmark'),
		'nl-NL' => array('language' => 'Dutch',               'direction' => 'ltr', 'country' => 'nl', 'filename' => 'dutch_netherlands'),
		'en-US' => array('language' => 'English',             'direction' => 'ltr', 'country' => 'us', 'filename' => 'english_usa'),
		'en-GB' => array('language' => 'English (Britain)',   'direction' => 'ltr', 'country' => 'gb', 'filename' => 'english_gb'),
		'et-EE' => array('language' => 'Estonian',            'direction' => 'ltr', 'country' => 'ee', 'filename' => 'estonian_estonia'),
		'fi-FI' => array('language' => 'Finnish',             'direction' => 'ltr', 'country' => 'fi', 'filename' => 'finnish_finland'),
		'fr-FR' => array('language' => 'French',              'direction' => 'ltr', 'country' => 'fr', 'filename' => 'french_france'),
		'de-DE' => array('language' => 'German',              'direction' => 'ltr', 'country' => 'de', 'filename' => 'german_germany'),
		'el-GR' => array('language' => 'Greek',               'direction' => 'ltr', 'country' => 'gr', 'filename' => 'greek_greece'),
		'he-IL' => array('language' => 'Hebrew',              'direction' => 'rtl', 'country' => 'il', 'filename' => 'hebrew_israel'),
		'hi-IN' => array('language' => 'Hindi',               'direction' => 'ltr', 'country' => 'in', 'filename' => 'hindi_india'),
		'hu-HU' => array('language' => 'Hungarian',           'direction' => 'ltr', 'country' => 'hu', 'filename' => 'hungarian_hungary'),
		'is-IS' => array('language' => 'Icelandic',           'direction' => 'ltr', 'country' => 'is', 'filename' => 'icelandic_iceland'),
		'id-ID' => array('language' => 'Indonesian',          'direction' => 'ltr', 'country' => 'id', 'filename' => 'indonesian_indonesia'),
		'ga-IE' => array('language' => 'Irish',               'direction' => 'ltr', 'country' => 'ie', 'filename' => 'irish_ireland'),
		'it-IT' => array('language' => 'Italian',             'direction' => 'ltr', 'country' => 'it', 'filename' => 'italian_italy'),
		'ja-JP' => array('language' => 'Japanese',            'direction' => 'ltr', 'country' => 'jp', 'filename' => 'japanese_japan'),
		'ko-KR' => array('language' => 'Korean',              'direction' => 'ltr', 'country' => 'kr', 'filename' => 'korean_korea'),
		'lv-LV' => array('language' => 'Latvian',             'direction' => 'ltr', 'country' => 'lv', 'filename' => 'latvian_latvia'),
		'lt-LT' => array('language' => 'Lithuanian',          'direction' => 'ltr', 'country' => 'lt', 'filename' => 'lithuanian_lithuania'),
		'mk-MK' => array('language' => 'Macedonian',          'direction' => 'ltr', 'country' => 'mk', 'filename' => 'macedonian_macedonia'),
		'ms-MY' => array('language' => 'Malay',               'direction' => 'ltr', 'country' => 'my', 'filename' => 'malay_malaysia'),
		'mt-LT' => array('language' => 'Maltese',             'direction' => 'ltr', 'country' => 'lt', 'filename' => 'maltese_malta'),
		'no-NO' => array('language' => 'Norwegian',           'direction' => 'ltr', 'country' => 'no', 'filename' => 'norwegian_norway'),
		'pl-PL' => array('language' => 'Polish',              'direction' => 'ltr', 'country' => 'pl', 'filename' => 'polish_poland'),
		'pt-PT' => array('language' => 'Portuguese',          'direction' => 'ltr', 'country' => 'pt', 'filename' => 'portuguese_portugal'),
		'pt-BR' => array('language' => 'Portuguese (Brazil)', 'direction' => 'ltr', 'country' => 'br', 'filename' => 'portuguese_brazil'),
		'ro-RO' => array('language' => 'Romanian',            'direction' => 'ltr', 'country' => 'ro', 'filename' => 'romanian_romania'),
		'ru-RU' => array('language' => 'Russian',             'direction' => 'ltr', 'country' => 'ru', 'filename' => 'russian_russia'),
		'sr-RS' => array('language' => 'Serbian',             'direction' => 'ltr', 'country' => 'rs', 'filename' => 'serbian_serbia'),
		'sk-SK' => array('language' => 'Slovak',              'direction' => 'ltr', 'country' => 'sk', 'filename' => 'slovak_slovakia'),
		'sl-SI' => array('language' => 'Slovenian',           'direction' => 'ltr', 'country' => 'si', 'filename' => 'slovenian_slovenia'),
		'es-ES' => array('language' => 'Spanish',             'direction' => 'ltr', 'country' => 'es', 'filename' => 'spanish_spain'),
		'sv-SE' => array('language' => 'Swedish',             'direction' => 'ltr', 'country' => 'se', 'filename' => 'swedish_sweden'),
		'th-TH' => array('language' => 'Thai',                'direction' => 'ltr', 'country' => 'th', 'filename' => 'thai_thailand'),
		'tr-TR' => array('language' => 'Turkish',             'direction' => 'ltr', 'country' => 'tr', 'filename' => 'turkish_turkey'),
		'uk-UA' => array('language' => 'Ukrainian',           'direction' => 'ltr', 'country' => 'ua', 'filename' => 'ukrainian_ukraine'),
		'vi-VN' => array('language' => 'Vietnamese',          'direction' => 'ltr', 'country' => 'vn', 'filename' => 'vietnamese_vietnam')
	);

	return $lang2locale;
}

/**
 * get_installed_locales - finds all installed locales
 *
 * @return - an associative array of all installed locales (e.g. 'en' => 'English')
 */
function get_installed_locales() {
	global $config, $lang2locale;

	$locations                    = array();
	$supported_languages['en-US'] = $lang2locale['en-US']['language'];

	foreach ($lang2locale as $locale => $properties) {
		$locations[$properties['filename'] . '.mo'] = array(
			'locale'   => $locale,
			'language' => $properties['language']
		);
		$locations[$locale . '.mo'] = array(
			'locale'   => $locale,
			'language' => $properties['language']
		);
	}

	/* create a list of all languages this Cacti system supports ... */
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

/* read_user_i18n_setting - finds the current value of a i18n configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings_user array
	 in 'include/global_settings.php'
   @returns - the current value of the i18n configuration option or the system default value */
function read_user_i18n_setting($config_name) {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */
	if (isset($_SESSION[SESS_USER_ID])) {
		$effective_uid = $_SESSION[SESS_USER_ID];
	} elseif ((read_config_option('auth_method') == AUTH_METHOD_NONE)) {
		if (isset($_SESSION[OPTIONS_WEB])) {
			$config_array = $_SESSION[OPTIONS_WEB];
		} elseif (isset($config[OPTIONS_CLI])) {
			$config_array = $config[OPTIONS_CLI];
		}

		if (!isset($config_array[$config_name])) {
			$effective_uid = db_fetch_cell_prepared('SELECT id
				FROM user_auth
				WHERE id = ?',
				array(get_guest_account()));
		}

		if ($effective_uid == '') {
			$effective_uid = 0;
		}
	} else {
		$effective_uid = 0;
	}

	if (db_table_exists('settings_user')) {
		$db_setting = db_fetch_row_prepared('SELECT value
			FROM settings_user
			WHERE name = ?
			AND user_id = ?',
			array($config_name, $effective_uid));
	}

	if (isset($db_setting['value'])) {
		return $db_setting['value'];
	} else {
		return false;
	}
}

/**
 * number_format_i18n - local specific number format wrapper
 *
 * @return - formatted number in the correct locale
 * @param mixed $number
 * @param null|mixed $decimals
 * @param mixed $baseu
 */
function number_format_i18n($number, $decimals = null, $baseu = 1024) {
	global $cacti_locale, $cacti_country;

	$country = strtoupper($cacti_country);

	if (function_exists('numfmt_create')) {
		$fmt_key = $cacti_locale . '_'. $country;
		$fmt     = numfmt_create($fmt_key, NumberFormatter::DECIMAL);

		if ($decimals == null) {
			$decimals = 0;
		}

		if ($fmt !== false && $fmt !== null) {
			numfmt_set_attribute($fmt, NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

			if ($number !== null) {
				return numfmt_format($fmt, $number);
			} else {
				return $number;
			}
		}
		cacti_log('DEBUG: Number format \'' . $fmt_key .'\' was unavailable, using older methods',false,'i18n',POLLER_VERBOSITY_HIGH);
	}

	$origlocales = explode(';', setlocale(LC_ALL, 0));
	setlocale(LC_ALL, $cacti_locale);
	$locale = localeconv();

	if (!isset($locale['decimal_point']) || $locale['decimal_point'] == '') {
		$locale['decimal_point'] = '.';
	}

	if (!isset($locale['thousands_sep']) || $locale['thousands_sep'] == '') {
		$locale['thousands_sep'] = ',';
	}

	if ($number === null) {
		$number = '';
	} elseif ($decimals == -1 || $decimals === null) {
		$number = number_format($number, 0, $locale['decimal_point'], $locale['thousands_sep']);
	} elseif ($number >= pow($baseu, 4)) {
		$number = number_format($number / pow($baseu, 4), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' T');
	} elseif ($number >= pow($baseu, 3)) {
		$number = number_format($number / pow($baseu, 3), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' G');
	} elseif ($number >= pow($baseu, 2)) {
		$number = number_format($number / pow($baseu, 2), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' M');
	} elseif ($number >= $baseu) {
		$number = number_format($number / $baseu, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' K');
	} else {
		$number = number_format($number, $decimals, $locale['decimal_point'], $locale['thousands_sep']);
	}

	foreach ($origlocales as $locale_setting) {
		if (strpos($locale_setting, '=') !== false) {
			list($category, $locale) = explode('=', $locale_setting);
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

function get_new_user_default_language() {
	$accepted = repair_locale(read_config_option('i18n_default_language'));

	if ($accepted == '') {
		$accepted = repair_locale(read_default_config_option('i18n_default_language'));
	}

	return $accepted;
}

function i18n_debug($text, $mode = FILE_APPEND, $eol = PHP_EOL) {
	if (is_dir('/share/') && is_writeable('/share/i18n.log')) {
		file_put_contents('/share/i18n.log', $text . $eol, $mode);
	} elseif (file_exists('/tmp/i18n.log') && is_writeable('/tmp/i18n.log')) {
		file_put_contents('/tmp/i18n.log', $text . $eol, $mode);
	}
}
