<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * cacti_process_run() and both of its backends. Runs real child processes;
 * the unit bootstrap loads lib/process.php through include/global.php.
 */

require_once CACTI_PATH_LIBRARY . '/process.php';

// A payload that would create a file, run id, or chain commands if any shell
// ever parsed it. The marker path is unique per run so a stale file from an
// earlier run cannot mask or fake the result.
$marker  = sys_get_temp_dir() . '/cacti-process-run-' . bin2hex(random_bytes(4));
$payload = '$(touch ' . $marker . '); `id`; a&&b || c; x|y > /dev/null; *';

test('Symfony Process is available, so cacti_process_run() uses it', function () {
	expect(class_exists(Symfony\Component\Process\Process::class))->toBeTrue();

	$r = cacti_process_run(['printf', '%s', 'hello']);

	expect($r['stdout'])->toBe('hello')
		->and($r['exit_code'])->toBe(0)
		->and($r['via'])->toBe('symfony')
		->and($r['timed_out'])->toBeFalse();
});

test('cacti_process_run passes shell metacharacters through as one literal argument', function () use ($marker, $payload) {
	$r = cacti_process_run(['printf', '%s', $payload]);

	expect($r['stdout'])->toBe($payload)
		->and(file_exists($marker))->toBeFalse();
});

test('cacti_process_run keeps each argv entry as a separate argument', function () {
	expect(cacti_process_run(['printf', '%s', 'a; id'])['stdout'])->toBe('a; id')
		->and(cacti_process_run(['printf', '[%s]', 'one two', 'three'])['stdout'])->toBe('[one two][three]')
		->and(cacti_process_run(['printf', '[%s]', 7])['stdout'])->toBe('[7]');
});

test('cacti_process_run pipes stdin and reports exit code and stderr', function () {
	$r = cacti_process_run(['cat'], "line1\nline2\n");

	expect($r['stdout'])->toBe("line1\nline2\n");

	$r = cacti_process_run(['sh', '-c', 'echo oops >&2; exit 3']);

	expect($r['exit_code'])->toBe(3)
		->and(trim($r['stderr']))->toBe('oops');
});

test('cacti_process_run rejects an empty or non-string argv without spawning', function () {
	$empty = cacti_process_run([]);
	$bad   = cacti_process_run(['printf', ['nested']]);

	expect($empty['exit_code'])->toBe(-1)
		->and($empty['via'])->toBe('none')
		->and($bad['exit_code'])->toBe(-1)
		->and($bad['via'])->toBe('none')
		->and($bad['stderr'])->toContain('argument 1');
});

test('cacti_process_run reports a missing binary and flags a timeout', function () {
	expect(cacti_process_run(['this-binary-does-not-exist-cacti'])['exit_code'])->not->toBe(0);

	$r = cacti_process_run(['sleep', '5'], null, 1);

	expect($r['timed_out'])->toBeTrue()
		->and($r['exit_code'])->toBe(-1);
});

test('the proc_open fallback keeps the same no-shell contract', function () use ($marker, $payload) {
	$r = cacti_process_run_proc_open(['printf', '%s', $payload], null, 5, null, null);

	expect($r['via'])->toBe('proc_open')
		->and($r['stdout'])->toBe($payload)
		->and($r['exit_code'])->toBe(0)
		->and($r['timed_out'])->toBeFalse()
		->and(file_exists($marker))->toBeFalse();
});

test('the proc_open fallback pipes stdin and captures stderr and exit code', function () {
	$r = cacti_process_run_proc_open(['cat'], "in\n", 5, null, null);

	expect($r['stdout'])->toBe("in\n");

	$r = cacti_process_run_proc_open(['sh', '-c', 'echo err >&2; exit 4'], null, 5, null, null);

	expect($r['exit_code'])->toBe(4)
		->and(trim($r['stderr']))->toBe('err');
});

test('the proc_open fallback kills the child on timeout', function () {
	$start = microtime(true);
	$r     = cacti_process_run_proc_open(['sleep', '5'], null, 1, null, null);

	expect($r['timed_out'])->toBeTrue()
		->and($r['exit_code'])->toBe(-1)
		->and(microtime(true) - $start)->toBeLessThan(4.0);
});

test('the proc_open fallback timeout covers a child that never reads its stdin', function () {
	$start = microtime(true);
	$r     = cacti_process_run_proc_open(['sleep', '30'], str_repeat('x', 1 << 20), 1, null, null);

	expect($r['timed_out'])->toBeTrue()
		->and($r['exit_code'])->toBe(-1)
		->and(microtime(true) - $start)->toBeLessThan(4.0);
});

test('the proc_open fallback delivers stdin larger than the pipe buffer in full', function () {
	$r = cacti_process_run_proc_open(['wc', '-c'], str_repeat('x', 1 << 20), 10, null, null);

	expect($r['timed_out'])->toBeFalse()
		->and($r['exit_code'])->toBe(0)
		->and(trim($r['stdout']))->toBe('1048576');
});

test('the proc_open fallback tolerates a child that exits without reading its stdin', function () {
	$r = cacti_process_run_proc_open(['sh', '-c', 'exit 3'], str_repeat('x', 1 << 20), 10, null, null);

	expect($r['timed_out'])->toBeFalse()
		->and($r['exit_code'])->toBe(3);
});

test('the proc_open fallback preserves binary stdin while draining both output pipes', function () {
	$input = str_repeat(implode('', array_map(chr(...), range(0, 255))), 8192) . 'tail';
	$child = <<<'PHP'
$hash = hash_init('sha256');
while (!feof(STDIN)) {
    $chunk = fread(STDIN, 4093);
    hash_update($hash, $chunk);
    fwrite(STDOUT, '.');
    fwrite(STDERR, str_repeat('e', 1024));
}
fwrite(STDOUT, hash_final($hash));
PHP;
	$r = cacti_process_run_proc_open([PHP_BINARY, '-r', $child], $input, 10, null, null);

	expect($r['timed_out'])->toBeFalse()
		->and($r['exit_code'])->toBe(0)
		->and($r['stdout'])->toEndWith(hash('sha256', $input))
		->and(strlen($r['stderr']))->toBeGreaterThan(65536);
});

test('the Symfony backend reports a process that cannot start without throwing', function () {
	$r = cacti_process_run_symfony(['printf', 'x'], null, 5, '/nonexistent-cacti-working-directory', null);

	expect($r['via'])->toBe('symfony')
		->and($r['exit_code'])->toBe(-1)
		->and($r['timed_out'])->toBeFalse()
		->and($r['stdout'])->toBe('')
		->and($r['stderr'])->not->toBe('');
});

test('the proc_open fallback reports a process that cannot be spawned', function () {
	// proc_open() warns before it returns false; the contract under test is the array.
	set_error_handler(static fn (): bool => true);

	try {
		$r = cacti_process_run_proc_open(['printf', 'x'], null, 5, '/nonexistent-cacti-working-directory', null);
	} finally {
		restore_error_handler();
	}

	expect($r['via'])->toBe('proc_open')
		->and($r['exit_code'])->toBe(-1)
		->and($r['stderr'])->toContain('unable to spawn printf');
});
