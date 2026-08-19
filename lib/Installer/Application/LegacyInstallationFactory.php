<?php

declare(strict_types=1);

namespace Cacti\Installer\Application;

use Cacti\Installer\Domain\Installation;
use Cacti\Installer\Domain\InstallationMode;
use LogicException;

/**
 * Anti-corruption layer between the legacy integer/settings-based installer
 * and the typed installation aggregate.
 */
final class LegacyInstallationFactory {
	public function create(string $id, int $legacyMode, bool $licenseAccepted): Installation {
		$mode = InstallationMode::tryFromLegacy($legacyMode);
		if ($mode === null) {
			throw new LogicException(sprintf('Unsupported legacy installation mode: %d.', $legacyMode));
		}

		$installation = Installation::begin($id);
		if ($licenseAccepted) {
			$installation->acceptLicense();
		}
		$installation->chooseMode($mode);

		return $installation;
	}
}
