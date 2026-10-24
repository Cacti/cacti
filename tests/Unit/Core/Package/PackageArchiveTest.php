<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use Symfony\Component\Process\Process;

test('package archives use their supplied metadata independently of request fields', function (): void {
	$process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/PackageArchive.php']);
	$process->setTimeout(30);
	$process->run();
	expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput());
	expect($process->getOutput())->toBe("PASS: package archive without request fields and with unrelated request fields\n");
});
