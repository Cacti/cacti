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
 * This is the base class for the \NetDNS2\Resolver and \NetDNS2\Updater classes.
 *
 */
class Client
{
    /**
     * the current version of this library
     */
    public const VERSION = '2.0.7';

    /**
     * the default path to a resolv.conf file
     */
    public const RESOLV_CONF = '/etc/resolv.conf';

    /**
     * override options from the resolv.conf file
     *
     * if this is set, then certain values from the resolv.conf file will override local settings. This is disabled by default to remain
     * backwards compatible.
     *
     */
    public bool $use_resolv_options = false;

    /**
     * use TCP only (true/false)
     */
    public bool $use_tcp = false;

    /**
     * use TLS (DoT) (true/false)
     *
     * this requires OpenSSL support enabled in PHP
     *
     * enabling this option, automatically also enables use_tcp, and sets the default port to 853
     *
     */
    public bool $use_tls = false;

    /**
     * if set, these values are pased to stream_context_create as the 'ssl' transport section, which lets you customize TLS connection settings.
     *
     * only applies when use_tls = true
     *
     * @var array<string,mixed>
     *
     */
    public array $tls_context = [];

    /**
     * DNS Port to use (-1 means default of 53)
     */
    public int $dns_port = -1;

    /**
     * the ip/port for use as a local socket
     */
    public string $local_host = '';
    public int $local_port = 0;

    /**
     * timeout value for socket connections
     */
    public float $timeout = 5.003;

    /**
     * randomize the name servers list
     */
    public bool $ns_random = false;

    /**
     * default domains
     */
    public string $domain = '';

    /**
     * domain search list - not actually used right now
     *
     * @var array<int,string>
     *
     */
    public array $search_list = [];

    /**
     * enable cache using the const defined in the Cache object
     */
    public int $cache_type = \NetDNS2\Cache::CACHE_TYPE_NONE;

    /**
     * options to pass to the underlying caching objects
     *
     * @var array<mixed>
     */
    public array $cache_options = [ 'file' => '/tmp/net_dns2.cache', 'size' => \NetDNS2\Cache::CACHE_DEFAULT_MAX_SIZE ];

    /**
     * by default, according to RFC 1034
     *
     * CNAME RRs cause special action in DNS software.  When a name server fails to find a desired RR in the resource set associated with the
     * domain name, it checks to see if the resource set consists of a CNAME record with a matching class.  If so, the name server includes the CNAME
     * record in the response and restarts the query at the domain name specified in the data field of the CNAME record.
     *
     * this can cause "unexpected" behavious, since i'm sure *most* people don't know DNS does this; there may be cases where NetDNS2 returns a
     * positive response, even though the hostname the user looked up did not actually exist.
     *
     * strict_query_mode means that if the hostname that was looked up isn't actually in the answer section of the response, NetDNS2 will return an
     * empty answer section, instead of an answer section that could contain CNAME records.
     *
     */
    public bool $strict_query_mode = false;

    /**
     * if we should set the recursion desired bit to 1 or 0.
     *
     * by default this is set to true, we want the DNS server to perform a recursive request. If set to false, the RD bit will be set to 0, and the
     * server will not perform recursion on the request.
     *
     */
    public bool $recurse = true;

    /**
     * request DNSSEC values, by setting the DO flag to 1; this actually makes the resolver add a OPT RR to the additional section, and sets the DO flag
     * in this RR to 1
     *
     */
    public bool $dnssec = false;

    /**
     * set the DNSSEC AD (Authentic Data) bit on/off; the AD bit on the request side was previously undefined, and resolvers we instructed to always clear
     * the AD bit when sending a request.
     *
     * RFC6840 section 5.7 defines setting the AD bit in the query as a signal to the server that it wants the value of the AD bit, without needed to
     * request all the DNSSEC data via the DO bit.
     *
     */
    public bool $dnssec_ad_flag = false;

    /**
     * set the DNSSEC CD (Checking Disabled) bit on/off; turning this off, means that the DNS resolver will perform it's own signature validation- so
     * the DNS servers simply pass through all the details.
     *
     */
    public bool $dnssec_cd_flag = false;

    /**
     * the EDNS(0) UDP payload size to use when making DNSSEC requests see RFC 4035 section 4.1 - EDNS Support.
     *
     * there is some different ideas on the suggested size to support; but it seems to be "at least 1220 bytes, but SHOULD support 4000 bytes.
     *
     * we'll just support 4000
     *
     */
    public int $dnssec_payload_size = 4000;

