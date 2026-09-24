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

/**
 * Public entry point for redirect-based Login Providers (SAML2/OpenID).
 *
 * Unlike LDAP/AD, these providers cannot authenticate from Cacti's own
 * username+password form; the browser is sent to the external Identity
 * Provider and comes back here. Three actions are handled:
 *   - login:    send the browser to the IdP (SamlLoginProvider::initiate() /
 *               OpenIdLoginProvider::initiate())
 *   - acs/callback: validate the IdP's response and complete the login
 *   - metadata: publish this Cacti instance's SAML2 SP metadata (SAML2 only)
 */

require_once('./include/auth.php');

use Cacti\Auth\LoginProviderFactory;
use Cacti\Auth\RedirectLoginProviderInterface;
use Cacti\Auth\SamlLoginProvider;

$realm = gfrv('realm');

if ($realm < 1000) {
	auth_display_custom_error_message(__('Access Denied!  Login Failed.'));

	exit;
}

$provider = LoginProviderFactory::fromRealm((int) $realm);

if (!$provider instanceof RedirectLoginProviderInterface) {
	auth_display_custom_error_message(__('Access Denied!  Login Failed.'));

	exit;
}

switch (grv('action')) {
	case 'metadata':
		if (!$provider instanceof SamlLoginProvider) {
			header('HTTP/1.1 404 Not Found');

			exit;
		}

		header('Content-Type: text/xml');
		print $provider->getMetadata();

		break;
	case 'login':
		$provider->initiate();

		break;
	case 'acs':
	case 'callback':
	case 'sls':
		login_sso_complete($provider, (int) $realm);

		break;
	default:
		header('HTTP/1.1 404 Not Found');

		break;
}

/**
 * Validates the IdP's callback, and on success completes the same
 * session/cookie transition auth_login.php performs for a password login.
 *
 * @param RedirectLoginProviderInterface $provider The provider handling this callback.
 * @param int                            $realm    The login realm (1000 + provider id).
 */
function login_sso_complete(RedirectLoginProviderInterface $provider, int $realm) : void {
	$result = $provider->complete();

	if (!$result->success) {
		cacti_log(sprintf("LOGIN FAILED: SSO Provider '%s' Error from IP address %s: %s", $provider->getName(), get_client_addr(), $result->error), false, 'AUTH');

		auth_display_custom_error_message($result->error !== '' ? $result->error : __('Access Denied!  Login Failed.'));

		exit;
	}

	$user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE username = ?
		AND realm = ?',
		[$result->username, $realm]);

	$templateUserId = method_exists($provider, 'getTemplateUserId') ? $provider->getTemplateUserId() : 0;

	if (!cacti_sizeof($user) && $templateUserId > 0 && $result->username != '') {
		$template = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			[$templateUserId]);

		if (!cacti_sizeof($template)) {
			cacti_log("LOGIN FAILED: Template user id '" . $templateUserId . "' does not exist.", false, 'AUTH');

			auth_display_custom_error_message(__('Access Denied!  Template user id %s does not exist.  Please contact your Administrator.', $templateUserId));

			exit;
		}

		user_copy($template['username'], $result->username, 0, $realm, false, [
			'full_name'     => $result->claims['full_name'] ?? '',
			'email_address' => $result->claims['email'] ?? '',
		]);

		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE username = ?
			AND realm = ?',
			[$result->username, $realm]);
	}

	if (!cacti_sizeof($user)) {
		cacti_log("LOGIN FAILED: user '" . $result->username . "' authenticated but the provider has no template and no existing account.", false, 'AUTH');

		auth_display_custom_error_message(__('Access Denied!  Provider template is not configured.  Please contact your Administrator.'));

		exit;
	}

	if (($user['enabled'] ?? '') != 'on') {
		auth_display_custom_error_message(__('Access Denied!  User account disabled.'));

		exit;
	}

	if (!auth_user_has_access($user)) {
		auth_display_custom_error_message(__('You do not have access to any area of Cacti.  Contact your administrator.'));

		exit;
	}

	if (!cacti_auth_transition((int) $user['id'], 'sso_login')) {
		auth_display_custom_error_message(__('Access Denied! User account locked.'));

		exit;
	}

	$client_addr = get_client_addr();

	cacti_log(sprintf("LOGIN: User '%s' authenticated via SSO Provider '%s' from IP Address '%s'", $user['username'], $provider->getName(), $client_addr), false, 'AUTH');

	db_execute_prepared('INSERT IGNORE INTO user_log
		(username, user_id, result, ip, time)
		VALUES (?, ?, 1, ?, NOW())',
		[$user['username'], $user['id'], $client_addr]);

	$_SESSION[SESS_USER_ID]     = $user['id'];
	$_SESSION[SESS_USER_AGENT]  = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$_SESSION[SESS_CLIENT_ADDR] = $client_addr;

	if ($provider->allowsAuthCookies() && read_config_option('auth_cache_enabled') == 'on') {
		set_auth_cookie($user);
	}

	if (user_setting_exists('user_language', $_SESSION[SESS_USER_ID])) {
		$_SESSION[SESS_USER_LANGUAGE] = read_user_setting('user_language');
	}

	auth_login_redirect($user['login_opts']);
}
