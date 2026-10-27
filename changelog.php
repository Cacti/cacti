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
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/boost.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/clog_webapi.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// set default action
set_default_action();

switch (grv('action')) {
	default:
		if (!api_plugin_hook_function('changelog_action', grv('action'))) {
			top_header();
			changelog_view();
			bottom_footer();
		}

		break;
}

function changelog_view() : void {
	// ================= input validation =================
	gfrv('tab', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z_A-Z]+)$/']]);
	// ====================================================

	top_header();

	// present a tabbed interface
	$tabs = [
		'highlights' => __('Highlights'),
		'full'       => __('Full'),
	];

	// set the default tab
	load_current_session_value('tab', 'sess_cl_tabs', 'summary');
	$current_tab = gnrv('tab');

	$page = 'changelog.php?tab=' . $current_tab;

	$refresh = [
		'seconds' => 999999,
		'page'    => $page,
		'logout'  => 'false'
	];

	set_page_refresh($refresh);
	$i = 0;

	// draw the tabs
	print "<div class='tabs'><nav><ul role='tablist'>";

	foreach (array_keys($tabs) as $tab_short_name) {
		print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
			" href='" . htmle(CACTI_PATH_URL .
			'changelog.php?tab=' . $tab_short_name) .
			"'>" . $tabs[$tab_short_name] . '</a></li>';

		$i++;
	}

	api_plugin_hook('changelog_tab');

	print '</ul></nav></div>';

	$tab = grv('tab');

	if (empty($tabs[$tab])) {
		$tab_keys = array_keys($tabs);
		$tab      = reset($tab_keys);
	}
	$header_label = __esc('Change Log [%s]', $tabs[$tab]);

	(new \Cacti\Controller\ChangelogController(\Cacti\View\TemplateRenderer::create(), CACTI_PATH_BASE . '/CHANGELOG'))
		->show($current_tab === 'full')->sendContent();

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
