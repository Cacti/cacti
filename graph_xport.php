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

// since we'll have additional headers, tell php when to flush them
ob_start();

$guest_account = true;

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

// ================= input validation =================
gfrv('graph_start');
gfrv('graph_end');
gfrv('graph_height');
gfrv('graph_width');
gfrv('local_graph_id');
gfrv('rra_id');
gfrv('stdout');
// ====================================================

// flush the headers now
ob_end_clean();

cacti_session_close();

$graph_data_array = [];

// override: graph start time (unix time)
if (!ierv('graph_start') && is_numeric(grv('graph_start')) && grv('graph_start') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_start'] = grv('graph_start');
}

// override: graph end time (unix time)
if (!ierv('graph_end') && is_numeric(grv('graph_end')) && grv('graph_end') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_end'] = grv('graph_end');
}

// override: graph height (in pixels)
if (!ierv('graph_height') && is_numeric(grv('graph_height')) && grv('graph_height') < 3000) {
	$graph_data_array['graph_height'] = grv('graph_height');
}

// override: graph width (in pixels)
if (!ierv('graph_width') && is_numeric(grv('graph_width')) && grv('graph_width') < 3000) {
	$graph_data_array['graph_width'] = grv('graph_width');
}

// override: skip drawing the legend?
if (!ierv('graph_nolegend')) {
	$graph_data_array['graph_nolegend'] = grv('graph_nolegend');
}

// print RRDtool graph source?
if (!ierv('show_source')) {
	$graph_data_array['print_source'] = grv('show_source');
}

$graph_info = db_fetch_row_prepared('SELECT *
	FROM graph_templates_graph
	WHERE local_graph_id = ?',
	[grv('local_graph_id')]);

// for bandwidth, NThPercentile
$xport_meta = [];

// tell function we are csv
$graph_data_array['export_csv'] = true;

// Get graph export
$xport_array = rrdtool_function_xport(grv('local_graph_id'), grv('rra_id'), $graph_data_array, $xport_meta, $_SESSION[SESS_USER_ID]);

// Bail out early if xport returned no data
if (!is_array($xport_array) || !isset($xport_array['meta']['start'])) {
	cacti_log('WARNING: Graph export for Local Graph ID ' . grv('local_graph_id') . ' returned no data.  Check RRDtool errors in the Cacti log.', false, 'EXPORT');

	header('Content-type: text/html; charset=UTF-8');
	print __('Error: Graph export returned no data. Check the Cacti log for RRDtool errors.');

	// log the memory usage
	cacti_log("The Peak Graph XPORT Memory Usage was '" . memory_get_peak_usage() . "'", false, 'WEBUI', POLLER_VERBOSITY_MEDIUM);

	exit;
}

// Make graph title the suggested file name
$filename = $xport_array['meta']['title_cache'] . '.csv';

header('Content-type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Transfer-Encoding: binary');

if (cacti_is_https()) {
	header('Pragma: cache');
}

header('Cache-Control: max-age=15');

