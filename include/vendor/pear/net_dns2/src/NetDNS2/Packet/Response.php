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
 * This class handles building new DNS response packets; it parses binary packed packets that come off the wire
 *
 */
final class Response extends \NetDNS2\Packet
{
    /**
     * The name servers that this response came from
     */
    public string $answer_from;

    /**
     * The socket type the answer came from (TCP/UDP)
     */
    public int $answer_socket_type;

    /**
     * The query response time in microseconds
     */
    public float $response_time = 0;

    /**
     * Constructor - builds a new \NetDNS2\Packet\Response object
     *
     * @param string  $_data binary DNS packet
     * @param integer $_size the length of the DNS packet
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(string $_data, int $_size)
    {
        $this->set($_data, $_size);
    }

    /**
     * builds a new \NetDNS2\Packet\Response object
     *
     * @param string  $_data binary DNS packet
     * @param integer $_size the length of the DNS packet
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function set(string $_data, int $_size): bool
    {
        //
        // store the full packet
        //
        $this->rdata    = $_data;
        $this->rdlength = $_size;

        //
        // parse the header
        //
        // we don't bother checking the size earlier, because the first thing the header class does, is check the size and throw and exception if it's
        // invalid.
        //
        $this->header = new \NetDNS2\Header($this);

        //
        // if the truncation bit is set, then just return right here, because the rest of the packet is probably empty; and there's no point in processing
        // anything else.
        //
        // we also don't need to worry about checking to see if the the header is null or not, since the \NetDNS2\Header() constructor will throw an
        // exception if the packet is invalid.
        //
        if ($this->header->tc == 1)
        {
            return false;
        }

        //
        // parse the questions
        //
        for($x = 0; $x < $this->header->qdcount; ++$x)
        {
            $this->question[$x] = new \NetDNS2\Question($this);
        }

        //
        // parse the answers
        //
        for($x = 0; $x < $this->header->ancount; ++$x)
        {
            $o = \NetDNS2\RR::parse($this);

            if (is_null($o) == false)
            {
                $this->answer[] = clone $o;
            }
        }

        //
        // parse the authority section
        //
        for($x = 0; $x < $this->header->nscount; ++$x)
        {
            $o = \NetDNS2\RR::parse($this);

            if (is_null($o) == false)
            {
                $this->authority[] = clone $o;
            }
        }

        //
        // parse the additional section
        //
        for($x = 0; $x < $this->header->arcount; ++$x)
        {
            $o = \NetDNS2\RR::parse($this);

            if (is_null($o) == false)
            {
                $this->additional[] = clone $o;
            }
        }

        return true;
    }
}
