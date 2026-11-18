<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require __DIR__ . '/automation_creation_bootstrap.php';
load_actual_functions($root . '/lib/functions.php', ['get_hash_data_template', 'generate_hash']);

clear_fixture();
seed_graph_template();
$values  = [];
$created = create_complete_graph_from_template(1, 1, [], $values);
check(is_array($created), 'source graph exists');
$source            = $created['local_data_id'][1];
$operation         = $argv[1];
$failure           = $argv[2];
$key               = "$database_hostname:$database_port:$database_default";
$source_argument   = $source;
$template_argument = 1;

if ($failure === 'connection') {
	unset($database_sessions[$key]);
} elseif ($failure === 'begin' || $failure === 'commit') {
	$pdo->transaction_failure = $failure;
} elseif ($failure === 'missing-source') {
	$source_argument = 999999;
} elseif ($failure === 'missing-source-data') {
	$pdo->exec("DELETE FROM data_template_data WHERE local_data_id=$source");
} elseif ($failure === 'missing-template') {
	$source_argument   = 0;
	$template_argument = 999999;
} elseif ($failure === 'missing-template-data') {
	$source_argument = 0;
	$pdo->exec('DELETE FROM data_template_data WHERE local_data_id=0');
} elseif ($failure === 'duplicate-items') {
	// Legacy databases can contain duplicate names before the unique-key upgrade.
	$pdo->exec('CREATE TEMPORARY TABLE legacy_rrds AS SELECT * FROM data_template_rrd');
	$pdo->exec('CREATE TEMPORARY TABLE data_template_rrd AS SELECT * FROM legacy_rrds');
	$pdo->exec("INSERT INTO data_template_rrd (data_template_id,data_source_name) VALUES (1,'traffic')");
} elseif ($failure === 'exception' || $failure === 'last-error') {
	$pdo->before = static function ($sql) use ($failure) {
		if ($failure === 'exception') {
			throw new RuntimeException('fixture interrupted operation');
		}
		$GLOBALS['database_last_error'] = 'fixture nested helper failure';
	};
} elseif ($failure === 'nested-success') {
	$pdo->beginTransaction();
}

$snapshot = static function () use ($pdo) {
	$result = [];

	foreach (['data_local', 'data_template', 'data_template_data', 'data_template_rrd', 'data_input_data'] as $table) {
		$result[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1,2")->fetchAll(PDO::FETCH_ASSOC);
	}

	return $result;
};
$before       = $snapshot();
$review_hooks = [];
$result       = $operation === 'copy'
	? api_data_source_duplicate($source_argument, $template_argument, 'Boundary copy')
	: ($operation === 'switch' ? change_data_template($source_argument, $template_argument)
		: create_complete_graph_from_template(1, 1, [], $values));
$database_sessions[$key]  = $pdo;
$pdo->before              = null;
$pdo->transaction_failure = '';

if ($failure === 'nested-success') {
	check($result !== false && $pdo->inTransaction(), 'successful nested copy preserves the caller transaction');
	check($review_hooks === [], 'nested copy does not publish uncommitted hooks');
	$pdo->rollBack();
} else {
	check($result === false, 'failed operation reports failure');
	check(!$pdo->inTransaction(), 'failed operation closes its transaction');
	check($review_hooks === [], 'failed operation does not publish hooks');
}
check($snapshot() === $before, 'failed or rolled-back operation preserves all source tables');
emit_result($operation . '-' . $failure, ['preserved' => true]);
