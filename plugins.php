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

global $local_db_cnn_id;

$actions = array(
	'install'   => __('Install'),
	'enable'    => __('Enable'),
	'disable'   => __('Disable'),
	'uninstall' => __('Uninstall'),
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
$modes = array(
	'installold',
	'uninstallold',
	'install',
	'uninstall',
	'disable',
	'enable',
	'check',
	'remote_enable',
	'remote_disable',
	'moveup',
	'movedown'
);

if (isset_request_var('mode') && in_array(get_nfilter_request_var('mode'), $modes) && isset_request_var('id')) {
	get_filter_request_var('id', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9 _]+)$/')));

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

			define('IN_PLUGIN_INSTALL', 1);

			if ($_SESSION['sess_plugins_state'] >= 0) {
				header('Location: plugins.php?state=5' . ($option != '' ? '&' . $option:''));
			} else {
				header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			}
			exit;

			break;
		case 'uninstall':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			define('IN_PLUGIN_INSTALL', 1);

			api_plugin_uninstall($id);

			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;

			break;
		case 'disable':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			api_plugin_disable($id);

			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;

			break;
		case 'enable':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			if (!in_array($id, $plugins_integrated)) {
				api_plugin_enable($id);
			}

			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;

			break;
		case 'check':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			break;
		case 'moveup':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			if (in_array($id, $plugins_integrated)) {
				break;
			}

			api_plugin_moveup($id);

			header('Location: plugins.php' . ($option != '' ? '?' . $option:''));
			exit;

			break;
		case 'movedown':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			if (in_array($id, $plugins_integrated)) {
				break;
			}

			api_plugin_movedown($id);

			header('Location: plugins.php' . ($option != '' ? '&' . $option:''));
			exit;

			break;
		case 'remote_enable':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			if (in_array($id, $plugins_integrated)) {
				break;
			}

			if ($config['poller_id'] > 1) {
				db_execute_prepared('UPDATE plugin_config
					SET status = 1
					WHERE directory = ?',
					array($id), false, $local_db_cnn_id);
			}

			header('Location: plugins.php' . ($option != '' ? '&' . $option:''));
			exit;

			break;
		case 'remote_disable':
			if (!in_array($id, $pluginslist)) {
				break;
			}

			if (in_array($id, $plugins_integrated)) {
				break;
			}

			if ($config['poller_id'] > 1) {
				db_execute_prepared('UPDATE plugin_config
					SET status = 4
					WHERE directory = ?',
					array($id), false, $local_db_cnn_id);
			}

			header('Location: plugins.php' . ($option != '' ? '&' . $option:''));
			exit;

			break;
	}
}

function retrieve_plugin_list() {
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
	return cacti_sizeof(db_fetch_row("SHOW TABLES LIKE '$table'"));
}

