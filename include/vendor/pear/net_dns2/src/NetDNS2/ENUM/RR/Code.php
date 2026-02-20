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

enum Code: int
{
    use \NetDNS2\ENUM\Base;

    case NOERROR    = 0;    // RFC 1035
    case FORMERR    = 1;    // RFC 1035
    case SERVFAIL   = 2;    // RFC 1035
    case NXDOMAIN   = 3;    // RFC 1035
    case NOTIMP     = 4;    // RFC 1035
    case REFUSED    = 5;    // RFC 1035
    case YXDOMAIN   = 6;    // RFC 2136
    case YXRRSET    = 7;    // RFC 2136
    case NXRRSET    = 8;    // RFC 2136
    case NOTAUTH    = 9;    // RFC 2136
    case NOTZONE    = 10;   // RFC 2136
    case DSOTYPENI  = 11;   // RFC 8490

    // 12-15 reserved

    case BADSIG     = 16;   // RFC 2845
    case BADKEY     = 17;   // RFC 2845
    case BADTIME    = 18;   // RFC 2845
    case BADMODE    = 19;   // RFC 2930
    case BADNAME    = 20;   // RFC 2930
    case BADALG     = 21;   // RFC 2930
    case BADTRUNC   = 22;   // RFC 4635
    case BADCOOKIE  = 23;   // RFC 7873

    public function label(): string
    {
        return match($this)
        {
            self::NOERROR       => 'The request completed successfully.',
            self::FORMERR       => 'The name server was unable to interpret the query.',
            self::SERVFAIL      => 'The name server was unable to process this query due to a problem with the name server.',
            self::NXDOMAIN      => 'The domain name referenced in the query does not exist.',
            self::NOTIMP        => 'The name server does not support the requested kind of query.',
            self::REFUSED       => 'The name server refuses to perform the specified operation for policy reasons.',
            self::YXDOMAIN      => 'Name Exists when it should not.',
            self::YXRRSET       => 'RR Set Exists when it should not.',
            self::NXRRSET       => 'RR Set that should exist does not.',
            self::NOTAUTH       => 'Server Not Authoritative for zone.',
            self::NOTZONE       => 'Name not contained in zone.',
            self::DSOTYPENI     => 'DSO-TYPE Not Implemented',

            self::BADSIG        => 'TSIG Signature Failure.',
            self::BADKEY        => 'Key not recognized.',
            self::BADTIME       => 'Signature out of time window.',
            self::BADMODE       => 'Bad TKEY Mode.',
            self::BADNAME       => 'Duplicate key name.',
            self::BADALG        => 'Algorithm not supported.',
            self::BADTRUNC      => 'Bad truncation.',
            self::BADCOOKIE     => 'Bad/missing Server Cookie'
        };
    }
}
