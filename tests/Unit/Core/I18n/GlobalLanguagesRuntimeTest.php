<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

test('global_languages runtime handles positional printf, %% escaping, number_format_i18n, and RTL direction', function () use ($root) {
	$code = <<<'PHP'
$root = $argv[1];
$config = array(
	'base_path' => $root,
	'include_path' => $root . '/include',
	'config_options_array' => array('i18n_language_support' => '0'),
);
function read_config_option($name, $force = false) {
	return $name === 'i18n_language_support' ? '0' : '';
}
function cacti_log($msg, $out = false, $env = 'CMDPHP', $lvl = 0) {}
function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; }
function isempty_request_var($n) { return true; }
function set_request_var($n, $v) {}
function get_request_var($n, $d = '') { return $d; }
require_once $root . '/include/global_constants.php';
require_once $root . '/include/global_languages.php';

$i18n['testdomain'] = new class {
	public function translate($t) { return $t; }
	public function gettext($t) { return $t; }
};

$beforeNum = setlocale(LC_NUMERIC, '0');
$cacti_locale = 'de-DE';
$cacti_country = 'de';
$scaled = number_format_i18n(2048, 2, 1024);
$unscaled = number_format_i18n(2048, -1, 1024);
$afterNum = setlocale(LC_NUMERIC, '0');

print json_encode(array(
	'pos'      => __('Version %1$s | %2$s', '1.2.30', '2026'),
	'reorder'  => __('Page %2$s error %1$d (%3$.2f%%)', 7, 'host.php', 12.5),
	'pct_d'    => __('> %d %% of Devices Globally', 50, 'testdomain'),
	'pct_s'    => __('%% Dev [TIP:%s]', 'MAX', 'testdomain'),
	'x_lit'    => __x('ctx', '80% of total memory'),
	'x_pct'    => __x('ctx', 'Alert %1$s (%2$s %%)', 'HostA', '90'),
	'scaled'   => $scaled,
	'unscaled' => $unscaled,
	'lc_same'  => ($beforeNum === $afterNum),
	'rtl_ar'   => get_locale_direction('ar-SA'),
	'rtl_he'   => get_locale_direction('he-IL'),
	'ltr_en'   => get_locale_direction('en-US'),
));
PHP;

	$cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg($root);
	$out = shell_exec($cmd);
	$res = json_decode((string) $out, true);

	expect($res)->toBeArray()
		->and($res['pos'])->toBe('Version 1.2.30 | 2026')
		->and($res['reorder'])->toBe('Page host.php error 7 (12.50%)')
		->and($res['pct_d'])->toBe('> 50 % of Devices Globally')
		->and($res['pct_s'])->toBe('% Dev [TIP:MAX]')
		->and($res['x_lit'])->toBe('80% of total memory')
		->and($res['x_pct'])->toBe('Alert HostA (90 %)')
		->and($res['scaled'])->toBe('2 K')
		->and($res['unscaled'])->toBe('2.048')
		->and($res['lc_same'])->toBeTrue()
		->and($res['rtl_ar'])->toBe('rtl')
		->and($res['rtl_he'])->toBe('rtl')
		->and($res['ltr_en'])->toBe('ltr');
});

test('rrdtool_set_language uses C.UTF-8 with LC_NUMERIC=C and unsets cleanly when previously unset', function () use ($root) {
	$code = <<<'PHP'
$root = $argv[1];
define('CACTI_LOCALE', 'en-US');
$config = array('is_web' => false, 'config_options_array' => array('storage_location' => 0));
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';
require_once $root . '/lib/rrd.php';

putenv('LANG');
putenv('LC_NUMERIC');
rrdtool_set_language('en');
$setLang = getenv('LANG');
$setNum  = getenv('LC_NUMERIC');
rrdtool_reset_language();
$rstLang = getenv('LANG');
$rstNum  = getenv('LC_NUMERIC');

print json_encode(array(
	'set_lang' => $setLang,
	'set_num'  => $setNum,
	'rst_lang' => $rstLang,
	'rst_num'  => $rstNum,
));
PHP;

	$cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg($root);
	$out = shell_exec($cmd);
	$res = json_decode((string) $out, true);

	expect($res)->toBeArray()
		->and($res['set_lang'])->toBe('C.UTF-8')
		->and($res['set_num'])->toBe('C')
		->and($res['rst_lang'])->toBeFalse()
		->and($res['rst_num'])->toBeFalse();
});

test('translated JS strings in 1.2.x pages are emitted via json_encode', function (string $file, string $needle) use ($root) {
	$source = file_get_contents($root . '/' . $file);

	expect($source)->toContain($needle);
})->with([
	['automation_networks.php', "json_encode(__('Select the month(s) of the year'))"],
	['automation_snmp.php', "json_encode(__('Delete SNMP Option Item'))"],
	['automation_tree_rules.php', "json_encode(__('You are changing the leaf type to \"Device\" which does not support Graph-based object matching/creation.'))"],
	['data_templates.php', "json_encode(__('Custom Data Warning Message'))"],
	['host.php', "json_encode(__('Delete Item from Device'))"],
	['plugins.php', 'json_encode(html_escape($uninstall_msg))'],
	['settings.php', "json_encode(__esc('Poller Interval must be less than Cron Interval'))"],
	['settings.php', "json_encode(__esc('Send a Test Email'))"],
]);
