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

require_once CACTI_PATH_LIBRARY . '/functions.php';

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
	file_put_contents($outside . '/example.txt', 'sibling fixture');

	// A symlink living inside the base that points at the outside directory.
	@symlink($outside, $base . '/escape');

	return ['base' => $base, 'outside' => $outside, 'root' => $root];
}

function path_within_cleanup(array $fx): void {
	$entries = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($fx['root'], FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($entries as $entry) {
		// Remove a symlink itself, never what it points at.
		if ($entry->isLink() || !$entry->isDir()) {
			unlink($entry->getPathname());
		} else {
			rmdir($entry->getPathname());
		}
	}

	rmdir($fx['root']);
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
		expect(cacti_path_within($fx['base'], '../outside/example.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects an absolute-path escape', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], $fx['outside'] . '/example.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a symlinked segment that pivots outside the base', function () {
	$fx = path_within_fixture();

	try {
		// Lexically 'escape/example.txt' sits under the base, but 'escape' is a
		// symlink to the outside directory, so the physical target escapes.
		expect(cacti_path_within($fx['base'], 'escape/example.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within accepts a symlink that stays inside the base and returns its target', function () {
	$fx = path_within_fixture();

	try {
		if (!@symlink($fx['base'] . '/sub/ok.txt', $fx['base'] . '/alias.txt')
			|| !@symlink($fx['base'] . '/sub', $fx['base'] . '/sub-link')) {
			$this->markTestSkipped('symlinks are not available');
		}

		$real = str_replace('\\', '/', (string) realpath($fx['base']));

		// The caller gets the location the check resolved, not the link name,
		// so the link cannot be repointed between the check and the use.
		expect(cacti_path_within($fx['base'], 'alias.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub-link/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub-link/new/page.txt'))->toBe($real . '/sub/new/page.txt')
			->and(cacti_path_within($fx['base'], 'sub-link'))->toBe($real . '/sub');
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a symlinked file that leads outside the base', function () {
	$fx = path_within_fixture();

	try {
		if (!@symlink($fx['outside'] . '/example.txt', $fx['base'] . '/leak.txt')) {
			$this->markTestSkipped('symlinks are not available');
		}

		expect(cacti_path_within($fx['base'], 'leak.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'escape'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'escape/new.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('cacti_path_within rejects a dangling symlink even when its target would be inside', function () {
	$fx = path_within_fixture();

	try {
		if (!@symlink($fx['base'] . '/sub/later.txt', $fx['base'] . '/dangling.txt')
			|| !@symlink($fx['base'] . '/later-dir', $fx['base'] . '/dangling-dir')) {
			$this->markTestSkipped('symlinks are not available');
		}

		expect(cacti_path_within($fx['base'], 'dangling.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'dangling-dir/page.txt'))->toBeFalse();
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

test('path containment accepts existing absolute files and future nested leaves', function () {
	$fx = path_within_fixture();

	try {
		$real = str_replace('\\', '/', (string) realpath($fx['base']));

		expect(cacti_path_within($fx['base'], $fx['base'] . '/sub/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'new/nested/page.txt'))->toBe($real . '/new/nested/page.txt');
	} finally {
		path_within_cleanup($fx);
	}
});

test('path containment returns the physical path when the base is reached through a symlink', function () {
	$fx = path_within_fixture();

	try {
		if (!@symlink($fx['base'], $fx['root'] . '/base-link')) {
			$this->markTestSkipped('symlinks are not available');
		}

		$real = str_replace('\\', '/', (string) realpath($fx['base']));

		// The caller must get the resolved location, not one that still runs
		// through a link that could be repointed before the file is used.
		expect(cacti_path_within($fx['root'] . '/base-link', 'sub/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['root'] . '/base-link', '.'))->toBe($real);
	} finally {
		path_within_cleanup($fx);
	}
});

test('path containment rejects invalid base directories without throwing', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within('relative/base', 'page.txt'))->toBeFalse()
			->and(cacti_path_within($fx['root'] . '/missing', 'page.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'] . '/sub/ok.txt', 'page.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'] . "\0", 'page.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

test('a filesystem root contains every absolute path below it', function () {
	$fx = path_within_fixture();

	try {
		$target = str_replace('\\', '/', realpath($fx['base'] . '/sub/ok.txt'));
		$root   = str_starts_with($target, '/') ? '/' : substr($target, 0, 3);
		expect(cacti_path_within($root, $target))->toBe($target)
			->and(cacti_path_is_base_path($root, $target))->toBeTrue();
		expect(cacti_path_is_within($target, $root))->toBeTrue();
	} finally {
		path_within_cleanup($fx);
	}
});

test('candidate traversal and separators are resolved before containment', function () {
	$fx = path_within_fixture();

	try {
		$real = str_replace('\\', '/', (string) realpath($fx['base']));

		expect(cacti_path_within($fx['base'], 'sub/../sub/./ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub//ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub\\ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'] . '/', 'sub/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'] . '/sub/..', 'sub/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub/../../base/sub/ok.txt'))->toBe($real . '/sub/ok.txt')
			->and(cacti_path_within($fx['base'], 'sub\\..\\..\\outside\\example.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'sub/../../outside/example.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], '..'))->toBeFalse()
			->and(cacti_path_within($fx['base'], '/'))->toBeFalse()
			->and(cacti_path_within($fx['base'], '\\\\server\\share\\file'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'C:/windows/system.ini'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'C:'))->toBeFalse()
			->and(cacti_path_within($fx['base'], '~/x'))->toBe($real . '/~/x');
	} finally {
		path_within_cleanup($fx);
	}
});

test('a stream wrapper is never a contained path', function () {
	$fx = path_within_fixture();

	try {
		expect(cacti_path_within($fx['base'], 'phar://' . $fx['base'] . '/sub/ok.txt'))->toBeFalse()
			->and(cacti_path_within($fx['base'], 'php://filter/resource=sub/ok.txt'))->toBeFalse()
			->and(cacti_path_within('file://' . $fx['base'], 'sub/ok.txt'))->toBeFalse();
	} finally {
		path_within_cleanup($fx);
	}
});

/* Canonicalization vectors from the upstream Filesystem Path test suite, using
 * its Windows rules on every platform: backslashes are separators and a drive
 * letter is a root.  '~' is deliberately not expanded to a home directory. */
dataset('canonical paths', [
	['css/./style.css', 'css/style.css'], ['css/../style.css', 'style.css'], ['css/./../style.css', 'style.css'],
	['css/.././style.css', 'style.css'], ['css/../../style.css', '../style.css'], ['./css/style.css', 'css/style.css'],
	['../css/style.css', '../css/style.css'], ['./../css/style.css', '../css/style.css'], ['.././css/style.css', '../css/style.css'],
	['../../css/style.css', '../../css/style.css'], ['', ''], ['.', ''], ['..', '..'], ['./..', '..'], ['../.', '..'], ['../..', '../..'],
	['css\\.\\style.css', 'css/style.css'], ['css\\..\\style.css', 'style.css'], ['css\\..\\..\\style.css', '../style.css'],
	['..\\..\\css\\style.css', '../../css/style.css'], ['.\\..\\css\\style.css', '../css/style.css'],
	['/css/style.css', '/css/style.css'], ['/css/./style.css', '/css/style.css'], ['/css/../style.css', '/style.css'],
	['/css/./../style.css', '/style.css'], ['/css/.././style.css', '/style.css'], ['/./css/style.css', '/css/style.css'],
	['/../css/style.css', '/css/style.css'], ['/./../css/style.css', '/css/style.css'], ['/.././css/style.css', '/css/style.css'],
	['/../../css/style.css', '/css/style.css'], ['/', '/'], ['/..', '/'], ['/.', '/'],
	['\\css\\style.css', '/css/style.css'], ['\\css\\..\\style.css', '/style.css'], ['\\..\\..\\css\\style.css', '/css/style.css'],
	['css//style.css', 'css/style.css'], ['/css///style.css', '/css/style.css'], ['//css/style.css', '/css/style.css'],
	['css/style.css/', 'css/style.css'], ['/css/style.css//', '/css/style.css'], ['css\\\\style.css', 'css/style.css'],
	['~/css/style.css', '~/css/style.css'], ['~', '~'], ['...', '...'], ['a/.../b', 'a/.../b'], ['.hidden/..', ''],
	['C:/css/style.css', 'C:/css/style.css'], ['C:/css/./style.css', 'C:/css/style.css'], ['C:/css/../style.css', 'C:/style.css'],
	['C:/css/./../style.css', 'C:/style.css'], ['C:/css/.././style.css', 'C:/style.css'], ['C:/./css/style.css', 'C:/css/style.css'],
	['C:/../css/style.css', 'C:/css/style.css'], ['C:/./../css/style.css', 'C:/css/style.css'], ['C:/.././css/style.css', 'C:/css/style.css'],
	['C:/../../css/style.css', 'C:/css/style.css'], ['C:\\css\\style.css', 'C:/css/style.css'], ['C:\\css\\..\\style.css', 'C:/style.css'],
	['C:\\..\\..\\css\\style.css', 'C:/css/style.css'], ['C:', 'C:/'], ['C:/', 'C:/'], ['C:\\', 'C:/'], ['c:/x', 'c:/x'],
	['C:css/style.css', 'C:css/style.css'], ['\\\\server\\share\\file', '/server/share/file'],
]);

test('cacti_path_canonicalize', function (string $path, string $expected) {
	expect(cacti_path_canonicalize($path))->toBe($expected);
})->with('canonical paths');

/* Base path vectors from the upstream Filesystem Path test suite; an empty
 * path is never a base or a descendant. */
dataset('base paths', [
	['/base/path', '/base/path', true], ['/base/path', '/base/path/', true], ['/base/path/', '/base/path', true],
	['/base/path/', '/base/path/', true], ['/base/path', '/base/path/sub', true], ['/base/path', '/base/path/sub/sub', true],
	['/base/path', '/base/path\\sub', true], ['\\base\\path', '/base/path/sub', true],
	['/base/path', '/base/pathy', false], ['/base/path', '/base/pat', false], ['/base/path', '/base', false],
	['/base/path', '/base/path/..', false], ['/base/path', '/base/path/../path', true], ['/base/path', '/base/path/../pathy', false],
	['/base/path', '/base/path/sub/../..', false], ['/base/path', '/base/path/./sub', true],
	['/', '/', true], ['/', '/base', true], ['/', '/base/path', true], ['/base', '/', false],
	['/base/path', 'base/path', false], ['base/path', 'base/path/sub', true], ['base/path', '/base/path/sub', false],
	['/base/path', '/BASE/PATH', false], ['/base/path', '', false], ['', '/base', false],
	['C:/base/path', 'C:/base/path', true], ['C:/base/path', 'C:/base/path/', true], ['C:/base/path', 'C:\\base\\path', true],
	['C:\\base\\path', 'C:/base/path', true], ['C:/base/path', 'C:/base/path/sub', true], ['C:/base/path', 'C:/base/pathy', false],
	['C:/base/path', 'D:/base/path', false], ['C:/', 'C:/base', true], ['C:', 'C:/base', true], ['C:/base', 'C:/', false],
	['C:/base/path', '/base/path', false], ['/base/path', 'C:/base/path', false],
]);

test('cacti_path_is_base_path', function (string $base, string $path, bool $expected) {
	expect(cacti_path_is_base_path($base, $path))->toBe($expected);
})->with('base paths');

test('cacti_path_root recognises POSIX, UNC and drive roots only', function () {
	expect(cacti_path_root('/a'))->toBe('/')
		->and(cacti_path_root('//server/share'))->toBe('/')
		->and(cacti_path_root('C:/a'))->toBe('C:/')
		->and(cacti_path_root('C:'))->toBe('C:/')
		->and(cacti_path_root('C:a'))->toBe('')
		->and(cacti_path_root('1:/a'))->toBe('')
		->and(cacti_path_root('a/b'))->toBe('')
		->and(cacti_path_root(''))->toBe('');
});
