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

namespace NetDNS2\ENUM\EDNS;

enum Opt: int
{
    use \NetDNS2\ENUM\Base;

    case NONE           = 0;
    case LLQ            = 1;        // Not Implemented
    case UL             = 2;        // https://datatracker.ietf.org/doc/draft-ietf-dnssd-update-lease/08/
    case NSID           = 3;        // RFC 5001
    case DAU            = 5;        // RFC 6975
    case DHU            = 6;        // RFC 6975
    case N3U            = 7;        // RFC 6975
    case ECS            = 8;        // RFC 7871
    case EXPIRE         = 9;        // RFC 7314
    case COOKIE         = 10;       // RFC 7873
    case KEEPALIVE      = 11;       // RFC 7828
    case PADDING        = 12;       // RFC 7830
    case CHAIN          = 13;       // RFC 7901
    case KEYTAG         = 14;       // RFC 8145
    case EDE            = 15;       // RFC 8914
    case CLIENTTAG      = 16;       // Not Implemented
    case SERVERTAG      = 17;       // Not Implemented
    case RCHANNEL       = 18;       // RFC 9567
    case ZONEVERSION    = 19;       // RFC 9660
    case UMBRELLAIDENT  = 20292;    // Not Implemented
    case DEVICEID       = 26946;    // Not Implemented

    public function label(): string
    {
        return match($this)
        {
            self::NONE          => '',
            self::LLQ           => 'LLQ',
            self::UL            => 'UL',
            self::NSID          => 'NSID',
            self::DAU           => 'DAU',
            self::DHU           => 'DHU',
            self::N3U           => 'N3U',
            self::ECS           => 'ECS',
            self::EXPIRE        => 'EXPIRE',
            self::COOKIE        => 'COOKIE',
            self::KEEPALIVE     => 'KEEPALIVE',
            self::PADDING       => 'PADDING',
            self::CHAIN         => 'CHAIN',
            self::KEYTAG        => 'KEYTAG',
            self::EDE           => 'EDE',
            self::CLIENTTAG     => 'CLIENTTAG',
            self::SERVERTAG     => 'SERVERTAG',
            self::RCHANNEL      => 'RCHANNEL',
            self::ZONEVERSION   => 'ZONEVERSION',
            self::UMBRELLAIDENT => 'UMBRELLAIDENT',
            self::DEVICEID      => 'DEVICEID',
        };
    }

    //
    // return an ENUM value based on a PHP class name
    //
    public static function class_id(string $_class): self
    {
        foreach(self::cases() as $entry)
        {
            if ($_class == $entry->class())
            {
                return $entry;
            }
        }

        return self::NONE;
    }

    //
    // a PHP class name by supported EDNS type
    //
    public function class(): string
    {
        return match($this)
        {
            self::UL            => 'NetDNS2\RR\OPT\UL',
            self::NSID          => 'NetDNS2\RR\OPT\NSID',
            self::DAU           => 'NetDNS2\RR\OPT\DAU',
            self::DHU           => 'NetDNS2\RR\OPT\DHU',
            self::N3U           => 'NetDNS2\RR\OPT\N3U',
            self::ECS           => 'NetDNS2\RR\OPT\ECS',
            self::EXPIRE        => 'NetDNS2\RR\OPT\EXPIRE',
            self::COOKIE        => 'NetDNS2\RR\OPT\COOKIE',
            self::KEEPALIVE     => 'NetDNS2\RR\OPT\KEEPALIVE',
            self::PADDING       => 'NetDNS2\RR\OPT\PADDING',
            self::CHAIN         => 'NetDNS2\RR\OPT\CHAIN',
            self::KEYTAG        => 'NetDNS2\RR\OPT\KEYTAG',
            self::EDE           => 'NetDNS2\RR\OPT\EDE',
            self::RCHANNEL      => 'NetDNS2\RR\OPT\RCHANNEL',
            self::ZONEVERSION   => 'NetDNS2\RR\OPT\ZONEVERSION',
            default             => ''
        };
    }
}
