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

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once CACTI_PATH_LIBRARY . '/html.php';

test('rrdtool_info2html escapes non-numeric data source and archive values', function () {
	global $settings_user;

	// Column visibility falls back to the user setting defaults, which the
	// unit bootstrap does not load.
	$saved_settings = $settings_user;
	$settings_user  = [];

	$info = [
		'filename'    => '/rra/x.rrd',
		'rrd_version' => '0003',
		'step'        => 300,
		'last_update' => 1700000000,
		'ds'          => [
			'traffic_in' => [
				'type'                => 'COUNTER',
				'minimal_heartbeat'   => 600,
				'min'                 => 0,
				'max'                 => 'U',
				'last_ds'             => 'U',
				'value'               => '<b>ds</b>',
				'unknown_sec'         => 0,
			],
		],
		'rra'         => [
			0 => [
				'cf'          => 'AVERAGE',
				'rows'        => 600,
				'cur_row'     => 1,
				'pdp_per_row' => 1,
				'xff'         => 0.5,
				'cdp_prep'    => [0 => ['value' => 'NaN', 'unknown_datapoints' => 0]],
			],
		],
	];

	ob_start();

	try {
		rrdtool_info2html($info);
	} finally {
		$output        = ob_get_clean();
		$settings_user = $saved_settings;
	}

	expect($output)->toContain('&lt;b&gt;ds&lt;/b&gt;');
	expect($output)->not->toContain('<b>ds</b>');
	expect($output)->toContain('>NaN<');
});
