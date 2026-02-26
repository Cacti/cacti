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
 * SVCB Resource Record - RFC 9460 section 2.2
 */
class SVCB extends \NetDNS2\RR
{
    /**
     * service binding parameter keys
     */
    public const SVCB_PARAM_MANDATORY           = 0;        // RFC9460
    public const SVCB_PARAM_ALPN                = 1;        // RFC9460
    public const SVCB_PARAM_NO_DEFAULT_ALPN     = 2;        // RFC9460
    public const SVCB_PARAM_PORT                = 3;        // RFC9460
    public const SVCB_PARAM_IPV4HINT            = 4;        // RFC9460
    public const SVCB_PARAM_ECH                 = 5;        // RFC9460
    public const SVCB_PARAM_IPV6HINT            = 6;        // RFC9460
    public const SVCB_PARAM_DOHPATH             = 7;        // RFC9461
    public const SVCB_PARAM_OHTTP               = 8;        // RFC9540
    public const SVCB_PARAM_TLS_SUPP_GROUPS     = 9;        // https://www.ietf.org/archive/id/draft-ietf-tls-key-share-prediction-01.html
                                                            // 65280-65534 private use
    public const SVCB_PARAM_RESERVED            = 65535;

    /**
     * @var array<int,string>
     */
    public static array $param_id_to_name = [

        self::SVCB_PARAM_MANDATORY          => 'mandatory',
        self::SVCB_PARAM_ALPN               => 'alpn',
        self::SVCB_PARAM_NO_DEFAULT_ALPN    => 'no-default-alpn',
        self::SVCB_PARAM_PORT               => 'port',
        self::SVCB_PARAM_IPV4HINT           => 'ipv4hint',
        self::SVCB_PARAM_ECH                => 'ech',
        self::SVCB_PARAM_IPV6HINT           => 'ipv6hint',
        self::SVCB_PARAM_DOHPATH            => 'dohpath',
        self::SVCB_PARAM_OHTTP              => 'ohttp',
        self::SVCB_PARAM_TLS_SUPP_GROUPS    => 'tls-supported-groups'
    ];

    /**
     * @var array<string,int>
     */
    public static array $param_name_to_id = [

        'mandatory'             => self::SVCB_PARAM_MANDATORY,
        'alpn'                  => self::SVCB_PARAM_ALPN,
        'no-default-alpn'       => self::SVCB_PARAM_NO_DEFAULT_ALPN,
        'port'                  => self::SVCB_PARAM_PORT,
        'ipv4hint'              => self::SVCB_PARAM_IPV4HINT,
        'ech'                   => self::SVCB_PARAM_ECH,
        'ipv6hint'              => self::SVCB_PARAM_IPV6HINT,
        'dohpath'               => self::SVCB_PARAM_DOHPATH,
        'ohttp'                 => self::SVCB_PARAM_OHTTP,
        'tls-supported-groups'  => self::SVCB_PARAM_TLS_SUPP_GROUPS
    ];

    /**
     * service priority
     */
    protected int $svc_priority;

    /**
     * target name - can be empty
     */
    protected \NetDNS2\Data\Domain $target_name;

    /**
     * list of service parameters
     *
     * @var array<string,mixed>
     */
    protected array $svc_params = [];

