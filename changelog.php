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
	html_start_box($header_label, '100%', '', '3', 'center', '');

	$changelog = file($config['base_path'] . '/CHANGELOG');

	$full = $current_tab == 'full';
	foreach($changelog as $s) {
		if (strlen(trim($s))) {
			$l = strtoupper($s);
			if (strpos($l, 'CHANGELOG') === false) {
				if (strpos($l, '-') === false) {
					if (!$full) {
						break;
					}

					html_section_header(__('Version %s', $s), 2);
				} else {
					$is_wanted = ($current_tab == 'full' || strpos($l, '-FEATURE') === 0 || strpos($l, '-SECURITY') === 0);
					if ($is_wanted) {
						form_alternate_row();
						print '<td>' . $s . '</td>';
						form_end_row();
					}
				}
			}
		}
	}

	html_end_box();

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
