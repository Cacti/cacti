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
final class IPv4 extends \NetDNS2\Data
{
    public function __construct(mixed $_data = null, int &$_offset = -1)
    {
        parent::__construct(self::DATA_TYPE_IPV4, $_data, $_offset);
    }

    /**
      * encode the stored value and return it
      *
      */
    public function encode(int &$_offset = -1): string
    {
        $val = inet_pton($this->m_value);
        if ($val !== false)
        {
            $_offset += 4;
            return strval($val);
        }

        return '';
    }

    /**
      * decode the value provided and store it locally
      *
      */
    protected function decode(string $_rdata, int &$_offset): void
    {
        $val = inet_ntop(substr($_rdata, $_offset, 4));
        if ($val !== false)
        {
            if (\NetDNS2\Client::isIPv4($val) == false)
            {
                throw new \NetDNS2\Exception(sprintf('invalid IPv4 address: %s', $val), \NetDNS2\ENUM\Error::INT_INVALID_IPV4);
            }

            $this->m_value = strval($val);
            $_offset += 4;
            return;
        }
    }
}
