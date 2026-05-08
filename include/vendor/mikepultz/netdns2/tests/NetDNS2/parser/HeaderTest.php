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
 * test class to exercise DNS header parsing and serialisation
 *
 */
class HeaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that all header bit-fields survive a binary round-trip
     *
     * @return void
     * @access public
     *
     */
    public function testHeaderRoundTrip()
    {
        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');

        //
        // set non-default flag values so we can detect any bit-shift errors
        //
        $request->header->aa = 1;
        $request->header->rd = 0;
        $request->header->ad = 1;
        $request->header->cd = 1;

        $data     = $request->get();
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertSame($request->header->id,      $response->header->id,      'HeaderTest: id mismatch');
        $this->assertSame(1,                         $response->header->aa,      'HeaderTest: aa mismatch');
        $this->assertSame(0,                         $response->header->rd,      'HeaderTest: rd mismatch');
        $this->assertSame(1,                         $response->header->ad,      'HeaderTest: ad mismatch');
        $this->assertSame(1,                         $response->header->cd,      'HeaderTest: cd mismatch');
        $this->assertSame($request->header->qdcount, $response->header->qdcount, 'HeaderTest: qdcount mismatch');
        $this->assertSame(0,                         $response->header->ancount, 'HeaderTest: ancount mismatch');
    }

    /**
     * function to test that parsing a packet shorter than 12 bytes throws an exception
     *
     * @return void
     * @access public
     *
     */
    public function testTruncatedHeaderThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        //
        // four bytes is well under the 12-byte minimum required for a DNS header
        //
        $short_data = pack('n2', 0x1234, 0x0100);

        new \NetDNS2\Packet\Response($short_data, strlen($short_data));
    }

    /**
     * function to test that a header declaring qdcount=1 with no question bytes throws an exception
     *
     * Question::set() enforces a minimum of 4 bytes beyond the current offset; an exactly
     * 12-byte packet (header only) leaves zero bytes for the question section and must throw.
     *
     * @return void
     * @access public
     *
     */
    public function testTruncatedQuestionThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        //
        // 12-byte DNS header with qdcount=1 but no question bytes follow
        //
        $data  = pack('n', 0x1234);  // id
        $data .= pack('n', 0x0100);  // flags (RD=1)
        $data .= pack('n', 1);       // qdcount = 1
        $data .= pack('n', 0);       // ancount
        $data .= pack('n', 0);       // nscount
        $data .= pack('n', 0);       // arcount

        new \NetDNS2\Packet\Response($data, strlen($data));
    }
}
