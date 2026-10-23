<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
declare(strict_types = 1);

$root = dirname(__DIR__, 2);
require $root . '/include/vendor/autoload.php';
require $root . '/include/global_constants.php';
require $root . '/lib/CactiValidator.php';
$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$request  = [
	'id'        => '3', 'item_id' => (string) $scenario['id'], 'sequence' => '1',
	'operation' => '0', 'field' => $scenario['kind']   === 'match' ? 'ht.name' : 'ifDescr',
	'operator'  => '7', 'pattern' => $scenario['kind'] === 'match' ? 'Generic SNMP Device' : 'eth0',
];
$component           = $scenario['kind'] === 'match' ? 'save_component_automation_match_item' : 'save_component_automation_graph_rule_item';
$request[$component] = '1';
$_SESSION            = [];
$saved               = [];
$savedTable          = '';
$lookupId            = null;

function isrv(string $name): bool {
	return array_key_exists($name, $GLOBALS['request']);
}
function grv(string $name, mixed $default = ''): mixed {
	return $GLOBALS['request'][$name] ?? $default;
}
function gnrv(string $name): mixed {
	return grv($name);
}
function gfrv(string $name): int {
	return $GLOBALS['request'][$name] = (int) grv($name);
}
function raise_message(mixed ...$arguments): void {
}
function is_error_message(): bool {
	return !empty($_SESSION[SESS_ERROR_FIELDS]);
}
function db_column_exists(string $table, string $column): bool {
	return $column === 'name';
}
function db_fetch_cell_prepared(string $sql, array $parameters): mixed {
	if (str_contains($sql, 'SELECT hash')) {
		$GLOBALS['lookupId'] = $parameters[0];

		return $parameters[0] === 17 ? str_repeat('b', 32) : '';
	}

	return 'ifDescr';
}
function generate_hash(): string {
	return str_repeat('a', 32);
}
function sql_save(array $row, string $table): int {
	$GLOBALS['saved']      = $row;
	$GLOBALS['savedTable'] = $table;

	return $row['id'] ?: 27;
}

$body = '<?php use Symfony\\Component\\Validator\\Constraints as Assert;' . PHP_EOL;

foreach ($scenario['definitions'] as $file => $source) {
	if (!in_array($file, ['automation_graph_rules.php', 'lib/functions.php'], true) || hash_file('sha256', $root . '/' . $file) !== $source['hash']) {
		throw new RuntimeException('The extracted production source changed');
	}
	$definition = base64_decode($source['definition'], true);

	if ($definition === false) {
		throw new RuntimeException('Invalid production definition');
	}
	$body .= $definition . PHP_EOL;
}
$temporary = tempnam(sys_get_temp_dir(), 'cacti-graph-criteria-');

if ($temporary === false) {
	throw new RuntimeException('Cannot write the isolated production function');
}

try {
	if (file_put_contents($temporary, $body) !== strlen($body)) {
		throw new RuntimeException('Incomplete isolated production function');
	}
	require $temporary;
	form_save();
} finally {
	unlink($temporary);
}
print json_encode(['saved' => $saved, 'table' => $savedTable, 'lookup_id' => $lookupId, 'errors' => $_SESSION[SESS_ERROR_FIELDS] ?? []], JSON_THROW_ON_ERROR) . PHP_EOL;
