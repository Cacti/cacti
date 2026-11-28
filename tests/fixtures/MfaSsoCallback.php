<?php
namespace Cacti\Auth;

use RuntimeException;

$root = dirname(__DIR__, 2);
require $root . '/include/vendor/autoload.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/tests/Helpers/RequestCoverage.php';
require $root . '/include/global_constants.php';
define('CACTI_PATH_URL', '/');

function db_fetch_row_prepared(string $query, array $params) : array {
	if ($params !== ['alice', 1001]) {
		throw new RuntimeException('Unexpected provider identity lookup.');
	}

	return ['id' => 21, 'enabled' => 'on', 'locked' => ''];
}

function auth_user_has_access(array $user) : bool {
	return true;
}

function __esc(string $message) : string {
	return $message;
}

function cacti_redirect(string $path, array $params) : never {
	if ($path !== '/auth_profile.php' || $params !== ['tab' => '2fa']) {
		throw new RuntimeException('Unexpected callback destination.');
	}

	throw new \LogicException('profile');
}

cacti_fixture_source_function($root . '/login_sso.php', 'login_sso_complete', __NAMESPACE__);
$_SESSION[SESS_USER_ID] = $argv[1] === 'wrong-account' ? 22 : 21;
$challenge              = FreshAuthentication::begin($_SESSION[SESS_USER_ID], 1001);

if ($argv[1] === 'cancelled') {
	unset($_SESSION['sess_mfa_challenge']);
}
$result = $argv[1] === 'failed' ? LoginResult::failure('invalid response') :
	LoginResult::fromVerifiedAssertion('alice', [], false, $argv[1] === 'stale' ? time() - 300 : time(), $challenge);

$provider = new class(['id' => 1, 'name' => 'fixture', 'type' => 4, 'enabled' => 'on']) extends OpenIdLoginProvider {
	public function complete() : LoginResult {
		return $GLOBALS['result'];
	}
};

ob_start();

try {
	login_sso_complete($provider, 1001);
} catch (\LogicException $e) {
	if ($e->getMessage() !== 'profile') {
		throw $e;
	}
}
ob_end_clean();

if (FreshAuthentication::isFresh(21) !== ($argv[1] === 'success') || ($argv[1] !== 'success' && http_response_code() !== 403)) {
	throw new RuntimeException('Incorrect authentication decision.');
}
print 'ok';
