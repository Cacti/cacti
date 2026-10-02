<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-9x9h-2577-9w86: the custom.dropcolor autocomplete in include/layout.js
 * read colour names back out of the DOM and concatenated them into HTML
 * strings, undoing the server-side escaping. It now builds DOM nodes and
 * inserts the label as text.
 */

$root = dirname(__DIR__, 4);

test('the colordropdown builds nodes and inserts the label as text', function () use ($root) {
	$source = file_get_contents($root . '/include/layout.js');

	expect($source)->toContain('document.createTextNode(item.label)')
		// no HTML-string construction of the list item from the label
		->and($source)->not->toContain(".html('<div><span style=\"background-color:#'+color+';\"")
		// no parseHTML round-trip of the label
		->and($source)->not->toContain('var mylabel = $($.parseHTML(item.label));')
		// the input is built as a node, not from a value="'+value+'" string
		->and($source)->not->toContain('ui-selectmenu-text" style="background:transparent;border:0px;padding:0px;padding-left:24px;margin-left:-24px" value="\'+value+\'">');
});
