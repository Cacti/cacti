<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$coverage = getenv('CACTI_COVERAGE_FILE');

if ($coverage !== false && $coverage !== '') {
	if (!function_exists('xdebug_start_code_coverage')) {
		throw new RuntimeException('Xdebug coverage is required when CACTI_COVERAGE_FILE is set');
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
}

define('CACTI_SERVER_OS', 'unix');
$config = ['cacti_server_os' => 'unix'];
require_once dirname(__DIR__, 3) . '/lib/functions.php';

$check = static function ($actual, $expected, string $case) : void {
	if ($actual !== $expected) {
		throw new RuntimeException('Substitution failed: ' . $case);
	}
};

// Cover parser boundaries independently of the shell: malformed and unknown
// placeholders remain literal, raw paths retain quoting, values are single-pass.
$cases = [
	['', [], ''],
	['plain', [], 'plain'],
	['<unknown>', [], '<unknown>'],
	['"<unknown>"', [], '"<unknown>"'],
	['<bad-name>', [], '<bad-name>'],
	['<unterminated', [], '<unterminated'],
	['<0_>', ['0_' => 'ok'], 'ok'],
	['<a><b>', ['a' => '<b>', 'b' => 'safe'], '<b>safe'],
	['"<path>"', ['path' => '/space path/php'], '"/space path/php"'],
	['<path>/script.php', ['path' => '/space path'], '/space path/script.php'],
	['<a>', ['a' => ''], ''],
	['<a>', ['a' => "'"], "'"],
	['<a>', ['a' => "'raw"], "'raw"],
	['<a>', ['a' => '"raw'], '"raw'],
	['<a>', ['a' => 'raw"'], 'raw"'],
	['\\', [], '\\'],
	['"\\', [], '"\\'],
	['"\\x"', [], '"\\x"'],
	['\\x', [], '\\x'],
	['\\"', [], '\\"'],
	["\\'", [], "\\'"],
	['"\\\'"', [], '"\\\'"'],
	["'a'", [], "'a'"],
	['"a"', [], '"a"'],
	['<a>', ['a' => '"safe"'], '"safe"'],
	// command-substitution, backtick and escaped-dollar/backtick template shapes:
	// the scanner parses $(...) and backticks as nested contexts with their own
	// quoting and treats \$ / \` as escaped, so a self-quoting value stays literal
	['"$(printf %s <field>)"', ['field' => "'p'"], '"$(printf %s \'p\')"'],
	['$(printf %s <field>)', ['field' => "'p'"], '$(printf %s \'p\')'],
	['"`printf %s <field>`"', ['field' => "'p'"], ''],
	['`printf %s <field>`', ['field' => "'p'"], ''],
	['`printf %s <field>`', ['field' => 'configured-path'], '`printf %s configured-path`'],
	['cat <<EOF' . "\n<field>\nEOF", ['field' => "'p'"], ''],
	['cat <' . "\\\n" . '<EOF' . "\n<field>\nEOF", ['field' => "'p'"], ''],
	['"${name:-<field>}"', ['field' => "'p'"], ''],
	['${name#<field>}', ['field' => "'p'"], ''],
	['$' . "\\\n" . '{name:-<field>}', ['field' => "'p'"], ''],
	['"prefix' . "\\\r\n" . '<field>"', ['field' => "'p'"], ''],
	['"${HOME}" <field>', ['field' => "'p'"], '"${HOME}" \'p\''],
	['"<<" <field>', ['field' => "'p'"], '"<<" \'p\''],
	['"\\$(<field>)"', ['field' => "'p'"], '"\\$("\'p\'")"'],
	['"\\`<field>"', ['field' => "'p'"], '"\\`"\'p\'""'],
	['\\$(<field>)', ['field' => "'p'"], '\\$(\'p\')'],
	['\\`<field>', ['field' => "'p'"], '\\`\'p\''],
	// Unsupported nested shell syntax must reject the complete template.
	['"$( (true); printf %s <field>)"', ['field' => "'p'"], ''],
	['"$( ((true)); printf %s <field>)"', ['field' => "'p'"], ''],
	['"$(case x in x) printf %s <field>;; esac)"', ['field' => "'p'"], ''],
	['"$(printf %s <field>; (true))"', ['field' => "'p'"], ''],
	['"$(printf %s <field> # )\n)"', ['field' => "'p'"], ''],
	['"$(cat <<EOF\n<field>\nEOF\n)"', ['field' => "'p'"], ''],
	['"`(true); printf %s <field>`"', ['field' => "'p'"], ''],
	['"$((1 + <field>))"', ['field' => "'p'"], ''],
];

foreach ($cases as $index => [$template, $values, $expected]) {
	$check(substitute_script_path($template, $values), $expected, 'parser ' . $index);
}

$directory = sys_get_temp_dir() . '/cacti-shell-matrix-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$marker   = $directory . '/executed';
$payloads = ['', '0', 'hello world', "single'quote", '"double"', 'line' . "\n" . 'break', 'a\\b', '<other>',
	'$(touch ' . escapeshellarg($marker) . ')', '`touch ' . escapeshellarg($marker) . '`',
	'; touch ' . escapeshellarg($marker) . '; #', '${HOME}', '* ? [abc]', 'é 日本語'];
$count = count($cases);

try {
	foreach ($payloads as $value) {
		foreach (['', "'", '"'] as $quote) {
			foreach (['exact', 'prefix', 'suffix', 'embedded', 'adjacent'] as $shape) {
				for ($slashes = 0; $slashes <= 5; $slashes++) {
					$prefix   = in_array($shape, ['prefix', 'embedded'], true) ? 'prefix' : '';
					$suffix   = in_array($shape, ['suffix', 'embedded'], true) ? 'suffix' : '';
					$template = $quote . $prefix . str_repeat('\\', $slashes) . '<field>' . ($shape === 'adjacent' ? '<other>' : '') . $suffix . $quote;
					$escaped  = $value === '' ? escapeshellarg($value) : cacti_escapeshellarg($value);
					$argument = substitute_script_path($template, ['field' => $escaped, 'other' => cacti_escapeshellarg('other')]);
					$expected = $prefix . str_repeat('\\', $quote === "'" ? $slashes : intdiv($slashes, 2)) . str_replace(["\r", "\n"], '', $value) . ($shape === 'adjacent' ? 'other' : '') . $suffix;
					$pipes    = [];
					$process  = proc_open(['sh', '-c', 'printf %s ' . $argument], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

					if (!is_resource($process)) {
						throw new RuntimeException('Cannot start POSIX shell');
					}
					$stdout = stream_get_contents($pipes[1]);
					$stderr = stream_get_contents($pipes[2]);
					fclose($pipes[1]);
					fclose($pipes[2]);
					$status = proc_close($process);
					$check([$status, $stdout, $stderr], [0, $expected, ''], 'shell ' . $count);
					$check(file_exists($marker), false, 'execution sentinel');
					$count++;
				}
			}
		}
	}
} finally {
	if (file_exists($marker)) {
		unlink($marker);
	}
	rmdir($directory);

	if ($coverage !== false && $coverage !== '') {
		file_put_contents($coverage, json_encode(xdebug_get_code_coverage(), JSON_THROW_ON_ERROR));
		xdebug_stop_code_coverage();
	}
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
