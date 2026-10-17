<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

/** Runs without Composer, a database connection, or network access. */
final readonly class DependencyPreflight {
	/** @param list<string>|null $extensions
	 * @param list<class-string> $requiredClasses
	 */
	public function __construct(private string $root, private string $phpVersion = PHP_VERSION, private ?array $extensions = null, private array $requiredClasses = [
		\Symfony\Component\Process\Process::class, \Symfony\Component\Security\Csrf\CsrfTokenManager::class,
		\Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher::class, \Firebase\JWT\JWT::class, \Slim\App::class]) {
	}

	/** @return list<string> Actionable messages containing no configuration values. */
	public function errors() : array {
		$errors    = [];
		$manifest  = $this->readJson('composer.json');
		$lock      = $this->readJson('composer.lock');
		$installed = $this->readJson('include/vendor/composer/installed.json');

		if ($manifest === null || $lock === null || $installed === null
			|| !is_array($manifest['require'] ?? null) || !is_array($lock['packages'] ?? null)
			|| !is_array($installed['packages'] ?? null)) {
			return ['Dependency metadata is missing or invalid. Deploy a complete release package or run cli/prepare_install.sh.'];
		}

		$minimum = $manifest['config']['platform']['php'] ?? '8.3.0';

		if (version_compare($this->phpVersion, $minimum, '<') || version_compare($this->phpVersion, '9.0.0', '>=')) {
			$errors[] = 'Use a supported PHP runtime (' . $minimum . ' or later in PHP 8).';
		}
		$extensions = array_map('strtolower', $this->extensions ?? get_loaded_extensions());

		foreach ($manifest['require'] as $name => $constraint) {
			if (str_starts_with($name, 'ext-') && !in_array(strtolower(substr($name, 4)), $extensions, true)) {
				$errors[] = 'Enable the PHP extension ' . substr($name, 4) . ' in the installer runtime.';
			}
		}

		$versions = array_column($installed['packages'], 'version', 'name');

		foreach ($lock['packages'] as $package) {
			if (($versions[$package['name']] ?? null) !== $package['version']) {
				$errors[] = 'Prepare the locked Composer package ' . $package['name'] . ' with cli/prepare_install.sh.';
			}
		}

		foreach (['cacti.sql', 'include/vendor/autoload.php', 'include/vendor/composer/platform_check.php',
			'include/js/jquery.js', 'include/js/jquery-ui.js', 'include/js/htmx.js', 'include/js/purify.js',
			'include/js/select2.js', 'include/js/jquery.tablesorter.js', 'include/js/d3.js',
			'include/js/billboard.js', 'include/js/pace.js', 'include/js/big.js', 'include/js/lzjs.js',
			'include/js/screenfull.js', 'include/js/jstree.js', 'include/js/jquery.tablesorter.widgets.js',
			'include/js/jquery.tablesorter.pager.js', 'include/js/jquery.validate/jquery.validate.js',
			'include/js/jquery.validate/jquery.validate.min.js', 'include/js/jquery.validate/additional-methods.js',
			'include/js/jquery.validate/additional-methods.min.js', 'install/install.js', 'include/layout.js',
			'install/install.css', 'include/css/jquery.contextMenu.css', 'include/css/billboard.css',
			'include/fa/css/all.css', 'include/tabler/dist/tabler-icons.min.css',
			'include/vendor/lipis/flag-icons/css/flag-icons.css', 'include/themes/print.css'] as $file) {
			if (!is_readable($this->root . '/' . $file) || filesize($this->root . '/' . $file) === 0) {
				$errors[] = 'Restore the required application file ' . $file . ' from a complete release or run cli/prepare_install.sh.';
			}
		}

		if ($errors === []) {
			// Composer's generated platform check covers transitive platform requirements.
			// Capture its diagnostic output; only controlled messages reach web/CLI output.
			ob_start();

			try {
				require $this->root . '/include/vendor/composer/platform_check.php';
				require_once $this->root . '/include/vendor/autoload.php';

				foreach ($this->requiredClasses as $class) {
					if (!class_exists($class)) {
						throw new \RuntimeException('A required dependency class is unavailable');
					}
				}
			} catch (\Throwable) {
				$errors[] = 'Composer platform or autoloader verification failed. Run composer check-platform-reqs --no-dev and cli/prepare_install.sh in this PHP environment.';
			} finally {
				ob_end_clean();
			}
		}

		return $errors;
	}

	/** @return array<string, mixed>|null */
	private function readJson(string $file) : ?array {
		$path = $this->root . '/' . $file;

		if (!is_readable($path)) {
			return null;
		}
		$data = json_decode((string) file_get_contents($path), true);

		return is_array($data) ? $data : null;
	}
}
