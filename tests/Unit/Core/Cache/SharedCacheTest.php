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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioural coverage for the cross-process SharedCache used by the SNMP
 * credential cache. These tests exercise the durable file tier with temporary
 * directories and explicit backend orders, including fallback on a failed
 * higher-tier write and recovery from malformed/corrupt cache files, without
 * touching the database or the encryption key (encrypted is off by default).
 */

require_once dirname(__DIR__, 4) . '/lib/cache.php';

function sharedCacheTmpDir(): string {
	$dir = sys_get_temp_dir() . '/cacti_sharedcache_' . uniqid('', true);

	mkdir($dir, 0770, true);

	return $dir;
}

function sharedCacheRmTree(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}

	foreach (glob($dir . '/*') ?: array() as $path) {
		if (is_dir($path)) {
			sharedCacheRmTree($path);
		} else {
			@unlink($path);
		}
	}

	@rmdir($dir);
}

test('store and fetch round-trip through the file tier across instances', function () {
	$dir = sharedCacheTmpDir();

	try {
		$data = array('k1' => array('a', 'b'), 'k2' => 42, 'k3' => 'value');

		$writer = new \Cacti\Cache\SharedCache('sc_roundtrip', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($writer->store($data, 'token-1'))->toBeTrue();
		expect($writer->isShared())->toBeTrue();

		/* a fresh instance has no in-process memo, so it must read from disk */
		$reader = new \Cacti\Cache\SharedCache('sc_roundtrip', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($reader->fetch())->toEqual($data);
		expect($reader->checksum())->toBe('token-1');
	} finally {
		sharedCacheRmTree($dir);
	}
});

test('invalidate removes the stored entry from the file tier', function () {
	$dir = sharedCacheTmpDir();

	try {
		$writer = new \Cacti\Cache\SharedCache('sc_invalidate', array('cache_dir' => $dir, 'backends' => array('file')));
		$writer->store(array('v' => 1), 'token');
		expect(is_file($dir . '/sc_invalidate.cache'))->toBeTrue();

		$writer->invalidate();
		expect(is_file($dir . '/sc_invalidate.cache'))->toBeFalse();

		$reader = new \Cacti\Cache\SharedCache('sc_invalidate', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($reader->fetch())->toBeFalse();
	} finally {
		sharedCacheRmTree($dir);
	}
});

test('a corrupt payload is treated as a miss rather than crashing', function () {
	$dir = sharedCacheTmpDir();

	try {
		/* valid JSON envelope, but the payload is not a serialized value */
		file_put_contents($dir . '/sc_corrupt.cache', json_encode(array(
			'checksum' => 'token',
			'payload'  => base64_encode('this-is-not-serialized'),
		)));

		$cache = new \Cacti\Cache\SharedCache('sc_corrupt', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($cache->fetch())->toBeFalse();
	} finally {
		sharedCacheRmTree($dir);
	}
});

test('malformed JSON in the cache file is treated as a miss', function () {
	$dir = sharedCacheTmpDir();

	try {
		file_put_contents($dir . '/sc_badjson.cache', 'not-valid-json{');

		$cache = new \Cacti\Cache\SharedCache('sc_badjson', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($cache->fetch())->toBeFalse();
		expect($cache->checksum())->toBeNull();
	} finally {
		sharedCacheRmTree($dir);
	}
});

test('a non-string checksum field does not raise a TypeError', function () {
	$dir = sharedCacheTmpDir();

	try {
		/* a corrupt file could decode to an array-valued checksum; readChecksum()
		 * must treat that as absent instead of returning a non-string */
		file_put_contents($dir . '/sc_badsum.cache', json_encode(array(
			'checksum' => array('unexpected', 'array'),
			'payload'  => base64_encode(serialize(array('x' => 1))),
		)));

		$cache = new \Cacti\Cache\SharedCache('sc_badsum', array('cache_dir' => $dir, 'backends' => array('file')));
		expect($cache->checksum())->toBeNull();
	} finally {
		sharedCacheRmTree($dir);
	}
});

test('a failed higher-tier write falls through without serving stale data', function () {
	$dir = sharedCacheTmpDir();

	try {
		/* occupy the cache path with a directory so the file tier's atomic
		 * rename() always fails - deterministic even when the suite runs as
		 * root, where permission bits would be ignored */
		mkdir($dir . '/sc_fallback.cache', 0770, true);

		$writer = new \Cacti\Cache\SharedCache('sc_fallback', array('cache_dir' => $dir, 'backends' => array('file', 'static')));

		/* no cross-process tier accepted the write */
		expect($writer->store(array('fresh' => true), 'new'))->toBeFalse();

		/* a fresh consumer still gets the new value from the process-local
		 * fallback tier and never the unreadable stale directory entry */
		$reader = new \Cacti\Cache\SharedCache('sc_fallback', array('cache_dir' => $dir, 'backends' => array('file', 'static')));
		expect($reader->fetch())->toEqual(array('fresh' => true));
	} finally {
		@rmdir($dir . '/sc_fallback.cache');
		sharedCacheRmTree($dir);
	}
});
