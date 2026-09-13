<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Prevent Composer-generated dependencies from being committed.
 *
 * Composer installs into include/vendor, alongside a small set of legacy
 * libraries which are not managed by composer.json yet.  A committed partial
 * Composer tree can make a fresh checkout load stale or incomplete classes
 * instead of giving the documented "composer install" guidance.
 */

function cacti_vendor_policy_legacy_paths() : array {
	return [
		'include/vendor/GoogleAuthenticator/',
		'include/vendor/composer/index.php',
		'include/vendor/index.php',
		'include/vendor/parsedown/',
		'include/vendor/phpdiff/',
		'include/vendor/phpgettext/',
		'include/vendor/phpsnmp/',
	];
}

function cacti_vendor_policy_tracked_files(string $root) : array {
	$command = 'git -C ' . escapeshellarg($root) . ' -c core.quotePath=false ls-files -- include/vendor';
	$tracked = [];
	$status  = 0;

	exec($command, $tracked, $status);

	if ($status !== 0) {
		throw new RuntimeException('Unable to inspect tracked include/vendor files.');
	}

	return $tracked;
}

function cacti_vendor_policy_unexpected_files(array $tracked) : array {
	$legacy_paths = cacti_vendor_policy_legacy_paths();

	return array_values(array_filter($tracked, function (string $file) use ($legacy_paths) : bool {
		foreach ($legacy_paths as $legacy_path) {
			if ($file === $legacy_path || str_starts_with($file, $legacy_path)) {
				return false;
			}
		}

		return true;
	}));
}

function cacti_vendor_policy_main(string $root) : int {
	try {
		$unexpected = cacti_vendor_policy_unexpected_files(cacti_vendor_policy_tracked_files($root));
	} catch (RuntimeException $error) {
		fwrite(STDERR, $error->getMessage() . "\n");

		return 1;
	}

	if ($unexpected === []) {
		print "No Composer-generated dependencies are tracked in include/vendor.\n";

		return 0;
	}

	fwrite(STDERR, "Composer-generated files must not be committed under include/vendor:\n\n");
	fwrite(STDERR, implode("\n", array_map(fn (string $file) : string => "  $file", $unexpected)) . "\n\n");
	fwrite(STDERR, "Run 'composer install' locally, but leave its generated output ignored.\n");

	return 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	exit(cacti_vendor_policy_main(dirname(__DIR__, 2)));
}
