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
include_once('./lib/poller.php');

global $local_db_cnn_id;

/* the list of all known actions */
$actions = array(
	/* list functions */
	'list'           => __('Loaded Plugins'),
	'avail'          => __('Available Plugins'),

	/* classic calls */
	'install'        => __('Install'),
	'enable'         => __('Enable'),
	'disable'        => __('Disable'),
	'uninstall'      => __('Uninstall'),
	'check'          => __('Check Configuration'),
	'confirm'        => __('Install Prompt Confirmation'),

	/* removed plugin data handling */
	'remove_data'    => __('Remove Plugin Data'),

	/* load order switching  */
	'moveup'         => __('Move Up'),
	'movedown'       => __('Move Down'),

	/* plugin archiving */
	'archive'        => __('Archive'),
	'restore'        => __('Archive Restore'),
	'delete'         => __('Archive Delete'),

	/* manage downloaded content */
	'load'           => __('Install from Downloaded Plugins'),
	'readme'         => __('View the Plugins Readme File'),
	'changelog'      => __('View the Plugins ChangeLog File'),
	'latest'         => __('Fetch Latest Plugin Archives'),

	/* remote poller plugin functions */
	'remote_enable'  => __('Remote Enable'),
	'remote_disable' => __('Remote Disable'),

	/* drag and drop */
	'ajax_dnd'       => __('Drag and Drop'),
);

$status_names = array(
	-1 => __('Not Compatible'),
	-2 => __('Disabled Naming Errors'),
	-3 => __('Disabled Invalid Directory'),
	-4 => __('Disabled No INFO File'),
	-5 => __('Disabled Directory Missing'),
	0  => __('Loaded, Not Installed'),
	1  => __('Installed/Active'),
	2  => __('Configuration Issues'),
	3  => __('Awaiting Upgrade'),
	4  => __('Installed/Inactive'),
	5  => __('Installed or Active'),
	6  => __('Installable'),
	7  => __('Disabled by Error'),
	8  => __('Archived'),
);

/* temporary workaround till project finished */
db_execute("CREATE TABLE IF NOT EXISTS `plugin_available` (
	`plugin` varchar(32) NOT NULL DEFAULT '',
	`description` varchar(128) NOT NULL DEFAULT '',
	`author` varchar(40) NOT NULL DEFAULT '',
	`webpage` varchar(128) NOT NULL DEFAULT '',
	`tag_name` varchar(20) NOT NULL DEFAULT '',
	`published_at` timestamp NULL DEFAULT NULL,
	`compat` varchar(20) NOT NULL DEFAULT '',
	`requires` varchar(128) NOT NULL DEFAULT '',
	`body` blob DEFAULT NULL,
	`info` blob DEFAULT NULL,
	`readme` blob DEFAULT NULL,
	`changelog` blob DEFAULT NULL,
	`archive` longblob DEFAULT NULL,
	`last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	PRIMARY KEY (`plugin`,`tag_name`))
	ENGINE=InnoDB
	ROW_FORMAT=DYNAMIC");

db_execute("CREATE TABLE IF NOT EXISTS `plugin_archive` (
	`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
	`plugin` varchar(32) NOT NULL DEFAULT '',
	`description` varchar(64) NOT NULL DEFAULT '',
	`author` varchar(64) NOT NULL DEFAULT '',
	`webpage` varchar(255) NOT NULL DEFAULT '',
	`user_id` int(10) unsigned NOT NULL DEFAULT 0,
	`version` varchar(10) NOT NULL DEFAULT '',
	`requires` varchar(128) DEFAULT '',
	`compat` varchar(20) NOT NULL DEFAULT '',
	`dir_md5sum` varchar(32) NOT NULL DEFAULT '',
	`last_updated` timestamp NULL DEFAULT NULL,
	`archive` longblob DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `directory` (`plugin`))
	ENGINE=InnoDB
	ROW_FORMAT=DYNAMIC");

/* get the list of installed plugins */
$pluginslist = plugins_retrieve_plugin_list();

set_default_action('list');

/**
 * this is for legacy support for plugins like syslog
 * that are dependent on the mode request variable
 * to be set.
 */
if (isset_request_var('mode')) {
	set_request_var('action', get_nfilter_request_var('mode'));

	if (isset_request_var('id')) {
		set_request_var('plugin', get_nfilter_request_var('id'));
	}
}

$action = get_nfilter_request_var('action');

/* pre-check for actions that will fail by default */
if (isset_request_var('plugin')) {
	get_filter_request_var('plugin', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9 _]+)$/')));

	$plugin = sanitize_search_string(get_request_var('plugin'));

	$safe_actions = array(
		'changelog',
		'readme',
		'load',
		'install',
		'confirm',
		'delete',
		'ajax_dnd',
		'remove_data'
	);

	$display_action = ucwords(str_replace('_', ' ', $action));

	if (!in_array($plugin, $pluginslist, true) && !in_array($action, $safe_actions, true)) {
		raise_message('invalid_plugin', __('The action \'%s\' on Plugin \'%s\' can not be performed due to the Plugin in it\'s current state.', $display_action, $plugin), MESSAGE_LEVEL_ERROR);
		header('Location: plugins.php');
		exit;
	} elseif (in_array($plugin, $plugins_integrated, true)) {
		raise_message('invalid_plugin_action', __('The action \'%s\' \'%s\' on Plugin \'%s\' can not be taken as the Plugin is integrated.', $display_action, $plugin), MESSAGE_LEVEL_ERROR);
		header('Location: plugins.php');
		exit;
	}
} else {
	$plugin = '';
}

