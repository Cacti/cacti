<?php
// Copyright (C) 2026 The Cacti Group. GPL version 2 or later.

test('every Reports mutation rejects GET and invalid tokens before dispatch (#7773)', function () {
	$root    = dirname(__DIR__, 4);
	$parser  = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$finder  = new PhpParser\NodeFinder();
	$printer = new PhpParser\PrettyPrinter\Standard();
	$helper  = $finder->findFirst($parser->parse(file_get_contents($root . '/include/csrf.php')),
		static fn ($node) => $node instanceof PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'csrf_require_post');
	$gate = $finder->findFirst($parser->parse(file_get_contents($root . '/reports.php')),
		static fn ($node) => $node instanceof PhpParser\Node\Stmt\If_
			&& $node->cond instanceof PhpParser\Node\Expr\FuncCall
			&& $node->cond->name instanceof PhpParser\Node\Name
			&& $node->cond->name->toString() === 'in_array');
	expect($helper)->not->toBeNull()->and($gate)->not->toBeNull();
	$source = $printer->prettyPrint([$helper, $gate]);

	foreach (['save', 'actions', 'send', 'remove_history', 'ajax_dnd', 'item_moveup', 'item_movedown', 'item_remove'] as $action) {
		foreach ([['GET', null, 405], ['POST', null, 403], ['POST', 'invalid', 403], ['POST', 'valid', 200]] as [$method, $token, $expected]) {
			$seed   = var_export(['action' => $action, 'method' => $method, 'token' => $token], true);
			$script = '<?php $seed = ' . $seed . ';' . <<<'PHP'
$_SERVER['REQUEST_METHOD'] = $seed['method'];
http_response_code(200);
$dispatched = 0;
register_shutdown_function(function () { echo json_encode(['status' => http_response_code(), 'dispatched' => $GLOBALS['dispatched']]); });
function grv($name) { return $GLOBALS['seed']['action']; }
function csrf_check($fatal) { return $GLOBALS['seed']['token'] === 'valid'; }
PHP;
			$script .= "\n" . $source . "\n\$dispatched = 1;";
			$process = proc_open([PHP_BINARY], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			fwrite($pipes[0], $script);
			fclose($pipes[0]);
			$result = json_decode(stream_get_contents($pipes[1]), true);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			expect(proc_close($process))->toBe(0)->and($stderr)->toBe('')
				->and($result)->toBe(['status' => $expected, 'dispatched' => $expected === 200 ? 1 : 0]);
		}
	}
});
