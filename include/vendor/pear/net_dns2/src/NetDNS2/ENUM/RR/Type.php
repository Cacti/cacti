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

namespace NetDNS2\ENUM\RR;

enum Type: int
{
    use \NetDNS2\ENUM\Base;

    case SIG0       = 0;        // RFC 2931 - pseudo type
    case A          = 1;        // RFC 1035
    case NS         = 2;        // RFC 1035
    case MD         = 3;        // RFC 1035 - obsolete, Not implemented
    case MF         = 4;        // RFC 1035 - obsolete, Not implemented
    case CNAME      = 5;        // RFC 1035
    case SOA        = 6;        // RFC 1035
    case MB         = 7;        // RFC 1035 - obsolete, Not implemented
    case MG         = 8;        // RFC 1035 - obsolete, Not implemented
    case MR         = 9;        // RFC 1035 - obsolete, Not implemented
    case NULL       = 10;       // RFC 1035
    case WKS        = 11;       // RFC 1035
    case PTR        = 12;       // RFC 1035
    case HINFO      = 13;       // RFC 1035
    case MINFO      = 14;       // RFC 1035 - obsolete, Not implemented
    case MX         = 15;       // RFC 1035
    case TXT        = 16;       // RFC 1035
    case RP         = 17;       // RFC 1183
    case AFSDB      = 18;       // RFC 1183
    case X25        = 19;       // RFC 1183
    case ISDN       = 20;       // RFC 1183
    case RT         = 21;       // RFC 1183
    case NSAP       = 22;       // RFC 1706 - deprecated, Not implemented
    case NSAP_PTR   = 23;       // RFC 1348 - deprecated, Not implemented
    case SIG        = 24;       // RFC 2535
    case KEY        = 25;       // RFC 2535, RFC 2930
    case PX         = 26;       // RFC 2163
    case GPOS       = 27;       // RFC 1712
    case AAAA       = 28;       // RFC 3596
    case LOC        = 29;       // RFC 1876
    case NXT        = 30;       // RFC 2065, obsoleted by by RFC 3755, Not implemented
    case EID        = 31;       // [Patton][Patton1995]
    case NIMLOC     = 32;       // [Patton][Patton1995]
    case SRV        = 33;       // RFC 2782
    case ATMA       = 34;       // Removed; no longer defined.
    case NAPTR      = 35;       // RFC 2915
    case KX         = 36;       // RFC 2230
    case CERT       = 37;       // RFC 4398
    case A6         = 38;       // downgraded to experimental by RFC 3363, Not implemented
    case DNAME      = 39;       // RFC 2672
    case SINK       = 40;       // Not implemented
    case OPT        = 41;       // RFC 2671
    case APL        = 42;       // RFC 3123
    case DS         = 43;       // RFC 4034
    case SSHFP      = 44;       // RFC 4255
    case IPSECKEY   = 45;       // RFC 4025
    case RRSIG      = 46;       // RFC 4034
    case NSEC       = 47;       // RFC 4034
    case DNSKEY     = 48;       // RFC 4034
    case DHCID      = 49;       // RFC 4701
    case NSEC3      = 50;       // RFC 5155
    case NSEC3PARAM = 51;       // RFC 5155
    case TLSA       = 52;       // RFC 6698
    case SMIMEA     = 53;       // RFC 8162
                                // 54 unassigned
    case HIP        = 55;       // RFC 5205
    case NINFO      = 56;       // Not implemented
    case RKEY       = 57;       // Not implemented
    case TALINK     = 58;       //
    case CDS        = 59;       // RFC 7344
    case CDNSKEY    = 60;       // RFC 7344
    case OPENPGPKEY = 61;       // RFC 7929
    case CSYNC      = 62;       // RFC 7477
    case ZONEMD     = 63;       // RFC 8976
    case SVCB       = 64;       // RFC 9460
    case HTTPS      = 65;       // RFC 9460
    case DSYNC      = 66;       // https://datatracker.ietf.org/doc/draft-ietf-dnsop-generalized-notify/09/
    case HHIT       = 67;       // Not Implemented yet
    case BRID       = 68;       // Not Implemented yet
                                // 69 - 98 unassigned
    case SPF        = 99;       // RFC 4408
    case UINFO      = 100;      // no RFC, Not implemented
    case UID        = 101;      // no RFC, Not implemented
    case GID        = 102;      // no RFC, Not implemented
    case UNSPEC     = 103;      // no RFC, Not implemented
    case NID        = 104;      // RFC 6742
    case L32        = 105;      // RFC 6742
    case L64        = 106;      // RFC 6742
    case LP         = 107;      // RFC 6742
    case EUI48      = 108;      // RFC 7043
    case EUI64      = 109;      // RFC 7043
                                // 110 - 127 unassigned
    case NXNAME     = 128;      // Not Implemented yet
                                // 129 - 248 unassigned
    case TKEY       = 249;      // RFC 2930
    case TSIG       = 250;      // RFC 2845
    case IXFR       = 251;      // RFC 1995 - only a full (AXFR) is supported
    case AXFR       = 252;      // RFC 1035
    case MAILB      = 253;      // RFC 883, Not implemented
    case MAILA      = 254;      // RFC 973, Not implemented
    case ANY        = 255;      // RFC 1035 - we support both 'ANY' and '*'
    case URI        = 256;      // RFC 7553
    case CAA        = 257;      // RFC 8659
    case AVC        = 258;      // Application Visibility and Control
    case DOA        = 259;      // Not implemented yet, https://datatracker.ietf.org/doc/draft-durand-doa-over-dns/02/
    case AMTRELAY   = 260;      // RFC 8777
    case RESINFO    = 261;      // RFC 9606
    case WALLET     = 262;      // Not Implemented yet
    case CLA        = 263;      // Not Implemented yet
    case IPN        = 264;      // Not Implemented yet
                                // 265 - 32767 unassigned
    case TA         = 32768;    // same as DS
    case DLV        = 32769;    // RFC 4431
    case TYPE65534  = 65534;    // Private Bind record

