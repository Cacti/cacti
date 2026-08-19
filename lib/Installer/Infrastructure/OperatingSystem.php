<?php

declare(strict_types=1);

namespace Cacti\Installer\Infrastructure;

enum OperatingSystem: string {
	case Windows = 'windows';
	case Linux = 'linux';
	case FreeBsd = 'freebsd';
	case OtherUnix = 'other-unix';

	public static function fromPhpOsFamily(string $family): self {
		return match (strtolower($family)) {
			'windows' => self::Windows,
			'linux' => self::Linux,
			'bsd', 'freebsd' => self::FreeBsd,
			default => self::OtherUnix,
		};
	}

	public function usesWindowsPaths(): bool {
		return $this === self::Windows;
	}
}
