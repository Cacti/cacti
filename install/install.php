<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
error_reporting(E_ALL);

define('IN_CACTI_INSTALL', 1);

include_once('../include/auth.php');
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_automation.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/import.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');
include_once('./functions.php');

set_default_action();

prime_default_settings();

/***** SAFETY CHECKS FOR OLDER OR SECURED SYSTEMS ****/
$hasShellExec  = is_function_enabled('shell_exec');
$hasExec       = is_function_enabled('exec');
$hasJson       = interface_exists('JsonSerializable');
$hasEverything = $hasJson && $hasShellExec && $hasExec;

if ($hasEverything) {
	include_once('../lib/installer.php');
}

?>
<!DOCTYPE html>
<html>
<head>
<?php
print html_common_header(__('Cacti Server v%s - Maintenance', format_cacti_version_text(CACTI_VERSION)));
if ($hasEverything) {
	print get_md5_include_js('install/install.js');
}
print get_md5_include_css('install/install.css');
?>
</head>
<body>
	<div class='cactiInstallTable'>
		<div class='cactiTableTitleRow cactiBorderWall'>
			<div class='textHeaderDark'><?php print __('Cacti Server v%s - Installation Wizard', CACTI_VERSION_BRIEF_FULL); ?><span style="float:right"><i id="installRefresh" class="fa fa-redo"></i></span></div>
		</div>
		<div class='cactiInstallArea cactiBorderWall'>
			<div class='cactiInstallAreaContent' id='installContent'>
<?php
if ($hasEverything) {
	print Installer::sectionTitle(__('Initializing'));
	print Installer::sectionNormal(__('Please wait while the installation system for Cacti Version %s initializes. You must have JavaScript enabled for this to work.', CACTI_VERSION_BRIEF_FULL));
} else {
	print '<div class="installErrorImage"><img src=\'../images/cacti_logo.svg\'></div>';
	print '<div class="installErrorText">';
	print '<p>' . __('FATAL: We are unable to continue with this installation. In order to install Cacti, PHP must be at version 5.4 or later.') . '</p>';
	print '<ul>';
	if (!$hasJson) {
		print '<li>' . __('The php-json module must also be installed.') . '<br>' . __('See the PHP Manual: <a href="http://php.net/manual/en/book.json.php">JavaScript Object Notation</a>.') . '</li>';
		print '<br>';
	}
	if (!($hasExec && $hasShellExec)) {
		print '<li>' . __('The shell_exec() and/or exec() functions are currently blocked.') . '<br>' . __('See the PHP Manual: <a href="http://php.net/manual/en/ini.core.php#ini.disable-functions">Disable Functions</a>.') .'</li>';
	}
	print '</ul></div>';
}
?>
			</div>
			<div class='cactiInstallLoader' id='installLoader'>
				<div class='cactiInstallLoaderLogo'><img src='../images/cacti_logo.svg' /></div>
				<div class='cactiInstallLoaderSpinnerTheme cactiInstallLoaderSpinner'></div>
			</div>
		</div>
		<div class='cactiInstallButtonArea saveRow'>
			<input class='installButton' id='buttonPrevious' type='button' value='Previous' style='display: none'>
			<input class='installButton' id='buttonNext' type='button' value='Next' style='display: none'>
			<input class='installButton' id='buttonTest' type='button' value='Test' style='display:none'>
			<input id='installData' type='hidden'>
		</div>
		<div id='installDebug'></div>
		<div class='cactiInstallCopyrightArea textHeaderDark'><?php print COPYRIGHT_YEARS;?></div>
	</div>
<?php
include_once(dirname(__FILE__) . '/../include/global_session.php');
?>
</body>
</html>
