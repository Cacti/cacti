<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('GHSA-g9c7-23p2-6hh3: cacti_exec rejects binary strings that begin with dash', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_exec(');
	expect($functionsSource)->toContain("if (\$binary[0] === '-')");
	expect($functionsSource)->toContain('binary must not begin with "-"');
});

test('GHSA-g9c7-23p2-6hh3: cacti_exec still rejects whitespace-mixed command strings', function () use ($functionsSource) {
	expect($functionsSource)->toContain("preg_match('/\\s/', \$binary)");
});
