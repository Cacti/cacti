<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* default localization of Cacti */
$cacti_locale = 'en';
$cacti_country = 'us';

/* an array that will contains all textdomains being in use. */
$cacti_textdomains = array();

/* get a list of locale settings */
$lang2locale = get_list_of_locales();

/* use a fallback if i18n is disabled (default) */
if (!read_config_option('i18n_language_support') && read_config_option('i18n_language_support') != '')
{
	load_fallback_procedure();
	return;
}



/* determine whether or not we can support the language */
if (isset($_REQUEST['language']) && isset($lang2locale[$_REQUEST['language']]))
/* user requests another language */
{
	$cacti_locale  = $_REQUEST['language'];
	$cacti_country = $lang2locale[$_REQUEST['language']]['country'];
	$_SESSION['sess_user_language'] = $cacti_locale;
	unset($_SESSION['sess_current_date1']);
	unset($_SESSION['sess_current_date2']);

	/* save customized language setting (authenticated users only) */
	set_user_config_option('language', $cacti_locale);

}
/* language definition stored in the SESSION */
elseif (isset($_SESSION['sess_user_language']) && isset($lang2locale[$_SESSION['sess_user_language']]))
{
	$cacti_locale = $_SESSION['sess_user_language'];
	$cacti_country = $lang2locale[$_SESSION['sess_user_language']]['country'];

}
elseif ($user_locale = read_user_i18n_setting('user_language'))
/* look up for user customized language setting stored in Cacti DB */
{
	if (isset($lang2locale[$user_locale]))
	{
		$cacti_locale = $user_locale;
		$cacti_country = $lang2locale[$cacti_locale]['country'];
		$_SESSION['sess_user_language'] = $cacti_locale;
	}
}
elseif ( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && ( read_config_option('i18n_auto_detection') | read_config_option('i18n_auto_detection') == '' ) )
/* detect browser settings if auto detection is enabled */
{
	$accepted = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	$accepted = strtolower(str_replace(strstr($accepted, ','), '', $accepted));

	$accepted = (isset($lang2locale[$accepted])) ? $accepted : str_replace(strstr($accepted, '-'), '', $accepted);

	if (isset($lang2locale[$accepted]))
	{
		$cacti_locale = $accepted;
		$cacti_country = $lang2locale[$accepted]['country'];
	}

}
else
/* use the default language defined under 'general' */
{
	$accepted = read_config_option('i18n_default_language');
	if ($accepted == '')
	{
		$accepted = read_default_config_option('i18n_default_language');
	}

	if (isset($lang2locale[$accepted]))
	{
		$cacti_locale = $accepted;
		$cacti_country = $lang2locale[$accepted]['country'];
	}
}

/* define the path to the language file */
$path2catalogue = $config['base_path'] . '/locales/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo';

/* define the path to the language file of the DHTML calendar */
$path2calendar = $config['include_path'] . '/js/LC_MESSAGES/jquery.ui.datepicker-' . $lang2locale[$cacti_locale]['filename'] . '.js';

/* use fallback procedure if requested language is not available */
if (file_exists($path2catalogue) & file_exists($path2calendar))
{
	$cacti_textdomains['cacti']['path2locales'] = $config['base_path'] . '/locales';
	$cacti_textdomains['cacti']['path2catalogue'] = $path2catalogue;
}
else
{
	load_fallback_procedure();
	return;
}

/* search the correct textdomains for all plugins being installed */
$plugins = db_fetch_assoc('SELECT `directory` FROM `plugin_config` ORDER BY id');
if ($plugins && sizeof($plugins) > 0)
{
	foreach($plugins as $plugin)
	{
		$plugin = $plugin['directory'];
		$path2catalogue =  $config['base_path'] . '/plugins/' . $plugin . '/locales/LC_MESSAGES/' . $lang2locale[$cacti_locale]['filename'] . '.mo';

		if (file_exists($path2catalogue))
		{
			$cacti_textdomains[$plugin]['path2locales'] = $config['base_path'] . '/plugins/' . $plugin . '/locales';
			$cacti_textdomains[$plugin]['path2catalogue'] = $path2catalogue;
		}
	}

	/* if i18n support is set to strict mode then check if all plugins support the requested language */
	if (read_config_option('i18n_language_support') == 2)
	{
		if(sizeof($plugins) != (sizeof($cacti_textdomains) - 1))
		{
			load_fallback_procedure();
			return;
		}
	}
}

