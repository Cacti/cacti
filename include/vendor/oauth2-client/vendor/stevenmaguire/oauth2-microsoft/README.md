# Microsoft Provider for OAuth 2.0 Client
[![Latest Version](https://img.shields.io/github/release/stevenmaguire/oauth2-microsoft.svg?style=flat-square)](https://github.com/stevenmaguire/oauth2-microsoft/releases)
[![Build Status](https://img.shields.io/travis/stevenmaguire/oauth2-microsoft/master.svg?style=flat-square)](https://travis-ci.org/stevenmaguire/oauth2-microsoft)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/stevenmaguire/oauth2-microsoft.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-microsoft/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/stevenmaguire/oauth2-microsoft.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-microsoft)
[![Total Downloads](https://img.shields.io/packagist/dt/stevenmaguire/oauth2-microsoft.svg?style=flat-square)](https://packagist.org/packages/stevenmaguire/oauth2-microsoft)
[![Software License](https://img.shields.io/packagist/l/stevenmaguire/oauth2-microsoft.svg?style=flat-square)](LICENSE.md)

This package provides Microsoft OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require stevenmaguire/oauth2-microsoft
```

## Usage

Usage is the same as The League's OAuth client, using `\Stevenmaguire\OAuth2\Client\Provider\Microsoft` as the provider.

### Authorization Code Flow

```php
$provider = new Stevenmaguire\OAuth2\Client\Provider\Microsoft([
    // Required
    'clientId'                  => '{microsoft-client-id}',
    'clientSecret'              => '{microsoft-client-secret}',
    'redirectUri'               => 'https://example.com/callback-url',
    // Optional
    'urlAuthorize'              => 'https://login.windows.net/common/oauth2/authorize',
    'urlAccessToken'            => 'https://login.windows.net/common/oauth2/token',
    'urlResourceOwnerDetails'   => 'https://outlook.office.com/api/v1.0/me'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getFirstname());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

#### Managing Scopes and State

When creating your Microsoft authorization URL, you can specify the state and scopes your application may authorize.

```php
$options = [
    'state' => 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
    'scope' => ['wl.basic', 'wl.signin'] // array or string
];

$authorizationUrl = $provider->getAuthorizationUrl($options);
```
If neither are defined, the provider will utilize internal defaults.

At the time of authoring this documentation, the following scopes are available.

##### Core
- wl.basic
- wl.offline_access
- wl.signin

##### Extended
- wl.birthday
- wl.calendars
- wl.calendars_update
- wl.contacts_birthday
- wl.contacts_create
- wl.contacts_calendars
- wl.contacts_photos
- wl.contacts_skydrive
- wl.emails
- wl.events_create
- wl.imap
- wl.phone_numbers
- wl.photos
- wl.postal_addresses
- wl.skydrive
- wl.skydrive_update
- wl.work_profile
- office.onenote_create


## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/stevenmaguire/oauth2-microsoft/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Steven Maguire](https://github.com/stevenmaguire)
- [All Contributors](https://github.com/stevenmaguire/oauth2-microsoft/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/stevenmaguire/oauth2-microsoft/blob/master/LICENSE) for more information.