    //
    // is this value a meta/pseudo type?
    //
    public function meta(): bool
    {
        return match($this)
        {
            self::OPT, self::TKEY, self::TSIG, self::IXFR,
            self::AXFR, self::MAILB, self::MAILA, self::ANY => true,
            default => false
        };
    }

    //
    // a PHP class name by supported RR type
    //
    public function class(): string
    {
        return match($this)
        {
            self::A          => 'NetDNS2\RR\A',
            self::NS         => 'NetDNS2\RR\NS',
            self::CNAME      => 'NetDNS2\RR\CNAME',
            self::SOA        => 'NetDNS2\RR\SOA',
            self::NULL       => 'NetDNS2\RR\RR_NULL',
            self::WKS        => 'NetDNS2\RR\WKS',
            self::PTR        => 'NetDNS2\RR\PTR',
            self::HINFO      => 'NetDNS2\RR\HINFO',
            self::MX         => 'NetDNS2\RR\MX',
            self::TXT        => 'NetDNS2\RR\TXT',
            self::RP         => 'NetDNS2\RR\RP',
            self::AFSDB      => 'NetDNS2\RR\AFSDB',
            self::X25        => 'NetDNS2\RR\X25',
            self::ISDN       => 'NetDNS2\RR\ISDN',
            self::RT         => 'NetDNS2\RR\RT',
            self::SIG        => 'NetDNS2\RR\SIG',
            self::KEY        => 'NetDNS2\RR\KEY',
            self::PX         => 'NetDNS2\RR\PX',
            self::GPOS       => 'NetDNS2\RR\GPOS',
            self::AAAA       => 'NetDNS2\RR\AAAA',
            self::LOC        => 'NetDNS2\RR\LOC',
            self::EID        => 'NetDNS2\RR\EID',
            self::NIMLOC     => 'NetDNS2\RR\NIMLOC',
            self::SRV        => 'NetDNS2\RR\SRV',
            self::NAPTR      => 'NetDNS2\RR\NAPTR',
            self::KX         => 'NetDNS2\RR\KX',
            self::CERT       => 'NetDNS2\RR\CERT',
            self::DNAME      => 'NetDNS2\RR\DNAME',
            self::OPT        => 'NetDNS2\RR\OPT',
            self::APL        => 'NetDNS2\RR\APL',
            self::DS         => 'NetDNS2\RR\DS',
            self::SSHFP      => 'NetDNS2\RR\SSHFP',
            self::IPSECKEY   => 'NetDNS2\RR\IPSECKEY',
            self::RRSIG      => 'NetDNS2\RR\RRSIG',
            self::NSEC       => 'NetDNS2\RR\NSEC',
            self::DNSKEY     => 'NetDNS2\RR\DNSKEY',
            self::DHCID      => 'NetDNS2\RR\DHCID',
            self::NSEC3      => 'NetDNS2\RR\NSEC3',
            self::NSEC3PARAM => 'NetDNS2\RR\NSEC3PARAM',
            self::TLSA       => 'NetDNS2\RR\TLSA',
            self::SMIMEA     => 'NetDNS2\RR\SMIMEA',
            self::HIP        => 'NetDNS2\RR\HIP',
            self::TALINK     => 'NetDNS2\RR\TALINK',
            self::CDS        => 'NetDNS2\RR\CDS',
            self::CDNSKEY    => 'NetDNS2\RR\CDNSKEY',
            self::OPENPGPKEY => 'NetDNS2\RR\OPENPGPKEY',
            self::CSYNC      => 'NetDNS2\RR\CSYNC',
            self::ZONEMD     => 'NetDNS2\RR\ZONEMD',
            self::SVCB       => 'NetDNS2\RR\SVCB',
            self::HTTPS      => 'NetDNS2\RR\HTTPS',
            self::DSYNC      => 'NetDNS2\RR\DSYNC',
            self::SPF        => 'NetDNS2\RR\SPF',
            self::NID        => 'NetDNS2\RR\NID',
            self::L32        => 'NetDNS2\RR\L32',
            self::L64        => 'NetDNS2\RR\L64',
            self::LP         => 'NetDNS2\RR\LP',
            self::EUI48      => 'NetDNS2\RR\EUI48',
            self::EUI64      => 'NetDNS2\RR\EUI64',
            self::TKEY       => 'NetDNS2\RR\TKEY',
            self::TSIG       => 'NetDNS2\RR\TSIG',
            self::ANY        => 'NetDNS2\RR\ANY',
            self::URI        => 'NetDNS2\RR\URI',
            self::CAA        => 'NetDNS2\RR\CAA',
            self::AVC        => 'NetDNS2\RR\AVC',
            self::AMTRELAY   => 'NetDNS2\RR\AMTRELAY',
            self::RESINFO    => 'NetDNS2\RR\RESINFO',
            self::TA         => 'NetDNS2\RR\TA',
            self::DLV        => 'NetDNS2\RR\DLV',
            self::TYPE65534  => 'NetDNS2\RR\TYPE65534',
            default          => throw new \NetDNS2\Exception(sprintf('unknown or un-supported resource record type: %d', $this->value), \NetDNS2\ENUM\Error::INT_INVALID_TYPE)
        };
    }

