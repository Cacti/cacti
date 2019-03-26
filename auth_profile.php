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

$guest_account = true;
include('./include/auth.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'logout_everywhere':
		api_auth_logout_everywhere();

		break;
	case 'clear_user_settings':
		api_auth_clear_user_settings();

		break;
	case 'reset_default':
		$name  = get_nfilter_request_var('name');

		api_auth_clear_user_setting($name);

		break;
	case 'update_data':
		$name  = get_nfilter_request_var('name');
		$value = get_nfilter_request_var('value');

		$current_tab = get_nfilter_request_var('tab');
		if ($current_tab == 'general') {
			api_auth_update_user_setting($name, $value);
		} else {
			api_plugin_hook_function('auth_profile_update_data', $current_tab);
		}

		break;

	case 'disable_2fa':
		print disable_2fa($_SESSION['sess_user_id']);
		exit;

	case 'enable_2fa':
		print enable_2fa($_SESSION['sess_user_id']);
		exit;

	case 'verify_2fa':
		print verify_2fa($_SESSION['sess_user_id'], substr('000000' . get_nfilter_request_var('code'),-6));
		exit;

	default:
		// We must exempt ourselves from the page refresh, or else the settings page could update while the user is making changes
		$_SESSION['custom'] = 1;
		general_header();

		unset($_SESSION['custom']);

		/* ================= input validation ================= */
		get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([0-9a-z_A-Z]+)$/')));
		/* ==================================================== */

		/* present a tabbed interface */
		$tabs = array(
			'general'  => array(
				'display' => __('General'),
				'url'     => $config['url_path'] . 'auth_profile.php?tab=general&header=false'
			),
			'2fa' => array(
				'display' => __('2FA'),
				'url'     => $config['url_path'] . 'auth_profile.php?tab=2fa&header=false'
			),
		);

		$tabs = api_plugin_hook_function('auth_profile_tabs', $tabs);

		/* set the default tab */
		load_current_session_value('tab', 'sess_profile_tabs', 'general');
		$current_tab = get_nfilter_request_var('tab');

		if (cacti_sizeof($tabs) > 1) {
			$i = 0;

			/* draw the tabs */
			print "<div class='tabs'><nav><ul role='tablist'>\n";

			foreach ($tabs as $tab_short_name => $attribs) {
				print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
					" href='" . html_escape($attribs['url']) .
					"'>" . $attribs['display'] . "</a></li>\n";

				$i++;
			}

			print "</ul></nav></div>\n";
		}

		if ($current_tab == 'general') {
			settings();
			settings_javascript();
		} elseif ($current_tab == '2fa') {
			settings_2fa();
		} else {
			api_plugin_hook_function('auth_profile_run_action', get_request_var('tab'));
		}

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function api_auth_logout_everywhere() {
	$user = $_SESSION['sess_user_id'];

	if (!empty($user)) {
		db_execute_prepared('DELETE FROM user_auth_cache
			WHERE user_id = ?',
			array($user));
	}
}

function api_auth_clear_user_settings() {
	$user = $_SESSION['sess_user_id'];

	if (!empty($user)) {
		if (isset_request_var('tab') && get_nfilter_request_var('tab') == 'general') {
			db_execute_prepared('DELETE FROM settings_user
				WHERE user_id = ?',
				array($user));

			kill_session_var('sess_user_config_array');
		} elseif (isset_request_var('tab')) {
			api_plugin_hook('auth_profile_reset');
		}

		raise_message('37');
	}
}

function api_auth_clear_user_setting($name) {
	global $settings_user;

	$user = $_SESSION['sess_user_id'];

	if (read_config_option('client_timezone_support') == '0') {
		unset($settings_user['client_timezone_support']);
	}

	if (!empty($user)) {
		if (isset_request_var('tab') && get_nfilter_request_var('tab') == 'general') {
			db_execute_prepared('DELETE FROM settings_user
				WHERE user_id = ?
				AND name = ?',
				array($user, $name));

			foreach($settings_user as $tab => $settings) {
				if (isset($settings[$name])) {
					if (isset($settings[$name]['default'])) {
						db_execute_prepared('INSERT INTO settings_user
							(name, value, user_id)
							VALUES (?, ?, ?)',
							array($name, $settings[$name]['default'], $user));

						print $settings[$name]['default'];

						kill_session_var('sess_user_config_array');

						break;
					}
				}
			}
		} else {
			api_plugin_hook_function('auth_profile_reset_value', $name);
		}
	}
}

function api_auth_update_user_setting($name, $value) {
	global $settings_user;

	$user = $_SESSION['sess_user_id'];

	if (!empty($user)) {
		if ($name == 'full_name' || $name == 'email_address') {
			db_execute_prepared("UPDATE user_auth
				SET $name = ?
				WHERE id = ?",
				array($value, $user));
		} else {
			foreach($settings_user as $tab => $settings) {
				if (isset($settings[$name])) {
					db_execute_prepared('REPLACE INTO settings_user
						(name, value, user_id)
						VALUES (?, ?, ?)',
						array($name, $value, $user));

					kill_session_var('sess_user_config_array');
					kill_session_var('selected_theme');
					kill_session_var('sess_user_language');

					break;
				}
			}
		}
	}
}

function form_save() {
	global $settings_user;

	// Save the users profile information
	if (isset_request_var('full_name') && isset_request_var('email_address') && isset($_SESSION['sess_user_id'])) {
		db_execute_prepared("UPDATE user_auth
			SET full_name = ?, email_address = ?
			WHERE id = ?",
			array(
				get_nfilter_request_var('full_name'),
				get_nfilter_request_var('email_address'),
				$_SESSION['sess_user_id']
			)
		);
	}

	$errors = array();

	// Save the users graph settings if they have permission
	if (is_view_allowed('graph_settings') == true && isset_request_var('tab') && get_nfilter_request_var('tab') == 'general') {
		save_user_settings($_SESSION['sess_user_id']);
	} elseif (isset_request_var('tab')) {
		api_plugin_hook('auth_profile_save');
	}

	if (cacti_sizeof($errors) == 0) {
		raise_message(1);
	} else {
		raise_message(35);

		foreach($errors as $error) {
			raise_message($error);
		}
	}

	/* reset local settings cache so the user sees the new settings */
	kill_session_var('sess_user_language');
	kill_session_var('sess_user_config_array');
	kill_session_var('selected_theme');
}

/* --------------------------
    User Settings Functions
   -------------------------- */

function settings() {
	global $tabs_graphs, $settings_user, $current_user, $graph_views, $current_user;

	/* you cannot have per-user graph settings if cacti's user management is not turned on */
	if (read_config_option('auth_method') == 0) {
		raise_message(6);
		return;
	}

	if (isset($_SERVER['HTTP_REFERER'])) {
		$referer = $_SERVER['HTTP_REFERER'];

		if (strpos($referer, 'auth_profile.php') === false) {
			$timespan_sel_pos = strpos($referer, '&predefined_timespan');
			if ($timespan_sel_pos) {
				$referer = substr($referer, 0, $timespan_sel_pos);
			}

			$_SESSION['profile_referer'] = $referer;
		}
	} elseif (!isset($_SESSION['profile_referer'])) {
		$_SESSION['profile_referer'] = 'graph_view.php';
	}

	form_start('auth_profile.php', 'chk');

	html_start_box(__('User Account Details'), '100%', true, '3', 'center', '');

	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($_SESSION['sess_user_id']));

	if (!cacti_sizeof($current_user)) {
		return;
	}

	// Set the graph views the user has permission to
	unset($graph_views);
	if (is_view_allowed('show_tree')) {
		$graph_views[1] = __('Tree View');
	}

	if (is_view_allowed('show_list')) {
		$graph_views[2] = __('List View');
	}

	if (is_view_allowed('show_preview')) {
		$graph_views[2] = __('Preview View');
	}

	if (cacti_sizeof($graph_views)) {
		$settings_user['general']['default_view_mode']['array'] = $graph_views;
	} else {
		unset($settings_user['general']['default_view_mode']);
	}

	/* file: user_admin.php, action: user_edit (host) */
	$fields_user = array(
		'username' => array(
			'method' => 'value',
			'friendly_name' => __('User Name'),
			'description' => __('The login name for this user.'),
			'value' => '|arg1:username|',
			'max_length' => '40',
			'size' => '40'
		),
		'full_name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Full Name'),
			'description' => __('A more descriptive name for this user, that can include spaces or special characters.'),
			'value' => '|arg1:full_name|',
			'max_length' => '120',
			'size' => '60'
		),
		'email_address' => array(
			'method' => 'textbox',
			'friendly_name' => __('Email Address'),
			'description' => __('An Email Address you be reached at.'),
			'value' => '|arg1:email_address|',
			'max_length' => '60',
			'size' => '60'
		),
		'clear_settings' => array(
			'method' => 'button',
			'friendly_name' => __('Clear User Settings'),
			'description' => __('Return all User Settings to Default values.'),
			'value' => __('Clear User Settings'),
			'on_click' => 'clearUserSettings()'
		),
		'private_data' => array(
			'method' => 'button',
			'friendly_name' => __('Clear Private Data'),
			'description' => __('Clear Private Data including Column sizing.'),
			'value' => __('Clear Private Data'),
			'on_click' => 'clearPrivateData()'
		)
	);

	if (read_config_option('auth_cache_enabled') == 'on') {
		$fields_user += array(
			'logout_everywhere' => array(
				'method' => 'button',
				'friendly_name' => __('Logout Everywhere'),
				'description' => __('Clear all your Login Session Tokens.'),
				'value' => __('Logout Everywhere'),
				'on_click' => 'logoutEverywhere()'
	        )
		);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_user, (isset($current_user) ? $current_user : array()))
		)
	);

	html_end_box(true, true);

	if (is_view_allowed('graph_settings') == true) {
		if (read_config_option('auth_method') != 0) {
			$settings_user['tree']['default_tree_id']['sql'] = get_allowed_trees(false, true);
		}

		html_start_box(__('User Settings'), '100%', true, '3', 'center', '');

		foreach ($settings_user as $tab_short_name => $tab_fields) {
			$collapsible = true;

			print "<div class='spacer formHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$tab_short_name'><div class='formHeaderText'>" . $tabs_graphs[$tab_short_name] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":"") . "</div></div>\n";

			$form_array = array();

			foreach ($tab_fields as $field_name => $field_array) {
				$form_array += array($field_name => $tab_fields[$field_name]);

				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						if (graph_config_value_exists($sub_field_name, $_SESSION['sess_user_id'])) {
							$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
						}

						$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value
							FROM settings_user
							WHERE name = ?
							AND user_id = ?',
							array($sub_field_name, $_SESSION['sess_user_id']));
					}
				} else {
					if (graph_config_value_exists($field_name, $_SESSION['sess_user_id'])) {
						$form_array[$field_name]['form_id'] = 1;
					}

					$user_row = db_fetch_row_prepared('SELECT value
						FROM settings_user
						WHERE name = ?
						AND user_id = ?',
						array($field_name, $_SESSION['sess_user_id']));

					if (cacti_sizeof($user_row)) {
						$form_array[$field_name]['user_set'] = true;
						$form_array[$field_name]['value'] = $user_row['value'];
					} else {
						$form_array[$field_name]['user_set'] = false;
						$form_array[$field_name]['value'] = null;
					}
				}
			}

			draw_edit_form(
				array(
					'config' => array(
						'no_form_tag' => true
					),
					'fields' => $form_array
				)
			);
		}

		print "</td></tr>\n";

		html_end_box(true, true);
	}

	form_hidden_box('save_component_graph_config','1','');

	form_save_buttons(array(array('id' => 'return', 'value' => __esc('Return'))));

	form_end();
}

