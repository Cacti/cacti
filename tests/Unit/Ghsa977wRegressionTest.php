<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$graphXportSource = file_get_contents(__DIR__ . '/../../graph_xport.php');

test('GHSA-977w-79m7-xjc4: HTML preview escapes the graph title', function () use ($graphXportSource) {
	// title_cache is attacker-controllable via the graph template and
	// was echoed raw into the preview table on the vulnerable branch.
	expect($graphXportSource)->toContain("html_escape(\$xport_array['meta']['title_cache'])");
});

test('GHSA-977w-79m7-xjc4: HTML preview escapes each legend column', function () use ($graphXportSource) {
	// Per-column legends are joined into <th> cells; they need the same
	// escaping path as the title to stop reflected XSS.
	expect($graphXportSource)->toContain("html_escape(\$xport_array['meta']['legend']['col' . \$i])");
});

test('GHSA-977w-79m7-xjc4: CSV legend export applies RFC 4180 quote doubling', function () use ($graphXportSource) {
	// CR/LF get flattened to spaces and embedded double-quotes are
	// doubled so the CSV cell cannot be terminated early.
	expect($graphXportSource)->toContain('str_replace(array("\r", "\n", \'"\'), array(\' \', \' \', \'""\'), $legend)');
});

test('GHSA-977w-79m7-xjc4: CSV cells are wrapped in quotes after sanitisation', function () use ($graphXportSource) {
	// The defensive escape only works if the final field is actually
	// emitted as a quoted CSV cell.
	expect($graphXportSource)->toContain('$header .= \',"\' . str_replace(array("\r", "\n", \'"\'), array(\' \', \' \', \'""\'), $legend) . \'"\';');
});
