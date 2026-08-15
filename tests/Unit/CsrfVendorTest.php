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

/*
 * The Symfony CSRF component backs CactiCsrfGuard. include/vendor is
 * gitignored and force-committed, so a package can exist on a developer's
 * disk while being absent from a release tarball. These assertions read the
 * git index, not the filesystem, because only the index reflects what ships.
 */

$root = dirname(__DIR__, 2);

test('the Symfony CSRF classes are autoloadable', function () {
	expect(class_exists(Symfony\Component\Security\Csrf\CsrfTokenManager::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\CsrfToken::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator::class))->toBeTrue();
});

test('the Symfony CSRF package is committed, not merely installed', function () use ($root) {
	$tracked = shell_exec('git -C ' . escapeshellarg($root) . ' ls-files include/vendor/symfony/security-csrf/');

	expect(trim((string) $tracked))->not->toBe('');
	expect($tracked)->toContain('src/CsrfTokenManager.php');
});

test('composer.json declares the dependency', function () use ($root) {
	$composer = json_decode(file_get_contents($root . '/composer.json'), true);

	expect($composer['require'])->toHaveKey('symfony/security-csrf');
});
