<?php

declare(strict_types=1);

namespace Cacti\Tests\Installer\Domain;

use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallationMode;
use Cacti\Installer\Domain\InstallationStage;
use Cacti\Installer\Domain\TemplateSelection;
use Cacti\Installer\Application\LegacyInstallationFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

final class InstallationTest extends TestCase {
	public function testItBuildsAnOrderedNewInstallationPlanAfterConfirmation(): void {
		$installation = Installation::begin('install-123');
		$installation->acceptLicense();
		$installation->chooseMode(InstallationMode::NewInstall);
		$installation->selectTemplates(new TemplateSelection(['Local_Linux_Machine.xml.gz']));
		$installation->confirm();

		self::assertSame(InstallationStage::Confirmed, $installation->stage());
		self::assertSame(['Local_Linux_Machine.xml.gz'], $installation->templates()?->all());
		self::assertSame(
			['create-csrf-secret', 'convert-selected-tables', 'import-templates', 'provision-server', 'synchronise-collectors', 'record-version'],
			array_map(static fn ($task): string => $task->value, $installation->plan()->tasks())
		);
	}

	public function testItRefusesToConfirmBeforeTheLicenseIsAccepted(): void {
		$installation = Installation::begin('install-123');
		$installation->chooseMode(InstallationMode::NewInstall);

		$this->expectException(LogicException::class);
		$installation->confirm();
	}

	public function testAnUpgradePlanContainsMigrationsBeforeTemplateImports(): void {
		$installation = Installation::begin('upgrade-123');
		$installation->acceptLicense();
		$installation->chooseMode(InstallationMode::Upgrade);
		$installation->confirm();

		self::assertSame(
			['create-csrf-secret', 'convert-selected-tables', 'apply-database-migrations', 'import-templates', 'synchronise-collectors', 'record-version'],
			array_map(static fn ($task): string => $task->value, $installation->plan()->tasks())
		);
	}

	public function testTheLegacyModeContractIsTranslatedAtTheAntiCorruptionBoundary(): void {
		$installation = (new LegacyInstallationFactory())->create('legacy-123', 2, true);
		$installation->confirm();

		self::assertSame(
			['create-csrf-secret', 'convert-selected-tables', 'provision-poller', 'synchronise-collectors', 'record-version'],
			array_map(static fn ($task): string => $task->value, $installation->plan()->tasks())
		);
	}
}
