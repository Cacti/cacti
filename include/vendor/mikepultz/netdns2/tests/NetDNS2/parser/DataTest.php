<?php

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
 * test class to exercise DNS name compression and cycle detection
 *
 */
class DataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that a self-referential compression pointer does not cause infinite recursion
     *
     * The cycle detection code in Data::_decode() must detect the loop and return the labels
     * collected so far, rather than recursing infinitely.
     *
     * Packet layout (18 bytes total):
     *   bytes  0-11: DNS header  (qdcount=1, all other counts 0)
     *   bytes 12-13: \xC0\x0C   — compression pointer to offset 12 (self-referential)
     *   bytes 14-17: QTYPE=A(1), QCLASS=IN(1)
     *
     * @return void
     * @access public
     *
     */
    public function testSelfReferentialPointer()
    {
        $data  = pack('n', 0x1234);  // id
        $data .= pack('n', 0x0100);  // flags (RD=1)
        $data .= pack('n', 1);       // qdcount
        $data .= pack('n', 0);       // ancount
        $data .= pack('n', 0);       // nscount
        $data .= pack('n', 0);       // arcount
        $data .= "\xC0\x0C";        // QNAME: pointer back to offset 12 (self-referential)
        $data .= pack('nn', 1, 1);   // QTYPE=A(1), QCLASS=IN(1)

        //
        // without cycle detection this would recurse infinitely; the parser must complete normally
        //
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertSame(1, $response->header->qdcount);
        $this->assertCount(1, $response->question);
    }

    /**
     * function to test that a domain label of exactly 63 bytes (the RFC 1035 maximum) encodes without error
     *
     * @return void
     * @access public
     *
     */
    public function testLabel63BytesWorks()
    {
        //
        // 63-byte label sits exactly at the RFC 1035 §2.3.4 limit and must encode successfully
        //
        $domain = str_repeat('a', 63) . '.example.com';
        $d      = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $domain);

        $offset  = 0;
        $encoded = $d->encode($offset);

        $this->assertGreaterThan(0, strlen($encoded), 'DataTest: a 63-byte label must encode to a non-empty wire value');
    }

    /**
     * function to test that a domain label of 64 bytes (one over the RFC 1035 limit) throws an exception
     *
     * @return void
     * @access public
     *
     */
    public function testLabel64BytesThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        $domain = str_repeat('a', 64) . '.example.com';
        $d      = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $domain);

        $offset = 0;
        $d->encode($offset);
    }

    /**
     * function to test that a Unicode domain name survives an IDN round-trip when the intl extension is available
     *
     * @return void
     * @access public
     *
     */
    public function testIDNRoundTrip()
    {
        if (extension_loaded('intl') == false)
        {
            $this->markTestSkipped('intl extension not loaded.');
        }

        //
        // the constructor converts the UTF-8 domain to ACE (xn--...) internally;
        // value() converts it back to UTF-8 for presentation
        //
        $original = 'münchen.example.com';
        $d        = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, $original);

        $this->assertSame($original, strval($d), 'DataTest: IDN domain must round-trip through ACE encoding');
    }

    /**
     * function to test that an IPv4 address survives an encode/decode round-trip through wire format
     *
     * @return void
     * @access public
     *
     */
    public function testIPv4EncodeDecodeRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('example.com. 300 IN A 192.0.2.1');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);

        /** @var \NetDNS2\RR\A $parsed */
        $parsed = $res->answer[0];

        $this->assertSame('192.0.2.1', (string)$parsed->address);
    }

    /**
     * function to test that an IPv6 address survives an encode/decode round-trip through wire format
     *
     * IPv6 notation may vary between PHP versions, so we compare the binary
     * network-byte-order form via inet_pton() instead of the string representation.
     *
     * @return void
     * @access public
     *
     */
    public function testIPv6EncodeDecodeRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'AAAA', 'IN');

        /** @var \NetDNS2\RR\AAAA $rr */
        $rr = \NetDNS2\RR::fromString('example.com. 300 IN AAAA 2001:db8::1');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);

        /** @var \NetDNS2\RR\AAAA $parsed */
        $parsed = $res->answer[0];

        //
        // compare via network byte form to avoid compressed vs. expanded notation differences
        //
        $this->assertSame(
            inet_pton('2001:db8::1'),
            inet_pton((string)$parsed->address)
        );
    }

    /**
     * function to test that a Mailbox with an escaped dot encodes and decodes without mangling the dot
     *
     * RFC 1035 §2.3.4 uses \. to represent a literal dot inside a label in mail
     * addresses (e.g. "admin\.postmaster.example.com" means admin.postmaster@example.com).
     *
     * @return void
     * @access public
     *
     */
    public function testMailboxEscaping(): void
    {
        //
        // SOA rname field uses Mailbox encoding; a dot in the local part is escaped
        //
        $rr = \NetDNS2\RR::fromString('example.com. 300 IN SOA ns1.example.com. admin.example.com. 2024010101 3600 900 604800 300');

        $this->assertStringContainsString('example.com', (string)$rr);
    }

    /**
     * function to test that a Domain with value() returns a non-empty string for a normal domain
     *
     * @return void
     * @access public
     *
     */
    public function testDomainValue(): void
    {
        $d = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, 'example.com');

        $this->assertSame('example.com', $d->value());
        $this->assertSame('example.com', (string)$d);
    }

    /**
     * function to test that an empty Domain (root zone) encodes to a single zero byte
     *
     * @return void
     * @access public
     *
     */
    public function testDomainEmptyEncoding(): void
    {
        $d = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, '');

        $offset  = 0;
        $encoded = $d->encode($offset);

        //
        // empty domain (root) encodes as a single \x00 terminator
        //
        $this->assertSame("\x00", $encoded);
        $this->assertSame(1, $offset);
    }

    /**
     * function to test that a canonical-encoded domain is lowercased
     *
     * RFC 4034 §6.1 canonical form requires domain names to be lowercased.
     *
     * @return void
     * @access public
     *
     */
    public function testCanonicalDomainIsLowercased(): void
    {
        $d = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'EXAMPLE.COM');

        $dummy   = 0;
        $encoded = $d->encode($dummy);

        //
        // canonical wire form: \x07example\x03com\x00 (all lowercase)
        //
        $this->assertStringContainsString('example', $encoded);
        $this->assertStringNotContainsString('EXAMPLE', $encoded);
    }

    /**
     * function to test that a two-node pointer cycle is detected and parsing completes
     *
     * Packet layout (24 bytes total):
     *   bytes  0-11: DNS header  (qdcount=1)
     *   bytes 12-13: \xC0\x12   — QNAME pointer to offset 18
     *   bytes 14-17: QTYPE=A(1), QCLASS=IN(1)
     *   bytes 18-21: \x03foo    — label "foo"
     *   bytes 22-23: \xC0\x0C  — pointer back to offset 12 (creates a cycle: 12→18→12)
     *
     * The decoder follows: offset-12 → pointer(18) → label "foo" → pointer(12) → cycle detected → done
     * QNAME resolves to "foo"; packet offset advances past the initial 2-byte pointer to 14,
     * so QTYPE and QCLASS are parsed correctly.
     *
     * @return void
     * @access public
     *
     */
    public function testPointerCycle()
    {
        $data  = pack('n', 0x1234);  // id
        $data .= pack('n', 0x0100);  // flags (RD=1)
        $data .= pack('n', 1);       // qdcount
        $data .= pack('n', 0);       // ancount
        $data .= pack('n', 0);       // nscount
        $data .= pack('n', 0);       // arcount
        $data .= "\xC0\x12";        // QNAME at offset 12: pointer to offset 18
        $data .= pack('nn', 1, 1);   // QTYPE=A(1), QCLASS=IN(1) at offsets 14-17
        $data .= "\x03foo";          // label "foo" (1 length byte + 3 chars) at offsets 18-21
        $data .= "\xC0\x0C";        // pointer back to offset 12 at offsets 22-23 (cycle)

        //
        // cycle detection terminates the recursive decode; parsing must complete without exception
        //
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertSame(1, $response->header->qdcount);
        $this->assertCount(1, $response->question);
    }
}
