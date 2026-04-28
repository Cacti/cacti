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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared base for every Cacti console Command. Handles the include/global.php
 * bootstrap so individual Command classes can assume cacti_log(),
 * read_config_option(), and the db_*() helpers are available, and centralizes
 * argv validation that several legacy scripts had to reinvent.
 */
abstract class CactiCommand extends Command {
	/**
	 * Pull Cacti's global bootstrap in lazily so unit tests that build a
	 * Command in isolation are not forced to stand up a database. Skipping
	 * the include when PHP_TESTING is set keeps the existing Helpers/CactiStubs
	 * pattern working.
	 */
	protected function initialize(InputInterface $input, OutputInterface $output) : void {
		// In-process Pest tests define PHP_TESTING via the test bootstrap.
		// Spawned subprocesses (CliInvocationTest spawns the real shims via
		// proc_open) cannot inherit a PHP-level constant, so they signal the
		// same intent via CACTI_PHP_TESTING=1 in the environment instead.
		if (defined('PHP_TESTING') || getenv('CACTI_PHP_TESTING')) {
			return;
		}

		if (!defined('CACTI_PATH_BASE')) {
			require_once dirname(__DIR__) . '/include/cli_check.php';
		}
	}

	/**
	 * Validate an int-typed argument or option. Throws InvalidArgumentException
	 * with a message the Application surfaces directly to the operator. Used
	 * by every Command that accepts numeric IDs.
	 *
	 * @param InputInterface $input  The current Symfony input
	 * @param string         $name   Argument or option name
	 * @param int|null       $min    Inclusive lower bound, or null for none
	 * @param int|null       $max    Inclusive upper bound, or null for none
	 * @param bool           $option True to read from getOption(), false for getArgument()
	 *
	 * @return int The validated integer value
	 */
	protected function validateInt(InputInterface $input, string $name, ?int $min = null, ?int $max = null, bool $option = false) : int {
		$raw = $option ? $input->getOption($name) : $input->getArgument($name);

		if ($raw === null || $raw === '') {
			throw new \InvalidArgumentException(sprintf('Missing required %s "%s".', $option ? 'option' : 'argument', $name));
		}

		// filter_var rejects '12abc' and '12.5' that (int) would silently truncate.
		$value = filter_var($raw, FILTER_VALIDATE_INT);

		if ($value === false) {
			throw new \InvalidArgumentException(sprintf('Value for "%s" must be an integer, got %s.', $name, var_export($raw, true)));
		}

		if ($min !== null && $value < $min) {
			throw new \InvalidArgumentException(sprintf('Value for "%s" must be >= %d, got %d.', $name, $min, $value));
		}

		if ($max !== null && $value > $max) {
			throw new \InvalidArgumentException(sprintf('Value for "%s" must be <= %d, got %d.', $name, $max, $value));
		}

		return $value;
	}
}
