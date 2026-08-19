<?php

declare(strict_types=1);

/**
 * Lightweight PSR-4 loader for the installer bounded context.
 *
 * Cacti does not use Composer for its application code. Keeping this loader
 * local to the installer allows modern PHP 8.1-compatible code to be introduced without
 * changing the application's historical include mechanism.
 */
spl_autoload_register(static function (string $class): void {
	$prefix = 'Cacti\\Installer\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
	$file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;
	if (is_file($file)) {
		require_once $file;
	}
});
