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
 * Wire-format round-trip regression tests.
 *
 * Each test encodes one or more RRs into a binary packet and re-parses them,
 * verifying that:
 *   1. $_packet->offset is advanced correctly in rrGet() so subsequent RRs
 *      are not mis-parsed.
 *   2. RR values survive the binary round-trip unchanged.
 *   3. EDNS options are merged into a single OPT record (RFC 6891 §6.1.1).
 *
 */
class WireTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Regression test for BUG-8: NID::rrGet() was missing $_packet->offset += 10.
     *
     */
    public function testNIDWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'NID', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN NID 10 20:1:db8:1');

        $req->answer[]         = $rr;
        $req->header->ancount  = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * Two NID records in the answer section.  If rrGet() does not advance offset
     * by 10 bytes the second RR will be mis-parsed.
     *
     */
    public function testNIDMultiRROffsetTracking(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'NID', 'IN');

        $rr1 = \NetDNS2\RR::fromString('example.com. 300 IN NID 10 20:1:db8:1');
        $rr2 = \NetDNS2\RR::fromString('example.com. 300 IN NID 20 dead:beef:cafe:1');

        $req->answer[]         = $rr1;
        $req->answer[]         = $rr2;
        $req->header->ancount  = 2;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(2, $res->answer);
        $this->assertSame($rr1->__toString(), $res->answer[0]->__toString());
        $this->assertSame($rr2->__toString(), $res->answer[1]->__toString());
    }

    /**
     * Regression test for BUG-9: GPOS::rrGet() was missing $_packet->offset update.
     *
     */
    public function testGPOSWireRoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'GPOS', 'IN');

        $rr = \NetDNS2\RR::fromString('example.com. 300 IN GPOS -98.6502 19.7885 2134.0');

        $req->answer[]         = $rr;
        $req->header->ancount  = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->answer);
        $this->assertSame($rr->__toString(), $res->answer[0]->__toString());
    }

    /**
     * GPOS followed by an A record.  Verifies that offset is advanced correctly
     * after the GPOS so the A record parses at the right position.
     *
     */
    public function testGPOSFollowedByARoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $gpos = \NetDNS2\RR::fromString('example.com. 300 IN GPOS -98.6502 19.7885 2134.0');
        $a    = \NetDNS2\RR::fromString('example.com. 300 IN A 1.2.3.4');

        $req->answer[]         = $gpos;
        $req->answer[]         = $a;
        $req->header->ancount  = 2;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(2, $res->answer);
        $this->assertSame($gpos->__toString(), $res->answer[0]->__toString());
        $this->assertSame($a->__toString(), $res->answer[1]->__toString());
    }

    /**
     * Regression test for BUG-6: TXT::rrGet() never updated $_packet->offset.
     * A TXT record followed by an A record verifies offset tracking.
     *
     */
    public function testTXTFollowedByARoundTrip(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $txt = \NetDNS2\RR::fromString('example.com. 300 IN TXT "hello world"');
        $a   = \NetDNS2\RR::fromString('example.com. 300 IN A 192.0.2.1');

        $req->answer[]         = $txt;
        $req->answer[]         = $a;
        $req->header->ancount  = 2;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(2, $res->answer);
        $this->assertSame($txt->__toString(), $res->answer[0]->__toString());
        $this->assertSame($a->__toString(), $res->answer[1]->__toString());
    }

    /**
     * Verify that EDNS::build() produces a single merged OPT record even when
     * multiple EDNS options are configured (RFC 6891 §6.1.1).
     *
     */
    public function testMergedEDNSOPTSingleRecord(): void
    {
        $edns = new \NetDNS2\EDNS();

        //
        // dnssec() sets the DO flag only — no option RDATA bytes
        // dau()    adds algorithm list bytes to the RDATA
        //
        $edns->dnssec(true);
        $edns->dau(true, [13, 14, 15]);

        $opt = $edns->build(4096);

        $this->assertNotNull($opt);
        $this->assertInstanceOf(\NetDNS2\RR\OPT::class, $opt);

        //
        // DO bit must be propagated from the dnssec() option
        //
        $this->assertSame(1, $opt->do);

        //
        // DAU option bytes must be present in the merged option_data
        //
        $this->assertGreaterThan(0, strlen($opt->option_data));
    }

    /**
     * Encode a packet with two EDNS options and re-parse it.
     * The additional section must contain exactly one OPT record.
     *
     */
    public function testMergedEDNSOPTInPacket(): void
    {
        $edns = new \NetDNS2\EDNS();

        $edns->dnssec(true);
        $edns->dau(true, [13, 14, 15]);

        $opt = $edns->build(4096);

        $this->assertNotNull($opt);

        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $req->additional[]     = $opt;
        $req->header->arcount  = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        //
        // must be exactly one OPT in additional, not one per option
        //
        $this->assertCount(1, $res->additional);
        $this->assertSame('OPT', $res->additional[0]->type->label());
    }
}
