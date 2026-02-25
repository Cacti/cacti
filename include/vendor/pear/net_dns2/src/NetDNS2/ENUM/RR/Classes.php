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

// TODO: this is named "Classes" instead of "Class" since "Class" is a reserved word!
enum Classes: int
{
    use \NetDNS2\ENUM\Base;

    case IN    = 1;    // RFC 1035
    case CH    = 3;    // RFC 1035
    case HS    = 4;    // RFC 1035
    case NONE  = 254;  // RFC 2136
    case ANY   = 255;  // RFC 1035

    public function label(): string
    {
        return match($this)
        {
            self::IN    => 'IN',
            self::CH    => 'CH',
            self::HS    => 'HS',
            self::NONE  => 'NONE',
            self::ANY   => 'ANY'
        };
    }
}
