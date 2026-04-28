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

// Legacy entrypoint. Operators still invoke
//   php cli/splice_rrd.php --oldrrd=A --newrrd=B [--finrrd=C] [-d] [-D] ...
// so this file stays as a thin wrapper that defers to SpliceRrdCommand.

/* Load the autoloader before cli_check.php so Symfony Console classes
 * resolve even when --help is invoked from an environment without a
 * fully bootstrapped Cacti config (test harness, install). The full
 * Cacti bootstrap is loaded in CactiCommand::initialize() and only
 * fires when the Command actually executes work. */
require_once(__DIR__ . '/../include/vendor/autoload.php');
require_once(__DIR__ . '/../lib/CactiApplication.php');
require_once(__DIR__ . '/../lib/CactiCommand.php');
require_once(__DIR__ . '/../lib/SpliceRrdCommand.php');

$app = new CactiApplication();
$cmd = new SpliceRrdCommand();
$app->add($cmd);
$app->setDefaultCommand((string) $cmd->getName(), true);

exit($app->run());
