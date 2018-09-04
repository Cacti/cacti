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
error_reporting(E_ALL);

define('IN_CACTI_INSTALL', 1);

include_once('../include/auth.php');
include_once('../lib/api_data_source.php');
include_once('../lib/api_device.php');
include_once('../lib/api_tree.php');
include_once('../lib/import.php');
include_once('../lib/utility.php');
include_once('./functions.php');

set_default_action();

prime_default_settings();

if (isset_request_var('theme')) {
	$theme = get_validated_theme(get_nfilter_request_var('theme'), read_config_option('selected_theme', true));
	$_SESSION['install_theme'] = $theme;
	set_user_setting('selected_theme', $theme);
	set_config_option('install_theme', $theme);
} elseif (isset($_SESSION['install_theme'])) {
	$theme = $_SESSION['install_theme'];
} else {
	$theme = 'modern';
}

if (isset_request_var('language')) {
	$language = get_validated_language(get_nfilter_request_var('language'), read_config_option('user_language', true));
	$_SESSION['install_language'] = $language;
	set_user_setting('user_language', $language);
	set_config_option('install_language', $language);
} elseif (isset($_SESSION['install_language'])) {
	$language = $_SESSION['install_language'];
} else {
	$language = read_user_setting('user_language', get_new_user_default_language(), true);
}

// database test
if (get_nfilter_request_var('action') == 'testdb') {
	if (get_nfilter_request_var('location') == 'local') {
		install_test_local_database_connection();
	} else {
		install_test_remote_database_connection();
	}

	exit;
}

include_once('../lib/installer.php');
?>
<!DOCTYPE html>
<html>
<head>
	<?php print html_common_header(__('Cacti Server v%s - Install/Version Change', CACTI_VERSION), $theme);?>
	<?php print get_md5_include_js('install/install.js'); ?>
	<?php print get_md5_include_css('install/install.css'); ?>
	<?php print get_md5_include_css('include/vendor/flag-icon-css/css/flag-icon.css'); ?>
</head>
<body>
	<div class='cactiInstallTable'>
		<div class='cactiTableTitleRow cactiBorderWall'>
			<div class='textHeaderDark'><?php print __('Cacti Server v%s - Installation Wizard', CACTI_VERSION); ?></div>
		</div>
		<div class='cactiInstallArea cactiBorderWall'>
			<div class='cactiInstallAreaContent' id='installContent'>
<?php
				print Installer::sectionTitle(__('Initializing'));
				print Installer::sectionNormal(__('Please wait while the installation system for Cacti Version %s initializes.  You must have JavaScript enabled for this to work.', CACTI_VERSION));
?>
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
