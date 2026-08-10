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

require_once dirname(__DIR__, 2) . '/lib/path_containment.php';

beforeEach(function () : void {
	$this->base = sys_get_temp_dir() . '/cacti_containment_' . bin2hex(random_bytes(6));
	mkdir($this->base . '/nested', 0755, true);
	file_put_contents($this->base . '/nested/present.txt', 'x');
});

afterEach(function () : void {
	foreach (['/nested/present.txt', '/nested/link', '/link', '/nested', ''] as $leaf) {
		$path = $this->base . $leaf;

		if (is_link($path)) {
			unlink($path);
		} elseif (is_file($path)) {
			unlink($path);
		} elseif (is_dir($path)) {
			rmdir($path);
		}
	}
});

test('a plain relative path resolves inside the base', function () : void {
	expect(validate_relative_path_within('nested/present.txt', $this->base))
		->toBe(realpath($this->base) . '/nested/present.txt');
});

test('a path whose parent exists but file does not is still accepted', function () : void {
	expect(validate_relative_path_within('nested/new.txt', $this->base))
		->toBe(realpath($this->base) . '/nested/new.txt');
});

test('traversal out of the base is rejected', function () : void {
	expect(validate_relative_path_within('../escape.php', $this->base))->toBeFalse();
	expect(validate_relative_path_within('nested/../../escape.php', $this->base))->toBeFalse();
	expect(validate_relative_path_within('./nested/present.txt', $this->base))->toBeFalse();
});

test('absolute paths and drive letters are rejected', function () : void {
	expect(validate_relative_path_within('/etc/passwd', $this->base))->toBeFalse();
	expect(validate_relative_path_within('C:/windows/system32', $this->base))->toBeFalse();
	expect(validate_relative_path_within('\\\\server\\share', $this->base))->toBeFalse();
});

test('empty, non string and null byte paths are rejected', function () : void {
	expect(validate_relative_path_within('', $this->base))->toBeFalse();
	expect(validate_relative_path_within(null, $this->base))->toBeFalse();
	expect(validate_relative_path_within(['a'], $this->base))->toBeFalse();
	expect(validate_relative_path_within("nested/pre\0sent.txt", $this->base))->toBeFalse();
	expect(validate_relative_path_within('nested//present.txt', $this->base))->toBeFalse();
});

test('a missing base directory is rejected', function () : void {
	expect(validate_relative_path_within('a.txt', $this->base . '/does_not_exist'))->toBeFalse();
});

test('a symlinked segment under the base is rejected', function () : void {
	symlink(sys_get_temp_dir(), $this->base . '/link');

	expect(validate_relative_path_within('link/anything.txt', $this->base))->toBeFalse();
});

test('a path whose parent cannot be resolved is rejected', function () : void {
	expect(validate_relative_path_within('missing_dir/child/file.txt', $this->base))->toBeFalse();
});

test('cacti_path_is_within accepts the base itself and children', function () : void {
	expect(cacti_path_is_within($this->base . '/nested', $this->base))->toBeTrue();
	expect(cacti_path_is_within($this->base, $this->base))->toBeTrue();
});

test('cacti_path_is_within rejects unresolvable paths and outside paths', function () : void {
	expect(cacti_path_is_within($this->base . '/nope', $this->base))->toBeFalse();
	expect(cacti_path_is_within($this->base, $this->base . '/nope'))->toBeFalse();
	expect(cacti_path_is_within(sys_get_temp_dir(), $this->base))->toBeFalse();
});

test('windows comparison rules can be forced on any platform', function () : void {
	expect(cacti_path_is_within($this->base . '/nested', $this->base, true))->toBeTrue();
	expect(cacti_path_is_within(sys_get_temp_dir(), $this->base, true))->toBeFalse();
});

test('windows path normalization folds case, separators and long prefixes', function () : void {
	expect(cacti_normalize_windows_path('C:\\Cacti\\Plugins\\'))->toBe('c:/cacti/plugins');
	expect(cacti_normalize_windows_path('\\\\?\\C:\\Cacti'))->toBe('c:/cacti');
	expect(cacti_normalize_windows_path('\\\\?\\UNC\\server\\share'))->toBe('//server/share');
	expect(cacti_normalize_windows_path('/'))->toBe('/');
	expect(cacti_normalize_windows_path(null))->toBe('');
});

test('repository url accepts http and https with a host', function () : void {
	expect(plugin_validate_repository_url('https://api.github.com/'))->toBe('https://api.github.com');
	expect(plugin_validate_repository_url('http://repo.internal:8080/api'))->toBe('http://repo.internal:8080/api');
});

test('repository url rejects anything else', function () : void {
	// GHSA-pjmv-fxjm-29cx: an arbitrary URL redirects every plugin fetch
	expect(plugin_validate_repository_url('file:///etc/passwd'))->toBe('');
	expect(plugin_validate_repository_url('javascript:alert(1)'))->toBe('');
	expect(plugin_validate_repository_url('ftp://example.com/'))->toBe('');
	expect(plugin_validate_repository_url('not a url'))->toBe('');
	expect(plugin_validate_repository_url(''))->toBe('');
	expect(plugin_validate_repository_url(null))->toBe('');
	expect(plugin_validate_repository_url('http://'))->toBe('');
});
