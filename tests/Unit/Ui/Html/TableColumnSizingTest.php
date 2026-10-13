<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

test('table width identity survives transient actions in every box rendering mode', function (bool $div, string $title) {
	foreach ([[] , ['action' => 'go'], ['tab' => 'devices', 'action' => 'change'], ['report' => 'summary', 'action' => 'reset'], ['action' => 'edit']] as $request) {
		$pipes = [];
		$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/TableColumnSizing.php', json_encode($request, JSON_THROW_ON_ERROR), $div ? 'div' : 'table', $title], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		expect(is_resource($process))->toBeTrue();
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
		$html = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['html'];
		$view = $request['tab'] ?? $request['report'] ?? (($request['action'] ?? '') === 'edit' ? 'edit' : '');
		$key = 'monitor' . ($view === '' ? '' : '_' . $view) . '1_child';
		expect($html)->toContain("data-colsizing='$key'");
	}
})->with([[false, ''], [true, ''], [false, 'Title'], [true, 'Title']]);
