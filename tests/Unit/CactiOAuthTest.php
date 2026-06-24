<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiOAuth.php';

use League\OAuth2\Client\Provider\Google;

it('returns null for unknown provider', function () {
    expect(CactiOAuth::getProvider('unknown', []))->toBeNull();
});

it('returns a Google provider correctly', function () {
    $params = [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'redirectUri'  => 'uri'
    ];
    $provider = CactiOAuth::getProvider('google', $params);
    expect($provider)->toBeInstanceOf(Google::class);
});

it('returns a Keycloak provider correctly', function () {
    $params = [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'redirectUri'  => 'uri',
        'authServerUrl' => 'http://localhost:8080/auth',
        'realm'         => 'master'
    ];
    $provider = CactiOAuth::getProvider('keycloak', $params);
    expect($provider)->toBeInstanceOf(\Stevenmaguire\OAuth2\Client\Provider\Keycloak::class);
});

it('returns default options for known providers', function () {
    $options = CactiOAuth::getDefaultOptions('google');
    expect($options)->toHaveKey('scope');
    expect($options['scope'])->toContain('https://mail.google.com/');
});

it('returns empty options for unknown providers', function () {
    expect(CactiOAuth::getDefaultOptions('unknown'))->toBeEmpty();
});

it('returns Microsoft, Azure, Yahoo providers', function () {
    $params = ['clientId' => 'id', 'clientSecret' => 'secret', 'redirectUri' => 'uri'];
    expect(CactiOAuth::getProvider('microsoft', $params))
        ->toBeInstanceOf(\Stevenmaguire\OAuth2\Client\Provider\Microsoft::class);
    expect(CactiOAuth::getProvider('azure', $params + ['tenantId' => 't']))
        ->toBeInstanceOf(\Greew\OAuth2\Client\Provider\Azure::class);
    expect(CactiOAuth::getProvider('yahoo', $params))
        ->toBeInstanceOf(\Hayageek\OAuth2\Client\Provider\Yahoo::class);
});

it('returns expected default scopes per provider', function () {
    expect(CactiOAuth::getDefaultOptions('microsoft')['scope'])->toContain('wl.imap');
    expect(CactiOAuth::getDefaultOptions('azure')['scope'])->toContain('offline_access');
    expect(CactiOAuth::getDefaultOptions('keycloak'))->toBe([]);
});

it('returns null when provider name has wrong case', function () {
    $params = ['clientId' => 'id', 'clientSecret' => 'secret', 'redirectUri' => 'uri'];
    expect(CactiOAuth::getProvider('GOOGLE', $params))->toBeNull();
});

it('returns null for an empty provider name', function () {
    expect(CactiOAuth::getProvider('', []))->toBeNull();
});

it('returns the full Google scope set in defaults', function () {
    expect(CactiOAuth::getDefaultOptions('google')['scope'])->toContain('https://mail.google.com/');
});

it('returns both Microsoft scope entries in defaults', function () {
    $scope = CactiOAuth::getDefaultOptions('microsoft')['scope'];
    expect($scope)->toContain('wl.imap');
    expect($scope)->toContain('wl.offline_access');
});

it('returns both Azure scope entries in defaults', function () {
    $scope = CactiOAuth::getDefaultOptions('azure')['scope'];
    expect($scope)->toContain('https://outlook.office.com/SMTP.Send');
    expect($scope)->toContain('offline_access');
});

it('returns empty defaults for yahoo', function () {
    expect(CactiOAuth::getDefaultOptions('yahoo'))->toBe([]);
});

it('returns empty defaults for keycloak', function () {
    expect(CactiOAuth::getDefaultOptions('keycloak'))->toBe([]);
});

it('returns empty defaults for an unknown provider', function () {
    expect(CactiOAuth::getDefaultOptions('unknown'))->toBe([]);
});

it('returns empty defaults for an empty provider name', function () {
    expect(CactiOAuth::getDefaultOptions(''))->toBe([]);
});

it('runtime OAuth entrypoints load the flat CactiOAuth helper before use', function () {
    $oauth2 = file_get_contents(dirname(__DIR__, 2) . '/oauth2.php');
    $functions = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');

    expect($oauth2)->toContain("require_once(CACTI_PATH_LIBRARY . '/CactiOAuth.php')")
        ->and($oauth2)->toContain('CactiOAuth::getProvider')
        ->and($functions)->toContain("require_once(CACTI_PATH_LIBRARY . '/CactiOAuth.php')")
        ->and($functions)->toContain('CactiOAuth::getProvider');
});
