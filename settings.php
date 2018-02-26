<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* set default action */
set_default_action();

get_filter_request_var('tab', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

switch (get_request_var('action')) {
case 'save':
	$errors = array();
	foreach ($settings{get_request_var('tab')} as $field_name => $field_array) {
		if (($field_array['method'] == 'header') || ($field_array['method'] == 'spacer' )){
			/* do nothing */
		} elseif ($field_array['method'] == 'checkbox') {
			if (isset_request_var($field_name)) {
				db_execute_prepared("REPLACE INTO settings
					(name, value)
					VALUES (?, 'on')",
					array($field_name));
			} else {
				db_execute_prepared("REPLACE INTO settings
					(name, value)
					VALUES (?, '')",
					array($field_name));
			}
		} elseif ($field_array['method'] == 'checkbox_group') {
			foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
				if (isset_request_var($sub_field_name)) {
					db_execute_prepared("REPLACE INTO settings
					(name, value)
					VALUES (?, 'on')",
					array($sub_field_name));
				} else {
					db_execute_prepared("REPLACE INTO settings
					(name, value)
					VALUES (?, '')",
					array($sub_field_name));
				}
			}
		} elseif ($field_array['method'] == 'dirpath') {
			if (get_nfilter_request_var($field_name) != '' && !is_dir(get_nfilter_request_var($field_name))) {
				$_SESSION['sess_error_fields'][$field_name] = $field_name;
				$_SESSION['sess_field_values'][$field_name] = get_nfilter_request_var($field_name);
				$errors[8] = 8;
			} else {
				db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, get_nfilter_request_var($field_name)));
			}
		} elseif ($field_array['method'] == 'filepath') {
			if (get_nfilter_request_var($field_name) != '' && !is_file(get_nfilter_request_var($field_name))) {
				$_SESSION['sess_error_fields'][$field_name] = $field_name;
				$_SESSION['sess_field_values'][$field_name] = get_nfilter_request_var($field_name);
				$errors[36] = 36;
			} else {
				$continue = true;

				if ($field_name == 'path_cactilog') {
					$extension = pathinfo(get_nfilter_request_var($field_name), PATHINFO_EXTENSION);

					if ($extension != 'log') {
						$_SESSION['sess_error_fields'][$field_name] = $field_name;
						$_SESSION['sess_field_values'][$field_name] = get_nfilter_request_var($field_name);
						$errors[9] = 9;
						$continue = false;
					}
				}

				if ($continue) {
					db_execute_prepared('REPLACE INTO settings
						(name, value)
						VALUES (?, ?)',
						array($field_name, get_nfilter_request_var($field_name)));
				}
			}
		} elseif ($field_array['method'] == 'textbox_password') {
			if (get_nfilter_request_var($field_name) != get_nfilter_request_var($field_name . '_confirm')) {
				$_SESSION['sess_error_fields'][$field_name] = $field_name;
				$_SESSION['sess_field_values'][$field_name] = get_nfilter_request_var($field_name);
				$errors[4] = 4;
				break;
			} elseif (!isempty_request_var($field_name)) {
				db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, get_nfilter_request_var($field_name)));
			}
		} elseif ((isset($field_array['items'])) && (is_array($field_array['items']))) {
			foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
				if (isset_request_var($sub_field_name)) {
					db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($sub_field_name, get_nfilter_request_var($sub_field_name)));
				}
			}
		} elseif ($field_array['method'] == 'drop_multi') {
			if (isset_request_var($field_name)) {
				if (is_array(get_nfilter_request_var($field_name))) {
					db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, implode(',', get_nfilter_request_var($field_name))));
				} else {
					db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, get_nfilter_request_var($field_name)));
				}
			} else {
				db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, "")',
					array($field_name));
			}
		} elseif (isset_request_var($field_name)) {
			if (is_array(get_nfilter_request_var($field_name))) {
				db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, implode(',', get_nfilter_request_var($field_name))));
			} else {
				db_execute_prepared('REPLACE INTO settings
					(name, value)
					VALUES (?, ?)',
					array($field_name, get_nfilter_request_var($field_name)));
			}
		}
	}

	if (isset_request_var('log_verbosity')) {
		if (!isset_request_var('selective_debug')) {
			db_execute('REPLACE INTO settings
				(name, value)
				VALUES ("selective_debug", "")');
		}

		if (!isset_request_var('selective_plugin_debug')) {
			db_execute('REPLACE INTO settings
				(name, value)
				VALUES ("selective_plugin_debug", "")');
		}
	}

	// Disable template user from being able to login
	if (isset_request_var('user_template') && get_request_var('user_template') > 0) {
		db_execute_prepared('UPDATE user_auth
			SET enabled=""
			WHERE id = ?',
			array(get_request_var('user_template')));
	}

	// Update snmpcache
	snmpagent_global_settings_update();

	api_plugin_hook_function('global_settings_update');

	if (sizeof($errors) == 0) {
		raise_message(1);
	} else {
		raise_message(35);
		foreach($errors as $error) {
			raise_message($error);
		}
	}

	/* reset local settings cache so the user sees the new settings */
	kill_session_var('sess_config_array');

	if (isset_request_var('header') && get_nfilter_request_var('header') == 'false') {
		header('Location: settings.php?header=false&tab=' . get_request_var('tab'));
	} else {
		header('Location: settings.php?tab=' . get_request_var('tab'));
	}

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

	$system_tabs = array('general', 'path', 'snmp', 'poller', 'data', 'visual', 'authentication', 'boost', 'spikes', 'mail');

	/* draw the categories tabs on the top of the page */
	print "<div>\n";
	print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>\n";

	if (sizeof($tabs) > 0) {
		$i = 0;

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab" . (!in_array($tab_short_name, $system_tabs) ? ' pluginTab':'') . "'><a " . (($tab_short_name == $current_tab) ? "class='selected'" : "class=''") . " href='" . html_escape("settings.php?tab=$tab_short_name") . "'>" . $tabs[$tab_short_name] . "</a></li>\n";

			$i++;
		}
	}

	print "</ul></nav></div>\n";
	print "</div>\n";

	form_start('settings.php', 'chk');

	html_start_box( __('Cacti Settings (%s)', $tabs[$current_tab]), '100%', true, '3', 'center', '');

	$form_array = array();

	if (isset($settings[$current_tab])) {
		foreach ($settings[$current_tab] as $field_name => $field_array) {
			$form_array += array($field_name => $field_array);

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (config_value_exists($sub_field_name)) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] = db_fetch_cell_prepared('SELECT value
						FROM settings
						WHERE name = ?',
						array($sub_field_name));
				}
			} else {
				if (config_value_exists($field_name)) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value
					FROM settings
					WHERE name = ?',
					array($field_name));
			}
		}
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
	var currentTab = '<?php print $current_tab;?>';

	$(function() {
		$('.subTab').find('a').click(function(event) {
			event.preventDefault();
			strURL = $(this).attr('href');
			strURL += (strURL.indexOf('?') > 0 ? '&':'?') + 'header=false';
			loadPageNoHeader(strURL);
		});

		$('input[value="<?php print __esc('Save');?>"]').click(function(event) {
			event.preventDefault();

			if (parseInt($('#cron_interval').val()) < parseInt($('#poller_interval').val())) {
				$('#message_container').html('<div id="message" class="textError messageBox"><?php print __('Poller Interval must be less than Cron Interval');?></div>').show().delay(4000).slideUp('fast', function() {
					$('#message_container').empty();
				});
				return false;
			}

			if (themeChanged != true) {
				$.post('settings.php?tab='+$('#tab').val()+'&header=false', $('input, select, textarea').serialize()).done(function(data) {
					$('#main').hide().html(data);
					applySkin();
				});
			} else {
				$.post('settings.php?tab='+$('#tab').val()+'&header=false', $('input, select, textarea').serialize()).done(function(data) {
					document.location = 'settings.php?newtheme=1&tab='+$('#tab').val();
				});
			}
		});

		if (currentTab == 'general') {
			$('#selective_plugin_debug').multiselect({
				height: 300,
				noneSelectedText: '<?php print __('Select Plugin(s)');?>',
				selectedText: function(numChecked, numTotal, checkedItems) {
					myReturn = numChecked + ' <?php print __('Plugins Selected');?>';
					return myReturn;
				},
				checkAllText: '<?php print __('All');?>',
				uncheckAllText: '<?php print __('None');?>',
				uncheckall: function() {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).prop('checked', true);
					});
				}
			}).multiselectfilter( {
				label: '<?php print __('Search');?>',
				placeholder: '<?php print __('Enter keyword');?>',
				width: '150'
			});

			$('#selective_debug').multiselect({
				noneSelectedText: '<?php print __('Select File(s)');?>',
				selectedText: function(numChecked, numTotal, checkedItems) {
					myReturn = numChecked + ' <?php print __('Files Selected');?>';
					return myReturn;
				},
				checkAllText: '<?php print __('All');?>',
				uncheckAllText: '<?php print __('None');?>',
				uncheckall: function() {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).prop('checked', true);
					});
				}
			}).multiselectfilter( {
				label: '<?php print __('Search');?>',
				placeholder: '<?php print __('Enter keyword');?>',
				width: '150'
			});
		} else if (currentTab == 'spikes') {
			$('#spikekill_templates').multiselect({
				height: 300,
				noneSelectedText: '<?php print __('Select Template(s)');?>',
				selectedText: function(numChecked, numTotal, checkedItems) {
					myReturn = numChecked + ' <?php print __('Templates Selected');?>';
					$.each(checkedItems, function(index, value) {
						if (value.value == '0') {
							myReturn='<?php print __('All Templates Selected');?>';
							return false;
						}
					});
					return myReturn;
				},
				checkAllText: '<?php print __('All');?>',
				uncheckAllText: '<?php print __('None');?>',
				uncheckall: function() {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).prop('checked', true);
					});
				},
				click: function(event, ui) {
					checked=$(this).multiselect('widget').find('input:checked').length;

					if (ui.value == '0') {
						if (ui.checked == true) {
							$('#host').multiselect('uncheckAll');
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).prop('checked', true);
							});
						}
					}else if (checked == 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
						});
					}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
						if (checked > 0) {
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).click();
								$(this).prop('disable', true);
							});
						}
					}
				}
			}).multiselectfilter( {
				label: '<?php print __('Search');?>',
				placeholder: '<?php print __('Enter keyword');?>',
				width: '150'
			});
		} else if (currentTab == 'data') {
			$('#storage_location').change(function() {
				if ($(this).val() == '0') {
					$('#row_rrdp_header').hide();
					$('#row_rrdp_server').hide();
					$('#row_rrdp_port').hide();
					$('#row_rrdp_fingerprint').hide();
					$('#row_rrdp_header2').hide();
					$('#row_rrdp_load_balancing').hide();
					$('#row_rrdp_server_backup').hide();
					$('#row_rrdp_port_backup').hide();
					$('#row_rrdp_fingerprint_backup').hide();
				} else {
					$('#row_rrdp_header').show();
					$('#row_rrdp_server').show();
					$('#row_rrdp_port').show();
					$('#row_rrdp_fingerprint').show();
					$('#row_rrdp_header2').show();
					$('#row_rrdp_load_balancing').show();
					$('#row_rrdp_server_backup').show();
					$('#row_rrdp_port_backup').show();
					$('#row_rrdp_fingerprint_backup').show();
				}
			}).trigger('change');
		} else if (currentTab == 'mail') {
			initMail();

			$('#settings_how').change(function() {
				initMail();
			});

			$('#emailtest').click(function() {
				var $div = $('<div />').appendTo('body');
				$div.attr('id', 'testmail');
				$('#testmail').prop('title', '<?php print __('Test Email Results');?>');
				$('#testmail').dialog({
					autoOpen: false,
					modal: true,
					minHeight: 300,
					maxHeight: 600,
					height: 450,
					width: 500,
					show: {
						effect: 'appear',
						duration: 100
					},
					hide: {
						effect: 'appear',
						duratin: 100
					}
				});
				$.get('settings.php?action=send_test')
					.done(function(data) {
						$('#testmail').html(data);
						$('#testmail').dialog('open');
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
			initSearch();
			initGroupMember();

			$('#auth_method').change(function() {
				initAuth();
			});

			$('#ldap_mode').change(function() {
				initSearch();
			});

			$('#ldap_group_require').change(function() {
				initGroupMember();
			});
		} else if (currentTab == 'path') {
			initRRDClean();

			$('#rrd_autoclean').change(function() {
				initRRDClean();
			});

			$('#rrd_autoclean_method').change(function() {
				initRRDClean();
			});
		} else if (currentTab == 'boost') {
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
			$('#settings_smtp_password').val('');
			$('#settings_smtp_password_confirm').val('');

			switch($('#settings_how').val()) {
			case '0':
				$('#row_settings_sendmail_header').hide();
				$('#row_settings_sendmail_path').hide();
				$('#row_settings_smtp_header').hide();
				$('#row_settings_smtp_host').hide();
				$('#row_settings_smtp_port').hide();
				$('#row_settings_smtp_username').hide();
				$('#row_settings_smtp_password').hide();
				$('#row_settings_smtp_secure').hide();
				$('#row_settings_smtp_timeout').hide();
				break;
			case '1':
				$('#row_settings_sendmail_header').show();
				$('#row_settings_sendmail_path').show();
				$('#row_settings_smtp_header').hide();
				$('#row_settings_smtp_host').hide();
				$('#row_settings_smtp_port').hide();
				$('#row_settings_smtp_username').hide();
				$('#row_settings_smtp_password').hide();
				$('#row_settings_smtp_secure').hide();
				$('#row_settings_smtp_timeout').hide();
				break;
			case '2':
				$('#row_settings_sendmail_header').hide();
				$('#row_settings_sendmail_path').hide();
				$('#row_settings_smtp_header').show();
				$('#row_settings_smtp_host').show();
				$('#row_settings_smtp_port').show();
				$('#row_settings_smtp_username').show();
				$('#row_settings_smtp_password').show();
				$('#row_settings_smtp_secure').show();
				$('#row_settings_smtp_timeout').show();
				break;
			}
		}
	});

	function initBoostCache() {
		if ($('#boost_png_cache_enable').is(':checked')){
			$('#row_boost_png_cache_directory').show();
		} else {
			$('#row_boost_png_cache_directory').hide();
		}
	}

	function initBoostOD() {
		if ($('#boost_rrd_update_enable').is(':checked')){
			$('#row_boost_rrd_update_interval').show();
			$('#row_boost_rrd_update_max_records').show();
			$('#row_boost_rrd_update_max_records_per_select').show();
			$('#row_boost_rrd_update_string_length').show();
			$('#row_boost_poller_mem_limit').show();
			$('#row_boost_rrd_update_max_runtime').show();
			$('#row_boost_redirect').show();
		} else {
			$('#row_boost_rrd_update_interval').hide();
			$('#row_boost_rrd_update_max_records').hide();
			$('#row_boost_rrd_update_max_records_per_select').hide();
			$('#row_boost_rrd_update_string_length').hide();
			$('#row_boost_poller_mem_limit').hide();
			$('#row_boost_rrd_update_max_runtime').hide();
			$('#row_boost_redirect').hide();
		}
	}

	function themeChanger() {
		if ($('#selected_theme').val() != currentTheme) {
			themeChanged = true;
		} else {
			themeChanged = false;
		}
	}

	function initFonts() {
		if ($('#font_method').val() == 1) {
			$('#row_path_rrdtool_default_font').hide();
			$('#row_title_size').hide();
			$('#row_title_font').hide();
			$('#row_legend_size').hide();
			$('#row_legend_font').hide();
			$('#row_axis_size').hide();
			$('#row_axis_font').hide();
			$('#row_unit_size').hide();
			$('#row_unit_font').hide();
		} else {
			$('#row_path_rrdtool_default_font').show();
			$('#row_title_size').show();
			$('#row_legend_size').show();
			$('#row_axis_size').show();
			$('#row_unit_size').show();
			$('#row_title_font').show();
			$('#row_legend_font').show();
			$('#row_axis_font').show();
			$('#row_unit_font').show();
		}
	}

	function initRealtime() {
		if ($('#realtime_enabled').is(':checked')) {
			$('#row_realtime_gwindow').show();
			$('#row_realtime_interval').show();
			$('#row_realtime_cache_path').show();
		} else {
			$('#row_realtime_gwindow').hide();
			$('#row_realtime_interval').hide();
			$('#row_realtime_cache_path').hide();
		}
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

	function initSearch() {
		if ($('#auth_method').val() == 3) {
			switch($('#ldap_mode').val()) {
			case "0":
				$('#row_ldap_search_base_header').hide();
				$('#row_ldap_search_base').hide();
				$('#row_ldap_search_filter').hide();
				$('#row_ldap_specific_dn').hide();
				$('#row_ldap_specific_password').hide();
				break;
			case "1":
				$('#row_ldap_search_base_header').show();
				$('#row_ldap_search_base').show();
				$('#row_ldap_search_filter').show();
				$('#row_ldap_specific_dn').hide();
				$('#row_ldap_specific_password').hide();
				break;
			case "2":
				$('#row_ldap_search_base_header').show();
				$('#row_ldap_search_base').show();
				$('#row_ldap_search_filter').show();
				$('#row_ldap_specific_dn').show();
				$('#row_ldap_specific_password').show();
				break;
			}
		} else {
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
		}
	}

	function initGroupMember() {
		if ($('#auth_method').val() == 3) {
			if ($('#ldap_group_require').is(':checked')) {
				$('#row_ldap_group_header').show();
				$('#row_ldap_group_dn').show();
				$('#row_ldap_group_attrib').show();
				$('#row_ldap_group_member_type').show();
			} else {
				$('#row_ldap_group_header').hide();
				$('#row_ldap_group_dn').hide();
				$('#row_ldap_group_attrib').hide();
				$('#row_ldap_group_member_type').hide();
			}
		} else {
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
		}
	}

	function initAuth() {
		switch($('#auth_method').val()) {
		case "0": // None
			$('#row_special_users_header').hide();
			$('#row_auth_cache_enabled').hide();
			$('#row_guest_user').hide();
			$('#row_user_template').hide();
			$('#row_ldap_general_header').hide();
			$('#row_ldap_server').hide();
			$('#row_ldap_port').hide();
			$('#row_ldap_port_ssl').hide();
			$('#row_ldap_version').hide();
			$('#row_ldap_encryption').hide();
			$('#row_ldap_referrals').hide();
			$('#row_ldap_mode').hide();
			$('#row_ldap_dn').hide();
			$('#row_ldap_group_require').hide();
			$('#row_ldap_attrib').hide();
			$('#row_ldap_member_type').hide();
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			$('#row_secpass_header').hide();
			$('#row_secpass_minlen').hide();
			$('#row_secpass_reqmixcase').hide();
			$('#row_secpass_reqnum').hide();
			$('#row_secpass_reqspec').hide();
			$('#row_secpass_forceold').hide();
			$('#row_secpass_expireaccount').hide();
			$('#row_secpass_expirepass').hide();
			$('#row_secpass_history').hide();
			$('#row_secpass_lock_header').hide();
			$('#row_secpass_lockfailed').hide();
			$('#row_secpass_unlocktime').hide();
			break;
		case "1": // Builtin
			$('#row_special_users_header').show();
			$('#row_auth_cache_enabled').show();
			$('#row_guest_user').show();
			$('#row_user_template').show();
			$('#row_ldap_general_header').hide();
			$('#row_ldap_server').hide();
			$('#row_ldap_port').hide();
			$('#row_ldap_port_ssl').hide();
			$('#row_ldap_version').hide();
			$('#row_ldap_encryption').hide();
			$('#row_ldap_referrals').hide();
			$('#row_ldap_mode').hide();
			$('#row_ldap_dn').hide();
			$('#row_ldap_group_require').hide();
			$('#row_ldap_attrib').hide();
			$('#row_ldap_member_type').hide();
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			$('#row_secpass_header').show();
			$('#row_secpass_minlen').show();
			$('#row_secpass_reqmixcase').show();
			$('#row_secpass_reqnum').show();
			$('#row_secpass_reqspec').show();
			$('#row_secpass_forceold').show();
			$('#row_secpass_expireaccount').show();
			$('#row_secpass_expirepass').show();
			$('#row_secpass_history').show();
			$('#row_secpass_lock_header').show();
			$('#row_secpass_lockfailed').show();
			$('#row_secpass_unlocktime').show();
			break;
		case "2": // Web Basic
			$('#row_special_users_header').show();
			$('#row_auth_cache_enabled').hide();
			$('#row_guest_user').show();
			$('#row_user_template').show();
			$('#row_ldap_general_header').hide();
			$('#row_ldap_server').hide();
			$('#row_ldap_port').hide();
			$('#row_ldap_port_ssl').hide();
			$('#row_ldap_version').hide();
			$('#row_ldap_encryption').hide();
			$('#row_ldap_referrals').hide();
			$('#row_ldap_mode').hide();
			$('#row_ldap_dn').hide();
			$('#row_ldap_group_require').hide();
			$('#row_ldap_attrib').hide();
			$('#row_ldap_member_type').hide();
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			$('#row_secpass_header').hide();
			$('#row_secpass_minlen').hide();
			$('#row_secpass_reqmixcase').hide();
			$('#row_secpass_reqnum').hide();
			$('#row_secpass_reqspec').hide();
			$('#row_secpass_forceold').hide();
			$('#row_secpass_expireaccount').hide();
			$('#row_secpass_expirepass').hide();
			$('#row_secpass_history').hide();
			$('#row_secpass_lock_header').hide();
			$('#row_secpass_lockfailed').hide();
			$('#row_secpass_unlocktime').hide();
			break;
		case "4": // Multiple Domains
			$('#row_special_users_header').show();
			$('#row_auth_cache_enabled').show();
			$('#row_guest_user').show();
			$('#row_user_template').hide();
			$('#row_ldap_general_header').hide();
			$('#row_ldap_server').hide();
			$('#row_ldap_port').hide();
			$('#row_ldap_port_ssl').hide();
			$('#row_ldap_version').hide();
			$('#row_ldap_encryption').hide();
			$('#row_ldap_referrals').hide();
			$('#row_ldap_mode').hide();
			$('#row_ldap_dn').hide();
			$('#row_ldap_group_require').hide();
			$('#row_ldap_attrib').hide();
			$('#row_ldap_member_type').hide();
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			$('#row_secpass_header').show();
			$('#row_secpass_minlen').show();
			$('#row_secpass_reqmixcase').show();
			$('#row_secpass_reqnum').show();
			$('#row_secpass_reqspec').show();
			$('#row_secpass_forceold').show();
			$('#row_secpass_expireaccount').show();
			$('#row_secpass_expirepass').show();
			$('#row_secpass_history').show();
			$('#row_secpass_lock_header').show();
			$('#row_secpass_lockfailed').show();
			$('#row_secpass_unlocktime').show();
			break;
		case "3": // Single Domain
			$('#row_special_users_header').show();
			$('#row_auth_cache_enabled').show();
			$('#row_guest_user').show();
			$('#row_user_template').show();
			$('#row_ldap_general_header').show();
			$('#row_ldap_server').show();
			$('#row_ldap_port').show();
			$('#row_ldap_port_ssl').show();
			$('#row_ldap_version').show();
			$('#row_ldap_encryption').show();
			$('#row_ldap_referrals').show();
			$('#row_ldap_mode').show();
			$('#row_ldap_dn').show();
			$('#row_ldap_group_require').show();
			$('#row_ldap_attrib').show();
			$('#row_ldap_member_type').show();
			$('#row_ldap_group_header').show();
			$('#row_ldap_group_dn').show();
			$('#row_ldap_group_attrib').show();
			$('#row_ldap_group_member_type').show();
			$('#row_ldap_search_base_header').show();
			$('#row_ldap_search_base').show();
			$('#row_ldap_search_filter').show();
			$('#row_ldap_specific_dn').show();
			$('#row_ldap_specific_password').show();
			$('#row_cn_header').show();
			$('#row_cn_full_name').show();
			$('#row_cn_email').show();
			$('#row_secpass_header').show();
			$('#row_secpass_minlen').show();
			$('#row_secpass_reqmixcase').show();
			$('#row_secpass_reqnum').show();
			$('#row_secpass_reqspec').show();
			$('#row_secpass_forceold').show();
			$('#row_secpass_expireaccount').show();
			$('#row_secpass_expirepass').show();
			$('#row_secpass_history').show();
			$('#row_secpass_lock_header').show();
			$('#row_secpass_lockfailed').show();
			$('#row_secpass_unlocktime').show();
			initSearch();
			initGroupMember();
			break;
		default:
			$('#row_special_users_header').show();
			$('#row_auth_cache_enabled').show();
			$('#row_guest_user').show();
			$('#row_user_template').show();
			$('#row_ldap_general_header').hide();
			$('#row_ldap_server').hide();
			$('#row_ldap_port').hide();
			$('#row_ldap_port_ssl').hide();
			$('#row_ldap_version').hide();
			$('#row_ldap_encryption').hide();
			$('#row_ldap_referrals').hide();
			$('#row_ldap_mode').hide();
			$('#row_ldap_dn').hide();
			$('#row_ldap_group_require').hide();
			$('#row_ldap_attrib').hide();
			$('#row_ldap_member_type').hide();
			$('#row_ldap_group_header').hide();
			$('#row_ldap_group_dn').hide();
			$('#row_ldap_group_attrib').hide();
			$('#row_ldap_group_member_type').hide();
			$('#row_ldap_search_base_header').hide();
			$('#row_ldap_search_base').hide();
			$('#row_ldap_search_filter').hide();
			$('#row_ldap_specific_dn').hide();
			$('#row_ldap_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			$('#row_secpass_header').show();
			$('#row_secpass_minlen').show();
			$('#row_secpass_reqmixcase').show();
			$('#row_secpass_reqnum').show();
			$('#row_secpass_reqspec').show();
			$('#row_secpass_forceold').show();
			$('#row_secpass_expireaccount').show();
			$('#row_secpass_expirepass').show();
			$('#row_secpass_history').show();
			$('#row_secpass_lock_header').show();
			$('#row_secpass_lockfailed').show();
			$('#row_secpass_unlocktime').show();
			break;
		}
	}

	function initAvail() {
		switch($('#availability_method').val()) {
		case "0":
			$('#row_ping_method').hide();
			$('#row_ping_port').hide();
			$('#row_ping_timeout').hide();
			$('#row_ping_retries').hide();
			break;
		case "1":
		case "4":
			$('#row_ping_method').show();
			$('#row_ping_port').show();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();
			break;
		case "3":
			$('#row_ping_method').show();
			$('#row_ping_port').show();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();
			break;
		case "2":
		case "5":
		case "6":
			$('#row_ping_method').hide();
			$('#row_ping_port').hide();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();
			break;
		}
	}

	</script>
	<?php

	bottom_footer();

	break;
}

