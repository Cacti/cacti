<?php
// Copyright (C) 2026 The Cacti Group. GPL version 2 or later.

test('link and plugin reorders reject GET and invalid tokens before writing (#7454)', function () {
	$root    = dirname(__DIR__, 4);
	$parser  = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$finder  = new PhpParser\NodeFinder();
	$printer = new PhpParser\PrettyPrinter\Standard();

	foreach (['links.php', 'plugins.php'] as $file) {
		$nodes = $parser->parse(file_get_contents($root . '/' . $file));
		$case  = $finder->findFirst($nodes, static fn ($node) => $node instanceof PhpParser\Node\Stmt\Case_
			&& $node->cond instanceof PhpParser\Node\Scalar\String_ && $node->cond->value === 'ajax_dnd');
		expect($case)->not->toBeNull();
		$body = $printer->prettyPrint($case->stmts);

		foreach ([['GET', null, 405], ['POST', null, 403], ['POST', 'invalid', 403], ['POST', 'valid', 302]] as $scenario) {
			[$method, $token, $status] = $scenario;
			$seed                      = var_export(['method' => $method, 'token' => $token], true);
			$script                    = '<?php $seed = ' . $seed . ';' . <<<'PHP'

$_SERVER['REQUEST_METHOD'] = $seed['method'];
http_response_code(200);
$writes = 0;
register_shutdown_function(function () { echo json_encode(array('status' => http_response_code(), 'writes' => $GLOBALS['writes'])); });
function csrf_check($fatal) { return $GLOBALS['seed']['token'] === 'valid'; }
function gnrv($name) { return array(2, 1); }
function links_reorder($order) { $GLOBALS['writes']++; }
function api_plugin_reorder($order) { $GLOBALS['writes']++; }
PHP;
			$script .= "\nswitch ('ajax_dnd') { case 'ajax_dnd':\n" . $body . "\n}";
			$process = proc_open([PHP_BINARY], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			fwrite($pipes[0], $script);
			fclose($pipes[0]);
			$result = json_decode(stream_get_contents($pipes[1]), true);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			expect(proc_close($process))->toBe(0);
			expect($stderr)->toBe('');
			expect($result)->toBe(['status' => $status, 'writes' => $status === 302 ? 1 : 0]);
		}
	}
});

test('reorder clients submit their action and serialized order through the CSRF POST helper', function () {
	$root = dirname(__DIR__, 4);

	foreach (['links.php', 'plugins.php'] as $file) {
		$source = file_get_contents($root . '/' . $file);
		expect($source)->toContain("cactiPreparePostRequest('" . $file . "', 'action=ajax_dnd&' + $.tableDnD.serialize())");
		expect($source)->toContain('postUrl({url: request.url}, request.data)');
		expect($source)->not->toContain("loadUrl({url:'" . $file . '?action=ajax_dnd');
	}
});
