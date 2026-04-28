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

use Symfony\Component\Console\Application;

/**
 * Single Symfony Console application for every Cacti CLI tool. The legacy
 * scripts each rolled their own argv parser with subtle differences; routing
 * them through one Application removes that drift and gives us one place to
 * add input validation, telemetry, and consistent --help output.
 */
class CactiApplication extends Application {
	public function __construct(?string $version = null) {
		parent::__construct('Cacti CLI', $version ?? self::resolveVersion());
	}

	/**
	 * Build a fully wired Application with every known Command registered.
	 * Used by cli/cacti.php and by the legacy shims that need a one-Command
	 * Application as their default.
	 */
	public static function bootstrap() : self {
		$app = new self();

		require_once __DIR__ . '/CactiCommand.php';
		require_once __DIR__ . '/CmdRealtimeCommand.php';
		require_once __DIR__ . '/SpliceRrdCommand.php';

		$app->add(new CmdRealtimeCommand());
		$app->add(new SpliceRrdCommand());

		return $app;
	}

	/**
	 * Read the version from include/cacti_version, the same source global.php
	 * uses. Fall back to 'unknown' when the file is missing so the framework
	 * does not throw before the user sees a real error.
	 */
	private static function resolveVersion() : string {
		$file = __DIR__ . '/../include/cacti_version';

		if (is_file($file)) {
			$contents = @file_get_contents($file);

			if (is_string($contents) && trim($contents) !== '') {
				return trim($contents);
			}
		}

		return 'unknown';
	}
}
