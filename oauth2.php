<?php

include(__DIR__ . '/include/global.php');
require_once(__DIR__ . '/include/vendor/oauth2-client/vendor/autoload.php');

if (read_config_option('settings_how') != 3) {
	cacti_log('WARNING: Trying get OAuth2 token but different mail method is configured');
	die('OAuth is not configured');
}

$clientId = read_config_option('settings_oauth2_client_id');
$clientSecret = read_config_option('settings_oauth2_client_secret');
$redirectUri = read_config_option('settings_oauth2_redirect_uri');
// for azure only
$tenantId = read_config_option('settings_oauth2_tenant_id');

$params = [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri' => $redirectUri,
    'accessType' => 'offline'
];

$options = [];

$providerName = read_config_option('settings_oauth2_provider');

switch ($providerName) {

	case 'google':

		$provider = new League\OAuth2\Client\Provider\Google($params);
		$options = [
			'scope' => [
				'https://mail.google.com/'
			]
		];

		break;

	case 'yahoo':
		$provider = new Hayageek\OAuth2\Client\Provider\Yahoo($params);

		break;

	case 'microsoft':
		$provider = new Stevenmaguire\OAuth2\Client\Provider\Microsoft($params);
		$options = [
			'scope' => [
				'wl.imap',
				'wl.offline_access'
			]
		];

		break;

	case 'azure':
		$params['tenantId'] = $tenantId;

		$provider = new Greew\OAuth2\Client\Provider\Azure($params);
		$options = [
			'scope' => [
				'https://outlook.office.com/SMTP.Send',
				'offline_access'
			]
		];

		break;

	default:
		cacti_log('ERROR: Unknown OAuth2 provider');
		die('Provider missing');

		break;
}

if (!isset($_GET['code'])) { // If we don't have an authorization code then get one
	$authUrl = $provider->getAuthorizationUrl($options);
	$_SESSION['oauth2state'] = $provider->getState();
	header('Location: ' . $authUrl);
	exit;

	//Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && ($_GET['state'] !== $_SESSION['oauth2state']))) {
	unset($_SESSION['oauth2state']);
	exit('Invalid state');
} else { // Try to get an access token (using the authorization code grant)
	$token = $provider->getAccessToken(
		'authorization_code',
		[
			'code' => $_GET['code']
		]
	);

	//Use this to interact with an API on the users behalf
	//Use this to get a new access token if the old one expires
	print __('Refresh Token: ') . $token->getRefreshToken();
	print '<br/>' . __('Store this token in Settings -> Mail/Reporting/DNS -> Oauth2 refresh token');
}

?>