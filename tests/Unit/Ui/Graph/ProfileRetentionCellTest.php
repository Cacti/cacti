<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('profile retention labels choose the configured or computed span and retain emphasis', function (?string $configuredLabel) {
	$source       = file_get_contents(dirname(__DIR__, 4) . '/data_source_profiles.php');
	$parser       = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$nodes        = $parser->parse($source);
	$finder       = new \PhpParser\NodeFinder();
	$printer      = new \PhpParser\PrettyPrinter\Standard();
	$spanFunction = $finder->findFirst($nodes, static fn ($node) => $node instanceof \PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'get_span');
	expect($spanFunction)->not->toBeNull();
	// Bind the actual page-local get_span body as a closure; never shadow core functions.
	$spanNode = new \PhpParser\Node\Expr\Closure([
		'params'     => $spanFunction->params, 'stmts' => $spanFunction->stmts,
		'returnType' => $spanFunction->returnType,
	]);
	$span       = eval('return ' . $printer->prettyPrintExpr($spanNode) . ';');
	$assignment = $finder->findFirst($nodes, static fn ($node) => $node instanceof \PhpParser\Node\Expr\Assign &&
		$node->var instanceof \PhpParser\Node\Expr\Variable && $node->var->name === 'retention');
	$call = $finder->findFirst($nodes, static function ($node) use ($printer) {
		return $node instanceof \PhpParser\Node\Expr\FuncCall &&
			$node->name instanceof \PhpParser\Node\Name &&
			$node->name->toString() === 'form_selectable_cell' &&
			preg_match('/\$(timespans|retention)\b/', $printer->prettyPrintExpr($node->args[0]->value));
	});
	expect($call)->not->toBeNull();

	// Route only the page-local dependency to its production closure.
	foreach ($finder->findInstanceOf($nodes, \PhpParser\Node\Expr\FuncCall::class) as $dependency) {
		if ($dependency->name instanceof \PhpParser\Node\Name && $dependency->name->toString() === 'get_span') {
			$dependency->name = new \PhpParser\Node\Expr\Variable('span');
		}
	}
	$rra       = ['timespan' => 7200];
	$timespans = $configuredLabel === null ? [] : [7200 => $configuredLabel];

	if ($assignment !== null) {
		eval($printer->prettyPrintExpr($assignment) . ';');
	}
	$result = eval('return ' . $printer->prettyPrintExpr($call->args[0]->value) . ';');
	expect($result)->toBe('<em>' . ($configuredLabel ?? '2 Hours') . '</em>');
})->with(['configured span' => ['Configured span'], 'computed span' => [null]]);
