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
 * AMTRELAY Resource Record - RFC8777 section 4.2
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |   precedence  |D|    type     |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               +
 *  ~                            relay                              ~
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class AMTRELAY extends \NetDNS2\RR
{
    /**
     * type definitions that match the "type" field below
     */
    public const AMTRELAY_TYPE_NONE    = 0;
    public const AMTRELAY_TYPE_IPV4    = 1;
    public const AMTRELAY_TYPE_IPV6    = 2;
    public const AMTRELAY_TYPE_DOMAIN  = 3;

    /**
     * the precedence for this record
     */
    protected int $precedence;

    /**
     * "Discovery Optional" flag
     */
    protected int $discovery;

    /**
     * The type field indicates the format of the information that is stored in the relay field.
     */
    protected int $relay_type;

    /**
     * The relay field is the address or domain name of the AMT relay.
     */
    protected \NetDNS2\Data $relay;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->precedence . ' ' . $this->discovery . ' ' . $this->relay_type . ' ' . $this->relay;

        //
        // 4.3.1 - If the relay type field is 0, the relay field MUST be ".".
        //
        if ( ($this->relay_type == self::AMTRELAY_TYPE_NONE) || ($this->relay_type == self::AMTRELAY_TYPE_DOMAIN) )
        {
            $out .= '.';
        }

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        //
        // extract the values from the array
        //
        $this->precedence = intval($this->sanitize(array_shift($_rdata)));
        $this->discovery  = intval($this->sanitize(array_shift($_rdata)));
        $this->relay_type = intval($this->sanitize(array_shift($_rdata)));

        //
        // if there's anything else other than 0 in the discovery value, then force it to one, so that it effectively is either "true" or "false".
        //
        if ($this->discovery != 0)
        {
            $this->discovery = 1;
        }

        //
        // validate the type & relay values
        //
        switch($this->relay_type)
        {
            case self::AMTRELAY_TYPE_NONE:
            {
                $this->relay = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, '');
            }
            break;
            case self::AMTRELAY_TYPE_IPV4:
            {
                $this->relay = new \NetDNS2\Data\IPv4($this->sanitize(array_shift($_rdata) ?? ''));
            }
            break;
            case self::AMTRELAY_TYPE_IPV6:
            {
                $this->relay = new \NetDNS2\Data\IPv6($this->sanitize(array_shift($_rdata) ?? ''));
            }
            break;
            case self::AMTRELAY_TYPE_DOMAIN:
            {
                $this->relay = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->sanitize(array_shift($_rdata) ?? ''));
            }
            break;
            default:
            {
                return false;
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

        //
        // parse off the first two octets
        //
        $val = unpack('Cx/Cy', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->precedence, 'y' => $args) = (array)$val;

        $this->discovery  = ($args >> 7) & 0x1;
        $this->relay_type = $args & 0xf;

        $offset = 2;

        //
        // parse the relay value based on the type
        //
        switch($this->relay_type)
        {
            case self::AMTRELAY_TYPE_NONE:
            {
                $this->relay = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, '');
            }
            break;
            case self::AMTRELAY_TYPE_IPV4:
            {
                $this->relay = new \NetDNS2\Data\IPv4($this->rdata, $offset);
            }
            break;
            case self::AMTRELAY_TYPE_IPV6:
            {
                $this->relay = new \NetDNS2\Data\IPv6($this->rdata, $offset);
            }
            break;
            case self::AMTRELAY_TYPE_DOMAIN:
            {
                $this->relay = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->rdata, $offset);
            }
            break;
            default:
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // pack the precedence, discovery, and type
        //
        $data = pack('CC', $this->precedence, ($this->discovery << 7) | $this->relay_type);

        //
        // add the relay data based on the type
        //
        switch($this->relay_type)
        {
            case self::AMTRELAY_TYPE_NONE:
            {
                // add nothing
            }
            break;
            case self::AMTRELAY_TYPE_IPV4:
            case self::AMTRELAY_TYPE_IPV6:
            case self::AMTRELAY_TYPE_DOMAIN:
            {
                $data .= $this->relay->encode($_packet->offset);
            }
            break;
            default:
            {
                return '';
            }
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
