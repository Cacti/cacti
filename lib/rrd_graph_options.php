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

require_once(__DIR__ . '/rrd_graph_axis.php');

/**
 * Build graph options using Cacti settings, substitution and transport quoting.
 *
 * Kept separate from RRDtool process ownership and graph-item construction.
 * Call through rrd_function_process_graph_options() for the public legacy API.
 */
function rrdtool_build_graph_options(int $graph_start, int $graph_end, array &$graph, array &$graph_data_array) : string {
	global $image_types;

	// Normal bootstrapping already loads this shared format map.
	if (!isset($image_types)) {
		include(CACTI_PATH_INCLUDE . '/global_arrays.php');
	}

	// define some variables
	$scale                     = '';
	$rigid                     = '';
	$unit_value                = '';
	$version                   = get_rrdtool_version();
	$supports_graph_formatters = null;
	$unit_exponent_value       = '';

	if ($graph['auto_scale'] == 'on') {
		switch ($graph['auto_scale_opts']) {
			case '1': // autoscale ignores lower, upper limit
				$scale = '--alt-autoscale' . RRD_NL;

				break;
			case '2': // autoscale-max, accepts a given lower limit
				$scale = '--alt-autoscale-max' . RRD_NL;

				if (is_numeric($graph['lower_limit'])) {
					$scale .= '--lower-limit=' . cacti_escapeshellarg((string) $graph['lower_limit']) . RRD_NL;
				}

				break;
			case '3': // autoscale-min, accepts a given upper limit
				$scale = '--alt-autoscale-min' . RRD_NL;

				if (is_numeric($graph['upper_limit'])) {
					$scale .= '--upper-limit=' . cacti_escapeshellarg((string) $graph['upper_limit']) . RRD_NL;
				}

				break;
			case '4': // auto_scale with limits
				$scale = '--alt-autoscale' . RRD_NL;

				if (is_numeric($graph['upper_limit'])) {
					$scale .= '--upper-limit=' . cacti_escapeshellarg((string) $graph['upper_limit']) . RRD_NL;
				}

				if (is_numeric($graph['lower_limit'])) {
					$scale .= '--lower-limit=' . cacti_escapeshellarg((string) $graph['lower_limit']) . RRD_NL;
				}

				break;
		}
	} else {
		if ($graph['upper_limit'] != '') {
			$scale =  '--upper-limit=' . cacti_escapeshellarg($graph['upper_limit']) . RRD_NL;
		}

		if ($graph['lower_limit'] != '') {
			$scale .= '--lower-limit=' . cacti_escapeshellarg($graph['lower_limit']) . RRD_NL;
		}
	}

	if ($graph['auto_scale_log'] == 'on') {
		$scale .= '--logarithmic' . RRD_NL;
	}

	// --units=si only defined for logarithmic y-axis scaling, even if it doesn't hurt on linear graphs
	if ($graph['scale_log_units'] == 'on' && $graph['auto_scale_log'] == 'on') {
		$scale .= '--units=si' . RRD_NL;
	}

	if ($graph['auto_scale_rigid'] == 'on') {
		$rigid = '--rigid' . RRD_NL;
	}

	if ($graph['unit_value'] != '') {
		$unit_value = '--y-grid=' . cacti_escapeshellarg($graph['unit_value']) . RRD_NL;
	}

	if (preg_match('/^[0-9]+$/', $graph['unit_exponent_value'])) {
		$unit_exponent_value = '--units-exponent=' . cacti_escapeshellarg($graph['unit_exponent_value']) . RRD_NL;
	}

	/*
	 * optionally you can specify and array that overrides some of the db's values, lets set
	 * that all up here
	 */

	// override: graph height (in pixels)
	if (isset($graph_data_array['graph_height'])) {
		$graph_height = $graph_data_array['graph_height'];
	} else {
		$graph_height = $graph['height'];
	}

	// override: graph width (in pixels)
	if (isset($graph_data_array['graph_width'])) {
		$graph_width = $graph_data_array['graph_width'];
	} else {
		$graph_width = $graph['width'];
	}

	// override: skip drawing the legend?
	if (isset($graph_data_array['graph_nolegend'])) {
		$graph_legend = '--no-legend' . RRD_NL;
	} else {
		$graph_legend = '';
	}

	// export options
	if (isset($graph_data_array['export'])) {
		$graph_opts = cacti_escapeshellarg((string) $graph_data_array['export_filename']) . RRD_NL;
	} else {
		if (empty($graph_data_array['output_filename'])) {
			$graph_opts = '-' . RRD_NL;
		} else {
			$graph_opts = cacti_escapeshellarg((string) $graph_data_array['output_filename']) . RRD_NL;
		}
	}

	if (isset($graph_data_array['image_format']) && $graph_data_array['image_format'] == 'png') {
		$graph['image_format_id'] = 1;
	}

	// basic graph options
	$graph_opts .=
		'--imgformat=' . $image_types[$graph['image_format_id']] . RRD_NL .
		'--start=' . $graph_start . RRD_NL .
		'--end=' . $graph_end . RRD_NL;

	$graph_opts .= '--pango-markup ' . RRD_NL;

	if (read_config_option('rrdtool_watermark') == 'on') {
		$graph_opts .= '--disable-rrdtool-tag ' . RRD_NL;
	}

	// activate --add-jsontime option for graphv only and RRDtool version 1.8.0 and above
	if (isset($graph_data_array['graphv'])) {
		$rrdversion = get_rrdtool_version();

		if (cacti_version_compare($rrdversion, '1.8', '>=')) {
			$graph_opts .= '--add-jsontime ' . RRD_NL;
		}
	}

	$graph_opts .= rrd_graph_axis_options($graph, $version);

	foreach ($graph as $key => $value) {
		if (is_string($value)) {
			$value = rrdtool_resolve_graph_text($value, $graph);
		}

		switch($key) {
			case 'title_cache':
				if (!empty($value)) {
					$graph_opts .= '--title=' . cacti_escapeshellarg(htmle($value)) . RRD_NL;
				}

				break;
			case 'alt_y_grid':
				if ($value == CHECKED) {
					$graph_opts .= '--alt-y-grid' . RRD_NL;
				}

				break;
			case 'unit_value':
				if (!empty($value)) {
					$graph_opts .= '--y-grid=' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
			case 'unit_exponent_value':
				if (preg_match('/^[0-9]+$/', $value)) {
					$graph_opts .= '--units-exponent=' . $value . RRD_NL;
				}

				break;
			case 'height':
				if (isset($graph_data_array['graph_height']) && preg_match('/^[0-9]+$/', $graph_data_array['graph_height'])) {
					$graph_opts .= '--height=' . $graph_data_array['graph_height'] . RRD_NL;
				} else {
					$graph_opts .= '--height=' . $value . RRD_NL;
				}

				break;
			case 'width':
				if (isset($graph_data_array['graph_width']) && preg_match('/^[0-9]+$/', $graph_data_array['graph_width'])) {
					$graph_opts .= '--width=' . $graph_data_array['graph_width'] . RRD_NL;
				} else {
					$graph_opts .= '--width=' . $value . RRD_NL;
				}

				break;
			case 'graph_nolegend':
				if (isset($graph_data_array['graph_nolegend'])) {
					$graph_opts .= '--no-legend' . RRD_NL;
				} else {
					$graph_opts .= '';
				}

				break;
			case 'base_value':
				if ($value == 1000 || $value == 1024) {
					$graph_opts .= '--base=' . $value . RRD_NL;
				}

				break;
			case 'vertical_label':
				if (!empty($value)) {
					$graph_opts .= '--vertical-label=' . cacti_escapeshellarg(htmle($value)) . RRD_NL;
				}

				break;
			case 'slope_mode':
				if ($value == CHECKED) {
					$graph_opts .= '--slope-mode' . RRD_NL;
				}

				break;
			case 'right_axis':
				if (!empty($value)) {
					$graph_opts .= '--right-axis ' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
			case 'right_axis_label':
				if (!empty($value)) {
					$graph_opts .= '--right-axis-label ' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
			case 'right_axis_format':
				if (!empty($value)) {
					$format = $graph['right_axis_format_text'];
					$graph_opts .= '--right-axis-format ' . cacti_escapeshellarg(trim(str_replace('%s', '', $format))) . RRD_NL;
				}

				break;
			case 'left_axis_format':
				if (!empty($value)) {
					$format = $graph['left_axis_format_text'];
					$graph_opts .= '--left-axis-format ' . cacti_escapeshellarg(trim(str_replace('%s', '', $format))) . RRD_NL;
				}

				break;
			case 'no_gridfit':
				if ($value == CHECKED) {
					$graph_opts .= '--no-gridfit' . RRD_NL;
				}

				break;
			case 'unit_length':
				if (!empty($value)) {
					$graph_opts .= '--units-length ' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
			case 'tab_width':
				if (!empty($value)) {
					$graph_opts .= '--tabwidth ' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
			case 'dynamic_labels':
				if ($value == CHECKED) {
					$graph_opts .= '--dynamic-labels' . RRD_NL;
				}

				break;
			case 'force_rules_legend':
				if ($value == CHECKED) {
					$graph_opts .= '--force-rules-legend' . RRD_NL;
				}

				break;
			case 'legend_position':
			case 'legend_direction':
			case 'left_axis_formatter':
			case 'right_axis_formatter':
				if (($supports_graph_formatters ??= cacti_version_compare($version, '1.4', '>=')) && !empty($value)) {
					$graph_opts .= '--' . str_replace('_', '-', $key) . ' ' . cacti_escapeshellarg($value) . RRD_NL;
				}

				break;
		}
	}

	$graph_opts .= "$rigid" . trim("$scale$unit_value$unit_exponent_value$graph_legend", "\n\r " . RRD_NL) . RRD_NL;

	// add a date to the graph legend
	$graph_opts .= rrdtool_function_format_graph_date($graph_data_array);

	// process theme and font styling options
	$graph_opts .= rrdtool_function_theme_font_options($graph_data_array);

	/* NOTE: title, vertical-label and right-axis-label perform |query_*|
	 * substitution before cacti_escapeshellarg above, so a device-supplied
	 * value cannot break out of the quoted RRDtool argument. */

	$watermark = str_replace("'", '"', read_config_option('graph_watermark'));

	if ($watermark != '') {
		$graph_opts .= '--watermark ' . cacti_escapeshellarg($watermark) . RRD_NL;
	}

	// if the user desires to hide RRDtools warker, set it
	if (read_config_option('graph_watermark_rrd') != '') {
		$graph_opts .= '--disable-rrdtool-tag' . RRD_NL;
	}

	return $graph_opts;
}
