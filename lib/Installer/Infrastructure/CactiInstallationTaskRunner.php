<?php

declare(strict_types=1);

namespace Cacti\Installer\Infrastructure;

use Cacti\Installer\Application\Port\InstallationTaskRunner;
use Cacti\Installer\Application\TaskExecutionResult;
use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallTask;
use Throwable;

/**
 * Anti-corruption layer that translates domain tasks into Cacti operations.
 *
 * Cacti exceptions do not escape into the application service. They are
 * converted into the application's explicit task-result protocol instead.
 */
final class CactiInstallationTaskRunner implements InstallationTaskRunner {
	public function __construct(
		private readonly CactiLegacyInstallOperations $operations,
		private readonly OperatingSystem $operatingSystem,
	) {
	}

	public function run(InstallTask $task, Installation $installation): TaskExecutionResult {
		try {
			match ($task) {
				InstallTask::CreateCsrfSecret => $this->operations->createCsrfSecret(),
				InstallTask::ConvertSelectedTables => $this->operations->convertSelectedTables(),
				InstallTask::ApplyDatabaseMigrations => $this->operations->upgradeDatabase(),
				InstallTask::ImportTemplates => $this->operations->importTemplates($installation->templates()?->all() ?? []),
				InstallTask::ProvisionServer => $this->operations->provisionServer($this->operatingSystem),
				InstallTask::ProvisionPoller => $this->operations->provisionPoller(),
				InstallTask::SynchroniseCollectors => $this->operations->synchroniseCollectors(),
				InstallTask::RecordVersion => $this->operations->recordVersion(),
			};

			return TaskExecutionResult::succeeded();
		} catch (Throwable $exception) {
			return TaskExecutionResult::failed($exception->getMessage());
		}
	}
}