    public function label(): string
    {
        return match($this)
        {
            self::SIG0       => 'SIG0',
            self::A          => 'A',
            self::NS         => 'NS',
            self::MD         => 'MD',
            self::MF         => 'MF',
            self::CNAME      => 'CNAME',
            self::SOA        => 'SOA',
            self::MB         => 'MB',
            self::MG         => 'MG',
            self::MR         => 'MR',
            self::NULL       => 'NULL',
            self::WKS        => 'WKS',
            self::PTR        => 'PTR',
            self::HINFO      => 'HINFO',
            self::MINFO      => 'MINFO',
            self::MX         => 'MX',
            self::TXT        => 'TXT',
            self::RP         => 'RP',
            self::AFSDB      => 'AFSDB',
            self::X25        => 'X25',
            self::ISDN       => 'ISDN',
            self::RT         => 'RT',
            self::NSAP       => 'NSAP',
            self::NSAP_PTR   => 'NSAP_PTR',
            self::SIG        => 'SIG',
            self::KEY        => 'KEY',
            self::PX         => 'PX',
            self::GPOS       => 'GPOS',
            self::AAAA       => 'AAAA',
            self::LOC        => 'LOC',
            self::NXT        => 'NXT',
            self::EID        => 'EID',
            self::NIMLOC     => 'NIMLOC',
            self::SRV        => 'SRV',
            self::ATMA       => 'ATMA',
            self::NAPTR      => 'NAPTR',
            self::KX         => 'KX',
            self::CERT       => 'CERT',
            self::A6         => 'A6',
            self::DNAME      => 'DNAME',
            self::SINK       => 'SINK',
            self::OPT        => 'OPT',
            self::APL        => 'APL',
            self::DS         => 'DS',
            self::SSHFP      => 'SSHFP',
            self::IPSECKEY   => 'IPSECKEY',
            self::RRSIG      => 'RRSIG',
            self::NSEC       => 'NSEC',
            self::DNSKEY     => 'DNSKEY',
            self::DHCID      => 'DHCID',
            self::NSEC3      => 'NSEC3',
            self::NSEC3PARAM => 'NSEC3PARAM',
            self::TLSA       => 'TLSA',
            self::SMIMEA     => 'SMIMEA',
            self::HIP        => 'HIP',
            self::NINFO      => 'NINFO',
            self::RKEY       => 'RKEY',
            self::TALINK     => 'TALINK',
            self::CDS        => 'CDS',
            self::CDNSKEY    => 'CDNSKEY',
            self::OPENPGPKEY => 'OPENPGPKEY',
            self::CSYNC      => 'CSYNC',
            self::ZONEMD     => 'ZONEMD',
            self::SVCB       => 'SVCB',
            self::HTTPS      => 'HTTPS',
            self::DSYNC      => 'DSYNC',
            self::HHIT       => 'HHIT',
            self::BRID       => 'BRID',
            self::SPF        => 'SPF',
            self::UINFO      => 'UINFO',
            self::UID        => 'UID',
            self::GID        => 'GID',
            self::UNSPEC     => 'UNSPEC',
            self::NID        => 'NID',
            self::L32        => 'L32',
            self::L64        => 'L64',
            self::LP         => 'LP',
            self::EUI48      => 'EUI48',
            self::EUI64      => 'EUI64',
            self::NXNAME     => 'NXNAME',
            self::TKEY       => 'TKEY',
            self::TSIG       => 'TSIG',
            self::IXFR       => 'IXFR',
            self::AXFR       => 'AXFR',
            self::MAILB      => 'MAILB',
            self::MAILA      => 'MAILA',
            self::ANY        => 'ANY',
            self::URI        => 'URI',
            self::CAA        => 'CAA',
            self::AVC        => 'AVC',
            self::DOA        => 'DOA',
            self::AMTRELAY   => 'AMTRELAY',
            self::RESINFO    => 'RESINFO',
            self::WALLET     => 'WALLET',
            self::CLA        => 'CLA',
            self::IPN        => 'IPN',
            self::TA         => 'TA',
            self::DLV        => 'DLV',
            self::TYPE65534  => 'TYPE65534'
        };
    }
}
