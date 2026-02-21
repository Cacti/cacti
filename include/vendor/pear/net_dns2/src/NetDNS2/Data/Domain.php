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
 * container to store domain names, encoded in different formats for wire/presentation
 */
final class Domain extends \NetDNS2\Data
{
    /**
      * encode the stored value and return it
      *
      * @throws \NetDNS2\Exception
      */
    public function encode(int &$_offset = -1): string
    {
        switch($this->m_type)
        {
            case self::DATA_TYPE_CANON:
            {
                return $this->encode_canonical($this->m_value);
            }
            case self::DATA_TYPE_RFC1035:
            {
                return $this->encode_rfc1035($this->m_value, $_offset);
            }
            case self::DATA_TYPE_RFC2535:
            {
                return $this->encode_rfc2535($this->m_value, $_offset);
            }
        }

        throw new \NetDNS2\Exception('invalid domain encoding type.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
    }

    /**
      * decode the value provided and store it locally
      *
      */
    protected function decode(string $_rdata, int &$_offset): void
    {
        $this->m_value = implode('.', $this->_decode($_rdata, $_offset));
    }
}
