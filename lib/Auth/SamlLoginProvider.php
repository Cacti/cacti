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
*/

declare(strict_types = 1);

namespace Cacti\Auth;

use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Settings as SamlSettings;

/**
 * SAML2 Login Provider (Service Provider side), built on onelogin/php-saml.
 *
 * IdP metadata/certificates are pasted directly into the provider's form by
 * the admin rather than fetched from a remote metadata URL, so this class
 * makes no outbound calls of its own and needs no cacti_http() involvement -
 * every SAML message travels through the user's browser, not server-to-server.
 */
class SamlLoginProvider extends AbstractLoginProvider implements RedirectLoginProviderInterface {
	public static function collectParameters(): array {
		return [
			// no-validation: admin-chosen SAML SP entity ID URI, free text
			'sp_entity_id'           => form_input_validate(gnrv('sp_entity_id'), 'sp_entity_id', '', true, 3),
			'name_id_format'         => gnrv('name_id_format'),
			'sign_authn_requests'    => isrv('sign_authn_requests') ? 'on' : '',
			'want_assertions_signed' => isrv('want_assertions_signed') ? 'on' : '',
			// PEM certificate blob; onelogin/php-saml validates the structure
			// itself when the settings are used, not at form-save time.
			// no-validation: PEM certificate blob, validated by onelogin/php-saml itself
			'sp_x509cert'            => form_input_validate(gnrv('sp_x509cert'), 'sp_x509cert', '', true, 3),
			// no-validation: PEM private key blob, an arbitrary secret with no format to enforce here
			'sp_private_key'         => form_input_validate(gnrv('sp_private_key'), 'sp_private_key', '', true, 3),
			// no-validation: admin-entered IdP entity ID URI, free text
			'idp_entity_id'          => form_input_validate(gnrv('idp_entity_id'), 'idp_entity_id', '', true, 3),
			// no-validation: admin-entered IdP SSO URL, used server-side only by onelogin/php-saml
			'idp_sso_url'            => form_input_validate(gnrv('idp_sso_url'), 'idp_sso_url', '', true, 3),
			// no-validation: admin-entered IdP SLO URL, used server-side only by onelogin/php-saml
			'idp_slo_url'            => form_input_validate(gnrv('idp_slo_url'), 'idp_slo_url', '', true, 3),
			// PEM certificate blob; onelogin/php-saml validates the structure
			// itself when the settings are used, not at form-save time.
			// no-validation: PEM certificate blob, validated by onelogin/php-saml itself
			'idp_x509cert'           => form_input_validate(gnrv('idp_x509cert'), 'idp_x509cert', '', true, 3),
			'claim_username'         => gnrv('saml_claim_username'),
			'claim_full_name'        => gnrv('saml_claim_full_name'),
			'claim_email'            => gnrv('saml_claim_email'),
			'group_claim'            => gnrv('saml_group_claim'),
			'group_name'             => gnrv('saml_group_name'),
		];
	}

	public function getButtonLabel(): string {
		return $this->buttonLabel !== '' ? $this->buttonLabel : $this->getName();
	}

	public function initiate(): never {
		$auth = new SamlAuth($this->buildSettings());

		// Capture the AuthnRequest ID so complete() can require the IdP's
		// response carry a matching InResponseTo, closing the unsolicited-
		// assertion login CSRF window (logging a browser in as whatever account
		// the IdP names, even without that browser having requested it).
		$redirectUrl = $auth->login(null, [], false, false, true);

		$_SESSION['sess_saml_request_' . $this->getId()] = [
			'request_id' => $auth->getLastRequestID(),
			// Carried across the IdP redirect since the login form's checkbox
			// state cannot otherwise survive the round trip.
			'remember'   => isrv('remember_me'),
		];

		header('Location: ' . $redirectUrl);

		exit;
	}

