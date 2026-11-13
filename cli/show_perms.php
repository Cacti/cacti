#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

use Cacti\Console\Command\ShowPermissionsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

require(__DIR__ . '/../include/cli_check.php');

/* Thin compatibility shim. The behaviour lives in the native
 * Cacti\Console\Command\ShowPermissionsCommand; this entrypoint keeps
 * `php cli/show_perms.php` working for existing cron jobs and documentation.
 * global.php (loaded by cli_check.php) has already registered the composer
 * autoloader, so the Console classes resolve here. */
$command = new ShowPermissionsCommand();

$application = new Application('Cacti Database Permission Utility', get_cacti_cli_version());
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), true);

exit($application->run(new ArgvInput()));
