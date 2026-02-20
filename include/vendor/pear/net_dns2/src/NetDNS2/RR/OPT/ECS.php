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
 * RFC 7871 - Client Subnet in DNS Queries
 *
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *  0: |                          OPTION-CODE                          |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *  2: |                         OPTION-LENGTH                         |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *  4: |                            FAMILY                             |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *  6: |     SOURCE PREFIX-LENGTH      |     SCOPE PREFIX-LENGTH       |
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *  8: |                           ADDRESS...                          /
 *     +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 */
final class ECS extends \NetDNS2\RR\OPT
{
    /**
     * the IP family (1=IPv4, 2=IPv6)
     */
    protected int $family;

    /**
     * the source prefix value (depends on famiy)
     */
    protected int $source_prefix = 0;

    /**
     * must be set to 0 for requests
     */
    protected int $scope_prefix = 0;

    /**
     * the IP address (depends on family)
     */
    protected \NetDNS2\Data $address;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length;
    }

    /**
     * a single IP (1.1.1.1) or subnet (1.1.1.1/22)
     */
    public function parse_subnet(string $_address): bool
    {
        //
        // look for a prefix
        //
        $address = '';
        $prefix = null;

        if (strpos($_address, '/') !== false)
        {
            list($address, $prefix) = explode('/', $_address);
        } else
        {
            $address = $_address;
        }

        //
        // truncate the IP address based on the prefix value provided
        //
        if (\NetDNS2\Client::isIPv4($address) == true)
        {
            $this->family        = \NetDNS2\RR\APL::ADDRESS_FAMILY_IPV4;
            $this->source_prefix = intval($prefix ?? 24);

            //
            // adjust the IP to the network value based on the prefix
            //
            $address = long2ip(ip2long($address) & (-1 << (32 - $this->source_prefix)));

            $this->address = new \NetDNS2\Data\IPv4($address);

            return true;

        } elseif (\NetDNS2\Client::isIPv6($address) == true)
        {
            $this->family        = \NetDNS2\RR\APL::ADDRESS_FAMILY_IPV6;
            $this->source_prefix = intval($prefix ?? 128);

            //
            // adjust the IP to the network value based on the prefix
            //
            $bin = unpack('C*', strval(inet_pton($address)));
            if ($bin !== false)
            {
                for($i=($this->source_prefix / 8) + 1; $i<=16; $i++)
                {
                    $bin[$i] = 0;
                }

                $this->address = new \NetDNS2\Data\IPv6(inet_ntop(pack('C*', ...$bin)));

            } else
            {
                $this->address = new \NetDNS2\Data\IPv6($address);
            }

            return true;

        } else
        {
            throw new \NetDNS2\Exception('EDNS client subnet requires a valid IPv4 or IPv6 address.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }
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

        $val = unpack('nx/Cy/Cz', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        list('x' => $this->family, 'y' => $this->source_prefix, 'z' => $this->scope_prefix) = (array)$val;
        $offset = 4;

        //
        // the address portion is a 0-truncated value, based on the length
        //
        $address = unpack('C*', substr($this->option_data, $offset));
        if ($address === false)
        {
            return false;
        }

        switch($this->family)
        {
            case \NetDNS2\RR\APL::ADDRESS_FAMILY_IPV4:
            {
                $address = array_pad($address, 4, 0);

                $this->address = new \NetDNS2\Data\IPv4(inet_ntop(pack('C*', ...$address)));
            }
            break;
            case \NetDNS2\RR\APL::ADDRESS_FAMILY_IPV6:
            {
                $address = array_pad($address, 16, 0);

                $this->address = new \NetDNS2\Data\IPv6(inet_ntop(pack('C*', ...$address)));
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid address family value: %d', $this->family), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
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
        // add the integer values
        //
        $this->option_data = pack('nCC', $this->family, $this->source_prefix, $this->scope_prefix);

        //
        // this removes trailing 0's on address strings
        //
        $vals = \NetDNS2\RR\APL::trimZeros($this->family, $this->address->value());

        //
        // trimZeros() returns the value as octects for both IPv4 & IPv6
        //
        $this->option_data .= pack('C*', ...$vals);

        //
        // add the length
        //
        $this->option_length = strlen($this->option_data);

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
