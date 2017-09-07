<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* since we'll have additional headers, tell php when to flush them */
ob_start();

$guest_account = true;

include('./include/auth.php');
include_once('./lib/rrd.php');

/* ================= input validation ================= */
get_filter_request_var('graph_start');
get_filter_request_var('graph_end');
get_filter_request_var('graph_height');
get_filter_request_var('graph_width');
get_filter_request_var('local_graph_id');
get_filter_request_var('rra_id');
get_filter_request_var('stdout');
/* ==================================================== */

/* flush the headers now */
ob_end_clean();

session_write_close();

$graph_data_array = array();

/* override: graph start time (unix time) */
if (!isempty_request_var('graph_start') && is_numeric(get_request_var('graph_start')) && get_request_var('graph_start') < 1600000000) {
	$graph_data_array['graph_start'] = get_request_var('graph_start');
}

/* override: graph end time (unix time) */
if (!isempty_request_var('graph_end') && is_numeric(get_request_var('graph_end')) && get_request_var('graph_end') < 1600000000) {
	$graph_data_array['graph_end'] = get_request_var('graph_end');
}

/* override: graph height (in pixels) */
if (!isempty_request_var('graph_height') && is_numeric(get_request_var('graph_height')) && get_request_var('graph_height') < 3000) {
	$graph_data_array['graph_height'] = get_request_var('graph_height');
}

/* override: graph width (in pixels) */
if (!isempty_request_var('graph_width') && is_numeric(get_request_var('graph_width')) && get_request_var('graph_width') < 3000) {
	$graph_data_array['graph_width'] = get_request_var('graph_width');
}

/* override: skip drawing the legend? */
if (!isempty_request_var('graph_nolegend')) {
	$graph_data_array['graph_nolegend'] = get_request_var('graph_nolegend');
}

/* print RRDTool graph source? */
if (!isempty_request_var('show_source')) {
	$graph_data_array['print_source'] = get_request_var('show_source');
}

$graph_info = db_fetch_row_prepared('SELECT * 
	FROM graph_templates_graph 
	WHERE local_graph_id = ?', 
	array(get_request_var('local_graph_id')));

/* for bandwidth, NThPercentile */
$xport_meta = array();

/* tell function we are csv */
$graph_data_array['export_csv'] = true;

/* Get graph export */
$xport_array = rrdtool_function_xport(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, $xport_meta);

/* Make graph title the suggested file name */
if (is_array($xport_array['meta'])) {
	$filename = $xport_array['meta']['title_cache'] . '.csv';
} else {
	$filename = 'graph_export.csv';
}

header('Content-type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Transfer-Encoding: binary');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
	header('Pragma: cache');
}

