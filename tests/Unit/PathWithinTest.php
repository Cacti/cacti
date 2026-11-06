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

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/path.php';

/**
 * Build an isolated base directory with a contained file, plus a sibling
 * "outside" directory and a symlink from inside the base to it.
 *
 * @return array{base: string, outside: string, root: string}
 */
function path_within_fixture(): array {
	$root    = sys_get_temp_dir() . '/cacti-path-within-' . bin2hex(random_bytes(4));
	$base    = $root . '/base';
	$outside = $root . '/outside';

	mkdir($base . '/sub', 0777, true);
	mkdir($outside, 0777, true);

	file_put_contents($base . '/sub/ok.txt', 'contained');
	file_put_contents($outside . '/secret.txt', 'escaped');

	// A symlink living inside the base that points at the outside directory.
	@symlink($outside, $base . '/escape');

	return ['base' => $base, 'outside' => $outside, 'root' => $root];
}

function path_within_cleanup(array $fx): void {
	exec('rm -rf ' . escapeshellarg($fx['root']));
}

test('cacti_path_within accepts a legitimate contained relative path', function () {
	$fx = path_within_fixture();

	try {
		$resolved = cacti_path_within($fx['base'], 'sub/ok.txt');

		expect($resolved)->toBeString()
			->and(is_file($resolved))->toBeTrue()
			->and(file_get_contents($resolved))->toBe('contained');
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within allows a not-yet-created leaf inside the base', function () {
	$fx = path_within_fixture();

	try {
		$resolved = cacti_path_within($fx['base'], 'sub/brand-new.txt');

		expect($resolved)->toBeString()
			->and(file_exists($resolved))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a ../ traversal escape', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], '../outside/secret.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects an absolute-path escape', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], $fx['outside'] . '/secret.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], '/etc/passwd'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a symlinked segment that pivots outside the base', function () {
	$fx = path_within_fixture();

	try {
		// Lexically 'escape/secret.txt' sits under the base, but 'escape' is a
		// symlink to the outside directory, so the physical target escapes.
		expect(cacti_path_within($fx['base'], 'escape/secret.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a null byte and empty input', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], "sub/ok.txt\0.png"))->toBeFalse()
			->and(cacti_path_within($fx['base'], ''))->toBeFalse()
			->and(cacti_path_within('', 'sub/ok.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within returns a contained path for the base itself', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], '.'))->toBeString();
	} finally {
		path_within_cleanup($fx);
	}
});

test('lexical fallback contains and rejects without the Symfony component', function () {
	// Exercise the graceful-degradation path directly: these are pure string
	// operations, independent of whether Symfony Path is autoloaded.
	expect(cacti_path_within_lexical_fallback('/var/www/cacti/include/content', 'welcome.php'))
		->toBe(['/var/www/cacti/include/content', '/var/www/cacti/include/content/welcome.php'])
		->and(cacti_path_within_lexical_fallback('/var/www/cacti/include/content', 'sub/page.php'))
		->toBe(['/var/www/cacti/include/content', '/var/www/cacti/include/content/sub/page.php'])
		->and(cacti_path_within_lexical_fallback('/var/www/cacti/include/content', '../../../etc/passwd'))
		->toBeFalse()
		->and(cacti_path_within_lexical_fallback('/var/www/cacti/include/content', '/etc/passwd'))
		->toBeFalse();
});

test('lexical canonicaliser resolves dot segments and never climbs above an absolute root', function () {
	expect(cacti_path_canonicalize_lexical('/a/b/../c/./d'))->toBe('/a/c/d')
		->and(cacti_path_canonicalize_lexical('/a/../../b'))->toBe('/b')
		->and(cacti_path_canonicalize_lexical('a/b/../c'))->toBe('a/c');
});
