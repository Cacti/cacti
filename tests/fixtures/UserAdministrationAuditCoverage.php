<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require dirname(__DIR__) . '/Helpers/IsolatedProbe.php';
$arguments = [];

if (getenv('CACTI_PR_XDEBUG_EXTENSION')) {
	$arguments = ['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')];
}

foreach (['edit', 'password', 'failed-save', 'failed-save-id', 'save-read-failure', 'save-missing', 'mismatch', 'new', 'audit-controls', 'delete', 'delete-template', 'delete-guest', 'delete-failure', 'delete-read-failure', 'delete-missing', 'enable', 'disable', 'lock', 'unlock', 'audit-invalid', 'audit-unknown', 'audit-cli', 'bulk-enable', 'bulk-disable', 'bulk-enable-failure', 'bulk-disable-failure', 'bulk-enable-read-failure', 'bulk-disable-read-failure', 'bulk-enable-missing', 'bulk-disable-missing', 'bulk-enable-noop', 'bulk-disable-noop'] as $scenario) {
	$process = proc_open(array_merge([PHP_BINARY], $arguments, [__DIR__ . '/UserAdministrationAuditProbe.php', $scenario]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output  = stream_get_contents($pipes[1]);
	$error   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0 || !is_array(json_decode($output, true))) {
		throw new RuntimeException($scenario . ': ' . $error);
	}
}
print "PASS: native account save and audit scenarios\n";
