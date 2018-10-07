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

global $config, $refresh, $messages;

if (isset($_SESSION['automation_message']) && $_SESSION['automation_message'] != '') {
	$messages['automation_message'] = array(
		'message' => $_SESSION['automation_message'],
		'type' => 'info'
	);
	kill_session_var('automation_message');
}

if (isset($_SESSION['clog_message']) && $_SESSION['clog_message'] != '') {
	$messages['clog_message'] = array(
		'message' => $_SESSION['clog_message'],
		'type' => 'info'
	);
	kill_session_var('clog_message');
}

if (isset($_SESSION['clog_error']) && $_SESSION['clog_error'] != '') {
	$messages['clog_error'] = array(
		'message' => $_SESSION['clog_error'],
		'type' => 'error'
	);
	kill_session_var('clog_error');
}

if (isset($_SESSION['reports_message']) && $_SESSION['reports_message'] != '') {
	$messages['reports_message'] = array(
		'message' => $_SESSION['reports_message'],
		'type' => 'info'
	);
	kill_session_var('reports_message');
}

$script = basename($_SERVER['SCRIPT_NAME']);
if ($script == 'graph_view.php' || $script == 'graph.php') {
	if (isset($_SESSION['custom']) && $_SESSION['custom'] == true) {
		$refreshIsLogout = 'true';
	}else if (isset_request_var('action') && get_nfilter_request_var('action') == 'zoom') {
		$refreshIsLogout = 'true';
	} else {
		$refresh = api_plugin_hook_function('top_graph_refresh', read_user_setting('page_refresh'));
		$refreshIsLogout = 'false';
	}
} elseif (strstr($_SERVER['SCRIPT_NAME'], 'plugins')) {
	$refresh = api_plugin_hook_function('top_graph_refresh', $refresh);
	if (empty($refresh)) {
		$refreshIsLogout = 'true';
	} else {
		$refreshIsLogout = 'false';
	}
}

