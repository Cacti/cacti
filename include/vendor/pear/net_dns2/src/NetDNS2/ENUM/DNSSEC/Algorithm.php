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

enum Algorithm: int
{
    use \NetDNS2\ENUM\Base;

    case DELETE             = 0;    // RFC 4034
    case RSAMD5             = 1;    // RFC 2538 - Not Recommended
    case DH                 = 2;    // RFC 2539
    case DSA                = 3;    // RFC 2536
    case ECC                = 4;    // TBA
    case RSASHA1            = 5;    // RFC 3110
    case DSANSEC3SHA1       = 6;    // RFC 5155
    case RSASHA1NSEC3SHA1   = 7;    // RFC 5155
    case RSASHA256          = 8;    // RFC 5702
    case RSASHA512          = 10;   // RFC 5702
    case ECCGOST            = 12;   // RFC 5933 - Deprecated
    case ECDSAP256SHA256    = 13;   // RFC 6605
    case ECDSAP384SHA384    = 14;   // RFC 6605
    case ED25519            = 15;   // RFC 8080
    case ED448              = 16;   // RFC 8080
    case SM2SM3             = 17;   // RFC 9563
    case ECCGOST12          = 23;   // RFC 9558
    case INDIRECT           = 252;  // RFC 4034
    case PRIVATEDNS         = 253;  // RFC 4034
    case PRIVATEOID         = 254;  // RFC 4034

    public function label(): string
    {
        return match($this)
        {
            self::DELETE            => 'DELETE',
            self::RSAMD5            => 'RSAMD5',
            self::DH                => 'DH',
            self::DSA               => 'DSA',
            self::ECC               => 'ECC',
            self::RSASHA1           => 'RSASHA1',
            self::DSANSEC3SHA1      => 'DSA-NSEC3-SHA1',
            self::RSASHA1NSEC3SHA1  => 'RSASHA1-NSEC3-SHA1',
            self::RSASHA256         => 'RSASHA256',
            self::RSASHA512         => 'RSASHA512',
            self::ECCGOST           => 'ECC-GOST',
            self::ECDSAP256SHA256   => 'ECDSAP256SHA256',
            self::ECDSAP384SHA384   => 'ECDSAP384SHA384',
            self::ED25519           => 'ED25519',
            self::ED448             => 'ED448',
            self::SM2SM3            => 'SM2SM3',
            self::ECCGOST12         => 'ECC-GOST12',
            self::INDIRECT          => 'INDIRECT',
            self::PRIVATEDNS        => 'PRIVATEDNS',
            self::PRIVATEOID        => 'PRIVATEOID',
        };
    }

    //
    // return a matching OpenSSL algo
    //
    public function openssl(): int
    {
        return match($this)
        {
            self::RSAMD5        => OPENSSL_ALGO_MD5,
            self::RSASHA1       => OPENSSL_ALGO_SHA1,
            self::RSASHA256     => OPENSSL_ALGO_SHA256,
            self::RSASHA512     => OPENSSL_ALGO_SHA512,
            default             => throw new \NetDNS2\Exception(sprintf('algorithm %s does not currently have openssl support.', $this->label()), \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM)
        };
    }
}
