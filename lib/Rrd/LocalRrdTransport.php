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

namespace Cacti\Rrd;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Executes a structured RRDtool command without invoking an operating-system
 * shell. Symfony owns process lifecycle and concurrent output collection.
 */
final class LocalRrdTransport implements RrdTransport {
	public function __construct(
		private readonly string $binary,
	) {
	}

	public function execute(RrdCommand $command): RrdProcessResult {
		try {
			$process = new Process($command->toArgv($this->binary));

			// Preserve the previous RRDtool execution contract, which had no timeout.
			$process->setTimeout(null);
			$exitCode = $process->run();
		} catch (Throwable $exception) {
			throw new RrdTransportException(
				'Unable to execute RRDtool: ' . $exception->getMessage(),
				(int) $exception->getCode(),
				$exception
			);
		}

		return new RrdProcessResult(
			$process->getOutput(),
			$process->getErrorOutput(),
			$exitCode
		);
	}
}
