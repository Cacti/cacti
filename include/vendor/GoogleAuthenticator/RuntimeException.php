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

/**
 * Contains runtime exception templates.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RuntimeException extends \RuntimeException
{
    public static function InvalidAccountName(string $accountName): self
    {
        return new self(sprintf(
            'The account name may not contain a double colon (:) and may not be an empty string. Given "%s".',
            $accountName
        ));
    }

    public static function InvalidIssuer(string $issuer): self
    {
        return new self(sprintf(
            'The issuer name may not contain a double colon (:) and may not be an empty string. Given "%s".',
            $issuer
        ));
    }

    public static function InvalidSecret(): self
    {
        return new self('The secret name may not be an empty string.');
    }
}

// NEXT_MAJOR: Remove class alias
class_alias('Sonata\GoogleAuthenticator\RuntimeException', 'Google\Authenticator\RuntimeException', false);
