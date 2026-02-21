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
 * KX Resource Record - RFC2230 section 3.1
 *
 * This class is almost identical to MX, except that the the exchanger domain is not compressed, it's added as a label
 *
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                  PREFERENCE                   |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   /                   EXCHANGER                   /
 *   /                                               /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class KX extends \NetDNS2\RR
{
    /**
     * the preference for this mail exchanger
     */
    protected int $preference;

    /**
     * the hostname of the mail exchanger
     */
    protected \NetDNS2\Data\Domain $exchange;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->preference . ' ' . $this->exchange . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->preference = intval($this->sanitize(array_shift($_rdata)));
        $this->exchange   = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, array_shift($_rdata));

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

        $val = unpack('nz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('z' => $this->preference) = (array)$val;

        $offset = $_packet->offset + 2;

        $this->exchange = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->exchange->length() == 0)
        {
            return '';
        }

        $_packet->offset += 2;

        return pack('n', $this->preference) . $this->exchange->encode($_packet->offset);
    }
}
