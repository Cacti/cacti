#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/**
 * Read-only integrity audit for graph template inputs.
 *
 * This command never changes database state. It exits 2 when findings exist.
 */

require(__DIR__ . '/../include/cli_check.php');

foreach (array_slice($_SERVER['argv'], 1) as $argument) {
	if (in_array($argument, array('--version', '-V', '-v'), true)) {
		display_version();
		exit(0);
	}

	if (in_array($argument, array('--help', '-H', '-h'), true)) {
		display_help();
		exit(0);
	}

	print 'ERROR: Invalid Parameter ' . $argument . PHP_EOL;
	exit(1);
}

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;
	exit(1);
}

$invalid_columns = array();

foreach (db_fetch_assoc('SELECT id, column_name FROM graph_template_input ORDER BY id') as $input) {
	if (!graph_template_input_column_is_allowed($input['column_name'])) {
		$invalid_columns[] = (int) $input['id'];
	}
}

$orphan_definitions = db_fetch_assoc('SELECT gtid.graph_template_input_id, gtid.graph_template_item_id
	FROM graph_template_input_defs AS gtid
	LEFT JOIN graph_template_input AS input
	ON input.id = gtid.graph_template_input_id
	LEFT JOIN graph_templates_item AS item
	ON item.id = gtid.graph_template_item_id
	WHERE input.id IS NULL OR item.id IS NULL');

$cross_template_definitions = db_fetch_assoc('SELECT gtid.graph_template_input_id, gtid.graph_template_item_id
	FROM graph_template_input_defs AS gtid
	INNER JOIN graph_template_input AS input
	ON input.id = gtid.graph_template_input_id
	INNER JOIN graph_templates_item AS item
	ON item.id = gtid.graph_template_item_id
	WHERE input.graph_template_id <> item.graph_template_id');

$non_template_definitions = db_fetch_assoc('SELECT gtid.graph_template_input_id, gtid.graph_template_item_id
	FROM graph_template_input_defs AS gtid
	INNER JOIN graph_templates_item AS item
	ON item.id = gtid.graph_template_item_id
	WHERE item.local_graph_id <> 0');

$empty_inputs = db_fetch_assoc('SELECT input.id
	FROM graph_template_input AS input
	LEFT JOIN graph_template_input_defs AS gtid
	ON gtid.graph_template_input_id = input.id
	WHERE gtid.graph_template_input_id IS NULL');

$findings = array(
	'disallowed column names'         => $invalid_columns,
	'orphaned definitions'            => $orphan_definitions,
	'cross-template definitions'      => $cross_template_definitions,
	'non-template item definitions'   => $non_template_definitions,
	'inputs without associated items' => $empty_inputs,
);

$finding_count = 0;

foreach ($findings as $label => $rows) {
	$count = cacti_sizeof($rows);
	$finding_count += $count;
	print sprintf('%-32s %d', $label . ':', $count) . PHP_EOL;
}

if ($finding_count > 0) {
	print 'Graph template input integrity findings detected; no changes were made.' . PHP_EOL;
	exit(2);
}

print 'Graph template input integrity audit passed.' . PHP_EOL;
exit(0);

function display_version() {
	print 'Cacti Graph Template Input Audit, Version ' . get_cacti_cli_version() . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();
	print PHP_EOL . 'Usage: audit_graph_template_inputs.php [--help|--version]' . PHP_EOL . PHP_EOL;
	print 'Reports graph template input integrity findings without changing database state.' . PHP_EOL;
}
