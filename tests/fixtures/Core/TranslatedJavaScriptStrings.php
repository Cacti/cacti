<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\TranslatedJavaScript;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
if (!getenv('CACTI_COVERAGE_FILE')) {
	require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
}

$root = dirname(__DIR__, 3);
$GLOBALS['config'] = array(
	'base_path'            => $root,
	'include_path'         => $root . '/include',
	'is_web'               => false,
	'config_options_array' => array(
		'i18n_language_support' => '0',
		'storage_location'      => 0,
	),
);

require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';
require_once $root . '/include/global_languages.php';
require_once $root . '/lib/rrd.php';

function __($message) {
	return "Détails d'élément \"réseau\" & chemin \\test\n</script><b>texte</b>";
}

function __esc($message) {
	return htmlspecialchars(__($message), ENT_QUOTES, 'UTF-8');
}

function html_escape($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_locale_direction($locale = null) {
	return \get_locale_direction($locale);
}

function get_filter_request_var($name) {
	return 42;
}

$cases = array(
	array('aggregate_graphs.php', 'Hide Item Details'),
	array('aggregate_graphs.php', 'Show Item Details'),
	array('cdef.php', 'Delete CDEF Item'),
	array('data_source_profiles.php', 'Delete Data Source Profile Item'),
	array('host_templates.php', 'Delete Item from Device Template'),
	array('package_import.php', 'Key Generation Required to Use Plugin'),
	array('package_import.php', 'Package Key Information Not Found'),
	array('package_import.php', 'File Differences for: '),
	array('aggregate_graphs.php', 'Total'),
	array('aggregate_graphs.php', 'All Items'),
	array('aggregate_graphs.php', 'Show'),
	array('data_source_profiles.php', 'Select Consolidation Function(s)'),
	array('aggregate_templates.php', 'Total'),
	array('aggregate_templates.php', 'All Items'),
	array('automation_networks.php', 'Select the days(s) of the week'),
	array('automation_networks.php', 'Select the month(s) of the year'),
	array('automation_networks.php', 'Select the day(s) of the month'),
	array('automation_networks.php', 'Select the week(s) of the month'),
	array('automation_networks.php', 'Select the day(s) of the week'),
	array('automation_networks.php', 'every X Weeks'),
	array('automation_networks.php', 'every X.'),
	array('automation_networks.php', 'every X Days'),
	array('automation_snmp.php', 'Delete SNMP Option Item'),
	array('automation_tree_rules.php', 'Yes'),
	array('automation_tree_rules.php', 'No'),
	array('automation_tree_rules.php', 'Change Leaf Type'),
	array('automation_tree_rules.php', 'WARNING:'),
	array('automation_tree_rules.php', 'You are changing the leaf type to "Device" which does not support Graph-based object matching/creation.'),
	array('automation_tree_rules.php', 'By changing the leaf type, all invalid rules will be automatically removed and will not be recoverable.'),
	array('automation_tree_rules.php', 'Are you sure you wish to continue?'),
	array('color_templates.php', 'Delete Color Item'),
	array('data_input.php', 'Delete Data Input Field'),
	array('data_input.php', 'Update Whitelist'),
	array('data_queries.php', 'Delete Associated Graph'),
	array('data_templates.php', 'Custom Data Warning Message'),
	array('graph_realtime.php', ' seconds left.'),
	array('graph_view.php', 'Add Selected Graphs to Report'),
	array('graph_view.php', 'Cancel'),
	array('graph_view.php', 'Ok'),
	array('graphs.php', 'Total'),
	array('graphs.php', 'All Items'),
	array('graphs.php', 'Cancel'),
	array('graphs_new.php', 'Filter Settings Saved'),
	array('host.php', 'None'),
	array('host.php', 'Ping'),
	array('host.php', 'Ping and SNMP Uptime'),
	array('host.php', 'SNMP Uptime'),
	array('host.php', 'Ping or SNMP Uptime'),
	array('host.php', 'SNMP Desc'),
	array('host.php', 'SNMP GetNext'),
	array('host.php', 'Delete Item from Device'),
	array('lib/html_reports.php', 'None'),
	array('lib/html_tree.php', 'Login to Cacti'),
	array('plugins.php', 'Cancel'),
	array('plugins.php', 'Uninstall'),
	array('settings.php', 'Changing Permission Model Warning'),
	array('settings.php', 'Test Email Results'),
	array('tree.php', 'Alphabetic'),
);

$executed = 0;

foreach ($cases as list($name, $message)) {
	$file = $root . '/' . $name;
	$source = file_get_contents($file);
	$pattern = '/<\?php(?:(?!\?>)[^\r\n])*(?:cacti_js_encode|json_encode)\(__\(' . preg_quote("'" . $message . "'", '/') . '\)(?:(?!\?>)[^\r\n])*\?>/';
	if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) < 1) {
		throw new \RuntimeException('Cannot locate translated expression in ' . $name . ': ' . $message);
	}
	foreach ($matches[0] as $match) {
		$line = substr_count(substr($source, 0, $match[1]), "\n") + 1;
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-translated-js-');
		// Compile the exact shipped expression at its original source line.
		file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . '?>' . $match[0]);
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
		ob_start();
		try {
			require $temporary;
			$encoded = ob_get_contents();
		} finally {
			ob_end_clean();
			unlink($temporary);
		}
		if (json_decode($encoded, true) !== __($message) || stripos($encoded, '</script') !== false) {
			throw new \RuntimeException('Translated JavaScript string did not round trip safely: ' . $name);
		}
		$executed++;
	}
}

