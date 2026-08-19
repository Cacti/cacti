<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

enum InstallationMode: string {
	case NewInstall = 'new-install';
	case Poller = 'poller';
	case Upgrade = 'upgrade';
	case Downgrade = 'downgrade';

	/** Maps the integer contract used by the existing Installer façade. */
	public static function tryFromLegacy(int $mode): ?self {
		return match ($mode) {
			1 => self::NewInstall,
			2 => self::Poller,
			3 => self::Upgrade,
			4 => self::Downgrade,
			default => null,
		};
	}
}
