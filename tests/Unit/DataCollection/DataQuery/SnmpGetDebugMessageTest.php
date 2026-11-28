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
 * The VALUE/REGEXP branch of query_snmp_host() logged its SNMP get with the
 * message 'Executing SNMP get for data @ \'%s\' [value=\'$value\']'. The
 * message is single-quoted, so $value is not interpolated and the second
 * argument has no placeholder: the data query debug log printed the literal
 * text "$value" instead of the value. Both SNMP get debug lines must format
 * the value through a placeholder.
 */

$dataQuerySource = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');

test('every SNMP get debug line formats the value through a placeholder', function () use ($dataQuerySource) {
	preg_match_all("/__esc\\('Executing SNMP get for data @ ((?:[^'\\\\]|\\\\.)*)'/", $dataQuerySource, $matches);

	expect(count($matches[0]))->toBe(2);

	foreach ($matches[1] as $tail) {
		expect($tail)->toBe("\\'%s\\' [value=\\'%s\\']");
	}
});

test('no single-quoted message in lib/data_query.php names a PHP variable', function () use ($dataQuerySource) {
	preg_match_all("/__(?:esc)?\\('((?:[^'\\\\]|\\\\.)*)'/", $dataQuerySource, $matches);

	$withVariables = array_values(array_filter($matches[1], function (string $msgid) : bool {
		return preg_match('/\$[A-Za-z_]/', $msgid) === 1;
	}));

	expect($withVariables)->toBe([]);
});
