<?php

namespace Hayageek\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Yahoo extends AbstractProvider
{
    use BearerAuthorizationTrait;

    const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'xoauth_yahoo_guid';

    /*
    https://developer.yahoo.com/oauth2/guide/flows_authcode/#step-2-get-an-authorization-url-and-authorize-access
    */
    protected $language = "en-us";

    private $imageSize = '192x192';

    public function getBaseAuthorizationUrl()
    {
        return 'https://api.login.yahoo.com/oauth2/request_auth';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://api.login.yahoo.com/oauth2/get_token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        $guid = $token->getResourceOwnerId();

        return 'https://social.yahooapis.com/v1/user/' . $guid . '/profile?format=json';
    }

    /**
     * Get user image from provider
     *
     * @param  array $response
     * @param  AccessToken $token
     *
     * @return array
     */
    protected function getUserImage(array $response, AccessToken $token)
    {
        $guid = $token->getResourceOwnerId();

        $url = 'https://social.yahooapis.com/v1/user/' . $guid . '/profile/image/' . $this->imageSize . '?format=json';

        $request = $this->getAuthenticatedRequest('get', $url, $token);

        $response = $this->getResponse($request);

        return $response;
    }

    protected function getAuthorizationParameters(array $options)
    {
        $params = parent::getAuthorizationParameters($options);

        $params['language'] = isset($options['language']) ? $options['language'] : $this->language;

        return $params;
    }

    protected function getDefaultScopes()
    {
        /*
           No scope is required. scopes are part of APP Settings.
        */
        return [];
    }

    protected function getScopeSeparator()
    {
        return ' ';
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $code = 0;
            $error = $data['error'];

            if (is_array($error)) {
                /*
                   No code is returned in the error
                */
                $code = -1;
                $error = $error['description'];
            }
            throw new IdentityProviderException($error, $code, $data);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        $user = new YahooUser($response);

        $imageUrl = $this->getUserImageUrl($response, $token);

        return $user->setImageURL($imageUrl);
    }

    /**
     * Get user image url from provider, if available
     *
     * @param  array $response
     * @param  AccessToken $token
     *
     * @return string
     */
    protected function getUserImageUrl(array $response, AccessToken $token)
    {
        $image = $this->getUserImage($response, $token);

        if (isset($image['image']['imageUrl'])) {
            return $image['image']['imageUrl'];
        }
        return null;
    }
}
