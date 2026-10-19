<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise real HTTP authentication and the shipped list/edit forms. */
function library_web(): void {
	$temporary = sys_get_temp_dir() . '/cacti-library-web-' . bin2hex(random_bytes(8));
	library_expect(mkdir($temporary, 0700), true, 'HTTP fixture owns its temporary directory');
	$user   = 0;
	$server = null;

	try {
		library_expect(mkdir($temporary . '/coverage', 0700), true, 'HTTP fixture owns its coverage directory');
		$password = bin2hex(random_bytes(12));
		$user     = (int) sql_save(['id' => 0, 'username' => 'coverage_web', 'password' => password_hash($password, PASSWORD_DEFAULT),
			'realm'                         => 0, 'enabled' => 'on', 'must_change_password' => '', 'password_change' => 'on', 'login_opts' => 2,
			'policy_graphs'                 => 1, 'policy_hosts' => 1, 'policy_graph_templates' => 1, 'policy_trees' => 1], 'user_auth');
		library_expect($user > 0, true, 'HTTP fixture owns its local account');
		library_expect(db_execute_prepared('INSERT INTO user_auth_realm (user_id, realm_id) SELECT ?, realm_id FROM user_auth_realm WHERE user_id = 1', [$user]), true, 'HTTP account receives the disposable administrator realms');
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
		library_expect(is_resource($socket), true, 'HTTP fixture reserves a loopback address');
		$address = stream_socket_get_name($socket, false);
		fclose($socket);
		$base                                 = 'http://' . $address;
		$environment                          = getenv();
		$environment['CACTI_PR_RAW_COVERAGE'] = $temporary . '/coverage';
		$environment['XDEBUG_MODE']           = 'coverage';
		$server                               = proc_open([PHP_BINARY, '-d', 'memory_limit=512M', '-d', 'auto_prepend_file=' . __DIR__ . '/../../Helpers/RequestCoverage.php',
			'-S', $address, '-t', CACTI_PATH_BASE], [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes, CACTI_PATH_BASE, $environment);
		library_expect(is_resource($server), true, 'HTTP fixture starts the real PHP application');
		fclose($pipes[0]);
		$client = curl_init();
		curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS                                 => 5, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 20]);
		$request = static function (string $path, ?array $fields = null) use ($client, $base): DOMXPath {
			curl_setopt($client, CURLOPT_URL, $base . '/' . $path);
			curl_setopt($client, CURLOPT_POST, $fields !== null);

			if ($fields !== null) {
				curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
			}
			$html = curl_exec($client);
			library_expect(is_string($html), true, 'HTTP response exists for ' . $path);
			library_expect(curl_getinfo($client, CURLINFO_RESPONSE_CODE), 200, 'HTTP status for ' . $path);
			library_expect(str_starts_with(curl_getinfo($client, CURLINFO_EFFECTIVE_URL), $base . '/'), true, 'Redirects remain in the isolated application');

			return library_form_dom(static function () use ($html): void { print $html; });
		};

		$ready = false;

		for ($attempt = 0; $attempt < 50; $attempt++) {
			$connection = @stream_socket_client('tcp://' . $address, $errorNumber, $errorMessage, 0.1);

			if (is_resource($connection)) {
				fclose($connection);
				$ready = true;

				break;
			}
			usleep(100000);
		}
		library_expect($ready, true, 'HTTP fixture becomes ready within its deadline');
		$login = $request('index.php');
		library_expect($login->query('//input[@name="login_username"]')->length, 1, 'Unauthenticated console shows the real login form');
		$fields = [];

		foreach ($login->query('//form[@id="auth"]//input[@type="hidden"]') as $input) {
			$fields[$input->getAttribute('name')] = $input->getAttribute('value');
		}
		library_expect(!empty($fields['__csrf_magic']), true, 'Login includes a server-issued CSRF token');
		$console = $request('index.php', $fields + ['login_username' => 'coverage_web', 'login_password' => $password, 'realm' => '0']);
		library_expect($console->evaluate('string(//span[@id="user"])'), 'coverage_web', 'Actual credentials establish the HTTP session');
		$pages = [
			'host.php'            => 'Devices', 'host_templates.php' => 'Device Templates',
			'graphs.php'          => 'Graphs', 'data_sources.php' => 'Data Sources',
			'graph_templates.php' => 'Graph Templates', 'data_templates.php' => 'Data Templates',
			'data_queries.php'    => 'Data Queries', 'data_input.php' => 'Data Input Methods',
			'cdef.php'            => 'CDEF', 'vdef.php' => 'VDEF', 'tree.php' => 'Trees',
			'user_admin.php'      => 'Users', 'user_group_admin.php' => 'Groups',
		];

		foreach ($pages as $path => $heading) {
			$dom = $request($path);
			library_expect($dom->evaluate('string(//span[@id="user"])'), 'coverage_web', 'List page retains its authenticated viewer: ' . $path);
			library_expect(str_contains($dom->document->textContent, $heading), true, 'List page identifies its resource: ' . $path);
			library_expect($dom->query('//input[@name="login_username"]')->length, 0, 'List page does not fall back to authentication');
			library_expect($dom->query('//table')->length > 0, true, 'List page renders its resource table');
		}

		foreach (['host.php?action=edit'           => 'description', 'host_templates.php?action=edit' => 'name', 'graph_templates.php?action=template_edit' => 'name',
			'data_templates.php?action=template_edit' => 'template_name', 'data_queries.php?action=edit' => 'name', 'data_input.php?action=edit' => 'name',
			'cdef.php?action=edit'                    => 'name', 'vdef.php?action=edit' => 'name', 'tree.php?action=edit' => 'name'] as $path => $field) {
			$dom = $request($path);
			library_expect($dom->evaluate('string(//span[@id="user"])'), 'coverage_web', 'Creation form retains its viewer: ' . $path);
			library_expect($dom->query('//input[@name="' . $field . '"]')->length, 1, 'Creation form exposes its primary field: ' . $path);
			library_expect($dom->query('//form//input[@name="__csrf_magic"]')->length > 0, true, 'Creation form carries CSRF protection: ' . $path);
		}

		foreach (['path' => 'path_rrdtool', 'general' => 'graph_auth_method', 'authentication' => 'auth_method', 'mail' => 'settings_smtp_host'] as $tab => $field) {
			$dom = $request('settings.php?tab=' . $tab);
			library_expect($dom->evaluate('string(//span[@id="user"])'), 'coverage_web', 'Settings retain their authenticated viewer');
			library_expect($dom->query('//form[@id="form_settings"]//*[@name="' . $field . '"]')->length, 1, 'Settings tab exposes its configured field: ' . $tab);
			library_expect($dom->query('//form[@id="form_settings"]//input[@name="__csrf_magic"]')->length, 1, 'Settings form carries CSRF protection');
		}
		$logout = $request('logout.php');
		library_expect($logout->query('//input[@name="login_username"]')->length, 1, 'Logout returns to authentication');
		$denied = $request('host.php');
		library_expect($denied->query('//input[@name="login_username"]')->length, 1, 'The logged-out session cannot reach the device list');

		$reports = glob($temporary . '/coverage/*.json');
		library_expect(count($reports) >= count($pages) + 9 + 4 + 4, true, 'Every HTTP scenario produces native coverage');

		foreach ($reports as $path) {
			$report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

			foreach ($report['coverage'] as $file => $lines) {
				if (hash_file('sha256', $file) !== $report['hashes'][$file]) {
					throw new RuntimeException('HTTP coverage does not match ' . $file);
				}

				foreach ($lines as $line => $hits) {
					$GLOBALS['cacti_fixture_request_coverage'][$file][$line] = max($hits, $GLOBALS['cacti_fixture_request_coverage'][$file][$line] ?? -2);
				}
			}
		}
		library_expect(count($GLOBALS['cacti_fixture_request_coverage'] ?? []) > 0, true, 'Completed HTTP scenarios retain matching source coverage');
	} finally {
		if (is_resource($server)) {
			proc_terminate($server);
			proc_close($server);
		}

		if ($user > 0) {
			user_remove($user);
		}
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($temporary);
	}
}
