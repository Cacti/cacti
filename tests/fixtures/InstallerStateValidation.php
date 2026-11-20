<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require dirname(__DIR__, 2) . '/lib/html.php';
require dirname(__DIR__, 2) . '/lib/installer.php';

$config = ['base_path' => dirname(__DIR__, 2), 'cacti_server_os' => 'unix'];
function __($text, ...$args) { return $args ? vsprintf($text, $args) : $text; }

function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

// A wizard step left behind by an upgrade finished outside the browser.
check(Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, false, 'Web'), 'A finished upgrade kept the browser wizard open.');
check(Installer::isStaleWizardStep(Installer::STEP_PROFILE_AND_AUTOMATION, false, 'Json'), 'A finished upgrade kept the JSON wizard open.');
check(!Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, true, 'Web'), 'A pending upgrade lost its wizard.');
check(!Installer::isStaleWizardStep(Installer::STEP_INSTALL, false, 'Web'), 'The background install state was retired.');
check(!Installer::isStaleWizardStep(Installer::STEP_COMPLETE, false, 'Web'), 'The complete state was retired.');
check(!Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, false, 'Cli'), 'The command line installer was restricted.');

// Theme names must be a single installed directory.
check(Installer::isInstalledThemeName('modern'), 'An installed theme was rejected.');

foreach (['../themes/modern', 'modern/../modern', './modern', '', 'not-a-shipped-theme'] as $theme) {
	check(!Installer::isInstalledThemeName($theme), 'Theme name was accepted: ' . $theme);
}

// Browser requests always run with the browser runtime.
$params = Installer::browserParameters(['Runtime' => 'Cli', 'Step' => 3]);
check($params['Runtime'] === 'Web' && $params['Step'] === 3, 'A browser request selected another runtime.');
check(Installer::browserParameters([])['Runtime'] === 'Web', 'An empty browser request lost its runtime.');

// The missing-schema guidance must not render the configured password.
$database_username = 'cactiuser';
$database_default  = 'cacti';
$database_password = 'Fixture-Db-Secret-7f3a';

$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
$output    = (new ReflectionMethod(Installer::class, 'exitSqlNeeded'))->invoke($installer);

check(strpos($output, 'Fixture-Db-Secret-7f3a') === false, 'The database password was rendered.');
check(strpos($output, 'GRANT SELECT ON mysql.time_zone_name') !== false, 'The time zone grant guidance is missing.');

print json_encode(['stale_cases' => 6, 'theme_cases' => 6, 'runtime' => $params['Runtime'], 'password_rendered' => false]);
