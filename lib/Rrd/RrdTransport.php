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
 * Runs a structured RRDtool command.
 */
interface RrdTransport {
	/**
	 * Execute a command and capture its result.
	 *
	 * @param RrdCommand $command the command to run
	 *
	 * @throws RrdTransportException when the command cannot be started
	 *
	 * @return RrdProcessResult the captured output and exit status
	 */
	public function execute(RrdCommand $command): RrdProcessResult;
}
