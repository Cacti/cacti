<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Loaded only by the disposable E2E image when coverage is explicitly enabled.
$requestCoverageDirectory = getenv('CACTI_PR_RAW_COVERAGE');

if ($requestCoverageDirectory !== false && $requestCoverageDirectory !== '') {
	if (!function_exists('xdebug_start_code_coverage')) {
		throw new RuntimeException('Request coverage requires Xdebug.');
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	register_shutdown_function(static function () use ($requestCoverageDirectory) : void {
		$data = xdebug_get_code_coverage();

		foreach ($GLOBALS['cacti_fixture_source_map'] ?? [] as $temporary => $original) {
			foreach ($data[$temporary] ?? [] as $line => $hits) {
				$data[$original][$line] = max($hits, $data[$original][$line] ?? -2);
			}
			unset($data[$temporary]);
		}
		$root      = realpath(dirname(__DIR__, 2));
		$hashes    = [];
		$htmlSpans = [];

		foreach ($data as $file => $lines) {
			if (!str_starts_with($file, $root . '/') || str_contains($file, '/vendor/')) {
				unset($data[$file]);

				continue;
			}
			$hashes[$file]    = hash_file('sha256', $file);
			$htmlSpans[$file] = [];

			// Zend records a literal HTML output opcode at the end of its block.
			foreach (token_get_all(file_get_contents($file)) as $token) {
				if (is_array($token) && $token[0] === T_INLINE_HTML) {
					$htmlSpans[$file][] = [$token[2], $token[2] + substr_count($token[1], "\n")];
				}
			}
		}
		$path = $requestCoverageDirectory . '/' . getmypid() . '-' . bin2hex(random_bytes(8)) . '.json';

		if (file_put_contents($path, json_encode(['root' => $root, 'coverage' => $data, 'hashes' => $hashes, 'html_spans' => $htmlSpans], JSON_THROW_ON_ERROR)) === false) {
			throw new RuntimeException('Could not preserve request coverage.');
		}
	});
}
