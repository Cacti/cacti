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

/**
 * Graph item field contract.
 *
 * Graph item options are appended into RRDtool's colon delimited argument
 * strings rather than passed as their own argv entries, so they cannot be
 * escaped after the fact with cacti_escapeshellarg().  The stored value has to
 * be the right shape in the first place, and these validators are applied where
 * the command line is built so a row already in the database cannot reach a
 * shell.
 */

/**
 * rrd_graph_item_alpha - validates a graph item alpha channel
 *
 * Alpha is appended straight onto a #RRGGBB colour, so it has to be a pair of
 * hex digits.  Anything else is dropped and RRDtool renders the colour opaque.
 *
 * @param mixed $alpha The stored alpha channel
 *
 * @return string The hex pair, or an empty string when unusable
 */
function rrd_graph_item_alpha(mixed $alpha) : string {
	$alpha = trim((string) $alpha);

	return (strlen($alpha) == 2 && ctype_xdigit($alpha)) ? $alpha : '';
}

/**
 * rrd_graph_item_number_list - validates a comma separated numeric option
 *
 * RRDtool's dashes= option takes one or more comma separated lengths.
 *
 * @param mixed $value The stored option value
 *
 * @return string The validated list, or an empty string when unusable
 */
function rrd_graph_item_number_list(mixed $value) : string {
	$value = trim((string) $value);

	if ($value == '') {
		return '';
	}

	foreach (explode(',', $value) as $part) {
		if (!is_numeric($part)) {
			return '';
		}
	}

	return $value;
}

/**
 * rrd_graph_item_textalign - validates a graph item text alignment
 *
 * @param mixed $align The stored alignment
 *
 * @return string One of RRDtool's alignment keywords, or an empty string
 */
function rrd_graph_item_textalign(mixed $align) : string {
	$align = trim((string) $align);

	return in_array($align, ['left', 'right', 'center', 'justify'], true) ? $align : '';
}
