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
 * Test class to test the DNSSEC logic
 *
 */
class DNSSECTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test the TSIG logic
     *
     * @return void
     * @access public
     * @group network
     *
     */
    public function testDNSSEC()
    {
        try
        {
            $r = new \NetDNS2\Resolver([ 'nameservers' => [ '1.1.1.1' ] ]);

            $r->dnssec = true;

            $result = $r->query('org', 'SOA', 'IN');

            $this->assertTrue(($result->header->ad == 1), sprintf('DNSSECTest::testDNSSEC(): the ad bit is not set!'));
            $this->assertTrue(($result->additional[0] instanceof \NetDNS2\RR\OPT), sprintf('DNSSECTest::testDNSSEC(): additional[0] is not a OPT RR'));
            $this->assertTrue(($result->additional[0]->do == 1), sprintf('DNSSECTest::testDNSSEC(): the do bit is not set!'));

        } catch(\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('DNSSECTest::testDNSSEC(): exception thrown: %s', $e->getMessage()));
        }
    }

    // -------------------------------------------------------------------------
    // Offline validator tests — no network required
    // -------------------------------------------------------------------------

    /**
     * Build a minimal fake response from the given RR list so offline tests
     * do not need a live DNS server.
     *
     * @param list<\NetDNS2\RR> $rrs
     */
    private function buildResponse(array $rrs): \NetDNS2\Packet\Response
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        foreach ($rrs as $rr)
        {
            $req->answer[] = $rr;
        }

        $req->header->ancount = count($rrs);

        $data = $req->get();
        return new \NetDNS2\Packet\Response($data, strlen($data));
    }

    /**
     * A dummy trust anchor that can be added to pass the "no anchors" guard
     * without affecting any chain validation (since we never reach chain walks
     * in these tests).
     *
     */
    private function dummyAnchor(): \NetDNS2\RR\DS
    {
        /** @var \NetDNS2\RR\DS $ds */
        $ds = \NetDNS2\RR::fromString(
            '. 0 IN DS 12345 8 2 0000000000000000000000000000000000000000000000000000000000000000'
        );
        return $ds;
    }

    /**
     * validate() must throw INT_DNSSEC_NO_ANCHOR when no trust anchors are configured.
     *
     */
    public function testValidatorRequiresTrustAnchor(): void
    {
        $resolver  = new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ]);
        $validator = new \NetDNS2\DNSSEC\Validator($resolver);

        $response = $this->buildResponse([]);

        $this->expectException(\NetDNS2\Exception::class);
        $this->expectExceptionCode(\NetDNS2\ENUM\Error::INT_DNSSEC_NO_ANCHOR->value);

        $validator->validate($response);
    }

    /**
     * validate() must throw INT_DNSSEC_TIME when the RRSIG sigexp is in the past.
     *
     */
    public function testExpiredRRSIGThrows(): void
    {
        $resolver  = new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ]);
        $validator = new \NetDNS2\DNSSEC\Validator($resolver);
        $validator->addTrustAnchor($this->dummyAnchor());

        /** @var \NetDNS2\RR\A $a */
        $a = \NetDNS2\RR::fromString('example.com. 300 IN A 93.184.216.34');

        //
        // sigexp 20200101000000 is in 2020; sigincep is also in the past
        // both are before the current date (2026), so sigexp < now → expired
        //
        /** @var \NetDNS2\RR\RRSIG $rrsig */
        $rrsig = \NetDNS2\RR::fromString(
            'example.com. 300 IN RRSIG A 8 2 300 20200101000000 20191201000000 12345 example.com. AAAA'
        );

        $response = $this->buildResponse([ $a, $rrsig ]);

        $this->expectException(\NetDNS2\Exception::class);
        $this->expectExceptionCode(\NetDNS2\ENUM\Error::INT_DNSSEC_TIME->value);

        $validator->validate($response);
    }

    /**
     * validate() must throw INT_DNSSEC_TIME when the RRSIG sigincep is in the future.
     *
     */
    public function testFutureRRSIGThrows(): void
    {
        $resolver  = new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ]);
        $validator = new \NetDNS2\DNSSEC\Validator($resolver);
        $validator->addTrustAnchor($this->dummyAnchor());

        /** @var \NetDNS2\RR\A $a */
        $a = \NetDNS2\RR::fromString('example.com. 300 IN A 93.184.216.34');

        //
        // sigincep 20280101000000 is in 2028; now (2026) < sigincep → not yet valid
        //
        /** @var \NetDNS2\RR\RRSIG $rrsig */
        $rrsig = \NetDNS2\RR::fromString(
            'example.com. 300 IN RRSIG A 8 2 300 20300101000000 20280101000000 12345 example.com. AAAA'
        );

        $response = $this->buildResponse([ $a, $rrsig ]);

        $this->expectException(\NetDNS2\Exception::class);
        $this->expectExceptionCode(\NetDNS2\ENUM\Error::INT_DNSSEC_TIME->value);

        $validator->validate($response);
    }

    /**
     * validate() must throw INT_DNSSEC_UNSIGNED when an answer RR has no RRSIG.
     *
     */
    public function testMissingRRSIGThrows(): void
    {
        $resolver  = new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ]);
        $validator = new \NetDNS2\DNSSEC\Validator($resolver);
        $validator->addTrustAnchor($this->dummyAnchor());

        /** @var \NetDNS2\RR\A $a */
        $a = \NetDNS2\RR::fromString('example.com. 300 IN A 93.184.216.34');

        //
        // no RRSIG in the response → unsigned RRset
        //
        $response = $this->buildResponse([ $a ]);

        $this->expectException(\NetDNS2\Exception::class);
        $this->expectExceptionCode(\NetDNS2\ENUM\Error::INT_DNSSEC_UNSIGNED->value);

        $validator->validate($response);
    }

    /**
     * keyTag() must return the correct value for a known DNSKEY.
     *
     * Wire bytes for DNSKEY 256 3 8 AAAA:
     *   flags = 0x0100 (256)  → bytes 01 00
     *   protocol = 3          → byte  03
     *   algorithm = 8         → byte  08
     *   key = base64("AAAA") = 3 zero bytes → 00 00 00
     *
     * RFC 4034 Appendix B checksum:
     *   ac = (0x01<<8) + 0x00 + (0x03<<8) + 0x08 + 0 + 0 + 0 = 256+768+8 = 1032
     *   fold: 1032 >> 16 = 0; keytag = 1032 & 0xffff = 1032
     *
     */
    public function testKeyTagKnownVector(): void
    {
        /** @var \NetDNS2\RR\DNSKEY $dnskey */
        $dnskey = \NetDNS2\RR::fromString('test. 0 IN DNSKEY 256 3 8 AAAA');

        $validator = new \NetDNS2\DNSSEC\Validator(
            new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ])
        );

        $this->assertSame(1032, $validator->keyTag($dnskey));
    }

    /**
     * dsDigest() must produce a correct SHA-256 digest.
     *
     * We independently compute the expected value using PHP's hash() and
     * verify that dsDigest() produces the same result.
     *
     */
    public function testDSDigestKnownVector(): void
    {
        /** @var \NetDNS2\RR\DNSKEY $dnskey */
        $dnskey = \NetDNS2\RR::fromString(
            'example. 86400 IN DNSKEY 256 3 5 '
            . 'AQOvwCDZrz/tFzUhpkCELaB7dJVuUdVMlWYt2PN90G4F'
            . 'nEPvz61TXgDEyAfVSO3K0H3a7CvjpH4q7mCkDJlV82JW'
            . 'g6s9rcgvwIm9LOvBk4Whe7C0TkL4VKDL2bBXc2mSBV9u'
            . 'CqnGEzJwNbfOJvMxm3Z98G0NVNR1Px8Y7AeT4lYTtVMp'
        );

        //
        // owner wire: canonical encoding of "example." → \x07example\x00
        //
        $dummy      = 0;
        $owner_wire = (new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'example'))->encode($dummy);

        //
        // independently compute the expected digest:
        //   hash_input = owner_wire || flags(n) || protocol(C) || algorithm(C) || key_bytes
        // flags = 256 (zone key bit), protocol = 3, algorithm = 5
        //
        $key_bytes     = (string)base64_decode(
            'AQOvwCDZrz/tFzUhpkCELaB7dJVuUdVMlWYt2PN90G4F'
            . 'nEPvz61TXgDEyAfVSO3K0H3a7CvjpH4q7mCkDJlV82JW'
            . 'g6s9rcgvwIm9LOvBk4Whe7C0TkL4VKDL2bBXc2mSBV9u'
            . 'CqnGEzJwNbfOJvMxm3Z98G0NVNR1Px8Y7AeT4lYTtVMp'
        );
        $hash_input    = $owner_wire . pack('nCC', 256, 3, 5) . $key_bytes;
        $expected_sha256 = hash('sha256', $hash_input);

        $validator = new \NetDNS2\DNSSEC\Validator(
            new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ])
        );

        $this->assertSame($expected_sha256, $validator->dsDigest($dnskey, $owner_wire, \NetDNS2\ENUM\DNSSEC\Digest::SHA256));

        //
        // also verify that SHA-1 produces 40 hex chars (20 bytes) and SHA-256 produces 64 hex chars (32 bytes)
        //
        $sha1_result   = $validator->dsDigest($dnskey, $owner_wire, \NetDNS2\ENUM\DNSSEC\Digest::SHA1);
        $sha256_result = $validator->dsDigest($dnskey, $owner_wire, \NetDNS2\ENUM\DNSSEC\Digest::SHA256);

        $this->assertSame(40, strlen($sha1_result));
        $this->assertSame(64, strlen($sha256_result));
    }

    /**
     * End-to-end chain validation against the real root zone.
     *
     * Queries example.com A with DO=true and validates the complete
     * RRSIG → DNSKEY → DS → ... → root KSK trust anchor chain.
     *
     * @group network
     *
     */
    public function testFullChainValidation(): void
    {
        try
        {
            $resolver          = new \NetDNS2\Resolver([ 'nameservers' => [ '1.1.1.1' ] ]);
            $resolver->dnssec  = true;

            $validator = new \NetDNS2\DNSSEC\Validator($resolver);
            $validator->useRootTrustAnchor();

            $response = $resolver->query('example.com', 'A');

            $validator->validate($response);

            //
            // reaching here means no exception was thrown — validation passed
            //
            $this->addToAssertionCount(1);

        } catch (\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('DNSSECTest::testFullChainValidation(): exception thrown: %s', $e->getMessage()));
        }
    }
}
