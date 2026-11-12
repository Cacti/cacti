<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Password fields drawn with 'secret' set never write the stored value into
 * the page. A blank submission keeps the stored value, a typed value replaces
 * it, and only the field's '_clear' checkbox blanks it.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

function secret_field_render(array $field, string $name = 'settings_smtp_password') : string {
	ob_start();
	draw_edit_control($name, $field);

	return (string) ob_get_clean();
}

function secret_field_values(string $html) : array {
	preg_match_all("/<input type='password'[^>]* value='([^']*)'>/", $html, $matches);

	return $matches[1];
}

beforeEach(function () {
	$this->secretRequest = $_REQUEST;
	$this->secretSession = $_SESSION ?? [];
	$_REQUEST            = [];
	$_SESSION            = [];
});

afterEach(function () {
	$_REQUEST = $this->secretRequest;
	$_SESSION = $this->secretSession;
});

test('a stored secret is not rendered and a clear control is offered', function () {
	$html = secret_field_render(['method' => 'textbox_password', 'value' => 'stored-smtp-secret', 'max_length' => 255, 'form_id' => 1, 'secret' => true]);

	expect($html)->not->toContain('stored-smtp-secret')
		->and(secret_field_values($html))->toBe(['', ''])
		->and($html)->toContain("name='settings_smtp_password_confirm'")
		->and($html)->toContain('A value is stored. Leave the field blank to keep it.')
		->and($html)->toContain("name='settings_smtp_password_clear'");
});

test('a default used for a new record is not rendered either', function () {
	$html = secret_field_render(['method' => 'textbox_password', 'value' => '', 'default' => 'main-db-secret', 'max_length' => 64, 'secret' => true], 'dbpass');

	expect($html)->not->toContain('main-db-secret')
		->and(secret_field_values($html))->toBe(['', ''])
		->and($html)->toContain("name='dbpass_clear'");
});

test('an empty secret offers no clear control and noconfirm drops the second field', function () {
	$html = secret_field_render(['method' => 'textbox_password', 'value' => '', 'max_length' => 255, 'noconfirm' => true, 'secret' => true], 'github_access_token');

	expect(secret_field_values($html))->toBe([''])
		->and($html)->not->toContain('_clear')
		->and($html)->not->toContain('github_access_token_confirm');
});

test('a value submitted with a failed save is not echoed back into a secret field', function () {
	$_SESSION[SESS_ERROR_FIELDS] = ['settings_smtp_password' => 'settings_smtp_password'];
	$_SESSION[SESS_FIELD_VALUES] = ['settings_smtp_password' => 'typed-secret', 'settings_smtp_password_confirm' => 'typed-secret'];

	$html = secret_field_render(['method' => 'textbox_password', 'value' => 'stored-smtp-secret', 'max_length' => 255, 'form_id' => 1, 'secret' => true]);

	expect($html)->not->toContain('typed-secret')
		->and($html)->not->toContain('stored-smtp-secret')
		->and($html)->toContain('txtErrorTextBox');
});

test('a password field without secret keeps its existing rendering', function () {
	$html = secret_field_render(['method' => 'textbox_password', 'value' => 'legacy', 'max_length' => 255], 'plugin_password');

	expect(secret_field_values($html))->toBe(['legacy', 'legacy'])
		->and($html)->not->toContain('_clear');
});

test('form_secret_is_stored() follows the value and default rule of form_text_box()', function ($value, $default, $id, $expected) {
	expect(form_secret_is_stored($value, $default, $id))->toBe($expected);
})->with([
	'stored value'               => ['secret', '', 1, true],
	'zero is a value'            => ['0', '', 1, true],
	'empty existing record'      => ['', 'default', 1, false],
	'default for a new record'   => ['', 'default', 0, true],
	'nothing stored, no default' => ['', '', 0, false],
	'null value'                 => [null, '', 0, false],
]);

test('form_secret_value() keeps, replaces or clears the stored value', function (array $request, string $expected) {
	$_REQUEST = $request;

	expect(form_secret_value('settings_smtp_password', 'stored-secret'))->toBe($expected);
})->with([
	'blank keeps'                 => [['settings_smtp_password' => ''], 'stored-secret'],
	'missing keeps'               => [[], 'stored-secret'],
	'typed value replaces'        => [['settings_smtp_password' => 'new-secret'], 'new-secret'],
	'zero replaces'               => [['settings_smtp_password' => '0'], '0'],
	'clear blanks'                => [['settings_smtp_password' => '', 'settings_smtp_password_clear' => 'on'], ''],
	'typed value wins over clear' => [['settings_smtp_password' => 'new-secret', 'settings_smtp_password_clear' => 'on'], 'new-secret'],
]);

describe('login provider secrets', function () {
	beforeEach(function () {
		$this->providerSessions             = $GLOBALS['database_sessions'] ?? [];
		$key                                = $GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default'];
		$this->providerDb                   = new FakeMySQLPDO();
		$GLOBALS['database_sessions'][$key] = $this->providerDb;
		$this->providerDb->exec('CREATE TABLE login_providers (id INTEGER PRIMARY KEY, parameters TEXT)');
		$this->providerDb->exec("INSERT INTO login_providers VALUES (4, '" . json_encode(['specific_password' => 'stored-bind', 'client_secret' => 'stored-client']) . "')");
	});

	afterEach(function () {
		$GLOBALS['database_sessions'] = $this->providerSessions;
	});

	test('the LDAP bind password and OpenID client secret follow the secret field rules', function (string $class, string $key, array $request, string $expected) {
		// 'server' is the one LDAP field that must not be blank
		$_REQUEST = ['id' => '4', 'server' => 'ldap.example'] + $request;

		expect($class::collectParameters()[$key])->toBe($expected);
	})->with([
		'ldap blank keeps'     => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', ['specific_password' => ''], 'stored-bind'],
		'ldap change'          => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', ['specific_password' => 'new-bind'], 'new-bind'],
		'ldap clear'           => [\Cacti\Auth\LdapLoginProvider::class, 'specific_password', ['specific_password_clear' => 'on'], ''],
		'openid blank keeps'   => [\Cacti\Auth\OpenIdLoginProvider::class, 'client_secret', [], 'stored-client'],
		'openid change'        => [\Cacti\Auth\OpenIdLoginProvider::class, 'client_secret', ['client_secret' => 'new-client'], 'new-client'],
		'openid clear'         => [\Cacti\Auth\OpenIdLoginProvider::class, 'client_secret', ['client_secret' => '', 'client_secret_clear' => 'on'], ''],
	]);
});

test('settings and data collector saves keep, replace or clear the stored password', function () {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/SecretFieldSaveMatrix.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR)['passed'])->toBe(15);
});
