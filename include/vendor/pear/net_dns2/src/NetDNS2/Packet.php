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
 * This is the base class that holds a standard DNS packet.
 *
 * The \NetDNS2\Packet\Request and \NetDNS2\Packet\Response classes extend this class.
 *
 */
class Packet implements \Stringable
{
    /**
     * the full binary data and length for this packet
     */
    public string $rdata;
    public int $rdlength;

    /**
     * the offset pointer used when building/parsing packets
     */
    public int $offset = 0;

    /**
     * \NetDNS2\Header object with the DNS packet header
     */
    public \NetDNS2\Header $header;

    /**
     * array of \NetDNS2\Question objects
     *
     * used as "zone" for updates per RFC2136
     *
     * @var array<int,\NetDNS2\Question>
     */
    public array $question = [];

    /**
     * array of \NetDNS2\RR Objects for Answers
     *
     * used as "prerequisite" for updates per RFC2136
     *
     * @var array<int,\NetDNS2\RR>
     */
    public array $answer = [];

    /**
     * array of \NetDNS2\RR Objects for Authority
     *
     * used as "update" for updates per RFC2136
     *
     * @var array<int,\NetDNS2\RR>
     */
    public array $authority = [];

    /**
     * array of \NetDNS2\RR Objects for Addtitional
     *
     * @var array<int,\NetDNS2\RR>
     */
    public array $additional = [];

    /**
     * magic __toString() method to return the \NetDNS2\Packet as a string
     *
     */
    public function __toString(): string
    {
        $output = $this->header->__toString();

        foreach($this->question as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->answer as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->authority as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->additional as $x)
        {
            $output .= $x->__toString() . "\n";
        }

        return $output;
    }

    /**
     * returns a full binary DNS packet
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function get(): string
    {
        //
        // clear name compression class first
        //
        \NetDNS2\Data::$compressed = [];

        $data = $this->header->get($this);

        foreach($this->question as $x)
        {
            $data .= $x->get($this);
        }
        foreach($this->answer as $x)
        {
            $data .= $x->get($this);
        }
        foreach($this->authority as $x)
        {
            $data .= $x->get($this);
        }
        foreach($this->additional as $x)
        {
            $data .= $x->get($this);
        }

        return $data;
    }

    /**
     * copies the contents of the given packet, to the local packet object. this function intentionally ignores some of the packet data.
     *
     * @param \NetDNS2\Packet $_packet the DNS packet to copy the data from
     *
     */
    public function copy(\NetDNS2\Packet $_packet): void
    {
        $this->header     = $_packet->header;
        $this->question   = $_packet->question;
        $this->answer     = $_packet->answer;
        $this->authority  = $_packet->authority;
        $this->additional = $_packet->additional;
    }

    /**
     * resets the values in the current packet object
     *
     */
    public function reset(): void
    {
        $this->header->id = random_int(0, 65535);
        $this->rdata      = '';
        $this->rdlength   = 0;
        $this->offset     = 0;
        $this->answer     = [];
        $this->authority  = [];
        $this->additional = [];
    }
}
