<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Translated labels that are selected as SQL literals must be quoted with
 * db_qstr(). The shipped fr-FR catalog translates 'Any' as "N'importe lequel",
 * which closed the hand-written '...' literal in the report item Device
 * Template query and turned it into a syntax error.
 */

$root = dirname(__DIR__, 4);

dataset('translated_sql_files', [
	'lib/html_reports.php',
	'lib/auth.php',
	'include/global_form.php',
	'support.php',
]);

test('translated labels are not concatenated inside hand-quoted SQL literals', function (string $file) use ($root) {
	$offenders = [];

	foreach (file($root . '/' . $file) as $number => $line) {
		if (preg_match('/(?:\'" \. __\(|"\' \. __\()/', $line) && preg_match('/\b(?:SELECT|CONCAT)\b/', $line)) {
			$offenders[] = $file . ':' . ($number + 1);
		}
	}

	expect($offenders)->toBe([]);
})->with('translated_sql_files');

test('report item and tree branch queries quote their translated labels', function () use ($root) {
	$reports = file_get_contents($root . '/lib/html_reports.php');
	$auth    = file_get_contents($root . '/lib/auth.php');

	expect(substr_count($reports, "db_qstr(__('Any'))"))->toBe(2)
		->and(substr_count($reports, "db_qstr(__('None'))"))->toBe(2)
		->and($auth)->toContain("CONCAT(' . db_qstr(__('Branch:') . ' ') . \", gti.title)")
		->and($auth)->toContain("CONCAT(\" . db_qstr(__('Device:') . ' ') . \", h.description)");
});

test('the report item device template query keeps an apostrophe inside its label', function () use ($root) {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	// Locate the production 'sql' expression of the host_template_id field.
	$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$ast    = $parser->parse(file_get_contents($root . '/lib/html_reports.php'));
	$finder = new \PhpParser\NodeFinder();

	$fields = $finder->find($ast, function ($node) {
		return $node instanceof \PhpParser\Node\ArrayItem
			&& $node->key instanceof \PhpParser\Node\Scalar\String_
			&& $node->key->value === 'host_template_id'
			&& $node->value instanceof \PhpParser\Node\Expr\Array_;
	});

	$sql = null;

	foreach ($fields as $field) {
		foreach ($field->value->items as $item) {
			if ($item->key instanceof \PhpParser\Node\Scalar\String_ && $item->key->value === 'sql') {
				$sql = $item->value;
			}
		}
	}

	expect($sql)->not->toBeNull();

	// Feed the shipped fr-FR translations of 'Any' and 'None' to the real
	// expression; db_qstr() itself is not replaced.
	$catalog = ['Any' => "N'importe lequel", 'None' => 'Aucun'];

	foreach ($finder->find($sql, fn ($node) => $node instanceof \PhpParser\Node\Expr\FuncCall && $node->name instanceof \PhpParser\Node\Name && $node->name->toString() === '__') as $call) {
		$call->name = new \PhpParser\Node\Name('strval');
		$call->args = [new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_($catalog[$call->args[0]->value->value]))];
	}

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;
	$conn    = new PDO('sqlite::memory:');

	$database_sessions[$session] = $conn;

	try {
		$query = eval('return ' . (new \PhpParser\PrettyPrinter\Standard())->prettyPrintExpr($sql) . ';');
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}

	// The two label rows must parse on their own; the remainder is MySQL-only.
	$labels = substr($query, 0, (int) strpos($query, ' UNION ('));
	$rows   = $conn->query($labels)->fetchAll(PDO::FETCH_ASSOC);

	expect(array_column($rows, 'name', 'id'))->toBe([-1 => "N'importe lequel", 0 => 'Aucun']);
});
