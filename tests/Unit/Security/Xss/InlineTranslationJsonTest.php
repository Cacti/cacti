<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

test('inline translated values use the shared JSON boundary and preserve HTML escaping', function (string $path, string $variable, string $catalogMessage) {
	$source  = file_get_contents(CACTI_PATH_BASE . '/' . $path);
	$pattern = '/\bvar\s+' . preg_quote($variable, '/') . '\s*=\s*[\'\"]?<\?php\s+print\s+(.*?)\?>/s';
	expect(preg_match($pattern, $source, $match))->toBe(1);
	$parser     = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$statements = $parser->parse('<?php print ' . trim($match[1]));
	$expression = $statements[0]->expr;
	expect($expression)->toBeInstanceOf(\PhpParser\Node\Expr\Print_::class);
	$encodedCall = $expression->expr;
	expect($encodedCall)->toBeInstanceOf(\PhpParser\Node\Expr\FuncCall::class);
	expect($encodedCall->name->toString())->toBe('cacti_js_encode');
	$translatedCall = $encodedCall->args[0]->value;
	expect($translatedCall)->toBeInstanceOf(\PhpParser\Node\Expr\FuncCall::class);
	expect($translatedCall->name->toString())->toBe('__esc');

	// Supply adversarial catalog text to the actual production expression;
	// neither the translation helper nor the JSON encoder is replaced.
	$translatedCall->args[0]->value = new \PhpParser\Node\Expr\Variable('catalogMessage');
	$printer                        = new \PhpParser\PrettyPrinter\Standard();
	$encoded                        = eval('return ' . $printer->prettyPrintExpr($encodedCall) . ';');
	expect(json_decode($encoded, true, 512, JSON_THROW_ON_ERROR))->toBe(__esc($catalogMessage));
	expect($encoded)->not->toContain('</script>');
	expect($encoded)->not->toContain("\u{2028}");
	expect($encoded)->not->toContain("\u{2029}");
})->with([
	['settings.php', 'permsHeader'],
	['settings.php', 'permsMessage'],
	['lib/html.php', 'tableConstraints'],
	['lib/html.php', 'zoom_outOfRangeMessage'],
])->with([
	'quotes and backslashes' => ["single' double\" backslash\\"],
	'script closing text'    => ['</script><script>window.untrusted=true</script>'],
	'line terminators'       => ["first\nsecond\u{2028}third\u{2029}fourth"],
	'HTML entities'          => ['&quot; &apos; <b>caption</b>'],
]);
