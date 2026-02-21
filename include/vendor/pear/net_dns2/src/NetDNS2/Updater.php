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
 * The main dynamic DNS updater class.
 *
 * This class provices functions to handle all defined dynamic DNS update requests as defined by RFC 2136.
 *
 * This is separate from the \NetDNS2\Resolver class, as while the underlying protocol is the same, the functionality is completely different.
 *
 * Generally, query (recursive) lookups are done against caching server, while update requests are done against authoratative servers.
 *
 */
final class Updater extends \NetDNS2\Client
{
    /**
     * a \NetDNS2\Packet\Request object used for the update request
     */
    private \NetDNS2\Packet\Request $m_packet;

    /**
     * Constructor - builds a new \NetDNS2\Updater objected used for doing dynamic DNS updates
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
        $this->m_packet = new \NetDNS2\Packet\Request(strtolower(trim($_zone, " \n\r\t.")), 'SOA', 'IN');

        //
        // make sure the opcode on the packet is set to UPDATE
        //
        $this->m_packet->header->opcode = \NetDNS2\ENUM\OpCode::UPDATE;
    }

    /**
     * checks that the given name matches the name for the zone we're updating
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
    }

    /**
     * 2.5.1 - Add To An RRset
     *
     * RRs are added to the Update Section whose NAME, TYPE, TTL, RDLENGTH and RDATA are those being added, and CLASS is the same as the zone class. Any
     * duplicate RRs will be silently ignored by the primary master.
     *
     * @param \NetDNS2\RR $_rr the \NetDNS2\RR object to be added to the zone
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function add(\NetDNS2\RR $_rr): void
    {
        $this->checkName(strval($_rr->name));

        //
        // add the RR to the "update" section
        //
        if (in_array($_rr, $this->m_packet->authority) == false)
        {
            $this->m_packet->authority[] = clone $_rr;
        }
    }

    /**
     * 2.5.4 - Delete An RR From An RRset
     *
     * RRs to be deleted are added to the Update Section. The NAME, TYPE, RDLENGTH and RDATA must match the RR being deleted. TTL must be specified as
     * zero (0) and will otherwise be ignored by the primary master. CLASS must be specified as NONE to distinguish this from an RR addition. If no
     * such RRs exist, then this Update RR will be silently ignored by the primary master.
     *
     * @param \NetDNS2\RR $_rr the \NetDNS2\RR object to be deleted from the zone
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function delete(\NetDNS2\RR $_rr): void
    {
        $this->checkName(strval($_rr->name));

        $_rr->ttl   = 0;
        $_rr->class = \NetDNS2\ENUM\RR\Classes::set('NONE');

        //
        // add the RR to the "update" section
        //
        if (in_array($_rr, $this->m_packet->authority) == false)
        {
            $this->m_packet->authority[] = clone $_rr;
        }
    }

    /**
     * 2.5.2 - Delete An RRset
     *
     * One RR is added to the Update Section whose NAME and TYPE are those of the RRset to be deleted. TTL must be specified as zero (0) and is otherwise
     * not used by the primary master. CLASS must be specified as ANY. RDLENGTH must be zero (0) and RDATA must therefore be empty. If no such RRset
     * exists, then this Update RR will be silently ignored by the primary master
     *
     * @param string $_name the RR name to be removed from the zone
     * @param string $_type the RR type to be removed from the zone
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function deleteAny(string $_name, string $_type): void
    {
        $this->checkName($_name);

        /**
         * @var \NetDNS2\RR $rr
         */
        $rr = new (\NetDNS2\ENUM\RR\Type::set($_type)->class());

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "update" section
        //
        if (in_array($rr, $this->m_packet->authority) == false)
        {
            $this->m_packet->authority[] = clone $rr;
        }
    }

    /**
     * 2.5.3 - Delete All RRsets From A Name
     *
     * One RR is added to the Update Section whose NAME is that of the name to be cleansed of RRsets. TYPE must be specified as ANY. TTL must be specified
     * as zero (0) and is otherwise not used by the primary master. CLASS must be specified as ANY. RDLENGTH must be zero (0) and RDATA must therefore be
     * empty. If no such RRsets exist, then this Update RR will be silently ignored by the primary master.
     *
     * @param string $_name the RR name to be removed from the zone
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function deleteAll(string $_name): void
    {
        $this->checkName($_name);

        //
        // the \NetDNS2\RR\ANY class is just an empty stub class used for these cases only
        //
        $rr = new \NetDNS2\RR\ANY();

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->type     = \NetDNS2\ENUM\RR\Type::set('ANY');
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "update" section
        //
        if (in_array($rr, $this->m_packet->authority) == false)
        {
            $this->m_packet->authority[] = clone $rr;
        }
    }

    /**
     * 2.4.1 - RRset Exists (Value Independent)
     *
     * At least one RR with a specified NAME and TYPE (in the zone and class specified in the Zone Section) must exist.
     *
     * For this prerequisite, a requestor adds to the section a single RR whose NAME and TYPE are equal to that of the zone RRset whose existence is
     * required. RDLENGTH is zero and RDATA is therefore empty. CLASS must be specified as ANY to differentiate this condition from that of an actual RR
     * whose RDLENGTH is naturally zero (0) (e.g., NULL). TTL is specified as zero (0).
     *
     * @param string $_name the RR name for the prerequisite
     * @param string $_type the RR type for the prerequisite
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function checkExists(string $_name, string $_type): void
    {
        $this->checkName($_name);

        /**
         * @var \NetDNS2\RR $rr
         */
        $rr = new (\NetDNS2\ENUM\RR\Type::set($_type)->class());

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "prerequisite" section
        //
        if (in_array($rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $rr;
        }
    }

    /**
     * 2.4.2 - RRset Exists (Value Dependent)
     *
     * A set of RRs with a specified NAME and TYPE exists and has the same members with the same RDATAs as the RRset specified here in this section. While
     * RRset ordering is undefined and therefore not significant to this comparison, the sets be identical in their extent.
     *
     * For this prerequisite, a requestor adds to the section an entire RRset whose preexistence is required. NAME and TYPE are that of the RRset being
     * denoted. CLASS is that of the zone. TTL must be specified as zero (0) and is ignored when comparing RRsets for identity.
     *
     * @param \NetDNS2\RR $_rr the RR object to be used as a prerequisite
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function checkValueExists(\NetDNS2\RR $_rr): void
    {
        $this->checkName(strval($_rr->name));

        $_rr->ttl = 0;

        //
        // add the RR to the "prerequisite" section
        //
        if (in_array($_rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $_rr;
        }
    }

    /**
     * 2.4.3 - RRset Does Not Exist
     *
     * No RRs with a specified NAME and TYPE (in the zone and class denoted by the Zone Section) can exist.
     *
     * For this prerequisite, a requestor adds to the section a single RR whose NAME and TYPE are equal to that of the RRset whose nonexistence is
     * required. The RDLENGTH of this record is zero (0), and RDATA field is therefore empty. CLASS must be specified as NONE in order to distinguish
     * this condition from a valid RR whose RDLENGTH is naturally zero (0) (for example, the NULL RR). TTL must be specified as zero (0).
     *
     * @param string $_name the RR name for the prerequisite
     * @param string $_type the RR type for the prerequisite
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function checkNotExists(string $_name, string $_type): void
    {
        $this->checkName($_name);

        /**
         * @var \NetDNS2\RR $rr
         */
        $rr = new (\NetDNS2\ENUM\RR\Type::set($_type)->class());

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('NONE');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "prerequisite" section
        //
        if (in_array($rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $rr;
        }
    }

    /**
     * 2.4.4 - Name Is In Use
     *
     * Name is in use.  At least one RR with a specified NAME (in the zone and class specified by the Zone Section) must exist. Note that this
     * prerequisite is NOT satisfied by empty nonterminals.
     *
     * For this prerequisite, a requestor adds to the section a single RR whose NAME is equal to that of the name whose ownership of an RR is required.
     * RDLENGTH is zero and RDATA is therefore empty. CLASS must be specified as ANY to differentiate this condition from that of an actual RR whose
     * RDLENGTH is naturally zero (0) (e.g., NULL). TYPE must be specified as ANY to differentiate this case from that of an RRset existence test. TTL
     * is specified as zero (0).
     *
     * @param string $_name the RR name for the prerequisite
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function checkNameInUse(string $_name): void
    {
        $this->checkName($_name);

        //
        // the \NetDNS2\RR\ANY class is just an empty stub class used for these cases only
        //
        $rr = new \NetDNS2\RR\ANY();

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->type     = \NetDNS2\ENUM\RR\Type::set('ANY');
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "prerequisite" section
        //
        if (in_array($rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $rr;
        }
    }

    /**
     * 2.4.5 - Name Is Not In Use
     *
     * Name is not in use.  No RR of any type is owned by a specified NAME. Note that this prerequisite IS satisfied by  empty nonterminals.
     *
     * For this prerequisite, a requestor adds to the section a single RR whose NAME is equal to that of the name whose nonownership of any RRs is
     * required. RDLENGTH is zero and RDATA is therefore empty. CLASS must be specified as NONE. TYPE must be specified as ANY. TTL must be specified
     * as zero (0).
     *
     * @param string $_name the RR name for the prerequisite
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function checkNameNotInUse(string $_name): void
    {
        $this->checkName($_name);

        //
        // the \NetDNS2\RR\ANY class is just an empty stub class used for these cases only
        //
        $rr = new \NetDNS2\RR\ANY();

        $rr->name     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_name);
        $rr->ttl      = 0;
        $rr->type     = \NetDNS2\ENUM\RR\Type::set('ANY');
        $rr->class    = \NetDNS2\ENUM\RR\Classes::set('NONE');
        $rr->rdlength = -1;
        $rr->rdata    = '';

        //
        // add the RR to the "prerequisite" section
        //
        if (in_array($rr, $this->m_packet->answer) == false)
        {
            $this->m_packet->answer[] = clone $rr;
        }
    }

    /**
     * returns the current internal packet object.
     #
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
     * executes the update request with the object informaton
     *
     * @param \NetDNS2\Packet\Response &$_response ref to the response object
     * @param-out \NetDNS2\Packet\Response $_response
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function update(?\NetDNS2\Packet\Response &$_response = null): bool
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
        if ( ($this->m_packet->header->qdcount == 0) || ($this->m_packet->header->nscount == 0) )
        {
            throw new \NetDNS2\Exception('invalid or empty packet data provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        //
        // send the packet and get back the response
        //
        $_response = $this->sendPacket($this->m_packet, $this->use_tcp);

        //
        // clear the internal packet so if we make another request, we don't have
        // old data being sent.
        //
        $this->m_packet->reset();

        //
        // clear the name compression cache
        //
        \NetDNS2\Data::$compressed = [];

        //
        // for updates, we just need to know it worked- we don't actualy need to
        // return the response object
        //
        return true;
    }
}