/* load php-gettext class */
require($config['include_path'] . '/phpgettext/streams.php');
require($config['include_path'] . '/phpgettext/gettext.php');

/* prefetch all language files to work in memory only,
   die if one of the language files is corrupted */
$l10n = array();

foreach($cacti_textdomains as $domain => $paths) {
	$input = new FileReader($cacti_textdomains[$domain]['path2catalogue']);
	if($input == false) {
		die('Unable to read file: ' . $cacti_textdomains[$domain]['path2catalogue']);
	}

	$l10n[$domain] = new gettext_reader($input);
	if($l10n[$domain] == false) {
		die('Invalid language file: ' . $cacti_textdomains[$domain]['path2catalogue']);
	}
}
unset($input);

/* load standard wrappers */
load_i18n_gettext_wrappers();

define('CACTI_LOCALE', $cacti_locale);
define('CACTI_COUNTRY', $cacti_country);
define('CACTI_LANGUAGE', $lang2locale[CACTI_LOCALE]['language']);
define('CACTI_LANGUAGE_FILE', $lang2locale[CACTI_LOCALE]['filename']);

/**
 * Universal escaping wrappers
 */
function __esc() {
	return htmlspecialchars( call_user_func_array('__', func_get_args()), ENT_QUOTES);
}

function __esc_n() {
	return htmlspecialchars( call_user_func_array('__n', func_get_args()), ENT_QUOTES);
}

function __esc_x() {
	return htmlspecialchars( call_user_func_array('__x', func_get_args()), ENT_QUOTES);
}

function __esc_xn() {
	return htmlspecialchars( call_user_func_array('__xn', func_get_args()), ENT_QUOTES);
}

/**
 * load_fallback_procedure - loads wrapper package if native language (English) has to be used
 *
 * @return
 */
function load_fallback_procedure(){
	global $cacti_textdomains, $cacti_locale, $cacti_country, $lang2locale;

	/* load wrappers if native gettext is not available */
	load_i18n_fallback_wrappers();

	/* reset variables */
	$_SESSION['sess_user_language'] = '';

	$cacti_textdomains = array();
	define('CACTI_LOCALE', 'en');
	define('CACTI_COUNTRY', 'us');
	define('CACTI_LANGUAGE', 'English');
	define('CACTI_LANGUAGE_FILE', 'english_usa');

}



/**
 * load_i18n_gettext_wrappers - creates all wrappers to translate strings by using php-gettext
 *
 * @return
 */