$escCases = array(
	array('plugins.php', 'This permanently removes all of its entries'),
	array('settings.php', 'Changing Permission Model will alter'),
	array('settings.php', 'After you change the Graph Permission Model'),
	array('settings.php', 'Poller Interval must be less than Cron Interval'),
	array('settings.php', 'Send a Test Email'),
	array('user_admin.php', 'Password Too Short'),
	array('user_admin.php', 'Password Validation Passes'),
	array('user_admin.php', 'Passwords do Not Match'),
	array('user_admin.php', 'Passwords Match'),
);

foreach ($escCases as list($name, $needle)) {
	$file = $root . '/' . $name;
	$source = file_get_contents($file);
	$pattern = '/<\?php(?:(?!\?>)[^\r\n])*(?:cacti_js_encode|json_encode)\(__esc\((?:(?!\?>)[^\r\n])*' . preg_quote($needle, '/') . '(?:(?!\?>)[^\r\n])*\?>/';
	if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) < 1) {
		throw new \RuntimeException('Cannot locate escaped translated expression in ' . $name . ': ' . $needle);
	}
	foreach ($matches[0] as $match) {
		$line = substr_count(substr($source, 0, $match[1]), "\n") + 1;
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-translated-js-');
		file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . '?>' . $match[0]);
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
		ob_start();
		try {
			require $temporary;
			$encoded = ob_get_contents();
		} finally {
			ob_end_clean();
			unlink($temporary);
		}
		if (json_decode($encoded, true) !== __esc($needle) || stripos($encoded, '</script') !== false) {
			throw new \RuntimeException('Escaped translated JavaScript string did not round trip safely: ' . $name);
		}
		$executed++;
	}
}

