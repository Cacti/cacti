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
 * Test class to exercise \NetDNS2\Exception construction and accessors.
 *
 */
class ExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * getRequest() returns null when no request packet is supplied.
     *
     */
    public function testGetRequestNullByDefault(): void
    {
        $e = new \NetDNS2\Exception('test', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);

        $this->assertNull($e->getRequest());
    }

    /**
     * getResponse() returns null when no response packet is supplied.
     *
     */
    public function testGetResponseNullByDefault(): void
    {
        $e = new \NetDNS2\Exception('test', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);

        $this->assertNull($e->getResponse());
    }

    /**
     * getMessage() returns the string passed to the constructor.
     *
     */
    public function testGetMessage(): void
    {
        $e = new \NetDNS2\Exception('something went wrong', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);

        $this->assertSame('something went wrong', $e->getMessage());
    }

    /**
     * getCode() returns the integer value of the error enum case.
     *
     */
    public function testGetCode(): void
    {
        $e = new \NetDNS2\Exception('msg', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);

        $this->assertSame(\NetDNS2\ENUM\Error::INT_PARSE_ERROR->value, $e->getCode());
    }

    /**
     * getRequest() returns a cloned copy of the supplied request packet.
     *
     * Modifying the clone must not affect the original.
     *
     */
    public function testGetRequestReturnsClone(): void
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $e = new \NetDNS2\Exception('msg', \NetDNS2\ENUM\Error::INT_PARSE_ERROR, null, $req);

        $captured = $e->getRequest();

        $this->assertNotNull($captured);
        $this->assertInstanceOf(\NetDNS2\Packet\Request::class, $captured);

        //
        // the captured copy is a different object (deep-cloned in the constructor)
        //
        $this->assertNotSame($req, $captured);
    }

    /**
     * getResponse() returns a cloned copy of the supplied response packet.
     *
     */
    public function testGetResponseReturnsClone(): void
    {
        //
        // build a minimal valid response packet for testing
        //
        $req  = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');
        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $e = new \NetDNS2\Exception('msg', \NetDNS2\ENUM\Error::INT_PARSE_ERROR, null, null, $res);

        $captured = $e->getResponse();

        $this->assertNotNull($captured);
        $this->assertInstanceOf(\NetDNS2\Packet\Response::class, $captured);
        $this->assertNotSame($res, $captured);
    }

    /**
     * Both request and response can be captured simultaneously.
     *
     */
    public function testBothPacketsCaptured(): void
    {
        $req  = new \NetDNS2\Packet\Request('example.com.', 'MX', 'IN');
        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $e = new \NetDNS2\Exception('msg', \NetDNS2\ENUM\Error::INT_PARSE_ERROR, null, $req, $res);

        $this->assertNotNull($e->getRequest());
        $this->assertNotNull($e->getResponse());
    }

    /**
     * Exception is correctly instanceof both \NetDNS2\Exception and \Exception.
     *
     */
    public function testExceptionInheritance(): void
    {
        $e = new \NetDNS2\Exception('test', \NetDNS2\ENUM\Error::NONE);

        $this->assertInstanceOf(\NetDNS2\Exception::class, $e);
        $this->assertInstanceOf(\Exception::class, $e);
    }

    /**
     * The NONE error code stores as integer 0.
     *
     */
    public function testNoneErrorCode(): void
    {
        $e = new \NetDNS2\Exception('', \NetDNS2\ENUM\Error::NONE);

        $this->assertSame(0, $e->getCode());
    }
}
