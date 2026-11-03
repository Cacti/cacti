<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryLoginRoutingFixture;

final class Redirect extends \RuntimeException {
}
/** Intercept only the response header before the production function exits. */
function header(string $value): void {
	throw new Redirect($value);
}

function run(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/auth.php', 'auth_login_redirect', __NAMESPACE__);
	$session   = $_SESSION;
	$server    = $_SERVER;
	$error     = [$GLOBALS['error'] ?? null, $GLOBALS['error_msg'] ?? null];
	$threshold = \read_config_option('secpass_lockfailed');
	$username  = 'coverage_route_' . bin2hex(random_bytes(6));
	$user      = (int) \sql_save(['id' => 0, 'username' => $username, 'realm' => 0, 'enabled' => 'on', 'login_opts' => 1], 'user_auth');
	\library_expect($user > 0, true, 'Login routing owns its account');

	try {
		foreach ([false, true] as $console) {
			\db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', [$user]);

			if ($console) {
				\db_execute_prepared('INSERT INTO user_auth_realm (user_id, realm_id) VALUES (?, 8)', [$user]);
			}

			foreach ([['REDIRECT_URL' => CACTI_PATH_URL . 'host.php', 'REDIRECT_QUERY_STRING' => 'action=edit&id=17'], ['REQUEST_URI' => CACTI_PATH_URL . 'host.php?action=edit&id=17']] as $origin) {
				$_SESSION = [SESS_USER_ID => $user];
				\auth_row_cache_purge($user);
				$_SERVER = $server;
				unset($_SERVER['REDIRECT_URL'], $_SERVER['REDIRECT_QUERY_STRING'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']);
				$_SERVER     = array_replace($_SERVER, $origin);
				$destination = null;

				try {
					auth_login_redirect('1');
				} catch (Redirect $response) {
					$destination = $response->getMessage();
				}
				\library_expect($destination, 'Location: ' . ($console ? 'index.php' : 'graph_view.php'), 'A disallowed return page falls back to a page the viewer can use');
				\library_expect($_SESSION[SESS_USER_ID], $user, 'Routing cannot replace the authenticated identity');
			}
		}
		\set_config_option('secpass_lockfailed', '3');
		\db_execute_prepared('UPDATE user_auth SET enabled = ?, failed_attempts = 0 WHERE id = ?', ['', $user]);
		$GLOBALS['error']     = false;
		$GLOBALS['error_msg'] = '';
		\auth_process_lockout($username, 0);
		\library_expect($GLOBALS['error'], true, 'A disabled account receives a login denial');
		\library_expect((int) \db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), 0, 'Disabled accounts do not consume credential attempts');
		$count = (int) \db_fetch_cell('SELECT COUNT(*) FROM user_auth');
		\auth_process_lockout($username . '_missing', 0);
		\library_expect((int) \db_fetch_cell('SELECT COUNT(*) FROM user_auth'), $count, 'Unknown login cannot create an account during lockout handling');
		$missing = (int) \db_fetch_cell('SELECT MAX(id) + 1 FROM user_auth');
		\library_expect(\auth_valid_user($missing), false, 'A nonexistent positive account identity is rejected');
	} finally {
		\db_execute_prepared('DELETE FROM user_log WHERE user_id = ? OR username IN (?, ?)', [$user, $username, $username . '_missing']);
		\user_remove($user);
		\set_config_option('secpass_lockfailed', $threshold);
		$_SESSION                                  = $session;
		$_SERVER                                   = $server;
		[$GLOBALS['error'], $GLOBALS['error_msg']] = $error;
	}
}
