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

include('./include/auth.php');

$actions = array(
	'install'   => __('Install'),
	'enable'    => __('Enable'),
	'disable'   => __('Disable'),
	'uninstall' => __('Uninstall'),
//	'check' => 'Check'
);

$status_names = array(
	-1 => __('Not Compatible'),
	0  => __('Not Installed'),
	1  => __('Active'),
	2  => __('Awaiting Configuration'),
	3  => __('Awaiting Upgrade'),
	4  => __('Installed')
);

/* get the comprehensive list of plugins */
$pluginslist = retrieve_plugin_list();

/* Check to see if we are installing, etc... */
$modes = array('installold', 'uninstallold', 'install', 'uninstall', 'disable', 'enable', 'check', 'moveup', 'movedown');

if (isset_request_var('mode') && in_array(get_nfilter_request_var('mode'), $modes) && isset_request_var('id')) {
	get_filter_request_var('id', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9]+)$/')));

	$mode = get_nfilter_request_var('mode');
	$id   = sanitize_search_string(get_request_var('id'));

	if (isset_request_var('header')) {
		$option = 'header=false';
	} else {
		$option = '';
	}

	switch ($mode) {
		case 'install':
			if (!in_array($id, $plugins_integrated)) {
				api_plugin_install($id);
			}

			if ($_SESSION['sess_plugins_state'] != '-3') {
				header('Location: plugins.php?state=5' . ($option != '' ? '&' . $option:''));
			} else {
				header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			}
			exit;
			break;
		case 'uninstall':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_uninstall($id);
			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;
			break;
		case 'disable':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_disable($id);
			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;
			break;
		case 'enable':
			if (!in_array($id, $pluginslist)) break;
			if (!in_array($id, $plugins_integrated)) {
				api_plugin_enable($id);
			}
			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;
			break;
		case 'check':
			if (!in_array($id, $pluginslist)) break;
			break;
		case 'moveup':
			if (!in_array($id, $pluginslist)) break;
			if (in_array($id, $plugins_integrated)) break;
			api_plugin_moveup($id);
			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;
			break;
		case 'movedown':
			if (!in_array($id, $pluginslist)) break;
			if (in_array($id, $plugins_integrated)) break;
			api_plugin_movedown($id);
			header('Location: plugins.php' . ($option != '' ? '&' . $option:''));
			exit;
			break;
	}
}

function retrieve_plugin_list () {
	$pluginslist = array();
	$temp = db_fetch_assoc('SELECT directory FROM plugin_config ORDER BY name');
	foreach ($temp as $t) {
		$pluginslist[] = $t['directory'];
	}
	return $pluginslist;
}

top_header();

update_show_current();

bottom_footer();

function plugins_temp_table_exists($table) {
	return sizeof(db_fetch_row("SHOW TABLES LIKE '$table'"));
}

