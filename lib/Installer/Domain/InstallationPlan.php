<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

final class InstallationPlan {
	/** @var non-empty-list<InstallTask> */
	private readonly array $tasks;

	/** @param non-empty-list<InstallTask> $tasks */
	public function __construct(array $tasks) {
		$this->tasks = $tasks;
	}

	/** @return non-empty-list<InstallTask> */
	public function tasks(): array {
		return $this->tasks;
	}
}
