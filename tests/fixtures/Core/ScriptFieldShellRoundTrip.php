<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('CACTI_SERVER_OS', 'unix');
$config = ['cacti_server_os' => 'unix'];
require_once dirname(__DIR__, 3) . '/lib/functions.php';

$values = ['hello world', '$(printf unexpected)', 'back`printf unexpected`tick', '"quoted"', "single'quote", 'dollar$HOME'];
$count  = 0;

foreach ($values as $value) {
	foreach (['', "'", '"'] as $quote) {
		for ($slashes = 0; $slashes <= 4; $slashes++) {
			$prefix   = str_repeat('\\', $slashes);
			$template = $quote . 'prefix' . $prefix . '<field>suffix' . $quote;
			$argument = substitute_script_path($template, ['field' => cacti_escapeshellarg($value)]);
			$expected = 'prefix' . str_repeat('\\', $quote === "'" ? $slashes : intdiv($slashes, 2)) . $value . 'suffix';
			$pipes    = [];
			$process  = proc_open('printf %s ' . $argument, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

			if (!is_resource($process)) {
				throw new RuntimeException('Unable to start shell round-trip');
			}

			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$status = proc_close($process);

			if ($status !== 0 || $stderr !== '' || $stdout !== $expected) {
				throw new RuntimeException('Shell round-trip failed at case ' . $count);
			}

			$count++;
		}
	}
}

// Templates that place the field inside a command substitution or backticks: the
// surrounding $()/`` re-parses with its own fresh quoting context, so the scanner
// must not leave the already-escaped value re-interpretable there. Each case must
// echo the field value back verbatim with nothing inside it executing. Backtick
// templates are not paired with backtick-bearing values (backticks do not nest).
$nestedCases = [
	['"$(printf %s <field>)"', 'hello world',                 'hello world'],
	['"$(printf %s <field>)"', '$(printf unexpected)',         '$(printf unexpected)'],
	['"$(printf %s <field>)"', 'back`printf unexpected`tick',  'back`printf unexpected`tick'],
	['"$(printf %s <field>)"', 'dollar$HOME',                  'dollar$HOME'],
	['"`printf %s <field>`"',  'hello world',                  'hello world'],
	['"`printf %s <field>`"',  '$(printf unexpected)',         '$(printf unexpected)'],
	['"`printf %s <field>`"',  'dollar$HOME',                  'dollar$HOME'],
];

foreach ($nestedCases as $case) {
	[$template, $value, $expected] = $case;

	$argument = substitute_script_path($template, ['field' => cacti_escapeshellarg($value)]);
	$pipes    = [];
	$process  = proc_open('printf %s ' . $argument, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start shell round-trip');
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== 0 || $stderr !== '' || $stdout !== $expected) {
		throw new RuntimeException('Nested shell round-trip failed at case ' . $count);
	}

	$count++;
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
