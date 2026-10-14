<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require dirname(__DIR__, 2) . '/include/vendor/autoload.php';

/** Compare parsed behavior, preserving literal values and argument order. */
function formatting_ast($value) {
	if ($value instanceof PhpParser\Node\Expr\BinaryOp\Concat) {
		$left  = formatting_ast($value->left);
		$right = formatting_ast($value->right);

		if (($left['type'] ?? '') === 'Scalar_String' && ($right['type'] ?? '') === 'Scalar_String') {
			return ['type' => 'Scalar_String', 'value' => $left['value'] . $right['value']];
		}

		return ['type' => $value->getType(), 'left' => $left, 'right' => $right];
	}

	if ($value instanceof PhpParser\Node) {
		$result = ['type' => $value->getType()];

		foreach ($value->getSubNodeNames() as $name) {
			$result[$name] = formatting_ast($value->$name);
		}

		return $result;
	}

	if (is_array($value)) {
		return array_map('formatting_ast', $value);
	}

	return $value;
}

function formatting_git(array $arguments) : string {
	$process = proc_open(array_merge(['git'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Could not start Git');
	}
	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0) {
		throw new RuntimeException($error);
	}

	return $output;
}

$parser  = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$options = getopt('', ['base:', 'self-test']);

if (isset($options['self-test'])) {
	$cases = [
		['guard($a, $b);', "/* comment */ guard(\n\t\$a, \$b);", true],
		['echo "one two";', 'echo "one " . "two";', true],
		['echo "one two";', 'echo "one " . "t" . "wo";', true],
		['echo "one  two";', 'echo "one two";', false],
		['guard($a, $b);', 'guard($b, $a);', false],
		['if ($ok) save();', 'if (!$ok) save();', false],
		['echo $a . $b . "end";', 'echo $a . ($b . "end");', false],
	];

	foreach ($cases as [$before, $after, $equal]) {
		$actual = formatting_ast($parser->parse('<?php ' . $before)) === formatting_ast($parser->parse('<?php ' . $after));

		if ($actual !== $equal) {
			throw new RuntimeException('Formatting checker regression: ' . $before);
		}
	}
	print count($cases) . " formatting checker contracts passed.\n";
	exit(0);
}

if (empty($options['base'])) {
	fwrite(STDERR, "Usage: php tests/tools/check_php_formatting.php --base COMMIT\n");
	exit(2);
}

$base  = trim(formatting_git(['merge-base', $options['base'], 'HEAD']));
$files = array_filter(explode("\n", trim(formatting_git([
	'-c', 'core.quotePath=false', 'diff', '--no-ext-diff', '--no-textconv', '--name-only',
	$base, 'HEAD', '--', '*.php', ':(exclude)tests/**', ':(exclude)include/vendor/**'
]))));
$failed = false;

foreach ($files as $file) {
	$before = formatting_ast($parser->parse(formatting_git(['show', $base . ':' . $file])));
	$after  = formatting_ast($parser->parse(file_get_contents($file)));

	if ($before !== $after) {
		fwrite(STDERR, $file . ": parsed behavior differs; add behavior tests and measure coverage.\n");
		$failed = true;
	}
}

print count($files) . " production PHP files checked for preserved behavior.\n";
exit($failed ? 1 : 0);
