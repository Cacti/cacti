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
 * register the auto-load function
 *
 */
spl_autoload_register(function($_class)
{
    if (strncmp($_class, 'NetDNS2', 7) == 0)
    {
        require_once \str_replace('\\', DIRECTORY_SEPARATOR, $_class) . '.php';
    }
});

/**
 * This is the main resolver class, providing DNS query functions.
 *
 */
final class Resolver extends Client
{
    /**
     * Constructor - creates a new \NetDNS2\Resolver object
     *
     * @param array<string,mixed> $_options either an array with options or null
     *
     */
    public function __construct(?array $_options = null)
    {
        parent::__construct($_options);
    }

    /**
     * does a basic DNS lookup query
     *
     * @param string $_name  the DNS name to loookup
     * @param string $_type  the name of the RR type to lookup
     * @param string $_class the name of the RR class to lookup
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function query(string $_name, string $_type = 'A', string $_class = 'IN'): \NetDNS2\Packet\Response
    {
        //
        // init some network settings
        //
        $this->initNetwork();

        //
        // make sure we have some name servers set
        //
        $this->checkServers(\NetDNS2\Client::RESOLV_CONF);

        //
        // we dont' support incremental zone tranfers; so if it's requested, a full zone transfer can be returned
        //
        if ($_type == 'IXFR')
        {
            $_type = 'AXFR';
        }

        //
        // if the name *looks* too short, then append the domain from the config
        //
        if ( (strpos($_name, '.') === false) && (strlen($this->domain) > 0) && ($_type != 'PTR') )
        {
            $_name .= '.' . strtolower($this->domain);
        }

        //
        // clear the name compression cache
        //
        \NetDNS2\Data::$compressed = [];

        //
        // create a new packet based on the input
        //
        $packet = new \NetDNS2\Packet\Request($_name, $_type, $_class);

        //
        // check for an authentication method; either TSIG or SIG
        //
        if ( (is_null($this->auth_signature) == false) && (($this->auth_signature instanceof \NetDNS2\RR\TSIG) || ($this->auth_signature instanceof \NetDNS2\RR\SIG)) )
        {
            $packet->additional[]    = clone $this->auth_signature;
            $packet->header->arcount = count($packet->additional);
        }

        //
        // check for the DNSSEC flag, and if it's true, then add an OPT RR to the additional section, and set the DO flag to 1.
        //
        if ($this->dnssec == true)
        {
            $this->edns->dnssec(true);
        }

        //
        // look for additional EDNS request objects; merge all options into a single OPT record
        // per RFC 6891 §6.1.1 ("at most one OPT RR in the additional data section")
        //
        $merged_opt = $this->edns->build($this->dnssec_payload_size);
        if ($merged_opt !== null)
        {
            $packet->additional[]    = $merged_opt;
            $packet->header->arcount = count($packet->additional);
        }

        //
        // set the DNSSEC AD or CD bits
        //
        if ($this->dnssec_ad_flag == true)
        {
            $packet->header->ad = 1;
        }
        if ($this->dnssec_cd_flag == true)
        {
            $packet->header->cd = 1;
        }

        //
        // if caching is turned on, then hash the question and do a cache lookup.
        //
        // don't use the cache for zone transfers
        //
        $packet_hash = '';

        if ( ($this->use_cache == true) && ($this->cacheable($_type) == true) )
        {
            //
            // build the key and check for it in the cache.
            //
            $packet_hash = hash('xxh128', $packet->question[0]->qname . '|' . $packet->question[0]->qtype->label());

            $response = $this->cache->get($packet_hash);
            if ($response !== false)
            {
                return $response;
            }
        }

        //
        // set the RD (recursion desired) bit to 1 / 0 depending on the config setting.
        //
        if ($this->recurse == false)
        {
            $packet->header->rd = 0;
        } else
        {
            $packet->header->rd = 1;
        }

        //
        // send the packet and get back the response
        //
        // *always* use TCP for zone transfers
        //
        $response = $this->sendPacket($packet, ($_type == 'AXFR') ? true : $this->use_tcp);

        //
        // if strict mode is enabled, then make sure that the name that was looked up is *actually* in the response object.
        //
        // only do this is strict_query_mode is turned on, AND we've received some answers; no point doing any else if there were no answers.
        //
        if ( ($this->strict_query_mode == true) && ($response->header->ancount > 0) )
        {
            $found = false;

            //
            // look for the requested name/type/class
            //
            foreach($response->answer as $index => $object)
            {
                if ( (strcasecmp(trim($object->name->value(), '.'), trim($packet->question[0]->qname->value(), '.')) == 0) &&
                    ($object->type == $packet->question[0]->qtype) && ($object->class == $packet->question[0]->qclass) )
                {
                    $found = true;
                    break;
                }
            }

            //
            // if it's not found, then unset the answer section; it's not correct to throw an exception here; if the hostname didn't exist, then
            // sendPacket() would have already thrown an NXDOMAIN error- so the host *exists*, but just not the request type/class.
            //
            // the correct response in this case, is an empty answer section; the authority section may still have usual information, like a SOA record.
            //
            if ($found == false)
            {
                $response->answer = [];
                $response->header->ancount = 0;
            }
        }

        //
        // cache the response object
        //
        if ( ($this->use_cache == true) && ($this->cacheable($_type) == true) )
        {
            $this->cache->put($packet_hash, $response);
        }

        return $response;
    }

    /**
     * does an inverse query for the given RR; most DNS servers do not implement inverse queries, but they should be able to return "not implemented"
     *
     * @param \NetDNS2\RR $_rr the RR object to lookup
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function iquery(\NetDNS2\RR $_rr): \NetDNS2\Packet\Response
    {
        //
        // make sure we have some name servers set
        //
        $this->checkServers(\NetDNS2\Client::RESOLV_CONF);

        //
        // create an empty packet
        //
        $packet = new \NetDNS2\Packet\Request(strval($_rr->name), 'A', 'IN');

        //
        // unset the question
        //
        $packet->question = [];
        $packet->header->qdcount = 0;

        //
        // set the opcode to IQUERY
        //
        $packet->header->opcode = \NetDNS2\ENUM\OpCode::IQUERY;

        //
        // add the given RR as the answer
        //
        $packet->answer[] = clone $_rr;
        $packet->header->ancount = 1;

        //
        // check for an authentication method; either TSIG or SIG
        //
        if ( (($this->auth_signature instanceof \NetDNS2\RR\TSIG) == true) || (($this->auth_signature instanceof \NetDNS2\RR\SIG) == true) )
        {
            $packet->additional[] = clone $this->auth_signature;
            $packet->header->arcount = count($packet->additional);
        }

        //
        // send the packet and get back the response
        //
        return $this->sendPacket($packet, $this->use_tcp);
    }
}
