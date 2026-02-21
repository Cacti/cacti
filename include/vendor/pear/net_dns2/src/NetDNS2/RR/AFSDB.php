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
 * AFSDB Resource Record - RFC1183 section 1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    SUBTYPE                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                    HOSTNAME                   /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class AFSDB extends \NetDNS2\RR
{
    /**
     * The AFSDB sub type
     */
    protected int $subtype;

    /**
     * The AFSDB hostname
     */
    protected \NetDNS2\Data\Domain $hostname;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->subtype . ' ' . $this->hostname . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->subtype  = intval($this->sanitize(array_shift($_rdata)));
        $this->hostname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, array_shift($_rdata));

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
        // unpack the subtype
        //
        $val = unpack('nx', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->subtype) = (array)$val;
        $offset = $_packet->offset + 2;

        $this->hostname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $_packet->offset += 2;

        return pack('n', $this->subtype) . $this->hostname->encode($_packet->offset);
    }
}
