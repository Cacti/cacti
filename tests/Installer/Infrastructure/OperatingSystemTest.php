<?php

declare(strict_types=1);

namespace Cacti\Tests\Installer\Infrastructure;

use Cacti\Installer\Infrastructure\OperatingSystem;
use PHPUnit\Framework\TestCase;

final class OperatingSystemTest extends TestCase {
	public function testItNormalisesSupportedPlatforms(): void {
		self::assertSame(OperatingSystem::Windows, OperatingSystem::fromPhpOsFamily('Windows'));
		self::assertSame(OperatingSystem::Linux, OperatingSystem::fromPhpOsFamily('Linux'));
		self::assertSame(OperatingSystem::FreeBsd, OperatingSystem::fromPhpOsFamily('FreeBSD'));
		self::assertSame(OperatingSystem::FreeBsd, OperatingSystem::fromPhpOsFamily('BSD'));
		self::assertSame(OperatingSystem::OtherUnix, OperatingSystem::fromPhpOsFamily('Darwin'));
	}
}
