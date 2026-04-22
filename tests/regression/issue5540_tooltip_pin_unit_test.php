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

if (strpos($layout, 'var pinnedTooltipElement = null;') === false) {
	fwrite(STDERR, "missing pinned tooltip state\n");
	exit(1);
}

if (strpos($layout, 'function clearPinnedTooltip() {') === false) {
	fwrite(STDERR, "missing pinned tooltip clear helper\n");
	exit(1);
}

if (strpos($layout, "target.removeData('cacti-tooltip-pinned');") === false) {
	fwrite(STDERR, "missing pinned tooltip reset\n");
	exit(1);
}

print "issue5540 tooltip pin unit regression passed\n";
