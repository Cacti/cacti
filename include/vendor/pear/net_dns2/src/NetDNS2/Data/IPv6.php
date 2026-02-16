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
final class IPv6 extends \NetDNS2\Data
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
            $_offset += 16;
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
        //
        // PHP's inet_ntop returns IPv6 addresses in their compressed form, but we want to keep with
        // the preferred standard, so we'll parse it manually.
        //
        $ar = unpack('n8', $_rdata, $_offset);
        if ($ar !== false)
        {
            $val = vsprintf('%x:%x:%x:%x:%x:%x:%x:%x', (array)$ar);

            if (\NetDNS2\Client::isIPv6($val) == false)
            {
                throw new \NetDNS2\Exception(sprintf('invalid IPv6 address: %s', $val), \NetDNS2\ENUM\Error::INT_INVALID_IPV6);
            }

            $this->m_value = $val;
            $_offset += 16;

            return;
        }
    }
}
