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

/*
 * A slice may carry behavior fixes next to its re-wrapping, and those are held
 * to the changed-line coverage gate instead.  So each style commit is checked
 * against its parent, which keeps a fix from hiding a formatting change that
 * alters behavior, or a formatting change from hiding inside a fix.
 */
$base    = trim(formatting_git(['merge-base', $options['base'], 'HEAD']));
$commits = array_filter(explode("\n", trim(formatting_git([
	'log', '--no-merges', '--reverse', '--format=%H %s', $base . '..HEAD'
]))));
$checked = 0;
$failed  = false;

foreach ($commits as $commit) {
	[$sha, $subject] = explode(' ', $commit, 2) + [1 => ''];

	if (preg_match('/^style(\(|!?:)/', $subject) !== 1) {
		continue;
	}

	$files = array_filter(explode("\n", trim(formatting_git([
		'-c', 'core.quotePath=false', 'diff-tree', '--no-commit-id', '--name-only', '-r', '--diff-filter=M',
		$sha, '--', '*.php', ':(exclude)tests/**', ':(exclude)include/vendor/**'
	]))));

	foreach ($files as $file) {
		$before = formatting_ast($parser->parse(formatting_git(['show', $sha . '^:' . $file])));
		$after  = formatting_ast($parser->parse(formatting_git(['show', $sha . ':' . $file])));
		$checked++;

		if ($before !== $after) {
			fwrite(STDERR, substr($sha, 0, 10) . ' ' . $file . ": parsed behavior differs in a style commit; move the change to a fix commit with behavior tests.\n");
			$failed = true;
		}
	}
}

print $checked . " production PHP files checked for preserved behavior in style commits.\n";
exit($failed ? 1 : 0);