if (isset($_SESSION['refresh'])) {
	if (isset($_SESSION['refresh']['seconds'])) {
		$myrefresh['seconds'] = $_SESSION['refresh']['seconds'];
	} else {
		$myrefresh['seconds'] = ini_get('session.gc_maxlifetime');
	}

    if (isset($_SESSION['refresh']['logout'])) {
        $refreshIsLogout = $_SESSION['refresh']['logout'];
    } else {
		$refreshIsLogout = 'true';
	}

    if (isset($_SESSION['refresh']['page'])) {
        $myrefresh['page'] = sanitize_uri($_SESSION['refresh']['page']);
    } else {
		$myrefresh['page'] = $config['url_path'] . 'logout.php?action=timeout';
	}

	unset($_SESSION['refresh']);
} elseif (isset($refresh) && is_array($refresh)) {
	$myrefresh['seconds'] = $refresh['seconds'];
	$myrefresh['page']    = sanitize_uri($refresh['page'] . (strpos($refresh['page'], '?') ? '&':'?') . 'header=false');
	$refreshIsLogout      = 'false';
} elseif (isset($refresh)) {
	$myrefresh['seconds'] = $refresh;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&':'?') . 'header=false');
	$refreshIsLogout      = 'false';
} elseif (read_config_option('auth_cache_enabled') == 'on' && isset($_COOKIE['cacti_remembers'])) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = 'index.php';
	$refreshIsLogout      = 'false';
} elseif (read_config_option('auth_method') == 2) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = 'index.php';
	$refreshIsLogout      = 'false';
} elseif (!isset($_SESSION['sess_user_id']) && strpos($_SERVER['REQUEST_URI'], 'index.php') !== false) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI']);
	$refreshIsLogout      = 'false';
} else {
	$myrefresh['seconds'] = ini_get('session.gc_maxlifetime');
	$myrefresh['page']    = $config['url_path'] . 'logout.php?action=timeout';
	$refreshIsLogout      = 'true';
} ?>
<script type='text/javascript'>
	var cactiVersion='<?php print $config['cacti_version'];?>';
	var cactiServerOS='<?php print $config['cacti_server_os'];?>';
	var theme='<?php print get_selected_theme();?>';
	var refreshIsLogout=<?php print $refreshIsLogout;?>;
	var refreshPage='<?php print $myrefresh['page'];?>';
	var refreshMSeconds=<?php print $myrefresh['seconds']*1000;?>;
	var urlPath='<?php print $config['url_path'];?>';
	var previousPage='';
	var searchFilter='<?php print __('Enter a search term');?>';
	var searchRFilter='<?php print __('Enter a regular expression');?>';
	var noFileSelected='<?php print __('No file selected');?>';
	var timeGraphView='<?php print __('Time Graph View');?>';
	var filterSettingsSaved='<?php print __('Filter Settings Saved');?>';
	var spikeKillResuls='<?php print __('SpikeKill Results');?>';
	var utilityView='<?php print __('Utility View');?>';
	var realtimeClickOn='<?php print __('Click to view just this Graph in Realtime');?>';
	var realtimeClickOff='<?php print __('Click again to take this Graph out of Realtime');?>';
	var treeView='<?php print __('Tree View');?>';
	var listView='<?php print __('List View');?>';
	var previewView='<?php print __('Preview View');?>';
	var cactiHome='<?php print __('Cacti Home');?>';
	var cactiProjectPage='<?php print __('Cacti Project Page');?>';
	var cactiCommunityForum='<?php print __('User Community');?>';
	var cactiDocumentation='<?php print __('Documentation');?>';
	var reportABug='<?php print __('Report a bug');?>';
	var aboutCacti='<?php print __('About Cacti');?>';
	var spikeKillResults='<?php print __esc('SpikeKill Results');?>';
	var showHideFilter='<?php print __esc('Click to Show/Hide Filter');?>';
	var clearFilterTitle='<?php print __esc('Clear Current Filter');?>';
	var clipboard='<?php print __esc('Clipboard');?>';
	var clipboardID='<?php print __esc('Clipboard ID');?>';
	var clipboardNotAvailable='<?php print __esc('Copy operation is unavailable at this time');?>';
	var clipboardCopyFailed='<?php print __esc('Failed to find data to copy!');?>';
	var clipboardUpdated='<?php print __esc('Clipboard has been updated');?>';
	var clipboardNotUpdated='<?php print __esc('Sorry, your clipboard could not be updated at this time');?>';
	var defaultSNMPSecurityLevel='<?php print read_config_option('snmp_security_level');?>';
	var defaultSNMPAuthProtocol='<?php print read_config_option('snmp_auth_protocol');?>';
	var defaultSNMPPrivProtocol='<?php print read_config_option('snmp_priv_protocol');?>';
	var passwordPass='<?php print __('Passphrase length meets 8 character minimum');?>';
	var passwordTooShort='<?php print __('Passphrase too short');?>';
	var passwordMatchTooShort='<?php print __('Passphrase matches but too short');?>';
	var passwordNotMatchTooShort='<?php print __('Passphrase too short and not matching');?>';
	var passwordMatch='<?php print __('Passphrases match');?>';
	var passwordNotMatch='<?php print __('Passphrases do not match');?>';
	var errorOnPage='<?php print __('Sorry, we could not process your last action.');?>';
	var errorNumberPrefix='<?php print __('Error:');?>';
	var errorReasonPrefix='<?php print __('Reason:');?>';
	var errorReasonTitle='<?php print __('Action failed');?>';
	var errorReasonUnexpected='<?php print __('The response to the last action was unexpected.');?>';
	var sessionMessageTitle='<?php print __('Operation successful');?>';
	var sessionMessageSave='<?php print __('The Operation was successful.  Details are below.');?>';
	var sessionMessage=<?php print display_output_messages(false);?>;
	var sessionMessageOk='<?php print __('Ok');?>';
	var sessionMessagePause='<?php print __('Pause');?>';
	var sessionMessageContinue='<?php print __('Continue');?>';
	var sessionMessageCancel='<?php print __('Cancel');?>';
	var zoom_i18n_zoom_in='<?php print __('Zoom In');?>';
	var zoom_i18n_zoom_out='<?php print __('Zoom Out');?>';
	var zoom_i18n_zoom_out_factor='<?php print __('Zoom Out Factor');?>';
	var zoom_i18n_timestamps='<?php print __('Timestamps');?>';
	var zoom_i18n_zoom_2='<?php print __('2x');?>';
	var zoom_i18n_zoom_4='<?php print __('4x');?>';
	var zoom_i18n_zoom_8='<?php print __('8x');?>';
	var zoom_i18n_zoom_16='<?php print __('16x');?>';
	var zoom_i18n_zoom_32='<?php print __('32x');?>';
	var zoom_i18n_zoom_out_positioning='<?php print __('Zoom Out Positioning');?>';
	var zoom_i18n_mode='<?php print __('Zoom Mode');?>';
	var zoom_i18n_graph='<?php print __('Graph');?>';
	var zoom_i18n_quick='<?php print __('Quick');?>';
	var zoom_i18n_advanced='<?php print __('Advanced');?>';
	var zoom_i18n_newTab='<?php print __('Open in new tab');?>';
	var zoom_i18n_save_graph='<?php print __('Save graph');?>';
	var zoom_i18n_copy_graph='<?php print __('Copy graph');?>';
	var zoom_i18n_copy_graph_link='<?php print __('Copy graph link');?>';
	var zoom_i18n_on='<?php print __('Always On');?>';
	var zoom_i18n_auto='<?php print __('Auto');?>';
	var zoom_i18n_off='<?php print __('Always Off');?>';
	var zoom_i18n_begin='<?php print __('Begin with');?>';
	var zoom_i18n_center='<?php print __('Center');?>';
	var zoom_i18n_end='<?php print __('End with');?>';
	var zoom_i18n_disabled='<?php print __('Disabled');?>';
	var zoom_i18n_close='<?php print __('Close');?>';
	var zoom_i18n_settings='<?php print __('Settings');?>';
	var zoom_i18n_3rd_button='<?php print __('3rd Mouse Button');?>';
</script>
