<?php

declare(strict_types=1);

namespace Cacti\Tests\Installer\Application;

use Cacti\Installer\Application\InstallCoordinator;
use Cacti\Installer\Application\Port\InstallationRepository;
use Cacti\Installer\Application\Port\InstallationTaskRunner;
use Cacti\Installer\Application\TaskExecutionResult;
use Cacti\Installer\Domain\InstallTask;
use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallationMode;
use Cacti\Installer\Domain\InstallationStage;
use PHPUnit\Framework\TestCase;

final class InstallCoordinatorTest extends TestCase {
    public function testItRunsTheConfirmedPlanAndCompletesTheAggregate(): void {
        $installation = $this->confirmedInstallation();
        $repository = new InMemoryInstallationRepository($installation);
        $runner = new RecordingTaskRunner();

        (new InstallCoordinator($repository, $runner))->run($installation->id());

        self::assertSame(InstallationStage::Completed, $repository->get($installation->id())->stage());
        self::assertSame(
            ['create-csrf-secret', 'convert-selected-tables', 'apply-database-migrations', 'import-templates', 'synchronise-collectors', 'record-version'],
            array_map(static fn (InstallTask $task): string => $task->value, $runner->ran)
        );
    }

    public function testItMarksTheAggregateFailedAndStopsAtTheFirstFailedTask(): void {
        $installation = $this->confirmedInstallation();
        $repository = new InMemoryInstallationRepository($installation);
        $runner = new RecordingTaskRunner(InstallTask::ApplyDatabaseMigrations);

        $result = (new InstallCoordinator($repository, $runner))->run($installation->id());

        self::assertFalse($result->successful);
        self::assertSame(InstallationStage::Failed, $repository->get($installation->id())->stage());
        self::assertSame(
            ['create-csrf-secret', 'convert-selected-tables', 'apply-database-migrations'],
            array_map(static fn (InstallTask $task): string => $task->value, $runner->ran)
        );
    }

    private function confirmedInstallation(): Installation {
        $installation = Installation::begin('upgrade-123');
        $installation->acceptLicense();
        $installation->chooseMode(InstallationMode::Upgrade);
        $installation->confirm();

        return $installation;
    }
}

final class InMemoryInstallationRepository implements InstallationRepository {
    /** @var array<string, Installation> */
    private array $installations = [];

    public function __construct(Installation $installation) {
        $this->save($installation);
    }

    public function get(string $id): Installation {
        return $this->installations[$id];
    }

    public function save(Installation $installation): void {
        $this->installations[$installation->id()] = $installation;
    }
}

final class RecordingTaskRunner implements InstallationTaskRunner {
    /** @var list<InstallTask> */
    public array $ran = [];

    public function __construct(private readonly ?InstallTask $failingTask = null) {
    }

    public function run(InstallTask $task, Installation $installation): TaskExecutionResult {
        $this->ran[] = $task;

        return $task === $this->failingTask
            ? TaskExecutionResult::failed('expected failure')
            : TaskExecutionResult::succeeded();
    }
}
