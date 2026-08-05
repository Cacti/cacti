<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs a probe script in a child PHP process and returns its JSON verdict.
 *
 * A test that has to fake a Cacti runtime function must declare a name that
 * lib/ already owns. PHP function declarations are process-global and cannot
 * be undone, so the fake and the real declaration cannot coexist: whichever
 * lands first wins, and the loser fatals with "Cannot redeclare". Because
 * PHPUnit loads every file of a suite into one process, that turns the suite
 * into a load-order lottery -- a new test file that requires lib/database.php
 * is enough to break an unrelated test that fakes db_execute_prepared().
 *
 * Probe scripts sidestep the whole problem by owning their own process. They
 * live under a fixtures/ directory so PHPUnit does not collect them, declare
 * their fakes freely, and print a JSON verdict the parent asserts against.
 */

if (!function_exists('cacti_test_isolated_probe')) {
	/**
	 * @param string   $script Absolute path to the probe script.
	 * @param string[] $args   Arguments appended to the child command line.
	 *
	 * @return array The decoded JSON verdict printed by the probe.
	 */
	function cacti_test_isolated_probe(string $script, array $args = []): array {
		// display_errors=stderr keeps PHP diagnostics (the GoogleAuthenticator
		// deprecations lib/auth.php emits on PHP 8.4, for one) out of the JSON
		// the probe writes to stdout.
		// argv form, so the child is executed directly and no shell parses
		// the scenario payload
		$argv = [PHP_BINARY, '-d', 'display_errors=stderr', $script];

		foreach ($args as $arg) {
			$argv[] = (string) $arg;
		}

		$pipes   = [];
		$process = proc_open(
			$argv,
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes
		);

		if (!is_resource($process)) {
			throw new RuntimeException('could not spawn ' . basename($script));
		}

		$stdout = (string) stream_get_contents($pipes[1]);
		$stderr = (string) stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		$status = proc_close($process);

		if ($status !== 0) {
			throw new RuntimeException(
				basename($script) . ' exited ' . $status . ":\n" . trim($stderr)
			);
		}

		$verdict = json_decode(trim($stdout), true);

		if (!is_array($verdict)) {
			throw new RuntimeException(
				basename($script) . " printed no JSON verdict:\n"
				. trim($stdout . "\n" . $stderr)
			);
		}

		return $verdict;
	}
}
