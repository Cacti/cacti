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

/* nth_percentile - given a data source, calculate the Nth percentile for a given over a time period
   @arg $local_data_ids - the data source array to perform the Nth percentile calculation
   @arg $start_seconds - start seconds of time range
   @arg $stop_seconds - stop seconds of time range
   @arg $percentile - Nth Percentile to calculate, integer between 1 and 99
   @arg $resolution - the accuracy of the data measured in seconds
   @returns - (array) an array containing each data source item, and its 95th percentile */
function nth_percentile($local_data_ids, $start_seconds, $end_seconds, $percentile = 95, $resolution = 0, $peak = false) {
	$stats = json_decode(rrdtool_function_stats($local_data_ids, $start_seconds, $end_seconds, $percentile, $resolution, $peak), true);

	if ($peak) {
		if (array_key_exists('peak', $stats)) {
			return $stats['peak'];
		} else if (array_key_exists('avg', $stats)) {
			return $stats['avg'];
		} else {
			return $stats;
		}
	} else {
		if (array_key_exists('avg', $stats)) {
			return $stats['avg'];
		} else if (array_key_exists('peak', $stats)) {
			return $stats['peak'];
		} else {
			return $stats;
		}
	}
}

/* rrdtool_function_stats - given a data source, calculate a number of statistics for an RRDfile or files
   over a specified time period
   @arg $local_data_ids - the data source array to perform the Nth percentile calculation
   @arg $start_seconds - start seconds of time range
   @arg $stop_seconds - stop seconds of time range
   @arg $percentile - Nth Percentile to calculate, integer between 1 and 99
   @arg $resolution - the accuracy of the data measured in seconds
   @returns - (array) an array containing each data source item, and its 95th percentile */
function rrdtool_function_stats($local_data_ids, $start_seconds, $end_seconds, $percentile = 95, $resolution = 0, $peak = false) {
	global $config;

	include_once($config['library_path'] . '/rrd.php');

	if (!is_array($local_data_ids)) {
		return json_encode(array());
	}

	/* initialize some variables */
	$sum_array       = array();
	$fetch_array_avg = array();
	$fetch_array_max = array();
	$good_data       = false;

	/* Do a fetch for each data source and discard
	 * nth_percentile_maximum and any invalid data sources
	 *
	 * After this loop is finished, all data will have
	 * been retrieved from the RRDfiles.
	 */
	foreach ($local_data_ids as $ldi => $data_source_name) {
		/* more error checking for invalid data */
		if ($ldi == 0) {
			continue;
		}

		// See if the RRDfile contains the MAX consolidation function, if so prime the array with the fetch data
		if (rrdtool_function_contains_cf($ldi, 'MAX')) {
			$fetch_array_max[$ldi] = @rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution, false, null, 'MAX');
		}

		// See if the RRDfile contains the AVERAGE consolidation function, if so prime the array with the fetch data
		if (rrdtool_function_contains_cf($ldi, 'AVERAGE')) {
			$fetch_array_avg[$ldi] = @rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution);
		}

		/* clean up unwanted data source items from the AVERAGE cf data */
		if (isset($fetch_array_avg[$ldi]) && cacti_sizeof($fetch_array_avg[$ldi])) {
			if (isset($fetch_array_avg[$ldi]['data_source_names'])) {
				$good_data = true;
			} else {
				unset($fetch_array_avg[$ldi]);

				continue;
			}

			/* discard the unused data sources, we will figure it out ourselves */
			foreach ($fetch_array_avg[$ldi]['data_source_names'] as $index => $name) {
				/* clean up DS items that aren't defined on the graph */
				if (!in_array($name, $local_data_ids[$ldi])) {
					if (isset($fetch_array_avg[$ldi]['data_source_names'][$index])) {
						unset($fetch_array_avg[$ldi]['data_source_names'][$index]);
					}

					if (isset($fetch_array_avg[$ldi]['values'][$index])) {
						unset($fetch_array_avg[$ldi]['values'][$index]);
					}
				}
			}
		}

		/* clean up unwanted data source items from the MAX cf data */
		if (isset($fetch_array_max[$ldi]) && cacti_sizeof($fetch_array_max[$ldi])) {
			if (isset($fetch_array_max[$ldi]['data_source_names'])) {
				$good_data = true;
			} else {
				unset($fetch_array_max[$ldi]);

				continue;
			}

			/* discard the unused data sources, we will figure it out ourselves */
			foreach ($fetch_array_max[$ldi]['data_source_names'] as $index => $name) {
				/* clean up DS items that aren't defined on the graph */
				if (!in_array($name, $local_data_ids[$ldi])) {
					if (isset($fetch_array_max[$ldi]['data_source_names'][$index])) {
						unset($fetch_array_max[$ldi]['data_source_names'][$index]);
					}

					if (isset($fetch_array_max[$ldi]['values'][$index])) {
						unset($fetch_array_max[$ldi]['values'][$index]);
					}
				}
			}
		}
	}

	/* Do a sanity check, return right away if we don't have
	 * good data.  Else prepare a new array with summary data.
	 */
	if (!$good_data) {
		return json_encode(array());
	}

	$stats = $stats_max = array();

	if (cacti_sizeof($fetch_array_avg)) {
		$stats['avg'] = nth_percentile_fetch_statistics($percentile, $local_data_ids, $fetch_array_avg, 'AVERAGE');
	}

	if (cacti_sizeof($fetch_array_max)) {
		$stats['peak'] = nth_percentile_fetch_statistics($percentile, $local_data_ids, $fetch_array_max, 'MAX');
	}

	return json_encode($stats);
}