// Exercise json_encode($uninstall_msg / $uninstall_title) expressions in plugins.php.
$pluginFile = $root . '/plugins.php';
$pluginSource = file_get_contents($pluginFile);
$uninstall_msg = __("Uninstall 'plugin'?");
$uninstall_title = __("Confirm 'uninstall'");
foreach (array(
	array('/<\?php\s+print\s+json_encode\(html_escape\(\$uninstall_msg\)\);\?>/', html_escape($uninstall_msg)),
	array('/<\?php\s+print\s+json_encode\(\$uninstall_title\);\?>/', $uninstall_title),
) as list($pattern, $expected)) {
	if (preg_match($pattern, $pluginSource, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new \RuntimeException('Cannot locate plugin dialog expression');
	}
	$line = substr_count(substr($pluginSource, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-translated-js-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . '?>' . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($pluginFile);
	ob_start();
	try {
		require $temporary;
		$encoded = ob_get_contents();
	} finally {
		ob_end_clean();
		unlink($temporary);
	}
	if (json_decode($encoded, true) !== $expected) {
		throw new \RuntimeException('Plugin dialog expression did not round trip safely');
	}
	$executed++;
}

// Exercise shipped RTL dir= output lines across HTML headers and auth pages.
$rtlTemplateFiles = array(
	'auth_changepassword.php',
	'auth_login.php',
	'graph_realtime.php',
	'include/top_general_header.php',
	'include/top_graph_header.php',
	'include/top_header.php',
	'install/install.php',
);

foreach ($rtlTemplateFiles as $name) {
	$file = $root . '/' . $name;
	$source = file_get_contents($file);
	if (preg_match("/<html lang='<\?php print CACTI_LOCALE;\?>' dir='<\?php print get_locale_direction\(\);\?>'>/", $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new \RuntimeException('Cannot locate RTL HTML root tag in ' . $name);
	}
	$line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-rtl-html-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . '?>' . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	ob_start();
	try {
		require $temporary;
		$rendered = ob_get_contents();
	} finally {
		ob_end_clean();
		unlink($temporary);
	}
	if (strpos($rendered, "dir='ltr'") === false) {
		throw new \RuntimeException('RTL HTML tag did not emit direction in ' . $name);
	}
	$executed++;
}

foreach (array('include/auth.php', 'logout.php', 'permission_denied.php') as $name) {
	$file = $root . '/' . $name;
	$source = file_get_contents($file);
	if (preg_match('/print "<html lang=[^\r\n]+get_locale_direction\(\)[^\r\n]+;/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new \RuntimeException('Cannot locate RTL print statement in ' . $name);
	}
	$line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-rtl-print-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	ob_start();
	try {
		require $temporary;
		$rendered = ob_get_contents();
	} finally {
		ob_end_clean();
		unlink($temporary);
	}
	if (strpos($rendered, "dir='ltr'") === false) {
		throw new \RuntimeException('RTL print statement did not emit direction in ' . $name);
	}
	$executed++;
}

// Exercise core i18n and RRDtool locale runtime paths directly for coverage.
$GLOBALS['i18n']['testdomain'] = new class {
	public function translate($t) {
		return $t;
	}
	public function gettext($t) {
		return $t;
	}
};

$assertions = array(
	array(\__('Version %1$s | %2$s', '1.2.30', '2026'), 'Version 1.2.30 | 2026'),
	array(\__('Page %2$s error %1$d (%3$.2f%%)', 7, 'host.php', 12.5), 'Page host.php error 7 (12.50%)'),
	array(\__('> %d %% of Devices Globally', 50, 'testdomain'), '> 50 % of Devices Globally'),
	array(\__('%% Dev [TIP:%s]', 'MAX', 'testdomain'), '% Dev [TIP:MAX]'),
	array(\__('80% of total memory', 'testdomain'), '80% of total memory'),
	array(\__('Literal %%s', 'testdomain'), 'Literal %s'),
	array(\__gettext('100%% ready', 'cacti', true), '100% ready'),
	array(\__gettext('100%% ready', 'cacti', false), '100%% ready'),
	array(\__x('ctx', '80% of total memory'), '80% of total memory'),
	array(\__x('ctx', 'Alert %1$s (%2$s %%)', 'HostA', '90'), 'Alert HostA (90 %)'),
	array(\__x('ctx', '100%% done', 'ignored'), '100% done'),
	array(\get_locale_direction('ar-SA'), 'rtl'),
	array(\get_locale_direction('he-IL'), 'rtl'),
	array(\get_locale_direction('en-US'), 'ltr'),
	array(\get_locale_direction('unknown-XX'), 'ltr'),
	array(\get_locale_direction(), 'ltr'),
);

foreach ($assertions as list($actual, $expected)) {
	if ($actual !== $expected) {
		throw new \RuntimeException('Unexpected i18n runtime result: ' . var_export($actual, true) . ' !== ' . var_export($expected, true));
	}
}

$beforeNum = setlocale(LC_NUMERIC, '0');
$GLOBALS['cacti_locale']  = 'de-DE';
$GLOBALS['cacti_country'] = 'de';
$scaledK = \number_format_i18n(2048, 2, 1024);
$scaledM = \number_format_i18n(2097152, 2, 1024);
$scaledG = \number_format_i18n(2147483648, 2, 1024);
$scaledT = \number_format_i18n(2199023255552, 2, 1024);
$unscaled = \number_format_i18n(2048, -1, 1024);
$GLOBALS['cacti_locale']  = 'en';
$GLOBALS['cacti_country'] = 'us';
$enFormatted = \number_format_i18n(1234.56, 2, 0);
if (setlocale(LC_NUMERIC, '0') !== $beforeNum) {
	throw new \RuntimeException('number_format_i18n leaked LC_NUMERIC');
}
if (strpos($scaledK, 'K') === false || strpos($scaledM, 'M') === false || strpos($scaledG, 'G') === false || strpos($scaledT, 'T') === false || $unscaled === '' || $enFormatted === '') {
	throw new \RuntimeException('number_format_i18n formatting failed');
}

$tmpLog = tempnam(sys_get_temp_dir(), 'cacti-i18n-text-log-');
$GLOBALS['config']['i18n_log'] = '';
$GLOBALS['config']['i18n_text_log'] = $tmpLog;
\i18n_text_debug('debug-line');
$logContents = file_get_contents($tmpLog);
unlink($tmpLog);
if (strpos((string) $logContents, 'debug-line') === false) {
	throw new \RuntimeException('i18n_text_debug did not write to i18n_text_log');
}

$origLang = getenv('LANG');
$origNum  = getenv('LC_NUMERIC');
putenv('LANG');
putenv('LC_NUMERIC');
\rrdtool_set_language('en');
if (getenv('LANG') !== 'C.UTF-8' || getenv('LC_NUMERIC') !== 'C') {
	throw new \RuntimeException('rrdtool_set_language did not set C.UTF-8 / LC_NUMERIC=C');
}
\rrdtool_reset_language();
if (getenv('LANG') !== false || getenv('LC_NUMERIC') !== false) {
	throw new \RuntimeException('rrdtool_reset_language did not unset LANG / LC_NUMERIC');
}
putenv('LANG=de_DE.UTF-8');
putenv('LC_NUMERIC=de_DE.UTF-8');
\rrdtool_set_language(-1);
\rrdtool_reset_language();
if (getenv('LANG') !== 'de_DE.UTF-8' || getenv('LC_NUMERIC') !== 'de_DE.UTF-8') {
	throw new \RuntimeException('rrdtool_reset_language did not restore prior LANG / LC_NUMERIC');
}
if ($origLang === false) {
	putenv('LANG');
} else {
	putenv('LANG=' . $origLang);
}
if ($origNum === false) {
	putenv('LC_NUMERIC');
} else {
	putenv('LC_NUMERIC=' . $origNum);
}

print json_encode(array('passed' => $executed)) . "\n";
