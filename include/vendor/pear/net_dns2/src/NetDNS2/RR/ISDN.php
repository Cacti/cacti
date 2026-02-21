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
 * ISDN Resource Record - RFC1183 section 3.2
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                    ISDN-address               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                    SA                         /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class ISDN extends \NetDNS2\RR
{
    /**
     * ISDN Number
     */
    protected \NetDNS2\Data\Text $isdnaddress;

    /**
     * Sub-Address
     */
    protected \NetDNS2\Data\Text $sa;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return \NetDNS2\RR::formatString($this->isdnaddress->value()) . ' ' . \NetDNS2\RR::formatString($this->sa->value());
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $data = $this->buildString($_rdata);
        if (count($data) >= 1)
        {
            $this->isdnaddress = new \NetDNS2\Data\Text($data[0]);

            if (isset($data[1]) === true)
            {
                $this->sa = new \NetDNS2\Data\Text($data[1]);
            }

            return true;
        }

        return false;
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

        $this->isdnaddress = new \NetDNS2\Data\Text($_packet->rdata, $offset);

        //
        // look for a SA (sub address) - it's optional
        //
        if ( ($this->isdnaddress->length() + 1) < $this->rdlength)
        {
            $this->sa = new \NetDNS2\Data\Text($_packet->rdata, $offset);
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->isdnaddress->length() == 0)
        {
            return '';
        }

        $data = $this->isdnaddress->encode();

        if ($this->sa->length() > 0)
        {
            $data .= $this->sa->encode();
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
