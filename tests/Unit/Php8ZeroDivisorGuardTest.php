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
 * Two divisors reached zero from ordinary data: a colour template with no
 * items, and a database account that cannot read max_connections. PHP 7 warned
 * and carried on. PHP 8 raises DivisionByZeroError, ending the request.
 *
 * PHPStan stays quiet about both here, because this branch's type hints let it
 * narrow the operands. Silence from the analyser is not the same as the value
 * being impossible at runtime, which is why these are pinned by hand.
 */

$base = dirname(__DIR__, 2);

test('the sources under test are readable', function () use ($base) {
	foreach (['/lib/api_aggregate.php', '/lib/utility.php'] as $rel) {
		expect(file_get_contents($base . $rel))->toBeString()->not->toBeEmpty();
	}
});

test('PHP 8 makes both division and modulo by zero fatal', function () {
	expect(fn () => 5 % 0)->toThrow(DivisionByZeroError::class)
		->and(fn () => 5 / 0)->toThrow(DivisionByZeroError::class);
});

test('the aggregate colour round robin checks the template has colours', function () use ($base) {
	$src = file_get_contents($base . '/lib/api_aggregate.php');

	// the id is bound now, as the count above it already was
	expect($src)->not->toContain("WHERE color_template_id=' . \$_color_templates[\$i] . '")
		->and($src)->toContain('WHERE color_template_id = ?');

	$guard = strpos($src, 'if ($num_colors > 0) {');
	$use   = strpos($src, '$offset = $_selected_graph_index % $num_colors;');

	expect($guard)->not->toBeFalse()
		->and($use)->not->toBeFalse()
		->and($guard)->toBeLessThan($use);
});

test('the connection recommendation checks max_connections came back', function () use ($base) {
	$src = file_get_contents($base . '/lib/utility.php');

	expect($src)->not->toContain('$recommendation = $remainingMem / $maxConnections;')
		->and($src)->toContain('$maxConnections > 0 ? $remainingMem / $maxConnections : 0');
});
