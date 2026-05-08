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
 * RFC 7830 - The EDNS(0) Padding Option
 *
 * 0                       8                      16
 * +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 * |                  OPTION-CODE                  |
 * +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 * |                 OPTION-LENGTH                 |
 * +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 * |        (PADDING) ...        (PADDING) ...     /
 * +-  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -
 *
 * @property string $padding
 */
final class PADDING extends \NetDNS2\RR\OPT
{
    /**
     * raw binary padding bytes (SHOULD be 0x00 per RFC 7830)
     */
    protected string $padding = '';

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length;
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

        $this->padding = $this->option_data;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $this->option_data   = $this->padding;
        $this->option_length = strlen($this->padding);

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
