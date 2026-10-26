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
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('successive graph options reuse bootstrapped arrays without rebuilding menus', function () {
	global $menu, $image_types, $datechar, $settings, $settings_user;
	require CACTI_PATH_INCLUDE . '/global_arrays.php';
	require CACTI_PATH_INCLUDE . '/global_settings.php';
	$original       = $menu;
	$original_types = $image_types;
	$menu           = ['fixture' => 'retain bootstrapped menu'];
	$image_types    = [1 => 'PNG', 3 => 'SVG'];

	try {
		$graph = [
			'auto_scale'      => '', 'upper_limit' => '', 'lower_limit' => '',
			'auto_scale_log'  => '', 'scale_log_units' => '', 'auto_scale_rigid' => '',
			'unit_value'      => '', 'unit_exponent_value' => '', 'height' => '100', 'width' => '200',
			'image_format_id' => 1,
		];

		foreach ([1, 3, 1] as $format) {
			$graph['image_format_id'] = $format;
			$data                     = [];
			$options                  = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);
			expect($options)->toContain('--imgformat=' . $image_types[$format])
				->and($menu)->toBe(['fixture' => 'retain bootstrapped menu']);
		}
	} finally {
		$menu        = $original;
		$image_types = $original_types;
	}
});
