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
 * RT Resource Record - RFC1183 section 3.3
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                preference                     |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /             intermediate-host                 /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property int $preference
 * @property \NetDNS2\Data\Domain $intermediatehost
 */
final class RT extends \NetDNS2\RR
{
    /**
     * the preference of this route
     */
    protected int $preference;

    /**
     * host which will servce as an intermediate in reaching the owner host
     */
    protected \NetDNS2\Data\Domain $intermediatehost;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->preference . ' ' . $this->intermediatehost . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->preference       = intval($this->sanitize(array_shift($_rdata)));
        $this->intermediatehost = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, array_shift($_rdata));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->rdlength == 0)
        {
            return false;
        }

        $val = unpack('nx', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->preference) = (array)$val;
        $offset = $_packet->offset + 2;

        $this->intermediatehost = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->intermediatehost->length() == 0)
        {
            return '';
        }

        $_packet->offset += 2;

        return pack('n', $this->preference) . $this->intermediatehost->encode($_packet->offset);
    }
}
