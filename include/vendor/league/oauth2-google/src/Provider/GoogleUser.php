<?php

namespace League\OAuth2\Client\Provider;

class GoogleUser implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId()
    {
        return $this->response['sub'];
    }

    /**
     * Get preferred display name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->response['name'];
    }

    /**
     * Get preferred first name.
     *
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->getResponseValue('given_name');
    }

    /**
     * Get preferred last name.
     *
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->getResponseValue('family_name');
    }

    /**
     * Get locale.
     *
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->getResponseValue('locale');
    }

    /**
     * Get email address.
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->getResponseValue('email');
    }

    /**
     * Get email_verified attribute.
     *
     * @return bool|null
     */
    public function getEmailVerified(): ?bool
    {
        return $this->getResponseValue('email_verified');
    }

    /**
     * Returns whether the email is trustable enough to be used for authentication purpose.
     *
     * @see https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
     */
    public function isEmailTrustworthy(): bool
    {
        $email = $this->getEmail();
        if (! $email) {
            return false;
        }
        if ('@gmail.com' === substr($email, -10)) {
            return true;
        }
        if ($this->getHostedDomain() && $this->getEmailVerified()) {
            return true;
        }
        return false;
    }

    /**
     * Get hosted domain.
     *
     * @return string|null
     */
    public function getHostedDomain(): ?string
    {
        return $this->getResponseValue('hd');
    }

    /**
     * Get avatar image URL.
     *
     * @return string|null
     */
    public function getAvatar(): ?string
    {
        return $this->getResponseValue('picture');
    }

    /**
     * Get user data as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->response;
    }

    private function getResponseValue($key)
    {
        return $this->response[$key] ?? null;
    }
}
