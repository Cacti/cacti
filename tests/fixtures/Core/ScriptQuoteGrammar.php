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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';

// Check strings only; none of these templates is executed by a shell.
$cases = [
	['<field>', ['field' => "'sample'"], "'sample'"],
	['"<field>"', ['field' => "'sample'"], "'sample'"],
	["'<field>'", ['field' => "'sample'"], "'sample'"],
	['$<path>', ['path' => '/opt/cacti'], '$/opt/cacti'],
	['<prefix>"<field>"', ['prefix' => '$', 'field' => "'sample'"], ''],
];

foreach (["'", '"'] as $quote) {
	for ($slashes = 0; $slashes <= 3; $slashes++) {
		$prefix   = str_repeat('\\', $slashes) . '$';
		$value    = $quote . 'sample' . $quote;
		$expected = $slashes % 2 === 0 ? '' : $prefix . $value;
		$cases[]  = [$prefix . '<field>', ['field' => $value], $expected];
		$cases[]  = [$prefix . $quote . '<field>' . $quote, ['field' => $value], $expected];

		// Backslashes between the $ and the token: the odd-run shed drops one,
		// so a single backslash must not leave the $ glued to the value.
		foreach ([1, 3] as $inner) {
			$kept     = str_repeat('\\', $inner - 1);
			$refused  = $kept === '' && $slashes % 2 === 0;
			$cases[]  = [$prefix . str_repeat('\\', $inner) . '<field>', ['field' => $value], $refused ? '' : $prefix . $kept . $value];
		}
	}
}

foreach ($cases as $index => [$template, $values, $expected]) {
	if (substitute_script_path($template, $values) !== $expected) {
		throw new RuntimeException('Quote grammar case failed: ' . $index);
	}
}

print json_encode(['passed' => count($cases)], JSON_THROW_ON_ERROR) . "\n";
