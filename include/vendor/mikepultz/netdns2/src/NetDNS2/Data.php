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

namespace NetDNS2;

abstract class Data implements \Stringable
{
    /**
     * internal encoding types
     */
    public const DATA_TYPE_NONE     = 0;
    public const DATA_TYPE_CANON    = 1;
    public const DATA_TYPE_RFC1035  = 2;
    public const DATA_TYPE_RFC2535  = 3;
    public const DATA_TYPE_IPV4     = 10;
    public const DATA_TYPE_IPV6     = 11;

    /**
     * the encoding type used by this object
     */
    protected int $m_type;

    /**
     * the value stored in this object
     */
    protected string $m_value = '';

    /**
     * an internal hash used for tracking compressed values
     *
     * @var array<string,int>
     */
    public static array $compressed = [];

    /**
     * when true, encode_rfc1035() skips both reading from and writing to the
     * compression table, producing fully-expanded (uncompressed) wire names.
     *
     * RFC 2136 §1.2 forbids compression pointers in UPDATE message RDATA, so
     * \NetDNS2\Packet::get() sets this flag after the question section when
     * encoding an UPDATE packet.
     */
    public static bool $no_compress = false;

    /**
     * create an new intance of the object; this needs
     *
     * @param int   $_type   encoding type
     * @param mixed $_data   a string or \NetDNS2\Packet object to extract a value from
     * @param int   $_offset an offset value used to seek inside the data provided.
     *
     * @throws \NetDNS2\Exception
     */
    public function __construct(int $_type, mixed $_data = null, int &$_offset = -1)
    {
        //
        // store the encoding type
        //
        $this->m_type = $_type;

        //
        // parse as a rdata
        //
        if ( (is_null($_data) == false) && (is_string($_data) == true) && ($_offset != -1) )
        {
            $this->decode($_data, $_offset);

        //
        // store from a \NetDNS2\Packet object
        //
        } elseif ( (is_null($_data) == false) && (($_data instanceof \NetDNS2\Packet) == true) )
        {
            $this->decode($_data->rdata, $_offset);

        //
        // assign as a string
        //
        } elseif ( (is_null($_data) == false) && (gettype($_data) == 'string') )
        {
            $value = $_data;

            //
            // it's important that we trim off the trailing periods for domains and mailboxes, but we shouldn't
            // do this for text strings
            //
            if ( (($this instanceof \NetDNS2\Data\Domain) == true) || (($this instanceof \NetDNS2\Data\Mailbox) == true) )
            {
                $value = rtrim($value, '.');

            //
            // but we should remove leading / trailing quotes from text entries
            //
            } else if (($this instanceof \NetDNS2\Data\Text) == true)
            {
                $value = trim($value, '"');
            }

            if (strlen($value) > 0)
            {
                //
                // if the Intl extension is loaded, then automatically convert the domain if it contains unicode characters; we
                // only use it for the Domain type, and not Text or IPv4/6.
                //
                if ( (extension_loaded('intl') == true) && (($this instanceof \NetDNS2\Data\Domain) == true) )
                {
                    $res = idn_to_ascii($value, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
                    if ($res !== false)
                    {
                        $this->m_value = $res;
                    } else
                    {
                        $this->m_value = $value;
                    }

                } else
                {
                    $this->m_value = $value;
                }
            }

        //
        // copy constructor
        //
        } elseif ( (is_null($_data) == false) && ($_data instanceof \NetDNS2\Data) )
        {
            $this->m_type  = $_data->type();
            $this->m_value = $_data->value();
        }
    }

    /**
     * return the internal value in the magic method
     */
    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * the encoding type
     */
    public function type(): int
    {
        return $this->m_type;
    }

    /**
     * return the internal value
     */
    public function value(): string
    {
        //
        // only convert it if we have the Intl extension installed, and it looks like a Punycode string
        //
        if ( (extension_loaded('intl') == true) && (strpos($this->m_value, 'xn--') !== false) )
        {
            $res = idn_to_utf8($this->m_value);

            return ($res === false) ? $this->m_value : $res;

        } else
        {
            return $this->m_value;
        }
    }

    /**
     * return the internal value length
     */
    public function length(): int
    {
        return strlen($this->value());
    }

    /**
     * underlying derived classes need to implement an encode & decode function
     */
    abstract public function encode(int &$_offset = -1): string;
    abstract protected function decode(string $_rdata, int &$_offset): void;

    /**
     * domain expansion function
     *
     * @param string         $_rdata  the text to extract the values from.
     * @param int            $_offset the offset in the text.
     * @param bool           $_escape if we should escape periods in labels (used for mailboxes)
     * @param array<int,bool> $_seen  pointer offsets already visited, used for cycle detection
     *
     * @return array<int,string>
     */
    protected function _decode(string $_rdata, int &$_offset, bool $_escape = false, array $_seen = []): array
    {
        /**
         * @var array<int,string> $labels
         */
        $labels = [];

        while($_offset < strlen($_rdata))
        {
            $length = ord($_rdata[$_offset++]);

            if ($length <= 0)
            {
                return $labels;

            } elseif ($length < 0x40)
            {
                $label = substr($_rdata, $_offset, $length);

                //
                // this function supports escaping periods in labels - primarily for mailbox
                //
                if ( ($_escape == true) && (strpos($label, '.') !== false) )
                {
                    $res = preg_replace('/(?<!\\\)\./', '\.', $label);
                    if (is_null($res) == false)
                    {
                        $label = $res;
                    }
                }

                $labels[] = $label;
                $_offset += $length;

            } else
            {
                //
                // bounds check the second byte of the pointer before reading it
                //
                if ($_offset >= strlen($_rdata))
                {
                    return $labels;
                }

                $pointer = (($length & 0x3f) << 8) + ord($_rdata[$_offset++]);

                //
                // cycle detection - if we've already followed this pointer, stop to prevent infinite recursion
                //
                if (isset($_seen[$pointer]) == true)
                {
                    return $labels;
                }

                $_seen[$pointer] = true;

                return array_merge($labels, $this->_decode($_rdata, $pointer, $_escape, $_seen));
            }
        }

        return $labels;
    }

    /**
     * canonical encoding
     *
     * @param string $_value the value to encode
     */
    public function encode_canonical(string $_value): string
    {
        if (strlen($_value) == 0)
        {
            return pack('C', 0);
        }

        $labels = explode('.', strtolower($_value));
        $data = '';

        foreach($labels as $label)
        {
            if (strlen($label) > 63)
            {
                throw new \NetDNS2\Exception(
                    sprintf('label "%s" exceeds the 63-octet limit defined in RFC 1035.', $label),
                    \NetDNS2\ENUM\Error::INT_PARSE_ERROR
                );
            }

            $data .= pack('Ca*', strlen($label), $label);
        }

        return $data . pack('x');
    }

    /**
     * compressed names defined in RFC 1035
     *
     * @param string $_value  the value to encode
     * @param int    $_offset used to increment the position in the wire output
     *
     * @throws \NetDNS2\Exception
     */
    protected function encode_rfc1035(string $_value, int &$_offset): string
    {
        if (strlen($_value) == 0)
        {
            $_offset++;
            return pack('C', 0);
        }

        $data = '';

        //
        // use a lookahead to support escaped periods
        //
        $labels = preg_split('/(?<!\\\)\./', $_value);
        if ($labels === false)
        {
            throw new \NetDNS2\Exception('failed to parse local name value.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        while(count($labels) > 0)
        {
            $name = implode('.', $labels);

            if ( (isset(self::$compressed[$name]) === true) && (self::$no_compress == false) )
            {
                $_offset += 2;
                return $data . pack('n', 0xC000 | self::$compressed[$name]);    // 0xC000 first two bits as 1

            } else
            {
                $label = array_shift($labels);

                //
                // RFC 1035 §2.3.4: each label must be 63 octets or fewer
                //
                if (strlen($label) > 63)
                {
                    throw new \NetDNS2\Exception(
                        sprintf('label "%s" exceeds the 63-octet limit defined in RFC 1035.', $label),
                        \NetDNS2\ENUM\Error::INT_PARSE_ERROR
                    );
                }

                $data .= pack('Ca*', strlen($label), $label);
                if ( ($_offset < 0x4000) && (self::$no_compress == false) )
                {
                    self::$compressed[$name] = $_offset;
                    $_offset += strlen($label) + 1;
                }
            }
        }

        $_offset++;

        return $data . pack('x');
    }

    /**
     * uncompressed names defined in RFC 2335
     *
     * @param string $_value  the value to encode
     * @param int    $_offset used to increment the position in the wire output
     *
     * @throws \NetDNS2\Exception
     *
     */
    protected function encode_rfc2535(string $_value, int &$_offset): string
    {
        if (strlen($_value) == 0)
        {
            $_offset++;
            return pack('C', 0);
        }

        $data = '';

        //
        // use a lookahead to support escaped periods
        //
        $labels = preg_split('/(?<!\\\)\./', strtolower($_value));
        if ($labels === false)
        {
            throw new \NetDNS2\Exception('failed to parse local name value.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        foreach($labels as $label)
        {
            if (strlen($label) > 63)
            {
                throw new \NetDNS2\Exception(
                    sprintf('label "%s" exceeds the 63-octet limit defined in RFC 1035.', $label),
                    \NetDNS2\ENUM\Error::INT_PARSE_ERROR
                );
            }

            $data .= pack('Ca*', strlen($label), $label);
        }

        $_offset += strlen($data) + 1;

        return $data . pack('x');
    }
}
