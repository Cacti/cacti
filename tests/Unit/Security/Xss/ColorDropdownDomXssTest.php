<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-9x9h-2577-9w86: the custom.dropcolor autocomplete built its input from
 * an HTML string with a raw value and round-tripped the label through
 * parseHTML. It now builds the input as a node and uses the label as text.
 */

$root = dirname(__DIR__, 4);

test('the colordropdown input is a node and the label is not re-parsed as HTML', function () use ($root) {
	$source = file_get_contents($root . '/include/layout.js');

	// input built as a node, not from a value="' + value + '" HTML string
	expect($source)->toContain('value: value')
		->and($source)->not->toContain('ui-selectmenu-text" style="background:transparent;border:0px;padding:0px;padding-left:24px;margin-left:-24px" value="\' + value + \'">')
		// no parseHTML round-trip of the label
		->and($source)->not->toContain('var mylabel = $($.parseHTML(item.label));')
		// the label still reaches the DOM as text
		->and($source)->toContain('document.createTextNode(label)');
});
