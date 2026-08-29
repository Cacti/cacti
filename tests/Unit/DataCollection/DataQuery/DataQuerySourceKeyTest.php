<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for #7809.
 *
 * query_snmp_host() iterates every data query field, including output-only
 * fields that carry no 'source' key. The pre-processing guard read
 * $field_array['source'] as its left-most term, so PHP raised
 * "Undefined array key source" for every output field before the direction
 * test could short-circuit. Testing the direction first reads 'source' only
 * for input fields, which always define it.
 */

function _data_query_source_7809() {
	$path = dirname(__DIR__, 4) . '/lib/data_query.php';
	$src  = file_get_contents($path);

	expect($src)->not->toBeFalse('Failed to read lib/data_query.php');

	return $src;
}

test('#7809: the method-fix guard tests direction before reading source', function () {
	$src = _data_query_source_7809();

	$needle = 'if ((' . '$field_array' . "['direction'] == 'input' || " . '$field_array' . "['direction'] == 'input-output') && " . '$field_array' . "['source'] != 'index'";
	$start  = strpos($src, $needle);

	expect($start)->not->toBeFalse(
		"query_snmp_host() must test direction before reading \$field_array['source'] so output-only fields short-circuit"
	);

	// The old ordering (source first) must be gone.
	$oldNeedle = 'if (' . '$field_array' . "['source'] != 'index' && (" . '$field_array' . "['direction'] == 'input'";
	$old       = strpos($src, $oldNeedle);
	expect($old)->toBeFalse('the source-first ordering must not remain');
});

test('#7809: an output-only field does not read a missing source key', function () {
	// Mirrors the real guard. An output field carries direction=output and no source.
	$field_array = array(
		'direction' => 'output',
		'method'    => 'walk',
	);

	$warned = false;
	set_error_handler(function ($errno, $errstr) use (&$warned) {
		if (stripos($errstr, 'source') !== false) {
			$warned = true;
		}
		return true;
	});

	$hit =
		($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') &&
		$field_array['source'] != 'index' &&
		$field_array['method'] != 'get' &&
		(isset($field_array['rewrite_index']) || isset($field_array['oid_suffix']));

	restore_error_handler();

	expect($warned)->toBeFalse('output-only field must not trigger an undefined source-key warning');
	expect($hit)->toBeFalse('output-only field must not enter the input method-fix branch');
});

test('#7809: an input field still enters the branch when it should', function () {
	$field_array = array(
		'direction'     => 'input',
		'source'        => 'value',
		'method'        => 'walk',
		'rewrite_index' => '.1',
	);

	$hit =
		($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') &&
		$field_array['source'] != 'index' &&
		$field_array['method'] != 'get' &&
		(isset($field_array['rewrite_index']) || isset($field_array['oid_suffix']));

	expect($hit)->toBeTrue('input field with rewrite_index must still be corrected to method=get');
});
