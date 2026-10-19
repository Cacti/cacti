<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryProviderFixture;

class_alias(\Cacti\Auth\CredentialLoginProviderInterface::class, __NAMESPACE__ . '\\CredentialLoginProviderInterface');

/** Supply provider outcomes at the protocol boundary while retaining real account persistence. */
class LoginProviderFactory {
	public static function fromRealm(int $realm): \Cacti\Auth\LoginProviderInterface {
		\library_expect($realm, 1000 + $GLOBALS['library_provider_id'], 'Authentication selects the requested enabled provider');

		return $GLOBALS['library_provider'];
	}
}
class Provider implements \Cacti\Auth\CredentialLoginProviderInterface {
	public int $template = 0;
	public \Cacti\Auth\LoginResult $result;
	public array $calls       = [];
	public array $assignments = [];
	public function getType(): int {
		return PROVIDER_TYPE_LDAP;
	}
	public function getId(): int {
		return $GLOBALS['library_provider_id'];
	}
	public function getName(): string {
		return 'Fixture provider';
	}
	public function isEnabled(): bool {
		return true;
	}
	public function allowsAuthCookies(): bool {
		return false;
	}
	public function getTemplateUserId(): int {
		return $this->template;
	}
	public function authenticate(string $username, string $password): \Cacti\Auth\LoginResult {
		$this->calls[] = [$username, $password];

		return $this->result;
	}
	public function applyAutoAssignments(int $id, string $username, array $groups): void {
		$this->assignments[] = [$id, $username, $groups];
	}
}
function run(): void {
	global $_CACTI_REQUEST, $realm, $error, $error_msg;
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/auth.php', 'login_providers_login_process', __NAMESPACE__);
	$state   = [$_SESSION, $_CACTI_REQUEST ?? [], $_REQUEST, $_GET, $_POST, $realm ?? 0, $error ?? false, $error_msg ?? ''];
	$options = [];

	foreach (['auth_method', 'secpass_lockfailed'] as $key) {
		$options[$key] = \read_config_option($key);
	}
	$id = (int) \sql_save(['id' => 0, 'name' => 'Fixture credential provider', 'type' => PROVIDER_TYPE_LDAP, 'enabled' => 'on', 'parameters' => '{}'], 'login_providers');
	\library_expect($id > 0, true, 'Provider fixture owns an enabled realm');
	$GLOBALS['library_provider_id'] = $id;
	$provider                       = new Provider();
	$GLOBALS['library_provider']    = $provider;
	$user                           = (int) \sql_save(['id' => 0, 'username' => 'coverage_provider_user', 'realm' => 1000 + $id, 'enabled' => 'on', 'password' => ''], 'user_auth');
	$template                       = (int) \sql_save(['id' => 0, 'username' => 'coverage_provider_template', 'realm' => 0, 'enabled' => 'on', 'password' => '', 'policy_graphs' => 2], 'user_auth');
	\library_expect($user > 0 && $template > 0, true, 'Provider owns its existing account and provisioning template');

	try {
		\set_config_option('auth_method', (string) AUTH_METHOD_PROVIDERS);
		\set_config_option('secpass_lockfailed', '10');
		\srv('realm', (string) (1000 + $id));
		\srv('login_password', 'Disposable provider password');

		foreach ([false, true] as $credentialFailure) {
			$provider->result = \Cacti\Auth\LoginResult::failure('Fixture directory failure', $credentialFailure);
			$provider->calls  = [];
			$error            = false;
			\library_expect(login_providers_login_process('coverage_provider_user'), [], 'Rejected provider result cannot authenticate');
			\library_expect($error && $error_msg !== '' && ($credentialFailure || $error_msg === 'Fixture directory failure'), true, 'Provider failure reaches the authentication response');
			\library_expect($provider->calls, [['coverage_provider_user', 'Disposable provider password']], 'Provider receives the submitted credential once');
			\library_expect((int) \db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), $credentialFailure ? 1 : 0, 'Only credential failure consumes the account lockout budget');
		}
		$provider->result = \Cacti\Auth\LoginResult::authenticated('coverage_provider_user', ['groups' => ['operators']]);
		$error            = false;
		$result           = login_providers_login_process('coverage_provider_user');
		\library_expect($error, false, 'Admitted provider result leaves no failure status');
		\library_expect((int) $result['id'], $user, 'Successful provider result resolves the existing realm-specific account');
		\library_expect($provider->assignments, [[$user, 'coverage_provider_user', ['operators']]], 'Successful login hands current provider groups to assignment reconciliation');
		$provider->result = \Cacti\Auth\LoginResult::authenticated('coverage_provider_new', ['full_name' => 'Provisioned Fixture', 'email' => 'fixture@example.invalid', 'groups' => []]);

		foreach ([0, 999999] as $missingTemplate) {
			$provider->template = $missingTemplate;
			$error              = false;
			\library_expect(login_providers_login_process('coverage_provider_new'), [], 'New provider identity requires a real configured template');
			\library_expect($error, true, 'Missing provisioning template reports denial');
			\library_expect((int) \db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth WHERE username = ? AND realm = ?', ['coverage_provider_new', 1000 + $id]), 0, 'Rejected provisioning creates no partial account');
		}
		$provider->template = $template;
		$error              = false;
		$result             = login_providers_login_process('coverage_provider_new');
		\library_expect((int) $result['id'] > 0, true, 'Valid provider template creates a real account');
		\library_expect([$result['full_name'], $result['email_address'], (int) $result['realm'], (int) $result['policy_graphs']], ['Provisioned Fixture', 'fixture@example.invalid', 1000 + $id, 2], 'Provisioning preserves identity claims and template policy');
		$again = login_providers_login_process('coverage_provider_new');
		\library_expect((int) $again['id'], (int) $result['id'], 'Repeated provider login reuses the same account');
		\library_expect((int) \db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth WHERE username = ? AND realm = ?', ['coverage_provider_new', 1000 + $id]), 1, 'Provider login cannot duplicate its account');
	} finally {
		foreach (\db_fetch_assoc_prepared('SELECT id FROM user_auth WHERE realm = ?', [1000 + $id]) as $row) {
			\user_remove((int) $row['id']);
		}
		\user_remove($template);
		\db_execute_prepared('DELETE FROM login_providers WHERE id = ?', [$id]);

		foreach ($options as $key => $value) {
			\set_config_option($key, $value);
		}
		[$_SESSION, $_CACTI_REQUEST, $_REQUEST, $_GET, $_POST, $realm, $error, $error_msg] = $state;
		unset($GLOBALS['library_provider'], $GLOBALS['library_provider_id']);
	}
}

/** Blank secret controls retain stored provider configuration through the real form collector. */
function parameters(): void {
	global $_CACTI_REQUEST;
	$state  = [$_SESSION, $_CACTI_REQUEST ?? [], $_REQUEST, $_GET, $_POST];
	$keyRow = \db_fetch_row_prepared('SELECT * FROM settings WHERE name = ?', ['secret_encryption_key']);
	$id     = (int) \sql_save(['id' => 0, 'name' => 'Fixture SAML settings', 'type' => PROVIDER_TYPE_SAML2, 'parameters' => '{}'], 'login_providers');
	\library_expect($id > 0, true, 'SAML settings fixture owns its provider row');

	try {
		foreach (['sp_entity_id' => 'https://sp.example.invalid/', 'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
			'idp_entity_id'         => 'https://idp.example.invalid/', 'idp_sso_url' => 'https://idp.example.invalid/sso', 'idp_slo_url' => 'https://idp.example.invalid/slo',
			'saml_claim_username'   => 'uid', 'saml_claim_full_name' => 'cn', 'saml_claim_email' => 'mail', 'saml_group_claim' => 'groups', 'saml_group_name' => 'operators',
			'sign_authn_requests'   => 'on', 'want_assertions_signed' => 'on'] as $field => $value) {
			\srv($field, $value);
		}
		\srv('id', (string) $id);
		$secret = 'Disposable SAML private-key fixture';

		foreach (['legacy', 'encrypted', 'replace'] as $mode) {
			$stored = ['sp_x509cert' => 'Stored SP certificate', 'idp_x509cert' => 'Stored IdP certificate', 'sp_private_key' => $mode === 'legacy' ? $secret : \cacti_encrypt_secret($secret)];
			\db_execute_prepared('UPDATE login_providers SET parameters = ? WHERE id = ?', [json_encode($stored, JSON_THROW_ON_ERROR), $id]);
			\srv('sp_x509cert', $mode === 'replace' ? 'Replacement SP certificate' : '');
			\srv('idp_x509cert', '');
			\srv('sp_private_key', $mode === 'replace' ? 'Replacement disposable key' : '');
			$result = \Cacti\Auth\LoginProviderFactory::collectParameters(PROVIDER_TYPE_SAML2);
			\library_expect($result['sp_x509cert'], $mode === 'replace' ? 'Replacement SP certificate' : $stored['sp_x509cert'], 'Certificate input preserves omission and explicit replacement');
			\library_expect($result['idp_x509cert'], $stored['idp_x509cert'], 'Blank IdP certificate preserves stored data');
			\library_expect(\cacti_decrypt_secret($result['sp_private_key']) === ($mode === 'replace' ? 'Replacement disposable key' : $secret), true, 'Private key survives blank submission and explicit rotation');
			\library_expect($result['sp_private_key'] !== $secret, true, 'Legacy provider key is encrypted before persistence');

			if ($mode === 'encrypted') {
				\library_expect($result['sp_private_key'] === $stored['sp_private_key'], true, 'Unchanged encrypted key retains its ciphertext');
			}
			\library_expect([$result['claim_username'], $result['claim_full_name'], $result['claim_email'], $result['group_claim'], $result['group_name']], ['uid', 'cn', 'mail', 'groups', 'operators'], 'SAML identity and group mappings retain administrator input');
			\library_expect([$result['sign_authn_requests'], $result['want_assertions_signed']], ['on', 'on'], 'Signing requirements survive form collection');
			\library_expect(\db_fetch_cell_prepared('SELECT parameters FROM login_providers WHERE id = ?', [$id]), json_encode($stored, JSON_THROW_ON_ERROR), 'Form collection does not persist before the save boundary');
		}

		try {
			\Cacti\Auth\LoginProviderFactory::create(['type' => 255]);

			throw new \RuntimeException('Unknown provider was accepted');
		} catch (\InvalidArgumentException $exception) {
			\library_expect(str_contains($exception->getMessage(), '255'), true, 'Unknown provider type fails with its stored identifier');
		}
	} finally {
		\db_execute_prepared('DELETE FROM login_providers WHERE id = ?', [$id]);

		if (!$keyRow) {
			\db_execute_prepared('DELETE FROM settings WHERE name = ?', ['secret_encryption_key']);
			unset($GLOBALS['config'][OPTIONS_CLI]['secret_encryption_key']);
		}
		[$_SESSION, $_CACTI_REQUEST, $_REQUEST, $_GET, $_POST] = $state;
	}
}
