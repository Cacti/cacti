<?php

declare(strict_types=1);

namespace Cacti\Installer\Application\Port;

use Cacti\Installer\Application\TaskExecutionResult;
use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallTask;

interface InstallationTaskRunner {
	public function run(InstallTask $task, Installation $installation): TaskExecutionResult;
}
