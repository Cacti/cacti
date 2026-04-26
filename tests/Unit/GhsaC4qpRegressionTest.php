<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('GHSA-c4qp-j9r9-fq24: test-script path expansion shell-escapes data_input values', function () use ($functionsSource) {
	expect($functionsSource)->toContain("function get_full_test_script_path(");
	expect($functionsSource)->toContain("\$value = cacti_escapeshellarg((string) \$item['value']);");
});

test('GHSA-c4qp-j9r9-fq24: get_full_test_script_path does not wrap raw field values in manual quotes', function () use ($functionsSource) {
	expect($functionsSource)->not->toContain("\$value = \"'\" . \$item['value'] . \"'\";");
});