function nth_percentile_fetch_statistics($percentile, &$local_data_ids, &$fetch_array, $cf) {
	/* start by summing the data across local data ids, for the average cf */
	$asum_array = array();

	foreach ($local_data_ids as $ldi => $data_source_name) {
		if (cacti_sizeof($fetch_array[$ldi]['data_source_names'])) {
			foreach ($fetch_array[$ldi]['data_source_names'] as $index => $ds_name) {
				if (isset($fetch_array[$ldi]['values'][$index]) && cacti_sizeof($fetch_array[$ldi]['values'][$index])) {
					foreach ($fetch_array[$ldi]['values'][$index] as $timestamp => $data) {
						if (isset($asum_array[$ds_name]) && isset($asum_array[$ds_name][$timestamp])) {
							$asum_array[$ds_name][$timestamp] += $data;
						} else {
							$asum_array[$ds_name][$timestamp]  = $data;
						}
					}
				}
			}
		}
	}

	//print '<pre>';print_r($asum_array);print '</pre>';

	/* next get the max values of all the data sources */
	$max_values_array = array();
	if (cacti_sizeof($asum_array)) {
		foreach ($asum_array as $ds_name => $sum_by_timestamp) {
			foreach ($sum_by_timestamp as $timestamp => $data) {
				if (!isset($max_values_array[$timestamp])) {
					$max_values_array[$timestamp] = $data;
				} else {
					$max_values_array[$timestamp] = max($data, $max_values_array[$timestamp]);
				}
			}
		}
	}

	/* store some known information for legacy cacti behavior */
	$asum_array['nth_percentile_maximum'] = $max_values_array;

	/* get the sum data now across all data sources */
	$sum_values_array = array();
	if (cacti_sizeof($asum_array)) {
		foreach ($asum_array as $ds_name => $sum_by_timestamp) {
			if ($ds_name == 'nth_percentile_maximum') {
				continue;
			}

			foreach ($sum_by_timestamp as $timestamp => $data) {
				if (!isset($sum_values_array[$timestamp])) {
					$sum_values_array[$timestamp] = $data;
				} else {
					$sum_values_array[$timestamp] += $data;
				}
			}
		}
	}

	/* store some known information for legacy cacti behavior */
	$asum_array['nth_percentile_sum'] = $sum_values_array;

	/* get some nice analytical statistics about the data */
	$stats = array();
	$agg_total = 0;

	foreach ($asum_array as $ds_name => $data_by_timestamp) {
		$cstats['stats_' . $ds_name] = cacti_stats_calc($data_by_timestamp, $percentile);
		$stats[$ds_name] = $cstats['stats_' . $ds_name]['p' . $percentile . 'n'];

		/* scan all non built-in data sources for aggregate total data */
		if ($ds_name != 'nth_percentile_sum' &&
			$ds_name != 'nth_percentile_maximum') {

			if ($agg_total < $cstats['stats_' . $ds_name]['p' . $percentile . 'n']) {
				$agg_total = $cstats['stats_' . $ds_name]['p' . $percentile . 'n'];
			}
		}
	}

	/* store some known information for legacy cacti behavior */
	$stats['nth_percentile_aggregate_total'] = $agg_total;

	$stats += $cstats;

	return($stats);
}

