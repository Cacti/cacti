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
 * SOA Resource Record - RFC1035 section 3.3.13
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     MNAME                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     RNAME                     /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    SERIAL                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    REFRESH                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     RETRY                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    EXPIRE                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    MINIMUM                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property \NetDNS2\Data\Domain $mname
 * @property \NetDNS2\Data\Mailbox $rname
 * @property int $serial
 * @property int $refresh
 * @property int $retry
 * @property int $expire
 * @property int $minimum
 */
final class SOA extends \NetDNS2\RR
{
    /**
     * The master DNS server
     */
    protected \NetDNS2\Data\Domain $mname;

    /**
     * mailbox of the responsible person
     */
    protected \NetDNS2\Data\Mailbox $rname;

    /**
     * serial number
     */
    protected int $serial;

    /**
     * refresh time
     */
    protected int $refresh;

    /**
     * retry interval
     */
    protected int $retry;

    /**
     * expire time
     */
    protected int $expire;

    /**
     * minimum TTL for any RR in this zone
     */
    protected int $minimum;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->mname . '. ' . $this->rname->display() . '. ' . $this->serial . ' ' . $this->refresh . ' ' . $this->retry . ' ' . $this->expire . ' ' . $this->minimum;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->mname    = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, array_shift($_rdata));
        $this->rname    = new \NetDNS2\Data\Mailbox(\NetDNS2\Data::DATA_TYPE_RFC1035, array_shift($_rdata));

        $this->serial   = intval($this->sanitize(array_shift($_rdata)));
        $this->refresh  = intval($this->sanitize(array_shift($_rdata)));
        $this->retry    = intval($this->sanitize(array_shift($_rdata)));
        $this->expire   = intval($this->sanitize(array_shift($_rdata)));
        $this->minimum  = intval($this->sanitize(array_shift($_rdata)));

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

        $offset = $_packet->offset;

        //
        // parse the names
        //
        $this->mname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $offset);
        $this->rname = new \NetDNS2\Data\Mailbox(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $offset);

        //
        // get the SOA values
        //
        $val = unpack('Na/Nb/Nc/Nd/Ne/', $_packet->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        list('a' => $this->serial, 'b' => $this->refresh, 'c' => $this->retry, 'd' => $this->expire, 'e' => $this->minimum) = (array)$val;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $data = $this->mname->encode($_packet->offset) . $this->rname->encode($_packet->offset) .
            pack('N5', $this->serial, $this->refresh, $this->retry, $this->expire, $this->minimum);

        //
        // the mname and rname encode() calls above already advanced $_packet->offset; this adds the
        // remaining 20 bytes for the five 4-byte integers (serial, refresh, retry, expire, minimum).
        //
        $_packet->offset += 20;

        return $data;
    }
}
