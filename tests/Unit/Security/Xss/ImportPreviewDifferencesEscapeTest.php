<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$importSource = file_get_contents(__DIR__ . '/../../../../lib/import.php');

test('the import preview differences list escapes the imported template/data-query name', function () use ($importSource) {
	/* the differences strings are rendered raw by form_selectable_cell in
	 * templates_import.php, so the imported name must be escaped where the
	 * difference line is composed */
	expect($importSource)->toContain("__('New Graph Template: %s', html_escape(\$dname))")
		->and($importSource)->toContain("__('New Data Query: %s', html_escape(\$dname))")
		->and($importSource)->not->toContain("__('New Graph Template: %s', \$dname)")
		->and($importSource)->not->toContain("__('New Data Query: %s', \$dname)");
});

test('the import preview orphan list escapes the orphaned graph-item text', function () use ($importSource) {
	expect($importSource)->toContain("html_escape('Found Orphaned Graph Items, Type: '");
});
