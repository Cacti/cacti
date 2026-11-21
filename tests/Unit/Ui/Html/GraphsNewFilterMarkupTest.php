<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The New Graphs filter is drawn with presentation tables. Its option tags
 * keep each PHP block whole inside the tag, so a markup parser reads the tag
 * to its end and sees the filter tables closed before the data query table,
 * whose header cells are real column headers.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/graphs_new.php');

test('the graph type options keep their PHP blocks whole inside the start tag', function () use ($src) {
	$start  = strpos($src, "<form id='graphs_new'");
	$filter = substr($src, $start, strpos($src, '</form>', $start) - $start);

	expect($filter)->toContain("<option value='-2'<?php if (get_request_var('graph_type') == '-2') { print ' selected'; }?>><?php print __('All');?></option>")
		->and($filter)->toContain("<option value='-1'<?php if (get_request_var('graph_type') == '-1') { print ' selected'; }?>><?php print __('Graph Template Based');?></option>")
		->and($filter)->not->toContain('{?>')
		->and(substr_count($filter, "<table role='presentation'"))->toBe(3)
		->and(substr_count($filter, '</table>'))->toBe(3);
});

test('the data query table keeps its escaped header cells', function () use ($src) {
	expect($src)->toContain("\$html_dq_header .= \"<th class='tableSubHeaderColumn'>\" . html_escape(\$field_array['name']) . '</th>';");
});
