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

namespace NetDNS2\Data;

/**
 * class for managing text strings in DNS RR objects
 */
final class Text extends \NetDNS2\Data
{
    public function __construct(mixed $_data = null, int &$_offset = -1)
    {
        parent::__construct(self::DATA_TYPE_CANON, $_data, $_offset);
    }

    /**
      * encode the stored value and return it
      *
      */
    public function encode(int &$_offset = -1): string
    {
        return pack('Ca*', strlen($this->m_value), $this->m_value);
    }

    /**
      * decode the value provided and store it locally
      *
      */
    protected function decode(string $_rdata, int &$_offset): void
    {
        if ($_offset > strlen($_rdata))
        {
            return;
        }

        $len = ord($_rdata[$_offset++]);
        if ($len == 0)
        {
            return;
        }

        if ( ($len + $_offset) > strlen($_rdata))
        {
            $this->m_value = substr($_rdata, $_offset);
        } else
        {
            $this->m_value = substr($_rdata, $_offset, $len);
        }

        $_offset += $len;
    }
}
