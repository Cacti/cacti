<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$dataInputSource = file_get_contents(__DIR__ . '/../../../../data_input.php');

test('host placeholders require a special type code', function () use ($dataInputSource) {
	expect($dataInputSource)->toContain("\$save['type_code'] == ''");
	expect($dataInputSource)->toContain("preg_match('/^(?:' . VALID_HOST_FIELDS . ')$/i', \$save['data_name']) === 1");
	expect($dataInputSource)->toContain("\$_SESSION[SESS_ERROR_FIELDS]['type_code'] = 'type_code'");
});

test('data input field save validates missing special type codes', function () use ($dataInputSource) {
	expect($dataInputSource)->toContain('requires Special Type Code');
});
