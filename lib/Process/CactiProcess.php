<?php
declare(strict_types = 1);
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

namespace Cacti\Process;

use Symfony\Component\Process\Process;

class CactiProcess {
	/**
	 * Run a command synchronously.
	 *
	 * @param array      $argv    The command and its arguments as an array.
	 * @param array      $env     Environment variables to set.
	 * @param float|null $timeout Timeout in seconds.
	 *
	 * @return Process The finished process object.
	 */
	public static function run(array $argv, array $env = [], ?float $timeout = 60): Process {
		$process = new Process($argv);

		if (count($env) > 0) {
			$process->setEnv($env);
		}
		$process->setTimeout($timeout);
		$process->run();

		return $process;
	}

	/**
	 * Start a command asynchronously (background).
	 *
	 * @param array $argv The command and its arguments as an array.
	 * @param array $env  Environment variables to set.
	 *
	 * @return Process The started process object.
	 */
	public static function start(array $argv, array $env = []): Process {
		$process = new Process($argv);

		if (count($env) > 0) {
			$process->setEnv($env);
		}
		// Background processes should not have a timeout enforced by the parent
		$process->setTimeout(null);
		$process->start();

		return $process;
	}

	/**
	 * Best-effort tokenizer for command lines that the caller already controls.
	 * Does NOT handle escaped quotes, backslash escapes, or mixed quoting.
	 * NEVER pass attacker-influenced input to this method; use a real argv array.
	 *
	 * @param string $commandLine The command line to split.
	 *
	 * @return array The split arguments.
	 */
	public static function split(string $commandLine): array {
		$argv = [];
		preg_match_all('/(?:"[^"]*"|\'[^\']*\'|[^\s"]+)+/', $commandLine, $matches);

		if (isset($matches[0])) {
			foreach ($matches[0] as $arg) {
				$argv[] = trim($arg, "\"'");
			}
		}

		return $argv;
	}
}
