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

use Symfony\Component\Process\Process;

/**
 * cacti_process_run - run an external command from an argument vector without a
 * command shell, draining the OS command-injection class (CWE-78 / CWE-88).
 *
 * The command is supplied as an already-split argument vector, never as a
 * single shell string, so shell metacharacters in any argument ($, ;, |, &&,
 * backticks, globs, newlines, ...) are passed through verbatim to the target
 * program as literal argv entries and are never interpreted by a shell. No
 * caller-side escaping (cacti_escapeshellarg()/cacti_escapeshellcmd()) is
 * required or wanted; escaping an argv entry would corrupt the literal value.
 *
 * Backed by Symfony\Component\Process\Process when it is available (it is a
 * hard dependency of Cacti via composer). If the vendor tree is missing or
 * partial on a running site, it degrades gracefully to a native proc_open()
 * call that still uses the array form, so the no-shell guarantee is preserved
 * on both the primary and the fallback path. The helper never builds a shell
 * string and never calls exec()/shell_exec()/passthru().
 *
 * @param array       $argv    Argument vector. $argv[0] is the program (a bare
 *                             name is resolved against PATH; an absolute path
 *                             is used as-is). Remaining entries are literal
 *                             arguments. Every entry must be a string or
 *                             Stringable scalar.
 * @param string|null $input   Optional data written to the child's stdin and
 *                             then closed. Used for stdin-fed tools; null
 *                             leaves stdin empty.
 * @param int|null    $timeout Wall-clock timeout in seconds, or null to
 *                             disable. On timeout the child is terminated and
 *                             'timed_out' is set true.
 * @param string|null $cwd     Working directory for the child, or null to
 *                             inherit the current one.
 * @param array|null  $env     Additional environment for the child, or null to
 *                             inherit the current environment.
 *
 * @return array {
 *               'stdout'    => string Captured standard output.
 *               'stderr'    => string Captured standard error.
 *               'exit_code' => int    Process exit status (-1 if it could not be spawned).
 *               'timed_out' => bool   True when the command was killed on timeout.
 *               'via'       => string 'symfony' or 'proc_open', the backend that ran it.
 *               }
 */
function cacti_process_run(array $argv, ?string $input = null, ?int $timeout = 60, ?string $cwd = null, ?array $env = null): array {
	// Normalise the argument vector to a list of strings. A command with no
	// program name cannot run; report a spawn failure rather than guessing.
	$argv = array_values($argv);

	if (cacti_sizeof($argv) == 0) {
		return [
			'stdout'    => '',
			'stderr'    => 'cacti_process_run: empty argument vector',
			'exit_code' => -1,
			'timed_out' => false,
			'via'       => 'none',
		];
	}

	foreach ($argv as $i => $arg) {
		if (is_string($arg)) {
			continue;
		}

		if (is_scalar($arg) || $arg instanceof Stringable) {
			$argv[$i] = (string) $arg;

			continue;
		}

		return [
			'stdout'    => '',
			'stderr'    => 'cacti_process_run: argument ' . $i . ' is not a string',
			'exit_code' => -1,
			'timed_out' => false,
			'via'       => 'none',
		];
	}

	/** @var list<string> $argv every element was checked or cast to a string above */

	// Primary path: Symfony Process with an explicit argv. class_exists() is
	// probed at call time (not include time) so a site whose vendor tree is not
	// yet autoloadable cannot be fataled by merely loading this file.
	if (class_exists(Process::class)) {
		return cacti_process_run_symfony($argv, $input, $timeout, $cwd, $env);
	}

	// Graceful degradation: native proc_open() in list form. This still avoids
	// the shell (execvp on POSIX, bypass_shell on Windows), so the security
	// property holds even without the Symfony component.
	return cacti_process_run_proc_open($argv, $input, $timeout, $cwd, $env);
}

/**
 * cacti_process_run_symfony - cacti_process_run() backend using Symfony Process.
 *
 * @param list<string> $argv    Normalised list of string arguments.
 * @param string|null  $input   stdin payload or null.
 * @param int|null     $timeout Timeout in seconds or null.
 * @param string|null  $cwd     Working directory or null.
 * @param array|null   $env     Extra environment or null.
 *
 * @return array See cacti_process_run().
 */
