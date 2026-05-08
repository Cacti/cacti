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
 * RFC 8145 - Signaling Trust Anchor Knowledge in DNS Security Extensions (DNSSEC)
 *
 *   0                       8                      16
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                  OPTION-CODE                  |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                 OPTION-LENGTH                 |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                    KEY-TAG                    |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                      ...                      /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property array<int,int> $key_tag
 */
final class KEYTAG extends \NetDNS2\RR\OPT
{
    /**
     * the list o fkey tag values
     *
     * @var array<int>
     */
    protected array $key_tag = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . implode(' ', $this->key_tag);
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

        $val = unpack('n*', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        foreach($val as $key_tag)
        {
            $this->key_tag[] = $key_tag;
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (count($this->key_tag) > 0)
        {
            $this->option_data   = pack('n*', ...$this->key_tag);
            $this->option_length = 2 * count($this->key_tag);
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
