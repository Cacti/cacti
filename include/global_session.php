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

$script = basename($_SERVER['SCRIPT_NAME']);
if ($script == 'graph_view.php' || $script == 'graph.php') {
	if (isset($_SESSION['custom']) && $_SESSION['custom'] == true) {
		$refreshIsLogout = 'true';
	} elseif (isset_request_var('action') && get_nfilter_request_var('action') == 'zoom') {
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
		$refreshIsLogout = 'false';
	}

    if (isset($_SESSION['refresh']['page'])) {
        $myrefresh['page'] = sanitize_uri($_SESSION['refresh']['page']);
    } else {
		$myrefresh['page'] = $config['url_path'] . 'logout.php?action=timeout';
		$refreshIsLogout   = 'true';
	}

	unset($_SESSION['refresh']);
} elseif (isset($refresh) && is_array($refresh)) {
	$myrefresh['seconds'] = $refresh['seconds'];
	$myrefresh['page']    = sanitize_uri($refresh['page']);
	$refreshIsLogout      = 'false';
} elseif (isset($refresh)) {
	$myrefresh['seconds'] = $refresh;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI']);
	$refreshIsLogout      = 'false';
} elseif (read_config_option('auth_cache_enabled') == 'on' && isset($_SESSION['cacti_remembers']) && $_SESSION['cacti_remembers'] == true) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI']);
	$refreshIsLogout      = 'false';
} elseif (read_user_setting('user_auto_logout_time') > 0 && is_realm_allowed(8)) {
	$myrefresh['seconds'] = read_user_setting('user_auto_logout_time');
	$myrefresh['page']    = $config['url_path'] . 'logout.php?action=timeout';
	$refreshIsLogout      = 'true';
} elseif (read_config_option('auth_method') == 2) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = 'index.php';
	$refreshIsLogout      = 'false';
} elseif (!isset($_SESSION['sess_user_id']) && isset($_SERVER['REQUEST_URL']) && strpos($_SERVER['REQUEST_URI'], 'index.php') !== false) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI']);
	$refreshIsLogout      = 'false';
} else {
	$myrefresh['seconds'] = ini_get('session.gc_maxlifetime');
	$myrefresh['page']    = $config['url_path'] . 'logout.php?action=timeout';
	$refreshIsLogout      = 'true';
}

/* guest account does not auto log off */
if (isset($_SESSION['sess_user_id']) && $_SESSION['sess_user_id'] == read_config_option('guest_user')) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page']    = sanitize_uri($_SERVER['REQUEST_URI']);
	$refreshIsLogout      = 'false';
}

?>
<script type='text/javascript'>
	var cactiVersion='<?php print $config['cacti_version'];?>';
	var cactiServerOS='<?php print $config['cacti_server_os'];?>';
	var cactiAction='<?php print get_filter_request_var('action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([-a-zA-Z0-9_\s]+)$/')));?>';
	var theme='<?php print get_selected_theme();?>';
	var refreshIsLogout=<?php print $refreshIsLogout;?>;
	var refreshPage='<?php print $myrefresh['page'];?>';
	var refreshMSeconds=<?php print $myrefresh['seconds']*1000;?>;
	var urlPath='<?php print $config['url_path'];?>';
	var previousPage='';
	var sessionMessage=<?php print display_output_messages(false);?>;
	var csrfMagicToken='<?php print csrf_get_tokens();?>';
</script>