	public function complete(): LoginResult {
		$saved = $_SESSION['sess_saml_request_' . $this->getId()] ?? [];

		unset($_SESSION['sess_saml_request_' . $this->getId()]);

		// A missing/expired session entry means this browser never called
		// initiate() for this provider - fail closed rather than passing null
		// to processResponse(), which would skip InResponseTo validation and
		// accept an unsolicited, IdP-initiated assertion (login CSRF).
		if (empty($saved['request_id'])) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$auth = new SamlAuth($this->buildSettings());
		$auth->processResponse($saved['request_id']);

		if ($auth->getErrors()) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		if (!$auth->isAuthenticated()) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$attributes = $auth->getAttributes();
		$username   = (string) $this->firstAttribute($attributes, (string) $this->param('claim_username')) ?: $auth->getNameId();

		if ($username === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$groupClaim  = (string) $this->param('group_claim');
		$memberships = $groupClaim !== '' ? ($attributes[$groupClaim] ?? []) : [];

		if (!$this->groupMembershipAllows($memberships, (string) $this->param('group_name'))) {
			return LoginResult::failure(__('Access Denied!  You are not a member of the required group.'));
		}

		return LoginResult::authenticated($username, [
			'full_name' => (string) $this->firstAttribute($attributes, (string) $this->param('claim_full_name')),
			'email'     => (string) $this->firstAttribute($attributes, (string) $this->param('claim_email')),
		], null, (bool) ($saved['remember'] ?? false));
	}

	/**
	 * Validates an IdP-initiated or SP-initiated Single Logout message and
	 * clears the local Cacti session. Kept separate from complete(): SAML's
	 * SLS binding carries LogoutRequest/LogoutResponse messages, which
	 * processResponse() (built for AuthnResponse) does not understand.
	 */
	public function processLogout(): void {
		$auth = new SamlAuth($this->buildSettings());

		// Let Cacti's own logout.php own session teardown/cookie clearing;
		// this only validates the SAML message itself.
		$auth->processSLO(true);

		if ($auth->getErrors()) {
			cacti_log('LOGIN: SAML SLO error for provider \'' . $this->getName() . '\': ' . implode(', ', $auth->getErrors()), false, 'AUTH');
		}

		header('Location: ' . rtrim((string) read_config_option('base_url'), '/') . '/logout.php');

		exit;
	}

	/** The SP metadata XML this Cacti instance publishes for the IdP to consume. */
	public function getMetadata(): string {
		$settings = new SamlSettings($this->buildSettings(), true);
		$metadata = $settings->getSPMetadata();
		$errors   = $settings->validateMetadata($metadata);

		if (!empty($errors)) {
			throw new \RuntimeException('Invalid SP metadata: ' . implode(', ', $errors));
		}

		return $metadata;
	}

	protected function firstAttribute(array $attributes, string $name): string {
		if ($name === '' || empty($attributes[$name])) {
			return '';
		}

		return (string) reset($attributes[$name]);
	}

	protected function buildSettings(): array {
		$realm       = 1000 + $this->getId();
		$acsUrl      = rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=acs&realm=' . $realm;
		$sloUrl      = rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=sls&realm=' . $realm;
		$entityId    = (string) $this->param('sp_entity_id') ?: rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=metadata&realm=' . $realm;

		return [
			'strict' => true,
			'debug'  => $this->debugEnabled,
			'sp'     => [
				'entityId'                 => $entityId,
				'assertionConsumerService' => [
					'url'     => $acsUrl,
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
				],
				'singleLogoutService' => [
					'url'     => $sloUrl,
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
				],
				'NameIDFormat'  => (string) $this->param('name_id_format', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
				'x509cert'      => (string) $this->param('sp_x509cert'),
				'privateKey'    => (string) $this->param('sp_private_key'),
			],
			'idp' => [
				'entityId'            => (string) $this->param('idp_entity_id'),
				'singleSignOnService' => [
					'url'     => (string) $this->param('idp_sso_url'),
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
				],
				'singleLogoutService' => [
					'url'     => (string) $this->param('idp_slo_url'),
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
				],
				'x509cert' => (string) $this->param('idp_x509cert'),
			],
			'security' => [
				'authnRequestsSigned'  => (bool) $this->param('sign_authn_requests', false),
				// wantMessagesSigned would additionally require the outer SAML Response
				// message itself to be signed - stricter than what the "Require Signed
				// Assertions" checkbox promises, and would reject IdPs that only sign
				// the assertion. Leave it off; wantAssertionsSigned alone covers the UI setting.
				'wantAssertionsSigned' => (bool) $this->param('want_assertions_signed', true),
				'signatureAlgorithm'   => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
				'digestAlgorithm'      => 'http://www.w3.org/2001/04/xmlenc#sha256',
			],
		];
	}
}
