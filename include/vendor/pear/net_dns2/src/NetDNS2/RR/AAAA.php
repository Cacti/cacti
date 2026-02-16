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

namespace NetDNS2\RR;

/**
 * A Resource Record - RFC1035 section 3.4.1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                                               |
 *    |                                               |
 *    |                                               |
 *    |                    ADDRESS                    |
 *    |                                               |
 *    |                   (128 bit)                   |
 *    |                                               |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class AAAA extends \NetDNS2\RR
{
    /**
     * the IPv6 address in the preferred hexadecimal values of the eight 16-bit pieces per RFC1884
     */
    protected \NetDNS2\Data\IPv6 $address;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return strval($this->address);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->address = new \NetDNS2\Data\IPv6(array_shift($_rdata) ?? '');

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet();
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->rdlength != 16)
        {
            return false;
        }

        $offset = 0;

        $this->address = new \NetDNS2\Data\IPv6($this->rdata, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        return $this->address->encode($_packet->offset);
    }
}
