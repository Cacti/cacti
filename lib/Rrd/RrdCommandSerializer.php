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
 * Serializes structured commands for transports that speak RRDtool's line protocol.
 */
final class RrdCommandSerializer {
	/**
	 * Serialize a command for RRDtool's line-oriented stdin/proxy protocol.
	 *
	 * @param RrdCommand               $command        the command to serialize
	 * @param callable(string): string $escapeArgument quotes one argument for the protocol
	 *
	 * @return string the operation followed by the escaped arguments
	 */
	public function forLineProtocol(RrdCommand $command, callable $escapeArgument): string {
		if ($command->arguments === []) {
			return $command->operation;
		}

		return $command->operation . ' ' . implode(' ', array_map(
			$escapeArgument,
			$command->arguments
		));
	}
}
