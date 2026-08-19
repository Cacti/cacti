<?php

declare(strict_types=1);

namespace Cacti\Tests\Installer\Infrastructure;

use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallationMode;
use Cacti\Installer\Domain\InstallTask;
use Cacti\Installer\Domain\TemplateSelection;
use Cacti\Installer\Infrastructure\CactiInstallationTaskRunner;
use Cacti\Installer\Infrastructure\CactiLegacyInstallOperations;
use Cacti\Installer\Infrastructure\OperatingSystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CactiInstallationTaskRunnerTest extends TestCase {
	public function testItTranslatesDomainTasksIntoCactiOperations(): void {
		$operations = new RecordingCactiOperations();
		$installation = $this->installation();
		$runner = new CactiInstallationTaskRunner($operations, OperatingSystem::FreeBsd);

		$result = $runner->run(InstallTask::ImportTemplates, $installation);

		self::assertTrue($result->successful);
		self::assertSame([['importTemplates', ['base.xml.gz']]], $operations->calls);
	}

	public function testItDoesNotLeakLegacyExceptionsAcrossTheApplicationBoundary(): void {
		$operations = new RecordingCactiOperations(failOn: 'upgradeDatabase');
		$runner = new CactiInstallationTaskRunner($operations, OperatingSystem::Linux);

		$result = $runner->run(InstallTask::ApplyDatabaseMigrations, $this->installation());

		self::assertFalse($result->successful);
		self::assertSame('legacy upgrade failure', $result->failure);
	}

	private function installation(): Installation {
		$installation = Installation::begin('new-install');
		$installation->acceptLicense();
		$installation->chooseMode(InstallationMode::NewInstall);
		$installation->selectTemplates(new TemplateSelection(['base.xml.gz']));
		$installation->confirm();

		return $installation;
	}
}

final class RecordingCactiOperations implements CactiLegacyInstallOperations {
	/** @var list<array{string, list<string>}> */
	public array $calls = [];

	public function __construct(private readonly ?string $failOn = null) {
	}

	public function createCsrfSecret(): void {
		$this->record('createCsrfSecret');
	}

	public function convertSelectedTables(): void {
		$this->record('convertSelectedTables');
	}

	public function upgradeDatabase(): void {
		$this->record('upgradeDatabase');
	}

	/** @param list<string> $templates */
	public function importTemplates(array $templates): void {
		$this->record('importTemplates', $templates);
	}

	public function provisionServer(OperatingSystem $operatingSystem): void {
		$this->record('provisionServer', [$operatingSystem->value]);
	}

	public function provisionPoller(): void {
		$this->record('provisionPoller');
	}

	public function synchroniseCollectors(): void {
		$this->record('synchroniseCollectors');
	}

	public function recordVersion(): void {
		$this->record('recordVersion');
	}

	/** @param list<string> $arguments */
	private function record(string $operation, array $arguments = []): void {
		if ($operation === $this->failOn) {
			throw new RuntimeException('legacy upgrade failure');
		}

		$this->calls[] = [$operation, $arguments];
	}
}
