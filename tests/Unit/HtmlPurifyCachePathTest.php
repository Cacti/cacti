<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Tests for the html_purify() definition cache location (#7552).
 *
 * HTMLPurifier defaults its serializer cache to a directory inside its own
 * library tree.  Packaged installs mount the web root read-only, so the write
 * fails and purify() raises E_USER_WARNING for every call.  html_purify()
 * points the cache at cache/purifier instead, and drops to the null cache
 * when that is not writable either.
 *
 * The purifier runs in a child process so the cache directory used by each
 * case is observable, and so a failed write surfaces as child output rather
 * than a warning swallowed by the Pest process.
 */

function _html_purify_probe($cache_path) {
	$stub = <<<'PHP'
<?php
$base       = $argv[1];
$cache_path = $argv[2];

require $base . '/include/vendor/autoload.php';

/* lib/functions.php pulls in the database layer; html_purify() only needs
 * this one helper from it. */
function is_resource_writable($path) {
	if (substr($path, -1) === '/') {
		$path .= uniqid('probe') . '.tmp';
	}

	$handle = @fopen($path, 'w');

	if ($handle === false) {
		return false;
	}

	fclose($handle);
	unlink($path);

	return true;
}

$config = array('base_path' => $base);

if ($cache_path !== '') {
	$config['purifier_cache_path'] = $cache_path;
}

require $base . '/lib/html.php';

print html_purify('<b>keep</b><script>alert(1)</script>');
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_purify_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(defined('PHP_BINARY') ? PHP_BINARY : 'php') . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 2)) . ' ' .
		escapeshellarg($cache_path) . ' 2>&1';

	$output = array();
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	return array(
		'status' => $status,
		'output' => implode("\n", $output),
	);
}

function _html_purify_tmpdir() {
	$dir = sys_get_temp_dir() . '/cacti_purify_' . bin2hex(random_bytes(6));
	mkdir($dir, 0755, true);

	return $dir;
}

function _html_purify_rmdir($dir) {
	if (!is_dir($dir)) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($items as $item) {
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}

	rmdir($dir);
}

function _html_purify_library_cache() {
	return dirname(__DIR__, 2) .
		'/include/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer';
}

function _html_purify_count($dir) {
	if (!is_dir($dir)) {
		return 0;
	}

	return iterator_count(new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
	));
}

test('the alternate cache path is documented in the distributed config', function () {
	$config = file_get_contents(dirname(__DIR__, 2) . '/include/config.php.dist');

	expect($config)->not->toBeFalse()
		->and($config)->toContain("\$config['purifier_cache_path'] = '/var/cache/cacti/purifier';")
		->and($config)->toContain('The directory must already exist and be writable');
});

test('the definition cache lands in the configured path, not the library tree', function () {
	$cache  = _html_purify_tmpdir();
	$before = _html_purify_count(_html_purify_library_cache());

	$result = _html_purify_probe($cache);

	$written = _html_purify_count($cache);
	$after   = _html_purify_count(_html_purify_library_cache());

	_html_purify_rmdir($cache);

	expect($result['status'])->toBe(0, $result['output']);
	expect($written)->toBeGreaterThan(0, 'the serializer cache must be written under the configured path');
	expect($after)->toBe($before, 'nothing may be written into the HTMLPurifier library tree, it is read-only on packaged installs');
});

test('an unwritable cache path purifies without raising a warning', function () {
	$cache  = _html_purify_tmpdir();
	chmod($cache, 0500);

	$result = _html_purify_probe($cache);

	chmod($cache, 0755);
	_html_purify_rmdir($cache);

	expect($result['status'])->toBe(0, $result['output']);
	expect($result['output'])->not->toContain('Warning');
	expect($result['output'])->not->toContain('not writable');
});

test('purification still strips script content', function () {
	$cache  = _html_purify_tmpdir();
	$result = _html_purify_probe($cache);

	_html_purify_rmdir($cache);

	expect($result['output'])->toContain('<b>keep</b>')
		->and($result['output'])->not->toContain('alert(1)');
});
