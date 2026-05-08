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
 * WKS Resource Record - RFC1035 section 3.4.2
 *
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                    ADDRESS                    |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |       PROTOCOL        |                       |
 *   +--+--+--+--+--+--+--+--+                       |
 *   |                                               |
 *   /                   <BIT MAP>                   /
 *   /                                               /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property \NetDNS2\Data\IPv4 $address
 * @property int $protocol
 * @property array<int,int> $bitmap
 */
final class WKS extends \NetDNS2\RR
{
    /**
     * The IP address of the service
     */
    protected \NetDNS2\Data\IPv4 $address;

    /**
     * The protocol of the service
     */
    protected int $protocol;

    /**
     * bitmap
     *
     * @var array<int,int>
     */
    protected array $bitmap = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->address . ' ' . $this->protocol . ' ' . implode(' ', $this->bitmap);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->address  = new \NetDNS2\Data\IPv4(array_shift($_rdata) ?? '');
        $this->protocol = intval($this->sanitize(array_shift($_rdata)));

        foreach($_rdata as $value)
        {
            $this->bitmap[] = intval($value);
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

        $offset = 0;
        $this->address = new \NetDNS2\Data\IPv4($this->rdata, $offset);

        //
        // get the address and protocol value
        //
        $val = unpack('Cx', $this->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->protocol) = (array)$val;
        $offset++;

        //
        // unpack the port list bitmap
        //
        $port = 0;

        $val = unpack('C*', $this->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        foreach((array)$val as $set)
        {
            $s = sprintf('%08b', $set);

            for($i=0; $i<8; $i++, $port++)
            {
                if ($s[$i] == '1')
                {
                    $this->bitmap[] = $port;
                }
            }
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->address->length() == 0)
        {
            return '';
        }

        $data = $this->address->encode();
        $data .= pack('C', $this->protocol);

        $ports = [];
        $n = 0;

        foreach($this->bitmap as $port)
        {
            $ports[$port] = 1;

            if ($port > $n)
            {
                $n = $port;
            }
        }
        for($i=0; $i<ceil(($n+1)/8)*8; $i++)
        {
            if (isset($ports[$i]) == false)
            {
                $ports[$i] = 0;
            }
        }

        ksort($ports);

        $string = '';
        $n = 0;

        foreach($ports as $s)
        {
            $string .= $s;
            $n++;

            if ($n == 8)
            {
                $data .= chr(intval(bindec($string)));
                $string = '';
                $n = 0;
            }
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
