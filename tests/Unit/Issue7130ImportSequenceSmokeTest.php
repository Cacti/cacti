<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke guard for issue #7130. Imported input strings are decoded into
 * $save['input_string'] before persistence and validation. Sequence
 * regeneration must use that decoded value; using the raw XML value can
 * miss placeholders from exported/base64 input strings.
 */

$importSource = file_get_contents(__DIR__ . '/../../lib/import.php');

test('data input import regenerates field sequences from decoded input string', function () use ($importSource) {
	expect($importSource)->toContain("generate_data_input_field_sequences(\$save['input_string'], \$data_input_id);");
	expect($importSource)->not->toContain("generate_data_input_field_sequences(\$xml_array['input_string'], \$data_input_id);");
});
