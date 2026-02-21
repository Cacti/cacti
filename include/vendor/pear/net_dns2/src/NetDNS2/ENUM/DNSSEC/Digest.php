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

namespace NetDNS2\ENUM\DNSSEC;

enum Digest: int
{
    use \NetDNS2\ENUM\Base;

    case RES                = 0;    // RFC 3658
    case SHA1               = 1;    // RFC 3658
    case SHA256             = 2;    // RFC 4509
    case ECCGOST            = 3;    // RFC 5933 - Deprecated
    case SHA384             = 4;    // RFC 6605
    case ECCGOST12          = 5;    // RFC 9558
    case SM2SM3             = 6;    // RFC 9563

    public function label(): string
    {
        return match($this)
        {
            self::RES       => 'RES',
            self::SHA1      => 'SHA-1',
            self::SHA256    => 'SHA-256',
            self::ECCGOST   => 'ECC-GOST',
            self::SHA384    => 'SHA-384',
            self::ECCGOST12 => 'ECC-GOST12',
            self::SM2SM3    => 'SM3'
        };
    }
}
