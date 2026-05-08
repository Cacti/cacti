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
 * HINFO Resource Record - RFC1035 section 3.3.2
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                      CPU                      /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                       OS                      /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property \NetDNS2\Data\Text $cpu
 * @property \NetDNS2\Data\Text $os
 */
final class HINFO extends \NetDNS2\RR
{
    /**
     * computer information
     */
    protected \NetDNS2\Data\Text $cpu;

    /**
     * operataing system
     */
    protected \NetDNS2\Data\Text $os;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return \NetDNS2\RR::formatString($this->cpu->value()) . ' ' . \NetDNS2\RR::formatString($this->os->value());
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $data = $this->buildString($_rdata);

        if (count($data) == 2)
        {
            $this->cpu = new \NetDNS2\Data\Text($data[0]);
            $this->os  = new \NetDNS2\Data\Text($data[1]);

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

        $offset = 0;

        $this->cpu = new \NetDNS2\Data\Text($this->rdata, $offset);
        $this->os  = new \NetDNS2\Data\Text($this->rdata, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->cpu->length() == 0)
        {
            return '';
        }

        $data = $this->cpu->encode() . $this->os->encode();

        $_packet->offset += strlen($data);

        return $data;
    }
}
