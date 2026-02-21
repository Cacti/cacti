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
 * EUI64 Resource Record - RFC7043 section 4.1
 *
 *  0                   1                   2                   3
 *  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |                          EUI-64 Address                       |
 * |                                                               |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class EUI64 extends \NetDNS2\RR
{
    /**
     * The EUI64 address, in hex format
     */
    protected string $address;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->address;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $value = $this->sanitize(array_shift($_rdata));

        //
        // re: RFC 7043, the field must be represented as 8 two-digit hex numbers separated by hyphens.
        //
        $a = explode('-', $value);
        if (count($a) != 8)
        {
            return false;
        }

        //
        // make sure they're all hex values
        //
        foreach($a as $i)
        {
            if (ctype_xdigit($i) == false)
            {
                return false;
            }
        }

        //
        // store it
        //
        $this->address = $value;

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

        $this->address = vsprintf('%02x-%02x-%02x-%02x-%02x-%02x-%02x-%02x', (array)unpack('C8', $this->rdata));
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $data = '';

        $a = explode('-', $this->address);
        foreach($a as $b)
        {
            $data .= chr(intval(hexdec($b)));
        }

        $_packet->offset += 8;

        return $data;
    }
}
