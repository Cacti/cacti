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
 * The main dynamic DNS notifier class.
 *
 * This class provides functions to handle DNS notify requests as defined by RFC 1996.
 *
 * This is separate from the \NetDNS2\Resolver class, as while the underlying protocol is the same, the functionality is
 * completely different. Generally, query (recursive) lookups are done against caching server, while notify requests are
 * done against authoratative servers.
 *
 */
final class Notifier extends \NetDNS2\Client
{
    /**
     * a \NetDNS2\Packet\Request object used for the notify request
     */
    private \NetDNS2\Packet\Request $m_packet;

    /**
     * Constructor - builds a new \NetDNS2\Notifier objected used for doing DNS notification for a changed zone
     *
     * @param string              $_zone    the domain name to use for DNS updates
     * @param array<string,mixed> $_options an array of config options or null
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(string $_zone, ?array $_options = null)
    {
        parent::__construct($_options);

        //
        // create the packet
        //
        $this->m_packet = new \NetDNS2\Packet\Request($_zone, 'SOA', 'IN');

        //
        // make sure the opcode on the packet is set to NOTIFY
        //
        $this->m_packet->header->opcode = \NetDNS2\ENUM\OpCode::NOTIFY;
    }

    /**
     * checks that the given name matches the name for the zone we're notifying
     *
     * @param string $_name The name to be checked.
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function checkName(string $_name): void
    {
        if (preg_match('/' . $this->m_packet->question[0]->qname . '$/', $_name) !== 1)
        {
            throw new \NetDNS2\Exception(sprintf('name %s does not match zone name %s.', $_name, $this->m_packet->question[0]->qname), \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        return;
    }

    /**
     * 3.7 - Add RR to notify
     *
     * @param \NetDNS2\RR $_rr the \NetDNS2\RR object to be sent in the notify message
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function add(\NetDNS2\RR $_rr): void
    {
        $this->checkName(strval($_rr->name));

        //
        // add the RR to the "notify" section
        //
        if (in_array($_rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $_rr;
        }

        return;
    }

    /**
     * returns the current internal packet object.
     *
     */
    public function packet(): \NetDNS2\Packet\Request
    {
        //
        // take a copy
        //
        $p = clone $this->m_packet;

        //
        // check for an authentication method; either TSIG or SIG
        //
        if ( (($this->auth_signature instanceof \NetDNS2\RR\TSIG) == true) || (($this->auth_signature instanceof \NetDNS2\RR\SIG) == true) )
        {
            $p->additional[] = clone $this->auth_signature;
        }

        //
        // update the counts
        //
        $p->header->qdcount = count($p->question);
        $p->header->ancount = count($p->answer);
        $p->header->nscount = count($p->authority);
        $p->header->arcount = count($p->additional);

        return $p;
    }

    /**
     * executes the notify request
     *
     * @param \NetDNS2\Packet\Response &$_response ref to the response object
     * @param-out \NetDNS2\Packet\Response $_response
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function notify(?\NetDNS2\Packet\Response &$_response = null): void
    {
        //
        // init some network settings
        //
        $this->initNetwork();

        //
        // check for an authentication method; either TSIG or SIG
        //
        if ( (($this->auth_signature instanceof \NetDNS2\RR\TSIG) == true) || (($this->auth_signature instanceof \NetDNS2\RR\SIG) == true) )
        {
            $this->m_packet->additional[] = clone $this->auth_signature;
        }

        //
        // update the counts
        //
        $this->m_packet->header->qdcount = count($this->m_packet->question);
        $this->m_packet->header->ancount = count($this->m_packet->answer);
        $this->m_packet->header->nscount = count($this->m_packet->authority);
        $this->m_packet->header->arcount = count($this->m_packet->additional);

        //
        // make sure we have some data to send
        //
        if ($this->m_packet->header->qdcount == 0)
        {
            throw new \NetDNS2\Exception('invalid or empty header data provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        //
        // send the packet and get back the response
        //
        $_response = $this->sendPacket($this->m_packet, $this->use_tcp);

        //
        // clear the internal packet so if we make another request, we don't have old data being sent.
        //
        $this->m_packet->reset();

        //
        // clear the name compression cache
        //
        \NetDNS2\Data::$compressed = [];
    }
}