function cacti_process_run_symfony(array $argv, ?string $input, ?int $timeout, ?string $cwd, ?array $env): array {
	$process = new Process($argv, $cwd, $env, $input, $timeout);

	$timed_out = false;

	try {
		$exit_code = $process->run();
	} catch (Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
		$timed_out = true;
		$exit_code = -1;
	} catch (Symfony\Component\Process\Exception\RuntimeException $e) {
		return [
			'stdout'    => '',
			'stderr'    => $e->getMessage(),
			'exit_code' => -1,
			'timed_out' => false,
			'via'       => 'symfony',
		];
	}

	return [
		'stdout'    => $process->getOutput(),
		'stderr'    => $process->getErrorOutput(),
		'exit_code' => $exit_code === null ? -1 : (int) $exit_code,
		'timed_out' => $timed_out,
		'via'       => 'symfony',
	];
}

/**
 * cacti_process_run_proc_open - cacti_process_run() fallback using proc_open()
 * in list form. Kept shell-free: proc_open() accepts an argv array and spawns
 * the program directly, so this preserves the no-shell guarantee.
 *
 * @param list<string> $argv    Normalised list of string arguments.
 * @param string|null  $input   stdin payload or null.
 * @param int|null     $timeout Timeout in seconds or null.
 * @param string|null  $cwd     Working directory or null.
 * @param array|null   $env     Extra environment or null.
 *
 * @return array See cacti_process_run().
 */
function cacti_process_run_proc_open(array $argv, ?string $input, ?int $timeout, ?string $cwd, ?array $env): array {
	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$pipes   = [];
	$process = proc_open($argv, $descriptors, $pipes, $cwd, $env, ['bypass_shell' => true]);

	if (!is_resource($process)) {
		return [
			'stdout'    => '',
			'stderr'    => 'cacti_process_run: unable to spawn ' . $argv[0],
			'exit_code' => -1,
			'timed_out' => false,
			'via'       => 'proc_open',
		];
	}

	// The deadline covers the whole exchange, including stdin.  A child that
	// stops reading would block a plain fwrite(), and one that fills its
	// output pipes while stdin is still being written would deadlock it, so
	// stdin is written in non-blocking pieces alongside the output reads.
	$timed_out = false;
	$deadline  = ($timeout === null) ? null : (microtime(true) + $timeout);
	$input     = $input ?? '';
	$length    = strlen($input);
	$offset    = 0;
	$stdin     = $pipes[0];

	if ($length === 0) {
		fclose($stdin);
		$stdin = null;
	} else {
		stream_set_blocking($stdin, false);
	}

	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);

	$stdout = '';
	$stderr = '';

	do {
		if ($stdin !== null) {
			$read   = [];
			$write  = [$stdin];
			$except = null;

			// stream_select() cannot wait on Windows pipes; write as before there
			$ready = stream_select($read, $write, $except, 0, 10000);

			if ($ready === false || $ready > 0) {
				// a child that exits without reading closes the pipe, and
				// that EPIPE notice must not reach the Cacti log
				set_error_handler(static fn (): bool => true);
				// Copy a bounded chunk, not the entire remaining payload on
				// every partial pipe write. Advance only by accepted bytes.
				$written = fwrite($stdin, substr($input, $offset, 65536));
				restore_error_handler();

				if ($written === false) {
					$offset = $length;
				} else {
					$offset += $written;
				}
			}

			if ($offset === $length) {
				fclose($stdin);
				$stdin = null;
			}
		}

		$status = proc_get_status($process);

		$stdout .= stream_get_contents($pipes[1]);
		$stderr .= stream_get_contents($pipes[2]);

		if (!$status['running']) {
			break;
		}

		if ($deadline !== null && microtime(true) >= $deadline) {
			$timed_out = true;
			proc_terminate($process, 9);

			break;
		}

		if ($stdin === null) {
			usleep(10000);
		}
	} while (true);

	if ($stdin !== null) {
		fclose($stdin);
	}

	// Drain anything buffered after the child exited or was terminated.
	$stdout .= stream_get_contents($pipes[1]);
	$stderr .= stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit_code = proc_close($process);

	if ($timed_out) {
		$exit_code = -1;
	}

	return [
		'stdout'    => $stdout,
		'stderr'    => $stderr,
		'exit_code' => (int) $exit_code,
		'timed_out' => $timed_out,
		'via'       => 'proc_open',
	];
}
