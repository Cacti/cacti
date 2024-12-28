<?php

require __DIR__ . '/../vendor/autoload.php';

use Hayageek\OAuth2\Client\Provider\Yahoo;

// Replace these with your token settings
// Create a project at https://developer.yahoo.com/apps

$clientId = '<your_client_id>';
$clientSecret = '<yout_client_secret>';
// Change this if you are not using the built-in PHP server
$redirectUri = 'http://myapp.com/index.php';

// Start the session
session_start();

// Initialize the provider
$provider = new Yahoo(compact('clientId', 'clientSecret', 'redirectUri'));

// No HTML for demo, prevents any attempt at XSS
header('Content-Type', 'text/plain');

return $provider;
