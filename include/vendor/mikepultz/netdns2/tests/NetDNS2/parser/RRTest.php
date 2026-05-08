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
 * test class to exercise RR::fromString() error paths
 *
 */
class RRTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that fromString() throws on an empty input string
     *
     * @return void
     * @access public
     *
     */
    public function testFromStringEmptyThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\RR::fromString('');
    }

    /**
     * function to test that fromString() throws when fewer than 3 whitespace-separated tokens are given
     *
     * The minimum valid line requires at least 3 tokens (name, type, and rdata);
     * two tokens is not enough.
     *
     * @return void
     * @access public
     *
     */
    public function testFromStringTooFewTokensThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\RR::fromString('example.com A');
    }

    /**
     * function to test that fromString() throws when an unrecognised token appears where a type mnemonic is expected
     *
     * @return void
     * @access public
     *
     */
    public function testFromStringUnknownTypeThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\RR::fromString('example.com 300 IN BADTYPE somedata');
    }

    /**
     * function to test that a valid A record survives a wire-format round-trip unchanged
     *
     * @return void
     * @access public
     *
     */
    public function testARecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN A 192.0.2.1');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * function to test that a valid AAAA record survives a wire-format round-trip unchanged
     *
     * IPv6 notation may vary between PHP versions (compressed vs. expanded), so we
     * compare the binary network-byte-order form via inet_pton() instead of the string.
     *
     * @return void
     * @access public
     *
     */
    public function testAAAARecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'AAAA', 'IN');

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
     * function to test that an SOA record survives a wire-format round-trip unchanged
     *
     * @return void
     * @access public
     *
     */
    public function testSOARecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'SOA', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN SOA ns1.example.com. admin.example.com. 2024010101 3600 900 604800 300');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * function to test that an MX record survives a wire-format round-trip unchanged
     *
     * @return void
     * @access public
     *
     */
    public function testMXRecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'MX', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN MX 10 mail.example.com.');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * function to test that a TXT record with multiple strings survives a wire-format round-trip unchanged
     *
     * @return void
     * @access public
     *
     */
    public function testTXTRecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'TXT', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN TXT "v=spf1 include:example.com ~all"');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * function to test that a PTR record survives a wire-format round-trip
     *
     * @return void
     * @access public
     *
     */
    public function testPTRRecordWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('1.2.0.192.in-addr.arpa.', 'PTR', 'IN');

        $rr = \NetDNS2\RR::fromString('1.2.0.192.in-addr.arpa. 300 IN PTR example.com.');

        $req->answer[]        = $rr;
        $req->header->ancount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }
}