switch($action) {
	case 'list':
	case 'avail':
		top_header();

		update_show_current();

		bottom_footer();

		break;
	case 'load':
		$tag = get_nfilter_request_var('tag');

		api_plugin_archive_restore($plugin, $tag, 'available');

		header('Location: plugins.php?state=-99');
		exit;

		break;
	case 'readme':
		$tag = get_nfilter_request_var('tag');

		api_plugin_get_available_file_contents($plugin, $tag, 'readme');

		break;
	case 'changelog':
		$tag = get_nfilter_request_var('tag');

		api_plugin_get_available_file_contents($plugin, $tag, 'changelog');

		break;
	case 'latest':
		$running = is_process_running('pfetch', 'master', 0);

		if ($running === false) {
			$php_binary = read_config_option('path_php_binary');

			exec_background($php_binary, CACTI_PATH_CLI . '/fetch_plugins.php');

			usleep(300000);

			raise_message('fetch_background', __('The fetch latest plugins process has been launched into background.'), MESSAGE_LEVEL_INFO);
		} elseif ($running === true) {
			raise_message('fetch_background', __('The fetch latest plugins process has already been started.'), MESSAGE_LEVEL_WARN);
		}

		header('Location: plugins.php');

		exit;

		break;
	case 'install':
		api_plugin_install($plugin);

		define('IN_PLUGIN_INSTALL', 1);

		if ($_SESSION['sess_plugins_state'] >= 0) {
			header('Location: plugins.php?state=5');
		} else {
			header('Location: plugins.php');
		}

		break;
	case 'uninstall':
		define('IN_PLUGIN_INSTALL', 1);

		api_plugin_uninstall($plugin);

		header('Location: plugins.php');

		break;
	case 'remove_data':
		api_plugin_remove_data($plugin);

		header('Location: plugins.php');

		break;
	case 'disable':
		api_plugin_disable($plugin);

		header('Location: plugins.php');

		break;
	case 'enable':
		api_plugin_enable($plugin);

		header('Location: plugins.php');

		break;
	case 'check':
		$response = api_plugin_check_config($plugin);

		if ($response === true) {
			/* set the status as installable again if check passes */
			db_execute_prepared('UPDATE plugin_config
				SET status = 0
				WHERE directory = ?',
				array($plugin));

			raise_message('plugin_good', __('Plugin \'%s\' has passed it\'s Configuration Check test and can not be Installed', $plugin), MESSAGE_LEVEL_INFO);
		} elseif ($response === null) {
			raise_message('plugin_good', __('Plugin \'%s\' Check Configuration function returned a null response which is invalid.  Please check with Plugin Developer for an update.', $plugin), MESSAGE_LEVEL_WARN);
		}

		header('Location: plugins.php');

		break;
	case 'moveup':
		api_plugin_moveup($plugin);

		header('Location: plugins.php');

		break;
	case 'movedown':
		api_plugin_movedown($plugin);

		header('Location: plugins.php');

		break;
	case 'remote_enable':
		if ($config['poller_id'] > 1) {
			db_execute_prepared('UPDATE plugin_config
				SET status = 1
				WHERE directory = ?',
				array($plugin), false, $local_db_cnn_id);
		}

		header('Location: plugins.php' . ($option != '' ? '&' . $option:''));

		break;
	case 'remote_disable':
		if ($config['poller_id'] > 1) {
			db_execute_prepared('UPDATE plugin_config
				SET status = 4
				WHERE directory = ?',
				array($plugin), false, $local_db_cnn_id);
		}

		header('Location: plugins.php' . ($option != '' ? '&' . $option:''));

		break;
	case 'restore':
		$id = get_filter_request_var('id');

		api_plugin_archive_restore($plugin, $id, 'archive');

		header('Location: plugins.php');

		break;
	case 'delete':
		$id = get_filter_request_var('id');

		api_plugin_archive_remove($plugin, $id);

		header('Location: plugins.php');

		break;
	case 'archive':
		api_plugin_archive($plugin);

		header('Location: plugins.php');

		break;
	case 'ajax_dnd':
		$new_order = get_nfilter_request_var('dnd');

		api_plugin_reorder($new_order);

		header('Location: plugins.php');

		break;
}

exit;

function plugins_retrieve_plugin_list() {
	$pluginslist = array();

	$temp = db_fetch_assoc('SELECT directory AS plugin FROM plugin_config ORDER BY name');

	foreach ($temp as $t) {
		$pluginslist[] = $t['plugin'];
	}

	return $pluginslist;
}

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
			CHANGE COLUMN directory plugin varchar(32) NOT NULL default '',
			CHANGE COLUMN name description varchar(64) NOT NULL default '',
			ADD COLUMN compat varchar(64) NOT NULL default '',
			ADD COLUMN remote_status tinyint(2) DEFAULT '0' AFTER status,
			ADD COLUMN capabilities varchar(128) DEFAULT NULL,
			ADD COLUMN requires varchar(80) DEFAULT NULL,
			ADD COLUMN dir_md5sum varchar(32) DEFAULT NULL");
	}

	if ($config['poller_id'] > 1) {
		$status = db_fetch_assoc('SELECT directory AS plugin, status
			FROM plugin_config', false, $local_db_cnn_id);

		if (cacti_sizeof($status)) {
			foreach ($status as $r) {
				$exists = db_fetch_cell_prepared("SELECT id
					FROM $table
					WHERE plugin = ?",
					array($r['plugin']));

				if ($exists) {
					$capabilities = api_plugin_remote_capabilities($r['plugin']);

					db_execute_prepared("UPDATE $table
						SET capabilities = ?
						WHERE plugin = ?",
						array($capabilities, $r['plugin']));

					db_execute_prepared("UPDATE $table
						SET remote_status = ?
						WHERE plugin = ?",
						array($r['status'], $r['plugin']));
				} else {
					db_execute_prepared("UPDATE $table
						SET status = -2, remote_status = ?
						WHERE plugin = ?",
						array($r['status'], $r['plugin']));
				}
			}
		}
	}

	$path  = CACTI_PATH_PLUGINS . '/';
	$dh    = opendir($path);
	$cinfo = array();

	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if (is_dir("$path$file") && file_exists("$path$file/setup.php") && !in_array($file, $plugins_integrated, true)) {
				$info_file = "$path$file/INFO";

				$md5sum = md5sum_path("$path$file");

				if (file_exists($info_file)) {
					$cinfo[$file]  = plugin_load_info_file($info_file);
					$pluginslist[] = $file;
				} else {
					$cinfo[$file] = plugin_load_info_defaults($info_file, false);
				}

				$exists = db_fetch_cell_prepared("SELECT COUNT(*)
					FROM $table
					WHERE plugin = ?",
					array($file));

				$plugin_name = $cinfo[$file]['name'];

				if (!$exists) {
					db_execute_prepared("INSERT INTO $table
						(plugin, description, status, author, webpage, version, requires, compat, dir_md5sum)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
						array(
							$plugin_name,
							$cinfo[$file]['longname'],
							$cinfo[$file]['status'],
							$cinfo[$file]['author'],
							$cinfo[$file]['homepage'],
							$cinfo[$file]['version'],
							$cinfo[$file]['requires'],
							$cinfo[$file]['compat'],
							$md5sum
						)
					);
				} else {
					db_execute_prepared("UPDATE $table
						SET requires = ?, dir_md5sum = ?, compat = ?
						WHERE plugin = ?",
						array($cinfo[$file]['requires'], $md5sum, $cinfo[$file]['compat'], $plugin_name));
				}
			}
		}

		closedir($dh);
	}

	$found_plugins = array_keys($cinfo);

	$plugins = db_fetch_assoc('SELECT id, directory AS plugin, status FROM plugin_config');

	if (cacti_sizeof($plugins)) {
		foreach ($plugins as $plugin) {
			if (!in_array($plugin['plugin'], $found_plugins, true)) {
				$plugin['status'] = '-5';

				$exists = db_fetch_cell_prepared("SELECT COUNT(*)
					FROM $table
					WHERE plugin = ?",
					array($plugin['plugin']));

				if (!$exists) {
					$md5sum = md5sum_path("$path$file");

					db_execute_prepared("INSERT INTO $table
						(plugin, description, status, author, webpage, version, requires, dir_md5sum)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
						array(
							$plugin['plugin'],
							$plugin['longname'],
							$plugin['status'],
							$plugin['author'],
							$plugin['homepage'],
							$plugin['version'],
							$plugin['requires'],
							$md5sum
						)
					);
				} else {
					$md5sum = md5sum_path("$path$file");

					db_execute_prepared("UPDATE $table
						SET status = ?, dir_md5sum = ?
						WHERE plugin = ?",
						array($plugin['status'], $md5sum, $plugin['plugin']));
				}
			}
		}
	}

	return $table;
}

