<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
include_once('./lib/api_data_source.php');
include_once('./lib/boost.php');
include_once('./lib/rrd.php');
include_once('./lib/clog_webapi.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	default:
		if (!api_plugin_hook_function('changelog_action', get_request_var('action'))) {
			top_header();
			changelog_view();
			bottom_footer();
		}
		break;
}

/* -----------------------
    Functions
   ----------------------- */

function changelog_view() {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types, $local_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	top_header();

	/* present a tabbed interface */
	$tabs = array(
		'highlights' => __('Highlights'),
		'full'       => __('Full'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_cl_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'changelog.php?tab=' . $current_tab;

	$refresh = array(
		'seconds' => 999999,
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);
	$i = 0;

	/* draw the tabs */
	print "<div class='tabs'><nav><ul role='tablist'>";

	foreach (array_keys($tabs) as $tab_short_name) {
		print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
			" href='" . html_escape($config['url_path'] .
			'changelog.php?tab=' . $tab_short_name) .
			"'>" . $tabs[$tab_short_name] . "</a></li>";

		$i++;
	}

	api_plugin_hook('changelog_tab');

	print "</ul></nav></div>";

	$tab = get_request_var('tab');
	if (empty($tabs[$tab])) {
		$tab = reset(array_keys($tabs));
	}
	$header_label = __esc('Change Log [%s]', $tabs[$tab]);

	/* Display tech information */
	$changelog = file($config['base_path'] . '/CHANGELOG');

	$full = $current_tab == 'full';

	array_shift($changelog);
	array_shift($changelog);

	$ver     = '';
	$vers    = array();
	$details = array();
	$first   = '';
	foreach ($changelog as $line) {
		$line = trim($line);
		if (isset($line[0]) && ($line[0] == '*' || $line[0] == '-')) {
			$detail = false;
			if (preg_match('/-(issue|feature|security): (.*)/i', $line, $parts)) {
				$detail = array('desc' => $parts[2]);
			} elseif (preg_match('/-(issue|feature|security)#(\d+)\: (.*)/i', $line, $parts)) {
				$detail = array('desc' => $parts[3], 'issue' => $parts[2]);
			}

			$type = 'unknown';
			if (isset($parts[1])) {
				$type = strtolower($parts[1]);
				if ($type == 'security') $type = ' security';
			}

			if (!empty($detail)) {
				if (empty($details[$type])) {
					$details[$type] = array();
				}
				$details[$type][] = $detail;
			}
		} else if (!empty($line)) {
			if (!empty($ver)) {
				$vers[$ver] = $details;
				$first=true;
				$details = array();
			}

			if (count($vers) > 4) {
				break;
			}
			$ver = $line;
		}
	}

	krsort($vers);
	foreach($vers as $ver => $changelog) {
		if (!empty($ver)) {
			html_start_box(__('Version %s', $ver), '100%', '', '3', 'center', '');
			ksort($changelog);
			foreach ($changelog as $type => $details) {
				$output = false;
				foreach ($details as $detail) {
					$highlight = false;
					switch ($type) {
						case 'issue':
							$icon = '<i class="fas fa-wrench"></i>';
							break;
						case 'feature':
							$icon = '<i class="fas fa-rocket"></i>';
							$highlight = true;
							break;
						case ' security':
							$icon = '<i class="fas fa-shield-alt"></i>';
							$highlight = true;
							break;
						default;
							$icon = '<i class="far fa-question-circle"></i>';
							break;
					}

					if ($current_tab == 'full' || $highlight) {
						if (!$output) {
							html_section_header(html_escape($type), 4);
							$output = true;
						}

						form_alternate_row();

						print '<td>' . $icon . '</td><td>' . html_escape($type) . '</td><td>';
						if (!empty($detail['issue'])) {
							print '<a target="_blank" href="https://github.com/cacti/cacti/issues/' . html_escape($detail['issue']) . '">' . html_escape($detail['issue']) . '</a>';
						}
						print '</td><td>' . html_escape($detail['desc']) . '</td>';

						form_end_row();
					}
				}
			}
			html_end_box();
			if ($current_tab !== 'full') {
				break;
			}
		}
	}


	?>
	<script type='text/javascript'>
	$(function() {
		$('#tables').tablesorter({
			widgets: ['zebra'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-up',
			cssIconDesc: 'fa-sort-down',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});
	});
	</script>
	<?php

	bottom_footer();
}
