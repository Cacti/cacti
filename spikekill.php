<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

include('./include/auth.php');
include_once(CACTI_PATH_LIBRARY . '/spikekill.php');

$debug = false;

if (isset_request_var('method')) {
	switch(get_nfilter_request_var('method')) {
		case 'stddev':
		case 'variance':
		case 'fill':
		case 'float':
		case 'absolute':
			break;

		default:
			print __("FATAL: Spike Kill method '%s' is Invalid", html_escape(get_nfilter_request_var('method'))) . PHP_EOL;

			exit(1);

			break;
	}
}

if (is_realm_allowed(1043)) {
	$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT data_template_rrd.local_data_id
		FROM graph_templates_item
		LEFT JOIN data_template_rrd
		ON graph_templates_item.task_item_id=data_template_rrd.id
		WHERE graph_templates_item.local_graph_id = ?',
		array(get_filter_request_var('local_graph_id')));

	$results = '';

	if (cacti_sizeof($local_data_ids)) {
		foreach ($local_data_ids as $local_data_id) {
			$data_source_path = get_data_source_path($local_data_id['local_data_id'], true);

			if ($data_source_path != '') {
				$html      = true;
				$dryrun    = false;
				$out_start = '';
				$out_end   = '';
				$avgnan    = '';
				$method    = '';
				$rrdfile   = $data_source_path;

				if (isset_request_var('dryrun')) {
					$dryrun = true;
				}

				if (isset_request_var('method')) {
					$method = get_nfilter_request_var('method');
				}

				if (isset_request_var('avgnan')) {
					$avgnan = get_nfilter_request_var('avgnan');
				}

				if (isset_request_var('outlier-start')) {
					$out_start = get_nfilter_request_var('outlier-start');
				}

				if (isset_request_var('outlier-end')) {
					$out_end = get_nfilter_request_var('outlier-end');
				}

				$spiker = new spikekill($rrdfile, $method, $avgnan, '', $out_start, $out_end);

				$spiker->dryrun = $dryrun;
				$spiker->html   = $html;

				$result = $spiker->remove_spikes();

				if ($debug) {
					if (!$result) {
						cacti_log("ERROR: SpikeKill failed for $rrdfile.  Message is " . $spiker->get_errors(), false, 'SPIKEKILL');
					} else {
						cacti_log("NOTICE: SpikeKill succeeded for $rrdfile.  Message is " . $spiker->get_output(), false, 'SPIKEKILL');
					}
				} else {
					if (!$result) {
						$results = $spiker->get_errors();
					} else {
						$results = $spiker->get_output();
					}
				}
			}
		}
	}

	print json_encode(array('local_graph_id' => get_request_var('local_graph_id'), 'results' => $results));
} else {
	print __('FATAL: Spike Kill Not Allowed') . PHP_EOL;
}
