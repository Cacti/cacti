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
	default:
		// We must exempt ourselves from the page refresh, or else the settings page could update while the user is making changes
		$_SESSION['custom'] = 1;
		general_header();

		unset($_SESSION['custom']);

		settings();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function api_auth_logout_everywhere() {
	$user = $_SESSION['sess_user_id'];

	if (!empty($user)) {
		db_execute_prepared('DELETE FROM user_auth_cache WHERE user_id=?', array($user));
	}
}

function form_save() {
	global $settings_user;

	// Save the users profile information
	if (isset_request_var('full_name') && isset_request_var('email_address') && isset($_SESSION['sess_user_id'])) {
		db_execute_prepared("UPDATE user_auth SET full_name = ?, email_address = ? WHERE id = ?", array(get_nfilter_request_var('full_name'), get_nfilter_request_var('email_address'), $_SESSION['sess_user_id']));
	}

	// Save the users graph settings if they have permission
	if (is_view_allowed('graph_settings') == true) {
		while (list($tab_short_name, $tab_fields) = each($settings_user)) {
			while (list($field_name, $field_array) = each($tab_fields)) {
				/* Check every field with a numeric default value and reset it to default if the inputted value is not numeric  */
				if (isset($field_array['default']) && is_numeric($field_array['default']) && !is_numeric(get_nfilter_request_var($field_name))) {
					set_request_var($field_name, $field_array['default']);
				}

				if ($field_array['method'] == 'checkbox') {
					if (isset_request_var($field_name)) {
						db_execute_prepared("REPLACE INTO settings_user (user_id,name,value) VALUES (?, ?, 'on')", array($_SESSION['sess_user_id'], $field_name));
					}else{
						db_execute_prepared("REPLACE INTO settings_user (user_id,name,value) VALUES (?, ?, '')", array($_SESSION['sess_user_id'], $field_name));
					}
				}elseif ($field_array['method'] == 'checkbox_group') {
					while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
						if (isset_request_var($sub_field_name)) {
							db_execute_prepared("REPLACE INTO settings_user (user_id,name,value) VALUES (?, ?, 'on')", array($_SESSION['sess_user_id'], $sub_field_name));
						}else{
							db_execute_prepared("REPLACE INTO settings_user (user_id,name,value) VALUES (?, ?, '')", array($_SESSION['sess_user_id'], $sub_field_name));
						}
					}
				}elseif ($field_array['method'] == 'textbox_password') {
					if (get_nfilter_request_var($field_name) != get_nfilter_request_var($field_name.'_confirm')) {
						raise_message(4);
						break;
					}elseif (isset_request_var($field_name)) {
						db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', array($_SESSION['sess_user_id'], $field_name, get_nfilter_request_var($field_name)));
					}
				}elseif ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
						if (isset_request_var($sub_field_name)) {
							db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) values (?, ?, ?)', array($_SESSION['sess_user_id'], $sub_field_name, get_nfilter_request_var($sub_field_name)));
						}
					}
				}else if (isset_request_var($field_name)) {
					db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) values (?, ?, ?)', array($_SESSION['sess_user_id'], $field_name, get_nfilter_request_var($field_name)));
				}
			}
		}
	}

	raise_message(1);

	/* reset local settings cache so the user sees the new settings */
	kill_session_var('sess_user_language');
	kill_session_var('sess_graph_config_array');
}

/* --------------------------
    User Settings Functions
   -------------------------- */

function settings() {
	global $tabs_graphs, $settings_user, $current_user, $graph_views, $current_user;

	/* you cannot have per-user graph settings if cacti's user management is not turned on */
	if (read_config_option('auth_method') == 0) {
		raise_message(6);
		display_output_messages();
		return;
	}

	if (get_request_var('action') == 'edit') {
		if (isset($_SERVER['HTTP_REFERER'])) {
			$timespan_sel_pos = strpos($_SERVER['HTTP_REFERER'],'&predefined_timespan');
			if ($timespan_sel_pos) {
				$_SERVER['HTTP_REFERER'] = substr($_SERVER['HTTP_REFERER'],0,$timespan_sel_pos);
			}
		}

		$_SESSION['profile_referer'] = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']:'graph_view.php'); 
	}

	form_start('auth_profile.php');

	html_start_box( __('User Account Details'), '100%', '', '3', 'center', '');

	$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	if (!sizeof($current_user)) {
		return;
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

	html_end_box();

	if (is_view_allowed('graph_settings') == true) {
		if (read_config_option('auth_method') != 0) {
			$settings_user['tree']['default_tree_id']['sql'] = get_graph_tree_array(true);
		}

		html_start_box( __('User Settings'), '100%', '', '3', 'center', '');

		while (list($tab_short_name, $tab_fields) = each($settings_user)) {
			$collapsible = true;

			print "<tr class='spacer tableHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$tab_short_name'><td colspan='2' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":"") . "</td></tr>\n";

			$form_array = array();

			while (list($field_name, $field_array) = each($tab_fields)) {
				$form_array += array($field_name => $tab_fields[$field_name]);

				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
						if (graph_config_value_exists($sub_field_name, $_SESSION['sess_user_id'])) {
							$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
						}

						$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($sub_field_name, $_SESSION['sess_user_id']));
					}
				}else{
					if (graph_config_value_exists($field_name, $_SESSION['sess_user_id'])) {
						$form_array[$field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($field_name, $_SESSION['sess_user_id']));
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

		html_end_box();
	}

	?>
	<script type="text/javascript">

	var themeFonts=<?php print read_config_option('font_method');?>;
	var themeChanged = false;
	var currentTheme = '<?php print get_selected_theme();?>';

	function clearPrivateData() {
		$.localStorage.removeAll();
		$.sessionStorage.removeAll();

		$('body').append('<div style="display:none;" id="cleared" title="<?php print __('Private Data Cleared');?>"><p><?php print __('Your Private Data has been cleared.');?></p></div>');

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
		$.get('auth_profile.php?action=logout_everywhere', function(data) {
			$('body').append('<div style="display:none;" id="cleared" title="<?php print __('User Sessions Cleared');?>"><p><?php print __('All your login sessions have been cleared.');?></p></div>');

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
		}else{
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

	function themeChanger() {
		if ($('#selected_theme').val() != currentTheme) {
			themeChanged = true;
		}else{
			themeChanged = false;
		}
	}

	$(function() {
		graphSettings();

		$('#navigation').show();
		$('#navigation_right').show();

		$('input[value="Save"]').unbind().click(function(event) {
			event.preventDefault();
            if (themeChanged != true) {
                $.post('auth_profile.php?header=false', $('input, select, textarea').serialize()).done(function(data) {
					loadPageNoHeader('auth_profile.php?action=noreturn&header=false');
                });
            }else{
                $.post('auth_profile.php?header=false', $('input, select, textarea').serialize()).done(function(data) {
                    document.location = 'auth_profile.php?newtheme=1';
                });
            }
		});

		$('#selected_theme').change(function() {
			themeChanger();
		});

		$('input[value="Return"]').unbind().click(function(event) {
			document.location = '<?php print $_SESSION['profile_referer'];?>';
		});
	});

	</script>
	<?php

	form_hidden_box('save_component_graph_config','1','');

	form_save_buttons(array(array('id' => 'return', 'value' => 'Return'), array('id' => 'save', 'value' => 'Save')));

	form_end();
}

