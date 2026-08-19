<?php

declare(strict_types=1);

namespace Cacti\Installer\Application;

use Cacti\Installer\Application\Port\InstallationRepository;
use Cacti\Installer\Application\Port\InstallationTaskRunner;

/** Executes a confirmed plan, persisting the aggregate after every state change. */
final class InstallCoordinator {
	public function __construct(
		private readonly InstallationRepository $installations,
		private readonly InstallationTaskRunner $tasks,
	) {
	}

	public function run(string $installationId): InstallationExecutionResult {
		$installation = $this->installations->get($installationId);
		$plan = $installation->plan();
		$installation->start();
		$this->installations->save($installation);

		foreach ($plan->tasks() as $task) {
			$result = $this->tasks->run($task, $installation);
			if (!$result->successful) {
				$installation->fail();
				$this->installations->save($installation);

				$failure = $result->failure;

				return InstallationExecutionResult::failed(
					$failure !== null && $failure !== '' ? $failure : 'The installation task failed.'
				);
			}
		}

		$installation->complete();
		$this->installations->save($installation);

		return InstallationExecutionResult::completed();
	}
}
