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
 * NID Resource Record - RFC6742 section 2.1
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |          Preference           |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               +
 *  |                             NodeID                            |
 *  +                               +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 * @property int $preference
 * @property string $nodeid
 */
final class NID extends \NetDNS2\RR
{
    /**
     * The preference
     */
    protected int $preference;

    /**
     * The node ID field
     */
    protected string $nodeid;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->preference . ' ' . $this->nodeid;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->preference   = intval($this->sanitize(array_shift($_rdata)));
        $this->nodeid       = $this->sanitize(array_shift($_rdata));

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
        // unpack the values
        //
        $x = unpack('npreference/n4nodeid', $this->rdata);
        if ($x === false)
        {
            return false;
        }

        $this->preference = intval($x['preference']);

        //
        // build the node id; this is displayed as a hex string
        //
        $this->nodeid = dechex($x['nodeid1']) . ':' . dechex($x['nodeid2']) . ':' . dechex($x['nodeid3']) . ':' . dechex($x['nodeid4']);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->nodeid) == 0)
        {
            return '';
        }

        //
        // break out the node id
        //
        $n = explode(':', $this->nodeid);
        if (count($n) != 4)
        {
            return '';
        }

        //
        // pack the data
        //
        $_packet->offset += 10;

        return pack('n5', $this->preference, hexdec($n[0]), hexdec($n[1]), hexdec($n[2]), hexdec($n[3]));
    }
}
