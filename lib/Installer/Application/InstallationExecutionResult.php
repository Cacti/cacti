<?php

declare(strict_types=1);

namespace Cacti\Installer\Application;

final class InstallationExecutionResult {
	private function __construct(
		public readonly bool $successful,
		public readonly ?string $failure = null,
	) {
	}

	public static function completed(): self {
		return new self(true);
	}

	public static function failed(string $failure): self {
		return new self(false, $failure);
	}
}
