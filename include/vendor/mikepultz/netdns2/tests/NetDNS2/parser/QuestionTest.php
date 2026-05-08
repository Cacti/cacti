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

namespace NetDNS2\Tests;

/**
 * Test class to exercise \NetDNS2\Question construction and wire-format round-trips.
 *
 */
class QuestionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * A Question constructed with no packet has sensible default values.
     *
     */
    public function testDefaultConstruction(): void
    {
        $q = new \NetDNS2\Question();

        $this->assertSame('', (string)$q->qname);
        $this->assertSame('A', $q->qtype->label());
        $this->assertSame('IN', $q->qclass->label());
    }

    /**
     * A Question built via Packet\Request and re-parsed via Packet\Response
     * survives the binary round-trip with all fields intact.
     *
     */
    public function testWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'MX', 'IN');

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->question);

        $q = $res->question[0];

        $this->assertSame('example.com', (string)$q->qname);
        $this->assertSame('MX', $q->qtype->label());
        $this->assertSame('IN', $q->qclass->label());
    }

    /**
     * Wire round-trip preserves an AAAA query.
     *
     */
    public function testWireRoundTripAAAA(): void
    {
        $req = new \NetDNS2\Packet\Request('ipv6.example.com.', 'AAAA', 'IN');

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $q = $res->question[0];

        $this->assertSame('ipv6.example.com', (string)$q->qname);
        $this->assertSame('AAAA', $q->qtype->label());
    }

    /**
     * The Question::__toString() output contains the qname, type, and class.
     *
     */
    public function testToStringContainsFields(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'TXT', 'IN');

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $str = $res->question[0]->__toString();

        $this->assertStringContainsString('example.com', $str);
        $this->assertStringContainsString('TXT', $str);
        $this->assertStringContainsString('IN', $str);
    }

    /**
     * set() throws \NetDNS2\Exception when the packet is too short to hold
     * a valid question section (fewer than offset + 4 bytes available).
     *
     */
    public function testSetThrowsOnTruncatedPacket(): void
    {
        $this->expectException(\NetDNS2\Exception::class);

        //
        // Build a 12-byte DNS header only (qdcount=1) with no question section.
        // When set() is called, the packet will be too short to parse the
        // question entry and must throw INT_INVALID_PACKET.
        //
        $data  = pack('n', 0x1234);  // id
        $data .= pack('n', 0x0100);  // flags (RD=1)
        $data .= pack('n', 1);       // qdcount = 1
        $data .= pack('n', 0);       // ancount
        $data .= pack('n', 0);       // nscount
        $data .= pack('n', 0);       // arcount
        // deliberately omit the question section

        new \NetDNS2\Packet\Response($data, strlen($data));
    }

    /**
     * Question::get() advances the packet offset by the correct number of
     * bytes so that a second question can be parsed at the right position.
     *
     * We build a packet with qdcount=2, encoding two different questions,
     * and verify both are recovered correctly.
     *
     */
    public function testTwoQuestionOffsetTracking(): void
    {
        //
        // Craft a packet with two question entries manually.
        // We use Packet\Request for the first and splice the second in.
        //
        // Instead, build it directly to avoid relying on the Resolver's
        // qdcount enforcement.
        //
        $req1 = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');
        $req2 = new \NetDNS2\Packet\Request('example.org.', 'AAAA', 'IN');

        //
        // Extract raw question bytes by comparing full packet minus header.
        // The question section starts at byte 12.
        //
        $raw1 = $req1->get();
        $raw2 = $req2->get();

        $q1_bytes = substr($raw1, 12);
        $q2_bytes = substr($raw2, 12);

        //
        // Build a synthetic packet: header with qdcount=2 followed by both
        // question sections concatenated.
        //
        $header  = pack('n', 0x0001);  // id
        $header .= pack('n', 0x0100);  // flags (RD=1)
        $header .= pack('n', 2);       // qdcount
        $header .= pack('n', 0);       // ancount
        $header .= pack('n', 0);       // nscount
        $header .= pack('n', 0);       // arcount

        $combined = $header . $q1_bytes . $q2_bytes;

        $res = new \NetDNS2\Packet\Response($combined, strlen($combined));

        $this->assertCount(2, $res->question);
        $this->assertSame('example.com', (string)$res->question[0]->qname);
        $this->assertSame('A', $res->question[0]->qtype->label());
        $this->assertSame('example.org', (string)$res->question[1]->qname);
        $this->assertSame('AAAA', $res->question[1]->qtype->label());
    }
}
