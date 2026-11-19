<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use Symfony\Component\Process\Process;

test('rra path migration skips a stored path outside the RRA directory', function (): void {
	$process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/Rrd/RraContainmentSkip.php']);
	$process->setTimeout(30);
	$process->run();
	expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput());
	expect($process->getOutput())->toContain('PASS: RRA-containment guard skips');
});
