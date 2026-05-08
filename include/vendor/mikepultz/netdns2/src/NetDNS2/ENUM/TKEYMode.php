<?php declare(strict_types=1);

/**
 * This file is part of the NetDNS2 package.
 *
 * (c) Mike Pultz <mike@mikepultz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace NetDNS2\ENUM;

enum TKEYMode: int
{
    use \NetDNS2\ENUM\Base;

    case RES           = 0;
    case SERV_ASSIGN   = 1;
    case DH            = 2;
    case GSS_API       = 3;
    case RESV_ASSIGN   = 4;
    case KEY_DELE      = 5;

    public function label(): string
    {
        return match($this)
        {
            self::RES           => 'Reserved',
            self::SERV_ASSIGN   => 'Server Assignment',
            self::DH            => 'Diffie-Hellman',
            self::GSS_API       => 'GSS-API',
            self::RESV_ASSIGN   => 'Resolver Assignment',
            self::KEY_DELE      => 'Key Deletion'
        };
    }
}
