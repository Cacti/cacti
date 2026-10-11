<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('dsstats partition options retain the selected collector type', function (array $parameters, string $expectedType, bool $expectedPartition) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/poller_dsstats.php');
	$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$nodes  = $parser->parse($source);
	$block  = (new \PhpParser\NodeFinder())->findFirst($nodes, static function ($node) {
		return $node instanceof \PhpParser\Node\Stmt\If_ &&
			$node->cond instanceof \PhpParser\Node\Expr\FuncCall &&
			$node->cond->name instanceof \PhpParser\Node\Name &&
			$node->cond->name->toString() === 'cacti_sizeof' &&
			$node->cond->args[0]->value instanceof \PhpParser\Node\Expr\Variable &&
			$node->cond->args[0]->value->name === 'parms';
	});
	expect($block)->not->toBeNull();
	$fragment = substr($source, $block->getStartFilePos(), $block->getEndFilePos() - $block->getStartFilePos() + 1);

	// Execute the actual repository argument parser, before DB/poller work.
	$parse = static function (array $parms) use ($fragment) : array {
		$debug      = false;
		$force      = false;
		$fpartition = false;
		$type       = 'pmaster';
		$thread_id  = 0;
		eval($fragment);

		return [$type, $fpartition];
	};

	expect($parse($parameters))->toBe([$expectedType, $expectedPartition]);
})->with([
	'no partition request'        => [[], 'pmaster', false],
	'bare long option'            => [['--partition'], 'pmaster', true],
	'bare short option'           => [['-p'], 'pmaster', true],
	'type before partition'       => [['--type=bmaster', '--partition'], 'bmaster', true],
	'type after partition'        => [['--partition', '--type=dmaster'], 'dmaster', true],
	'legacy partition type'       => [['--partition=bmaster'], 'bmaster', true],
	'legacy short partition type' => [['-p=dmaster'], 'dmaster', true],
]);
