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

namespace Cacti\Domain\Installation;

enum InstallRunState: string {
	case Ready     = 'ready';
	case Running   = 'running';
	case Succeeded = 'succeeded';
	case Failed    = 'failed';
	case Cancelled = 'cancelled';

	public function canTransitionTo(self $next): bool {
		return match ($this) {
			self::Ready   => $next === self::Running || $next === self::Cancelled,
			self::Running => $next === self::Succeeded || $next === self::Failed || $next === self::Cancelled,
			self::Failed  => $next === self::Ready,
			default       => false,
		};
	}

	public function isTerminal(): bool {
		return match ($this) {
			self::Succeeded, self::Cancelled => true,
			default                          => false,
		};
	}
}
