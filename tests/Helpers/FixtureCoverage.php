<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

$fixtureCoveragePath = getenv('CACTI_COVERAGE_FILE');

if ($fixtureCoveragePath !== false && $fixtureCoveragePath !== '') {
	if (!function_exists('xdebug_start_code_coverage')) {
		throw new RuntimeException('Xdebug coverage required');
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
	register_shutdown_function(static function () use ($fixtureCoveragePath) : void {
		$data = xdebug_get_code_coverage();

		foreach ($GLOBALS['cacti_fixture_source_map'] ?? [] as $temporary => $original) {
			if (isset($data[$temporary])) {
				$existing = $data[$original] ?? ['lines' => [], 'functions' => []];

				foreach ($data[$temporary]['lines'] as $line => $hits) {
					$existing['lines'][$line] = max($hits, $existing['lines'][$line] ?? -2);
				}
				$existing['functions'] = array_merge($existing['functions'], $data[$temporary]['functions'] ?? []);
				$data[$original]       = $existing;
				unset($data[$temporary]);
			}
		}
		file_put_contents($fixtureCoveragePath, json_encode($data, JSON_THROW_ON_ERROR));
	});
}