function plugins_load_temp_table() {
	global $config, $plugins, $plugins_integrated;

	$pluginslist = retrieve_plugin_list();

	if (isset($_SESSION['plugin_temp_table'])) {
		$table = $_SESSION['plugin_temp_table'];
	} else {
		$table = 'plugin_temp_table_' . rand();
	}
	$x = 0;
	while ($x < 30) {
		if (!plugins_temp_table_exists($table)) {
			$_SESSION['plugin_temp_table'] = $table;

			db_execute("CREATE TEMPORARY TABLE IF NOT EXISTS $table LIKE plugin_config");
			db_execute("TRUNCATE $table");
			db_execute("INSERT INTO $table SELECT * FROM plugin_config");

			break;
		} else {
			$table = 'plugin_temp_table_' . rand();
		}
		$x++;
	}

	$path = $config['base_path'] . '/plugins/';
	$dh = opendir($path);
	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if (is_dir("$path/$file") && file_exists("$path/$file/setup.php") && !in_array($file, $pluginslist) && !in_array($file, $plugins_integrated)) {
				if (file_exists("$path/$file/INFO")) {
					$cinfo[$file] = plugin_load_info_file("$path/$file/INFO");

					if (!isset($cinfo[$file]['author']))   $cinfo[$file]['author']   = __('Unknown');
					if (!isset($cinfo[$file]['homepage'])) $cinfo[$file]['homepage'] = __('Not Stated');
					if (isset($cinfo[$file]['webpage']))   $cinfo[$file]['homepage'] = $cinfo[$file]['webpage'];
					if (!isset($cinfo[$file]['longname'])) $cinfo[$file]['longname'] = ucfirst($file);

					$cinfo[$file]['status'] = 0;
					$pluginslist[] = $file;

					if (!isset($cinfo[$file]['compat']) || cacti_version_compare(CACTI_VERSION, $cinfo[$file]['compat'], '<')) {
						$cinfo[$file]['status'] = -1;
					}
				} else {
					$cinfo[$file] = array('name' => ucfirst($file), 'longname' => '', 'author' => '', 'homepage' => '', 'version' => '', 'compat' => '0.8');
					$cinfo[$file]['status'] = -1;
				}

				$pluginslist[] = $file;

				db_execute_prepared("REPLACE INTO $table
					(directory, name, status, author, webpage, version)
					VALUES (?, ?, ?, ?, ?, ?)",
					array(
						$file,
						$cinfo[$file]['longname'],
						$cinfo[$file]['status'],
						$cinfo[$file]['author'],
						$cinfo[$file]['homepage'],
						$cinfo[$file]['version']));
			}
		}

		closedir($dh);
	}

	return $table;
}

