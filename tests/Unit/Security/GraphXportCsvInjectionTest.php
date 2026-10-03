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
 * Tests for the CSV/DDE formula-injection and header-injection hardening in
 * graph_xport.php (GHSA-hh7r-464j-rf39).
 *
 * The CSV export writes the graph title, vertical label and every column
 * legend into spreadsheet cells. All three are attacker-influenced (titles
 * and labels are user-entered; legends are frequently SNMP-sourced
 * ifAlias/ifDescr), so a cell beginning with =, +, -, @, tab or carriage
 * return would be evaluated as a formula when the file is opened. The fix
 * routes every such cell through graph_xport_csv_cell(). The suggested
 * Content-Disposition filename is also derived from the title and must not
 * carry CR/LF/quote/backslash that could break out of the quoted header.
 */

$xportSource = file_get_contents(__DIR__ . '/../../../graph_xport.php');

if ($xportSource === false) {
	throw new RuntimeException('Unable to read graph_xport.php');
}

test('every attacker-influenced CSV cell is routed through the formula guard', function () use ($xportSource) {
	expect($xportSource)->toContain('graph_xport_csv_cell($xport_array[\'meta\'][\'title_cache\'])');
	expect($xportSource)->toContain('graph_xport_csv_cell($xport_array[\'meta\'][\'vertical_label\'])');
	// the column legends are the cells flagged by review as still unguarded.
	expect($xportSource)->toContain('graph_xport_csv_cell($legend)');
});

test('the raw, unguarded legend concatenation is gone', function () use ($xportSource) {
	expect($xportSource)->not->toContain("str_replace(array(\"\\r\", \"\\n\", '\"'), array(' ', ' ', '\"\"'), \$legend)");
});

test('the Content-Disposition filename strips header-injection characters', function () use ($xportSource) {
	expect($xportSource)->toContain("str_replace(array(\"\\r\", \"\\n\", '\"', '\\\\'), ''");
});

/*
 * Behavioral proof that the guard neutralizes formula triggers and preserves
 * benign values. The helper is pulled directly out of this repo's own
 * graph_xport.php and eval()'d into scope (Test-only; never external/user
 * input), following the extract-and-eval pattern used elsewhere in the suite,
 * because the 1.2.x unit bootstrap does not load the page scripts.
 */
if (!function_exists('graph_xport_csv_cell')) {
	preg_match('/\nfunction graph_xport_csv_cell\b.*?\n\}/s', "\n" . $xportSource, $cellMatch);

	if (!empty($cellMatch)) {
		eval($cellMatch[0]);
	}
}

test('a leading formula trigger is neutralized with a single-quote prefix', function () {
	expect(function_exists('graph_xport_csv_cell'))->toBeTrue();

	foreach (array('=cmd', '+1+2', '-1+1', '@SUM(A1)', "\tTAB", "\rCR") as $payload) {
		expect(graph_xport_csv_cell($payload))->toBe("'" . $payload);
	}
});

test('embedded double quotes are RFC 4180 doubled and benign text is unchanged', function () {
	expect(graph_xport_csv_cell('a"b"c'))->toBe('a""b""c');
	expect(graph_xport_csv_cell('eth0 traffic'))->toBe('eth0 traffic');
	expect(graph_xport_csv_cell(''))->toBe('');
});
