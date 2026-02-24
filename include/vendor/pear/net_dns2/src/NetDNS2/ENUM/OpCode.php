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

enum OpCode: int
{
    use \NetDNS2\ENUM\Base;

    case QUERY          = 0;     // RFC 1035
    case IQUERY         = 1;     // RFC 1035, RFC 3425
    case STATUS         = 2;     // RFC 1035
    case NOTIFY         = 4;     // RFC 1996
    case UPDATE         = 5;     // RFC 2136
    case DSO            = 6;     // RFC 8490

    public function label(): string
    {
        return match($this)
        {
            self::QUERY     => 'Query',
            self::IQUERY    => 'IQuery',
            self::STATUS    => 'Status',
            self::NOTIFY    => 'Notify',
            self::UPDATE    => 'Update',
            self::DSO       => 'DSO'
        };
    }
}
