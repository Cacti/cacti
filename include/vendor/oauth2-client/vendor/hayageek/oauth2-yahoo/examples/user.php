<?php

$provider = require __DIR__ . '/provider.php';

if (!empty($_SESSION['token'])) {
    $token = unserialize($_SESSION['token']);
}

if (empty($token)) {
    header('Location: /');
    exit;
}

try {


    // We got an access token, let's now get the user's details
    $ownerDetails = $provider->getResourceOwner($token);

    // Use these details to create a new profile

    echo 'Name: ' . $ownerDetails->getName() . "<br>";
    echo 'FirstName: ' . $ownerDetails->getFirstName() . "<br>";
    echo 'Lastname: ' . $ownerDetails->getLastName() . "<br>";

    echo 'Email: ' . $ownerDetails->getEmail() . "<br>";
    echo 'Image: ' . $ownerDetails->getAvatar() . "<br>";

} catch (Exception $e) {

    // Failed to get user details
    exit('Something went wrong: ' . $e->getMessage());

}


// Use this to interact with an API on the users behalf
echo "Token: " . $token->getToken() . "<br>";

// Use this to get a new access token if the old one expires
echo "Refresh Token: " . $token->getRefreshToken() . "<br>";

// Number of seconds until the access token will expire, and need refreshing
echo "Expires:" . $token->getExpires() . "<br>";


echo "After Refreshing Token <br>";

$grant = new League\OAuth2\Client\Grant\RefreshToken();
$token = $provider->getAccessToken($grant, ['refresh_token' => $token->getRefreshToken()]);

// Use this to interact with an API on the users behalf
echo "Token: " . $token->getToken() . "<br>";

// Use this to get a new access token if the old one expires
echo "Refresh Token: " . $token->getRefreshToken() . "<br>";

// Number of seconds until the access token will expire, and need refreshing
echo "Expires:" . $token->getExpires() . "<br>";