function load_i18n_gettext_wrappers(){

	function __gettext($text, $domain = 'cacti') {
		global $l10n;
		if (isset($l10n[$domain])) {
			return $l10n[$domain]->translate($text);
		}else {
			return $text;
		}
	}

	function __n($singular, $plural, $number, $domain = 'cacti'){
		global $l10n;
		if (isset($l10n[$domain])) {
    		return $l10n[$domain]->ngettext($singular, $plural, $number);
    	}else {
			return ($number == 1) ? $singular : $plural;
		}
	}


	function __() {
		global $l10n;

		$args = func_get_args();
		$num  = func_num_args();

		/* this should not happen */
		if ($num < 1) {
			return false;

		/* convert pure text strings */
		} elseif ($num == 1) {
			return __gettext($args[0]);

		/* convert pure text strings by using a different textdomain */
		} elseif ($num == 2 && isset($l10n[$args[1]])) {
			return __gettext($args[0], $args[1]);

		/* convert stings including one or more placeholders */
		}else {

			/* only the last argument is allowed to initiate
			the use of a different textdomain */

			/* get gettext string */
			$args[0] = isset($l10n[$args[$num-1]]) 	? __gettext($args[0], $args[$num-1])
													: __gettext($args[0]);

			/* process return string against input arguments */
			return call_user_func_array('sprintf', $args);
		}
	}

	function __xn($context, $singular, $plural, $number, $domain = 'cacti'){
		$xsingular = $context . chr(4) . $singular;
		$xplural = $context . chr(4) . $plural;

		$msgstr = __n($xsingular, $xplural, $number, $domain);
		if($number == 1 ) {
			return ( $msgstr == $xsingular ) ? $singular : $msgstr;
		}else {
			return ( $msgstr == $xplural ) ? $plural : $msgstr;
		}
	}

	function __x(){
		global $l10n;

		$args = func_get_args();
		$num  = func_num_args();

		/* this should never happen */
		if ($num < 2) {
			return false;
		}else {
			$context = array_shift($args);
			$num--;

			$msgid = reset($args);
			$xmsgid = $context . chr(4) . $msgid;

			$args[0] = $xmsgid;

			if($num == 1) {
				/* pure text string without placeholders and a change of the default textdomain */
				$msgstr = __gettext($args[0]);
			}else {
				/* get gettext string */
				$msgstr = isset($l10n[$args[$num-1]]) 	? __gettext($args[0], $args[$num-1])
														: __gettext($args[0]);
			}

			/* use the raw message id if language catalogue does not contain a context specific message string */
			$args[0] = ( $msgstr == $xmsgid ) ? $msgid : $msgstr;

			/* process return string against input arguments */
			return call_user_func_array('sprintf', $args);
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

		return $date;
	}

}



/**
 * load_i18n_fallback_wrappers - creates special wrappers to leave the native language untouched
 *
 * @return
 */
function load_i18n_fallback_wrappers(){

	function __gettext($text, $domain = 'cacti') {
		return $text;
	}

	function __n($singular, $plural, $number, $domain = 'cacti') {
		return ($number == 1) ? $singular : $plural;
	}

	function __() {

		$args = func_get_args();
		$num  = func_num_args();

		/* this should not happen */
		if ($num < 1) {
			return false;

		/* convert pure text strings */
		} elseif ($num == 1) {
			return $args[0];

		/* convert pure text strings by using a different textdomain */
		} elseif ($num == 2 && isset($l10n[$args[1]])) {
			return $args[0];

		/* convert stings including one or more placeholders */
		}else {

			/* only the last argument is allowed to initiate
			the use of a different textdomain */

			/* process return string against input arguments */
			return call_user_func_array('sprintf', $args);
		}
	}

	function __xn($context, $singular, $plural, $number, $domain = 'cacti'){
		return __n($singular, $plural, $number, $domain);
	}

	function __x(){
		$args = func_get_args();
		$num  = func_num_args();

		/* this should never happen */
		if ($num < 2) {
			return false;
		}else {
			$context = array_shift($args);
			return call_user_func_array('__', $args);
		}
	}

	function __date($format, $timestamp = false, $domain = 'cacti') {
		if (!$timestamp) {$timestamp = time();}
		return date($format, $timestamp);
	}
}

/**
 * get_list_of_locales - returns the default settings being used for l10n
 *
 * @return - a multi-dimensional array with the locale code as main key
 */
function get_list_of_locales () {
	$lang2locale = array(
		'sq'	=> array('language' => 'Albanian',            'country' => 'al', 'filename' => 'albanian_albania'),
		'ar'	=> array('language' => 'Arabic',              'country' => 'sa', 'filename' => 'arabic_saudi_arabia'),
		'hy'	=> array('language' => 'Armenian',            'country' => 'am', 'filename' => 'armenian_armenia'),
		'be'	=> array('language' => 'Belarusian',          'country' => 'by', 'filename' => 'belarusian_belarus'),
		'bg'	=> array('language' => 'Bulgarian',           'country' => 'bg', 'filename' => 'bulgarian_bulgaria'),
		'zh'	=> array('language' => 'Chinese',             'country' => 'cn', 'filename' => 'chinese_china'),
		'zh-cn' => array('language' => 'Chinese (China)',     'country' => 'cn', 'filename' => 'chinese_china_simplified'),
		'zh-hk' => array('language' => 'Chinese (Hong Kong)', 'country' => 'hk', 'filename' => 'chinese_hong_kong'),
		'zh-sg' => array('language' => 'Chinese (Singapore)', 'country' => 'sg', 'filename' => 'chinese_singapore'),
		'zh-tw' => array('language' => 'Chinese (Taiwan)',    'country' => 'tw', 'filename' => 'chinese_taiwan'),
		'hr'	=> array('language' => 'Croatian',            'country' => 'hr', 'filename' => 'croatian_croatia'),
		'cs'	=> array('language' => 'Czech',               'country' => 'cz', 'filename' => 'czech_czech_republic'),
		'da'	=> array('language' => 'Danish',              'country' => 'dk', 'filename' => 'danish_denmark'),
		'nl'	=> array('language' => 'Dutch',               'country' => 'nl', 'filename' => 'dutch_netherlands'),
		'en'	=> array('language' => 'English',             'country' => 'us', 'filename' => 'english_usa'),
		'et'	=> array('language' => 'Estonian',            'country' => 'ee', 'filename' => 'estonian_estonia'),
		'fi'	=> array('language' => 'Finnish',             'country' => 'fi', 'filename' => 'finnish_finland'),
		'fr'	=> array('language' => 'French',              'country' => 'fr', 'filename' => 'french_france'),
		'de'	=> array('language' => 'German',              'country' => 'de', 'filename' => 'german_germany'),
		'el'	=> array('language' => 'Greek',               'country' => 'gr', 'filename' => 'greek_greece'),
		'iw'	=> array('language' => 'Hebrew',              'country' => 'il', 'filename' => 'hebrew_israel'),
		'hi'	=> array('language' => 'Hindi',               'country' => 'in', 'filename' => 'hindi_india'),
		'hu'	=> array('language' => 'Hungarian',           'country' => 'hu', 'filename' => 'hungarian_hungary'),
		'is'	=> array('language' => 'Icelandic',           'country' => 'is', 'filename' => 'icelandic_iceland'),
		'id'	=> array('language' => 'Indonesian',          'country' => 'id', 'filename' => 'indonesian_indonesia'),
		'ga'	=> array('language' => 'Irish',               'country' => 'ie', 'filename' => 'irish_ireland'),
		'it'	=> array('language' => 'Italian',             'country' => 'it', 'filename' => 'italian_italy'),
		'ja'	=> array('language' => 'Japanese',            'country' => 'jp', 'filename' => 'japanese_japan'),
		'ko'	=> array('language' => 'Korean',              'country' => 'kr', 'filename' => 'korean_korea'),
		'lv'	=> array('language' => 'Lativan',             'country' => 'lv', 'filename' => 'latvian_latvia'),
		'lt'	=> array('language' => 'Lithuanian',          'country' => 'lt', 'filename' => 'lithuanian_lithuania'),
		'mk'	=> array('language' => 'Macedonian',          'country' => 'mk', 'filename' => 'macedonian_macedonia'),
		'ms'	=> array('language' => 'Malay',               'country' => 'my', 'filename' => 'malay_malaysia'),
		'mt'	=> array('language' => 'Maltese',             'country' => 'lt', 'filename' => 'maltese_malta'),
		'no'	=> array('language' => 'Norwegian',           'country' => 'no', 'filename' => 'norwegian_norway'),
		'pl'	=> array('language' => 'Polish',              'country' => 'pl', 'filename' => 'polish_poland'),
		'pt'	=> array('language' => 'Portuguese',          'country' => 'pt', 'filename' => 'portuguese_portugal'),
		'pt-br' => array('language' => 'Portuguese (Brazil)', 'country' => 'br', 'filename' => 'portuguese_brazil'),
		'ro'	=> array('language' => 'Romanian',            'country' => 'ro', 'filename' => 'romanian_romania'),
		'ru'	=> array('language' => 'Russian',             'country' => 'ru', 'filename' => 'russian_russia'),
		'sr'	=> array('language' => 'Serbian',             'country' => 'rs', 'filename' => 'serbian_serbia'),
		'sk'	=> array('language' => 'Slovak',              'country' => 'sk', 'filename' => 'slovak_slovakia'),
		'sl'	=> array('language' => 'Slovenian',           'country' => 'si', 'filename' => 'slovenian_slovenia'),
		'es'	=> array('language' => 'Spanish',             'country' => 'es', 'filename' => 'spanish_spain'),
		'sv'	=> array('language' => 'Swedish',             'country' => 'se', 'filename' => 'swedish_sweden'),
		'th'	=> array('language' => 'Thai',                'country' => 'th', 'filename' => 'thai_thailand'),
		'tr'	=> array('language' => 'Turkish',             'country' => 'tr', 'filename' => 'turkish_turkey'),
		'vi'	=> array('language' => 'Vietnamese',          'country' => 'vn', 'filename' => 'vietnamese_vietnam')
	);

	return $lang2locale;
}

/**
 * get_installed_locales - finds all installed locales
 *
 * @return - an associative array of all installed locales (e.g. 'en' => 'English')
 */
function get_installed_locales(){
	global $config, $lang2locale;

	$locations = array();
	$supported_languages['en'] = $lang2locale['en']['language'];
	foreach($lang2locale as $locale => $properties) {
		$locations[$properties['filename'] . '.mo'] = array('locale' => $locale, 'language' => $properties['language']);
	}

	/* create a list of all languages this Cacti system supports ... */
	$dhandle = opendir($config['base_path'] . '/locales/LC_MESSAGES');
	if(is_resource($dhandle)) {
		while (false !== ($filename = readdir($dhandle))) {
			/* check if language file for DHTML calendar is also available */
			$path2calendar = $config['include_path'] . '/js/LC_MESSAGES/jquery.ui.datepicker-' . str_replace('.mo', '.js', $filename);
			if(isset($locations[$filename]) & file_exists($path2calendar)) {
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
	if (isset($_SESSION['sess_user_id'])) {
		$effective_uid = $_SESSION['sess_user_id'];
	}else if ((read_config_option('auth_method') == 0)) {
		if (isset($_SESSION['sess_config_array'])) {
			$config_array = $_SESSION['sess_config_array'];
		}else if (isset($config['config_options_array'])) {
			$config_array = $config['config_options_array'];
		}
		if (!isset($config_array[$config_name])) {
			$effective_uid = db_fetch_cell("SELECT user_auth.id
				FROM settings
				INNER JOIN user_auth
				ON user_auth.username = settings.value
				WHERE settings.name = 'guest_user'");
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
 * @return - formatted numer in the correct locale
 */
function number_format_i18n($number, $decimals = 0, $baseu = 1024) {
	global $cacti_locale, $cacti_country;

	$country = strtoupper($cacti_country);

	if (function_exists('numfmt_create')) {
		$fmt = numfmt_create($cacti_locale . '_' . $country, NumberFormatter::DECIMAL);
		numfmt_set_attribute($fmt, NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

		return numfmt_format($fmt, $number);
	} else {
		$origlocales = explode(';', setlocale(LC_ALL, 0));
		setlocale(LC_ALL, $cacti_locale . '_' . $country);
		$locale = localeconv();

		if ($decimals == -1) {
			$number =  number_format($number, $decimals, $locale['decimal_point'], $locale['thousands_sep']);
		} elseif ($number>=pow($baseu, 4)) {
			$number =  number_format($number/pow($baseu, 4), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' T');
		} elseif($number>=pow($baseu, 3)) {
			$number = number_format($number/pow($baseu, 3), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' G');
		} elseif($number>=pow($baseu, 2)) {
			$number = number_format($number/pow($baseu, 2), $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' M');
		} elseif($number>=$baseu) {
			$number = number_format($number/$baseu, $decimals, $locale['decimal_point'], $locale['thousands_sep']) . __(' K');
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
}

