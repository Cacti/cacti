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

enum CertFormat: int
{
    use \NetDNS2\ENUM\Base;

    case PKIX      = 1;
    case SPKI      = 2;
    case PGP       = 3;
    case IPKIX     = 4;
    case ISPKI     = 5;
    case IPGP      = 6;
    case ACPKIX    = 7;
    case IACPKIX   = 8;
    case URI       = 253;
    case OID       = 254;

    public function label(): string
    {
        return match($this)
        {
            self::PKIX      => 'PKIX',
            self::SPKI      => 'SPKI',
            self::PGP       => 'PGP',
            self::IPKIX     => 'IPKIX',
            self::ISPKI     => 'ISPKI',
            self::IPGP      => 'IPGP',
            self::ACPKIX    => 'ACPKIX',
            self::IACPKIX   => 'IACPKIX',
            self::URI       => 'URI',
            self::OID       => 'OID'
        };
    }
}
