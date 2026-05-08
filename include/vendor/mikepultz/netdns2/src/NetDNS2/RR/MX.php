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
 * MX Resource Record - RFC1035 section 3.3.9
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                  PREFERENCE                   |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   EXCHANGE                    /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property int $preference
 * @property \NetDNS2\Data\Domain $exchange
 */
final class MX extends \NetDNS2\RR
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
        $this->exchange   = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, array_shift($_rdata));

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

        //
        // parse the preference
        //
        $val = unpack('nz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('z' => $this->preference) = (array)$val;

        //
        // get the exchange entry server)
        //
        $offset = $_packet->offset + 2;

        $this->exchange = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $_packet->offset += 2;

        return pack('n', $this->preference) . $this->exchange->encode($_packet->offset);
    }
}
