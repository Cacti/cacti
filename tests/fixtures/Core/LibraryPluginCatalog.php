<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise the catalog's actual HTTP, archive, and database boundaries. */
function library_plugin_catalog(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	cacti_fixture_source_function(CACTI_PATH_CLI . '/fetch_plugins.php', 'debug', '');
	$directory = sys_get_temp_dir() . '/cacti-catalog-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Catalog fixture owns its HTTP files');
	$plugin      = 'coveragecatalog';
	$destination = CACTI_PATH_PLUGINS . '/' . $plugin;
	library_expect(file_exists($destination), false, 'Catalog fixture cannot replace an installed plugin');
	$server   = null;
	$cwd      = getcwd();
	$session  = $_SESSION;
	$previous = [];

	foreach (['github_repository', 'github_user', 'github_access_token'] as $option) {
		$previous[$option] = read_config_option($option);
	}

	try {
		$archive = new PharData($directory . '/fixture.tar');
		$archive->addFromString('repository-root/payload.txt', 'Original catalog payload');
		$archive->addFromString('repository-root/nested/readme.txt', 'Nested file');
		$archive->addFromString('repository-root/.github/secret.txt', 'Excluded metadata');
		$archive->addFromString('repository-root/.htaccess', 'Require all denied');
		$archive->compress(Phar::GZ);
		unset($archive);
		file_put_contents($directory . '/mode', 'ok');
		$router = <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$mode = file_get_contents(__DIR__ . '/mode');
file_put_contents(__DIR__ . '/requests.log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
header('Content-Type: application/json');
if ($path === '/rate-limit') {
    http_response_code(429);
    echo json_encode(['message' => 'Fixture rate limit']);
} elseif ($path === '/raw') {
    echo 'Fixture raw bytes';
} elseif ($path === '/auth') {
    echo json_encode(['authorized' => isset($_SERVER['HTTP_AUTHORIZATION'])]);
} elseif ($path === '/users/fixture/repos') {
    echo json_encode($mode === 'repositories' ? false : [['full_name' => 'fixture/plugin_coveragecatalog'], ['full_name' => 'fixture/unrelated']]);
} elseif (str_ends_with($path, '/releases')) {
    echo json_encode($mode === 'releases' ? false : [['tag_name' => 'v1', 'published_at' => '2026-01-02T03:04:05Z', 'body' => 'Fixture release notes']]);
} elseif (str_contains($path, '/contents/')) {
    if ($mode === 'contents' || ($mode === 'branchcontents' && ($_GET['ref'] ?? '') === 'main')) {
        echo 'false';
    } else {
        $text = basename($path) === 'INFO' ? "compat = 1.3\nrequires = dependency\nlongname = Catalog fixture\nhomepage = https://example.invalid/catalog\nauthor = Cacti tests\n" : 'Fixture ' . basename($path);
        echo json_encode(['content' => base64_encode($text)]);
    }
} elseif (str_ends_with($path, '/tarball')) {
    readfile(__DIR__ . '/fixture.tar.gz');
} elseif ($path === '/repos/fixture/plugin_coveragecatalog') {
    echo json_encode($mode === 'branch' ? false : ['pushed_at' => '2026-01-03T03:04:05Z', 'default_branch' => 'main']);
} else {
    http_response_code(404);
    echo 'false';
}
PHP;
		library_expect(file_put_contents($directory . '/router.php', $router), strlen($router), 'Catalog router is written completely');
		$socket = stream_socket_server('tcp://127.0.0.1:0', $number, $message);
		library_expect(is_resource($socket), true, 'Catalog fixture reserves a loopback port');
		$address = stream_socket_get_name($socket, false);
		fclose($socket);
		$base   = 'http://' . $address;
		$server = proc_open([PHP_BINARY, '-S', $address, $directory . '/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $directory);
		library_expect(is_resource($server), true, 'Catalog fixture starts its isolated HTTP server');
		fclose($pipes[0]);
		$ready = false;

		for ($attempt = 0; $attempt < 50; $attempt++) {
			$connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);

			if (is_resource($connection)) {
				fclose($connection);
				$ready = true;

				break;
			}
			usleep(100000);
		}
		library_expect($ready, true, 'Catalog fixture becomes ready');
		set_config_option('github_repository', $base);
		set_config_option('github_user', 'fixture');
		set_config_option('github_access_token', '');
		library_expect(plugin_make_github_request($base . '/auth'), ['authorized' => false], 'Unauthenticated repository requests omit credentials');
		set_config_option('github_access_token', bin2hex(random_bytes(16)));
		library_expect(plugin_make_github_request($base . '/auth'), ['authorized' => true], 'Configured credentials reach only the isolated repository');
		library_expect(plugin_make_github_request($base . '/raw', 'raw'), 'Fixture raw bytes', 'Raw repository responses preserve their bytes');
		library_expect(plugin_make_github_request($base . '/rate-limit'), false, 'Rate-limited repository cannot report success');
		library_expect($_SESSION[SESS_MESSAGES]['rate_limited']['message'], 'Fixture rate limit', 'Rate-limit message reaches the operator');
		$plugins = plugin_fetch_latest_plugins();
		library_expect(array_keys($plugins), [$plugin], 'Catalog ignores unrelated repositories');
		$rows = db_fetch_assoc_prepared('SELECT * FROM plugin_available WHERE plugin = ? ORDER BY tag_name', [$plugin]);
		library_expect(array_column($rows, 'tag_name'), ['main', 'v1'], 'Catalog stores the actual default branch and release');

		foreach ($rows as $row) {
			library_expect($row['description'], 'Catalog fixture', 'Catalog parses the INFO description');
			library_expect($row['author'], 'Cacti tests', 'Catalog preserves attribution');
			library_expect($row['requires'], 'dependency', 'Catalog retains dependency metadata');
			library_expect(base64_decode($row['archive'], true), file_get_contents($directory . '/fixture.tar.gz'), 'Stored download preserves the actual archive');
			library_expect(base64_decode($row['readme'], true), 'Fixture README.md', 'Stored readme retains its original bytes');
		}
		$before = count(file($directory . '/requests.log'));
		plugin_fetch_latest_plugins();
		library_expect(count(file($directory . '/requests.log')) - $before, 3, 'Unchanged release and branch avoid content and archive downloads');
		library_expect(db_fetch_assoc_prepared('SELECT * FROM plugin_available WHERE plugin = ? ORDER BY tag_name', [$plugin]), $rows, 'Unchanged catalog does not rewrite stored metadata');
		library_expect(db_execute_prepared('INSERT INTO plugin_config (directory, name, status) VALUES (?, ?, ?)', [$plugin, 'Catalog restore fixture', 4]), true, 'Archive fixture owns its disabled plugin record');
		library_expect(api_plugin_archive_restore($plugin, 'v1', 'available'), true, 'Downloaded release can be restored');
		library_expect(file_get_contents($destination . '/payload.txt'), 'Original catalog payload', 'Restore strips the repository root directory');
		library_expect(file_get_contents($destination . '/nested/readme.txt'), 'Nested file', 'Restore creates nested payload directories');
		library_expect(file_exists($destination . '/.github/secret.txt'), false, 'Restore excludes repository automation metadata');
		library_expect(file_get_contents($destination . '/.htaccess'), 'Require all denied', 'Restore retains the plugin access-control file');
		library_expect(db_fetch_cell_prepared('SELECT last_updated FROM plugin_config WHERE directory = ?', [$plugin]), '2026-01-02 03:04:05', 'Restore records the selected release timestamp');
		library_expect(api_plugin_archive_restore($plugin, 'missing', 'available'), false, 'Missing catalog archive cannot report restoration');

		foreach (['repositories', 'releases', 'contents', 'branch', 'branchcontents'] as $mode) {
			db_execute_prepared('DELETE FROM plugin_available WHERE plugin = ?', [$plugin]);
			file_put_contents($directory . '/mode', $mode);
			ob_start();

			try {
				$result = plugin_fetch_latest_plugins();
				$output = ob_get_contents();
			} finally {
				ob_end_clean();
			}
			library_expect($result, false, 'Incomplete catalog fetch reports failure at ' . $mode);
			library_expect(strlen($output) > 0, true, 'Failed catalog fetch supplies an operator diagnostic');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_available WHERE plugin = ?', [$plugin]), str_starts_with($mode, 'branch') ? 1 : 0, 'Only completely fetched entries survive a later catalog failure');
		}
		file_put_contents($directory . '/mode', 'ok');
		library_expect(isset(plugin_fetch_latest_plugins()[$plugin]['main']), true, 'Catalog retry recovers the missing branch');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_available WHERE plugin = ?', [$plugin]), 2, 'Catalog retry restores both complete entries');
	} finally {
		chdir($cwd);

		if (is_resource($server)) {
			proc_terminate($server);
			proc_close($server);
		}
		db_execute_prepared('DELETE FROM plugin_available WHERE plugin = ?', [$plugin]);
		db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', [$plugin]);

		foreach ($previous as $option => $value) {
			set_config_option($option, $value);
		}
		$_SESSION = $session;

		foreach ([$destination, $directory] as $owned) {
			if (is_dir($owned)) {
				$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($owned, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

				foreach ($files as $file) {
					$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
				}
				rmdir($owned);
			}
		}
	}
}
