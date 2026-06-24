<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$layout = file_get_contents(__DIR__ . '/../../include/layout.js');

if ($layout === false) {
	fwrite(STDERR, "unable to read include/layout.js\n");
	exit(1);
}

$needles = array(
	"close: function( event ) {",
	"target.data( 'cacti-tooltip-pinned' ) === true",
	"if (event.keyCode === $.ui.keyCode.ESCAPE) {",
	"clearPinnedTooltip();"
);

foreach ($needles as $needle) {
	if (strpos($layout, $needle) === false) {
		fwrite(STDERR, "missing e2e behavior guard: $needle\n");
		exit(1);
	}
}

print "issue5540 tooltip pin e2e regression passed\n";