function settings_2fa() {
	global $tabs_graphs, $settings_user, $current_user, $graph_views, $current_user;

	/* you cannot have per-user graph settings if cacti's user management is not turned on */
	if (read_config_option('auth_method') == 0) {
		raise_message(6);
		return;
	}

	if (isset($_SERVER['HTTP_REFERER'])) {
		$referer = $_SERVER['HTTP_REFERER'];

		if (strpos($referer, 'auth_profile.php') === false) {
			$timespan_sel_pos = strpos($referer, '&predefined_timespan');
			if ($timespan_sel_pos) {
				$referer = substr($referer, 0, $timespan_sel_pos);
			}

			$_SESSION['profile_referer'] = $referer;
		}
	} elseif (!isset($_SESSION['profile_referer'])) {
		$_SESSION['profile_referer'] = 'graph_view.php';
	}

	form_start('auth_profile.php', 'chk');

	html_start_box(__('2FA Settings'), '100%', true, '3', 'center', '');

	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($_SESSION['sess_user_id']));

	if (!cacti_sizeof($current_user)) {
		return;
	}

	$fields_user = array(
		'username' => array(
			'method' => 'value',
			'friendly_name' => __('User Name'),
			'description' => __('The login name for this user.'),
			'value' => '|arg1:username|',
			'max_length' => '40',
			'size' => '40',
		),
		'tfa_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('2FA Enabled'),
			'description' => __('Whether 2FA is enabled for this user.'),
			'value' => '|arg1:tfa_enabled|',
			'on_click' => 'toggle2FA()',
			'max_length' => '40',
			'size' => '40',
		),
		'tfa_qr_code' => array(
			'method' => 'value',
			'friendly_name' => __('2FA QA Code'),
			'description' => __('The 2FA QA Code to be scanned with Google Authenticator, Authy or any compatible 2FA app'),
			'value' => '	',
			'max_length' => '40',
			'size' => '40',
		),
		'tfa_token' => array(
			'method' => 'textbox',
			'friendly_name' => __('2FA App Token'),
			'description' => __('The token generated by Google Authenticator, Auth or any compatible 2FA app'),
			'value' => '',
			'max_length' => '40',
			'size' => '40',
		),
		'tfa_verify' => array(
			'method' => 'button',
			'friendly_name' => __('Verify App Token'),
			'description' => __('Verify the 2FA App token entered above'),
			'value' => __('Verify App Token'),
			'max_length' => '40',
			'size' => '40',
		),
	);

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_user, $current_user),
		)
	);

	html_end_box(true, true);

	form_save_buttons(array(array('id' => 'return', 'value' => __esc('Return'))));

	?>
	<script type='text/javascript'>
	var tfa_enabled = <?php print $current_user['tfa_enabled'] != '' ? 'true' : 'false'; ?>;
	var tfa_text = '<?php print $current_user['tfa_enabled'] != '' ? __('Enabled') : __('Disabled'); ?>';
	var tfa_verified = false;
	var tfa_enabling = '<?php print __('Enabling...'); ?>';

	function set2FAText(text,id,cls) {
		if (id === undefined) {
			id ='tfa_qr_code';
		}

		if (cls !== undefined) {
			cls = ' class=\'' + cls + '\'';
		}
		$('#' + id).html('<div id=\'' + id + '\'' + cls + '>' + text + '</div>');
	}

	$(function() {
		$('#row_tfa_token,#row_tfa_verify').hide();
		$('#tfa_qr_code').parent().parent().html('<div id=\'tfa_qr_code\'></div>');
		$('#tfa_verify').parent().append('<div id="tfa_error" class="textError"></div>');

		set2FAText(tfa_text);
		$('#tfa_enabled').change(function(e) {
			$('#tfa_enabled').prop('disabled',true);

			if ($('#tfa_enabled').is(':checked')) {
				if (!tfa_verified) {
					set2FAText(tfa_enabling);
					$.getJSON('auth_profile.php?action=enable_2fa', function(data) {
						$('#tfa_enabled').prop('disabled',false);

						if (data.status == 200) {
							var link = '<a href="'  + data.link + '"><img src="' + data.link + '"/></a>';

							set2FAText(link);
							$('#row_tfa_token,#row_tfa_verify').show();
						} else {
							set2FAText(data.status + ' - ' + data.text);
						}
					});
				} else {
					$('#tfa_enabled').prop('disabled',false);
				}
			} else {
				$.getJSON('auth_profile.php?action=disable_2fa', function(data) {
					$('#tfa_enabled').prop('disabled',false);
					set2FAText('<?php print __('Disabled')?>');
					$('#row_tfa_token,#row_tfa_verify').hide();
				});
			}
		});
		$('#tfa_verify').click(function(e) {
			var code = $('#tfa_token').val();
			$.getJSON('auth_profile.php?action=verify_2fa&code=' + code, function(data) {
				var tfa_error = $('#tfa_error');
				if (!(tfa_error.length > 0)) {
				}
				$('#tfa_token').val('');
				if (data.status == 200) {
					set2FAText(data.text);
					$('#row_tfa_token,#row_tfa_verify').hide();
					data.text = '';
				}
				set2FAText(data.text, 'tfa_error', 'textError');
			});
		});
		$('#return').click(function() {
			document.location = '<?php print $_SESSION['profile_referer'];?>';
		});
	});
	</script>
