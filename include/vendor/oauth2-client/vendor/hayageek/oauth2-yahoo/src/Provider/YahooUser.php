<?php

namespace Hayageek\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class YahooUser implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $response;


    /**
     * @var image URL
     */
    private $imageUrl;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId()
    {
        return $this->response['profile']['guid'];
    }

    /**
     * Get perferred display name.
     *
     * @return string
     */
    public function getName()
    {
        /*
        nickname is not coming in the response.
        $this->response['profile']['nickname']
        */
        return $this->getFirstName() . " " . $this->getLastName();
    }

    /**
     * Get perferred first name.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->response['profile']['givenName'];
    }

    /**
     * Get perferred last name.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->response['profile']['familyName'];
    }

    /**
     * Get email address.
     *
     * @return string|null
     */
    public function getEmail()
    {
        if (!empty($this->response['profile']['emails'])) {
            return $this->response['profile']['emails'][0]['handle'];
        }
    }

    /**
     * Get avatar image URL.
     *
     * @return string|null
     */
    public function getAvatar()
    {
        return $this->response['imageUrl'];
    }

    public function setImageURL($url)
    {
        $this->response['imageUrl'] = $url;
        return $this;
    }

    /**
     * Get user data as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
