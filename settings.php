<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* set default action */
set_default_action();

get_filter_request_var('tab', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

global $disable_log_rotation, $local_db_cnn_id;

switch (get_request_var('action')) {
	case 'save':
		$errors  = array();
		$inserts = array();

		foreach ($settings[get_request_var('tab')] as $field_name => $field_array) {
			if (($field_array['method'] == 'header') || ($field_array['method'] == 'spacer')) {
				/* do nothing */
			} elseif ($field_array['method'] == 'checkbox') {
				if (isset_request_var($field_name)) {
					$inserts[] = '(' . db_qstr($field_name) . ', "on")';
					db_execute_prepared(
						"REPLACE INTO settings
					(name, value)
					VALUES (?, 'on')",
						array($field_name)
					);
				} else {
					$inserts[] = '(' . db_qstr($field_name) . ', "")';
					db_execute_prepared(
						"REPLACE INTO settings
					(name, value)
					VALUES (?, '')",
						array($field_name)
					);
				}
			} elseif ($field_array['method'] == 'checkbox_group') {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (isset_request_var($sub_field_name)) {
						$inserts[] = '(' . db_qstr($field_name) . ', "on")';
						db_execute_prepared(
							"REPLACE INTO settings
					(name, value)
					VALUES (?, 'on')",
							array($sub_field_name)
						);
					} else {
						$inserts[] = '(' . db_qstr($field_name) . ', "on")';
						db_execute_prepared(
							"REPLACE INTO settings
					(name, value)
					VALUES (?, '')",
							array($sub_field_name)
						);
					}
				}
			} elseif ($field_array['method'] == 'dirpath') {
				if (get_nfilter_request_var($field_name) != '' && !is_dir(get_nfilter_request_var($field_name))) {
					$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
					$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
					$errors[8]                                  = 8;
				} else {
					if (get_request_var('tab') == 'path' && is_remote_path_setting($field_name)) {
						db_execute_prepared(
							'REPLACE INTO settings
						(name, value)
						VALUES (?, ?)',
							array($field_name, get_nfilter_request_var($field_name)),
							true,
							$local_db_cnn_id
						);
					} else {
						$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($field_name)) . ')';
						db_execute_prepared(
							'REPLACE INTO settings
						(name, value)
						VALUES (?, ?)',
							array($field_name, get_nfilter_request_var($field_name))
						);
					}
				}
			} elseif ($field_array['method'] == 'filepath') {
				if (
					isset($field_array['file_type']) &&
					$field_array['file_type'] == 'binary' &&
					get_nfilter_request_var($field_name) != '' &&
					file_exists(get_nfilter_request_var($field_name)) === false
				) {
					$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
					$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
					$errors[36]                                 = 36;
				} else {
					$continue = true;

					if ($field_name == 'path_cactilog') {
						$extension = pathinfo(get_nfilter_request_var($field_name), PATHINFO_EXTENSION);

						if ($extension != 'log') {
							$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
							$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
							$errors[9]                                  = 9;
							$continue                                   = false;
						}
					} elseif (get_nfilter_request_var($field_name) != '' && !is_valid_pathname(get_nfilter_request_var($field_name))) {
						$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
						$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
						$errors[36]                                 = 36;
					}

					if ($continue) {
						if (get_request_var('tab') == 'path' && is_remote_path_setting($field_name)) {
							db_execute_prepared(
								'REPLACE INTO settings
							(name, value)
							VALUES (?, ?)',
								array($field_name, get_nfilter_request_var($field_name)),
								true,
								$local_db_cnn_id
							);
						} else {
							$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($field_name)) . ')';
							db_execute_prepared(
								'REPLACE INTO settings
							(name, value)
							VALUES (?, ?)',
								array($field_name, get_nfilter_request_var($field_name))
							);
						}
					}
				}
			} elseif ($field_array['method'] == 'textbox_password') {
				if (get_nfilter_request_var($field_name) != get_nfilter_request_var($field_name . '_confirm')) {
					$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
					$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
					$errors[4]                                  = 4;

					break;
				}

				if (!isempty_request_var($field_name)) {
					$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($field_name)) . ')';
					db_execute_prepared(
						'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
						array($field_name, get_nfilter_request_var($field_name))
					);
				}
			} elseif ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (isset_request_var($sub_field_name)) {
						$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($sub_field_name)) . ')';
						db_execute_prepared(
							'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
							array($sub_field_name, get_nfilter_request_var($sub_field_name))
						);
					}
				}
			} elseif ($field_array['method'] == 'drop_multi') {
				if (isset_request_var($field_name)) {
					if (is_array(get_nfilter_request_var($field_name))) {
						$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(implode(',', get_nfilter_request_var($field_name))) . ')';
						db_execute_prepared(
							'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
							array($field_name, implode(',', get_nfilter_request_var($field_name)))
						);
					} else {
						$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($field_name)) . ')';
						db_execute_prepared(
							'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
							array($field_name, get_nfilter_request_var($field_name))
						);
					}
				} else {
					$inserts[] = '(' . db_qstr($field_name) . ', "")';
					db_execute_prepared(
						'REPLACE INTO settings
					(name, value)
					VALUES (?, "")',
						array($field_name)
					);
				}
			} elseif (isset_request_var($field_name)) {
				if ($field_array['method'] == 'textbox' && isset($field_array['filter'])) {
					if (isset($field_array['options'])) {
						$value = filter_var(get_nfilter_request_var($field_name), $field_array['filter'], $field_array['options']);
					} else {
						$value = filter_var(get_nfilter_request_var($field_name), $field_array['filter']);
					}

					if ($value === false) {
						$_SESSION[SESS_ERROR_FIELDS][$field_name]   = $field_name;
						$_SESSION[SESS_FIELD_VALUES][$field_name]   = get_nfilter_request_var($field_name);
						$errors[3]                                  = 3;

						continue;
					}
				}

				if (is_array(get_nfilter_request_var($field_name))) {
					$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(implode(',', get_nfilter_request_var($field_name))) . ')';
					db_execute_prepared(
						'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
						array($field_name, implode(',', get_nfilter_request_var($field_name)))
					);
				} else {
					$inserts[] = '(' . db_qstr($field_name) . ', ' . db_qstr(get_nfilter_request_var($field_name)) . ')';
					db_execute_prepared(
						'REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
						array($field_name, get_nfilter_request_var($field_name))
					);
				}
			}

			if ($field_name == 'auth_method') {
				if (get_nfilter_request_var($field_name) == '2') {
					db_execute('TRUNCATE TABLE user_auth_cache');
				}
			}
		}

		if (isset_request_var('log_verbosity')) {
			if (!isset_request_var('selective_debug')) {
				$inserts[] = '("selective_debug", "")';
				db_execute('REPLACE INTO settings
				(name, value)
				VALUES ("selective_debug", "")');
			}

			if (!isset_request_var('selective_plugin_debug')) {
				$inserts[] = '("selective_debug_plugin", "")';
				db_execute('REPLACE INTO settings
				(name, value)
				VALUES ("selective_plugin_debug", "")');
			}
		}

		// Disable template user from being able to login
		if (isset_request_var('user_template')) {
			db_execute_prepared(
				'UPDATE user_auth
			SET enabled=""
			WHERE id = ?',
				array(get_nfilter_request_var('user_template'))
			);
		}

		// Update snmpcache
		snmpagent_global_settings_update();

		api_plugin_hook_function('global_settings_update');

		$gone_time = read_config_option('poller_interval') * 2;

		$pollers = array_rekey(
			db_fetch_assoc('SELECT
			id,
			UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) AS last_polled
			FROM poller
			WHERE id > 1
			AND disabled=""'),
			'id',
			'last_polled'
		);

		if (get_request_var('tab') == 'path' && $config['poller_id'] > 1) {
			raise_message('poller_paths');
		}

		if (cacti_sizeof($errors) == 0) {
			if (cacti_sizeof($pollers) && $config['poller_id'] == 1) {
				$sql = 'INSERT INTO settings
				(name, value)
				VALUES ' . implode(', ', $inserts) . '
				ON DUPLICATE KEY UPDATE value=VALUES(value)';

				foreach ($pollers as $p => $t) {
					if ($t > $gone_time) {
						raise_message('poller_' . $p, __('Settings save to Data Collector %d skipped due to heartbeat.', $p), MESSAGE_LEVEL_WARN);
					} else {
						$rcnn_id = poller_connect_to_remote($p);

						if ($rcnn_id) {
							if (db_execute($sql, false, $rcnn_id) === false) {
								$rcnn_id = false;
							}
						}

						// check if we still have rcnn_id, if it's now become false, we had a problem
						if (!$rcnn_id) {
							raise_message('poller_' . $p, __('Settings save to Data Collector %d Failed.', $p), MESSAGE_LEVEL_ERROR);
						}
					}
				}

				raise_message(42);
			} else {
				raise_message(1);
			}
		} else {
			raise_message(35);

			foreach ($errors as $error) {
				raise_message($error);
			}
		}

		/* reset local settings cache so the user sees the new settings */
		kill_session_var(OPTIONS_WEB);

		header('Location: settings.php?tab=' . get_request_var('tab'));

		break;
	case 'send_test':
		email_test();

		break;

	default:
		top_header();

		/* set the default settings category */
		if (!isset_request_var('tab')) {
			/* there is no selected tab; select the first one */
			if (isset($_SESSION['sess_settings_tab'])) {
				$current_tab = $_SESSION['sess_settings_tab'];
			} else {
				$current_tab = array_keys($tabs);
				$current_tab = $current_tab[0];
			}
		} else {
			$current_tab = get_request_var('tab');
		}

		// If the tab no longer exists, use the first
		if (!isset($tabs[$current_tab])) {
			$current_tab = array_keys($tabs);
			$current_tab = $current_tab[0];
		}

		$_SESSION['sess_settings_tab'] = $current_tab;

		set_request_var('tab', $current_tab);

		$data_collectors = db_fetch_cell('SELECT COUNT(*) FROM poller WHERE disabled=""');

		if ($data_collectors > 1) {
			set_config_option('boost_rrd_update_enable', 'on');
			set_config_option('boost_redirect', 'on');
		}

		$system_tabs = array(
			'general',
			'path',
			'snmp',
			'poller',
			'data',
			'visual',
			'authentication',
			'boost',
			'spikes',
			'mail'
		);

		/* draw the categories tabs on the top of the page */
		print "<div>\n";
		print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>\n";

		if (cacti_sizeof($tabs) > 0) {
			$i = 0;

			foreach (array_keys($tabs) as $tab_short_name) {
				print "<li class='subTab" . (!in_array($tab_short_name, $system_tabs, true) ? ' pluginTab' : '') . "'><a " . (($tab_short_name == $current_tab) ? "class='selected'" : "class=''") . " href='" . html_escape("settings.php?tab=$tab_short_name") . "'>" . $tabs[$tab_short_name] . "</a></li>\n";

				$i++;
			}
		}

		print "</ul></nav></div>\n";
		print "</div>\n";

		form_start('settings.php', 'form_settings');

		if ($config['poller_id'] > 1 && $current_tab == 'path') {
			$suffix = ' [<span class="deviceDown">' . __('NOTE: Path Settings on this Tab are only saved locally!') . '</span>]';
		} else {
			$suffix = '';
		}

		html_start_box(__('Cacti Settings (%s)%s', $tabs[$current_tab], $suffix), '100%', true, '3', 'center', '');

		$form_array = array();

		// Remove log rotation is disabled by package maintainer
		if (isset($disable_log_rotation) && $disable_log_rotation == true) {
			unset($settings['path']['logrotate_enabled']);
			unset($settings['path']['logrotate_frequency']);
			unset($settings['path']['logrotate_retain']);
		}

		// RRDtool is not required for remote data collectors
		if ($config['poller_id'] > 1) {
			$settings['path']['path_rrdtool']['method'] = 'other';
		}

		if (isset($settings[$current_tab])) {
			foreach ($settings[$current_tab] as $field_name => $field_array) {
				$form_array += array($field_name => $field_array);

				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						if (config_value_exists($sub_field_name)) {
							$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
						}

						if ($current_tab == 'path' && is_remote_path_setting($field_name)) {
							$form_array[$field_name]['items'][$sub_field_name]['value'] = db_fetch_cell_prepared(
								'SELECT value
							FROM settings
							WHERE name = ?',
								array($sub_field_name),
								'',
								true,
								$local_db_cnn_id
							);
						} else {
							$form_array[$field_name]['items'][$sub_field_name]['value'] = db_fetch_cell_prepared(
								'SELECT value
							FROM settings
							WHERE name = ?',
								array($sub_field_name)
							);
						}
					}
				} else {
					if (config_value_exists($field_name)) {
						$form_array[$field_name]['form_id'] = 1;
					}

					if ($current_tab == 'path' && is_remote_path_setting($field_name)) {
						$form_array[$field_name]['value'] = db_fetch_cell_prepared(
							'SELECT value
						FROM settings
						WHERE name = ?',
							array($field_name),
							'',
							true,
							$local_db_cnn_id
						);
					} else {
						$form_array[$field_name]['value'] = db_fetch_cell_prepared(
							'SELECT value
						FROM settings
						WHERE name = ?',
							array($field_name)
						);
					}
				}
			}
		}

		// Cache this setting as on large systems
		// this query runs long
		if ($current_tab == 'spikes') {
			if (!isset($_SESSION['sk_templates'])) {
				$spikekill_templates = array_rekey(
					db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
					FROM graph_templates AS gt
					INNER JOIN graph_templates_item AS gti
					ON gt.id=gti.graph_template_id
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id=dtr.id
					WHERE gti.local_graph_id=0 AND data_source_type_id IN (3,2)
					ORDER BY name'),
					'id',
					'name'
				);

				$_SESSION['sk_templates'] = $spikekill_templates;
			} else {
				$spikekill_templates = $_SESSION['sk_templates'];
			}

			$form_array['spikekill_templates']['array'] = $spikekill_templates;
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);

		html_end_box(true, true);

		form_hidden_box('tab', $current_tab, '');

		form_save_button('', 'save');

		?>
		<script type='text/javascript'>
			var themeChanged = false;
			var currentTheme = '';
			var rrdArchivePath = '';
			var smtpPath = '';
			var currentTab = '<?php print $current_tab; ?>';
			var dataCollectors = '<?php print $data_collectors; ?>';
			var permsTitle     = '<?php print __esc('Changing Permission Model Warning');?>';
			var permsHeader    = '<?php print __esc('Changing Permission Model will alter a users effective Graph permissions.');?>';
			var permsMessage   = '<?php print __esc('After you change the Graph Permission Model you should audit your Users and User Groups Effective Graph permission to ensure that you still have adequate control of your Graphs.  NOTE: If you want to restrict all Graphs at the Device or Graph Template Graph Permission Model, the default Graph Policy should be set to \'Deny\'.');?>';

			$(function() {
				$('.subTab').find('a').click(function(event) {
					event.preventDefault();
					strURL = $(this).attr('href');
					strURL += (strURL.indexOf('?') > 0 ? '&' : '?');
					loadUrl({
						url: strURL,
						scroll: true
					});
				});

				$('input[value="<?php print __esc('Save'); ?>"]').unbind().click(function(event) {
					event.preventDefault();

					if (parseInt($('#cron_interval').val()) < parseInt($('#poller_interval').val())) {
						$('#message_container').html('<div id="message" class="textError messageBox"><?php print __('Poller Interval must be less than Cron Interval'); ?></div>').show().delay(4000).slideUp('fast', function() {
							$('#message_container').empty();
						});
						return false;
					}

					var options = {
						url: 'settings.php?tab=' + $('#tab').val(),
					}

					if (themeChanged == true) {
						options.redirect = options.url;
					}

					var data = $('input, select, textarea').serialize();

					postUrl(options, data);
				});

				if (currentTab == 'general') {
					currentPerms = $('#graph_auth_method').val();

					$('#selective_plugin_debug').multiselect({
						menuHeight: $(window).height() * .7,
						menuWidth: 'auto',
						linkInfo: faIcons,
						noneSelectedText: '<?php print __('Select Plugin(s)'); ?>',
						selectedText: function(numChecked, numTotal, checkedItems) {
							myReturn = numChecked + ' <?php print __('Plugins Selected'); ?>';
							return myReturn;
						},
						checkAllText: '<?php print __('All'); ?>',
						uncheckAllText: '<?php print __('None'); ?>',
						uncheckall: function() {
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).prop('checked', true);
							});
						}
					}).multiselectfilter({
						label: '<?php print __('Search'); ?>',
						placeholder: '<?php print __('Enter keyword'); ?>',
						width: '150'
					});

					$('#selective_debug').multiselect({
						menuHeight: $(window).height() * .7,
						menuWidth: 'auto',
						linkInfo: faIcons,
						noneSelectedText: '<?php print __('Select File(s)'); ?>',
						selectedText: function(numChecked, numTotal, checkedItems) {
							myReturn = numChecked + ' <?php print __('Files Selected'); ?>';
							return myReturn;
						},
						checkAllText: '<?php print __('All'); ?>',
						uncheckAllText: '<?php print __('None'); ?>',
						uncheckAll: function() {
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).prop('checked', true);
							});
						}
					}).multiselectfilter({
						label: '<?php print __('Search'); ?>',
						placeholder: '<?php print __('Enter keyword'); ?>',
						width: '150'
					});

					$('#graph_auth_method').change(function() {
						permsChanger();
					});
				} else if (currentTab == 'spikes') {
					$('#spikekill_templates').multiselect({
						menuHeight: $(window).height() * .7,
						menuWidth: 'auto',
						linkInfo: faIcons,
						noneSelectedText: '<?php print __('Select Template(s)'); ?>',
						selectedText: function(numChecked, numTotal, checkedItems) {
							myReturn = numChecked + ' <?php print __('Templates Selected'); ?>';
							$.each(checkedItems, function(index, value) {
								if (value.value == '0') {
									myReturn = '<?php print __('All Templates Selected'); ?>';
									return false;
								}
							});
							return myReturn;
						},
						checkAllText: '<?php print __('All'); ?>',
						uncheckAllText: '<?php print __('None'); ?>',
						uncheckAll: function() {
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).prop('checked', true);
							});
						},
						click: function(event, ui) {
							checked = $(this).multiselect('widget').find('input:checked').length;

							if (ui.value == '0') {
								if (ui.checked == true) {
									$('#host').multiselect('uncheckAll');
									$(this).multiselect('widget').find(':checkbox:first').each(function() {
										$(this).prop('checked', true);
									});
								}
							} else if (checked == 0) {
								$(this).multiselect('widget').find(':checkbox:first').each(function() {
									$(this).click();
								});
							} else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
								if (checked > 0) {
									$(this).multiselect('widget').find(':checkbox:first').each(function() {
										$(this).click();
										$(this).prop('disable', true);
									});
								}
							}
						}
					}).multiselectfilter({
						label: '<?php print __('Search'); ?>',
						placeholder: '<?php print __('Enter keyword'); ?>',
						width: '150'
					});
				} else if (currentTab == 'data') {
					$('#storage_location').change(function() {
						var showField = $(this).val() != '0';
						toggleFields({
							rrdp_header: showField,
							rrdp_server: showField,
							rrdp_port: showField,
							rrdp_fingerprint: showField,
							rrdp_header2: showField,
							rrdp_load_balancing: showField,
							rrdp_server_backup: showField,
							rrdp_port_backup: showField,
							rrdp_fingerprint_backup: showField,
						});
					}).trigger('change');

					$('#extended_paths').change(function() {
						toggleFields({
							extended_paths_type: $(this).is(':checked'),
						});
					}).trigger('change');
				} else if (currentTab == 'mail') {
					$('#row_settings_email_header div.formHeaderText').append('<div id="emailtest" class="emailtest"><?php print __('Send a Test Email'); ?></div>');

					initMail();

					$('#settings_how').change(function() {
						initMail();
					});

					$('#emailtest').click(function() {
						$.get('settings.php?action=send_test')
							.done(function(data) {
								$('body').append('<div id="testmail" title="<?php print __esc('Test Email Results'); ?>"></div>');
								$('#testmail').html(data);

								$('#testmail').dialog({
									autoOpen: false,
									modal: true,
									minHeight: 300,
									maxHeight: 600,
									height: 450,
									width: 500,
									autoOpen: true,
									show: {
										effect: 'appear',
										duration: 100
									},
									hide: {
										effect: 'appear',
										duration: 100
									}
								});
							})
							.fail(function(data) {
								getPresentHTTPError(data);
							});
					});
				} else if (currentTab == 'visual') {
					currentTheme = $('#selected_theme').val();

					initFonts();
					initRealtime();

					$('#font_method').change(function() {
						initFonts();
					});

					$('#selected_theme').change(function() {
						themeChanger();
					});

					$('#realtime_enabled').change(function() {
						initRealtime();
					});
				} else if (currentTab == 'snmp') {
					// Need to set this for global snmpv3 functions to remain sane between edits
					snmp_security_initialized = false;

					setSNMP();

					$('#snmp_version, #snmp_auth_protocol, #snmp_priv_protocol, #snmp_security_level').change(function() {
						setSNMP();
					});

					initAvail();
					$('#availability_method').change(function() {
						initAvail();
					});
				} else if (currentTab == 'authentication') {
					initAuth();
					$('#auth_method').change(function() {
						initAuth();
					});
				} else if (currentTab == 'path') {
					initRRDClean();

					$('#rrd_autoclean').change(function() {
						initRRDClean();
					});

					if (cactiServerOS == 'win32') {
						$('#row_path_stderrlog').hide();
					}

					$('#rrd_autoclean_method').change(function() {
						initRRDClean();
					});
				} else if (currentTab == 'boost') {
					if (dataCollectors > 1) {
						$('#boost_rrd_update_enable').prop('checked', true);
						$('#boost_rrd_update_enable').prop('disabled', true);
						$('#boost_redirect').prop('checked', true);
						$('#boost_redirect').prop('disabled', true);
					}

					initBoostOD();
					initBoostCache();

					$('#boost_rrd_update_enable').change(function() {
						initBoostOD();
					});

					$('#boost_png_cache_enable').change(function() {
						initBoostCache();
					});
				}

				function initMail() {
					/* clear passwords */
					if ($('#settings_sendmail_path').val() != '') {
						smtpPath = $('#settings_sendmail_path').val();
					}

					$('#settings_smtp_password').val('');
					$('#settings_smtp_password_confirm').val('');

					var smtpMode = $('#settings_how').val();
					if (smtpMode == '0') {
						$('#settings_sendmail_path').val('');
					} else if (smtpMode == '1') {
						if (smtpPath != '') {
							$('#settings_sendmail_path').val(smtpPath);
						}
					}

					toggleFields({
						settings_sendmail_header: smtpMode == 1,
						settings_sendmail_path: smtpMode == 1,
						settings_smtp_header: smtpMode == 2,
						settings_smtp_host: smtpMode == 2,
						settings_smtp_port: smtpMode == 2,
						settings_smtp_username: smtpMode == 2,
						settings_smtp_password: smtpMode == 2,
						settings_smtp_secure: smtpMode == 2,
						settings_smtp_timeout: smtpMode == 2,
					});
				}
			});

			function initBoostCache() {
				toggleFields({
					boost_png_cache_directory: $('#boost_png_cache_enable').is(':checked')
				});
			}

			function initBoostOD() {
				var showField = $('#boost_rrd_update_enable').is(':checked');
				toggleFields({
					boost_rrd_update_interval: showField,
					boost_parallel: showField,
					path_boost_log: showField,
					boost_rrd_update_max_records: showField,
					boost_rrd_update_max_records_per_select: showField,
					boost_rrd_update_string_length: showField,
					boost_poller_mem_limit: showField,
					boost_rrd_update_max_runtime: showField,
					boost_redirect: showField,
				});
			}

			function themeChanger() {
				if ($('#selected_theme').val() != currentTheme) {
					themeChanged = true;
				} else {
					themeChanged = false;
				}
			}

			function permsChanger() {
				if ($('#graph_auth_method').val() != currentPerms) {
					raiseMessage(permsTitle, permsHeader, permsMessage, MESSAGE_LEVEL_MIXED);
				}
			}

			function initFonts() {
				var showField = $('#font_method').val() != 1;
				toggleFields({
					path_rrdtool_default_font: showField,
					title_size: showField,
					title_font: showField,
					legend_size: showField,
					legend_font: showField,
					axis_size: showField,
					axis_font: showField,
					unit_size: showField,
					unit_font: showField,
				});
			}

			function initRealtime() {
				var showField = $('#realtime_enabled').is(':checked');
				toggleFields({
					realtime_gwindow: showField,
					realtime_interval: showField,
					realtime_cache_path: showField,
				});
			}

			function initRRDClean() {
				if ($('#rrd_autoclean').is(':checked')) {
					$('#row_rrd_autoclean_method').show();
					if ($('#rrd_autoclean_method').val() == '3') {
						if (rrdArchivePath != '') {
							$('#rrd_archive').val(rrdArchivePath);
						}
						$('#row_rrd_archive').show();
					} else {
						if ($('#rrd_archive').val() != '') {
							rrdArchivePath = $('#rrd_archive').val();
						}
						$('#row_rrd_archive').hide();
						$('#rrd_archive').val('');
					}
				} else {
					if ($('#rrd_archive').val() != '') {
						rrdArchivePath = $('#rrd_archive').val();
					}
					$('#rrd_archive').val('');

					$('#row_rrd_autoclean_method').hide();
					$('#row_rrd_archive').hide();
				}
			}

			function initAuth() {
				let AUTH_METHOD_NONE = 0;
				let AUTH_METHOD_CACTI = 1;
				let AUTH_METHOD_BASIC = 2;
				let AUTH_METHOD_LDAP = 3;
				let AUTH_METHOD_DOMAIN = 4;

				var authMode = $('#auth_method').val();
				if (authMode == AUTH_METHOD_LDAP) {
					authMode = AUTH_METHOD_DOMAIN;
				}

				var fields = {
					special_users_header: true,
					user_template: true,
					admin_user: true,
					guest_user: true,
					auth_cache_enabled: authMode != AUTH_METHOD_BASIC,
					basic_auth_fail_message: authMode == AUTH_METHOD_BASIC,
					basic_header: authMode == AUTH_METHOD_BASIC,
					path_basic_mapfile: authMode == AUTH_METHOD_BASIC,
					secpass_lock_header: authMode != AUTH_METHOD_BASIC,
					secpass_lockfailed: authMode != AUTH_METHOD_BASIC,
					secpass_unlocktime: authMode != AUTH_METHOD_BASIC,
					secpass_pwned_header: authMode == AUTH_METHOD_CACTI,
					secpass_pwnedcheck: authMode == AUTH_METHOD_CACTI,
					secpass_pwnedcount: authMode == AUTH_METHOD_CACTI,
					secpass_header: authMode == AUTH_METHOD_CACTI,
					secpass_expireaccount: authMode == AUTH_METHOD_CACTI,
					secpass_expirepass: authMode == AUTH_METHOD_CACTI,
					secpass_forceold: authMode == AUTH_METHOD_CACTI,
					secpass_history: authMode == AUTH_METHOD_CACTI,
					secpass_minlen: authMode == AUTH_METHOD_CACTI,
					secpass_reqmixcase: authMode == AUTH_METHOD_CACTI,
					secpass_reqnum: authMode == AUTH_METHOD_CACTI,
					secpass_reqspec: authMode == AUTH_METHOD_CACTI,
				}

				toggleFields(fields);
			}

			function initAvail() {
				var availableMethod = $('#availability_method').val();

				var hideTimeouts = [0];
				var showMethods = [1, 3, 4];

				toggleFields({
					ping_method: showMethods.include(availableMethod),
					ping_port: showMethods.include(availableMethod),
					ping_timeout: !hideTimeouts.include(availableMethod),
					ping_retries: !hideTimeouts.include(availableMethod),
				});
			}
		</script>
<?php

				bottom_footer();

		break;
}
