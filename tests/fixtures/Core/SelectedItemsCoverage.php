<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Request-coverage runner for the places that write selected items back into
 * a page: the form_actions() export and download scripts, and the hidden
 * input of form_continue_confirmation(). The export branches exit, so every
 * scenario runs in its own probe process.
 */

$arguments = ['-d', 'display_errors=stderr'];

if (getenv('CACTI_PR_XDEBUG_EXTENSION')) {
	$arguments = array_merge($arguments, ['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')]);
}

$scenarios = [['SelectedItemsConfirmationProbe.php', ['list']], ['SelectedItemsConfirmationProbe.php', ['quoted']]];

foreach (['automation_graph_rules', 'automation_networks', 'automation_snmp', 'automation_templates', 'automation_tree_rules', 'data_source_profiles', 'host_templates', 'host_template_archives'] as $page) {
	foreach (['list', 'trailing', 'whitespace'] as $payload) {
		$scenarios[] = ['SelectedItemsExportProbe.php', [$page, $payload]];
	}
}

foreach ($scenarios as [$probe, $args]) {
	$process = proc_open(array_merge([PHP_BINARY], $arguments, [__DIR__ . '/' . $probe], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output  = stream_get_contents($pipes[1]);
	$error   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0 || !is_array(json_decode($output, true))) {
		throw new RuntimeException($probe . ' ' . implode(' ', $args) . ': ' . $error);
	}
}
print "PASS: selected items export and confirmation scenarios\n";
