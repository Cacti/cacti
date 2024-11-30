<?php


/*
je to funkcni

refresh token si musim ulozit:
1//09MdlTdADX7Y9CgYIARAAGAkSNwF-L9Ir9c12gkC-6PrQ5Fs1NJzanYw80LeAEnth6nmV1VBqNMIaZm0Xnyx-P77ChyorA2uovMA

kdyz ho ztratim, musim ho smazat:
https://myaccount.google.com/u/0/security

udelat to tak, ze ho zatim vypisu
prejmenovat to v nastaveni na refresh token

*/

/**
 * Based on PHPMailer code
 * Get token from provider
 */

namespace PHPMailer\PHPMailer;

//!!pm cesty upravit
require_once('include/vendor/oauth2-client/vendor/autoload.php');


use League\OAuth2\Client\Provider\Google;
//use Hayageek\OAuth2\Client\Provider\Yahoo;
//use Stevenmaguire\OAuth2\Client\Provider\Microsoft;
//use Greew\OAuth2\Client\Provider\Azure;

session_start();


$clientId = '1004677091607-j81m0605pkdja9br74uvgno05m3qoiej.apps.googleusercontent.com';
$clientSecret = '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';


//If this automatic URL doesn't work, set it yourself manually to the URL of this script
$redirectUri = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
//$redirectUri = 'https://cactidevelop.kostax.cz/oauth2.php';
$providerName = 'Google';
$tenantId = '';

$params = [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri' => $redirectUri,
    'accessType' => 'offline'
];

$options = [];

switch ($providerName) {
    case 'Google':
        $provider = new Google($params);
        $options = [
            'scope' => [
                'https://mail.google.com/'
            ]
        ];
        break;
    case 'Yahoo':
        $provider = new Yahoo($params);
        break;
    case 'Microsoft':
        $provider = new Microsoft($params);
        $options = [
            'scope' => [
                'wl.imap',
                'wl.offline_access'
            ]
        ];
        break;
    case 'Azure':
        $params['tenantId'] = $tenantId;

        $provider = new Azure($params);
        $options = [
            'scope' => [
                'https://outlook.office.com/SMTP.Send',
                'offline_access'
            ]
        ];
        break;
}

if (null === $provider) {
    exit('Provider missing');
}
echo "<hr/>";
//$_SESSION['oauth2state'] = '772c43d840ca64f40c94addb443160ce';
var_dump($_SESSION['oauth2state']);

echo "<hr/>";



if (!isset($_GET['code'])) {
    //If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl($options);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;
    //Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['provider']);
  var_dump($_GET);
    exit('Invalid state');
} else {
    unset($_SESSION['provider']);
    //Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken(
        'authorization_code',
        [
            'code' => $_GET['code']
        ]
    );
    //Use this to interact with an API on the users behalf
    //Use this to get a new access token if the old one expires
    echo 'Refresh Token: ', $token->getRefreshToken();
var_dump($token);
}


//var_dump($_SESSION);

?>