<?php
	form_end();
}

function settings_javascript() {
	global $config;

	?>
	<script type='text/javascript'>

	var themeFonts   = <?php print read_config_option('font_method');?>;
	var currentTab   = '<?php print get_nfilter_request_var('tab');?>';
	var currentTheme = '<?php print get_selected_theme();?>';
	var currentLang  = '<?php print read_config_option('user_language');?>';
	var authMethod   = '<?php print read_config_option('auth_method');?>';

	function clearUserSettings() {
		$.get('auth_profile.php?action=clear_user_settings', function() {
			document.location = 'auth_profile.php?newtheme=1';
			$('#clear_settings').blur();
		});
	}

	function clearPrivateData() {
		Storages.localStorage.removeAll();
		Storages.sessionStorage.removeAll();

		$('body').append('<div style="display:none;" id="cleared" title="<?php print __esc('Private Data Cleared');?>"><p><?php print __('Your Private Data has been cleared.');?></p></div>');

		$('#private_data').blur();
		$('#cleared').dialog({
			modal: true,
			resizable: false,
			draggable: false,
			height:140,
			buttons: {
				Ok: function() {
					$(this).dialog('close');
					$('#cleared').remove();
				}
			}
		});

		$('#cleared').dialog('open');
	}

	function logoutEverywhere() {
		$('#logout_everywhere').blur();
		$.get('auth_profile.php?action=logout_everywhere', function(data) {
			$('body').append('<div style="display:none;" id="cleared" title="<?php print __esc('User Sessions Cleared');?>"><p><?php print __('All your login sessions have been cleared.');?></p></div>');

			$('#cleared').dialog({
				modal: true,
				resizable: false,
				draggable: false,
				height:140,
				buttons: {
					Ok: function() {
						$(this).dialog('close');
						$('#cleared').remove();
					}
				}
			});

			$('#cleared').dialog('open');
		});
	}

	function graphSettings() {
		if (themeFonts == 1) {
			$('#row_fonts').hide();
			$('#row_custom_fonts').hide();
			$('#row_title_size').hide();
			$('#row_title_font').hide();
			$('#row_legend_size').hide();
			$('#row_legend_font').hide();
			$('#row_axis_size').hide();
			$('#row_axis_font').hide();
			$('#row_unit_size').hide();
			$('#row_unit_font').hide();
		} else {
			var custom_fonts = $('#custom_fonts').is(':checked');

			switch(custom_fonts) {
			case true:
				$('#row_fonts').show();
				$('#row_title_size').show();
				$('#row_title_font').show();
				$('#row_legend_size').show();
				$('#row_legend_font').show();
				$('#row_axis_size').show();
				$('#row_axis_font').show();
				$('#row_unit_size').show();
				$('#row_unit_font').show();
				break;
			case false:
				$('#row_fonts').show();
				$('#row_title_size').hide();
				$('#row_title_font').hide();
				$('#row_legend_size').hide();
				$('#row_legend_font').hide();
				$('#row_axis_size').hide();
				$('#row_axis_font').hide();
				$('#row_unit_size').hide();
				$('#row_unit_font').hide();
				break;
			}
		}
	}

	$(function() {
		graphSettings();

		$('#navigation, #navigation_right').show();
		$('#tabs').find('li a.selected').removeClass('selected');

		$('input[value="<?php print __esc('Save');?>"]').unbind().click(function(event) {
			event.preventDefault();
			$.post('auth_profile.php?header=false', $('input, select, textarea').serialize()).done(function(data) {
				loadPageNoHeader('auth_profile.php?action=noreturn&header=false');
			});
		});

		if (authMethod == 2) {
			$('#row_logout_everywhere').hide();
		}

		$('#auth_profile_edit2 .formData, #auth_profile_noreturn2 .formData').each(function() {
			if ($(this).find('select, input[type!="button"]').length) {
				$(this).parent().hover(
					function() {
						var id = $(this).find('select, input[type!="button"]').attr('id');

						$('<a class="resetHover" data-id="'+id+'" style="padding-left:10px" href="#"><?php print __('Reset');?></a>').appendTo($(this));
						$('.resetHover').on('click', function(event) {
							event.preventDefault();

							var id = $(this).attr('data-id');

							if (id != undefined) {
								$.get('auth_profile.php?tab='+currentTab+'&action=reset_default&name='+id, function(data) {
									if (id != 'selected_theme' && id != 'user_language' && id != 'enable_hscroll') {
										if ($('#'+id).is(':checkbox')) {
											if (data == 'on') {
												$('#'+id).prop('checked', true);
											} else {
												$('#'+id).prop('checked', false);
											}
										} else {
											$('#'+id).val(data);
											if ($('#'+id).selectmenu('instance')) {
												$('#'+id).selectmenu('refresh');
											}
										}
									} else {
										document.location = 'auth_profile.php?action=edit';
									}
								});
							}
						});
					},
					function() {
						$('.resetHover').remove();
					}
				);
			}
		});

		$('select, input[type!="button"]').unbind().keyup(function() {
			name  = $(this).attr('id');
			if ($(this).attr('type') == 'checkbox') {
				if ($(this).is(':checked')) {
					value = 'on';
				} else {
					value = '';
				}
			} else {
				value = $(this).val();
			}

			$.post('auth_profile.php?tab='+currentTab+'&action=update_data', {
				__csrf_magic: csrfMagicToken,
				name: name,
				value: value
			});
		}).change(function() {
			name  = $(this).attr('id');
			if ($(this).attr('type') == 'checkbox') {
				if ($(this).is(':checked')) {
					value = 'on';
				} else {
					value = '';
				}
			} else {
				value = $(this).val();
			}

			$.post('auth_profile.php?tab='+currentTab+'&action=update_data', {
				__csrf_magic: csrfMagicToken,
				name: name,
				value: value
				}, function() {
				if (name == 'selected_theme' || name == 'user_language' || name == 'enable_hscroll') {
					document.location = 'auth_profile.php?action=edit';
				}
			});
		});

		$('#return').click(function() {
			document.location = '<?php print $_SESSION['profile_referer'];?>';
		});

		// set the buttons active
		$('#clear_settings, #private_data, #logout_everywhere').addClass('ui-state-active');
	});

	</script>
	<?php
}
