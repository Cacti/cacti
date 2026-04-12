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
	".on('click.cactiTooltipPin', 'div.cactiTooltipHint, span.cactiTooltipHint'",
	"target.data('cacti-tooltip-pinned', true);",
	"$(document).tooltip('open', $.Event('mouseover', {",
	".on('click.cactiTooltipPinDismiss', function (event) {",
	"tooltip = tooltipId ? $('#' + tooltipId) : $();"
);

foreach ($needles as $needle) {
	if (strpos($layout, $needle) === false) {
		fwrite(STDERR, "missing integration behavior: $needle\n");
		exit(1);
	}
}

print "issue5540 tooltip pin integration regression passed\n";
