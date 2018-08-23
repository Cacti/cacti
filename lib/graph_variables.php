<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* nth_percentile - given a data source, calculate the Nth percentile for a given
     time period
   @arg $local_data_id - the data source to perform the Nth percentile calculation
   @arg $start_seconds - start seconds of time range
   @arg $stop_seconds - stop seconds of time range
   @arg $percentile - Nth Percentile to calculate, integer between 1 and 99
   @arg $resolution - the accuracy of the data measured in seconds
   @returns - (array) an array containing each data source item, and its 95th percentile */
function nth_percentile($local_data_id, $start_seconds, $end_seconds, $percentile = 95, $resolution = 0, $peak = false) {
	global $config;

	include_once($config['library_path'] . '/rrd.php');

	/* Check to see if we have array, if array, we are doing complex 95th calculations */
	if (is_array($local_data_id)) {
		$sum_array   = array();
		$fetch_array = array();

		$i = 0;
		/* do a fetch for each data source */
		foreach ($local_data_id as $ldi => $ldi_value) {
			if ($peak) {
				$fetch_array[$i] = @rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution, false, 'MAX');

				if (!sizeof($fetch_array[$i])) {
					$fetch_array[$i] = @rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution);
				}
			} else {
				$fetch_array[$i] = @rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution);
			}
			/* clean up unwanted data source items */
			if (!empty($fetch_array[$i])) {
				foreach ($fetch_array[$i]['data_source_names'] as $id => $name) {
					/* get rid of the Nth max for now since we'll need to re-calculate it later */
					if ($name == 'nth_percentile_maximum') {
						unset($fetch_array[$i]['data_source_names'][$id], $fetch_array[$i]['values'][$id]);
						continue;
					}
					/* clean up DS items that aren't defined on the graph */
					if (!in_array($name, $local_data_id[$ldi])) {
						unset($fetch_array[$i]['data_source_names'][$id], $fetch_array[$i]['values'][$id]);
					}
				}
				$i++;
			}
		}

		/* Create our array for working  */
		if (empty($fetch_array[0]['data_source_names'])) {
			/* here to avoid warning on non-exist file */
			$fetch_array = array();
		} else {
			$sum_array['data_source_names'] = $fetch_array[0]['data_source_names'];
		}

		if (sizeof($fetch_array)) {
			/* Create hash of ds_item_name => array or fetch_array indexes */
			/* this is used to later sum, max and total the data sources used on the graph */
			/* Loop fetch array index  */
			$dsi_name_to_id = array();
			for ($i=0; $i<count($fetch_array); $i++) {
				/* Go through data souce names */
				foreach ($fetch_array[$i]['data_source_names'] as $ds_name) {
					$dsi_name_to_id[$ds_name][] = $i;
				}
			}

			/* Sum up the like data sources */
			$sum_array = array();
			$i = 0;

			foreach ($dsi_name_to_id as $ds_name => $id_array) {
				$sum_array['data_source_names'][$i] = $ds_name;

				foreach ($id_array as $id) {
					$fetch_id = array_search($ds_name,$fetch_array[$id]['data_source_names']);

					/* Sum up like ds names */
					for ($j=0; $j<count($fetch_array[$id]['values'][$fetch_id]); $j++) {
						if (isset($fetch_array[$id]['values'][$fetch_id][$j])) {
							$value = $fetch_array[$id]['values'][$fetch_id][$j];
						} else {
							$value = 0;
						}

						if (isset($sum_array['values'][$i][$j])) {
							$sum_array['values'][$i][$j] += $value;
						} else {
							$sum_array['values'][$i][$j] = $value;
						}
					}
				}

				$i++;
			}
		}

		/* calculate extra data, max, sum */
		if (isset($sum_array['values'])) {
			$num_ds = count($sum_array['values']);
			$total_ds = count($sum_array['values']);

			for ($j=0; $j<$total_ds; $j++) { /* each data source item */
				for ($k=0; $k<count($sum_array['values'][$j]); $k++) { /* each rrd row */
					/* now we must re-calculate the 95th max */
					$value = 0;
					if (isset($sum_array['values'][$j][$k])) {
						$value = $sum_array['values'][$j][$k];
					}

					if (isset($sum_array['values'][$num_ds][$k])) {
						$sum_array['values'][$num_ds][$k] = max($value, $sum_array['values'][$num_ds][$k]);
					} else {
						$sum_array['values'][$num_ds][$k] = max($value, 0);
					}

					/* sum of all ds rows */
					$value = 0;
					if (isset($sum_array['values'][$j][$k])) {
						$value = $sum_array['values'][$j][$k];
					}

					if (isset($sum_array['values'][$num_ds + 1][$k])) {
						$sum_array['values'][$num_ds + 1][$k] += $value;
					} else {
						$sum_array['values'][$num_ds + 1][$k] = $value;
					}
				}
			}

			$sum_array['data_source_names'][$num_ds] = 'nth_percentile_aggregate_max';
			$sum_array['data_source_names'][$num_ds + 1] = 'nth_percentile_aggregate_sum';
		}

		$fetch_array = $sum_array;
	} else {
		/* No array, just calculate the 95th for the data source */
		$fetch_array = @rrdtool_function_fetch($local_data_id, $start_seconds, $end_seconds, $resolution);
	}

	/* loop through each data source */
	if (empty($fetch_array['data_source_names'])) {
		return array();
	}

	$values_array = array();
	for ($i=0; $i<count($fetch_array['data_source_names']); $i++) {
		if (isset($fetch_array['values'][$i])) {
			$values_array = $fetch_array['values'][$i];

			/* sort the array in descending order */
			rsort($values_array, SORT_NUMERIC);
		}

		/* grab the N% row (or 1 - N% in reverse) and use that as our Nth percentile value */
		$inverse_percentile = 1 - ($percentile / 100);
		$target = ((count($values_array) + 1) * $inverse_percentile);
		$target = sprintf('%d', $target);

		if (empty($values_array[$target])) {
			$values_array[$target] = 0;
		}

		/* collect Nth percentile values in this array so we can return them */
		$return_array[$fetch_array['data_source_names'][$i]] = $values_array[$target];

		/* get max Nth calculation for aggregate */
		if ($fetch_array['data_source_names'][$i] != 'nth_percentile_aggregate_max' &&
			$fetch_array['data_source_names'][$i] != 'nth_percentile_aggregate_sum' &&
			$fetch_array['data_source_names'][$i] != 'nth_percentile_maximum') {
			if (isset($return_array['nth_percentile_aggregate_total'])) {
				if ($return_array['nth_percentile_aggregate_total'] < $values_array[$target]) {
					$return_array['nth_percentile_aggregate_total'] = $values_array[$target];
				}
			} else {
				$return_array['nth_percentile_aggregate_total'] = $values_array[$target];
			}
		}
	}

	if (isset($return_array)) {
		return $return_array;
	}
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

	if (!isset($fetch_array['data_source_names']) || count($fetch_array['data_source_names']) == 0) {
		return;
	}

	$return_array = array();

	/* loop through each regexp determined above (or each data source) */
	for ($i=0; $i<count($fetch_array['data_source_names']); $i++) {
		if (isset($fetch_array['values'][$i])) {
			$sum = array_sum($fetch_array['values'][$i]);

			if (count($fetch_array['values'][$i]) > 0) {
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

	if (sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if ($graph['base_value'] == 1024) {
		$pow = 10.24;
	} else {
		$pow = 10;
	}

	if ($regexp_match_array[1] < 1 || $regexp_match_array[1] > 99) {
		/* error Nth Percentile variable is incorrect */
		return -1;
	}

	/* Get the Nth percentile values */
	switch ($regexp_match_array[4]) {
		case 'current':
			$nth_cache[$graph_item['local_data_id']] = nth_percentile($graph_item['local_data_id'], $graph_start, $graph_end, $regexp_match_array[1]);

			break;
		case 'max':
			$nth_cache[$graph_item['local_data_id']] = nth_percentile($graph_item['local_data_id'], $graph_start, $graph_end, $regexp_match_array[1], 0, true);

			break;
		case 'total':
		case 'all_max_current':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['local_data_id'])) {
					$nth_cache[$graph_element['local_data_id']] = nth_percentile($graph_element['local_data_id'], $graph_start, $graph_end, $regexp_match_array[1]);
				}
			}

			break;
		case 'total_peak':
		case 'all_max_peak':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['local_data_id'])) {
					$nth_cache[$graph_element['local_data_id']] = nth_percentile($graph_element['local_data_id'], $graph_start, $graph_end, $regexp_match_array[1], 0, true);
				}
			}

			break;
		case 'aggregate':
		case 'aggregate_sum':
			$local_data_array = array();
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					$local_data_array[$graph_element['local_data_id']][] = $graph_element['data_source_name'];
				}
			}
			$nth_cache[0] = nth_percentile($local_data_array, $graph_start, $graph_end, $regexp_match_array[1]);

			break;
		case 'aggregate_max':
			$local_data_array = array();
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					$local_data_array[$graph_element['local_data_id']][] = $graph_element['data_source_name'];
				}
			}
			$nth_cache[0] = nth_percentile($local_data_array, $graph_start, $graph_end, $regexp_match_array[1], 0, true);

			break;
		case 'aggregate_current':
			$local_data_array = array();
			if (!empty($graph_item['data_source_name'])) {
				foreach ($graph_items as $graph_element) {
					if ($graph_item['data_source_name'] == $graph_element['data_source_name'] &&
						!empty($graph_element['data_template_rrd_id']) &&
						preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
						$local_data_array[$graph_element['local_data_id']][] = $graph_element['data_source_name'];
					}
				}
				$nth_cache[0] = nth_percentile($local_data_array, $graph_start, $graph_end, $regexp_match_array[1]);
			}

			break;
	}

	$nth = 0;

	/* format the output according to args passed to the variable */
	switch($regexp_match_array[4]) {
		case 'current':
			if (!empty($nth_cache[$graph_item['local_data_id']][$graph_item['data_source_name']])) {
				$nth = $nth_cache[$graph_item['local_data_id']][$graph_item['data_source_name']];
				$nth = ($regexp_match_array[2] == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($pow, intval($regexp_match_array[3]));
			}

			break;
		case 'total':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					if (!empty($nth_cache[$graph_element['local_data_id']][$graph_element['data_source_name']])) {
						$local_nth = $nth_cache[$graph_element['local_data_id']][$graph_element['data_source_name']];
						$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
						$local_nth /= pow($pow, intval($regexp_match_array[3]));

						$nth += $local_nth;
					}
				}
			}

			break;
		case 'max':
			if (!empty($nth_cache[$graph_item['local_data_id']]['nth_percentile_maximum'])) {
				$nth = $nth_cache[$graph_item['local_data_id']]['nth_percentile_maximum'];
				$nth = ($regexp_match_array[2] == 'bits') ? $nth * 8 : $nth;
				$nth /= pow($pow, intval($regexp_match_array[3]));
			}

			break;
		case 'total_peak':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					if (!empty($nth_cache[$graph_element['local_data_id']]['nth_percentile_maximum'])) {
						$local_nth = $nth_cache[$graph_element['local_data_id']]['nth_percentile_maximum'];
						$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
						$local_nth /= pow($pow, intval($regexp_match_array[3]));

						$nth += $local_nth;
					}
				}
			}

			break;
		case 'all_max_current':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					if (!empty($ninety_fifth_cache[$graph_element['local_data_id']][$graph_element['data_source_name']])) {
						$local_nth = $ninety_fifth_cache[$graph_element['local_data_id']][$graph_element['data_source_name']];
						$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
						$local_nth /= pow($pow, intval($regexp_match_array[3]));

						if ($local_nth > $nth) {
							$nth = $local_nth;
						}
					}
				}
			}

			break;
		case 'all_max_peak':
			foreach ($graph_items as $graph_element) {
				if (!empty($graph_element['data_template_rrd_id']) && preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					if (!empty($nth_cache[$graph_element['local_data_id']]['nth_percentile_maximum'])) {
						$local_nth = $nth_cache[$graph_element['local_data_id']]['nth_percentile_maximum'];
						$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
						$local_nth /= pow($pow, intval($regexp_match_array[3]));

						if ($local_nth > $nth) {
							$nth = $local_nth;
						}
					}
				}
			}

			break;
		case 'aggregate':
		case 'aggregate_current':
			if (!empty($nth_cache[0]['nth_percentile_aggregate_total'])) {
				$local_nth = $nth_cache[0]['nth_percentile_aggregate_total'];
				$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
				$local_nth /= pow($pow, intval($regexp_match_array[3]));
				$nth = $local_nth;
			}

			break;
		case 'aggregate_max':
			if (!empty($nth_cache[0]['nth_percentile_aggregate_max'])) {
				$local_nth = $nth_cache[0]['nth_percentile_aggregate_max'];
				$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
				$local_nth /= pow($pow, intval($regexp_match_array[3]));
				$nth = $local_nth;
			}

			break;
		case 'aggregate_sum':
			if (!empty($nth_cache[0]['nth_percentile_aggregate_sum'])) {
				$local_nth = $nth_cache[0]['nth_percentile_aggregate_sum'];
				$local_nth = ($regexp_match_array[2] == 'bits') ? $local_nth * 8 : $local_nth;
				$local_nth /= pow($pow, intval($regexp_match_array[3]));
				$nth = $local_nth;
			}

			break;
	}

	/* determine the floating point precision */
	if (is_numeric($regexp_match_array[5])) {
		$round_to = $regexp_match_array[5];
	} else {
		$round_to = 2;
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
   @returns - a string containg the bandwidth summation suitable for placing on the graph */
function variable_bandwidth_summation(&$regexp_match_array, &$graph, &$graph_item, &$graph_items, $graph_start, $graph_end, $rra_step, $ds_step) {
	global $graph_item_types;

	if (sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if ($graph['base_value'] == 1024) {
		$pow = 10.24;
	} else {
		$pow = 10;
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
				if (!empty($graph_element['local_data_id'])) {
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
					isset($summation_cache[$graph_element['local_data_id']][$graph_element['data_source_name']]) &&
					preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_element['graph_type_id']])) {
					$summation += $summation_cache[$graph_element['local_data_id']][$graph_element['data_source_name']];
				}
			}

			break;
	}

	if (preg_match('/\d+/', $regexp_match_array[1])) {
		$summation /= pow($pow, intval($regexp_match_array[1]));
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
		return number_format_i18n($summation, $round_to) . " $summation_label";
	} else {
		return number_format_i18n($summation, $round_to);
	}
}
