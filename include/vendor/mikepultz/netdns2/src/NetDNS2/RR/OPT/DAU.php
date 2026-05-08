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
 * RFC 6975 - Signaling Cryptographic Algorithm Understanding in DNS Security Extensions (DNSSEC)
 *
 *  0                       8                      16
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |                  OPTION-CODE                  |
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |                  LIST-LENGTH                  |
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |       ALG-CODE        |        ...            /
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property array<int,int> $alg_code
 */
class DAU extends \NetDNS2\RR\OPT
{
    /**
     * list of assigned values of DNSSEC zone signing algorithms, DS hash algorithms, or NSEC3 hash algorithms (depending
     * on the OPTION-CODE in use) that the client declares to be supported.
     *
     * @var array<int>
     */
    protected array $alg_code = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . implode(' ', $this->alg_code);
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

        $val = unpack('C*', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        foreach($val as $alg_code)
        {
            $this->alg_code[] = $alg_code;
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (count($this->alg_code) > 0)
        {
            $this->option_data   = pack('C*', ...$this->alg_code);
            $this->option_length = count($this->alg_code);
        } else
        {
            $this->option_data   = '';
            $this->option_length = 0;
        }

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