function cacti_stats_calc($array, $ptile = 95) {
	rsort($array, SORT_NUMERIC);

	$elements = cacti_sizeof($array);

	if ($elements == 0) {
		$results = array(
			'p95n'     => 0,
			'p90n'     => 0,
			'p75n'     => 0,
			'p50n'     => 0,
			'p25n'     => 0,
			'average'  => 0,
			'sum'      => 0,
			'elements' => 0,
			'variance' => 0,
			'stddev'   => 0
		);

		$results['p' . $ptile . 'n'] = 0;

		return $results;
	}

	$variance = 0;
	$sum      = array_sum($array);
	$average  = $sum/$elements;
	$var      = 'p' . $ptile . 'n';

	if ($var == 'p95n') {
		$var = '';
	}

	foreach ($array as $number) {
		$variance += pow(abs($number - $average), 2);
	}

	$ptile_index = ceil($elements * (1 - ($ptile/100)));
	$p95n_index  = ceil($elements * 0.05);
	$p90n_index  = ceil($elements * 0.1);
	$p75n_index  = ceil($elements * 0.25);
	$p50n_index  = ceil($elements * 0.50);
	$p25n_index  = ceil($elements * 0.75);

	$results = array(
		'p95n'     => (isset($array[$p95n_index]) ? $array[$p95n_index] : 0),
		'p90n'     => (isset($array[$p90n_index]) ? $array[$p90n_index] : 0),
		'p75n'     => (isset($array[$p75n_index]) ? $array[$p75n_index] : 0),
		'p50n'     => (isset($array[$p50n_index]) ? $array[$p50n_index] : 0),
		'p25n'     => (isset($array[$p25n_index]) ? $array[$p25n_index] : 0),
		'average'  => $average,
		'sum'      => $sum,
		'elements' => $elements,
		'variance' => $variance,
		'stddev'   => sqrt($variance/$elements)
	);

	if ($var != '') {
		$results[$var] = $array[$ptile_index];
	}

	return $results;
}

/* bandwidth_summation - given a data source, sums all data in the rrd for a given
     time period
   @arg $local_data_id - the data source to perform the summation for
   @arg $start_time - the start time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $end_time - the end time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $resolution - the accuracy of the data measured in seconds
   @arg $rra_steps - how many periods each sample in the RRA counts for, values above '1'
     result in an averaged summation
   @arg $ds_steps - how many seconds each period represents
   @returns - (array) an array containing each data source item, and its sum */
function bandwidth_summation($local_data_id, $start_time, $end_time, $rra_steps, $ds_steps) {
	$fetch_array = @rrdtool_function_fetch($local_data_id, $start_time, $end_time, $rra_steps * $ds_steps);

	if (!isset($fetch_array['data_source_names']) || cacti_count($fetch_array['data_source_names']) == 0) {
		return;
	}

	$return_array = array();

	/* loop through each regexp determined above (or each data source) */
	for ($i=0; $i<cacti_count($fetch_array['data_source_names']); $i++) {
		if (isset($fetch_array['values'][$i])) {
			$sum = array_sum($fetch_array['values'][$i]);

			if (cacti_count($fetch_array['values'][$i]) > 0) {
				$sum = ($sum * $ds_steps * $rra_steps);
			} else {
				$sum = 0;
			}

			/* collect summation values in this array so we can return them */
			$return_array[$fetch_array['data_source_names'][$i]] = $sum;
		}
	}

	return $return_array;
}

