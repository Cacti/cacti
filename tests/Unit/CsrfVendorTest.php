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
 * gitignored and resolved by Composer: a source checkout runs composer
 * install, and the release build installs from the lock. Committing the tree
 * would put a second, drifting copy of the component in the repository, so
 * the index is asserted to stay clear of it.
 */

$root = dirname(__DIR__, 2);

test('the Symfony CSRF classes are autoloadable', function () {
	expect(class_exists(Symfony\Component\Security\Csrf\CsrfTokenManager::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\CsrfToken::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage::class))->toBeTrue();
	expect(class_exists(Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator::class))->toBeTrue();
});

test('the Symfony CSRF package is resolved by Composer, not committed', function () use ($root) {
	$tracked = shell_exec('git -C ' . escapeshellarg($root) . ' ls-files include/vendor/symfony/security-csrf/');

	expect(trim((string) $tracked))->toBe('');
});

test('composer.json declares the dependency', function () use ($root) {
	$composer = json_decode(file_get_contents($root . '/composer.json'), true);

	expect($composer['require'])->toHaveKey('symfony/security-csrf');
});
