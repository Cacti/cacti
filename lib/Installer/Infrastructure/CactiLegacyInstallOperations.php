<?php

declare(strict_types=1);

namespace Cacti\Installer\Infrastructure;

/**
 * The sole boundary at which Cacti's legacy installer APIs may be represented.
 *
 * Implementations may call global Cacti functions, use the settings table, or
 * invoke existing installer helpers. Neither the domain nor application layer
 * may depend on those details.
 */
interface CactiLegacyInstallOperations {
	public function createCsrfSecret(): void;

	public function convertSelectedTables(): void;

	public function upgradeDatabase(): void;

	/** @param list<string> $templates */
	public function importTemplates(array $templates): void;

	public function provisionServer(OperatingSystem $operatingSystem): void;

	public function provisionPoller(): void;

	public function synchroniseCollectors(): void;

	public function recordVersion(): void;
}