if (!isrv('stdout')) {
	header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if (isrv('format') && gnrv('format') == 'table') {
	$html = true;
} else {
	$html = false;
}

if (isset($xport_array['meta']['start'])) {
	if (!$html) {
		$output  = cacti_csv_cell(__('Title')) . ',' . cacti_csv_cell($xport_array['meta']['title_cache']) . "\n";
		$output .= cacti_csv_cell(__('Vertical Label')) . ',' . cacti_csv_cell($xport_array['meta']['vertical_label']) . "\n";

		$output .= cacti_csv_cell(__('Start Date')) . ',' . cacti_csv_cell(date('Y-m-d H:i:s', $xport_array['meta']['start'])) . "\n";
		$output .= cacti_csv_cell(__('End Date')) . ',' . cacti_csv_cell(date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step'] * ($xport_array['meta']['rows'] - 1) : $xport_array['meta']['end'])) . "\n";
		$output .= cacti_csv_cell(__('Step')) . ',' . cacti_csv_cell($xport_array['meta']['step']) . "\n";
		$output .= cacti_csv_cell(__('Total Rows')) . ',' . cacti_csv_cell($xport_array['meta']['rows']) . "\n";
		$output .= cacti_csv_cell(__('Graph ID')) . ',' . cacti_csv_cell($xport_array['meta']['local_graph_id']) . "\n";
		$output .= cacti_csv_cell(__('Host ID')) . ',' . cacti_csv_cell($xport_array['meta']['host_id']) . "\n";

		if (isset($xport_meta['NthPercentile'])) {
			foreach ($xport_meta['NthPercentile'] as $item) {
				$output .= cacti_csv_cell(__('Nth Percentile')) . ',' . cacti_csv_cell($item['value']) . ',' . cacti_csv_cell($item['format']) . "\n";
			}
		}

		if (isset($xport_meta['Summation'])) {
			foreach ($xport_meta['Summation'] as $item) {
				$output .= cacti_csv_cell(__('Summation')) . ',' . cacti_csv_cell($item['value']) . ',' . cacti_csv_cell($item['format']) . "\n";
			}
		}

		$output .= '""' . "\n";

		$header = cacti_csv_cell(__('Date'));

		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			$header .= ',' . cacti_csv_cell($xport_array['meta']['legend']['col' . $i]);
		}

		$output .= $header . "\n";

		if (isset($xport_array['data']) && is_array($xport_array['data'])) {
			$j = 0;

			foreach ($xport_array['data'] as $row) {
				$data = cacti_csv_cell(date('Y-m-d H:i:s', (isset($row['timestamp']) ? $row['timestamp'] : $xport_array['meta']['start'] + $j * $xport_array['meta']['step'])));

				for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
					$data .= ',' . cacti_csv_cell($row['col' . $i]);
				}

				$output .= $data . "\n";
				$j++;
			}

			// Full UTF-8 Output
			print "\xEF\xBB\xBF";
			print $output;
		}
	} else {
		print "<div class='cactiTable'>";

		print "<div class='cactiTableTitleRow'>
			<div class='cactiTableTitle'>" . __('Summary Details') . "</div>
			<div class='cactiTableButton'>
				<span><a href='#' role='link' class='download linkOverDark' id='graph_" . $xport_array['meta']['local_graph_id'] . "'>" . __('Download as CSV') . '</a></span>
			</div>
		</div>';

		// print the header information
		print "<table class='cactiTable selectable'>";

		print "<tr class='even'>";
		print '<td style="width:25%">' . __('Title') . '</td>';
		print '<td style="width:25%">' . htmle($xport_array['meta']['title_cache']) . '</td>';
		print '<td style="width:25%">' . __('Vertical Label') . '</td>';
		print '<td style="width:25%">' . htmle($xport_array['meta']['vertical_label']) . '</td>';
		print '</tr>';

		print "<tr class='odd'>";
		print '<td style="width:25%">' . __('Start Date') . '</td>';
		print '<td style="width:25%">' . date('Y-m-d H:i:s', $xport_array['meta']['start']) . '</td>';
		print '<td style="width:25%">' . __('End Date') . '</td>';
		print '<td style="width:25%">' . date('Y-m-d H:i:s', ($xport_array['meta']['end'] == $xport_array['meta']['start']) ? $xport_array['meta']['start'] + $xport_array['meta']['step'] * ($xport_array['meta']['rows'] - 1) : $xport_array['meta']['end']) . '</td>';
		print '</tr>';

		print "<tr class='even'>";
		print '<td style="width:25%">' . __('Step') . '</td>';
		print '<td style="width:25%">' . $xport_array['meta']['step'] . '</td>';
		print '<td style="width:25%">' . __('Total Rows') . '</td>';
		print '<td style="width:25%">' . $xport_array['meta']['rows'] . '</td>';
		print '</tr>';

		print "<tr class='odd'>";
		print '<td style="width:25%">' . __('Graph ID') . '</td>';
		print '<td style="width:25%">' . $xport_array['meta']['local_graph_id'] . '</td>';
		print '<td style="width:25%">' . __('Host ID') . '</td>';
		print '<td style="width:25%">' . $xport_array['meta']['host_id'] . '</td>';
		print '</tr>';

		$class = 'even';
		$index = 0;

		if (isset($xport_meta['NthPercentile'])) {
			foreach ($xport_meta['NthPercentile'] as $index => $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}

				if ($index % 2 == 0) {
					if ($index > 0) {
						print '</tr>';
					}

					print "<tr class='$class'>";
				}

				print '<td style="width:25%">' . __('Nth Percentile') . ' [ ' . htmle($item['format']) . ' ]</td>';
				print '<td style="width:25%">' . htmle($item['value']) . '</td>';
			}

			if ($index % 2 != 0) {
				print '</tr>';
			}
		}

		$index = 0;

		if (isset($xport_meta['Summation'])) {
			foreach ($xport_meta['Summation'] as $index => $item) {
				if ($class == 'even') {
					$class = 'odd';
				} else {
					$class = 'even';
				}

				if ($index % 2 == 0) {
					if ($index > 0) {
						print '</tr>';
					}

					print "<tr class='$class'>";
				}

				print '<td style="width:25%">' . __('Summation') . ' [ ' . htmle($item['format']) . ' ]</td>';
				print '<td style="width:25%">' . htmle($item['value']) . '</td>';
			}

			if ($index % 2 != 0) {
				print '</tr>';
			}
		}

		print '</table>';
		print '</div><br>';

		// end of CSV header information

		print "<div class='wrapperTop'><div class='fake'></div></div>";
		print "<div class='wrapperMain' style='display:none;'>";

		print "<table id='csvExport' class='cactiTable'><thead>";

		print "<tr class='tableHeader'>
			<th class='tableSubHeaderColumn left ui-resizable'>" . __('Date') . '</th>';

		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			print "<th class='{sorter: \"numberFormat\"} tableSubHeaderColumn right ui-resizable'>" . htmle($xport_array['meta']['legend']['col' . $i]) . '</th>';
		}

		print '</tr></thead>';

		if (isset($xport_array['data']) && is_array($xport_array['data'])) {
			$j = 0;

			foreach ($xport_array['data'] as $row) {
				print "<tr><td class='left'>" . date('Y-m-d H:i:s', (isset($row['timestamp']) ? $row['timestamp'] : $xport_array['meta']['start'] + $j * $xport_array['meta']['step'])) . '</td>';

				for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
					$row_data = floatval($row['col' . $i]);

					if ($row_data > 1) {
						$row_data = trim(number_format_i18n(round($row_data, 3), 2, $graph_info['base_value']));
					} elseif ($row_data == 0) {
						$row_data = '-';

						if (!is_numeric($row['col' . $i])) {
							$row_data .= '(unexpected: ' . htmle($row['col' . $i]) . ')';
						}
					} else {
						$row_data = trim(number_format_i18n(round($row_data, 5), 4));
					}

					print "<td class='right'>$row_data</td>";
				}

				print '</tr>';
				$j++;
			}

			print '<tr><td>';

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

			print '</td></tr></table></div>';
		}
	}
}

// log the memory usage
cacti_log("The Peak Graph XPORT Memory Usage was '" . memory_get_peak_usage() . "'", false, 'WEBUI', POLLER_VERBOSITY_MEDIUM);
