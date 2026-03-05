<?php
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

/*
 * Shared stubs for Cacti helper functions used by production code.
 * These replicate the production signatures so that test stubs
 * can call them without loading the full Cacti environment.
 */

function cacti_sizeof($value): int {
	if (is_array($value)) {
		return sizeof($value);
	} elseif ($value instanceof Countable) {
		return count($value);
	}

	return 0;
}

function cacti_count($value): int {
	if (is_array($value)) {
		return count($value);
	} elseif ($value instanceof Countable) {
		return count($value);
	}

	return 0;
}

function __($text) {
	return $text;
}

function __esc($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
