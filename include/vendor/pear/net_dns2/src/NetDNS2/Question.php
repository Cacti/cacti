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
 * This class handles parsing and constructing the question sectino of DNS packets.
 *
 * This is referred to as the "zone" for update per RFC2136
 *
 * DNS question format - RFC1035 section 4.1.2
 *
 *      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                                               |
 *    /                     QNAME                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     QTYPE                     |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     QCLASS                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class Question implements \Stringable
{
    /**
     * The name of the question
     *
     * referred to as "zname" for updates per RFC2136
     *
     */
    public \NetDNS2\Data\Domain $qname;

    /**
     * The RR type for the question
     *
     * referred to as "ztype" for updates per RFC2136
     *
     */
    public \NetDNS2\ENUM\RR\Type $qtype;

    /**
     * The RR class for the question
     *
     * referred to as "zclass" for updates per RFC2136
     *
     */
    public \NetDNS2\ENUM\RR\Classes $qclass;

    /**
     * Constructor - builds a new \NetDNS2\Question object
     *
     * @param \NetDNS2\Packet &$_packet either a \NetDNS2\Packet object, or null to build an empty object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(?\NetDNS2\Packet &$_packet = null)
    {
        if (is_null($_packet) == false)
        {
            $this->set($_packet);

        } else
        {
            $this->qname  = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035);
            $this->qtype  = \NetDNS2\ENUM\RR\Type::set('A');
            $this->qclass = \NetDNS2\ENUM\RR\Classes::set('IN');
        }
    }

    /**
     * magic __toString() function to return the \NetDNS2\Question object as a string
     *
     */
    public function __toString(): string
    {
        return ";;\n;; Question:\n;;\t " . $this->qname . '. ' . $this->qtype->label() . ' ' . $this->qclass->label() . "\n";
    }

    /**
     * builds a new \NetDNS2\Header object from a \NetDNS2\Packet object
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function set(\NetDNS2\Packet &$_packet): void
    {
        if ($_packet->rdlength < ($_packet->offset + 4))
        {
            throw new \NetDNS2\Exception('invalid or empty question section provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        //
        // expand the name
        //
        $this->qname = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $_packet, $_packet->offset);

        //
        // unpack the type and class
        //
        $val = unpack('nx/ny', $_packet->rdata, $_packet->offset);
        if ($val == false)
        {
            throw new \NetDNS2\Exception('failed to parse values from question section.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        list('x' => $type, 'y' => $class) = (array)$val;

        //
        // advance the offset pointer
        //
        $_packet->offset += 4;

        //
        // store it
        //
        $this->qtype  = \NetDNS2\ENUM\RR\Type::set($type);
        $this->qclass = \NetDNS2\ENUM\RR\Classes::set($class);
    }

    /**
     * returns a binary packed \NetDNS2\Question object
     *
     * @param \NetDNS2\Packet &$_packet the \NetDNS2\Packet object this question is part of. This needs to be passed in so that
     *                                 the compressed qname value can be packed in with the names of the other parts of the packet.
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function get(\NetDNS2\Packet &$_packet): string
    {
        $data = $this->qname->encode($_packet->offset) . pack('nn', $this->qtype->value, $this->qclass->value);

        $_packet->offset += 4;

        return $data;
    }
}