function plugins_load_temp_table() {
	global $config, $plugins, $plugins_integrated, $local_db_cnn_id;

	$table = 'plugin_temp_table_' . rand();

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

	if (!db_column_exists($table, 'requires')) {
		db_execute("ALTER TABLE $table
			ADD COLUMN remote_status tinyint(2) DEFAULT '0' AFTER status,
			ADD COLUMN capabilities varchar(128) DEFAULT NULL,
			ADD COLUMN requires varchar(80) DEFAULT NULL,
			ADD COLUMN infoname varchar(20) DEFAULT NULL");
	}

	if ($config['poller_id'] > 1) {
		$status = db_fetch_assoc('SELECT directory, status
			FROM plugin_config', false, $local_db_cnn_id);

		if (cacti_sizeof($status)) {
			foreach($status as $r) {
				$exists = db_fetch_cell_prepared("SELECT id
					FROM $table
					WHERE directory = ?",
					array($r['directory']));

				if ($exists) {
					$capabilities = api_plugin_remote_capabilities($r['directory']);

					db_execute_prepared("UPDATE $table
						SET capabilities = ?
						WHERE directory = ?",
						array($capabilities, $r['directory']));

					db_execute_prepared("UPDATE $table
						SET remote_status = ?
						WHERE directory = ?",
						array($r['status'], $r['directory']));
				} else {
					db_execute_prepared("UPDATE $table
						SET status = -2, remote_status = ?
						WHERE directory = ?",
						array($r['status'], $r['directory']));
				}
			}
		}
	}

	$path  = $config['base_path'] . '/plugins/';
	$dh    = opendir($path);
	$cinfo = array();
	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if (is_dir("$path$file") && file_exists("$path$file/setup.php") && !in_array($file, $plugins_integrated)) {
				$info_file = "$path$file/INFO";
				if (file_exists($info_file)) {
					$cinfo[$file] = plugin_load_info_file($info_file);
					$pluginslist[] = $file;
				} else {
					$cinfo[$file] = plugin_load_info_defaults($info_file, false);
				}

				$exists = db_fetch_cell_prepared("SELECT COUNT(*)
					FROM $table
					WHERE directory = ?",
					array($file));

				$infoname = ($cinfo[$file]['name'] == strtolower($cinfo[$file]['name']) ? ucfirst($cinfo[$file]['name']) : $cinfo[$file]['name']);
				if (!$exists) {
					db_execute_prepared("INSERT INTO $table
						(directory, name, status, author, webpage, version, requires, infoname)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
						array(
							$file,
							$cinfo[$file]['longname'],
							$cinfo[$file]['status'],
							$cinfo[$file]['author'],
							$cinfo[$file]['homepage'],
							$cinfo[$file]['version'],
							$cinfo[$file]['requires'],
							$infoname
						)
					);
				} else {
					db_execute_prepared("UPDATE $table
						SET infoname = ?, requires = ?
						WHERE directory = ?",
						array($infoname, $cinfo[$file]['requires'], $file));
				}
			}
		}

		closedir($dh);
	}

	$found_plugins = array_keys($cinfo);
	$plugins = db_fetch_assoc('SELECT id, directory, status FROM plugin_config');

	if ($plugins !== false && sizeof($plugins)) {
		foreach ($plugins as $plugin) {
			if (!in_array($plugin['directory'], $found_plugins)) {
				$plugin['status'] = '-5';

				$exists = db_fetch_cell_prepared("SELECT COUNT(*)
					FROM $table
					WHERE directory = ?",
					array($plugin['directory']));

				if (!$exists) {
					db_execute_prepared("INSERT INTO $table
						(directory, name, status, author, webpage, version, requires, infoname)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
						array(
							$plugin['directory'],
							$plugin['longname'],
							$plugin['status'],
							$plugin['author'],
							$plugin['homepage'],
							$plugin['version'],
							$plugin['requires'],
							($plugin['name'] == strtolower($plugin['name']) ? ucfirst($plugin['name']) : $plugin['name'])
						)
					);
				} else {
					db_execute_prepared("UPDATE $table
						SET status = ?
						WHERE directory = ?",
						array($plugin['status'], $plugin['directory']));
				}
			}
		}
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
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
			'default' => '-99'
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
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='state' name='state' onChange='applyFilter()'>
							<option value='-99'<?php if (get_request_var('state') == '-99') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='-98'<?php if (get_request_var('state') == '-98') {?> selected<?php }?>><?php print __('Plugin Error');?></option>
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
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		$sql_where = "WHERE ($table.name LIKE " . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			"$table.directory LIKE " . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (!isset_request_var('state')) {
		set_request_var('status', -99);
	}

	switch (get_request_var('state')) {
		case 5:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' status IN(1,4)';
			break;
		case -98:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' status < 0';
			break;
		case -99:
			break;
		default:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' status=' . get_request_var('state');
			break;
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

	$sql_order = str_replace('`version` ', 'INET_ATON(`version`) ', $sql_order);
	$sql_order = str_replace('version ', 'version+0 ', $sql_order);
	$sql_order = str_replace('id DESC', 'id ASC', $sql_order);

	$sql = "SELECT *
		FROM $table
		$sql_where
		$sql_order
		$sql_limit";

	$plugins = db_fetch_assoc($sql);

	$nav = html_nav_bar('plugins.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Plugins'), 'page', 'main');

	form_start('plugins.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'nosort' => array(
			'display' => __('Actions'),
			'align' => 'left',
			'sort' => '',
			'tip' => __('Actions available include \'Install\', \'Activate\', \'Disable\', \'Enable\', \'Uninstall\'.')
		),
		'directory' => array(
			'display' => __('Plugin Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name for this Plugin.  The name is controlled by the directory it resides in.')
		),
		'name' => array(
			'display' => __('Plugin Description'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('A description that the Plugins author has given to the Plugin.')
		),
		'status' => array(
			'display' => $config['poller_id'] == 1 ? __('Status'):__('Main / Remote Status'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The status of this Plugin.')
		),
		'author' => array(
			'display' => __('Author'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The author of this Plugin.')
		),
		'requires' => array(
			'display' => __('Requires'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('This Plugin requires the following Plugins be installed first.')
		),
		'version' => array(
			'display' => __('Version'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The version of this Plugin.')
		),
		'id' => array(
			'display' => __('Load Order'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The load order of the Plugin.  You can change the load order by first sorting by it, then moving a Plugin either up or down.')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1);

	$i = 0;
	if (cacti_sizeof($plugins)) {
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

			print format_plugin_row($plugin, $last_plugin, $load_ordering, $table);

			$i++;

			$j++;
		}
	} else {
		print '<tr><td colspan="' . cacti_sizeof($display_text) . '"><em>' . __('No Plugins Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($plugins)) {
		print $nav;
	}

	form_end();

	$uninstall_msg = __('Uninstalling this Plugin will remove all Plugin Data and Settings.  If you really want to Uninstall the Plugin, click \'Uninstall\' below.  Otherwise click \'Cancel\'');
	$uninstall_title = __('Are you sure you want to Uninstall?');

	?>
	<script type='text/javascript'>
	var url = '';

	$(function() {
		$('.piuninstall').click(function(event) {
			event.preventDefault();
			url = $(this).attr('href');

			var btnUninstall = {
				'Cancel': {
					text: '<?php print __('Cancel');?>',
					id: 'btnCancel',
					click: function() {
						$(this).dialog('close');
					}
				},
				'Uninstall': {
					text: '<?php print __('Uninstall');?>',
					id: 'btnUninstall',
					click: function() {
						$('#uninstalldialog').dialog('close');
						document.location = url;
					}
				}
			};

			$('body').remove('#uninstalldialog').append("<div id='uninstalldialog'><h4><?php print $uninstall_msg;?></h4></div>");

			$('#uninstalldialog').dialog({
				title: '<?php print $uninstall_title;?>',
				minHeight: 80,
				minWidth: 500,
				buttons: btnUninstall
			});
		});
	});
	</script>
	<?php

	db_execute("DROP TABLE $table");
}

function format_plugin_row($plugin, $last_plugin, $include_ordering, $table) {
	global $status_names, $config;
	static $first_plugin = true;

	$row = plugin_actions($plugin, $table);

	$row .= "<td><a href='" . html_escape($plugin['webpage']) . "' target='_blank' rel='noopener'>" . filter_value($plugin['infoname'], get_request_var('filter')) . '</a></td>';

	$row .= "<td class='nowrap'>" . filter_value($plugin['name'], get_request_var('filter')) . "</td>\n";

	if ($plugin['status'] == '-1') {
		$status = plugin_is_compatible($plugin['directory']);
		$row .= "<td class='nowrap'>" . __('Not Compatible, %s', $status['requires']);
	} elseif ($plugin['status'] < -1) {
		$row .= "<td class='nowrap'>" . __('Plugin Error');
	} else {
		$row .= "<td class='nowrap'>" . $status_names[$plugin['status']];
	}

	if ($config['poller_id'] > 1) {
		if (strpos($plugin['capabilities'], 'remote_collect:1') !== false || strpos($plugin['capabilities'], 'remote_poller:1') !== false) {
			if ($plugin['remote_status'] == '-1') {
				$status = plugin_is_compatible($plugin['directory']);
				$row .= ' / ' . __('Not Compatible, %s', $status['requires']);
			} elseif ($plugin['remote_status'] < -1) {
				$row .= ' / ' . __('Plugin Error');
			} else {
				$row .= ' / ' . $status_names[$plugin['remote_status']];
			}
		} else {
			$row .= ' / ' . __('N/A');
		}
	}

	$row .= '</td>';

	if ($plugin['requires'] != '') {
		$requires = explode(' ', $plugin['requires']);
		foreach($requires as $r) {
			$nr[] = ucfirst($r);
		}

		$requires = implode(', ', $nr);
	} else {
		$requires = $plugin['requires'];
	}

	$row .= "<td class='nowrap'>" . $plugin['author'] . "</td>\n";
	$row .= "<td class='nowrap'>" . $requires . "</td>\n";
	$row .= "<td class='right'>"  . $plugin['version'] . "</td>\n";

	if ($include_ordering) {
		$row .= "<td class='nowrap right'>";
		if (!$first_plugin) {
			$row .= "<a class='pic fa fa-caret-up moveArrow' href='" . html_escape($config['url_path'] . 'plugins.php?mode=moveup&id=' . $plugin['directory']) . "' title='" . __esc('Order Before Previous Plugin') . "'></a>";
		} else {
			$row .= '<span class="moveArrowNone"></span>';
		}
		if (!$last_plugin) {
			$row .= "<a class='pic fa fa-caret-down moveArrow' href='" . html_escape($config['url_path'] . 'plugins.php?mode=movedown&id=' . $plugin['directory']) . "' title='" . __esc('Order After Next Plugin') . "'></a>";
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

function plugin_required_for_others($plugin, $table) {
	$required_for_others = db_fetch_cell("SELECT GROUP_CONCAT(directory)
		FROM $table
		WHERE requires LIKE '%" . $plugin['directory'] . "%'
		AND status IN (1,4)");

	if ($required_for_others) {
		$parts = explode(',', $required_for_others);
		foreach($parts as $p) {
			$np[] = ucfirst($p);
		}

		return implode(', ', $np);
	} else {
		return false;
	}
}

function plugin_required_installed($plugin, $table) {
	$not_installed = '';
	api_plugin_can_install($plugin['infoname'], $not_installed);
	return $not_installed;
}

function plugin_actions($plugin, $table) {
	global $config, $pluginslist, $plugins_integrated;

	$link = '<td class="nowrap">';
	switch ($plugin['status']) {
		case '0': // Not Installed
			$not_installed = plugin_required_installed($plugin, $table);
		 	if ($not_installed != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Install Plugin.  The following Plugins must be installed first: %s', ucfirst($not_installed)) . "' class='linkEditMain'><img src='" . $config['url_path'] . "images/cog_error.png'></a>";
			} else {
				$link .= "<a href='" . html_escape($config['url_path'] . 'plugins.php?mode=install&id=' . $plugin['directory']) . "' title='" . __esc('Install Plugin') . "' class='piinstall linkEditMain'><img src='" . $config['url_path'] . "images/cog_add.png'></a>";
			}
			$link .= "<img src='" . $config['url_path'] . "images/view_none.gif'>";
			break;
		case '1':	// Currently Active
			$required = plugin_required_for_others($plugin, $table);
			if ($required != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Uninstall.  This Plugin is required by: %s', ucfirst($required)) . "'><img src='" . $config['url_path'] . "images/cog_error.png'></a>";
			} else {
				$link .= "<a class='piuninstall' href='" . html_escape($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "'><img src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			}
			$link .= "<a class='pidisable' href='" . html_escape($config['url_path'] . 'plugins.php?mode=disable&id=' . $plugin['directory']) . "' title='" . __esc('Disable Plugin') . "'><img src='" . $config['url_path'] . "images/stop.png'></a>";
			break;
		case '2': // Configuration issues
			$link .= "<a class='piuninstall' href='" . html_escape($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "'><img src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			break;
		case '4':	// Installed but not active
			$required = plugin_required_for_others($plugin, $table);
			if ($required != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Uninstall.  This Plugin is required by: %s', ucfirst($required)) . "'><img src='" . $config['url_path'] . "images/cog_error.png'></a>";
			} else {
				$link .= "<a class='piuninstall' href='" . html_escape($config['url_path'] . 'plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='" . __esc('Uninstall Plugin') . "'><img src='" . $config['url_path'] . "images/cog_delete.png'></a>";
			}
			$link .= "<a class='pienable' href='" . html_escape($config['url_path'] . 'plugins.php?mode=enable&id=' . $plugin['directory']) . "' title='" . __esc('Enable Plugin') . "'><img src='" . $config['url_path'] . "images/accept.png'></a>";
			break;
		case '-5': // Plugin directory missing
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory is missing!') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			break;
		case '-4': // Plugins should have INFO file since 1.0.0
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is not compatible (Pre-1.x)') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			break;
		case '-3': // Plugins can have spaces in their names
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directories can not include spaces') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			break;
		case '-2': // Naming issues
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory is not correct.  Should be \'%s\' but is \'%s\'', strtolower($plugin['infoname']), $plugin['directory']) . "' class='linkEditMain'><img src='images/cog_error.png'></a>";

			break;
		default: // Old PIA
			$path = $config['base_path'] . '/plugins/' . $plugin['directory'];
			$directory  = $plugin['name'];

			if (!file_exists("$path/setup.php")) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory \'%s\' is missing setup.php', $plugin['directory']) . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			} elseif (!file_exists("$path/INFO")) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is lacking an INFO file') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			} elseif (in_array($directory, $plugins_integrated)) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is integrated into Cacti core') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			} else {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is not compatible') . "' class='linkEditMain'><img src='images/cog_error.png'></a>";
			}

			break;
	}

	if ($config['poller_id'] > 1) {
		if (strpos($plugin['capabilities'], 'remote_collect:1') !== false || strpos($plugin['capabilities'], 'remote_poller:1') !== false) {
			if ($plugin['remote_status'] == 1) { // Installed and Active
				// TO-DO: Disabling here does not make much sense as the main will be replicated
				// with any change of any other plugin thus undoing.  Fix that moving forward
				//$link .= "<a class='pidisable' href='" . html_escape($config['url_path'] . 'plugins.php?mode=remote_disable&id=' . $plugin['directory']) . "' title='" . __esc('Disable Plugin Locally') . "'><img src='" . $config['url_path'] . "images/stop.png'></a>";
			} elseif ($plugin['remote_status'] == 4) { // Installed but inactive
				if ($plugin['status'] == 1) {
					$link .= "<a class='pienable' href='" . html_escape($config['url_path'] . 'plugins.php?mode=remote_enable&id=' . $plugin['directory']) . "' title='" . __esc('Enable Plugin Locally') . "'><img src='" . $config['url_path'] . "images/accept.png'></a>";
				}
			}
		}
	}

	$link .= '</td>';

	return $link;
}

