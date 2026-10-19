<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise real HTTP authentication and the shipped list/edit forms. */
function library_web(): void {
	$temporary = sys_get_temp_dir() . '/cacti-library-web-' . bin2hex(random_bytes(8));
	library_expect(mkdir($temporary, 0700), true, 'HTTP fixture owns its temporary directory');
	$user        = 0;
	$ownedDevice = 0;
	$server      = null;
	$authMethod  = read_config_option('auth_method');

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
			CURLOPT_USERAGENT                                 => 'Cacti disposable coverage fixture', CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 20]);
		$request = static function (string $path, ?array $fields = null) use ($client, $base): DOMXPath {
			curl_setopt($client, CURLOPT_URL, $base . '/' . $path);
			curl_setopt($client, CURLOPT_POST, $fields !== null);

			if ($fields !== null) {
				curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
			}
			$html = curl_exec($client);
			library_expect(is_string($html), true, 'HTTP response exists for ' . $path . ': ' . curl_error($client));
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
		$graph = (int) db_fetch_cell('SELECT id FROM graph_local ORDER BY id LIMIT 1');
		$data  = (int) db_fetch_cell('SELECT id FROM data_local ORDER BY id LIMIT 1');
		library_expect($graph > 0 && $data > 0, true, 'HTTP editors use installed graph and data-source records');

		foreach (['graphs.php?action=graph_edit&id=' . $graph => ['local_graph_id', $graph],
			'data_sources.php?action=ds_edit&id=' . $data        => ['local_data_id', $data],
			'user_admin.php?action=user_edit&id=' . $user        => ['id', $user]] as $path => [$field, $identity]) {
			$dom = $request($path);
			library_expect($dom->evaluate('string(//input[@name="' . $field . '"]/@value)'), (string) $identity, 'HTTP editor retains its stored record identity');
			library_expect($dom->query('//input[@name="__csrf_magic"]')->length > 0, true, 'Stored-record editor includes server-issued CSRF protection');
		}
		$create      = $request('host.php?action=edit');
		$values      = library_web_form_values($create, 'host_form');
		$description = 'HTTP lifecycle ' . bin2hex(random_bytes(6));
		$values      = array_replace($values, ['action' => 'save', 'description' => $description, 'hostname' => '127.0.0.1', 'snmp_version' => '0', 'disabled' => 'on', 'host_template_id' => '0']);
		$edited      = $request('host.php', $values);
		$ownedDevice = (int) db_fetch_cell_prepared('SELECT id FROM host WHERE description = ?', [$description]);
		library_expect($ownedDevice > 0, true, 'Submitting the actual device form creates its owned disabled device');
		library_expect($edited->evaluate('string(//input[@name="description"]/@value)'), $description, 'Successful save redisplays the stored device');
		$values                = library_web_form_values($edited, 'host_form');
		$values['description'] = $description . ' revised';
		$edited                = $request('host.php', $values + ['action' => 'save']);
		library_expect(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$ownedDevice]), $description . ' revised', 'Editing preserves identity and stores the changed description');
		library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$ownedDevice]), 'on', 'Unchanged form preserves disabled state');
		$values             = library_web_form_values($edited, 'host_form');
		$values['hostname'] = '';
		$request('host.php', $values + ['action' => 'save']);
		library_expect(db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', [$ownedDevice]), '127.0.0.1', 'Invalid form save preserves the previous hostname');

		foreach (['1', '2', '3', '4', '5', '7'] as $action) {
			$list  = $request('host.php');
			$token = $list->evaluate('string(//input[@name="__csrf_magic"]/@value)');
			library_expect($token !== '', true, 'Bulk action uses a fresh CSRF token');
			$confirmation = $request('host.php', ['action' => 'actions', 'drp_action' => $action, 'chk_' . $ownedDevice => 'on', '__csrf_magic' => $token]);
			library_expect(str_contains($confirmation->document->textContent, $description . ' revised'), true, 'Bulk confirmation identifies the selected device');
			library_expect($confirmation->query('//input[@name="selected_items"]')->length, 1, 'Bulk confirmation carries its selected identity to submission');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$ownedDevice]), 1, 'Preparing a bulk action does not perform the mutation');
		}

		$invalid = $request('host.php?action=edit&id=invalid-fixture');
		library_expect(str_contains($invalid->document->textContent, 'Validation error for variable'), true, 'Invalid device ID renders a controlled HTML validation error');
		library_expect($invalid->query('//input[@name="description"]')->length, 0, 'Invalid device ID never reaches an editable form');
		cacti_log('Native HTTP fixture log marker', false, 'SYSTEM');
		$log = $request('clog.php?filename=cacti.log');
		library_expect(str_contains($log->document->textContent, 'Native HTTP fixture log marker'), true, 'Log viewer renders the actual stored entry');

		foreach (['purge', 'rotate'] as $action) {
			$confirmation = $request('clog.php?filename=cacti.log&action=' . $action);
			library_expect($confirmation->query('//button[@id="pc"]')->length, 1, 'Log operation exposes its confirmation control');
			library_expect(str_contains($confirmation->document->textContent, 'cacti.log'), true, 'Log confirmation identifies its target file');
		}
		$logout = $request('logout.php');
		library_expect($logout->query('//input[@name="login_username"]')->length, 1, 'Logout returns to authentication');
		$denied = $request('host.php');
		library_expect($denied->query('//input[@name="login_username"]')->length, 1, 'The logged-out session cannot reach the device list');

		foreach ([['1', '', 'index.php'], ['1', $base . '/host.php?action=login', 'host.php'],
			['1', 'https://foreign.example.invalid/cacti/index.php', 'index.php'], ['1', $base . '/logout.php', 'index.php'],
			['3', '', 'graph_view.php']] as [$option, $referer, $destination]) {
			db_execute_prepared('UPDATE user_auth SET login_opts = ? WHERE id = ?', [$option, $user]);
			curl_setopt($client, CURLOPT_REFERER, $referer);
			$login  = $request('index.php');
			$fields = [];

			foreach ($login->query('//form[@id="auth"]//input[@type="hidden"]') as $input) {
				$fields[$input->getAttribute('name')] = $input->getAttribute('value');
			}
			library_expect(!empty($fields['__csrf_magic']), true, 'Redirect scenario obtains a fresh login token');
			$location = '';
			curl_setopt_array($client, [CURLOPT_URL => $base . '/index.php', CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_POSTFIELDS                     => http_build_query($fields + ['login_username' => 'coverage_web', 'login_password' => $password, 'realm' => '0']),
				CURLOPT_HEADERFUNCTION                 => static function ($handle, string $header) use (&$location): int {
					if (str_starts_with(strtolower($header), 'location:')) {
						$location = trim(substr($header, 9));
					}

					return strlen($header);
				}]);

			try {
				library_expect(is_string(curl_exec($client)), true, 'Authenticated redirect produces an HTTP response');
				library_expect(curl_getinfo($client, CURLINFO_RESPONSE_CODE), 302, 'Login emits its configured redirect');
				library_expect(basename(parse_url($location, PHP_URL_PATH)), $destination, 'Login redirect chooses the configured local destination');
				library_expect(parse_url($location, PHP_URL_HOST) === null || str_starts_with($location, $base . '/'), true, 'Untrusted referer cannot redirect the browser to another host');
				library_expect(str_contains($location, 'action=login'), false, 'Redirect removes the login action before navigation');
			} finally {
				curl_setopt($client, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($client, CURLOPT_HEADERFUNCTION, static fn ($handle, string $header): int => strlen($header));
				curl_setopt($client, CURLOPT_REFERER, '');
			}
			$logout = $request('logout.php');
			library_expect($logout->query('//input[@name="login_username"]')->length, 1, 'Each redirect scenario ends its own authenticated session');
		}
		$administrator = db_fetch_row('SELECT * FROM user_auth WHERE id = 1');
		$accountCount  = (int) db_fetch_cell('SELECT COUNT(*) FROM user_auth');

		foreach ([AUTH_METHOD_NONE => 'Authentication is set to None', AUTH_METHOD_BASIC => 'no username was passed'] as $method => $message) {
			set_config_option('auth_method', (string) $method);
			$denied = $request('index.php');
			library_expect(str_contains($denied->document->textContent, $message), true, 'Unsafe authentication configuration produces an explicit denial');
			library_expect($denied->query('//span[@id="user"]')->length, 0, 'Configuration failure never establishes an authenticated viewer');
			library_expect(db_fetch_row('SELECT * FROM user_auth WHERE id = 1'), $administrator, 'Authentication recovery cannot modify the administrator account');
			library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM user_auth'), $accountCount, 'Configuration failure cannot provision an account');
		}
		set_config_option('auth_method', $authMethod);
		$reports = glob($temporary . '/coverage/*.json');
		library_expect(count($reports) >= count($pages) + 9 + 4 + 4 + 1 + 3, true, 'Every HTTP scenario produces native coverage');

		library_import_coverage($reports);
		library_expect(count($GLOBALS['cacti_fixture_request_coverage'] ?? []) > 0, true, 'Completed HTTP scenarios retain matching source coverage');
	} catch (Throwable $error) {
		$evidence = getenv('CACTI_PR_RAW_COVERAGE');

		if (is_string($evidence) && is_dir($evidence) && is_file($temporary . '/server.log')) {
			copy($temporary . '/server.log', $evidence . '/library-web-failure.log');
		}

		throw $error;
	} finally {
		set_config_option('auth_method', $authMethod);

		if (is_resource($server)) {
			proc_terminate($server);
			proc_close($server);
		}

		if ($user > 0) {
			user_remove($user);
		}

		if ($ownedDevice > 0) {
			api_device_remove($ownedDevice);
		}
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($temporary);
	}
}

