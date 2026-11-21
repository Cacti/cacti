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
 * A graph item's text alignment is appended to the RRDtool command as it is.
 * The item editor only stores an alignment keyword, but a template import
 * writes the column directly, so the value is checked again where the command
 * is built and anything that is not a keyword leaves the TEXTALIGN out.
 */

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

test('the alignments the graph item editor offers are kept', function ($textalign) {
	expect(rrd_graph_item_textalign($textalign))->toBe($textalign);
})->with(array(RRD_ALIGN_LEFT, RRD_ALIGN_RIGHT, RRD_ALIGN_JUSTIFIED, RRD_ALIGN_CENTER));

test('surrounding white space is not part of the alignment', function () {
	expect(rrd_graph_item_textalign(" center\n"))->toBe('center');
});

test('anything else is not an alignment', function ($textalign) {
	expect(rrd_graph_item_textalign($textalign))->toBe('');
})->with(array(
	'none'            => array(RRD_ALIGN_NONE),
	'null'            => array(null),
	'upper case'      => array('LEFT'),
	'unknown keyword' => array('middle'),
	'two arguments'   => array('left --title x'),
	'command text'    => array('left; id'),
	'substitution'    => array('$(id)'),
	'quoted'          => array("'left'"),
	'line break'      => array("left\nright"),
	'array'           => array(array('left')),
));

test('the graph command uses the checked alignment', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');
	$start  = strpos($source, 'case GRAPH_ITEM_TYPE_TEXTALIGN:');
	$case   = substr($source, $start, strpos($source, 'break;', $start) - $start);

	expect($case)->toContain("\$textalign = rrd_graph_item_textalign(\$graph_item['textalign']);")
		->and($case)->toContain("\$graph_item_types[\$graph_item['graph_type_id']] . ':' . \$textalign;")
		->and($case)->not->toContain("':' . \$graph_item['textalign']");
});