function is_graphable_item($item) {
	if (preg_match('/(AREA|STACK|LINE[123])/', $item)) {
		return true;
	} else {
		return false;
	}
}

/* variable_nth_percentile - given a Nth percentile variable, calculate the Nth percentile
     and format it for display on the graph
   @arg $regexp_match_array - the array that contains each argument in the Nth percentile variable. it
     should be formatted like so:
       $arr[0] // full variable string
       $arr[1] // Nth percentile
       $arr[2] // bits or bytes
       $arr[3] // power of 10 divisor
       $arr[4] // current, total, max, total_peak, all_max_current, all_max_peak
       $arr[5] // digits of floating point precision
   @arg $graph - an array that contains the current graph data
   @arg $graph_item - an array that contains the current graph item
   @arg $graph_items - an array that contains all graph items
   @arg $graph_start - the start time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $graph_end - the end time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $seconds_between_graph_updates - the number of seconds between each update on the graph which
     varies depending on the RRA in use
   @returns - a string containing the Nth percentile suitable for placing on the graph */
function variable_nth_percentile(&$regexp_match_array, &$graph, &$graph_item, &$graph_items, $graph_start, $graph_end) {
	global $graph_item_types;

	$nth_cache = array();

	if (cacti_sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if ($graph['base_value'] == 1024) {
		$base = 10.24;
	} else {
		$base = 10;
	}

	// Convert the regex matches to human readable
	// Unexploded format is |95:bits:6:max:2|
	$percentile = $regexp_match_array[1];
	$bytebit    = $regexp_match_array[2];
	$power      = $regexp_match_array[3];
	$type       = $regexp_match_array[4];

	/* determine the floating point precision */
	if (is_numeric($regexp_match_array[5])) {
		$round_to = $regexp_match_array[5];
	} else {
		$round_to = 2;
	}

	// error Nth Percentile variable is incorrect
	if ($percentile < 1 || $percentile > 99) {
		return -1;
	}
cacti_log(implode(', ', array_values($graph_item)));

	if (empty($graph_item['local_data_id'])) {
		$graph_item['local_data_id'] = 0;
	}

	$gi = array();
	if (cacti_sizeof($graph_items)) {
		foreach ($graph_items as $item) {
			if ($item['local_data_id'] > 0 && $item['data_source_name'] != '') {
				if (!empty($item['data_template_rrd_id']) &&
					!empty($item['local_data_id']) &&
					is_graphable_item($graph_item_types[$item['graph_type_id']])) {
					$gi[$item['data_source_name'] . '|||' . $item['local_data_id']] = true;
				}
			}
		}

		foreach ($gi as $data_source => $true) {
			list($data_source_name, $local_data_id) = explode('|||', $data_source);
			$local_data_array[$local_data_id][] = $data_source_name;
		}
	}
cacti_log(" $type should be here");

	/* Get the Nth percentile values */
	if (!cacti_sizeof($nth_cache)) {
		switch ($type) {
			case 'current':
				// Query data for the individual case
				$local_data_array = array_intersect_key($local_data_array, array_flip(array($graph_item['local_data_id'])));
				$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile);

				break;
			case 'max':
				// Query data for the individual case
				$local_data_array = array_intersect_key($local_data_array, array_flip(array($graph_item['local_data_id'])));
				$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile, 0, true);

				break;
			case 'total':
			case 'all_max_current':
				if (cacti_sizeof($local_data_array)) {
					$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile);
				}

				break;
			case 'total_peak':
			case 'all_max_peak':
				if (cacti_sizeof($local_data_array)) {
					$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile, 0, true);
				}

				break;
			case 'aggregate':
			case 'aggregate_sum':
				if (cacti_sizeof($local_data_array)) {
					$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile);
				}

				break;
			case 'aggregate_peak':
			case 'aggregate_max':
			case 'aggregate_sum_peak':
				if (cacti_sizeof($local_data_array)) {
					$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile, 0, true);
				}

				break;
			case 'aggregate_current':
			case 'aggregate_current_peak':
				$local_data_array = array();

				if ($graph_item['data_source_name'] != '') {
cacti_log("In there $type should be here");
					foreach ($graph_items as $graph_element) {
						if ($graph_item['data_source_name'] == $graph_element['data_source_name'] &&
							!empty($graph_element['data_template_rrd_id']) &&
							!empty($graph_element['local_data_id']) &&
							is_graphable_item($graph_item_types[$graph_element['graph_type_id']])) {

							$local_data_array[$graph_element['local_data_id']][] = $graph_element['data_source_name'];
						}
					}

					if ($type == 'aggregate_current') {
						if (cacti_sizeof($local_data_array)) {
							$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile);
						}
					} else {
						if (cacti_sizeof($local_data_array)) {
							$nth_cache = nth_percentile($local_data_array, $graph_start, $graph_end, $percentile, 0, true);
						}
					}
				}

				break;
		}
	}

	$nth = 0;

	/* format the output according to args passed to the variable */
	switch($type) {
		case 'current': // Total of current data source from AVERAGE or MAX consolidation function
			if (!empty($nth_cache[$graph_item['data_source_name']])) {
				$nth = $nth_cache[$graph_item['data_source_name']];
				$nth = ($bytebit == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($base, $power);
			}

			break;
		case 'total':          // Total of the current data source name
		case 'total_peak':
		case 'aggregate_sum':
		case 'aggregate_sum_peak':
			if (!empty($nth_cache['nth_percentile_sum'])) {
				$nth = $nth_cache['nth_percentile_sum'];
				$nth = ($bytebit == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($base, $power);
			}

			break;
		case 'all_max_current': // Max of all data sources
		case 'all_max_peak':
		case 'aggregate_max':
		case 'aggregate_peak':
		case 'aggregate_current_peak':
		case 'max':
			if (!empty($nth_cache['nth_percentile_maximum'])) {
				$nth = $nth_cache['nth_percentile_maximum'];
				$nth = ($bytebit == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($base, $power);
			}

			break;
		case 'aggregate':
		case 'aggregate_current':
			if (!empty($nth_cache['nth_percentile_aggregate_total'])) {
				$nth = $nth_cache['nth_percentile_aggregate_total'];
				$nth = ($bytebit == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($base, $power);
			}

			break;
	}

	/* return the final result and round off to two decimal digits */
	return round($nth, $round_to);
}

/* variable_bandwidth_summation - given a bandwidth summation variable, calculate the summation
     and format it for display on the graph
   @arg $regexp_match_array - the array that contains each argument in the bandwidth summation variable. it
     should be formatted like so:
       $arr[0] // full variable string
       $arr[1] // power of 10 divisor or 'auto'
       $arr[2] // current, total
       $arr[3] // digits of floating point precision
       $arr[4] // seconds to perform the calculation for or 'auto'
   @arg $graph - an array that contains the current graph data
   @arg $graph_item - an array that contains the current graph item
   @arg $graph_items - an array that contains all graph items
   @arg $graph_start - the start time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $graph_end - the end time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $seconds_between_graph_updates - the number of seconds between each update on the graph which
     varies depending on the RRA in use
   @arg $rra_step - how many periods each sample in the RRA counts for, values above '1' result in an
     averaged summation
   @arg $ds_step - how many seconds each period represents
   @returns - a string containing the bandwidth summation suitable for placing on the graph */
function variable_bandwidth_summation(&$regexp_match_array, &$graph, &$graph_item, &$graph_items, $graph_start, $graph_end, $rra_step, $ds_step) {
	global $graph_item_types;

	if (cacti_sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if (empty($graph_item['local_data_id'])) {
		$graph_item['local_data_id'] = 0;
		$regexp_match_array[2] = 'total';
	}

	if ($graph['base_value'] == 1024) {
		$base = 10.24;
	} else {
		$base = 10;
	}

	if (is_numeric($regexp_match_array[4])) {
		$summation_timespan_start = -$regexp_match_array[4];
	} else {
		$summation_timespan_start = $graph_start;
	}

	$summation_cache = array();
	switch($regexp_match_array[2]) {
		case 'current':
			$summation_cache[$graph_item['local_data_id']] = bandwidth_summation($graph_item['local_data_id'], $summation_timespan_start, $graph_end, $rra_step, $ds_step);

			break;
		case 'total':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) &&
					!empty($graph_element['local_data_id']) &&
					is_graphable_item($graph_item_types[$graph_element['graph_type_id']])) {
					$summation_cache[$graph_element['local_data_id']] = bandwidth_summation($graph_element['local_data_id'], $summation_timespan_start, $graph_end, $rra_step, $ds_step);
				}
			}

			break;
		case 'atomic':
			$summation_cache[$graph_item['local_data_id']] = bandwidth_summation($graph_item['local_data_id'], $summation_timespan_start, $graph_end, $rra_step, 1);

			break;
	}

	$summation = 0;

	/* format the output according to args passed to the variable */
	switch ($regexp_match_array[2]) {
		case 'current':
		case 'atomic':
			if (isset($summation_cache[$graph_item['local_data_id']][$graph_item['data_source_name']])) {
				$summation = $summation_cache[$graph_item['local_data_id']][$graph_item['data_source_name']];
			}

			break;
		case 'total':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) &&
					!empty($graph_element['local_data_id']) &&
					isset($summation_cache[$graph_element['local_data_id']][$graph_element['data_source_name']]) &&
					is_graphable_item($graph_item_types[$graph_element['graph_type_id']])) {
					$summation += $summation_cache[$graph_element['local_data_id']][$graph_element['data_source_name']];
				}
			}

			break;
	}

	if (preg_match('/\d+/', $regexp_match_array[1])) {
		$summation /= pow($base, $regexp_match_array[1]);
	} elseif ($regexp_match_array[1] == 'auto') {
		if ($graph['base_value'] == 1000) {
			if ($summation < 1000) {
				$summation_label = 'B';
			} elseif ($summation < 1000000) {
				$summation_label = 'KB';
				$summation /= 1000;
			} elseif ($summation < 1000000000) {
				$summation_label = 'MB';
				$summation /= 1000000;
			} elseif ($summation < 1000000000000) {
				$summation_label = 'GB';
				$summation /= 1000000000;
			} else {
				$summation_label = 'TB';
				$summation /= 1000000000000;
			}
		} else {
			if ($summation < 1024) {
				$summation_label = 'iB';
			} elseif ($summation < 1048576) {
				$summation_label = 'KiB';
				$summation /= 1024;
			} elseif ($summation < 1073741824) {
				$summation_label = 'MiB';
				$summation /= 1048576;
			} elseif ($summation < 1099511627776) {
				$summation_label = 'GiB';
				$summation /= 1073741824;
			} else {
				$summation_label = 'TiB';
				$summation /= 1099511627776;
			}
		}
	}

	/* determine the floating point precision */
	if (is_numeric($regexp_match_array[3])) {
		$round_to = $regexp_match_array[3];
	} else {
		$round_to = 2;
	}

	/* substitute in the final result and round off to two decimal digits */
	if (isset($summation_label)) {
		return sprintf('%10s', number_format_i18n($summation, $round_to) . " $summation_label");
	} else {
		return sprintf('%10s', number_format_i18n($summation, $round_to));
	}
}