    /**
     * a local instance of the \NetDNS2\EDNS object for adding EDN(0) options to requests.
     */
    public \NetDNS2\EDNS $edns;

    /**
     * the last exeception that was generated
     */
    public ?\NetDNS2\Exception $last_exception = null;

    /**
     * the list of exceptions by name server
     *
     * @var array<string,\NetDNS2\Exception>
     *
     */
    public array $last_exception_list = [];

    /**
     * name server list
     *
     * @var array<string>
     *
     */
    public array $nameservers = [];

    /**
     * local sockets
     *
     * @var array<int,mixed>
     *
     */
    protected array $sock = [ \NetDNS2\Socket::SOCK_DGRAM => [], \NetDNS2\Socket::SOCK_STREAM => [] ];

    /**
     * the TSIG or SIG RR object for authentication
     */
    protected ?\NetDNS2\RR $auth_signature = null;

    /**
     * the shared memory segment id for the local cache
     *
     */
    protected \NetDNS2\Cache $cache;

    /**
     * internal setting for enabling cache
     */
    protected bool $use_cache = false;

    /**
     * Constructor - base constructor for the Resolver and Updater
     *
     * @param array<string,mixed> $_options array of options or null for none
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(?array $_options = null)
    {
        //
        //
        //
        $this->edns = new \NetDNS2\EDNS();

        //
        // load any options that were provided
        //
        if (is_null($_options) == false)
        {
            foreach($_options as $key => $value)
            {
                if ($key == 'nameservers')
                {
                    $this->setServers($value);
                } else
                {
                    $this->$key = $value;
                }
            }
        }

        //
        // if we're set to use the local shared memory cache, then make sure it's been initialized
        //
        if ($this->cache_type != \NetDNS2\Cache::CACHE_TYPE_NONE)
        {
            $this->cache = \NetDNS2\Cache::factory($this->cache_type, $this->cache_options);
            if (is_null($this->cache) == false)
            {
                $this->use_cache = true;
            }
        }
    }

    /**
     * sets the name servers to be used
     *
     * @param array<string>|string $_nameservers either an array of name servers, or a file name to parse, assuming it's in the resolv.conf format

     * @throws \NetDNS2\Exception
     *
     */
    public function setServers(array|string $_nameservers): bool
    {
        //
        // if it's an array, then use it directly
        //
        // otherwise, see if it's a path to a resolv.conf file and if so, load it
        //
        if (is_array($_nameservers) == true)
        {
            //
            // make sure all the name servers are IP addresses (either v4 or v6) or HTTPS URLs (for DoH)
            //
            foreach($_nameservers as $value)
            {
                if ( (self::isIPv4($value) == false) && (self::isIPv6($value) == false) && (strncasecmp($value, 'https://', 8) != 0) )
                {
                    throw new \NetDNS2\Exception(sprintf('invalid nameserver entry: %s', $value), \NetDNS2\ENUM\Error::INT_INVALID_NAMESERVER);
                }
            }

            $this->nameservers = $_nameservers;

        } else
        {
            //
            // temporary list of name servers; do it this way rather than just resetting the local nameservers value, just incase an exception
            // is thrown here; this way we might avoid ending up with an empty namservers list.
            //
            $ns = [];

            //
            // check to see if the file is readable
            //
            $resolv_conf = realpath($_nameservers);

            if ( ($resolv_conf !== false) && (is_readable($_nameservers) === true) )
            {
                $data = file_get_contents($resolv_conf);
                if ($data === false)
                {
                    throw new \NetDNS2\Exception(sprintf('failed to read contents of file: \'%s\'', $resolv_conf), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                }

                $lines = explode("\n", $data);

                foreach($lines as $line)
                {
                    $line = trim(strtolower($line));

                    //
                    // ignore empty lines, and lines that are commented out
                    //
                    if ( (strlen($line) == 0) || ($line[0] == '#') || ($line[0] == ';') )
                    {
                        continue;
                    }

                    //
                    // ignore lines with no spaces in them.
                    //
                    if (strpos($line, ' ') === false)
                    {
                        continue;
                    }

                    list($key, $value) = explode(' ', $line, 2);

                    $key   = trim($key);
                    $value = trim($value);

                    switch($key)
                    {
                        //
                        // nameserver can be a IPv4 or IPv6 address
                        //
                        case 'nameserver':
                        {
                            if ( (self::isIPv4($value) == true) || (self::isIPv6($value) == true) )
                            {
                                $ns[] = $value;
                            } else
                            {
                                throw new \NetDNS2\Exception(sprintf('invalid nameserver entry: %s', $value), \NetDNS2\ENUM\Error::INT_INVALID_NAMESERVER);
                            }
                        }
                        break;
                        case 'domain':
                        {
                            $this->domain = $value;
                        }
                        break;
                        case 'search':
                        {
                            $search_values = preg_split('/\s+/', $value);
                            if ($search_values !== false)
                            {
                                foreach($search_values as $search_value)
                                {
                                    $this->search_list[] = strval($search_value);
                                }
                            }
                        }
                        break;
                        case 'options':
                        {
                            $this->parseOptions($value);
                        }
                        break;
                        default:
                            ;
                    }
                }

                //
                // if we don't have a domain, but we have a search list, then take the first entry on the search list as the domain
                //
                if ( (strlen($this->domain) == 0) && (count($this->search_list) > 0) )
                {
                    $this->domain = $this->search_list[0];
                }

            } else
            {
                throw new \NetDNS2\Exception(sprintf('resolver file \'%s\' provided is not readable.', $_nameservers), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }

            //
            // store the name servers locally
            //
            if (count($ns) > 0)
            {
                $this->nameservers = $ns;
            }
        }

        //
        // remove any duplicates; not sure if we should bother with this- if people put duplicate name servers, who I am to stop them?
        //
        $this->nameservers = array_unique($this->nameservers);

        //
        // check the name servers
        //
        $this->checkServers();

        return true;
    }

    /**
     * return the internal $sock array
     *
     * @return array<int,mixed>
     *
     */
    public function getSockets(): array
    {
        return $this->sock;
    }

    /**
     * give users access to close all open sockets on the resolver object; resetting each array, calls the destructor on the \NetDNS2\Socket
     * object, which calls the close() method on each object.
     *
     */
    public function closeSockets(): void
    {
        $this->sock[\NetDNS2\Socket::SOCK_DGRAM]  = [];
        $this->sock[\NetDNS2\Socket::SOCK_STREAM] = [];
    }

    /**
     * parses the options line from a resolv.conf file; we don't support all the options yet, and using them is optional.
     *
     * @param string $_value is the options string from the resolv.conf file.
     *
     */
    private function parseOptions(string $_value): bool
    {
        //
        // if overrides are disabled (the default), or the options list is empty for some reason, then we don't need to do any of this work.
        //
        if ( ($this->use_resolv_options == false) || (strlen($_value) == 0) )
        {
            return true;
        }

        $options = preg_split('/\s+/', strtolower($_value));
        if ($options === false)
        {
            return false;
        }

        foreach((array)$options as $option)
        {
            //
            // override the timeout value from the resolv.conf file.
            //
            if ( (strncmp($option, 'timeout', 7) == 0) && (strpos($option, ':') !== false) )
            {
                list($key, $val) = explode(':', $option);

                if ( ($val > 0) && ($val <= 30) )
                {
                    $this->timeout = floatval($val);
                }

            //
            // the rotate option just enabled the ns_random option
            //
            } elseif (strncmp($option, 'rotate', 6) == 0)
            {
                $this->ns_random = true;
            }
        }

        return true;
    }

    /**
     * checks the list of name servers to make sure they're set
     *
     * @param mixed $_default a path to a resolv.conf file or an array of servers.
     *
     * @throws \NetDNS2\Exception
     *
     */
    protected function checkServers(mixed $_default = null): void
    {
        if (empty($this->nameservers) == true)
        {
            if (is_null($_default) == false)
            {
                $this->setServers($_default);
            } else
            {
                throw new \NetDNS2\Exception('empty name servers list; you must provide a list of name servers, or the path to a resolv.conf file.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }
        }
    }

    /**
     * adds a TSIG RR object for authentication
     *
     * @param \NetDNS2\RR\TSIG|string $_keyname   the key name to use for the TSIG RR or an instance of a \NetDNS2\RR\TSIG object to copy
     * @param string                  $_signature the key to sign the request.
     * @param string                  $_algorithm the algorithm to use
     *
     */
    public function signTSIG(\NetDNS2\RR\TSIG|string $_keyname, string $_signature = '', string $_algorithm = \NetDNS2\RR\TSIG::HMAC_MD5): bool
    {
        //
        // if the TSIG was pre-created and passed in, then we can just used it as provided.
        //
        if ( ($_keyname instanceof \NetDNS2\RR\TSIG) == true)
        {
            $this->auth_signature = clone $_keyname;

        } else
        {
            //
            // otherwise create the TSIG RR, but don't add it just yet; TSIG needs to be added as the last additional entry- so we'll add
            // it just before we send.
            //
            $this->auth_signature = new \NetDNS2\RR\TSIG();
            $this->auth_signature->factory($_keyname, $_algorithm, $_signature);
        }

        return true;
    }

    /**
     * adds a SIG RR object for authentication
     *
     * @param \NetDNS2\RR\SIG|string $_filename the name of a file to load the signature from, or an instance of a \NetDNS2\RR\SIG
     *                                          object that we copy from.
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function signSIG0(\NetDNS2\RR\SIG|string $_filename): bool
    {
        //
        // check for OpenSSL
        //
        if (extension_loaded('openssl') === false)
        {
            throw new \NetDNS2\Exception('the openssl extension is required to use SIG(0).', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
        }

        //
        // if the SIG was pre-created, then use it as-is
        //
        if ( ($_filename instanceof \NetDNS2\RR\SIG) == true)
        {
            $this->auth_signature = clone $_filename;

        } else
        {
            //
            // otherwise, it's filename which needs to be parsed and processed.
            //
            $private = new \NetDNS2\PrivateKey($_filename);

            //
            // create a new \NetDNS2\RR\SIG object
            //
            $this->auth_signature = new \NetDNS2\RR\SIG();

            //
            // reset some values
            //
            $this->auth_signature->name         = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $private->signname);
            $this->auth_signature->ttl          = 0;
            $this->auth_signature->class        = \NetDNS2\ENUM\RR\Classes::set('ANY');

            //
            // these values are pulled from the private key
            //
            $this->auth_signature->algorithm    = $private->algorithm;
            $this->auth_signature->keytag       = $private->keytag;
            $this->auth_signature->signname     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $private->signname);

            //
            // these values are hard-coded for SIG0
            //
            $this->auth_signature->typecovered  = 'SIG0';
            $this->auth_signature->labels       = 0;
            $this->auth_signature->origttl      = 0;

            //
            // generate the dates
            //
            $t = time();

            $this->auth_signature->sigincep     = gmdate('YmdHis', $t);
            $this->auth_signature->sigexp       = gmdate('YmdHis', $t + 600);

            //
            // store the private key in the SIG object for later.
            //
            $this->auth_signature->private_key  = $private;
        }

        //
        // only RSA algorithms are supported for SIG(0)
        //
        switch($this->auth_signature->algorithm)
        {
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSAMD5:
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA1:
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA256:
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA512:
            case \NetDNS2\ENUM\DNSSEC\Algorithm::DSA:
            {
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception('SIG(0) currently only supports asymmetric algorithms.', \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM);
            }
        }

        return true;
    }

    /**
     * a simple function to determine if the RR type is cacheable
     *
     * @param string $_type the RR type string
     *
     * @return bool returns true/false if the RR type if cachable
     *
     */
    public function cacheable(string $_type): bool
    {
        switch($_type)
        {
            case 'AXFR':
            case 'OPT':
            {
                return false;
            }
        }

        return true;
    }

    /**
     * returns true/false if the given address is a valid IPv4 address
     *
     * @param string $_address the IPv4 address to check
     *
     * @return boolean returns true/false if the address is IPv4 address
     *
     */
    public static function isIPv4(string $_address): bool
    {
        return (filter_var($_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == true) ? true : false;
    }

    /**
     * returns true/false if the given address is a valid IPv6 address
     *
     * @param string $_address the IPv6 address to check
     *
     * @return boolean returns true/false if the address is IPv6 address
     *
     */
    public static function isIPv6(string $_address): bool
    {
        return (filter_var($_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == true) ? true : false;
    }

    /**
     * formats the given IPv6 address as a fully expanded IPv6 address
     *
     * @param string $_address the IPv6 address to expand
     *
     * @return string the fully expanded IPv6 address
     *
     */
    public static function expandIPv6(string $_address): string
    {
        $hex = unpack('H*hex', strval(inet_pton($_address)));
        if ($hex === false)
        {
            return $_address;
        }

        return substr(preg_replace('/([A-f0-9]{4})/', "$1:", ((array)$hex)['hex']), 0, -1);
    }

    /**
     * sends a standard \NetDNS2\Packet\Request packet
     *
     * @param \NetDNS2\Packet\Request $_request a \NetDNS2\Packet\Request object
     * @param boolean                 $_use_tcp true/false if the function should use TCP for the request
     *
     * @throws \NetDNS2\Exception
     *
     */
    protected function sendPacket(\NetDNS2\Packet\Request $_request, bool $_use_tcp = false): \NetDNS2\Packet\Response
    {
        //
        // get the data from the packet
        //
        $data = $_request->get();
        if (strlen($data) < \NetDNS2\Header::DNS_HEADER_SIZE)
        {
            throw new \NetDNS2\Exception('invalid or empty packet data provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET, null, $_request);
        }

        reset($this->nameservers);

        //
        // randomize the name server list if it's asked for
        //
        if ($this->ns_random == true)
        {
            shuffle($this->nameservers);
        }

        //
        // loop so we can handle server errors
        //
        $response = null;
        $ns = '';

        while(1)
        {
            //
            // grab the next DNS server
            //
            $ns = current($this->nameservers);
            next($this->nameservers);

            if ( ($ns === false) || (strlen($ns) == 0) )
            {
                if ( ($this->last_exception instanceof \NetDNS2\Exception) == true)
                {
                    throw $this->last_exception;
                } else
                {
                    throw new \NetDNS2\Exception('every name server provided has failed', \NetDNS2\ENUM\Error::INT_FAILED_NAMESERVER);
                }
            }

            //
            // set the max UDP packet size
            //
            $max_udp_size = \NetDNS2\Header::DNS_MAX_UDP_SIZE;
            if ($this->dnssec == true)
            {
                $max_udp_size = $this->dnssec_payload_size;
            }

            //
            // if the DNS server provided is a URL, then assume DoH
            //
            if (strncasecmp($ns, 'https://', 8) == 0)
            {
                try
                {
                    $response = $this->sendDOHRequest($ns, $data);

                } catch(\NetDNS2\Exception $e)
                {
                    $this->last_exception = $e;
                    $this->last_exception_list[$ns] = $e;

                    continue;
                }

            //
            // if the use TCP flag (force TCP) is set, or the packet is bigger than our max allowed UDP size- which is either 512, or if this
            // is DNSSEC request, then whatever the configured dnssec_payload_size is.
            //
            } elseif ( ($_use_tcp == true) || (strlen($data) > $max_udp_size) )
            {
                try
                {
                    $response = $this->sendTCPRequest($ns, $data, ($_request->question[0]->qtype == \NetDNS2\ENUM\RR\Type::AXFR) ? true : false);

                } catch(\NetDNS2\Exception $e)
                {
                    $this->last_exception = $e;
                    $this->last_exception_list[$ns] = $e;

                    continue;
                }

            //
            // otherwise, send it using UDP
            //
            } else
            {
                try
                {
                    $response = $this->sendUDPRequest($ns, $data);

                    //
                    // check the packet header for a trucated bit; if it was truncated, then re-send the request as TCP.
                    //
                    if ($response->header->tc == 1)
                    {
                        $response = $this->sendTCPRequest($ns, $data);
                    }

                } catch(\NetDNS2\Exception $e)
                {
                    $this->last_exception = $e;
                    $this->last_exception_list[$ns] = $e;

                    continue;
                }
            }

            //
            // make sure header id's match between the request and response
            //
            if ($_request->header->id != $response->header->id)
            {
                $this->last_exception = new \NetDNS2\Exception('invalid header: the request and response id do not match.',
                    \NetDNS2\ENUM\Error::INT_INVALID_PACKET, null, $_request, $response);

                $this->last_exception_list[$ns] = $this->last_exception;
                continue;
            }

            //
            // make sure the response is actually a response
            //
            // 0 = query, 1 = response
            //
            if ($response->header->qr != \NetDNS2\Header::QR_RESPONSE)
            {
                $this->last_exception = new \NetDNS2\Exception('invalid header: the response provided is not a response packet.',
                    \NetDNS2\ENUM\Error::INT_INVALID_PACKET, null, $_request, $response);

                $this->last_exception_list[$ns] = $this->last_exception;
                continue;
            }

            //
            // make sure the response code in the header is ok
            //
            if ($response->header->rcode != \NetDNS2\ENUM\RR\Code::NOERROR)
            {
                $this->last_exception = new \NetDNS2\Exception('DNS request failed: ' . $response->header->rcode->label(),
                    \NetDNS2\ENUM\Error::set($response->header->rcode->value), null, $_request, $response);

                $this->last_exception_list[$ns] = $this->last_exception;
                continue;
            }

            break;
        }

        return $response;
    }

    /**
     * cleans up a failed socket and throws the given exception
     *
     * @param int                 $_proto the protocol of the socket
     * @param string              $_ns    the name server to use for the request
     * @param \NetDNS2\ENUM\Error $_error the error message to throw at the end of the function
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function generateError(int $_proto, string $_ns, \NetDNS2\ENUM\Error $_error): void
    {
        if (isset($this->sock[$_proto][$_ns]) === false)
        {
            throw new \NetDNS2\Exception('invalid socket reference provided.', \NetDNS2\ENUM\Error::INT_INVALID_SOCKET);
        }

        //
        // grab the last error message off the socket
        //
        $last_error = $this->sock[$_proto][$_ns]->last_error;

        //
        // remove it from the socket cache; this will call the destructor, which calls close() on the socket
        //
        unset($this->sock[$_proto][$_ns]);

        //
        // throw the error provided
        //
        throw new \NetDNS2\Exception($last_error, $_error);
    }

    /**
     * sends a DNS request using TCP
     *
     * @param string  $_ns   the name server to use for the request
     * @param string  $_data the raw DNS packet data
     * @param boolean $_axfr if this is a zone transfer request
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function sendTCPRequest(string $_ns, string $_data, bool $_axfr = false): \NetDNS2\Packet\Response
    {
        //
        // grab the start time
        //
        $start_time = microtime(true);

        //
        // see if we already have an open socket from a previous request; if so, try to use that instead of opening a new one.
        //
        if ( (isset($this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]) === false) ||
            ( ($this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns] instanceof \NetDNS2\Socket) == false) )
        {
            //
            // create the socket object
            //
            $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns] = new \NetDNS2\Socket(\NetDNS2\Socket::SOCK_STREAM, $_ns, $this->dns_port, $this->timeout);

            //
            // if TLS is enabled, then enable it on the socket, and copy over the context settings
            //
            if ($this->use_tls == true)
            {
                $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->m_use_tls = true;
                $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->m_tls_context = $this->tls_context;
            }

            //
            // if a local IP address / port is set, then add it
            //
            if (strlen($this->local_host) > 0)
            {
                $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->bindAddress($this->local_host, $this->local_port);
            }

            //
            // open the socket
            //
            if ($this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->open() === false)
            {
                $this->generateError(\NetDNS2\Socket::SOCK_STREAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
            }
        }

        //
        // write the data to the socket; if it fails, continue on
        // the while loop
        //
        if ($this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->write($_data) === false)
        {
            $this->generateError(\NetDNS2\Socket::SOCK_STREAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
        }

        //
        // read the content, using select to wait for a response
        //
        $size       = 0;
        $result     = null;
        $response   = null;

        //
        // handle zone transfer requests differently than other requests.
        //
        if ($_axfr == true)
        {
            $soa_count = 0;

            while(1)
            {
                //
                // read the data off the socket
                //
                $result = $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->read($size,
                    ($this->dnssec == true) ? $this->dnssec_payload_size : \NetDNS2\Header::DNS_MAX_UDP_SIZE);

                if ( ($result === false) || ($size < \NetDNS2\Header::DNS_HEADER_SIZE) )    // @phpstan-ignore-line
                {
                    //
                    // if we get an error, then keeping this socket around for a future request, could cause an error- for example,
                    // https://github.com/mikepultz/netdns2/issues/61
                    //
                    // in this case, the connection was timing out, which once it did finally respond, left data on the socket, which could be captured
                    // on a subsequent request.
                    //
                    // since there's no way to "reset" a socket, the only thing we can do it close it.
                    //
                    $this->generateError(\NetDNS2\Socket::SOCK_STREAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
                }

                //
                // parse the first chunk as a packet
                //
                $chunk = new \NetDNS2\Packet\Response($result, $size);

                //
                // if this is the first packet, then clone it directly, then go through it to see if there are two SOA records (indicating that it's
                // the only packet)
                //
                if (is_null($response) == true)
                {
                    $response = clone $chunk;

                    //
                    // look for a failed response; if the zone transfer failed, then we don't need to do anything else at this point, and we should
                    // just break out.
                    //
                    if ($response->header->rcode != \NetDNS2\ENUM\RR\Code::NOERROR)
                    {
                        break;
                    }

                    //
                    // go through each answer
                    //
                    foreach($response->answer as $index => $rr)
                    {
                        //
                        // count the SOA records
                        //
                        if ($rr->type == \NetDNS2\ENUM\RR\Type::SOA)
                        {
                            $soa_count++;
                        }
                    }

                    //
                    // if we have 2 or more SOA records, then we're done; otherwise continue out so we read the rest of the packets off the socket
                    //
                    if ($soa_count >= 2)
                    {
                        break;
                    } else
                    {
                        continue;
                    }

                } else
                {
                    //
                    // go through all these answers, and look for SOA records
                    //
                    foreach($chunk->answer as $index => $rr)
                    {
                        //
                        // count the number of SOA records we find
                        //
                        if ($rr->type == \NetDNS2\ENUM\RR\Type::SOA)
                        {
                            $soa_count++;
                        }

                        //
                        // add the records to a single response object
                        //
                        $response->answer[] = clone $rr;
                    }

                    //
                    // if we've found the second SOA record, we're done
                    //
                    if ($soa_count >= 2)
                    {
                        break;
                    }
                }
            }

        //
        // everything other than a AXFR
        //
        } else
        {
            $result = $this->sock[\NetDNS2\Socket::SOCK_STREAM][$_ns]->read($size,
                ($this->dnssec == true) ? $this->dnssec_payload_size : \NetDNS2\Header::DNS_MAX_UDP_SIZE);

            if ( ($result === false) || ($size < \NetDNS2\Header::DNS_HEADER_SIZE) )   // @phpstan-ignore-line
            {
                $this->generateError(\NetDNS2\Socket::SOCK_STREAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
            }

            //
            // create the packet object
            //
            $response = new \NetDNS2\Packet\Response($result, $size);
        }

        //
        // store the query time
        //
        $response->response_time = microtime(true) - $start_time;

        //
        // add the name server that the response came from to the response object, and the socket type that was used.
        //
        $response->answer_from = $_ns;
        $response->answer_socket_type = \NetDNS2\Socket::SOCK_STREAM;

        //
        // return the \NetDNS2\Packet\Response object
        //
        return $response;
    }

    /**
     * sends a DNS request using UDP
     *
     * @param string  $_ns   the name server to use for the request
     * @param string  $_data the raw DNS packet data
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function sendUDPRequest(string $_ns, string $_data): \NetDNS2\Packet\Response
    {
        //
        // grab the start time
        //
        $start_time = microtime(true);

        //
        // see if we already have an open socket from a previous request; if so, try to use that instead of opening a new one.
        //
        if ( (isset($this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns]) === false) ||
            ( ($this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns] instanceof \NetDNS2\Socket) == false) )
        {
            //
            // create the socket object
            //
            $this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns] = new \NetDNS2\Socket(\NetDNS2\Socket::SOCK_DGRAM, $_ns, $this->dns_port, $this->timeout);

            //
            // if a local IP address / port is set, then add it
            //
            if (strlen($this->local_host) > 0)
            {
                $this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns]->bindAddress($this->local_host, $this->local_port);
            }

            //
            // open the socket
            //
            if ($this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns]->open() === false)
            {
                $this->generateError(\NetDNS2\Socket::SOCK_DGRAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
            }
        }

        //
        // write the data to the socket
        //
        if ($this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns]->write($_data) === false)
        {
            $this->generateError(\NetDNS2\Socket::SOCK_DGRAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
        }

        //
        // read the content, using select to wait for a response
        //
        $size = 0;

        $result = $this->sock[\NetDNS2\Socket::SOCK_DGRAM][$_ns]->read($size,
            ($this->dnssec == true) ? $this->dnssec_payload_size : \NetDNS2\Header::DNS_MAX_UDP_SIZE);

        if (( $result === false) || ($size < \NetDNS2\Header::DNS_HEADER_SIZE))    // @phpstan-ignore-line
        {
            $this->generateError(\NetDNS2\Socket::SOCK_DGRAM, $_ns, \NetDNS2\ENUM\Error::INT_FAILED_SOCKET);
        }

        //
        // create the packet object
        //
        $response = new \NetDNS2\Packet\Response($result, $size);

        //
        // store the query time
        //
        $response->response_time = microtime(true) - $start_time;

        //
        // add the name server that the response came from to the response object, and the socket type that was used.
        //
        $response->answer_from = $_ns;
        $response->answer_socket_type = \NetDNS2\Socket::SOCK_DGRAM;

        //
        // return the \NetDNS2\Packet\Response object
        //
        return $response;
    }

    /**
     * sends a DNS request using DoH
     *
     * @param string  $_ns   the name server to use for the request
     * @param string  $_data the raw DNS packet data
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function sendDOHRequest(string $_ns, string $_data): \NetDNS2\Packet\Response
    {
        //
        // we use cURL for DoH requests - so make sure it's loaded
        //
        if (extension_loaded('curl') === false)
        {
            throw new \NetDNS2\Exception('the curl extension is required to enable DoH.', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
        }

        //
        // make sure we have a name server
        //
        if (strlen($_ns) == 0)
        {
            throw new \NetDNS2\Exception('you must pass a value URL to connect to.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        //
        // grab the start time
        //
        $start_time = microtime(true);

        //
        // set up cURL; right now we only support POST requests to "standard" RFC 8484 configured DoH endpoints
        //
        // the assumption is the DNS servers provided has as URL template that matches one defined in section 4.1.1,
        // e.g. "https://cloudflare-dns.com/dns-query"
        //
        $c = curl_init();

        curl_setopt($c, CURLOPT_URL, $_ns);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);      // return the data
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);      // follow redirects by default
        curl_setopt($c, CURLOPT_MAXREDIRS, 5);              // but limit redirects to 5 so it doesn't get crazy
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $_data);
        curl_setopt($c, CURLOPT_HTTPHEADER, [

            'Accept: application/dns-message',
            'Content-Type: application/dns-message'
        ]);

        //
        // convert the seconds + microseconds float to milliseconds
        //
        curl_setopt($c, CURLOPT_TIMEOUT_MS, intval($this->timeout * 1000));

        //
        // if a local IP address / port is set, then have cURL bind to it for the request
        //
        if (strlen($this->local_host) > 0)
        {
            //
            // it's possible users are already setting the IPv6 brackets, so I'll just clean them off first
            //
            $host = str_replace([ '[', ']' ], '', $this->local_host);

            if (strlen($host) > 0)
            {
                if (self::isIPv4($host) == true)
                {
                    curl_setopt($c, CURLOPT_INTERFACE, $host);

                } elseif (self::isIPv6($host) == true)
                {
                    curl_setopt($c, CURLOPT_INTERFACE, '[' . $host . ']');

                } else
                {
                    throw new \NetDNS2\Exception(sprintf('invalid bind address value: %s', $this->local_host), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                }
            }

            //
            // then add the port
            //
            curl_setopt($c, CURLOPT_LOCALPORT, $this->local_port);
        }

        //
        // execute the cURL request
        //
        $result = curl_exec($c);
        if ($result === false)
        {
            throw new \NetDNS2\Exception(sprintf('curl failed with response: %s', curl_error($c)), \NetDNS2\ENUM\Error::INT_FAILED_CURL);
        }

        //
        // get the response code
        //
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        if ($code != 200)
        {
            throw new \NetDNS2\Exception(sprintf('curl failed with response code %d on host %s: %s', $code, $_ns, curl_error($c)), \NetDNS2\ENUM\Error::INT_FAILED_CURL);
        }

        //
        // create the packet object
        //
        $response = new \NetDNS2\Packet\Response(strval($result), strlen(strval($result)));

        //
        // store the query time
        //
        $response->response_time = microtime(true) - $start_time;

        //
        // add the name server that the response came from to the response object, and the socket type that was used.
        //
        $response->answer_from = $_ns;
        $response->answer_socket_type = \NetDNS2\Socket::SOCK_DGRAM;

        //
        // return the \NetDNS2\Packet\Response object
        //
        return $response;
    }

    /**
     * sets up some default network settings
     *
     * @throws \NetDNS2\Exception
     *
     */
    protected function initNetwork(): void
    {
        //
        // if TLS is enabled
        //
        if ($this->use_tls == true)
        {
            //
            // check for the OpenSSL extension
            //
            if (extension_loaded('openssl') === false)
            {
                throw new \NetDNS2\Exception('the openssl extension is required to enable DoT (TLS).', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
            }

            //
            // if the DNS port is unset, then use 853 for TLS connections
            //
            if ($this->dns_port == -1)
            {
                $this->dns_port = 853;
            }

            //
            // enable TCP
            //
            $this->use_tcp = true;
        }

        //
        // set the default port
        //
        if ($this->dns_port == -1)
        {
            $this->dns_port = 53;
        }
    }
}
