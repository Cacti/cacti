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
 * test class to exercise SIG(0) request signing
 *
 */
class SIG0Test extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that signSIG0() rejects DSA since Algorithm::openssl() has no DSA mapping
     *
     * DSA was removed from the allowed-algorithm list in signSIG0() because the signing step in
     * SIG::rrGet() calls Algorithm::openssl() which throws for DSA.
     *
     * @return void
     * @access public
     *
     */
    public function testSignSIG0RejectsDSA()
    {
        $this->expectException(\NetDNS2\Exception::class);

        $r = new \NetDNS2\Resolver([ 'nameservers' => [ '127.0.0.1' ] ]);

        //
        // build a minimal SIG RR with a DSA algorithm and pass it as a pre-built object
        //
        $sig            = new \NetDNS2\RR\SIG();
        $sig->algorithm = \NetDNS2\ENUM\DNSSEC\Algorithm::DSA;

        $r->signSIG0($sig);
    }

    /**
     * function to test that SIG(0) signing with an RSA key produces a non-empty signature on the wire
     *
     * A live 1024-bit RSA key pair is generated in memory; the PrivateKey wrapper is populated
     * directly without parsing a file.  The signed packet is serialised and re-parsed; the SIG
     * record in the additional section must carry a non-empty signature field.
     *
     * @return void
     * @access public
     *
     */
    public function testSIG0RSASigningProducesSignature()
    {
        if (extension_loaded('openssl') == false)
        {
            $this->markTestSkipped('openssl extension not loaded.');
        }

        //
        // generate a temporary RSA key pair — 1024 bits is enough for a signing test
        //
        $openssl_key = openssl_pkey_new([ 'private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA ]);
        $this->assertNotFalse($openssl_key, 'SIG0Test: openssl_pkey_new() failed');

        /** @var \OpenSSLAsymmetricKey $openssl_key */

        //
        // build a PrivateKey wrapper without going through the file parser;
        // only instance is needed by SIG::rrGet() during signing
        //
        $private_key           = new \NetDNS2\PrivateKey();
        $private_key->instance = $openssl_key;

        //
        // build the SIG RR with the SIG(0) hard-coded values from RFC 2931
        //
        $t = time();

        $sig              = new \NetDNS2\RR\SIG();
        $sig->name        = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, 'example.com');
        $sig->ttl         = 0;
        $sig->class       = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $sig->typecovered = 'SIG0';
        $sig->algorithm   = \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA256;
        $sig->labels      = 0;
        $sig->origttl     = 0;
        $sig->sigincep    = gmdate('YmdHis', $t);
        $sig->sigexp      = gmdate('YmdHis', $t + 600);
        $sig->keytag      = 0;
        $sig->signname    = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'example.com');
        $sig->private_key = $private_key;

        //
        // put the SIG in a packet's additional section and serialise
        //
        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $request->additional[]    = $sig;
        $request->header->arcount = 1;

        $data = $request->get();

        //
        // re-parse and verify that the SIG record carries a non-empty signature
        //
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $response->additional, 'SIG0Test: additional section must contain exactly one record');

        /** @var \NetDNS2\RR\SIG $response_sig */
        $response_sig = $response->additional[0];

        $this->assertInstanceOf(\NetDNS2\RR\SIG::class, $response_sig, 'SIG0Test: additional[0] must be a SIG RR');

        $this->assertGreaterThan(
            0,
            strlen($response_sig->signature),
            'SIG0Test: signature must be non-empty after RSA signing'
        );

        //
        // the signature must be valid base64 that decodes to a non-empty byte string
        //
        $decoded = base64_decode($response_sig->signature, true);
        $this->assertNotFalse($decoded,           'SIG0Test: signature field must be valid base64');
        $this->assertGreaterThan(0, strlen($decoded), 'SIG0Test: decoded signature must be non-empty');
    }

    /**
     * function to test that a SIG(0) packet cannot be signed twice (signature already set)
     *
     * If a pre-built SIG with a non-empty signature is passed to signSIG0(), the signing block
     * inside rrGet() is skipped and the existing signature is preserved unchanged.
     *
     * @return void
     * @access public
     *
     */
    public function testSIG0ExistingSignaturePreserved()
    {
        if (extension_loaded('openssl') == false)
        {
            $this->markTestSkipped('openssl extension not loaded.');
        }

        $sentinel = base64_encode('existing_signature_sentinel');

        $t = time();

        $sig              = new \NetDNS2\RR\SIG();
        $sig->name        = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, 'example.com');
        $sig->ttl         = 0;
        $sig->class       = \NetDNS2\ENUM\RR\Classes::set('ANY');
        $sig->typecovered = 'SIG0';
        $sig->algorithm   = \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA256;
        $sig->labels      = 0;
        $sig->origttl     = 0;
        $sig->sigincep    = gmdate('YmdHis', $t);
        $sig->sigexp      = gmdate('YmdHis', $t + 600);
        $sig->keytag      = 0;
        $sig->signname    = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'example.com');
        $sig->signature   = $sentinel;

        //
        // no private_key set; rrGet() must skip the signing block because signature != ''
        //
        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $request->additional[]    = $sig;
        $request->header->arcount = 1;

        $data     = $request->get();
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        /** @var \NetDNS2\RR\SIG $response_sig */
        $response_sig = $response->additional[0];

        $this->assertSame(
            $sentinel,
            $response_sig->signature,
            'SIG0Test: a pre-set signature must survive the round-trip unchanged'
        );
    }
}
