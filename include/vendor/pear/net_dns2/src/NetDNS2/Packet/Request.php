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

namespace NetDNS2\Packet;

/**
 * This class handles building new DNS request packets; packets used for DNS queries and updates.
 *
 */
final class Request extends \NetDNS2\Packet
{
    /**
     * Constructor - builds a new \NetDNS2\Packet\Request object
     *
     * @param string $_name  the domain name for the packet
     * @param string $_type  the DNS RR type for the packet
     * @param string $_class the DNS class for the packet
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(string $_name, string $_type = 'A', string $_class = 'IN')
    {
        $this->set($_name, $_type, $_class);
    }

    /**
     * builds a new \NetDNS2\Packet\Request object
     *
     * @param string $_name  the domain name for the packet
     * @param string $_type  the DNS RR type for the packet
     * @param string $_class the DNS class for the packet
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function set(string $_name, string $_type = 'A', string $_class = 'IN'): bool
    {
        //
        // generate a new header
        //
        $this->header = new \NetDNS2\Header();

        //
        // if the type is "*", rename it to "ANY"- both are acceptable.
        //
        if ($_type == '*')
        {
            $_type = 'ANY';
        }

        //
        // add a new question
        //
        $q = new \NetDNS2\Question();

        //
        // sanitize a bit
        //
        $_name = trim($_name);

        //
        // store the data
        //
        $q->qtype  = \NetDNS2\ENUM\RR\Type::set($_type);
        $q->qclass = \NetDNS2\ENUM\RR\Classes::set($_class);

        //
        // check that the input string has some data in it
        //
        if (strlen($_name) == 0)
        {
            throw new \NetDNS2\Exception('invalid or empty  query string provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        if ($q->qtype == \NetDNS2\ENUM\RR\Type::PTR)
        {
            //
            // if it's a PTR request for an IP address, then make sure we tack on the arpa domain.
            //
            // there are other types of PTR requests, so if an IP adress doesn't match, then just let it flow through and assume it's a hostname
            //
            if (\NetDNS2\Client::isIPv4(strval($_name)) == true)
            {
                $q->qname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, implode('.', array_reverse(explode('.', $_name))) . '.in-addr.arpa');

            } elseif (\NetDNS2\Client::isIPv6(strval($_name)) == true)
            {
                $q->qname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035,
                    implode('.', array_reverse(str_split(str_replace(':', '', \NetDNS2\Client::expandIPv6($_name))))) . '.ip6.arpa');
            } else
            {
                $q->qname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
            }

        } else
        {
            $q->qname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        }

        $this->question[] = clone $q;

        //
        // the answer, authority and additional are empty; they can be modified
        // after the request is created for UPDATE requests if needed.
        //
        $this->answer     = [];
        $this->authority  = [];
        $this->additional = [];

        return true;
    }
}