header('Cache-Control: max-age=15');
if (!isset_request_var('stdout')) {
	header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if (isset_request_var('format') && get_nfilter_request_var('format') == 'table') {
	$html = true;
} else {
	$html = false;
}

if (is_array($xport_array['meta']) && isset($xport_array['meta']['start'])) {
	if (!$html) {
		print '"' . __('Title') . '","'          . $xport_array['meta']['title_cache']    . '"' . "\n";
		print '"' . __('Vertical Label') . '","' . $xport_array['meta']['vertical_label'] . '"' . "\n";

		print '"' . __('Start Date') . '","'     . date('Y-m-d H:i:s', $xport_array['meta']['start']) . '"' . "\n";
		print '"' . __('End Date') . '","'       . date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step']*($xport_array['meta']['rows']-1) : $xport_array['meta']['end']) . '"' . "\n";
		print '"' . __('Step') . '","'           . $xport_array['meta']['step']                       . '"' . "\n";
		print '"' . __('Total Rows') . '","'     . $xport_array['meta']['rows']                       . '"' . "\n";
		print '"' . __('Graph ID') . '","'       . $xport_array['meta']['local_graph_id']             . '"' . "\n";
		print '"' . __('Host ID') . '","'        . $xport_array['meta']['host_id']                    . '"' . "\n";

		if (isset($xport_meta['NthPercentile'])) {
			foreach($xport_meta['NthPercentile'] as $item) {
				print '"' . __('Nth Percentile') . '","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
			}
		}

		if (isset($xport_meta['Summation'])) {
			foreach($xport_meta['Summation'] as $item) {
				print '"' . __('Summation') . '","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
			}
		}

		print '""' . "\n";

		$header = '"' . __('Date') . '"';
		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			$header .= ',"' . $xport_array['meta']['legend']['col' . $i] . '"';
		}
		print $header . "\n";
	} else {
		$second = "align='right' colspan='2'";
		print "<table align='center' width='100%' style='border: 1px solid #bbbbbb;'><tr><td>\n";
		print "<table class='cactiTable' align='center' width='100%'>\n";
		print "<tr class='tableHeader'><td colspan='2' class='linkOverDark' style='font-weight:bold;'>" . __('Summary Details') . "</td><td align='right'><a href='#' role='link' style='cursor:pointer;' class='download linkOverDark' id='graph_" . $xport_array['meta']['local_graph_id'] . "'>Download</a></td></tr>\n";
		print "<tr class='even'><td align='left'>" . __('Title') . "</td><td $second>"          . html_escape($xport_array['meta']['title_cache'])      . "</td></tr>\n";
		print "<tr class='odd'><td align='left'>" . __('Vertical Label') . "</td><td $second>" . html_escape($xport_array['meta']['vertical_label'])    . "</td></tr>\n";
		print "<tr class='even'><td align='left'>" . __('Start Date') . "</td><td $second>"     . date('Y-m-d H:i:s', $xport_array['meta']['start']) . "</td></tr>\n";
		print "<tr class='odd'><td align='left'>" . __('End Date') . "</td><td $second>"       . date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step']*($xport_array['meta']['rows']-1) : $xport_array['meta']['end']) . "</td></tr>\n";
		print "<tr class='even'><td align='left'>" . __('Step') . "</td><td $second>"           . $xport_array['meta']['step']                       . "</td></tr>\n";
		print "<tr class='odd'><td align='left'>" . __('Total Rows') . "</td><td $second>"     . $xport_array['meta']['rows']                        . "</td></tr>\n";
		print "<tr class='even'><td align='left'>" . __('Graph ID') . "</td><td $second>"       . $xport_array['meta']['local_graph_id']             . "</td></tr>\n";
		print "<tr class='odd'><td align='left'>" . __('Host ID') . "</td><td $second>"        . $xport_array['meta']['host_id']                     . "</td></tr>\n";

		$class = 'even';
		if (isset($xport_meta['NthPercentile'])) {
			foreach($xport_meta['NthPercentile'] as $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}
				print "<tr class='$class'><td>" . __('Nth Percentile') . "</td><td align='left'>" . $item['value'] . "</td><td align='right'>" . $item['format'] . "</td></tr>\n";
			}
		}
		if (isset($xport_meta['Summation'])) {
			foreach($xport_meta['Summation'] as $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}
				print "<tr class='$class'><td>" . __('Summation') . "</td><td align='left'>" . $item['value'] . "</td><td align='right'>" . $item['format'] . "</td></tr>\n";
			}
		}

		print "</table><br>\n";
		print "<table id='csvExport' class='cactiTable' align='center' width='100%'><thead>\n";

		print "<tr class='tableHeader'><th class='tableSubHeaderColumn left ui-resizable'>Date</th>";
		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			print "<th class='{sorter: \"numberFormat\"} tableSubHeaderColumn right ui-resizable'>" . $xport_array['meta']['legend']['col' . $i] . "</th>";
		}
		print "</tr></thead>\n";
	}
}

if (isset($xport_array['data']) && is_array($xport_array['data'])) {
	if (!$html) {
		$j = 1;
		foreach($xport_array['data'] as $row) {
			$data = '"' . date('Y-m-d H:i:s', (isset($row['timestamp']) ? $row['timestamp'] : $xport_array['meta']['start'] + $j*$xport_array['meta']['step'])) . '"';
			for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
				$data .= ',"' . $row['col' . $i] . '"';
			}
			print $data . "\n";
			$j++;
		}
	} else {
		$j = 1;
		foreach($xport_array['data'] as $row) {
			print "<tr><td align='left'>" . date('Y-m-d H:i:s', (isset($row['timestamp']) ? $row['timestamp'] : $xport_array['meta']['start'] + $j*$xport_array['meta']['step'])) . "</td>";
			for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
				if ($row['col' . $i] > 1) {
					print "<td align='right'>" . trim(number_format_i18n(round($row['col' . $i],3))) . '</td>';
				} elseif($row['col' . $i] == 0) {
					print "<td align='right'>-</td>";
				} else {
					print "<td align='right'>" . round($row['col' . $i],4) . '</td>';
				}
			}
			print "</tr>\n";
			$j++;
		}

		?>
		<script type='text/javascript'>
		$(function() { 
			$('#csvExport').tablesorter({
				widgets: ['zebra'], 
				widgetZebra: { css: ['even', 'odd'] }, 
				headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>', 
				cssIconAsc: 'fa-sort-asc', 
				cssIconDesc: 'fa-sort-desc', 
				cssIconNone: 'fa-sort', 
				cssIcon: 'fa' 
			}); 
		});
		</script>
		<?php
	}
}

/* log the memory usage */
cacti_log("The Peak Graph XPORT Memory Usage was '" . memory_get_peak_usage() . "'", FALSE, 'WEBUI', POLLER_VERBOSITY_MEDIUM);

