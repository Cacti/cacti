<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* ninety_fifth_percentile - given a data source, calculate the 95th percentile for a given
     time period
   @arg $local_data_id - the data source to perform the 95th percentile calculation
   @arg $seconds - the number of seconds into the past to perform the calculation for
   @arg $resolution - the accuracy of the data measured in seconds
   @returns - (array) an array containing each data source item, and its 95th percentile */
function ninety_fifth_percentile($local_data_id, $start_seconds, $end_seconds, $resolution = 0) {
	global $config;

	include_once($config["library_path"] . "/rrd.php");

	$values_array = array();

	if (is_array($local_data_id)) {
		$sum_array = array();
		$fetch_array = array();
		$percentile_array = array();
		$num_ds = 0;

		$i = 0;
		/* do a fetch for each data source */
		while (list($ldi, $ldi) = each($local_data_id)) {
			$fetch_array[$i] = rrdtool_function_fetch($ldi, $start_seconds, $end_seconds, $resolution);

			/* get rid of the 95th max for now since we'll need to re-calculate it later */
			while (list($id, $name) = each($fetch_array[$i]["data_source_names"])) {
				if ($name == "ninety_fifth_percentile_maximum") {
					unset($fetch_array[$i]["data_source_names"][$id]);
					unset($fetch_array[$i]["values"][$id]);
				}
			}

			$i++;
		}

		$sum_array["data_source_names"] = $fetch_array[0]["data_source_names"];

		if (sizeof($fetch_array) > 0) {
			/* create a hash of dsi name -> id mappings */
			for ($i=0; $i<count($fetch_array); $i++) { /* each data source */
				for ($j=0; $j<count($fetch_array[0]["data_source_names"]); $j++) {
					$dsi_name_to_id[$i][$j] = array_search($fetch_array[0]["data_source_names"][$j], $fetch_array[$i]["data_source_names"]);
				}
			}

			for ($i=0; $i<count($fetch_array); $i++) { /* each data source */
				for ($j=0; $j<count($fetch_array[$i]["values"]); $j++) { /* each data source item */
					$dsi_index = $dsi_name_to_id[$i][$j];

					for ($k=0; $k<count($fetch_array[$i]["values"][$dsi_index]); $k++) { /* each rrd row */
						if (isset($fetch_array[$i]["values"][$dsi_index][$k])) {
							$value = $fetch_array[$i]["values"][$dsi_index][$k];
						}else{
							$value = 0;
						}

						if (isset($sum_array["values"][$j][$k])) {
							$sum_array["values"][$j][$k] += $value;
						}else{
							$sum_array["values"][$j][$k] = $value;
						}
					}
				}
			}
		}

		if (isset($sum_array["values"])) {
			$num_ds = count($sum_array["values"]);

			/* now we must re-calculate the 95th max */
			for ($j=0; $j<count($sum_array["values"]); $j++) { /* each data source item */
				for ($k=0; $k<count($sum_array["values"][$j]); $k++) { /* each rrd row */
					if (isset($sum_array["values"][$j][$k])) {
						$value = $sum_array["values"][$j][$k];
					}else{
						$value = 0;
					}

					if (isset($sum_array["values"][$num_ds][$k])) {
						$sum_array["values"][$num_ds][$k] = max($value, $sum_array["values"][$num_ds][$k]);
					}else{
						$sum_array["values"][$num_ds][$k] = max($value, 0);
					}
				}
			}

			$sum_array["data_source_names"][$num_ds] = "ninety_fifth_percentile_maximum";
		}

		$fetch_array = $sum_array;

	}else{
		$fetch_array = rrdtool_function_fetch($local_data_id, $start_seconds, $end_seconds, $resolution);
	}

	/* loop through each regexp determined above (or each data source) */
	for ($i=0; $i<count($fetch_array["data_source_names"]); $i++) {
		if (isset($fetch_array["values"][$i])) {
			$values_array = $fetch_array["values"][$i];

			/* sort the array in descending order */
			rsort($values_array, SORT_NUMERIC);
		}

		/* grab the 95% row (or 5% in reverse) and use that as our 95th percentile
		value */
		$target = ((count($values_array) + 1) * .05);
		$target = sprintf("%d", $target);

		if (empty($values_array[$target])) { $values_array[$target] = 0; }

		/* collect 95th percentile values in this array so we can return them */
		$return_array{$fetch_array["data_source_names"][$i]} = $values_array[$target];
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
	$fetch_array = rrdtool_function_fetch($local_data_id, $start_time, $end_time, $rra_steps * $ds_steps);

	if ((!isset($fetch_array["data_source_names"])) || (count($fetch_array["data_source_names"]) == 0)) {
		return;
	}

	$return_array = array();

	/* loop through each regexp determined above (or each data source) */
	for ($i=0;$i<count($fetch_array["data_source_names"]);$i++) {
		$sum = 0;

		if (isset($fetch_array["values"][$i])) {
			$values_array = $fetch_array["values"][$i];

			for ($j=0;$j<count($fetch_array["values"][$i]);$j++) {
				$sum += $fetch_array["values"][$i][$j];
			}

			if (count($fetch_array["values"][$i]) != 0) {
				$sum = ($sum * $ds_steps * $rra_steps);
			}else{
				$sum = 0;
			}

			/* collect 95th percentile values in this array so we can return them */
			$return_array{$fetch_array["data_source_names"][$i]} = $sum;
		}
	}

	return $return_array;
}

/* variable_ninety_fifth_percentile - given a 95th percentile variable, calculate the 95th percentile
     and format it for display on the graph
   @arg $regexp_match_array - the array that contains each argument in the 95th percentile variable. it
     should be formatted like so:
       $arr[0] // full variable string
       $arr[1] // bits or bytes
       $arr[2] // power of 10 divisor
       $arr[3] // current, total, max, total_peak, all_max_current, all_max_peak
       $arr[4] // digits of floating point precision
   @arg $graph_item - an array that contains the current graph item
   @arg $graph_items - an array that contains all graph items
   @arg $graph_start - the start time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $graph_end - the end time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $seconds_between_graph_updates - the number of seconds between each update on the graph which
     varies depending on the RRA in use
   @returns - a string containg the 95th percentile suitable for placing on the graph */
function variable_ninety_fifth_percentile(&$regexp_match_array, &$graph_item, &$graph_items, $graph_start, $graph_end) {
	global $graph_item_types;

	if (sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if (($regexp_match_array[3] == "current") || ($regexp_match_array[3] == "max")) {
		$ninety_fifth_cache{$graph_item["local_data_id"]} = ninety_fifth_percentile($graph_item["local_data_id"], $graph_start, $graph_end);
	}elseif (($regexp_match_array[3] == "total") || ($regexp_match_array[3] == "total_peak") || ($regexp_match_array[3] == "all_max_current") || ($regexp_match_array[3] == "all_max_peak")) {
		for ($t=0;($t<count($graph_items));$t++) {
			if (!empty($graph_items[$t]["local_data_id"])) {
				$ninety_fifth_cache{$graph_items[$t]["local_data_id"]} = ninety_fifth_percentile($graph_items[$t]["local_data_id"], $graph_start, $graph_end);
			}
		}
	}elseif ($regexp_match_array[3] == "aggregate") {
		$local_data_array = array();
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_data_array[$graph_items[$t]["local_data_id"]] = $graph_items[$t]["local_data_id"];
			}
		}
                $ninety_fifth_cache{0} = ninety_fifth_percentile($local_data_array, $graph_start, $graph_end);
	}

	$ninety_fifth = 0;

	/* format the output according to args passed to the variable */
	if ($regexp_match_array[3] == "current") {
		$ninety_fifth = $ninety_fifth_cache{$graph_item["local_data_id"]}{$graph_item["data_source_name"]};
		$ninety_fifth = ($regexp_match_array[1] == "bits") ? $ninety_fifth * 8 : $ninety_fifth;
		$ninety_fifth /= pow(10,intval($regexp_match_array[2]));
	}elseif ($regexp_match_array[3] == "total") {
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_ninety_fifth = $ninety_fifth_cache{$graph_items[$t]["local_data_id"]}{$graph_items[$t]["data_source_name"]};
				$local_ninety_fifth = ($regexp_match_array[1] == "bits") ? $local_ninety_fifth * 8 : $local_ninety_fifth;
				$local_ninety_fifth /= pow(10,intval($regexp_match_array[2]));

				$ninety_fifth += $local_ninety_fifth;

			}
		}
	}elseif ($regexp_match_array[3] == "max") {
		$ninety_fifth = $ninety_fifth_cache{$graph_item["local_data_id"]}["ninety_fifth_percentile_maximum"];
		$ninety_fifth = ($regexp_match_array[1] == "bits") ? $ninety_fifth * 8 : $ninety_fifth;
		$ninety_fifth /= pow(10,intval($regexp_match_array[2]));
	}elseif ($regexp_match_array[3] == "total_peak") {
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_ninety_fifth = $ninety_fifth_cache{$graph_items[$t]["local_data_id"]}["ninety_fifth_percentile_maximum"];
				$local_ninety_fifth = ($regexp_match_array[1] == "bits") ? $local_ninety_fifth * 8 : $local_ninety_fifth;
				$local_ninety_fifth /= pow(10,intval($regexp_match_array[2]));

				$ninety_fifth += $local_ninety_fifth;
			}
		}
	}elseif ($regexp_match_array[3] == "all_max_current") {
		$ninety_fifth = 0;
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_ninety_fifth = $ninety_fifth_cache{$graph_items[$t]["local_data_id"]}{$graph_items[$t]["data_source_name"]};
				$local_ninety_fifth = ($regexp_match_array[1] == "bits") ? $local_ninety_fifth * 8 : $local_ninety_fifth;
				$local_ninety_fifth /= pow(10,intval($regexp_match_array[2]));

				if ($local_ninety_fifth > $ninety_fifth) {
					$ninety_fifth = $local_ninety_fifth;
				}
			}
		}
	}elseif ($regexp_match_array[3] == "all_max_peak") {
		$ninety_fifth = 0;
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_ninety_fifth = $ninety_fifth_cache{$graph_items[$t]["local_data_id"]}["ninety_fifth_percentile_maximum"];
				$local_ninety_fifth = ($regexp_match_array[1] == "bits") ? $local_ninety_fifth * 8 : $local_ninety_fifth;
				$local_ninety_fifth /= pow(10,intval($regexp_match_array[2]));

				if ($local_ninety_fifth > $ninety_fifth) {
					$ninety_fifth = $local_ninety_fifth;
				}
			}
		}
	}elseif ($regexp_match_array[3] == "aggregate") {
		$local_ninety_fifth = $ninety_fifth_cache{0}["ninety_fifth_percentile_maximum"];
		$local_ninety_fifth = ($regexp_match_array[1] == "bits") ? $local_ninety_fifth * 8 : $local_ninety_fifth;
		$local_ninety_fifth /= pow(10,intval($regexp_match_array[2]));
		$ninety_fifth = $local_ninety_fifth;
	}

	/* determine the floating point precision */
	if ((isset($regexp_match_array[5])) && (ereg("^[0-9]+$", $regexp_match_array[5]))) {
		$round_to = $regexp_match_array[5];
	}else{
		$round_to = 2;
	}

	/* return the final result and round off to two decimal digits */
	return round($ninety_fifth, $round_to);
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
function variable_bandwidth_summation(&$regexp_match_array, &$graph_item, &$graph_items, $graph_start, $graph_end, $rra_step, $ds_step) {
	global $graph_item_types;

	if (sizeof($regexp_match_array) == 0) {
		return 0;
	}

	if (is_numeric($regexp_match_array[4])) {
		$summation_timespan_start = -$regexp_match_array[4];
	}else{
		$summation_timespan_start = $graph_start;
	}

	if ($regexp_match_array[2] == "current") {
		$summation_cache{$graph_item["local_data_id"]} = bandwidth_summation($graph_item["local_data_id"], $summation_timespan_start, $graph_end, $rra_step, $ds_step);
	}elseif ($regexp_match_array[2] == "total") {
		for ($t=0;($t<count($graph_items));$t++) {
			if (!empty($graph_items[$t]["local_data_id"])) {
				$summation_cache{$graph_items[$t]["local_data_id"]} = bandwidth_summation($graph_items[$t]["local_data_id"], $summation_timespan_start, $graph_end, $rra_step, $ds_step);
			}
		}
	}elseif ($regexp_match_array[2] == "atomic") {
		$summation_cache{$graph_item["local_data_id"]} = bandwidth_summation($graph_item["local_data_id"], $summation_timespan_start, $graph_end, $rra_step, 1);
	}

	$summation = 0;

	/* format the output according to args passed to the variable */
	if (($regexp_match_array[2] == "current") || ($regexp_match_array[2] == "atomic")) {
		$summation = $summation_cache{$graph_item["local_data_id"]}{$graph_item["data_source_name"]};
	}elseif ($regexp_match_array[2] == "total") {
		for ($t=0;($t<count($graph_items));$t++) {
			if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
				$local_summation = $summation_cache{$graph_items[$t]["local_data_id"]}{$graph_items[$t]["data_source_name"]};

				$summation += $local_summation;
			}
		}
	}

	if (preg_match("/\d+/", $regexp_match_array[1])) {
		$summation /= pow(10,intval($regexp_match_array[1]));
	}elseif ($regexp_match_array[1] == "auto") {
		if ($summation < 1000) {
			$summation_label = "bytes";
		}elseif ($summation < 1000000) {
			$summation_label = "KB";
			$summation /= 1000;
		}elseif ($summation < 1000000000) {
			$summation_label = "MB";
			$summation /= 1000000;
		}elseif ($summation < 1000000000000) {
			$summation_label = "GB";
			$summation /= 1000000000;
		}else{
			$summation_label = "TB";
			$summation /= 1000000000000;
		}
	}

	/* determine the floating point precision */
	if (is_numeric($regexp_match_array[3])) {
		$round_to = $regexp_match_array[3];
	}else{
		$round_to = 2;
	}

	/* substitute in the final result and round off to two decimal digits */
	if (isset($summation_label)) {
		return round($summation, $round_to) . " $summation_label";
	}else{
		return round($summation, $round_to);
	}
}

?>
