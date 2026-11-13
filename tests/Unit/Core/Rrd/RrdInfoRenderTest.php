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

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/html_utility.php';
require_once dirname(__DIR__, 4) . '/lib/html.php';
require_once dirname(__DIR__, 4) . '/lib/rrd.php';

global $config;

$config = (is_array($config) ? $config : array()) + array(
	'library_path' => dirname(__DIR__, 4) . '/lib',
	'poller_id'    => 1,
);

if (!defined('CACTI_DATE_TIME_FORMAT')) {
	define('CACTI_DATE_TIME_FORMAT', 'Y-m-d H:i:s');
}

if (!function_exists('number_format_i18n')) {
	function number_format_i18n($number, $decimals = null, $baseu = 1024) {
		return number_format((float) $number, (int) $decimals);
	}
}

if (!function_exists('api_plugin_hook_function')) {
	function api_plugin_hook_function($name, $data = '') {
		return $data;
	}
}

function rrd_info_render(array $info, array $diff = array()) {
	ob_start();

	try {
		rrdtool_info2html($info, $diff);
	} finally {
		$output = ob_get_clean();
	}

	return $output;
}

function rrd_info_hostile_array() {
	$markup = '<img src=x onerror=alert(1)>';

	return [
		'filename'    => '/rra/' . $markup . '.rrd',
		'rrd_version' => '0003',
		'step'        => 300,
		'last_update' => 1700000000,
		'ds'          => [
			// rrdtool limits DS names to [a-zA-Z0-9_] and RRA keys are indexes, so
			// only the values carry free text
			'traffic_in' => [
				'type'              => 'GAUGE' . $markup,
				'minimal_heartbeat' => 600,
				'min'               => 'min' . $markup,
				'max'               => 'max' . $markup,
				'last_ds'           => 'last' . $markup,
				'value'             => 'value' . $markup,
				'unknown_sec'       => 0,
			],
		],
		'rra' => [
			0 => [
				'cf'          => 'AVERAGE' . $markup,
				'rows'        => 600,
				'cur_row'     => 1,
				'pdp_per_row' => 1,
				'xff'         => '0.5',
				'cdp_prep'    => [['value' => 'NaN', 'unknown_datapoints' => 0]],
			],
		],
	];
}

test('rrdtool_info2html escapes every free-text value it renders', function () {
	$output = rrd_info_render(rrd_info_hostile_array(), ['filename' => true]);

	expect($output)->not->toContain('<img')
		->and($output)->toContain('/rra/&lt;img src=x onerror=alert(1)&gt;.rrd')
		->and($output)->toContain('GAUGE&lt;img')
		->and($output)->toContain('min&lt;img')
		->and($output)->toContain('max&lt;img')
		->and($output)->toContain('last&lt;img')
		->and($output)->toContain('value&lt;img')
		->and($output)->toContain('AVERAGE&lt;img');
});

test('rrdtool_info2html keeps numeric and unknown values readable', function () {
	$info = rrd_info_hostile_array();

	$info['ds'] = [
		'traffic_in' => [
			'type'              => 'COUNTER',
			'minimal_heartbeat' => 600,
			'min'               => 'U',
			'max'               => 'NaN',
			'last_ds'           => '42',
			'value'             => '7',
			'unknown_sec'       => '0',
		],
		'traffic_out' => [
			'type' => 'COUNTER',
		],
	];

	$output = rrd_info_render($info);

	expect($output)->toContain('traffic_in')
		->and($output)->toContain('COUNTER')
		->and($output)->toContain('NaN')
		->and($output)->toContain('Unknown');
});
