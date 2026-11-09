<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

// Evaluate the checked-in reports.php POST guard condition without
// dispatching the page: every mutating action must require POST and the
// read-only views must not.
$root   = dirname(__DIR__, 3);
$file   = $root . '/reports.php';
$source = file_get_contents($file);

if (!preg_match('/^if \((in_array\(grv\(\'action\'\), \[.*?\], true\))\) \{\s*csrf_require_post\(\);/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
	throw new RuntimeException('reports.php POST guard is missing');
}
[$expression, $offset] = $match[1];
$line                  = substr_count(substr($source, 0, $offset), "\n") + 1;
$temporary             = tempnam(sys_get_temp_dir(), 'cacti-reports-guard-');
file_put_contents($temporary, '<?php return static function (string $action) : bool { $grv = static fn () => $action;' . str_repeat("\n", $line - 1) . 'return ' . str_replace('grv(', '$grv(', $expression) . '; };');
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

try {
	$guard = require $temporary;
} finally {
	unlink($temporary);
}
$count = 0;

foreach (['save', 'actions', 'send', 'remove_history', 'ajax_dnd', 'item_moveup', 'item_movedown', 'item_remove'] as $action) {
	if ($guard($action) !== true) {
		throw new RuntimeException('Mutating reports action does not require POST: ' . $action);
	}
	$count++;
}

foreach (['', 'edit', 'item_edit', 'history', 'Save', 'save '] as $action) {
	if ($guard($action) !== false) {
		throw new RuntimeException('Read-only reports action requires POST: ' . $action);
	}
	$count++;
}
print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
