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

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/spikekill.php');

$debug = false;

if (isrv('method')) {
	switch(gnrv('method')) {
		case 'stddev':
		case 'fill':
		case 'float':
		case 'absolute':
			break;
		default:
			print __("FATAL: Spike Kill method '%s' is Invalid", htmle(gnrv('method'))) . PHP_EOL;

			exit(1);
	}
}

if (is_realm_allowed(1043)) {
	$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT data_template_rrd.local_data_id
		FROM graph_templates_item
		LEFT JOIN data_template_rrd
		ON graph_templates_item.task_item_id=data_template_rrd.id
		WHERE graph_templates_item.local_graph_id = ?',
		[gfrv('local_graph_id')]);

	$results = '';

	if (cacti_sizeof($local_data_ids)) {
		foreach ($local_data_ids as $local_data_id) {
			if ($local_data_id['local_data_id'] > 0) {
				$data_source_path = get_data_source_path($local_data_id['local_data_id'], true);
			} else {
				$data_source_path = '';
			}

			if ($data_source_path != '') {
				$html      = true;
				$dryrun    = false;
				$out_start = '';
				$out_end   = '';
				$avgnan    = '';
				$method    = '';
				$rrdfile   = $data_source_path;

				if (isrv('dryrun')) {
					$dryrun = true;
				}

				if (isrv('method')) {
					$method = gnrv('method');
				}

				if (isrv('avgnan')) {
					$avgnan = gnrv('avgnan');
				}

				if (isrv('outlier-start')) {
					$out_start = gnrv('outlier-start');
				}

				if (isrv('outlier-end')) {
					$out_end = gnrv('outlier-end');
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

	print json_encode(['local_graph_id' => grv('local_graph_id'), 'results' => $results]);
} else {
	print __('FATAL: Spike Kill Not Allowed') . PHP_EOL;
}
