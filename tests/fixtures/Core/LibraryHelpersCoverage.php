<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Auth {
	/**
	 * Serves the OpenID provider's HTTP calls from the fixture, so no IdP is
	 * contacted. The provider resolves cacti_http() in its own namespace first.
	 *
	 * @param string              $method  The HTTP method
	 * @param string              $url     The requested URL
	 * @param array<string,mixed> $options The request options
	 *
	 * @return array<string,mixed> The scripted response
	 */
	function cacti_http(string $method, string $url, array $options = []) : array {
		$GLOBALS['helpers_http_calls'][] = [$method, $url];

		return $GLOBALS['helpers_http'][$url] ?? ['success' => false, 'body' => '', 'error' => 'not scripted'];
	}
}

namespace {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}

	// Whole-file evidence for every included vendor class does not fit the
	// runner's memory limit, and only library lines are gated.
	if (function_exists('xdebug_set_filter')) {
		xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__, 3) . '/lib/']);
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

	// Without Composer the notification library must refuse to run rather than
	// fail later on a missing Symfony class. This runs in its own process.
	if (($argv[1] ?? '') === 'no-vendor') {
		require_once dirname(__DIR__, 3) . '/lib/api_notification.php';

		try {
			api_notification_recipients('fixture@example.test');
		} catch (RuntimeException $exception) {
			if ($exception->getMessage() !== 'Symfony Notifier dependencies are unavailable. Run Composer install to restore the vendor tree.') {
				throw new RuntimeException('Unexpected dependency message: ' . $exception->getMessage());
			}
			print json_encode(['passed' => 1], JSON_THROW_ON_ERROR) . "\n";

			exit(0);
		}

		throw new RuntimeException('Missing Symfony dependencies were accepted');
	}

	require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
	require_once dirname(__DIR__, 2) . '/Helpers/BufferedPDOStatement.php';
	require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
	require_once dirname(__DIR__, 3) . '/include/global.php';

	foreach (['api_notification', 'cdef', 'graph_template_input', 'import', 'renderer', 'CactiMd5FileFinder', 'CactiProcessLock', 'CactiRecipient'] as $library) {
		require_once CACTI_PATH_LIBRARY . '/' . $library . '.php';
	}

	$count = 0;

	/**
	 * Counts one assertion and throws when the values are not identical.
	 *
	 * @param mixed  $actual   The observed value
	 * @param mixed  $expected The required value
	 * @param string $message  What the assertion checks
	 *
	 * @return void
	 */
	function helpers_expect(mixed $actual, mixed $expected, string $message) : void {
		global $count;
		$count++;

		if ($actual !== $expected) {
			throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
		}
	}

	$connection = new FakeMySQLPDO();
	$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BufferedPDOStatement::class, []]);
	$GLOBALS['database_hostname'] = 'helpers-fixture';
	$GLOBALS['database_port']     = 0;
	$GLOBALS['database_default']  = 'helpers-fixture';
	$GLOBALS['database_sessions'] = ['helpers-fixture:0:helpers-fixture' => $connection];
	$connection->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$connection->exec('CREATE TABLE cdef (id INTEGER PRIMARY KEY)');
	$connection->exec('CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, cdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');

	// A nested CDEF expands its inner definition through the item query.
	$connection->exec('INSERT INTO cdef (id) VALUES (1), (2)');
	$connection->exec("INSERT INTO cdef_items (id, cdef_id, sequence, type, value) VALUES (1, 1, 1, 6, 'CURRENT_DATA_SOURCE'), (2, 1, 2, 6, '8'), (3, 1, 3, 2, '3'), (4, 2, 1, 5, '1'), (5, 2, 2, 6, '2'), (6, 2, 3, 2, '1')");
	helpers_expect(get_cdef(1), 'CURRENT_DATA_SOURCE,8,*', 'A CDEF joins its items in sequence');
	helpers_expect(get_cdef(2), 'CURRENT_DATA_SOURCE,8,*,2,+', 'A CDEF item expands a nested CDEF');

	// Numeric graph item columns accept ids only; other columns keep their rules.
	foreach (['graph_type_id', 'task_item_id', 'consolidation_function_id', 'cdef_id', 'vdef_id', 'gprint_id'] as $column) {
		helpers_expect(graph_template_input_value_is_allowed($column, '42'), true, $column . ' accepts an id');
		helpers_expect(graph_template_input_value_is_allowed($column, '4a'), false, $column . ' rejects a non-id');
	}
	helpers_expect(graph_template_input_value_is_allowed('color_id', '00FF00'), true, 'A color is not an id column');

	$item_hash  = 'hash_1000247e6b4f4ec5a0e0c0a0a0a0a0a0a0a0a0';
	$other_hash = 'hash_1000247e6b4f4ec5a0e0c0a0a0a0a0a0a0a0b1';
	$template   = ['items' => [$item_hash => []], 'inputs' => [['column_name' => 'color_id', 'items' => $item_hash . '|']]];
	helpers_expect(graph_template_input_xml_preflight($template), true, 'An input may reference its own template item');
	$template['inputs'][0]['items'] = $item_hash . '|' . $other_hash;
	helpers_expect(graph_template_input_xml_preflight($template), false, 'An input may not reference a missing item');
	helpers_expect(graph_template_input_xml_preflight(['items' => ['not-a-hash' => []]]), false, 'An item key must be an XML hash');
	helpers_expect(graph_template_input_xml_preflight(['items' => 'not-a-list']), true, 'A scalar item list holds no references');

	// Template names are relative to the renderer root on every platform.
	$renderer_root = sys_get_temp_dir() . '/cacti-renderer-' . bin2hex(random_bytes(4));
	mkdir($renderer_root);
	file_put_contents($renderer_root . '/page.php', 'Hello <?php print $name; ?>');

	try {
		$renderer = new CactiRenderer($renderer_root);
		helpers_expect($renderer->render('page.php', ['name' => 'fixture']), 'Hello fixture', 'A relative template renders');

		foreach (['/etc/passwd', '\\share\\page.php', 'C:\\page.php', 'c:/page.php'] as $absolute) {
			try {
				$renderer->render($absolute);
				helpers_expect($absolute, 'rejected', 'An absolute template name is refused');
			} catch (InvalidArgumentException $exception) {
				helpers_expect($exception->getMessage(), 'Renderer template name must be relative', 'An absolute template name is refused: ' . $absolute);
			}
		}

		// The manifest finder hashes the same tree and honors its ignore pattern.
		file_put_contents($renderer_root . '/skip.tmp', 'ignored');
		$ignored = [];
		$hashes  = (new CactiMd5FileFinder())->findHashes($renderer_root, '/\.tmp$/', [], static function (string $line) use (&$ignored) : void {
			$ignored[] = $line;
		});
		unlink($renderer_root . '/skip.tmp');
		helpers_expect($hashes, ['page.php' => md5_file($renderer_root . '/page.php')], 'The manifest lists only unignored files');
		helpers_expect(in_array('[                         Ignored] skip.tmp', $ignored, true), true, 'The finder reports an ignored file');
	} finally {
		unlink($renderer_root . '/page.php');
		rmdir($renderer_root);
	}

	// Symfony recipients pass through; mixing them with addresses is refused.
	$recipient = new CactiRecipient('one@example.test');
	helpers_expect(api_notification_recipients([$recipient]), [$recipient], 'A list of Symfony recipients passes through');

	try {
		api_notification_recipients([$recipient, 'two@example.test']);
		helpers_expect(false, true, 'Mixed recipients are refused');
	} catch (InvalidArgumentException $exception) {
		helpers_expect($exception->getMessage(), 'Symfony recipients cannot be mixed with email address values.', 'Mixed recipients are refused');
	}

	// Two processes asking for the same task lock exclude each other until release.
	$lock_store = new PDO('sqlite::memory:');
	$first      = CactiProcessLock::fromPdo($lock_store, 'poller', 'fixture', 1);
	$second     = CactiProcessLock::fromPdo($lock_store, 'poller', 'fixture', 1);
	helpers_expect($first->acquire(), true, 'The first process takes the task lock');
	helpers_expect($second->acquire(), false, 'A second process cannot take a held task lock');
	$first->release();
	helpers_expect($second->acquire(), true, 'A released task lock can be taken again');
	$second->release();

	// The OpenID callback reaches the token and UserInfo endpoints through the
	// scripted cacti_http(); ID token signature checks are covered elsewhere,
	// so this provider returns fixed verified claims.
	class HelpersOpenIdProvider extends Cacti\Auth\OpenIdLoginProvider {
		/**
		 * Returns the claims the fixture treats as a verified ID token.
		 *
		 * @param string              $idToken       The ID token
		 * @param array<string,mixed> $discovery     The discovery metadata
		 * @param string              $expectedNonce The nonce stored for the login
		 *
		 * @return array<string,mixed>|null The verified claims
		 */
		protected function verifyIdToken(string $idToken, array $discovery, string $expectedNonce) : ?array {
			return $GLOBALS['helpers_claims'];
		}
	}

	$oidc_log = tempnam(sys_get_temp_dir(), 'cacti-oidc-log-');

	foreach (['log_destination' => 1, 'log_verbosity' => POLLER_VERBOSITY_LOW, 'path_cactilog' => $oidc_log, 'selective_debug' => '', 'selective_plugin_debug' => ''] as $name => $value) {
		$config[OPTIONS_CLI][$name] = $value;
	}

	/**
	 * Runs one OpenID callback against the scripted IdP responses.
	 *
	 * @param string              $debug     The provider debug flag
	 * @param array<string,mixed> $discovery The discovery document the IdP serves
	 * @param array<string,mixed> $responses Further scripted responses by URL
	 *
	 * @return Cacti\Auth\LoginResult The login outcome
	 */
	function helpers_oidc_login(string $debug, array $discovery, array $responses = []) : Cacti\Auth\LoginResult {
		$discovery_url = 'https://idp.example.test/.well-known/openid-configuration';
		$provider      = new HelpersOpenIdProvider([
			'id'                 => 4,
			'name'               => 'Fixture OIDC',
			'type'               => PROVIDER_TYPE_OPENID,
			'enabled'            => 'on',
			'allow_auth_cookies' => 'on',
			'user_id'            => 0,
			'button_label'       => '',
			'debug'              => $debug,
			'parameters'         => json_encode(['client_id' => 'fixture-client', 'client_secret' => 'fixture-secret', 'discovery_url' => $discovery_url]),
		]);

		$GLOBALS['helpers_http']       = [$discovery_url => ['success' => true, 'body' => json_encode($discovery), 'error' => '']] + $responses;
		$GLOBALS['helpers_http_calls'] = [];
		$_SESSION['sess_oidc_4']       = ['state' => 'fixture-state', 'nonce' => 'fixture-nonce', 'verifier' => 'fixture-verifier', 'remember' => false];
		$_REQUEST                      = ['state' => 'fixture-state', 'code' => 'fixture-code'];

		return $provider->complete();
	}

	$endpoints = [
		'issuer'                 => 'https://idp.example.test',
		'authorization_endpoint' => 'https://idp.example.test/authorize',
		'token_endpoint'         => 'https://idp.example.test/token',
		'jwks_uri'               => 'https://idp.example.test/jwks',
		'userinfo_endpoint'      => 'https://idp.example.test/userinfo',
	];

	// A refused code exchange denies the login and is logged only when debugging.
	foreach (['on' => true, '' => false] as $debug => $logged) {
		file_put_contents($oidc_log, '');
		helpers_expect(helpers_oidc_login($debug, $endpoints)->success, false, 'A refused token exchange denies the login');
		helpers_expect(str_contains((string) file_get_contents($oidc_log), "AUTH OIDC: token exchange failed for provider 'Fixture OIDC': not scripted"), $logged, 'The exchange failure is logged only when debugging');
	}

	// UserInfo claims are merged only when their subject matches the ID token.
	$tokens = [
		'https://idp.example.test/token'    => ['success' => true, 'body' => json_encode(['id_token' => 'fixture.id.token', 'access_token' => 'fixture-access']), 'error' => ''],
		'https://idp.example.test/userinfo' => ['success' => true, 'body' => json_encode(['sub' => 'subject-1', 'preferred_username' => 'userinfo-user']), 'error' => ''],
	];
	$GLOBALS['helpers_claims'] = ['sub' => 'subject-1'];
	$login                     = helpers_oidc_login('', $endpoints, $tokens);
	helpers_expect([$login->success, $login->username], [true, 'userinfo-user'], 'Matching UserInfo claims are merged');
	helpers_expect(in_array(['POST', 'https://idp.example.test/token'], $GLOBALS['helpers_http_calls'], true), true, 'The code is exchanged at the token endpoint');
	$GLOBALS['helpers_claims'] = ['sub' => 'subject-2'];
	$login                     = helpers_oidc_login('', $endpoints, $tokens);
	helpers_expect([$login->success, $login->username], [true, 'subject-2'], 'UserInfo for another subject is ignored');

	// A discovery document without its required endpoints is refused.
	foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $missing) {
		try {
			helpers_oidc_login('', array_diff_key($endpoints, [$missing => true]));
			helpers_expect($missing, 'refused', 'Discovery without its required endpoint is refused');
		} catch (RuntimeException $exception) {
			helpers_expect($exception->getMessage(), 'Invalid OpenID discovery document', 'Discovery without ' . $missing . ' is refused');
		}
	}
	unlink($oidc_log);

	print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
}
