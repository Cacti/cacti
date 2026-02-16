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
 * CSYNC Resource Record - RFC 7477 seciond 2.1.1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                  SOA Serial                   |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    Flags                      |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                 Type Bit Map                  /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class CSYNC extends \NetDNS2\RR
{
    /**
     * serial number
     */
    protected int $serial;

    /**
     * flags; this value should not be accessed directly; the $immediate and $soaminimum values are used to
     * build the flags value on the way in/out of the object.
     */
    protected int $flags;

    /**
     * flags parsed from the $flags value; currently only two are supported
     */
    protected bool $immediate;      // 0x0001
    protected bool $soaminimum;     // 0x0002

    /**
     * array of RR type names
     *
     * @var array<int,string>
     */
    protected array $type_bit_maps = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        //
        // pack the flags
        //
        $this->flags = 0;
        $this->flags |= ($this->immediate == true) ? 0x0001 : 0;
        $this->flags |= ($this->soaminimum == true) ? 0x0002 : 0;

        return $this->serial . ' ' . $this->flags . ' ' . implode(' ', $this->type_bit_maps);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->serial = intval($this->sanitize(array_shift($_rdata)));
        $this->flags  = intval($this->sanitize(array_shift($_rdata)));

        //
        // extract the flags
        //
        $this->immediate  = ($this->flags & 0x0001) ? true : false;
        $this->soaminimum = ($this->flags & 0x0002) ? true : false;

        foreach($_rdata as $data)
        {
            $this->type_bit_maps[] = strtoupper($this->sanitize($data));
        }

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
        // unpack the serial and flags values
        //
        $val = unpack('Nx/ny', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->serial, 'y' => $this->flags) = (array)$val;

        //
        // extract the flags
        //
        $this->immediate  = ($this->flags & 0x0001) ? true : false;
        $this->soaminimum = ($this->flags & 0x0002) ? true : false;

        //
        // parse out the RR bitmap
        //
        $this->type_bit_maps = \NetDNS2\BitMap::bitMapToArray(substr($this->rdata, 6));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // pack the flags
        //
        $this->flags = 0;
        $this->flags |= ($this->immediate == true) ? 0x0001 : 0;
        $this->flags |= ($this->soaminimum == true) ? 0x0002 : 0;

        //
        // pack the serial and flags values
        //
        $data = pack('Nn', $this->serial, $this->flags) . \NetDNS2\BitMap::arrayToBitMap($this->type_bit_maps);

        //
        // advance the offset
        //
        $_packet->offset += strlen($data);

        return $data;
    }
}