function update_show_current() {
	global $plugins, $pluginslist, $config, $status_names, $actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'type' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'pi.plugin',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'state' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-99'
		)
	);

	validate_store_request_vars($filters, 'sess_plugins');
	/* ================= input validation ================= */

	$table = plugins_load_temp_table();

	$uninstall_msg   = __esc('Uninstalling this Plugin and may remove all Plugin Data and Settings.  If you really want to Uninstall the Plugin, click \'Uninstall\' below.  Otherwise click \'Cancel\'.');
	$uninstall_title = __esc('Are you sure you want to Uninstall?');

	$rmdata_msg   = __esc('Removing Plugin Data and Settings for will remove all Plugin Data and Settings.  If you really want to Remove Data and Settings for this Plugin, click \'Remove Data\' below.  Otherwise click \'Cancel\'.');
	$rmdata_title = __esc('Are you sure you want to Remove all Plugin Data and Settings?');

	$resarchive_msg   = __esc('Restoring this Plugin Archive will overwrite the current Plugin directory.  If you really want to Restore this Plugin Archive, click \'Restore\' below.  Otherwise click \'Cancel\'.');
	$resarchive_title = __esc('Are you sure you want to Restore this Archive?');

	$rmarchive_msg   = __esc('Deleting this Plugin Archive is not reversible without a table restore.  If you really want to Delete the Plugin Archive, click \'Delete\' below.  Otherwise click \'Cancel\'.');
	$rmarchive_title = __esc('Are you sure you want to Delete this Archive?');

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
							<select id='state' name='state' onChange='applyFilter()' data-defaultLabel='<?php print __('Status');?>'>
								<option value='-99'<?php if (get_request_var('state') == '-99') {?> selected<?php }?>><?php print __('Loaded on Disk');?></option>
								<option value='0'<?php   if (get_request_var('state') == '0')   {?> selected<?php }?>><?php print __('Loaded and Not Installed');?></option>
								<option value='1'<?php   if (get_request_var('state') == '1')   {?> selected<?php }?>><?php print __('Installed and Active');?></option>
								<option value='4'<?php   if (get_request_var('state') == '4')   {?> selected<?php }?>><?php print __('Installed and Inactive');?></option>
								<option value='5'<?php   if (get_request_var('state') == '5')   {?> selected<?php }?>><?php print __('Installed or Active');?></option>
								<option value='2'<?php   if (get_request_var('state') == '2')   {?> selected<?php }?>><?php print __('Configuration Issues');?></option>
								<option value='7'<?php   if (get_request_var('state') == '7')   {?> selected<?php }?>><?php print __('Plugin Errors');?></option>
								<option value='6'<?php   if (get_request_var('state') == '6')   {?> selected<?php }?>><?php print __('Available for Install');?></option>
								<option value='8'<?php   if (get_request_var('state') == '8')   {?> selected<?php }?>><?php print __('Archived');?></option>
							</select>
						</td>
						<?php if (get_request_var('state') == 6 && read_config_option('github_allow_unsafe', true) == 'on') { ?>
						<td>
							<?php print __('Tag Type');?>
						</td>
						<td>
							<select id='type' name='type' onChange='applyFilter()' data-defaultLabel='<?php print __('All');?>'>
								<option value='-1'<?php if (get_request_var('type') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
								<option value='1'<?php  if (get_request_var('type') == '1')  {?> selected<?php }?>><?php print __('Non Develop');?></option>
								<option value='2'<?php  if (get_request_var('type') == '2')  {?> selected<?php }?>><?php print __('Develop');?></option>
								<option value='3'<?php  if (get_request_var('type') == '3')  {?> selected<?php }?>><?php print __('Newer Than Installed');?></option>
							</select>
						</td>
						<?php } else { ?>
						<td><input type='hidden' id='type' value='-1'></td>
						<?php } ?>
						<td>
							<?php print __('Plugins');?>
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()' data-defaultLabel='<?php print __('Plugins');?>'>
								<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='latest' value='<?php print __esc('Check Latest');?>' title='<?php print __esc('Fetch the list of the latest Cacti Plugins');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type="text/javascript">
			var url = '';

			function applyFilter() {
				if ($('#state').val() == 6) {
					strURL = 'plugins.php?action=avail';
				} else {
					strURL = 'plugins.php?action=list';
				}

				strURL += '&filter='+$('#filter').val();
				strURL += '&type='+$('#type').val();
				strURL += '&rows='+$('#rows').val();
				strURL += '&state='+$('#state').val();
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = 'plugins.php?action=list&clear=1';
				loadUrl({url:strURL})
			}

			function displayDialog(url, dialogTitle, dialogMessage, buttonContinue, buttonCancel, height, width) {
				if ($('#pidialog').dialog('instance')) {
					$('#pidialog').dialog('close');
				}

				var btnButtons = {
					'Cancel': {
						text: buttonCancel,
						id: 'btnCancel',
						click: function() {
							$(this).dialog('close');
						}
					},
					'Continue': {
						text: buttonContinue,
						id: 'btnContinue',
						click: function() {
							$(this).dialog('close');
							loadUrl({url: url});
						}
					}
				};

				var message = "<div id='pidialog' style='display:none;'><div>"+dialogMessage+"</div></div>";

				if ($('#pidialog').length == 0) {
					$('#main').append(message);
				} else {
					$('#pidialog').remove().append(message);
				}

				$('#pidialog').dialog({
					title: dialogTitle,
					minHeight: height,
					minWidth: width,
					buttons: btnButtons,
					open: function() {
						$('.ui-dialog-buttonpane > button:last').focus();
						$('#pidialog').offset().top;
					}
				});
			}

			function displayFileDialog(url, dialogTitle, height, width) {
				if ($('#pidialog').dialog('instance')) {
					$('#pidialog').dialog('close');
				}

				$.get(url, function(data) {
					if (data != '') {
						var message = "<div id='pidialog' style='display:none;'><div>"+DOMPurify.sanitize(data)+'</div></div>';

						if ($('#pidialog').length == 0) {
							$('#main').append(message);
						} else {
							$('#pidialog').remove().append(message);
						}

						$('#pidialog').dialog({
							title: dialogTitle,
							maxHeight: height,
							minWidth: width,
							open: function() {
									$('.ui-dialog-buttonpane > button:last').focus();
								$('#pidialog').offset().top;
							}
						});
					}
				});
			}

			$(function() {
				var sortColumn = '<?php print get_request_var('sort_column');?>';
				var dndActive  = <?php print read_config_option('drag_and_drop') == 'on' ? 'true':'false';?>;
				var tableState = <?php print get_request_var('state');?>

				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#latest').click(function() {
					strURL = 'plugins.php?action=latest';
					loadUrl({url:strURL});
				});

				$('#form_plugins').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				if (sortColumn == 'pi.id' && dndActive && tableState == -99) {
					$('#plugins_list2_child').attr('id', 'dnd');

					$('#dnd').tableDnD({
						onDrag: function(table, row) {
							console.log(table);
							console.log(row);
						},
						onDrop: function(table, row) {
							loadUrl({url:'plugins.php?action=ajax_dnd&'+$.tableDnD.serialize()})
						}
					});
				}

				$('.pirestore').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle    = '<?php print $resarchive_title;?>';
					var dialogMessage  = '<?php print $resarchive_msg;?>';
					var buttonContinue = '<?php print __('Restore Archive');?>';
					var buttonCancel   = '<?php print __('Cancel');?>';
					var url            = $(this).attr('href');

					displayDialog(url, dialogTitle, dialogMessage, buttonContinue, buttonCancel, 80, 400);
				});

				$('.pirmarchive').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle    = '<?php print $rmarchive_title;?>';
					var dialogMessage  = '<?php print $rmarchive_msg;?>';
					var buttonContinue = '<?php print __('Delete Archive');?>';
					var buttonCancel   = '<?php print __('Cancel');?>';
					var url            = $(this).attr('href');

					displayDialog(url, dialogTitle, dialogMessage, buttonContinue, buttonCancel, 80, 400);
				});

				$('.pirmdata').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle    = '<?php print $rmdata_title;?>';
					var dialogMessage  = '<?php print $rmdata_msg;?>';
					var buttonContinue = '<?php print __('Remove Data');?>';
					var buttonCancel   = '<?php print __('Cancel');?>';
					var url            = $(this).attr('href');

					displayDialog(url, dialogTitle, dialogMessage, buttonContinue, buttonCancel, 80, 400);
				});

				$('.piuninstall').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle    = '<?php print $uninstall_title;?>';
					var dialogMessage  = '<?php print $uninstall_msg;?>';
					var buttonContinue = '<?php print __('Uninstall');?>';
					var buttonCancel   = '<?php print __('Cancel');?>';
					var url            = $(this).attr('href');

					displayDialog(url, dialogTitle, dialogMessage, buttonContinue, buttonCancel, 80, 400);
				});

				$('.pireadme').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle = '<?php print __esc('Plugin Reame File');?>';
					var url         = $(this).attr('href');

					displayFileDialog(url, dialogTitle, 400, 700);
				});

				$('.pichangelog').off('click').on('click', function(event) {
					event.preventDefault();

					var dialogTitle = '<?php print __esc('Plugin ChangeLog File');?>';
					var url         = $(this).attr('href');

					displayFileDialog(url, dialogTitle, 400, 700);
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		switch(get_request_var('state')) {
			case 8:
				$sql_where = 'WHERE (
					pi.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.author LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.plugin LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.webpage LIKE '     . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.author LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.plugin LIKE '      . db_qstr('%' . get_request_var('filter') . '%') .
				')';

				break;
			case 6:
				$sql_where = 'WHERE (
					pi.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.author LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.plugin LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.webpage LIKE '     . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pa.author LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.plugin LIKE '      . db_qstr('%' . get_request_var('filter') . '%') .
				')';

				break;
			default:
				$sql_where = 'WHERE (
					pi.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.author LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.webpage LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR
					pi.plugin LIKE '  . db_qstr('%' . get_request_var('filter') . '%') .
				')';
		}
	}

	if (!isset_request_var('state')) {
		set_request_var('status', -99);
	}

	switch (get_request_var('state')) {
		case 6:
			/* show all matching plugins */
			if (read_config_option('github_allow_unsafe') == '') {
				$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pa.tag_name != "develop"';
			} else {
				if (get_request_var('type') == '1') {
					$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pa.tag_name != "develop"';
				} elseif (get_request_var('type') == '2') {
					$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pa.tag_name = "develop"';
				} elseif (get_request_var('type') == '3') {
					$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(pi.last_updated != "0000-00-00" AND  pi.last_updated < pa.published_at)';
				}
			}

			break;
		case 8:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pi.status IN(0,1,2,4,7) OR pi.status IS NULL';

			break;
		case 5:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pi.status IN(1,4)';

			break;
		case 0:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pi.status NOT IN(1,4) OR pi.status IS NULL';

			break;
		case 7:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pi.status = 7';

			break;
		case -99:
			break;

		default:
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' pi.status = ' . get_request_var('state');

			break;
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	switch(get_request_var('state')) {
		case 8:
			$total_rows = db_fetch_cell("SELECT COUNT(*)
				FROM plugin_archive AS pa
				LEFT JOIN $table AS pi
				ON pa.plugin = pi.plugin
				$sql_where");

			break;
		case 6:
			$total_rows = db_fetch_cell("SELECT COUNT(*)
				FROM plugin_available AS pa
				LEFT JOIN $table AS pi
				ON pa.plugin = pi.plugin
				$sql_where");

			break;
		default:
			$total_rows = db_fetch_cell("SELECT COUNT(*)
				FROM $table AS pi
				$sql_where");

			break;
	}

	/* set order and limits */
	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$sql_order = str_replace('`pa`.`version` ', 'INET_ATON(`pa`.`version`) ', $sql_order);
	$sql_order = str_replace('`pi`.`version` ', 'INET_ATON(`pi`.`version`) ', $sql_order);
	$sql_order = str_replace('id DESC', 'id ASC', $sql_order);

	if (get_request_var('state') == 8) {
		$sql_order = str_replace('`pi`.`plugin` ', '`pa`.`plugin` ', $sql_order);
		$sql_order = str_replace('pi.plugin ', 'pa.plugin ', $sql_order);
		$sql_order = str_replace('`pi`.last_updated ', '`pa`.`last_updated` ', $sql_order);
	} elseif (get_request_var('state') == 6) {
		$sql_order = str_replace('`pi`.`plugin` ', '`pa`.`plugin` ', $sql_order);
		$sql_order = str_replace('pi.plugin ', 'pa.plugin ', $sql_order);
		$sql_order = str_replace('`pi`.last_updated ', '`pa`.`last_updated` ', $sql_order);
	} else {
		$sql_order = str_replace('`pa`.`plugin` ', '`pi`.`plugin` ', $sql_order);
		$sql_order = str_replace('pa.plugin ', 'pi.plugin ', $sql_order);
		$sql_order = str_replace('`pa`.last_updated ', '`pi`.`last_updated` ', $sql_order);
	}

	switch(get_request_var('state')) {
		case 8:
			$sql = "SELECT pa.id, pa.plugin, pa.description, pi.status, pi.remote_status,
				pa.author, pa.webpage, pi.version, pi.capabilities, pi.requires, pi.last_updated,
				pa.requires AS archive_requires, pa.compat AS archive_compat, pa.version AS archive_version,
				pa.user_id, pa.last_updated AS archive_date, pa.dir_md5sum, length(archive) AS archive_length
				FROM plugin_archive AS pa
				LEFT JOIN $table AS pi
				ON pa.plugin = pi.plugin
				$sql_where
				$sql_order
				$sql_limit";

			break;
		case 6:
			$sql = "SELECT pi.id, pi.plugin, pi.status, pi.remote_status,
				pi.author, pi.webpage, pi.version, pi.capabilities, pi.requires, pi.last_updated,
				pa.plugin, pa.description AS avail_description,
				pa.author AS avail_author, pa.webpage AS avail_webpage,
				pa.compat AS avail_compat, pa.published_at AS avail_published, pa.tag_name AS avail_tag_name,
				pa.requires AS avail_requires, length(pa.changelog) AS changelog, length(archive) AS archive_length
				FROM plugin_available AS pa
				LEFT JOIN $table AS pi
				ON pa.plugin = pi.plugin
				$sql_where
				$sql_order
				$sql_limit";

			break;
		default:
			$sql = "SELECT *
				FROM $table AS pi
				$sql_where
				$sql_order
				$sql_limit";

			break;
	}

	$plugins = db_fetch_assoc($sql);

	$nav = html_nav_bar('plugins.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Plugins'), 'page', 'main');

	form_start('plugins.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	switch(get_request_var('state')) {
		case 8:
			$display_text = array(
				'nosort' => array(
					'display' => __('Actions'),
					'align'   => 'left',
					'sort'    => '',
					'tip'     => __('Actions available include \'Restore\', \'Delete\'.')
				),
				'pa.plugin' => array(
					'display' => __('Plugin Name'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The name for this Plugin.  The name is controlled by the directory it resides in.')
				),
				'pi.description' => array(
					'display' => __('Plugin Description'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('A description that the Plugins author has given to the Plugin.')
				),
				'pi.status' => array(
					'display' => $config['poller_id'] == 1 ? __('Status'):__('Main / Remote Status'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The Status of this available Plugin.  Loadable means it is currently not installed and can be loaded.')
				),
				'pi.author' => array(
					'display' => __('Author'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The author of this Plugin.')
				),
				'pa.compat' => array(
					'display' => __('Cacti'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The Cacti version ranges required to use this Plugin.')
				),
				'pi.version' => array(
					'display' => __('Installed Version'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The version of this Plugin.')
				),
				'pa.version' => array(
					'display' => __('Version'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The version of this Plugin.')
				),
				'pa.archive_length' => array(
					'display' => __('Size'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The compressed size of this Plugin in bytes.')
				),
				'requires' => array(
					'display' => __('Requires'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('This Plugin requires the following Plugins be installed first.')
				),
				'pa.last_updated' => array(
					'display' => __('Archive Date'),
					'align'   => 'right',
					'sort'    => 'DESC',
					'tip'     => __('The date that this Plugin was Archived.')
				),
				'pi.last_updated' => array(
					'display' => __('Installed/Upgraded'),
					'align'   => 'right',
					'sort'    => 'DESC',
					'tip'     => __('The date that this Plugin was last Installed or Upgraded.')
				),
			);

			break;
		case 6:
			$display_text = array(
				'nosort0' => array(
					'display' => __('Actions'),
					'align'   => 'left',
					'sort'    => '',
					'tip'     => __('Actions available include \'Install\', \'Activate\', \'Disable\', \'Enable\', \'Uninstall\'.')
				),
				'pi.plugin' => array(
					'display' => __('Plugin Name'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The name for this Plugin.  The name is controlled by the directory it resides in.')
				),
				'pi.description' => array(
					'display' => __('Plugin Description'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('A description that the Plugins author has given to the Plugin.')
				),
				'status' => array(
					'display' => $config['poller_id'] == 1 ? __('Status'):__('Main / Remote Status'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The status of this Plugin.')
				),
				'author' => array(
					'display' => __('Author'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The author of this Plugin.')
				),
				'nosort1' => array(
					'display' => __('Cacti Releases'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The Cacti Releases that are eligible to use this Plugin.  The format of the allowed versions follows common naming.')
				),
				'pi.version' => array(
					'display' => __('Installed Version'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The currently installed version of this Plugin.')
				),
				'nosort2' => array(
					'display' => __('Version'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The Available version for install for this Plugin.')
				),
				'pa.archive_length' => array(
					'display' => __('Size'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The compressed size of this Plugin in bytes.')
				),
				'nosort3' => array(
					'display' => __('Requires'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('This Plugin requires the following Plugins be installed first.')
				),
				'pa.published_at' => array(
					'display' => __('Last Published'),
					'align'   => 'right',
					'sort'    => 'DESC',
					'tip'     => __('The date the release was published or develop was last pushed.')
				),
				'pi.last_updated' => array(
					'display' => __('Installed/Upgraded'),
					'align'   => 'right',
					'sort'    => 'DESC',
					'tip'     => __('The date that this Plugin was last installed or upgraded.')
				),
			);

			break;
		default:
			$display_text = array(
				'nosort' => array(
					'display' => __('Actions'),
					'align'   => 'left',
					'sort'    => '',
					'tip'     => __('Actions available include \'Install\', \'Activate\', \'Disable\', \'Enable\', \'Uninstall\'.')
				),
				'pi.plugin' => array(
					'display' => __('Plugin Name'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The name for this Plugin.  The name is controlled by the directory it resides in.')
				),
				'pi.description' => array(
					'display' => __('Plugin Description'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('A description that the Plugins author has given to the Plugin.')
				),
				'pi.status' => array(
					'display' => $config['poller_id'] == 1 ? __('Status'):__('Main / Remote Status'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The Status of this available Plugin.  Loadable means it is currently not installed and can be loaded.')
				),
				'pi.author' => array(
					'display' => __('Author'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The author of this Plugin.')
				),
				'pa.compat' => array(
					'display' => __('Cacti'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('The Cacti version ranges required to use this Plugin.')
				),
				'pi.requires' => array(
					'display' => __('Plugin Requires'),
					'align'   => 'left',
					'sort'    => 'ASC',
					'tip'     => __('This Plugin requires the following Plugins be installed first.')
				),
				'pi.version' => array(
					'display' => __('Version'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The version of this Plugin.')
				),
				'pi.last_updated' => array(
					'display' => __('Installed/Upgraded'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The date that this Plugin was last installed or upgraded.')
				),
				'pi.id' => array(
					'display' => __('Load Order'),
					'align'   => 'right',
					'sort'    => 'ASC',
					'tip'     => __('The load order of the Plugin.  You can change the load order by first sorting by it, then moving a Plugin either up or down.')
				)
			);

			break;
	}

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1);

	$i = 0;

	if (cacti_sizeof($plugins)) {
		$j = 0;

		foreach ($plugins as $plugin) {
			if ((isset($plugins[$j + 1]) && $plugins[$j + 1]['status'] < 0) || (!isset($plugins[$j + 1]))) {
				$last_plugin = true;
			} else {
				$last_plugin = false;
			}

			if ($plugin['status'] <= 0 || (get_request_var('sort_column') != 'pi.id')) {
				$load_ordering = false;
			} else {
				$load_ordering = true;
			}

			print "<tr id='line{$plugin['id']}' class='tableRow selectable" . ($plugin['status'] <= 0 ? ' nodrag':'') . "'>";

			switch(get_request_var('state')) {
				case 8:
					print format_archive_plugin_row($plugin, $table);

					break;
				case 6:
					print format_available_plugin_row($plugin, $table);

					break;
				default:
					print format_plugin_row($plugin, $last_plugin, $load_ordering, $table);

					break;
			}

			form_end_row();

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

	db_execute("DROP TABLE $table");
}

function format_plugin_row($plugin, $last_plugin, $include_ordering, $table) {
	global $status_names, $config;
	static $first_plugin = true;
	static $row_id = 1;

	$row = plugin_actions($plugin, $table);

	$uname = strtoupper($plugin['plugin']);
	if ($uname == $plugin['plugin']) {
		$plugin_name = $uname;
	} else {
		$plugin_name = ucfirst($plugin['plugin']);
	}

	$row .= "<td><a href='" . html_escape($plugin['webpage']) . "' target='_blank' rel='noopener'>" . filter_value($plugin_name, get_request_var('filter')) . '</a></td>';

	$row .= "<td class='nowrap'>" . filter_value($plugin['description'], get_request_var('filter')) . '</td>';

	if ($plugin['status'] == '-1') {
		$status = plugin_is_compatible($plugin['plugin']);
		$row .= "<td class='nowrap'>" . __('Not Compatible, \'%s\'', $status['requires']);
	} elseif ($plugin['status'] < -1) {
		$row .= "<td class='nowrap'>" . __('Plugin Error');
	} else {
		$row .= "<td class='nowrap'>" . $status_names[$plugin['status']];
	}

	if ($plugin['last_updated'] != '0000-00-00 00:00:00') {
		if (read_config_option('github_allow_unsafe') == 'on') {
			$newer = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_available
				WHERE plugin = ?
				AND published_at > ?',
				array($plugin['plugin'], $plugin['last_updated']));
		} else {
			$newer = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_available
				WHERE plugin = ?
				AND last_updated > ?
				AND tag_name != "develop"',
				array($plugin['plugin'], $plugin['last_updated']));
		}
	} else {
		$newer = 0;
	}

	if ($newer > 0) {
		$row .= ", <a class='pic deviceUp' href='" . html_escape('plugins.php?action=list&state=6&type=3&filter=' . $plugin['plugin']) . "'>" . __('New Version') . '</a>';
	}

	if ($config['poller_id'] > 1) {
		if (strpos($plugin['capabilities'], 'remote_collect:1') !== false || strpos($plugin['capabilities'], 'remote_poller:1') !== false) {
			if ($plugin['remote_status'] == '-1') {
				$status = plugin_is_compatible($plugin['plugin']);
				$row .= ' / ' . __('Not Compatible, \'%s\'', $status['requires']);
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

		foreach ($requires as $r) {
			$nr[] = ucfirst($r);
		}

		$requires = implode(', ', $nr);
	} else {
		$requires = $plugin['requires'];
	}

	if ($plugin['last_updated'] == '0000-00-00 00:00:00') {
		$last_updated = __('N/A');
	} elseif (isset($plugin['last_updated'])) {
		$last_updated = substr($plugin['last_updated'], 0, 16);
	} else {
		$last_updated = __('N/A');
	}

	$plugin['compat'] = plugin_display_compat($plugin['compat']);

	$row .= "<td class='prewrap'>" . filter_value($plugin['author'], get_request_var('filter')) . '</td>';
	$row .= "<td class='left'>"    . html_escape($plugin['compat'])  . '</td>';
	$row .= "<td class='nowrap'>"  . html_escape($requires)          . '</td>';
	$row .= "<td class='right'>"   . html_escape($plugin['version']) . '</td>';
	$row .= "<td class='right'>"   . $last_updated                   . '</td>';

	if ($include_ordering) {
		$row .= "<td class='nowrap right'>";

		if (!$first_plugin) {
			$row .= "<a class='pic fa fa-caret-up moveArrow' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=moveup&plugin=' . $plugin['plugin']) . "' title='" . __esc('Order Before Previous Plugin') . "'></a>";
		} else {
			$row .= '<span class="moveArrowNone"></span>';
		}

		if (!$last_plugin) {
			$row .= "<a class='pic fa fa-caret-down moveArrow' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=movedown&plugin=' . $plugin['plugin']) . "' title='" . __esc('Order After Next Plugin') . "'></a>";
		} else {
			$row .= '<span class="moveArrowNone"></span>';
		}
		$row .= '</td>';
	} else {
		$row .= "<td></td>";
	}

	if ($include_ordering) {
		$first_plugin = false;
	}

	$row_id++;

	return $row;
}

function plugin_check_available_status($plugin) {
	if (cacti_version_compare(CACTI_VERSION, $plugin['avail_compat'], '<')) {
		$row .= "<td class='nowrap'>" . __('Cacti Upgrade Required') . '</td>';;
	} else {
		$row .= "<td class='nowrap'>" . __('Compatible') . '</td>';
	}
}

function format_available_plugin_row($plugin, $table) {
	global $status_names, $config;

	/* action icons */
	$row  = "<td class='nowrap' style='width:1%'>";

	/* remove leading 'v' off tag names for compares */
	$avail_version = ltrim($plugin['avail_tag_name'], 'v');

	if (plugin_valid_version_range($plugin['avail_compat'])) {
		if (plugin_valid_dependencies($plugin['avail_requires'])) {
			if ($plugin['version'] == '') {
				if ($avail_version != 'develop') {
					$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Load this Plugin from available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceUp'></i></a>";
				} else {
					$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Load this Plugin from the available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceDown'></i></a>";
				}

				$status = __('Compatible, Loadable');
			} elseif ($avail_version == 'develop') {
				$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Upgrade this Plugin from the available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceDown'></i></a>";
				$status = __('Compatible, Upgrade');
			} elseif (cacti_version_compare($avail_version, $plugin['version'], '<')) {
				$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Downgrade this Plugin from the available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceRecovering'></i></a>";
				$status = __('Compatible, Downgrade');
			} elseif (cacti_version_compare($avail_version, $plugin['version'], '=')) {
				$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Replace Plugin from the available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceUp'></i></a>";
				$status = __('Compatible, Same Version');
			} else {
				$row .= "<a class='piload' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=load&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('Upgrade Plugin from the available Cacti Plugins') . "' class='linkEditMain'><i class='fas fa-download deviceUp'></i></a>";
				$status = __('Compatible, Upgrade');
			}
		} else {
			$row .= "<a class='piload' href='#' title='" . __esc('Unable to Restore the Archive due to Plugin Dependencies not being met.') . "' class='linkEditMain'><i class='fas fa-download deviceDisabled'></i></a>";
			$status = __('Not Compatible, Dependencies');
		}
	} else {
		$row .= "<a class='piload' href='#' title='" . __esc('Unable to Load due to a bad Cacti version.') . "' class='linkEditMain'><i class='fas fa-download deviceDisabled'></i></a>";
		$status = __('Not Compatible, Cacti Version');
	}

	$row .= "<a class='pireadme' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=readme&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('View the Plugins Readme File') . "' class='linkEditMain'><i class='fas fa-file deviceDisabled'></i></a>";

	/* no link to the changelog unless it exists */
	if ($plugin['changelog'] > 0) {
		$row .= "<a class='pichangelog' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=changelog&plugin=' . $plugin['plugin'] . '&tag=' . $plugin['avail_tag_name']) . "' title='" . __esc('View the Plugins ChangeLog') . "' class='linkEditMain'><i class='fas fa-file deviceRecovering'></i></a>";
	}

	$row .= '</td>';

	$uname = strtoupper($plugin['plugin']);
	if ($uname == $plugin['plugin']) {
		$plugin_name = $uname;
	} else {
		$plugin_name = ucfirst($plugin['plugin']);
	}

	$row .= "<td><a href='" . html_escape($plugin['webpage']) . "' target='_blank' rel='noopener'>" . filter_value($plugin_name, get_request_var('filter')) . '</a></td>';

	$row .= "<td class='nowrap'>" . filter_value($plugin['avail_description'], get_request_var('filter')) . '</td>';

	$row .= "<td class='nowrap'>" . $status . '</td>';

	if ($plugin['avail_requires'] != '') {
		$requires = explode(' ', $plugin['avail_requires']);

		foreach ($requires as $r) {
			$nr[] = ucfirst($r);
		}

		$requires = implode(', ', $nr);
	} else {
		$requires = $plugin['avail_requires'];
	}

	$plugin['avail_compat'] = plugin_display_compat($plugin['avail_compat']);

	$row .= "<td class='prewrap'>" . filter_value($plugin['avail_author'], get_request_var('filter'))    . '</td>';
	$row .= "<td class='nowrap'>"  . html_escape($plugin['avail_compat'])    . '</td>';

	if ($plugin['version'] == '') {
		$row .= "<td class='right'>" . __esc('Not Loaded')             . '</td>';
	} else {
		$row .= "<td class='right'>" . html_escape($plugin['version']) . '</td>';
	}

	if ($plugin['last_updated'] == '0000-00-00 00:00:00' || $plugin['last_updated'] == '') {
		$last_updated = __('N/A');
	} else {
		$last_updated = substr($plugin['last_updated'], 0, 16);
	}

	if ($plugin['avail_tag_name'] !== 'develop') {
		$tag_version = str_replace('v', '', $plugin['avail_tag_name']);
	} else {
		$tag_version = $plugin['avail_tag_name'];
	}

	$row .= "<td class='right'>" . html_escape($tag_version) . '</td>';

	$size   = $plugin['archive_length'];
	$suffix = '';

	if ($size > 1024) {
		$suffix = ' KB';
		$size /= 1024;
	}

	if ($size > 1024) {
		$suffix = ' MB';
		$size /= 1024;
	}

	$row .= "<td class='right'>"  . number_format_i18n($size, 1) . $suffix   . '</td>';

	$row .= "<td class='right'>" . html_escape($requires)                    . '</td>';

	$row .= "<td class='right'>" . substr($plugin['avail_published'], 0, 16) . '</td>';

	$row .= "<td class='right'>" . $last_updated . '</td>';

	return $row;
}

function format_archive_plugin_row($plugin, $table) {
	global $status_names, $config;
	static $first_plugin = true;

	/* action icons */
	$row  = "<td class='nowrap' style='width:1%'>";

	if (plugin_valid_version_range($plugin['archive_compat'])) {
		if (plugin_valid_dependencies($plugin['archive_requires'])) {
			if ($plugin['version'] == '') {
				$row .= "<a class='pirestore' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=restore&plugin=' . $plugin['plugin'] . '&id=' . $plugin['id']) . "' title='" . __esc('Load this Plugin from the archive') . "' class='linkEditMain'><i class='fas fa-download deviceRecovering'></i></a>";
				$status = __('Compatible, Loadable');
			} elseif (cacti_version_compare($plugin['archive_version'], $plugin['version'], '<')) {
				$row .= "<a class='pirestore' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=restore&plugin=' . $plugin['plugin'] . '&id=' . $plugin['id']) . "' title='" . __esc('Downgrade this Plugin from the archive') . "' class='linkEditMain'><i class='fas fa-download deviceRecovering'></i></a>";
				$status = __('Compatible, Downgrade');
			} elseif (cacti_version_compare($plugin['archive_version'], $plugin['version'], '=')) {
				$row .= "<a class='pirestore' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=restore&plugin=' . $plugin['plugin'] . '&id=' . $plugin['id']) . "' title='" . __esc('Restore Plugin from the archive') . "' class='linkEditMain'><i class='fas fa-download deviceUp'></i></a>";
				$status = __('Compatible, Same Version');
			} else {
				$row .= "<a class='pirestore' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=restore&plugin=' . $plugin['plugin'] . '&id=' . $plugin['id']) . "' title='" . __esc('Upgrade Plugin from the archive') . "' class='linkEditMain'><i class='fas fa-download deviceUp'></i></a>";
				$status = __('Compatible, Upgradable');
			}
		} else {
			$row .= "<a class='piload' href='#' title='" . __esc('Unable to Restore the archive due to Plugin Dependencies not being met.') . "' class='linkEditMain'><i class='fas fa-download deviceDisabled'></i></a>";
			$status = __('Not Compatible, Dependencies');
		}
	} else {
		$row .= "<a class='piload' href='#' title='" . __esc('Unable to Restore the archive due to a bad Cacti version.') . "' class='linkEditMain'><i class='fas fa-download deviceDisabled'></i></a>";
		$status = __('Not Compatible, Cacti Version');
	}

	$row .= "<a class='pirmarchive' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=delete&plugin=' . $plugin['plugin'] . '&id=' . $plugin['id']) . "' title='" . __esc('Delete this Plugin archive') . "' class='linkEditMain'><i class='fa fa-trash-alt deviceRecovering'></i></a>";
	$row .= '</td>';

	$uname = strtoupper($plugin['plugin']);
	if ($uname == $plugin['plugin']) {
		$plugin_name = $uname;
	} else {
		$plugin_name = ucfirst($plugin['plugin']);
	}

	$row .= "<td><a href='" . html_escape($plugin['webpage']) . "' target='_blank' rel='noopener'>" . filter_value($plugin_name, get_request_var('filter')) . '</a></td>';

	$row .= "<td class='nowrap'>" . filter_value($plugin['description'], get_request_var('filter')) . '</td>';

	$row .= "<td class='nowrap'>" . $status . '</td>';

	if ($plugin['archive_requires'] != '') {
		$requires = explode(' ', $plugin['archive_requires']);

		foreach ($requires as $r) {
			$nr[] = ucfirst($r);
		}

		$requires = implode(', ', $nr);
	} else {
		$requires = $plugin['archive_requires'];
	}

	$plugin['archive_compat'] = plugin_display_compat($plugin['archive_compat']);

	$row .= "<td class='prewrap'>" . filter_value($plugin['author'], get_request_var('filter')) . '</td>';
	$row .= "<td class='left'>"   . html_escape($plugin['archive_compat'])                     . '</td>';

	if ($plugin['version'] == '') {
		$row .= "<td class='right'>" . __esc('Not Installed')          . '</td>';
	} else {
		$row .= "<td class='right'>" . html_escape($plugin['version']) . '</td>';
	}

	$row .= "<td class='right'>" . html_escape($plugin['archive_version'])  . '</td>';

	$size   = $plugin['archive_length'];
	$suffix = '';

	if ($size > 1024) {
		$suffix = ' KB';
		$size /= 1024;
	}

	if ($size > 1024) {
		$suffix = ' MB';
		$size /= 1024;
	}

	$row .= "<td class='right'>"  . number_format_i18n($size, 1) . $suffix  . '</td>';

	$row .= "<td class='right'>" . html_escape($plugin['archive_requires']) . '</td>';

	if ($plugin['last_updated'] == '0000-00-00 00:00:00') {
		$last_updated = __('N/A');
	} else {
		$last_updated = substr($plugin['last_updated'], 0, 16);
	}

	$archive_date = substr($plugin['archive_date'], 0, 16);

	$row .= "<td class='right'>" . $archive_date . '</td>';

	$row .= "<td class='right'>" . $last_updated . '</td>';

	return $row;
}

function plugin_required_for_others($plugin, $table) {
	$required_for_others = db_fetch_cell("SELECT GROUP_CONCAT(plugin)
		FROM $table
		WHERE requires LIKE '%" . $plugin['plugin'] . "%'
		AND status IN (1,4,7)");

	if ($required_for_others) {
		$parts = explode(',', $required_for_others);

		foreach ($parts as $p) {
			$np[] = ucfirst($p);
		}

		return implode(', ', $np);
	} else {
		return false;
	}
}

function plugin_required_installed($plugin, $table) {
	$not_installed = '';

	api_plugin_can_install($plugin['plugin'], $not_installed);

	return $not_installed;
}

function plugin_display_compat($compat) {
	$compat = explode(' ', $compat);

	foreach($compat as $index => $c) {
		if (strpos($c, '>=') !== false) {
			$compat[$index] = str_replace('>=', '>= ', $c);
		} elseif (strpos($c, '>=') !== false) {
			$compat[$index] = str_replace('>=', '>= ', $c);
		} elseif (strpos($c, '>') !== false) {
			$compat[$index] = str_replace('>', '> ', $c);
		} elseif (strpos($c, '<') !== false) {
			$compat[$index] = str_replace('<', '< ', $c);
		} else {
			$compat[$index] = '>= ' . $c;
		}
	}

	return implode(' ', $compat);
}

function plugin_get_install_links($plugin, $table) {
	$path = CACTI_PATH_PLUGINS . '/' . $plugin['plugin'];

	$link = '';

	if ($plugin['status'] == 0) {
		if (!file_exists("$path/setup.php")) {
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory \'%s\' is missing setup.php', $plugin['plugin']) . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
		} elseif (!file_exists("$path/INFO")) {
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is lacking an INFO file') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
		} else {
			$not_installed = plugin_required_installed($plugin, $table);

			if ($not_installed != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Install Plugin!  %s', $not_installed) . "' class='linkEditMain'><i class='fa fa-cog deviceDisabled'></i></a>";
			} else {
				$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=install&plugin=' . $plugin['plugin']) . "' title='" . __esc('Install Plugin') . "' class='piinstall linkEditMain'><i class='fa fa-cog deviceUp'></i></a>";
			}

			$link .= "<a href='#' class='pidisable'><i class='fa fa-cog' style='color:transparent'></i></a>";

		}

		$link .= "<a href='#' title='" . __esc('Plugin \'%s\' can not be archived before it\'s been Installed.', $plugin['plugin']) . "' class='piarchive linkEditMain'><i class='fa fa-box deviceDisabled'></i></a>";

		$setup_file = CACTI_PATH_BASE . '/plugins/' . $plugin['plugin'] . '/setup.php';

		if (file_exists($setup_file)) {
			require_once($setup_file);

			$has_data_function = "plugin_{$plugin['plugin']}_has_data";
			$rm_data_function  = "plugin_{$plugin['plugin']}_remove_data";

			if (function_exists($has_data_function) && function_exists($rm_data_function) && $has_data_function()) {
				$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=remove_data&plugin=' . $plugin['plugin']) . "' title='" . __esc('Remove Plugin Data Tables and Settings') . "' class='pirmdata'><i class='fa fa-trash deviceDown'></i></a>";
			}
		}
	}

	return $link;
}

function plugin_actions($plugin, $table) {
	global $config, $pluginslist, $plugins_integrated;

	$link = '<td style="width:1%" class="nowrap">';

	$archived = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_archive
		WHERE plugin = ?
		AND dir_md5sum = ?',
		array($plugin['plugin'], $plugin['dir_md5sum']));

	switch ($plugin['status']) {
		case '0': // Not Installed
			$link .= plugin_get_install_links($plugin, $table);

			break;
		case '1':	// Currently Active
			$required = plugin_required_for_others($plugin, $table);

			if ($required != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Uninstall.  This Plugin is required by: \'%s\'', ucfirst($required)) . "'><i class='fa fa-cog deviceUnknown'></i></a>";
			} else {
				$link .= "<a class='piuninstall' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=uninstall&plugin=' . $plugin['plugin']) . "' title='" . __esc('Uninstall Plugin') . "'><i class='fa fa-cog deviceDown'></i></a>";
			}

			$link .= "<a class='pidisable' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=disable&plugin=' . $plugin['plugin']) . "' title='" . __esc('Disable Plugin') . "'><i class='fa fa-circle deviceRecovering'></i></a>";

			if ($archived) {
				$link .= "<a href='#' title='" . __esc('Plugin already archived and is Unchanged in the archive.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceDisabled'></i></a>";
			} else {
				$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=archive&plugin=' . $plugin['plugin']) . "' title='" . __esc('archive the Plugin in its current state.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceUnknown'></i></a>";
			}

			break;
		case '2': // Configuration issues
			$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=check&plugin=' . $plugin['plugin']) . "' title='" . __esc('Check Plugins Configuration') . "' class='piinstall linkEditMain'><i class='fa fa-cog deviceRecovering'></i></a>";

			$link .= "<a href='#' class='pidisable'><i class='fa fa-cog' style='color:transparent'></i></a>";

			$link .= "<a href='#' title='" . __esc('A Plugin can not be archived when it has Configuration Issues.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceDisabled'></i></a>";

			break;
		case '4':	// Installed but not active
			$required = plugin_required_for_others($plugin, $table);

			if ($required != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Uninstall.  This Plugin is required by: \'%s\'', ucfirst($required)) . "'><i class='fa fa-cog deviceUnknown'></i></a>";
			} else {
				$link .= "<a class='piuninstall' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=uninstall&plugin=' . $plugin['plugin']) . "' title='" . __esc('Uninstall Plugin') . "'><i class='fa fa-cog deviceDown'></i></a>";
			}

			$link .= "<a class='pienable' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=enable&plugin=' . $plugin['plugin']) . "' title='" . __esc('Enable Plugin') . "'><i class='fa fa-circle deviceUp'></i></a>";

			if ($archived) {
				$link .= "<a href='#' title='" . __esc('Plugin already archived and Unchanged in the archive.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceDisabled'></i></a>";
			} else {
				$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=archive&plugin=' . $plugin['plugin']) . "' title='" . __esc('Archive the Plugin in its current state.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceUnknown'></i></a>";
			}

			break;
		case '7':	// Installed but errored
			$required = plugin_required_for_others($plugin, $table);

			if ($required != '') {
				$link .= "<a class='pierror' href='#' title='" . __esc('Unable to Uninstall.  This Plugin is required by: \'%s\'', ucfirst($required)) . "'><i class='fa fa-cog deviceUnknown'></i></a>";
			} else {
				$link .= "<a class='piuninstall' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=uninstall&plugin=' . $plugin['plugin']) . "' title='" . __esc('Uninstall Plugin') . "'><i class='fa fa-cog deviceDown'></i></a>";
			}

			$link .= "<a class='pienable' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=enable&plugin=' . $plugin['plugin']) . "' title='" . __esc('Plugin was Disabled due to a Plugin Error.  Click to Re-enable the Plugin.  Search for \'DISABLING\' in the Cacti log to find the reason.') . "'><i class='fa fa-circle deviceDown'></i></a>";

			if ($archived) {
				$link .= "<a href='#' title='" . __esc('Plugin already archived and Unchanged in the archive.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceDisabled'></i></a>";
			} else {
				$link .= "<a href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=archive&plugin=' . $plugin['plugin']) . "' title='" . __esc('Archive the Plugin in its current state.') . "' class='piarchive linkEditMain'><i class='fa fa-box deviceUnknown'></i></a>";
			}

			break;
		case '-5': // Plugin directory missing
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory is missing!') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";

			break;
		case '-4': // Plugins should have INFO file since 1.0.0
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is not compatible (Pre-1.x)') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";

			break;
		case '-3': // Plugins can have spaces in their names
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directories can not include spaces') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";

			break;
		case '-2': // Naming issues
			$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory is not correct.  Should be \'%s\' but is \'%s\'', strtolower($plugin['plugin']), $plugin['plugin']) . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";

			break;
		default: // Old PIA
			$path = CACTI_PATH_PLUGINS . '/' . $plugin['plugin'];

			if (!file_exists("$path/setup.php")) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin directory \'%s\' is missing setup.php', $plugin['plugin']) . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
			} elseif (!file_exists("$path/INFO")) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is lacking an INFO file') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
			} elseif (in_array($plugin['plugin'], $plugins_integrated, true)) {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is integrated into Cacti core') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
			} else {
				$link .= "<a class='pierror' href='#' title='" . __esc('Plugin is not compatible') . "' class='linkEditMain'><i class='fa fa-cog deviceUnknown'></i></a>";
			}

			break;
	}

	if ($config['poller_id'] > 1) {
		if (strpos($plugin['capabilities'], 'remote_collect:1') !== false || strpos($plugin['capabilities'], 'remote_poller:1') !== false) {
			if ($plugin['remote_status'] == 1) { // Installed and Active
				// TO-DO: Disabling here does not make much sense as the main will be replicated
				// with any change of any other plugin thus undoing.  Fix that moving forward
				//$link .= "<a class='pidisable' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=remote_disable&plugin=' . $plugin['plugin']) . "' title='" . __esc('Disable Plugin Locally') . "'><i class='fa fa-cog deviceDown'></i></a>";
			} elseif ($plugin['remote_status'] == 4) { // Installed but inactive
				if ($plugin['status'] == 1) {
					$link .= "<a class='pienable' href='" . html_escape(CACTI_PATH_URL . 'plugins.php?action=remote_enable&plugin=' . $plugin['plugin']) . "' title='" . __esc('Enable Plugin Locally') . "'><i class='fa fa-circle deviceUp'></i></a>";
				}
			}
		}
	}

	$link .= '</td>';

	return $link;
}

