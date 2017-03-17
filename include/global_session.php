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

global $config, $refresh;

$script = basename($_SERVER['SCRIPT_NAME']);
if ($script == 'graph_view.php' || $script == 'graph.php') {
	if (isset($_SESSION['custom']) && $_SESSION['custom'] == true) {
		$refreshIsLogout = 'true';
	}else if (isset_request_var('action') && get_nfilter_request_var('action') == 'zoom') {
		$refreshIsLogout = 'true';
	}else{
		$refresh = api_plugin_hook_function('top_graph_refresh', read_user_setting('page_refresh'));
		$refreshIsLogout = 'false';
	}
} elseif (strstr($_SERVER['SCRIPT_NAME'], 'plugins')) {
	$refresh = api_plugin_hook_function('top_graph_refresh', $refresh);
	if (empty($refresh)) {
		$refreshIsLogout = 'true';
	}else{
		$refreshIsLogout = 'false';
	}
}

if (isset($_SESSION['refresh'])) {
	if (isset($_SESSION['refresh']['seconds'])) {
		$myrefresh['seconds'] = $_SESSION['refresh']['seconds'];
	}else{
		$myrefresh['seconds'] = ini_get('session.gc_maxlifetime');
	}

    if (isset($_SESSION['refresh']['logout'])) {
        $refreshIsLogout = $_SESSION['refresh']['logout'];
    }else{
		$refreshIsLogout = 'true';
	}

    if (isset($_SESSION['refresh']['page'])) {
        $myrefresh['page'] = $_SESSION['refresh']['page'];
    }else{
		$myrefresh['page'] = $config['url_path'] . 'logout.php?action=timeout';
	}

	unset($_SESSION['refresh']);
}elseif (isset($refresh) && is_array($refresh)) {
	$refreshIsLogout = 'false';
	$myrefresh['seconds'] = $refresh;
	$myrefresh['page']    = $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&':'?') . 'header=false';;
}elseif (isset($refresh)) {
	$refreshIsLogout = 'false';
	$myrefresh = $refresh;
	$myrefresh['page'] .= (strpos($myrefresh['page'], '?') ? '&':'?') . 'header=false';
} elseif (read_config_option('auth_cache_enabled') == 'on' && isset($_COOKIE['cacti_remembers'])) {
	$myrefresh['seconds'] = 99999999;
	$myrefresh['page'] = 'index.php';
	$refreshIsLogout = 'false';
}else{
	$myrefresh['seconds'] = ini_get('session.gc_maxlifetime');
	$myrefresh['page'] = $config['url_path'] . 'logout.php?action=timeout';
	$refreshIsLogout = 'true';
} ?> 
<script type='text/javascript'>
	var theme='<?php print get_selected_theme();?>';
	var refreshIsLogout=<?php print $refreshIsLogout;?>;
	var refreshPage='<?php print $myrefresh['page'];?>';
	var refreshMSeconds=<?php print $myrefresh['seconds']*1000;?>;
	var urlPath='<?php print $config['url_path'];?>';
	var previousPage='';
</script>
