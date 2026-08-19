<?php

declare(strict_types=1);

namespace Cacti\Installer\Application\Port;

use Cacti\Installer\Domain\Installation;

interface InstallationRepository {
	public function get(string $id): Installation;

	public function save(Installation $installation): void;
}
