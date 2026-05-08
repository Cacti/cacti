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

/**
 * This is the base class for DNS Resource Records
 *
 * Each resource record type (defined in RR/*.php) extends this class for base functionality.
 *
 * This class handles parsing and constructing the common parts of the DNS resource records, while the RR specific functionality is handled in each
 * child class.
 *
 * DNS resource record format - RFC1035 section 4.1.3
 *
 *      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                                               |
 *    /                                               /
 *    /                      NAME                     /
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                      TYPE                     |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     CLASS                     |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                      TTL                      |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   RDLENGTH                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--|
 *    /                     RDATA                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
abstract class RR implements \Stringable
{
    /**
     * The name of the resource record
     */
    public \NetDNS2\Data\Domain $name;

    /**
     * The resource record type
     */
    public \NetDNS2\ENUM\RR\Type $type;

    /**
     * The resouce record class
     */
    public \NetDNS2\ENUM\RR\Classes $class;

    /**
     * The UDP length used instead of the class value for OPT records
     */
    public int $udp_length;

    /**
     * The time to live for this resource record
     */
    public int $ttl;

    /**
     * The length of the rdata field
     */
    public int $rdlength = 0;

    /**
     * The resource record specific data as a packed binary string
     */
    public string $rdata = '';

    /**
     * abstract definition - method to return a RR as a string; not to be confused with the __toString() magic method.
     *
     */
    abstract protected function rrToString(): string;

    /**
     * abstract definition - parses a RR from a standard DNS config line
     *
     * @param array<string> $_rdata a string split line of values for the rdata
     *
     * @throws \NetDNS2\Exception
     */
    abstract protected function rrFromString(array $_rdata): bool;

    /**
     * abstract definition - sets a \NetDNS2\RR from a \NetDNS2\Packet object
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet packet to parse the RR from
     *
     * @throws \NetDNS2\Exception
     */
    abstract protected function rrSet(\NetDNS2\Packet &$_packet): bool;

    /**
     * abstract definition - returns a binary packet DNS RR object
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet packet
     *
     * @return string either returns a binary packed string or empty string on failure
     *
     * @throws \NetDNS2\Exception
     */
    abstract protected function rrGet(\NetDNS2\Packet &$_packet): string;

    /**
     * Constructor - builds a new \NetDNS2\RR object
     *
     * @param \NetDNS2\Packet     &$_packet a \NetDNS2\Packet packet or null to create an empty object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(?\NetDNS2\Packet $_packet = null)
    {
        if (is_null($_packet) == false)
        {
            if ($this->set($_packet) == false)
            {
                throw new \NetDNS2\Exception('failed to generate resource record.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }

        } else
        {
            $this->type  = \NetDNS2\ENUM\RR\Type::set(str_replace([ 'NetDNS2\\RR\\RR_', 'NetDNS2\\RR\\' ], '', get_class($this)));
            $this->class = \NetDNS2\ENUM\RR\Classes::set('IN');
            $this->ttl   = 86400;
        }
    }

    /**
     * magic method to handle setting values inside the individual \NetDNS2\RR objects
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __set(string $_name, mixed $_value): void
    {
        if (property_exists(get_called_class(), $_name) == false)
        {
            throw new \NetDNS2\Exception(sprintf('undefined property: %s', $_name), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        //
        // use reflection to look for custom internal types
        //
        $property = new \ReflectionProperty(get_called_class(), $_name);

        //
        // get the type, and make sure it's a instance of a "ReflectionNamedType"; the union and intersection types don't have a getName() function call.
        //
        $type = $property->getType();

        if (($type instanceof \ReflectionNamedType) == false)
        {
            throw new \NetDNS2\Exception(sprintf('property is not accessible via Reflection: %s', $_name), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        switch($type->getName())
        {
            case 'NetDNS2\Data\Domain':
            {
                $this->$_name = new \NetDNS2\Data\Domain($_value->type(), $_value);
            }
            break;
            case 'NetDNS2\Data\Mailbox':
            {
                $this->$_name = new \NetDNS2\Data\Mailbox($_value->type(), $_value);
            }
            break;
            case 'NetDNS2\Data\Text':
            {
                $this->$_name = new \NetDNS2\Data\Text($_value);
            }
            break;
            case 'NetDNS2\Data\IPv4':
            {
                $this->$_name = new \NetDNS2\Data\IPv4($_value);
            }
            break;
            case 'NetDNS2\Data\IPv6':
            {
                $this->$_name = new \NetDNS2\Data\IPv6($_value);
            }
            break;
            default:
            {
                $this->$_name = $_value;
            }
        }
    }

    /**
     * magic method to return values from \NetDNS2\RR objects
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __get(string $_name): mixed
    {
        if (property_exists(get_called_class(), $_name) == false)
        {
            throw new \NetDNS2\Exception(sprintf('undefined property: %s', $_name), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        return $this->$_name;
    }

    /**
     * magic isset() method for \NetDNS2\RR objects
     *
     */
    public function __isset(string $_name): bool
    {
        return ( (property_exists(get_called_class(), $_name) == true) && (isset($this->$_name) === true) ) ? true : false;
    }

    /**
     * magic unset() method for \NetDNS2\RR objects
     *
     * not implemented; unsetting member properties would lead to undefined behavior- why would you do this?
     */

    /**
     * magic __toString() method to return the \NetDNS2\RR object object as a string
     *
     */
    public function __toString(): string
    {
        return strval($this->name) . '. ' . $this->ttl . ' ' . $this->class->label() . ' ' . $this->type->label() . ' ' . $this->rrToString();
    }

    /**
     * return a formatted string; if a string has spaces in it, then return it with double quotes around it, otherwise, return it as it was passed in.
     *
     * @param string $_string the string to format
     *
     */
    public static function formatString(string $_string): string
    {
        return '"' . str_replace('"', '\"', trim($_string, '"')) . '"';
    }

    /**
     * builds an array of strings from an array of chunks of text split by spaces
     *
     * @param array<int,string> $_chunks an array of chunks of text split by spaces
     *
     * @return array<int,string>
     *
     */
    protected function buildString(array $_chunks): array
    {
        $data = [];
        $c = 0;
        $in = false;

        foreach($_chunks as $r)
        {
            $r = trim($r);
            if (strlen($r) == 0)
            {
                continue;
            }

            if ( ($r[0] == '"') && ($r[strlen($r) - 1] == '"') && ($r[strlen($r) - 2] != '\\') )
            {
                $data[$c] = $r;
                ++$c;
                $in = false;

            } elseif ($r[0] == '"')
            {
                $data[$c] = $r;
                $in = true;

            } elseif ( ($r[strlen($r) - 1] == '"') && ($r[strlen($r) - 2] != '\\') )
            {
                $data[$c] .= ' ' . $r;
                ++$c;
                $in = false;

            } else
            {
                if ($in == true)
                {
                    $data[$c] .= ' ' . $r;
                } else
                {
                    $data[$c++] = $r;
                }
            }
        }

        foreach($data as $index => $string)
        {
            $data[$index] = str_replace('\"', '"', trim($string, '"'));
        }

        return $data;
    }

    /**
     * builds a new \NetDNS2\RR object
     *
     * @param \NetDNS2\Packet     &$_packet a \NetDNS2\Packet packet or null to create an empty object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function set(\NetDNS2\Packet &$_packet): bool
    {
        //
        // expand the name
        //
        $this->name = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $_packet->offset);

        //
        // unpack the RR details
        //
        $this->type = \NetDNS2\ENUM\RR\Type::set(ord($_packet->rdata[$_packet->offset++]) << 8 | ord($_packet->rdata[$_packet->offset++]));
        $class      = ord($_packet->rdata[$_packet->offset++]) << 8 | ord($_packet->rdata[$_packet->offset++]);
        $this->ttl  = ord($_packet->rdata[$_packet->offset++]) << 24 | ord($_packet->rdata[$_packet->offset++]) << 16 |
                        ord($_packet->rdata[$_packet->offset++]) << 8 | ord($_packet->rdata[$_packet->offset++]);

        $this->rdlength = ord($_packet->rdata[$_packet->offset++]) << 8 | ord($_packet->rdata[$_packet->offset++]);

        //
        // if the packet length is too small break out
        //
        if ($_packet->rdlength < ($_packet->offset + $this->rdlength))
        {
            return false;
        }

        //
        // for RR OPT (41), the class value includes the requestors UDP payload size, and not a class value
        //
        if ($this->type == \NetDNS2\ENUM\RR\Type::OPT)
        {
            $this->udp_length = intval($class);
        } else
        {
            $this->class = \NetDNS2\ENUM\RR\Classes::set($class);
        }

        $this->rdata = substr($_packet->rdata, $_packet->offset, $this->rdlength);

        //
        // parse the rest of the RR object
        //
        return $this->rrSet($_packet);
    }

    /**
     * returns a binary packed DNS RR object
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet packet used for compressing names
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function get(\NetDNS2\Packet &$_packet): string
    {
        $data  = '';
        $rdata = '';

        //
        // compress the name
        //
        $data = $this->name->encode($_packet->offset);

        //
        // pack the main values
        //
        if ($this->type == \NetDNS2\ENUM\RR\Type::OPT)
        {
            //
            // pre-build the TTL value
            //
            $this->pre_build(); // @phpstan-ignore method.notFound

            //
            // the class value is different for OPT types
            //
            $data .= pack('nnN', $this->type->value, $this->udp_length, $this->ttl);

        } else
        {
            $data .= pack('nnN', $this->type->value, $this->class->value, $this->ttl);
        }

        $_packet->offset += 8;

        //
        // get the RR specific details
        //
        if ($this->rdlength != -1)
        {
            $rdata = $this->rrGet($_packet);
        }

        //
        // add the RR
        //
        if (strlen($rdata) > 0)
        {
            $data .= pack('n', strlen($rdata)) . $rdata;
        } else
        {
            $data .= pack('n', 0);
        }

        $_packet->offset += 2;

        return $data;
    }

    /**
     * Returns the RDATA portion of this RR in canonical wire form (RFC 4034 §6.2).
     *
     * Domain names are uncompressed and lowercase; no compression pointers are used.
     * Called by \NetDNS2\DNSSEC\Validator to reconstruct the RRSIG signed-data block.
     *
     * @throws \NetDNS2\Exception
     */
    public function canonicalRdata(): string
    {
        //
        // clear the compression table so no pointers can form; set the offset high
        // enough (>= 0x4000) that encode_rfc1035() never stores new entries, ensuring
        // every domain name in the RDATA is emitted as fully uncompressed labels.
        //
        \NetDNS2\Data::$compressed = [];

        $tmp         = new \NetDNS2\Packet();
        $tmp->offset = 0x4000;

        return $this->rrGet($tmp);
    }

    /**
     * parses a binary packet, and returns the appropriate \NetDNS2\RR object, based on the RR type of the binary content.
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet packet used for decompressing names
     *
     * @throws \NetDNS2\Exception
     *
     */
    public static function parse(\NetDNS2\Packet &$_packet): ?\NetDNS2\RR
    {
        //
        // validate the packet size
        //
        if ($_packet->rdlength == $_packet->offset)
        {
            return null;
        }
        if ($_packet->rdlength < ($_packet->offset + 10))
        {
            throw new \NetDNS2\Exception('failed to parse resource record: packet too small.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        //
        // store the offset so we don't increment the real value; we need to peek inside the packet just enough to figure
        // out what type it is, and then pass it to the real constructor for parsing.
        //
        $offset = $_packet->offset;

        //
        // expand the name
        //
        $name = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $offset);

        //
        // unpack the RR type
        //
        $type = ord($_packet->rdata[$offset++]) << 8 | ord($_packet->rdata[$offset++]);

        /**
          * @var \NetDNS2\RR $o
          */
        $o = new (\NetDNS2\ENUM\RR\Type::set($type)->class())($_packet);

        //
        // increment the offset for the full object length; the underlying object doesn't increment
        //
        $_packet->offset += $o->rdlength;

        //
        // if it's an EDNS OPT object, then covert it so we can parse the values properly
        //
        if ($o->type == \NetDNS2\ENUM\RR\Type::OPT)
        {
            /**
             * @var \NetDNS2\RR\OPT $o
             */
            if ($o->option_code != \NetDNS2\ENUM\EDNS\Opt::NONE)
            {
                $o = $o->generate_edns($_packet);
            }
        }

        return clone $o;
    }

    /**
     * does some basic sanitization
     */
    public function sanitize(?string $_data, bool $_lowercase = true): string
    {
        if (is_null($_data) == true)
        {
            return '';
        }

        return ($_lowercase == true) ? strtolower(rtrim($_data, " \n\r\t\v\x00.")) : rtrim($_data, " \n\r\t\v\x00.");
    }

    /**
     * parses a standard RR format lines, as defined by rfc1035 (kinda)
     *
     * In our implementation, the domain *must* be specified- format must be
     *
     *        <name> [<ttl>] [<class>] <type> <rdata>
     * or
     *        <name> [<class>] [<ttl>] <type> <rdata>
     *
     * name, title, class and type are parsed by this function, rdata is passed to the RR specific classes for parsing.
     *
     * @param string $_line a standard DNS config line
     *
     * @return \NetDNS2\RR        returns a new \NetDNS2\RR\* object for the given RR
     * @throws \NetDNS2\Exception
     *
     */
    public static function fromString(string $_line): \NetDNS2\RR
    {
        if (strlen($_line) == 0)
        {
            throw new \NetDNS2\Exception('empty config line provided.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        $name  = '';
        $type  = \NetDNS2\ENUM\RR\Type::set('SOA');
        $class = \NetDNS2\ENUM\RR\Classes::set('IN');
        $ttl   = 86400;

        //
        // split the line by spaces
        //
        $values = preg_split('/[\s]+/', $_line);
        if ( ($values === false) || (count((array)$values) < 3) )
        {
            throw new \NetDNS2\Exception('failed to parse config: minimum of name, type and rdata required.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        //
        // assume the first value is the name
        //
        $name = trim(strtolower(array_shift($values)), '.');

        //
        // The next value is either a TTL, Class or Type
        //
        foreach((array)$values as $value)
        {
            switch(true)
            {
                case is_numeric($value):
                {
                    $ttl = intval(array_shift($values) ?? 86400);
                }
                break;
                case (\NetDNS2\ENUM\RR\Classes::exists(strval($value)) == true):
                {
                    $class = \NetDNS2\ENUM\RR\Classes::set(array_shift($values) ?? '');
                }
                break;
                case (\NetDNS2\ENUM\RR\Type::exists(strval($value)) == true):
                {
                    $type = \NetDNS2\ENUM\RR\Type::set(array_shift($values) ?? '');
                    break 2;
                }
                default:
                {
                    throw new \NetDNS2\Exception(sprintf('invalid config line provided: unknown file: %s', $value), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                }
            }
        }

        /**
          * @var \NetDNS2\RR $o
          */
        $o = new ($type->class());

        //
        // set the parsed values
        //
        $o->name  = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $name);
        $o->class = $class;
        $o->ttl   = $ttl;

        //
        // parse the rdata
        //
        if ($o->rrFromString($values) === false)
        {
            throw new \NetDNS2\Exception(sprintf('failed to parse rdata for config: %s', $_line), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        return clone $o;
    }
}
