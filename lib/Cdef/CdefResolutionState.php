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

namespace Cacti\Cdef;

/** Mutable state for one resolution, also shared with legacy reference parameters. */
final class CdefResolutionState {
	/** @var array<int, true> CDEF ids on the active reference chain. */
	public array $visited = [];

	/** CDEF expansions performed so far. */
	public int $expansion = 0;

	/** @var array<int, string> Resolved CDEFs keyed by id. */
	public array $cache = [];

	/** Bytes held in $cache, bounded by the resolver's cache budget. */
	public int $cache_bytes = 0;
}
