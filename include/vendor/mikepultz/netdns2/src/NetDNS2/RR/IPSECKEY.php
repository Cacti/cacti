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
 * IPSECKEY Resource Record - RFC4025 section 2.1
 *
 *       0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *     +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *     |  precedence   | gateway type  |  algorithm  |     gateway     |
 *     +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-------------+                 +
 *     ~                            gateway                            ~
 *     +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *     |                                                               /
 *     /                          public key                           /
 *     /                                                               /
 *     +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-|
 *
 * @property int $precedence
 * @property int $gateway_type
 * @property int $algorithm
 * @property \NetDNS2\Data $gateway
 * @property string $key
 */
final class IPSECKEY extends \NetDNS2\RR
{
    /**
     * allowed gateway type values
     */
    public const GATEWAY_TYPE_NONE     = 0;
    public const GATEWAY_TYPE_IPV4     = 1;
    public const GATEWAY_TYPE_IPV6     = 2;
    public const GATEWAY_TYPE_DOMAIN   = 3;

    /**
     * supported algorithms
     */
    public const ALGORITHM_NONE        = 0;
    public const ALGORITHM_DSA         = 1;
    public const ALGORITHM_RSA         = 2;

    /**
     * Precedence (used the same was as a preference field)
     */
    protected int $precedence;

    /**
     * Gateway type - specifies the format of the gataway information
     */
    protected int $gateway_type;

    /**
     * The algorithm used
     */
    protected int $algorithm;

    /**
     * The gateway information
     */
    protected \NetDNS2\Data $gateway;

    /**
     * the public key
     */
    protected string $key;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->precedence . ' ' . $this->gateway_type . ' ' . $this->algorithm . ' ';

        switch($this->gateway_type)
        {
            case self::GATEWAY_TYPE_NONE:
            {
                $out .= '. ';
            }
            break;
            case self::GATEWAY_TYPE_IPV4:
            case self::GATEWAY_TYPE_IPV6:
            {
                $out .= $this->gateway . ' ';
            }
            break;
            case self::GATEWAY_TYPE_DOMAIN:
            {
                $out .= $this->gateway . '. ';
            }
            break;
            default:
                ;
        }

        $out .= $this->key;

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        //
        // load the data
        //
        $this->precedence   = intval($this->sanitize(array_shift($_rdata)));
        $this->gateway_type = intval($this->sanitize(array_shift($_rdata)));
        $this->algorithm    = intval($this->sanitize(array_shift($_rdata)));

        //
        // validate it
        //
        switch($this->gateway_type)
        {
            case self::GATEWAY_TYPE_NONE:
            {
                $this->gateway = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, '');
            }
            break;
            case self::GATEWAY_TYPE_IPV4:
            {
                $this->gateway = new \NetDNS2\Data\IPv4($this->sanitize(array_shift($_rdata) ?? ''));
            }
            break;
            case self::GATEWAY_TYPE_IPV6:
            {
                $this->gateway = new \NetDNS2\Data\IPv6($this->sanitize(array_shift($_rdata) ?? ''));
            }
            break;
            case self::GATEWAY_TYPE_DOMAIN:
            {
                $this->gateway = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->sanitize(array_shift($_rdata)));
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid gateway type value provided: %d', $this->gateway_type), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }
        }

        $this->key = array_shift($_rdata) ?? '';

        //
        // check the algorithm and key
        //
        switch($this->algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                $this->key = '';
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                // do nothing
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid algorithm value provided: %d', $this->algorithm), \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM);
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
        // parse off the precedence, gateway type and algorithm
        //
        $val = unpack('Cx/Cy/Cz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->precedence, 'y' => $this->gateway_type, 'z' => $this->algorithm) = (array)$val;

        $offset = 3;

        //
        // extract the gatway based on the type
        //
        switch($this->gateway_type)
        {
            case self::GATEWAY_TYPE_NONE:
            {
                $this->gateway = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, '');
            }
            break;
            case self::GATEWAY_TYPE_IPV4:
            {
                $this->gateway = new \NetDNS2\Data\IPv4($this->rdata, $offset);
            }
            break;
            case self::GATEWAY_TYPE_IPV6:
            {
                $this->gateway = new \NetDNS2\Data\IPv6($this->rdata, $offset);
            }
            break;
            case self::GATEWAY_TYPE_DOMAIN:
            {
                $this->gateway = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->rdata, $offset);
            }
            break;
            default:
            {
                return false;
            }
        }

        //
        // extract the key
        //
        switch($this->algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                $this->key = '';
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                $this->key = base64_encode(substr($this->rdata, $offset));
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
        // pack the precedence, gateway type and algorithm
        //
        $data = pack('CCC', $this->precedence, $this->gateway_type, $this->algorithm);

        //
        // add the gateway based on the type
        //
        switch($this->gateway_type)
        {
            case self::GATEWAY_TYPE_NONE:
            {
                // add nothing
            }
            break;
            case self::GATEWAY_TYPE_IPV4:
            case self::GATEWAY_TYPE_IPV6:
            case self::GATEWAY_TYPE_DOMAIN:
            {
                $data .= $this->gateway->encode();
            }
            break;
            default:
            {
                return '';
            }
        }

        //
        // add the key if there's one specified
        //
        switch($this->algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                // add nothing
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                $decode = base64_decode($this->key);
                if ($decode !== false)
                {
                    $data .= $decode;
                }
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
