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

/**
 * The captured output and exit status of one RRDtool process.
 */
final class RrdProcessResult {
	/**
	 * @param string $stdout   the process standard output
	 * @param string $stderr   the process standard error
	 * @param int    $exitCode the process exit status
	 */
	public function __construct(
		public readonly string $stdout,
		public readonly string $stderr,
		public readonly int $exitCode,
	) {
	}

	/**
	 * Whether RRDtool exited successfully.
	 *
	 * @return bool true for exit status 0
	 */
	public function succeeded(): bool {
		return $this->exitCode === 0;
	}

	/**
	 * Standard output followed by standard error, as the procedural callers read it.
	 *
	 * @return string the combined output
	 */
	public function outputWithErrors(): string {
		return $this->stdout . $this->stderr;
	}
}
