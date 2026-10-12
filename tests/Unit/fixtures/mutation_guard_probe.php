<?php

$root   = $argv[1];
$file   = $argv[2];
$action = $argv[3];
$method = $argv[4];
require $root . '/include/vendor/autoload.php';
$parser  = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$finder  = new PhpParser\NodeFinder();
$printer = new PhpParser\PrettyPrinter\Standard();
$nodes   = $parser->parse(file_get_contents($root . '/' . $file));
$guard   = $finder->findFirst($nodes, static fn ($node) => $node instanceof PhpParser\Node\Stmt\If_
	&& count($node->stmts) === 1
	&& $node->stmts[0] instanceof PhpParser\Node\Stmt\Expression
	&& $node->stmts[0]->expr instanceof PhpParser\Node\Expr\FuncCall
	&& $node->stmts[0]->expr->name instanceof PhpParser\Node\Name
	&& $node->stmts[0]->expr->name->toString() === 'csrf_require_post');

if (!$guard) {
	throw new RuntimeException('Missing mutation guard in ' . $file);
}
$helper = $finder->findFirst($parser->parse(file_get_contents($root . '/include/csrf.php')),
	static fn ($node) => $node instanceof PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'csrf_require_post');

if (!$helper) {
	throw new RuntimeException('Missing POST helper');
}
$_SERVER['REQUEST_METHOD'] = $method;
$reached                   = false;
http_response_code(200);
register_shutdown_function(static function () {
	print json_encode(['status' => http_response_code(), 'reached' => $GLOBALS['reached']]);
});
function grv($name) {
	return $GLOBALS['action'];
}
// csrf_startup() has already accepted the POST token before page dispatch.
// The helper also checks it when combined with the reorder hardening change.
function csrf_check(bool $fatal = true): bool {
	return true;
}
eval($printer->prettyPrint([$helper, $guard]));
$reached = true;
