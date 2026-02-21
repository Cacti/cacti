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

namespace NetDNS2\RR\OPT;

/**
 * RFC 7314 - Extension Mechanisms for DNS (EDNS) EXPIRE Option
 */
final class EXPIRE extends \NetDNS2\RR\OPT
{
    /**
     * the expire value (4 bytes)
     */
    protected int $expire = 0;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->timeout;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->option_length == 0)
        {
            return true;
        }

        $val = unpack('Nx', $this->option_data);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->expire) = (array)$val;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // build and add the local data
        //
        if ($this->expire > 0)
        {
            $this->option_length = 4;
            $this->option_data   = pack('N', $this->expire);
        } else
        {
            $this->option_length = 0;
            $this->option_data   = '';
        }

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
