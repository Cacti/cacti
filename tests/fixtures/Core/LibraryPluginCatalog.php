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
} elseif ($path === '/rate-limit-plain') {
    http_response_code(429);
} elseif ($path === '/raw') {
    echo 'Fixture raw bytes';
} elseif ($path === '/auth') {
    echo json_encode(['authorized' => isset($_SERVER['HTTP_AUTHORIZATION'])]);
} elseif ($path === '/users/fixture/repos') {
    echo json_encode($mode === 'repositories' ? false : ($mode === 'empty' ? [['full_name' => 'fixture/unrelated']] : [['full_name' => 'fixture/plugin_coveragecatalog'], ['full_name' => 'fixture/unrelated']]));
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
    if ($mode === 'tarball' || ($mode === 'branchtarball' && ($_GET['ref'] ?? '') === 'main')) {
        http_response_code(429);
    } else {
        readfile(__DIR__ . '/fixture.tar.gz');
    }
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
		file_put_contents($directory . '/mode', 'empty');
		unset($_SESSION[SESS_MESSAGES]['plugins_fetched'], $_SESSION[SESS_MESSAGES]['rate_limited']);
		library_expect(plugin_fetch_latest_plugins(), [], 'A repository owner without plugin repositories yields an empty catalog');
		library_expect($_SESSION[SESS_MESSAGES]['plugins_fetched']['level'] ?? null, MESSAGE_LEVEL_WARN, 'An empty catalog is reported as a warning');
		library_expect(plugin_make_github_request($base . '/rate-limit-plain'), false, 'A rate limit without a message cannot report success');
		library_expect(str_contains($_SESSION[SESS_MESSAGES]['rate_limited']['message'] ?? '', 'personal access token'), true, 'A rate limit without a message suggests an access token');
		library_plugin_catalog_web($directory, $plugin);
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

/**
 * A catalog refresh running under a web SAPI redirects back to the plugin list
 * on failure, so each failure runs as a request against a disposable PHP web
 * server. The shipped refresh runs from the CLI, so this is the only producer.
 */
function library_plugin_catalog_web(string $directory, string $plugin): void {
	$router = <<<'PHP'
<?php
// A router script is not subject to auto_prepend_file, so it loads coverage itself.
require getenv('CACTI_PATH_BASE') . '/tests/Helpers/RequestCoverage.php';
require getenv('CACTI_PATH_BASE') . '/include/global.php';
// The catalog logs through the debug() helper of its only shipped caller, cli/fetch_plugins.php.
require_once CACTI_PATH_BASE . '/tests/Helpers/SourceSlice.php';
cacti_fixture_source_function(CACTI_PATH_CLI . '/fetch_plugins.php', 'debug', '');
register_shutdown_function(static function (): void {
    print json_encode($_SESSION[SESS_MESSAGES] ?? [], JSON_UNESCAPED_SLASHES);
});
var_export(plugin_fetch_latest_plugins());
PHP;
	library_expect(file_put_contents($directory . '/cacti.php', $router), strlen($router), 'Catalog web router is written completely');
	library_expect(mkdir($directory . '/coverage', 0700), true, 'Catalog web server owns its coverage directory');
	$socket = stream_socket_server('tcp://127.0.0.1:0', $number, $message);
	library_expect(is_resource($socket), true, 'Catalog web server reserves a loopback port');
	$address = stream_socket_get_name($socket, false);
	fclose($socket);
	$environment                          = getenv();
	$environment['CACTI_PATH_BASE']       = CACTI_PATH_BASE;
	$environment['CACTI_PR_RAW_COVERAGE'] = $directory . '/coverage';
	$environment['XDEBUG_MODE']           = 'coverage';
	$server                               = proc_open([PHP_BINARY, '-d', 'memory_limit=512M', '-S', $address, $directory . '/cacti.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/web.log', 'a'], 2 => ['file', $directory . '/web.log', 'a']], $pipes, CACTI_PATH_BASE, $environment);
	library_expect(is_resource($server), true, 'Catalog web server starts');
	fclose($pipes[0]);
	$user = read_config_option('github_user');

	try {
		$ready = false;

		for ($attempt = 0; $attempt < 50 && !$ready; $attempt++) {
			$connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);
			$ready      = is_resource($connection);

			if ($ready) {
				fclose($connection);
			} else {
				usleep(100000);
			}
		}
		library_expect($ready, true, 'Catalog web server becomes ready');

		foreach ([
			'unset'         => [200, 'Base API Repository URL or User not being set'],
			'contents'      => [302, 'Unable to get archive from GitHub/GitLab location.'],
			'tarball'       => [302, 'Unable to get archive from GitHub/GitLab location.'],
			'branch'        => [302, 'Unable to get develop repo data for plugin ' . $plugin],
			'branchtarball' => [302, 'Unable to get archive from GitHub/GitLab location.'],
		] as $mode => [$status, $text]) {
			db_execute_prepared('DELETE FROM plugin_available WHERE plugin = ?', [$plugin]);
			set_config_option('github_user', $mode === 'unset' ? '' : $user);
			file_put_contents($directory . '/mode', $mode);
			$client = curl_init('http://' . $address . '/');
			curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
			$response = (string) curl_exec($client);
			library_expect(curl_getinfo($client, CURLINFO_RESPONSE_CODE), $status, 'Interactive catalog refresh status at ' . $mode);
			library_expect(str_contains($response, $text), true, 'Interactive catalog refresh explains the failure at ' . $mode);
			library_expect(preg_match('/^Location: plugins\.php\r?$/mi', $response), $status === 302 ? 1 : 0, 'Only a failed download returns to the plugin list at ' . $mode);
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_available WHERE plugin = ?', [$plugin]), str_starts_with($mode, 'branch') ? 1 : 0, 'Only the completed release survives an interactive failure at ' . $mode);
		}
		$reports = glob($directory . '/coverage/*.json');
		library_expect(count($reports), 5, 'Each interactive catalog request publishes its coverage');
		library_import_coverage($reports);
	} finally {
		set_config_option('github_user', $user);
		proc_terminate($server);
		proc_close($server);
	}
}
