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
 * APL Resource Record - RFC3123
 *
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *     |                          ADDRESSFAMILY                        |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *     |             PREFIX            | N |         AFDLENGTH         |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *     /                            AFDPART                            /
 *     |                                                               |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 * @property array<int,array<string,mixed>> $apl_items
 */
final class APL extends \NetDNS2\RR
{
    /**
     * possible address faimily values
     */
    public const ADDRESS_FAMILY_IPV4    = 1;
    public const ADDRESS_FAMILY_IPV6    = 2;

    /**
     * a list of all the address prefix list items
     *
     * @var array<int,mixed>
     */
    protected array $apl_items = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = '';

        foreach($this->apl_items as $item)
        {
            if ($item['negate'] == 1)
            {
                $out .= '!';
            }

            $out .= $item['address_family'] . ':' . $item['afd_part'] . '/' . $item['prefix'] . ' ';
        }

        return trim($out);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        foreach($_rdata as $item)
        {
            if (preg_match('/^(!?)([1|2])\:([^\/]*)\/([0-9]{1,3})$/', $item, $m) == 1)
            {
                $val = [

                    'address_family'    => $m[2],
                    'prefix'            => $m[4],
                    'negate'            => ($m[1] == '!') ? 1 : 0
                ];

                $address = self::trimZeros(intval($val['address_family']), $m[3]);

                $val['afd_length'] = count($address);

                switch($val['address_family'])
                {
                    case self::ADDRESS_FAMILY_IPV4:
                    {
                        $val['afd_part'] = new \NetDNS2\Data\IPv4($m[3]);
                    }
                    break;
                    case self::ADDRESS_FAMILY_IPV6:
                    {
                        $val['afd_part'] = new \NetDNS2\Data\IPv6($m[3]);
                    }
                    break;
                    default:
                    {
                        throw new \NetDNS2\Exception(sprintf('invalid address family value: %d', $val['address_family']), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                    }
                }

                $this->apl_items[] = $val;
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

        $offset = 0;

        while($offset < $this->rdlength)
        {
            //
            // unpack the family, prefix, negate and length values
            //
            $val = unpack('naddress_family/Cprefix/Cextra', substr($this->rdata, $offset));
            if ($val === false)
            {
                return false;
            }

            //
            // the negate value is the first bit from the length section
            //
            $val['negate']     = ($val['extra'] >> 7) & 0x1;
            $val['afd_length'] = $val['extra'] & 0x7f;

            unset($val['extra']);
            $offset += 4;

            //
            // the address portion is a 0-truncated value, based on the length
            //
            $address = unpack('C*', substr($this->rdata, $offset, $val['afd_length']));
            if ($address === false)
            {
                return false;
            }

            $offset += $val['afd_length'];

            switch($val['address_family'])
            {
                case self::ADDRESS_FAMILY_IPV4:
                {
                    $address = array_pad($address, 4, 0);

                    $val['afd_part'] = new \NetDNS2\Data\IPv4(inet_ntop(pack('C*', ...$address)));
                }
                break;
                case self::ADDRESS_FAMILY_IPV6:
                {
                    $address = array_pad($address, 16, 0);

                    $val['afd_part'] = new \NetDNS2\Data\IPv6(inet_ntop(pack('C*', ...$address)));
                }
                break;
                default:
                {
                    throw new \NetDNS2\Exception(sprintf('invalid address family value: %d', $val['address_family']), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                }
            }

            $this->apl_items[] = $val;
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (count($this->apl_items) == 0)
        {
            return '';
        }

        $data = '';

        foreach($this->apl_items as $item)
        {
            //
            // get the address so we know the length
            //
            $vals = self::trimZeros(intval($item['address_family']), strval($item['afd_part']));
            $item['afd_length'] = count($vals);

            //
            // pack the address_family and prefix values
            //
            $data .= pack('nCC', $item['address_family'], $item['prefix'], ($item['negate'] << 7) | $item['afd_length']);

            //
            // trimZeros() returns the value as octects for both IPv4 & IPv6
            //
            $data .= pack('C*', ...$vals);
        }

        $_packet->offset += strlen($data);

        return $data;
    }

    /**
     * returns an IP address with the right-hand zero's trimmed
     *
     * @param integer $_family  the IP address family from the rdata
     * @param string  $_address the IP address
     *
     * @return array<int> the trimmed IP addresss.
     *
     */
    public static function trimZeros(int $_family, string $_address): array
    {
        $out = [];

        switch($_family)
        {
            case self::ADDRESS_FAMILY_IPV4:
            {
                $a = array_reverse(explode('.', $_address));
                $started = false;

                foreach($a as $value)
                {
                    if ($started || intval($value) != 0)
                    {
                        $out[] = intval($value);
                        $started = true;
                    }
                }
            }
            break;
            case self::ADDRESS_FAMILY_IPV6:
            {
                $address = str_replace(':', '', \NetDNS2\Client::expandIPv6($_address));
                $begin   = false;

                for($i=strlen($address); $i!=0; $i-=2)
                {
                    $x = hexdec(substr($address, $i - 2, 2));

                    if ( ($x == 0) && ($begin == false) )
                    {
                        continue;
                    } else
                    {
                        $out[] = intval($x);

                        $begin = true;
                    }
                }
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid address family value: %d', $_family), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }
        }

        return array_reverse($out);
    }
}
