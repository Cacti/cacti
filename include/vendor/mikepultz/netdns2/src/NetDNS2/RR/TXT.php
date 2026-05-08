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
 * TXT Resource Record - RFC1035 section 3.3.14
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   TXT-DATA                    /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property array<int,\NetDNS2\Data\Text> $text
 */
class TXT extends \NetDNS2\RR
{
    /**
     * an array of the text strings
     *
     * @var array<int,\NetDNS2\Data\Text>
     */
    protected array $text = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        if (count($this->text) == 0)
        {
            return '""';
        }

        return implode(' ', array_map(function($value)
        {
            return \NetDNS2\RR::formatString($value->value());

        }, $this->text));
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $data = $this->buildString($_rdata);

        if (count($data) > 0)
        {
            foreach($data as $value)
            {
                $this->text[] = new \NetDNS2\Data\Text($value);
            }
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

        $offset = $_packet->offset;
        $limit  = $offset + $this->rdlength;

        while($offset < $limit)
        {
            $this->text[] = new \NetDNS2\Data\Text($_packet->rdata, $offset);
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $data = '';

        foreach($this->text as $text)
        {
            $data .= $text->encode();
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