/** Read successful native controls, including controls outside their owning form. */
function library_web_form_values(DOMXPath $dom, string $form): array {
	$values = [];

	foreach ($dom->query('//form[@id="' . $form . '"]//*[@name] | //*[@form="' . $form . '"][@name]') as $control) {
		if ($control->hasAttribute('disabled') || in_array(strtolower($control->getAttribute('type')), ['submit', 'button', 'file'], true)) {
			continue;
		}

		if (in_array(strtolower($control->getAttribute('type')), ['checkbox', 'radio'], true) && !$control->hasAttribute('checked')) {
			continue;
		}
		$name = $control->getAttribute('name');

		if ($control->nodeName === 'select') {
			$selected = $dom->query('.//option[@selected]', $control);

			if (!$selected->length) {
				$selected = $dom->query('.//option[1]', $control);
			}
			$values[$name] = $selected->length ? $selected->item(0)->getAttribute('value') : '';
		} else {
			$values[$name] = $control->nodeName === 'textarea' ? $control->textContent :
				(in_array(strtolower($control->getAttribute('type')), ['checkbox', 'radio'], true) && !$control->hasAttribute('value') ? 'on' : $control->getAttribute('value'));
		}
	}
	library_expect(!empty($values['__csrf_magic']), true, 'Native form controls retain the CSRF token');

	return $values;
}
