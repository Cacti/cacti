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
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once(__DIR__ . '/CactiProcessResult.php');
require_once(__DIR__ . '/CactiProcessException.php');

/*
 * Single entry point for spawning external commands from Cacti PHP code.
 *
 * argv is always passed as an array, so shell metacharacters in arguments are
 * literal: there is no /bin/sh between us and execve(). The environment is
 * scrubbed to a small allowlist by default; callers that need more must opt in
 * explicitly via $opts['env'].
 *
 * This wrapper does not replace input validation. Argument values that are
 * later interpreted by downstream tooling (rrdtool DEF lines, SQL fragments
 * embedded in --filter, etc.) still need their own escaping at the point of
 * construction.
 */
class CactiProcess {
	/*
	 * Run a command to completion and return a CactiProcessResult.
	 *
	 * Throws CactiProcessException on timeout or on an exit code that is not
	 * in $opts['expected_exit_codes'] (default [0]).
	 */
	public static function run(array $argv, array $opts = []): CactiProcessResult {
		if (count($argv) === 0) {
			throw new CactiProcessException('CactiProcess::run requires a non-empty argv array');
		}

		[$timeout, $env, $cwd, $stdin, $expected] = self::normalizeOptions($opts);

		$process = new \Symfony\Component\Process\Process($argv, $cwd, $env, $stdin, $timeout);

		try {
			$exit = $process->run();
		} catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
			throw new CactiProcessException(
				sprintf('CactiProcess timed out after %ds: %s', (int) $timeout, self::summarizeArgv($argv)),
				0,
				$e
			);
		} catch (\Throwable $e) {
			throw new CactiProcessException(
				sprintf('CactiProcess failed to launch: %s (%s)', self::summarizeArgv($argv), $e->getMessage()),
				0,
				$e
			);
		}

		if (!in_array($exit, $expected, true)) {
			$stderr = trim($process->getErrorOutput());

			throw new CactiProcessException(sprintf(
				'CactiProcess exited %d (expected %s) for: %s%s',
				$exit,
				implode(',', $expected),
				self::summarizeArgv($argv),
				$stderr !== '' ? ' stderr=' . $stderr : ''
			));
		}

