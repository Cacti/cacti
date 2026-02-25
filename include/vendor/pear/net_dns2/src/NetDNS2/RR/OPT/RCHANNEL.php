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
 * RFC 9567 - DNS Error Reporting
 *
 * 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |        OPTION-CODE = 18       |       OPTION-LENGTH           |
 * +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * /                         AGENT DOMAIN                          /
 * +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 */
final class RCHANNEL extends \NetDNS2\RR\OPT
{
    /**
     *
     */
    protected \NetDNS2\Data\Domain $agent_domain;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->agent_domain . '.';
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
        $offset = 0;
        $this->agent_domain = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->option_data, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $this->option_data   = $this->agent_domain->encode();
        $this->option_length = strlen($this->option_data);

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
