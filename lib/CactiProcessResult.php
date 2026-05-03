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

class CactiProcessResult {
	private int $exitCode;
	private string $stdout;
	private string $stderr;

	public function __construct(int $exitCode, string $stdout, string $stderr) {
		$this->exitCode = $exitCode;
		$this->stdout   = $stdout;
		$this->stderr   = $stderr;
	}

	public function exitCode(): int {
		return $this->exitCode;
	}

	public function stdout(): string {
		return $this->stdout;
	}

	public function stderr(): string {
		return $this->stderr;
	}

	/*
	 * Mimic exec()'s $output behaviour: split on newline and drop a trailing
	 * empty element introduced by the final \n. Callers migrating away from
	 * exec($cmd, $output) can swap to ::run([...])->outputLines() without
	 * changing downstream loops.
	 */
	public function outputLines(): array {
		if ($this->stdout === '') {
			return [];
		}

		$lines = explode("\n", $this->stdout);

		if (end($lines) === '') {
			array_pop($lines);
		}

		return $lines;
	}
}
