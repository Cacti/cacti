<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

cacti_session_close();

$graph_data_array = array();

/* override: graph start time (unix time) */
if (!isempty_request_var('graph_start') && is_numeric(get_request_var('graph_start')) && get_request_var('graph_start') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_start'] = get_request_var('graph_start');
}

/* override: graph end time (unix time) */
if (!isempty_request_var('graph_end') && is_numeric(get_request_var('graph_end')) && get_request_var('graph_end') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
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

/* print RRDtool graph source? */
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
$xport_array = rrdtool_function_xport(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, $xport_meta, $_SESSION[SESS_USER_ID]);

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
		$output  = '"' . __('Title') . '","'          . $xport_array['meta']['title_cache']    . '"' . "\n";
		$output .= '"' . __('Vertical Label') . '","' . $xport_array['meta']['vertical_label'] . '"' . "\n";

		$output .= '"' . __('Start Date') . '","'     . date('Y-m-d H:i:s', $xport_array['meta']['start']) . '"' . "\n";
		$output .= '"' . __('End Date') . '","'       . date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step']*($xport_array['meta']['rows']-1) : $xport_array['meta']['end']) . '"' . "\n";
		$output .= '"' . __('Step') . '","'           . $xport_array['meta']['step']                       . '"' . "\n";
		$output .= '"' . __('Total Rows') . '","'     . $xport_array['meta']['rows']                       . '"' . "\n";
		$output .= '"' . __('Graph ID') . '","'       . $xport_array['meta']['local_graph_id']             . '"' . "\n";
		$output .= '"' . __('Host ID') . '","'        . $xport_array['meta']['host_id']                    . '"' . "\n";

		if (isset($xport_meta['NthPercentile'])) {
			foreach($xport_meta['NthPercentile'] as $item) {
				$output .= '"' . __('Nth Percentile') . '","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
			}
		}

		if (isset($xport_meta['Summation'])) {
			foreach($xport_meta['Summation'] as $item) {
				$output .= '"' . __('Summation') . '","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
			}
		}

		$output .= '""' . "\n";

		$header = '"' . __('Date') . '"';
		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			$header .= ',"' . $xport_array['meta']['legend']['col' . $i] . '"';
		}
		$output .= $header . "\n";
	} else {
		print "<table class='cactiTable' class='center'>\n";

		print "<tr class='tableHeader'>
			<td>" . __('Summary Details') . "</td>
			<td class='right'><a href='#' role='link' style='cursor:pointer;' class='download linkOverDark' id='graph_" . $xport_array['meta']['local_graph_id'] . "'>" . __('Download') . "</a></td>
		</tr>\n";

		print "<tr class='even'>
			<td class='left' style='width:40%;'>" . __('Title') . "</td>
			<td class='right'>" . html_escape($xport_array['meta']['title_cache']) . "</td>
		</tr>\n";

		print "<tr class='odd'>
			<td class='left'>" . __('Vertical Label') . "</td>
			<td class='right'>" . html_escape($xport_array['meta']['vertical_label']) . "</td>
		</tr>\n";

		print "<tr class='even'>
			<td class='left'>" . __('Start Date') . "</td>
			<td class='right'>" . date('Y-m-d H:i:s', $xport_array['meta']['start']) . "</td>
		</tr>\n";

		print "<tr class='odd'>
			<td class='left'>" . __('End Date') . "</td>
			<td class='right'>" . date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step']*($xport_array['meta']['rows']-1) : $xport_array['meta']['end']) . "</td>
		</tr>\n";

		print "<tr class='even'>
			<td class='left'>" . __('Step') . "</td>
			<td class='right'>" . $xport_array['meta']['step'] . "</td>
		</tr>\n";

		print "<tr class='odd'>
			<td class='left'>" . __('Total Rows') . "</td>
			<td class='right'>" . $xport_array['meta']['rows'] . "</td>
		</tr>\n";

		print "<tr class='even'>
			<td class='left'>" . __('Graph ID') . "</td>
			<td class='right'>" . $xport_array['meta']['local_graph_id'] . "</td>
		</tr>\n";

		print "<tr class='odd'>
			<td class='left'>"  . __('Host ID') . "</td>
			<td class='right'>" . $xport_array['meta']['host_id'] . "</td>
		</tr>\n";

		$class = 'even';
		if (isset($xport_meta['NthPercentile'])) {
			foreach($xport_meta['NthPercentile'] as $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}

				print "<tr class='$class'>
					<td class='left'>" . __('Nth Percentile') . ' [ ' . html_escape($item['format']) . " ]</td>
					<td class='right'>" . html_escape($item['value']) . "</td>
				</tr>\n";
			}
		}

		if (isset($xport_meta['Summation'])) {
			foreach($xport_meta['Summation'] as $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}

				print "<tr class='$class'>
					<td class='left'>" . __('Summation') . ' [ ' . html_escape($item['format']) . " ]</td>
					<td class='right'>" . html_escape($item['value']) . "</td>
				</tr>\n";
			}
		}

		print "</table><br>\n";
		print "<div class='wrapperTop'><div class='fake'></div></div>\n";
		print "<div class='wrapperMain' style='display:none;'>\n";
		print "<table id='csvExport' class='cactiTable'><thead>\n";

		print "<tr class='tableHeader'>
			<th class='tableSubHeaderColumn left ui-resizable'>" . __('Date') . "</th>\n";

		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			print "<th class='{sorter: \"numberFormat\"} tableSubHeaderColumn right ui-resizable'>" . $xport_array['meta']['legend']['col' . $i] . "</th>\n";
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

			$output .= $data . "\n";
			$j++;
		}

		// Full UTF-8 Output
		print "\xEF\xBB\xBF";
		print $output;
	} else {
		$j = 1;

		foreach($xport_array['data'] as $row) {
			print "<tr><td class='left'>" . date('Y-m-d H:i:s', (isset($row['timestamp']) ? $row['timestamp'] : $xport_array['meta']['start'] + $j*$xport_array['meta']['step'])) . "</td>";

			for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
				$row_data = floatval($row['col'. $i]);

				if ($row_data > 1) {
					$row_data = trim(number_format_i18n(round($row_data, 3), 2, $graph_info['base_value']));
				} elseif($row_data == 0) {
					$row_data = '-';

					if (!is_numeric($row['col'.$i])) {
						$row_data .= '(unexpected: ' . $row['col' . $i] . ')';
					}
				} elseif (is_numeric($row_data)) {
					$row_data = trim(number_format_i18n(round($row_data, 5), 4));
				} else {
					$row_data = 'U';
				}

				print "<td class='right'>$row_data</td>";
			}

			print "</tr>\n";
			$j++;
		}

		print "<tr><td>\n";

		?>
		<script type='text/javascript'>
		$(function() {
			$('#csvExport').tablesorter({
				widgets: ['zebra'],
				widgetZebra: { css: ['even', 'odd'] },
				headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
				cssIconAsc: 'fa-sort-up',
				cssIconDesc: 'fa-sort-down',
				cssIconNone: 'fa-sort',
				cssIcon: 'fa'
			});

  			$('.wrapperTop').on('scroll', function(){
				$('.wrapperMain').scrollLeft($('.wrapperTop').scrollLeft());
			});
			$('.wrapperMain').on('scroll', function(){
				$('.wrapperTop').scrollLeft($('.wrapperMain').scrollLeft());
			});

			$(window).resize(function() {
				resizeWrapper();
			});
		});

		function resizeWrapper() {
			mainWidth = $(window).width() - $('#navigation').outerWidth() - 40;
			csvWidth = $('.wrapperMain').outerWidth();

			if (csvWidth > mainWidth) {
				$('.wrapperMain, .wrapperTop').css('width', mainWidth).css('overflow-x', 'scroll');
				$('.fake').css('width', csvWidth).css('height', '20px');
				$('.wrapperTop').css('height', '20px');
			} else {
				$('.wrapperTop').hide();
				$('.wrapperMain').css('width', '100%');
			}
			$('.wrapperMain').show();
		}
		</script>
		<?php

		print "</td></tr></table></div>\n";
	}
}

/* log the memory usage */
cacti_log("The Peak Graph XPORT Memory Usage was '" . memory_get_peak_usage() . "'", false, 'WEBUI', POLLER_VERBOSITY_MEDIUM);