    /**
     * service binding parameter keys
     */
    private static function service_id_to_name(int $_service): string
    {
        //
        // private use range
        //
        if ( ($_service >= 65280) && ($_service <= 65534) )
        {
            return 'key' . $_service;

        //
        // one of the defined values
        //
        } elseif (isset(self::$param_id_to_name[$_service]) === true)
        {
            return self::$param_id_to_name[$_service];
        }

        throw new \NetDNS2\Exception(sprintf('unsupported sevice id value provided: %d', $_service), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
    }
    private static function service_name_to_id(string $_service): int
    {
        //
        // private use range
        //
        if (strncasecmp($_service, 'key', 3) == 0)
        {
            return intval(str_replace('key', '', $_service));

        //
        // one of the defined values
        //
        } elseif (isset(self::$param_name_to_id[strtolower($_service)]) === true)
        {
            return self::$param_name_to_id[strtolower($_service)];
        }

        throw new \NetDNS2\Exception(sprintf('unsupported sevice name value provided: %s', $_service), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
    }

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->svc_priority . ' ' . $this->target_name . '.';

        foreach($this->svc_params as $service => $values)
        {
            switch(self::service_name_to_id($service))
            {
                case self::SVCB_PARAM_MANDATORY:
                case self::SVCB_PARAM_IPV4HINT:
                case self::SVCB_PARAM_IPV6HINT:
                case self::SVCB_PARAM_TLS_SUPP_GROUPS:
                {
                    $out .= ' ' . $service . '=' . implode(',', $values);
                }
                break;
                case self::SVCB_PARAM_ALPN:
                {
                    $out .= ' ' . $service . '="' . implode(',', $values) . '"';
                }
                break;
                case self::SVCB_PARAM_PORT:
                case self::SVCB_PARAM_ECH:
                case self::SVCB_PARAM_DOHPATH:
                {
                    $out .= ' ' . $service . '=' . $values;
                }
                break;
                case self::SVCB_PARAM_NO_DEFAULT_ALPN:
                case self::SVCB_PARAM_OHTTP:
                {
                    $out .= ' ' . $service;
                }
                break;
                default:
                {
                    if (strncmp($service, 'key', 3) == 0)
                    {
                        $out .= ' ' . $service . '="' . $values . '"';
                    }
                }
                break;
            }
        }

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->svc_priority = intval($this->sanitize(array_shift($_rdata)));
        $this->target_name  = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, array_shift($_rdata));

        foreach($_rdata as $data)
        {
            $service = '';
            $values = '';

            if (strpos($data, '=') !== false)
            {
                list($service, $values) = explode('=', strtolower($data));
            } else
            {
                $service = strtolower($data);
            }

            switch(self::service_name_to_id($service))
            {
                case self::SVCB_PARAM_MANDATORY:
                case self::SVCB_PARAM_ALPN:
                case self::SVCB_PARAM_TLS_SUPP_GROUPS:
                {
                    $this->svc_params[$service] = explode(',', trim($values, '"'));
                }
                break;
                case self::SVCB_PARAM_IPV4HINT:
                {
                    $x = explode(',', trim($values, '"'));
                    foreach($x as $ip)
                    {
                        $this->svc_params[$service][] = new \NetDNS2\Data\IPv4($ip);
                    }
                }
                break;
                case self::SVCB_PARAM_IPV6HINT:
                {
                    $x = explode(',', trim($values, '"'));
                    foreach($x as $ip)
                    {
                        $this->svc_params[$service][] = new \NetDNS2\Data\IPv6($ip);
                    }
                }
                break;
                case self::SVCB_PARAM_PORT:
                {
                    $this->svc_params[$service] = intval($values);
                }
                break;
                case self::SVCB_PARAM_ECH:
                case self::SVCB_PARAM_DOHPATH:
                {
                    $this->svc_params[$service] = $values;
                }
                break;
                case self::SVCB_PARAM_NO_DEFAULT_ALPN:
                case self::SVCB_PARAM_OHTTP:
                {
                    $this->svc_params[$service] = true;
                }
                break;
                default:
                {
                    if (strncmp($service, 'key', 3) == 0)
                    {
                        $this->svc_params[$service] = trim($values, '"');
                    }
                }
                break;
            }
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->rdlength > 0)
        {
            $offset = 0;

            //
            // unpack the priority and target
            //
            $val = unpack('nx', $this->rdata);
            if ($val === false)
            {
                return false;
            }

            list('x' => $this->svc_priority) = (array)$val;
            $offset += 2;

            //
            // check the target name
            //
            $this->target_name = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->rdata, $offset);

            $limit = $this->rdlength - 3;

            while($offset < $limit)
            {
                $val = unpack('nx/ny', $this->rdata, intval($offset));
                if ($val === false)
                {
                    return false;
                }

                list('x' => $key, 'y' => $size) = (array)$val;
                $offset += 4;

                //
                // figure out the sevice param key
                //
                switch($key)
                {
                    //
                    // mandatory values are a list of 2 octect values; $size is count * 2
                    //
                    case self::SVCB_PARAM_MANDATORY:
                    {
                        $values = unpack('n*', substr($this->rdata, intval($offset), $size));
                        if ($values !== false)
                        {
                            foreach($values as $value)
                            {
                                $this->svc_params[self::service_id_to_name($key)][] = $this->service_id_to_name($value);
                            }
                        }
                    }
                    break;

                    //
                    // alpn values are lists of strings preceeded by their length
                    //
                    case self::SVCB_PARAM_ALPN:
                    {
                        $start = 1;
                        $values = substr($this->rdata, intval($offset), $size);

                        for($i=0; $i<$size; $i++)
                        {
                            $len = ord($values[$i]);
                            if ($len > 0)
                            {
                                $svc = substr($values, $start, $len);
                                if (strlen($svc) > 0)
                                {
                                    $this->svc_params[self::service_id_to_name($key)][] = $svc;
                                }

                                $start += $len + 1;
                                $i += $len;
                            }
                        }
                    }
                    break;

                    //
                    // these are just a true/false flag
                    //
                    case self::SVCB_PARAM_NO_DEFAULT_ALPN:
                    case self::SVCB_PARAM_OHTTP:
                    {
                        $this->svc_params[self::service_id_to_name($key)] = true;
                    }
                    break;

                    //
                    // a single 2 octect decimal value between 0 and 65535
                    //
                    case self::SVCB_PARAM_PORT:
                    {
                        $value = unpack('n', substr($this->rdata, intval($offset), $size));
                        if ($value !== false)
                        {
                            $this->svc_params[self::service_id_to_name($key)] = intval($value[1]);
                        }
                    }
                    break;

                    //
                    // list of 4 byte IPv4 IP addresses
                    //
                    case self::SVCB_PARAM_IPV4HINT:
                    {
                        $this->svc_params['ipv4hint'] = [];

                        //
                        // a list of one or more ipv4 addresses
                        //
                        $values = unpack('N*', substr($this->rdata, intval($offset), $size));
                        if ($values !== false)
                        {
                            foreach((array)$values as $value)
                            {
                                $this->svc_params[self::service_id_to_name($key)][] = new \NetDNS2\Data\IPv4(long2ip($value));
                            }
                        }
                    }
                    break;

                    //
                    // ECH is a base64 encoded string
                    //
                    case self::SVCB_PARAM_ECH:
                    {
                        $val = substr($this->rdata, intval($offset), $size);
                        if (strlen($val) > 0)
                        {
                            $this->svc_params[self::service_id_to_name($key)] = base64_encode($val);
                        }
                    }
                    break;

                    //
                    // list of 16 byte IPv6 IP addresses
                    //
                    case self::SVCB_PARAM_IPV6HINT:
                    {
                        $this->svc_params['ipv6hint'] = [];

                        //
                        // a list of one or more ipv6 addresses
                        //
                        for($i=0; $i<$size; $i+=16)
                        {
                            $values = unpack('n8', substr($this->rdata, intval($offset + $i), 16));
                            if ($values !== false)
                            {
                                if (count((array)$values) == 8)
                                {
                                    $this->svc_params[self::service_id_to_name($key)][] = new \NetDNS2\Data\IPv6(vsprintf('%x:%x:%x:%x:%x:%x:%x:%x', (array)$values));
                                }
                            }
                        }
                    }
                    break;

                    //
                    // DNS over HTTP path - defined as an uncompressed string
                    //
                    case self::SVCB_PARAM_DOHPATH:
                    {
                        $value = substr($this->rdata, intval($offset), $size);
                        if (strlen($value) > 0)
                        {
                            $this->svc_params[self::service_id_to_name($key)] = $value;
                        }
                    }
                    break;

                    //
                    // a list of 2-byte numeric values, describing the supported TLS groups
                    //
                    case self::SVCB_PARAM_TLS_SUPP_GROUPS:
                    {
                        $values = unpack('n*', substr($this->rdata, intval($offset), $size));
                        if ($values !== false)
                        {
                            foreach($values as $value)
                            {
                                $this->svc_params[self::service_id_to_name($key)][] = intval($value);
                            }
                        }
                    }
                    break;

                    default:
                    {
                        //
                        // private key range
                        //
                        if ( ($key >= 65280) && ($key <= 65534) )
                        {
                            $value = substr($this->rdata, intval($offset), $size);
                            if (strlen($value) > 0)
                            {
                                $this->svc_params[self::service_id_to_name($key)] = $value;
                            }

                        } else
                        {
                            throw new \NetDNS2\Exception(sprintf('unsupported SVCB param key: %s', $key), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                        }
                    }
                }

                $offset += $size;
            }

            return true;
        }

        return false;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $data = pack('n', $this->svc_priority);

        //
        // if target_name is unset, then add a zero length entry
        //
        if ($this->target_name->length() == 0)
        {
            $data .= pack('C', 0);
        } else
        {
            $data .= $this->target_name->encode();
        }

        foreach($this->svc_params as $service => $values)
        {
            switch(self::service_name_to_id($service))
            {
                case self::SVCB_PARAM_MANDATORY:
                {
                    if (count($values) > 0)
                    {
                        $data .= pack('nn', self::SVCB_PARAM_MANDATORY, count($values) * 2);
                        foreach($values as $value)
                        {
                            $data .= pack('n', $this->service_name_to_id($value));
                        }
                    }
                }
                break;
                case self::SVCB_PARAM_ALPN:
                {
                    if (count($values) > 0)
                    {
                        $alpns = '';

                        foreach($values as $alpn)
                        {
                            $alpns .= pack('C', strlen($alpn)) . $alpn;
                        }

                        $data .= pack('nn', self::SVCB_PARAM_ALPN, strlen($alpns)) . $alpns;
                    }
                }
                break;
                case self::SVCB_PARAM_NO_DEFAULT_ALPN:
                case self::SVCB_PARAM_OHTTP:
                {
                    if ($values == true)
                    {
                        $data .= pack('nn', self::service_name_to_id($service), 0);
                    }
                }
                break;
                case self::SVCB_PARAM_PORT:
                {
                    $data .= pack('nnn', self::SVCB_PARAM_PORT, 2, $values);
                }
                break;
                case self::SVCB_PARAM_IPV4HINT:
                {
                    if (count($values) > 0)
                    {
                        $data .= pack('nn', self::SVCB_PARAM_IPV4HINT, count($values) * 4);
                        foreach($values as $ip)
                        {
                            $data .= $ip->encode();
                        }
                    }
                }
                break;
                case self::SVCB_PARAM_ECH:
                {
                    $value = base64_decode($values);

                    $data .= pack('nna*', self::SVCB_PARAM_ECH, strlen($value), $value);
                }
                break;
                case self::SVCB_PARAM_IPV6HINT:
                {
                    if (count($values) > 0)
                    {
                        $data .= pack('nn', self::SVCB_PARAM_IPV6HINT, count($values) * 16);
                        foreach($values as $ip)
                        {
                            $data .= $ip->encode();
                        }
                    }
                }
                break;
                case self::SVCB_PARAM_DOHPATH:
                {
                    $data .= pack('nna*', self::SVCB_PARAM_DOHPATH, strlen($values), $values);
                }
                break;
                case self::SVCB_PARAM_TLS_SUPP_GROUPS:
                {
                    if (count($values) > 0)
                    {
                        $data .= pack('nn', self::SVCB_PARAM_TLS_SUPP_GROUPS, count($values) * 2);
                        foreach($values as $value)
                        {
                            $data .= pack('n', $value);
                        }
                    }
                }
                break;
                default:
                {
                    $key = self::service_name_to_id($service);

                    if ( ($key >= 65280) && ($key <= 65534) )
                    {
                        $data .= pack('nna*', $key, strlen($values), $values);
                    } else
                    {
                        throw new \NetDNS2\Exception(sprintf('unsupported SVCB param key: %s', $key), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                    }
                }
                break;
            }
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
