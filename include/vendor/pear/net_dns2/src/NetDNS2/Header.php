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
 * DNS Packet Header class
 *
 * This class handles parsing and constructing DNS Packet Headers as defined by section 4.1.1 of RFC1035.
 *
 * DNS header format - RFC1035 section 4.1.1
 * DNS header format - RFC4035 section 3.2
 *
 *      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                      ID                       |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |QR|   Opcode  |AA|TC|RD|RA| Z|AD|CD|   RCODE   |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    QDCOUNT                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    ANCOUNT                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    NSCOUNT                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    ARCOUNT                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class Header implements \Stringable
{
    /**
     * max size of a UDP packet
     */
    public const DNS_MAX_UDP_SIZE      = 512;

    /**
     * size (in bytes) of a header in a standard DNS packet
     */
    public const DNS_HEADER_SIZE       = 12;

    /**
     * Query/Response flag
     */
    public const QR_QUERY              = 0;     // RFC 1035
    public const QR_RESPONSE           = 1;     // RFC 1035

    /**
     * DNS header values
     */
    public int $id;                         // 16 bit - identifier
    public int $qr;                         //  1 bit - 0 = query, 1 = response
    public \NetDNS2\ENUM\OpCode $opcode;    //  4 bit - op code
    public int $aa;                         //  1 bit - Authoritative Answer
    public int $tc;                         //  1 bit - TrunCation
    public int $rd;                         //  1 bit - Recursion Desired
    public int $ra;                         //  1 bit - Recursion Available
    public int $z;                          //  1 bit - Reserved
    public int $ad;                         //  1 bit - Authentic Data (RFC4035)
    public int $cd;                         //  1 bit - Checking Disabled (RFC4035)
    public \NetDNS2\ENUM\RR\Code $rcode;    //  4 bit - Response code
    public int $qdcount;                    // 16 bit - entries in the question section
    public int $ancount;                    // 16 bit - resource records in the answer section
    public int $nscount;                    // 16 bit - name server rr in the authority records section
    public int $arcount;                    // 16 bit - rr's in the additional records section

    /**
     * Constructor - builds a new NetDNS2\Header object
     *
     * @param \NetDNS2\Packet &$_packet either a NetDNS2\Packet object or null
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
            $this->id       = random_int(0, 65535);
            $this->qr       = self::QR_QUERY;
            $this->opcode   = \NetDNS2\ENUM\OpCode::QUERY;
            $this->aa       = 0;
            $this->tc       = 0;
            $this->rd       = 1;
            $this->ra       = 0;
            $this->z        = 0;
            $this->ad       = 0;
            $this->cd       = 0;
            $this->rcode    = \NetDNS2\ENUM\RR\Code::NOERROR;
            $this->qdcount  = 1;
            $this->ancount  = 0;
            $this->nscount  = 0;
            $this->arcount  = 0;
        }
    }

    /**
     * magic __toString() method to return the header as a string
     *
     */
    public function __toString(): string
    {
        $output = ";;\n;; Header:\n";

        $output .= ";;\t id         = " . $this->id . "\n";
        $output .= ";;\t qr         = " . $this->qr . "\n";
        $output .= ";;\t opcode     = " . $this->opcode->value . "\n";
        $output .= ";;\t aa         = " . $this->aa . "\n";
        $output .= ";;\t tc         = " . $this->tc . "\n";
        $output .= ";;\t rd         = " . $this->rd . "\n";
        $output .= ";;\t ra         = " . $this->ra . "\n";
        $output .= ";;\t z          = " . $this->z . "\n";
        $output .= ";;\t ad         = " . $this->ad . "\n";
        $output .= ";;\t cd         = " . $this->cd . "\n";
        $output .= ";;\t rcode      = " . $this->rcode->value . "\n";
        $output .= ";;\t qdcount    = " . $this->qdcount . "\n";
        $output .= ";;\t ancount    = " . $this->ancount . "\n";
        $output .= ";;\t nscount    = " . $this->nscount . "\n";
        $output .= ";;\t arcount    = " . $this->arcount . "\n";

        return $output;
    }

    /**
     * constructs a \NetDNS2\Header from a \NetDNS2\Packet object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function set(\NetDNS2\Packet &$_packet): bool
    {
        //
        // the header must be at least 12 bytes long.
        //
        if ($_packet->rdlength < self::DNS_HEADER_SIZE)
        {
            throw new \NetDNS2\Exception('invalid or empty header data provided.', \NetDNS2\ENUM\Error::INT_INVALID_PACKET);
        }

        $offset = 0;

        //
        // parse the values
        //
        $this->id       = ord($_packet->rdata[$offset]) << 8 | ord($_packet->rdata[++$offset]);

        ++$offset;
        $this->qr       = (ord($_packet->rdata[$offset]) >> 7) & 0x1;
        $this->opcode   = \NetDNS2\ENUM\OpCode::set((ord($_packet->rdata[$offset]) >> 3) & 0xf);
        $this->aa       = (ord($_packet->rdata[$offset]) >> 2) & 0x1;
        $this->tc       = (ord($_packet->rdata[$offset]) >> 1) & 0x1;
        $this->rd       = ord($_packet->rdata[$offset]) & 0x1;

        ++$offset;
        $this->ra       = (ord($_packet->rdata[$offset]) >> 7) & 0x1;
        $this->z        = (ord($_packet->rdata[$offset]) >> 6) & 0x1;
        $this->ad       = (ord($_packet->rdata[$offset]) >> 5) & 0x1;
        $this->cd       = (ord($_packet->rdata[$offset]) >> 4) & 0x1;
        $this->rcode    = \NetDNS2\ENUM\RR\Code::set(ord($_packet->rdata[$offset]) & 0xf);

        $this->qdcount  = ord($_packet->rdata[++$offset]) << 8 | ord($_packet->rdata[++$offset]);
        $this->ancount  = ord($_packet->rdata[++$offset]) << 8 | ord($_packet->rdata[++$offset]);
        $this->nscount  = ord($_packet->rdata[++$offset]) << 8 | ord($_packet->rdata[++$offset]);
        $this->arcount  = ord($_packet->rdata[++$offset]) << 8 | ord($_packet->rdata[++$offset]);

        //
        // increment the internal offset
        //
        $_packet->offset += self::DNS_HEADER_SIZE;

        return true;
    }

    /**
     * returns a binary packed DNS Header
     *
     */
    public function get(\NetDNS2\Packet &$_packet): string
    {
        $_packet->offset += self::DNS_HEADER_SIZE;

        return pack('n', $this->id) . chr(($this->qr << 7) | ($this->opcode->value << 3) | ($this->aa << 2) | ($this->tc << 1) | ($this->rd)) .
            chr(($this->ra << 7) | ($this->ad << 5) | ($this->cd << 4) | $this->rcode->value) .
            pack('n4', $this->qdcount, $this->ancount, $this->nscount, $this->arcount);
    }
}