		return new CactiProcessResult($exit, $process->getOutput(), $process->getErrorOutput());
	}

	/*
	 * Run a command and stream stdout lines through $onOutput as they arrive.
	 *
	 * The callback receives one logical line at a time (trailing newline
	 * stripped). Callers that need stderr should aggregate it themselves; the
	 * common Cacti use case is a producer that emits records on stdout while
	 * the parent watches counts/timing.
	 */
	public static function runStreaming(array $argv, array $opts, callable $onOutput, ?callable $onError = null): int {
		if (count($argv) === 0) {
			throw new CactiProcessException('CactiProcess::runStreaming requires a non-empty argv array');
		}

		[$timeout, $env, $cwd, $stdin, $expected] = self::normalizeOptions($opts);

		$process = new \Symfony\Component\Process\Process($argv, $cwd, $env, $stdin, $timeout);

		$buffer     = '';
		$err_buffer = '';

		try {
			$exit = $process->run(static function ($type, $data) use (&$buffer, &$err_buffer, $onOutput, $onError) {
				if ($type === \Symfony\Component\Process\Process::ERR) {
					$err_buffer .= $data;

					if ($onError !== null) {
						$onError($data);
					} elseif (defined('STDERR')) {
						// No dedicated handler: mirror to the parent's STDERR so
						// terminal users see child diagnostics, matching the prior
						// passthru() behaviour.
						fwrite(STDERR, $data);
					}

					return;
				}

				if ($type !== \Symfony\Component\Process\Process::OUT) {
					return;
				}

				$buffer .= $data;

				// Emit complete lines, leave the partial tail in the buffer.
				while (($nl = strpos($buffer, "\n")) !== false) {
					$line   = substr($buffer, 0, $nl);
					$buffer = substr($buffer, $nl + 1);
					$onOutput($line);
				}
			});
		} catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
			throw new CactiProcessException(
				sprintf('CactiProcess timed out after %ds: %s', (int) $timeout, self::summarizeArgv($argv)),
				0,
				$e
			);
		} catch (\Throwable $e) {
			throw new CactiProcessException(
				sprintf('CactiProcess failed to launch: %s (%s)', self::summarizeArgv($argv), $e->getMessage()),
				0,
				$e
			);
		}

		if ($buffer !== '') {
			$onOutput($buffer);
		}

		if (!in_array($exit, $expected, true)) {
			throw new CactiProcessException(sprintf(
				'CactiProcess exited %d (expected %s) for: %s%s',
				$exit,
				implode(',', $expected),
				self::summarizeArgv($argv),
				$err_buffer !== '' ? ' stderr=' . trim($err_buffer) : ''
			));
		}

		return $exit;
	}

	/*
	 * Resolve and validate $opts. Returns the tuple consumed by both run
	 * variants so the parsing logic stays in one place.
	 */
	private static function normalizeOptions(array $opts): array {
		$timeout = array_key_exists('timeout', $opts) ? $opts['timeout'] : 60;

		if ($timeout !== null && !is_int($timeout) && !is_float($timeout)) {
			throw new CactiProcessException('CactiProcess timeout must be int|float|null');
		}

		if ($timeout !== null && $timeout < 0) {
			throw new CactiProcessException('CactiProcess timeout must be non-negative');
		}

		// Cast to float so Symfony Process always receives a stable type regardless
		// of whether the caller passed an int literal or a float.
		$timeout = $timeout !== null ? (float) $timeout : null;

		$env_opt = $opts['env'] ?? [];

		if (!is_array($env_opt)) {
			throw new CactiProcessException('CactiProcess env must be an array of strings');
		}

		foreach ($env_opt as $key => $name) {
			if (!is_int($key) || !is_string($name)) {
				throw new CactiProcessException('CactiProcess env must be an array of strings');
			}
		}

		$env = self::resolveEnv($env_opt);

		$cwd = $opts['cwd'] ?? null;

		if ($cwd !== null && !is_string($cwd)) {
			throw new CactiProcessException('CactiProcess cwd must be a string or null');
		}

		$stdin = $opts['stdin'] ?? null;

		if ($stdin !== null && !is_string($stdin)) {
			throw new CactiProcessException('CactiProcess stdin must be a string or null');
		}

		$expected = $opts['expected_exit_codes'] ?? [0];

		if (!is_array($expected) || count($expected) === 0) {
			throw new CactiProcessException('CactiProcess expected_exit_codes must be a non-empty array');
		}

		foreach ($expected as $code) {
			if (!is_int($code)) {
				throw new CactiProcessException('CactiProcess expected_exit_codes must contain integers');
			}
		}

		return [$timeout, $env, $cwd, $stdin, $expected];
	}

	/*
	 * Build the env array passed to Process. An empty list of names means the
	 * minimal baseline (PATH/HOME/LANG/TZ): enough for rrdtool, snmpwalk, and
	 * php-binary callouts to find their tools and resolve user paths without
	 * leaking arbitrary parent-process state.
	 *
	 * On Windows the baseline also includes SYSTEMROOT, COMSPEC, and PATHEXT
	 * because PHP refuses to start without SYSTEMROOT and proc_open() relies
	 * on COMSPEC/PATHEXT to resolve executables under cmd.exe.
	 *
	 * Callers that legitimately need more (SNMP_PERSISTENT_DIR, SSH_AUTH_SOCK,
	 * a plugin-specific variable) pass that name in $opts['env']; only those
	 * names plus the baseline are forwarded.
	 */
	private static function resolveEnv(array $names): array {
		$allowed = ['PATH', 'HOME', 'LANG', 'TZ'];

		if (PHP_OS_FAMILY === 'Windows') {
			$allowed = array_merge($allowed, ['SYSTEMROOT', 'COMSPEC', 'PATHEXT', 'WINDIR']);
		}

		foreach ($names as $name) {
			if ($name !== '' && !in_array($name, $allowed, true)) {
				$allowed[] = $name;
			}
		}

		/* Symfony Process merges $this->env on top of the parent env it
		 * inherits via getDefaultEnv(). To scrub, we enumerate every
		 * parent-visible env var and explicitly set it to false (which
		 * Symfony / proc_open interpret as "remove this var from the
		 * child"); then we add the allowlisted vars back with their
		 * current values. The child sees only the allowed set. */
		$env = [];

		foreach (getenv() as $name => $_value) {
			if ($name === '') {
				continue;
			}

			if (!in_array($name, $allowed, true)) {
				$env[$name] = false;
			}
		}

		foreach ($allowed as $name) {
			$value = getenv($name);

			if ($value !== false) {
				$env[$name] = $value;
			}
		}

		return $env;
	}

	private static function summarizeArgv(array $argv): string {
		// Keep the message short; the full argv may contain credentials in some
		// callers (snmp community strings, etc.) so we log only argv[0] plus a
		// count, not the values.
		$head  = (string) ($argv[0] ?? '');
		$count = count($argv);

		return $count > 1 ? sprintf('%s (+%d args)', $head, $count - 1) : $head;
	}
}
