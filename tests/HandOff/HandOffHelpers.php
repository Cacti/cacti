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
 * Shared helpers for tests/HandOff/<Feature>HandOffTest.php files.
 *
 * Hand-off tests assert behaviour at boundaries the unit tests cannot reach:
 * a real filesystem path, a real ZIP entry, a real cacti_log() call site.
 * The helpers here build those fixtures and capture side effects so the
 * per-feature test files stay focused on the assertion they care about.
 *
 * Re-including this file is safe; every declaration is guarded so the
 * second include in a test run is a no-op.
 */

if (!function_exists('cacti_handoff_stub_cacti_log')) {

	/*
	 * Register a cacti_log() shim that records every call into a static
	 * buffer. Returns the buffer by reference so the caller can both clear
	 * it and assert on it without re-reaching into the function.
	 *
	 * Tests that load real Cacti code which already declares cacti_log()
	 * should call cacti_handoff_clear_log_buffer() instead and read the
	 * buffer directly via cacti_handoff_get_log_buffer().
	 */
	function &cacti_handoff_stub_cacti_log(): array {
		if (!function_exists('cacti_log')) {
			function cacti_log(string $string, bool $output = false, string $environ = 'CMDPHP', int $level = 0): bool {
				cacti_handoff_record_log_line($string, $environ, $level);
				return true;
			}
		}

		return cacti_handoff_log_buffer_ref();
	}

	/*
	 * Internal sink used by the eval'd cacti_log shim. Kept separate so
	 * the stored shape stays a single hashmap per call regardless of how
	 * cacti_log was invoked.
	 */
	function cacti_handoff_record_log_line(string $string, string $environ = 'CMDPHP', int $level = 0): void {
		$buffer = &cacti_handoff_log_buffer_ref();
		$buffer[] = array(
			'string'  => $string,
			'environ' => $environ,
			'level'   => $level,
		);
	}

	/*
	 * Single static buffer shared by the stub, the getter, and the
	 * clearer. A by-reference accessor avoids duplicating the static.
	 */
	function &cacti_handoff_log_buffer_ref(): array {
		static $buffer = array();
		return $buffer;
	}

	function cacti_handoff_get_log_buffer(): array {
		return cacti_handoff_log_buffer_ref();
	}

	function cacti_handoff_clear_log_buffer(): void {
		$buffer = &cacti_handoff_log_buffer_ref();
		$buffer = array();
	}

	/*
	 * Write $contents to a unique temp path with the given extension,
	 * register a shutdown cleanup, and return the path. The shutdown
	 * handler tolerates files already removed by the test itself.
	 */
	function cacti_handoff_temp_file(string $contents, string $extension = '.tmp'): string {
		$base = tempnam(sys_get_temp_dir(), 'cacti_handoff_');
		if ($base === false) {
			throw new RuntimeException('cacti_handoff_temp_file: tempnam failed');
		}

		$path = $base . $extension;
		if (rename($base, $path) === false) {
			@unlink($base);
			throw new RuntimeException('cacti_handoff_temp_file: rename failed');
		}

		if (file_put_contents($path, $contents) === false) {
			@unlink($path);
			throw new RuntimeException('cacti_handoff_temp_file: write failed');
		}

		register_shutdown_function(static function () use ($path) {
			if (is_file($path)) {
				@unlink($path);
			}
		});

		return $path;
	}

	/*
	 * Build a minimal valid ZIP archive containing the supplied
	 * path => content entries and return the temp path. Uses ext-zip
	 * which Cacti already requires; no shell-out, no third-party lib.
	 */
	function cacti_handoff_make_zip(array $entries): string {
		if (!class_exists('ZipArchive')) {
			throw new RuntimeException('cacti_handoff_make_zip: ext-zip not loaded');
		}

		$path = cacti_handoff_temp_file('', '.zip');

		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException('cacti_handoff_make_zip: open failed');
		}

		foreach ($entries as $name => $contents) {
			if ($zip->addFromString((string) $name, (string) $contents) === false) {
				$zip->close();
				throw new RuntimeException('cacti_handoff_make_zip: failed to add ZIP entry "' . (string) $name . '"');
			}
		}

		$zip->close();

		return $path;
	}
}
