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
*/

/*
 * query_snmp_host() normalizes the device's bulk walk size into $walk_size and
 * hands that to cacti_snmp_session() for the index walk. The output_format
 * branch instead passed $host['max_oids'] in the slot cacti_snmp_walk() reads
 * as $bulk_walk_size, so snmpbulkwalk ran with -Cr taken from max OIDs per get.
 * Devices that reject large bulk walks returned nothing for those fields.
 *
 * The call needs a live SNMP session to reach, so this checks the wiring rather
 * than the traffic: it reads the argument position out of cacti_snmp_walk()'s
 * own declaration, so it keeps holding if that signature is ever reordered.
 */

/**
 * Reads the text between one pair of balanced parentheses.
 *
 * @param string $source The PHP source to read from.
 * @param int    $open   The offset of the opening parenthesis.
 *
 * @return string The contents, or an empty string when the pair never closes.
 */
function walk_size_balanced(string $source, int $open) : string {
	$depth = 0;

	for ($pos = $open; $pos < strlen($source); $pos++) {
		if ($source[$pos] === '(') {
			$depth++;
		} elseif ($source[$pos] === ')') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $open + 1, $pos - $open - 1);
			}
		}
	}

	return '';
}

/**
 * Split an argument or parameter list on its top-level commas.
 *
 * @return string[]
 */
function walk_size_split(string $list) : array {
	$parts = [''];
	$depth = 0;
	$quote = '';

	for ($pos = 0; $pos < strlen($list); $pos++) {
		$char = $list[$pos];

		if ($quote !== '') {
			$parts[count($parts) - 1] .= $char;

			// $pos is never 0 here (the opening quote consumed that turn), but
			// read defensively rather than let a negative offset wrap around
			if ($char === $quote && ($pos === 0 || $list[$pos - 1] !== '\\')) {
				$quote = '';
			}

			continue;
		}

		if ($char === "'" || $char === '"') {
			$quote = $char;
		} elseif ($char === '(' || $char === '[') {
			$depth++;
		} elseif ($char === ')' || $char === ']') {
			$depth--;
		} elseif ($char === ',' && $depth === 0) {
			$parts[] = '';

			continue;
		}

		$parts[count($parts) - 1] .= $char;
	}

	return array_map('trim', $parts);
}

/**
 * Reads cacti_snmp_walk()'s declared parameter list out of lib/snmp.php.
 *
 * @return string[] One entry per declared parameter, in order.
 */
function walk_size_snmp_parameters() : array {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');
	expect($source)->not->toBeFalse('lib/snmp.php must be readable');

	$declaration = strpos($source, 'function cacti_snmp_walk(');

	// without this the strpos below would start from offset 0 and read some
	// unrelated parenthesis group, so the test would pass on the wrong text
	expect($declaration)->not->toBeFalse('cacti_snmp_walk() must exist in lib/snmp.php');

	$opening = strpos($source, '(', $declaration);
	expect($opening)->not->toBeFalse('cacti_snmp_walk() must have an opening parenthesis');

	return walk_size_split(walk_size_balanced($source, $opening));
}

/**
 * Reads the arguments query_snmp_host() passes to cacti_snmp_walk().
 *
 * @return string[] One entry per argument, in order.
 */
function walk_size_walk_arguments() : array {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');
	expect($source)->not->toBeFalse('lib/data_query.php must be readable');

	$host = strpos($source, 'function query_snmp_host(');

	expect($host)->not->toBeFalse('query_snmp_host() must exist in lib/data_query.php');

	$after = strpos($source, "\nfunction ", $host + 1);
	$body  = substr($source, $host, ($after === false ? strlen($source) : $after) - $host);

	$call = strpos($body, 'cacti_snmp_walk(');

	expect($call)->not->toBeFalse('query_snmp_host() must still walk for output_format fields');

	$opening = strpos($body, '(', $call);
	expect($opening)->not->toBeFalse('cacti_snmp_walk() call must have an opening parenthesis');

	return walk_size_split(walk_size_balanced($body, $opening));
}

test('cacti_snmp_walk still declares a bulk walk size and no max_oids', function () {
	$names = array_map(
		fn ($parameter) => preg_match('/\$(\w+)/', $parameter, $m) ? $m[1] : '',
		walk_size_snmp_parameters()
	);

	// the whole defect was reading this slot as if it were max OIDs per get
	expect($names)->toContain('bulk_walk_size')
		->and($names)->not->toContain('max_oids');
});

test('the output_format walk passes the normalized walk size in the bulk walk size slot', function () {
	$names = array_map(
		fn ($parameter) => preg_match('/\$(\w+)/', $parameter, $m) ? $m[1] : '',
		walk_size_snmp_parameters()
	);

	$slot = array_search('bulk_walk_size', $names, true);
	$args = walk_size_walk_arguments();

	expect($slot)->not->toBeFalse()
		->and($args)->toHaveCount(count($names));

	// pre-fix this slot held $host['max_oids'], which is max OIDs per get
	expect($args[$slot])->toBe('$walk_size');
});

test('the walk size is normalized before the output_format walk reads it', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');

	$host  = strpos($source, 'function query_snmp_host(');
	$after = strpos($source, "\nfunction ", $host + 1);
	$body  = substr($source, $host, ($after === false ? strlen($source) : $after) - $host);

	$normalization = strpos($body, '$walk_size = $host[\'bulk_walk_size\']');

	// anchor on the normalization itself: strpos($body, '$walk_size') would
	// find the auto-tuning branch's "$walk_size = 5" and pass even if the
	// normalization moved below the walk
	expect($normalization)->not->toBeFalse()
		->and($normalization)->toBeLessThan(strpos($body, 'cacti_snmp_walk('));
});

test('max_oids is still passed to the session calls that take it', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');

	// the fix must not have swapped every max_oids in the file
	expect(substr_count($source, "\$host['max_oids']"))->toBeGreaterThan(0);
});
