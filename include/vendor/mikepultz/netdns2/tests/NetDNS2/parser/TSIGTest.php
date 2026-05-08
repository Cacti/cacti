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
 * test class to exercise TSIG signing and verification
 *
 */
class TSIGTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that verify() returns true for a correctly-signed packet
     *
     * @return void
     * @access public
     *
     */
    public function testVerifyCorrectKey()
    {
        $key = base64_encode('test_secret_key_netdns2');

        $tsig = new \NetDNS2\RR\TSIG();
        $tsig->factory('mykey', \NetDNS2\RR\TSIG::HMAC_SHA256, $key);

        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $request->additional[]    = $tsig;
        $request->header->arcount = 1;

        $data     = $request->get();
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        /** @var \NetDNS2\RR\TSIG $response_tsig */
        $response_tsig = $response->additional[0];

        $this->assertTrue($response_tsig->verify($response, $key), 'TSIGTest: verify() should return true for the correct key');
    }

    /**
     * function to test that verify() returns false when given the wrong key
     *
     * @return void
     * @access public
     *
     */
    public function testVerifyWrongKey()
    {
        $key   = base64_encode('correct_key');
        $wrong = base64_encode('wrong_key');

        $tsig = new \NetDNS2\RR\TSIG();
        $tsig->factory('mykey', \NetDNS2\RR\TSIG::HMAC_SHA256, $key);

        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $request->additional[]    = $tsig;
        $request->header->arcount = 1;

        $data     = $request->get();
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        /** @var \NetDNS2\RR\TSIG $response_tsig */
        $response_tsig = $response->additional[0];

        $this->assertFalse($response_tsig->verify($response, $wrong), 'TSIGTest: verify() should return false for the wrong key');
    }

    /**
     * function to test that verify() returns false for an unsupported algorithm (GSS_TSIG is not in hash_algorithms)
     *
     * @return void
     * @access public
     *
     */
    public function testVerifyUnsupportedAlgorithm()
    {
        //
        // build a minimal response object to satisfy the method signature
        //
        $request  = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $data     = $request->get();
        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        //
        // construct a TSIG directly with an unsupported algorithm; the algorithm check in
        // verify() short-circuits before any packet data is inspected.
        //
        $key  = base64_encode('test_key');
        $tsig = new \NetDNS2\RR\TSIG();
        $tsig->factory('mykey', \NetDNS2\RR\TSIG::GSS_TSIG, $key);

        $this->assertFalse($tsig->verify($response, $key), 'TSIGTest: verify() should return false for an unsupported algorithm');
    }

    /**
     * function to test that verify() returns false when the TSIG original_id does not match the response header id
     *
     * The original_id check was added to provide a fast-path rejection before the HMAC is computed.
     * We provoke the mismatch by flipping the high byte of the message ID in the serialised binary
     * after signing; the TSIG rdata retains the original id while the header id changes.
     *
     * @return void
     * @access public
     *
     */
    public function testVerifyOriginalIdMismatch()
    {
        $key = base64_encode('test_secret_key_netdns2');

        $tsig = new \NetDNS2\RR\TSIG();
        $tsig->factory('mykey', \NetDNS2\RR\TSIG::HMAC_SHA256, $key);

        $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
        $request->additional[]    = $tsig;
        $request->header->arcount = 1;

        $data = $request->get();

        //
        // flip the high byte of the 2-byte message ID (bytes 0-1) so the header id
        // no longer matches the original_id stored in the TSIG rdata
        //
        $data[0] = chr(ord($data[0]) ^ 0xFF);

        $response = new \NetDNS2\Packet\Response($data, strlen($data));

        /** @var \NetDNS2\RR\TSIG $response_tsig */
        $response_tsig = $response->additional[0];

        $this->assertFalse($response_tsig->verify($response, $key), 'TSIGTest: verify() should return false when original_id does not match the response header id');
    }

    /**
     * function to test that all 6 supported HMAC algorithm variants sign and verify correctly
     *
     * @return void
     * @access public
     *
     */
    public function testAllHMACAlgorithms()
    {
        $algorithms = [
            \NetDNS2\RR\TSIG::HMAC_MD5,
            \NetDNS2\RR\TSIG::HMAC_SHA1,
            \NetDNS2\RR\TSIG::HMAC_SHA224,
            \NetDNS2\RR\TSIG::HMAC_SHA256,
            \NetDNS2\RR\TSIG::HMAC_SHA384,
            \NetDNS2\RR\TSIG::HMAC_SHA512,
        ];

        $key = base64_encode('test_secret_key_netdns2');

        foreach($algorithms as $algorithm)
        {
            $tsig = new \NetDNS2\RR\TSIG();
            $tsig->factory('mykey', $algorithm, $key);

            $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
            $request->additional[]    = $tsig;
            $request->header->arcount = 1;

            $data     = $request->get();
            $response = new \NetDNS2\Packet\Response($data, strlen($data));

            /** @var \NetDNS2\RR\TSIG $response_tsig */
            $response_tsig = $response->additional[0];

            $this->assertTrue(
                $response_tsig->verify($response, $key),
                sprintf('TSIGTest: verify() failed for algorithm %s', $algorithm)
            );
        }
    }
}