function update_show_current () {
	global $plugins, $pluginslist, $config, $status_names, $actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-3'
			)
	);

	validate_store_request_vars($filters, 'sess_plugins');
	/* ================= input validation ================= */

	$table = plugins_load_temp_table();

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL  = 'plugins.php?header=false';
		strURL += '&filter='+$('#filter').val();
		strURL += '&rows='+$('#rows').val();
		strURL += '&state='+$('#state').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'plugins.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_plugins').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Plugin Management'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_plugins' method='get' action='plugins.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='state' name='state' onChange='applyFilter()'>
							<option value='-3'<?php if (get_request_var('state') == '-3') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('state') == '1') {?> selected<?php }?>><?php print __('Active');?></option>
							<option value='4'<?php if (get_request_var('state') == '4') {?> selected<?php }?>><?php print __('Installed');?></option>
							<option value='5'<?php if (get_request_var('state') == '5') {?> selected<?php }?>><?php print __('Active/Installed');?></option>
							<option value='2'<?php if (get_request_var('state') == '2') {?> selected<?php }?>><?php print __('Configuration Issues');?></option>
							<option value='0'<?php if (get_request_var('state') == '0') {?> selected<?php }?>><?php print __('Not Installed');?></option>
						</select>
					</td>
					<td>
						<?php print __('Plugins');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE ($table.name LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('state') > -3) {
		if (get_request_var('state') == 5) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' status IN(1,4)';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' status=' . get_request_var('state');
		}
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM $table
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql_order = str_replace('version ', 'version+0 ', $sql_order);
	$sql_order = str_replace('id DESC', 'id ASC', $sql_order);

	$plugins = db_fetch_assoc("SELECT *
		FROM $table
		$sql_where
		$sql_order
		$sql_limit");

	db_execute("DROP TABLE $table");

	$nav = html_nav_bar('plugins.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Plugins'), 'page', 'main');

	form_start('plugins.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'nosort' => array('display' => __('Actions'), 'align' => 'left', 'sort' => '', 'tip' => __('Actions available include \'Install\', \'Activate\', \'Disable\', \'Enable\', \'Uninstall\'.')),
		'directory' => array('display' => __('Plugin Name'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name for this Plugin.  The name is controlled by the directory it resides in.')),
		'name' => array('display' => __('Plugin Description'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('A description that the Plugins author has given to the Plugin.')),
		'status' => array('display' => __('Status'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The status of this Plugin.')),
		'author' => array('display' => __('Author'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The author of this Plugin.')),
		'version' => array('display' => __('Version'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The version of this Plugin.')),
		'id' => array('display' => __('Load Order'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The load order of the Plugin.  You can change the load order by first sorting by it, then moving a Plugin either up or down.')));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1);

	$i = 0;
	if (sizeof($plugins)) {
		$j = 0;
		foreach ($plugins as $plugin) {
			if ((isset($plugins[$j+1]) && $plugins[$j+1]['status'] < 0) || (!isset($plugins[$j+1]))) {
				$last_plugin = true;
			} else {
				$last_plugin = false;
			}
			if ($plugin['status'] <= 0 || (get_request_var('sort_column') != 'id')) {
				$load_ordering = false;
			} else {
				$load_ordering = true;
			}

			form_alternate_row('', true);

			print format_plugin_row($plugin, $last_plugin, $load_ordering);

			$i++;

			$j++;
		}
	} else {
		print '<tr><td><em>' . __('No Plugins Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($plugins)) {
		print $nav;
	}

	form_end();
}

function format_plugin_row($plugin, $last_plugin, $include_ordering) {
	global $status_names, $config;
	static $first_plugin = true;

	$row = plugin_actions($plugin);

	$row .= "<td><a href='" . htmlspecialchars($plugin['webpage']) . "' target='_blank'>" . (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", ucfirst($plugin['directory'])) : ucfirst($plugin['directory'])) . '</a>' . (is_dir($config['base_path'] . '/plugins/' . $plugin['directory']) ? '':' (<span class="txtErrorText">' . __('ERROR: Directory Missing') . '</span>)') . '</td>';

	$row .= "<td class='nowrap'>" . filter_value($plugin['name'], get_request_var('filter')) . "</td>\n";

	if ($plugin['status'] == '-1') {
		$status = plugin_is_compatible($plugin['directory']);
		$row .= "<td class='nowrap'>" . __('Not Compatible, %s', $status['requires']) . "</td>\n";
	}else{
		$row .= "<td class='nowrap'>" . $status_names[$plugin['status']] . "</td>\n";
	}

	$row .= "<td class='nowrap'>" . $plugin['author'] . "</td>\n";
	$row .= "<td class='right'>"  . $plugin['version'] . "</td>\n";

	if ($include_ordering) {
		$row .= "<td class='nowrap right'>";
		if (!$first_plugin) {
			$row .= "<a class='pic fa fa-caret-up moveArrow' href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=moveup&id=' . $plugin['directory']) . "' title='" . __esc('Order Before Previous Plugin') . "'></a>";
		} else {
			$row .= '<span class="moveArrowNone"></span>';
		}
		if (!$last_plugin) {
			$row .= "<a class='pic fa fa-caret-down moveArrow' href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=movedown&id=' . $plugin['directory']) . "' title='" . __esc('Order After Next Plugin') . "'></a>";
		} else {
			$row .= '<span class="moveArrowNone"></span>';
		}
		$row .= "</td>\n";
	} else {
		$row .= "<td></td>\n";
	}

	$row .= "</tr>\n";

	if ($include_ordering) {
		$first_plugin = false;
	}

	return $row;
}

function plugin_actions($plugin) {
	global $config;

	$link = '<td>';
	switch ($plugin['status']) {
		case '0': // Not Installed
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=install&id=' . $plugin['directory']) . "' title='" . __esc('Install Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/cog_add.png'></a>";
			$link .= "<img align='absmiddle' src='" . $config['url_path'] . "images/view_none.gif'>";
			break;
		case '1':	// Currently Active
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=disable&id=' . $plugin['directory']) . "' title='" . __esc('Disable Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/stop.png'></a>";
			break;
		case '2': // Configuration issues
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			break;
		case '4':	// Installed but not active
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			$link .= "<a href='" . htmlspecialchars($config['url_path'] . 'plugins.php?mode=enable&id=' . $plugin['directory']) . "' title='" . __esc('Enable Plugin') . "' class='linkEditMain'><img align='absmiddle' src='" . $config['url_path'] . "images/accept.png'></a>";
			break;
		default: // Old PIA
			$link .= "<a href='#' title='" . __esc('Plugin is not compatible') . "' class='linkEditMain'><img align='absmiddle' src='images/cog_error.png'></a>";
			break;
	}
	$link .= '</td>';

	return $link;
}

