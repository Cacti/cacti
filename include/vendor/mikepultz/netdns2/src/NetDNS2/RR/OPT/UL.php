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
 * RFC 9664 - EDNS(0) option to negotiate Leases on DNS Updates
 *
 * @property int $lease
 * @property int $key_lease
 */
final class UL extends \NetDNS2\RR\OPT
{
    /**
     * desired lease (request) or granted lease (response), in seconds
     */
    protected int $lease = 0;

    /**
     * optional desired (or granted) lease for KEY records, in seconds
     */
    protected int $key_lease = 0;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->lease;

        if ($this->key_lease > 0)
        {
            $out .= ' ' . $this->key_lease;
        }

        return $out;
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
            return false;
        }

        //
        // if the length is 8, then there's both a lease and a key lease value
        //
        if ($this->option_length == 8)
        {
            $val = unpack('Nx/Ny', $this->option_data);
            if ($val === false)
            {
                return false;
            }

            list('x' => $this->lease, 'y' => $this->key_lease) = (array)$val;

        //
        // if it's 4, then there's only the lease value
        //
        } elseif ($this->option_length == 4)
        {
            $val = unpack('Nx', $this->option_data);
            if ($val === false)
            {
                return false;
            }

            list('x' => $this->lease) = (array)$val;
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // set the option length for the parent class
        //
        $this->option_length = ($this->key_lease > 0) ? 8 : 4;

        //
        // build and add the local data
        //
        $this->option_data = pack('N', $this->lease);
        if ($this->key_lease > 0)
        {
            $this->option_data .= pack('N', $this->key_lease);
        }

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
