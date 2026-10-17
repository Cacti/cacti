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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Runs installer subprocesses without a shell, with bounded time and output. */
final readonly class CommandRunner {
	public function __construct(private float $timeout = 86400, private int $outputLimit = 65536) {
		if ($timeout <= 0 || $outputLimit < 1) {
			throw new \InvalidArgumentException('Installer command limits must be positive');
		}
	}

	/** @param list<string> $command
	 * @return array{exitCode: int, output: list<string>}
	 */
	public function run(array $command) : array {
		$output = '';

		try {
			$process = new Process($command, timeout: $this->timeout);
			// proc_open throws ValueError for an empty command; Symfony does not document it.
			/** @throws \ValueError|ExceptionInterface */
			$exitCode = $process->run(function (string $type, string $buffer) use (&$output, $process) : void {
				$output = substr($output . $buffer, -$this->outputLimit);
				$process->clearOutput();
				$process->clearErrorOutput();
			});
		} catch (ProcessTimedOutException) {
			$exitCode = 124;
			$output .= "\nInstaller command exceeded its time limit.";
		} catch (ExceptionInterface|\ValueError) {
			$exitCode = 127;
			$output .= "\nUnable to start the installer command.";
		}

		return [
			'exitCode' => $exitCode,
			'output'   => array_values(array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", trim($output))),
				static fn (string $line) : bool => $line !== '')),
		];
	}
}
