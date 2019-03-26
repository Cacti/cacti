<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\GoogleAuthenticator;

interface GoogleAuthenticatorInterface
{
    /**
     * @param string $secret
     * @param string $code
     */
    public function checkCode($secret, $code): bool;

    /**
     * NEXT_MAJOR: add the interface typehint to $time and remove deprecation.
     *
     * @param string                                   $secret
     * @param float|string|int|\DateTimeInterface|null $time
     */
    public function getCode($secret, /* \DateTimeInterface */$time = null): string;

    /**
     * NEXT_MAJOR: Remove this method.
     *
     * @param string $user
     * @param string $hostname
     * @param string $secret
     *
     * @deprecated deprecated as of 2.1 and will be removed in 3.0. Use Sonata\GoogleAuthenticator\GoogleQrUrl::generate() instead.
     */
    public function getUrl($user, $hostname, $secret): string;

    public function generateSecret(): string;
}
