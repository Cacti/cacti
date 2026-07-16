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
 * Regression tests for two form-wiring drifts in aggregate_graphs.php.
 *
 * 1. The item editor posts save_component_item (hidden field in item_edit),
 *    but the form_save() gate only accepted save_component_graph /
 *    save_component_input, so item-override saves (form_save_aggregate, the
 *    only writer of t_cdef_id / t_graph_type_id) redirected away unsaved.
 *
 * 2. The form_continue_confirmation() 'extra' arrays for drp_action 2
 *    (Migrate to Aggregate Template) and 3 (Combine into Aggregate Graph)
 *    keyed their input as 'title_format'; lib/html_form.php renders the
 *    array key as the input name, while the handlers read
 *    gnrv('aggregate_template_id') and grv('aggregate_name').
 */

function getAggregateGraphsSource(): string {
	$src = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');
	expect($src)->not->toBeFalse('Failed to read aggregate_graphs.php');

	return $src;
}

test('form_save gate accepts save_component_item posts', function () {
	$source  = getAggregateGraphsSource();
	$pattern = "/!isrv\('save_component_graph'\) && !isrv\('save_component_input'\) && !isrv\('save_component_item'\)/";

	expect(preg_match($pattern, $source))->toBe(1,
		'form_save() must let save_component_item posts reach the item-save branch'
	);
});

test('migrate-to-template confirmation posts aggregate_template_id', function () {
	$source = getAggregateGraphsSource();

	// api_aggregate_convert_template() reads gnrv('aggregate_template_id')
	$pattern = "/'extra'\s*=>\s*\[\s*'aggregate_template_id'\s*=>\s*\[\s*'method'\s*=>\s*'drop_array'/";

	expect(preg_match($pattern, $source))->toBe(1,
		'the drp_action 2 extra input must be named aggregate_template_id'
	);
});

test('combine confirmation posts aggregate_name', function () {
	$source = getAggregateGraphsSource();

	// the drp_action 3 handler reads grv('aggregate_name')
	$pattern = "/'extra'\s*=>\s*\[\s*'aggregate_name'\s*=>\s*\[\s*'method'\s*=>\s*'textbox'/";

	expect(preg_match($pattern, $source))->toBe(1,
		'the drp_action 3 extra input must be named aggregate_name'
	);
});

test('no confirmation extra input is misnamed title_format', function () {
	$source  = getAggregateGraphsSource();
	$pattern = "/'extra'\s*=>\s*\[\s*'title_format'/";

	expect(preg_match($pattern, $source))->toBe(0,
		'no bulk-action dialog may post its input as title_format'
	);
});
