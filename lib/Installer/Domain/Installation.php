<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

use LogicException;

/**
 * Aggregate root for a single installer run.
 *
 * It owns workflow invariants only. SQL, files, process invocation and HTML
 * remain outside this class in application and infrastructure adapters.
 */
final class Installation {
	private InstallationStage $stage;
	private ?InstallationMode $mode = null;
	private bool $licenseAccepted = false;
	private ?TemplateSelection $templates = null;

	private function __construct(private readonly string $id) {
		if (trim($id) === '') {
			throw new LogicException('An installation requires an identifier.');
		}

		$this->stage = InstallationStage::Draft;
	}

	public static function begin(string $id): self {
		return new self($id);
	}

	public function id(): string {
		return $this->id;
	}

	public function stage(): InstallationStage {
		return $this->stage;
	}

	public function acceptLicense(): void {
		$this->assertNotTerminal();
		$this->licenseAccepted = true;
	}

	public function chooseMode(InstallationMode $mode): void {
		$this->assertNotRunning();
		$this->mode = $mode;
		$this->stage = InstallationStage::Configured;
	}

	public function selectTemplates(TemplateSelection $templates): void {
		$this->assertNotRunning();
		$this->templates = $templates;
	}

	public function confirm(): void {
		$this->assertNotTerminal();
		if (!$this->licenseAccepted) {
			throw new LogicException('The license must be accepted before confirmation.');
		}
		if ($this->mode === null) {
			throw new LogicException('An installation mode must be selected before confirmation.');
		}

		$this->stage = InstallationStage::Confirmed;
	}

	public function plan(): InstallationPlan {
		if ($this->stage !== InstallationStage::Confirmed && $this->stage !== InstallationStage::Running) {
			throw new LogicException('An installation plan is available only after confirmation.');
		}
		$mode = $this->mode;
		if ($mode === null) {
			throw new LogicException('An installation mode must be selected before planning.');
		}

		return new InstallationPlan(match ($mode) {
			InstallationMode::NewInstall => [
				InstallTask::CreateCsrfSecret,
				InstallTask::ConvertSelectedTables,
				InstallTask::ImportTemplates,
				InstallTask::ProvisionServer,
				InstallTask::SynchroniseCollectors,
				InstallTask::RecordVersion,
			],
			InstallationMode::Upgrade => [
				InstallTask::CreateCsrfSecret,
				InstallTask::ConvertSelectedTables,
				InstallTask::ApplyDatabaseMigrations,
				InstallTask::ImportTemplates,
				InstallTask::SynchroniseCollectors,
				InstallTask::RecordVersion,
			],
			InstallationMode::Poller => [
				InstallTask::CreateCsrfSecret,
				InstallTask::ConvertSelectedTables,
				InstallTask::ProvisionPoller,
				InstallTask::SynchroniseCollectors,
				InstallTask::RecordVersion,
			],
			InstallationMode::Downgrade => [
				InstallTask::CreateCsrfSecret,
				InstallTask::ConvertSelectedTables,
				InstallTask::SynchroniseCollectors,
				InstallTask::RecordVersion,
			],
		});
	}

	public function templates(): ?TemplateSelection {
		return $this->templates;
	}

	public function start(): void {
		if ($this->stage !== InstallationStage::Confirmed) {
			throw new LogicException('Only a confirmed installation can be started.');
		}
		$this->stage = InstallationStage::Running;
	}

	public function complete(): void {
		if ($this->stage !== InstallationStage::Running) {
			throw new LogicException('Only a running installation can be completed.');
		}
		$this->stage = InstallationStage::Completed;
	}

	public function fail(): void {
		if ($this->stage !== InstallationStage::Running) {
			throw new LogicException('Only a running installation can fail.');
		}
		$this->stage = InstallationStage::Failed;
	}

	private function assertNotTerminal(): void {
		if ($this->stage === InstallationStage::Completed || $this->stage === InstallationStage::Failed) {
			throw new LogicException('A completed or failed installation cannot be changed.');
		}
	}

	private function assertNotRunning(): void {
		$this->assertNotTerminal();
		if ($this->stage === InstallationStage::Running) {
			throw new LogicException('A running installation cannot be reconfigured.');
		}
	}
}
