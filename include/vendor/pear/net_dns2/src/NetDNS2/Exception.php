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
 * Exception handler used by NetDNS2
 *
 */
final class Exception extends \Exception
{
    private ?\NetDNS2\Packet\Request $m_request   = null;
    private ?\NetDNS2\Packet\Response $m_response = null;

    /**
     * Constructor - overload the constructor so we can pass in the request and response object (when it's available)
     *
     * @param string                   $_message  the exception message
     * @param \NetDNS2\ENUM\Error      $_code     the exception code
     * @param \Throwable               $_previous the previous Exception object
     * @param \NetDNS2\Packet\Request  $_request  the \NetDNS2\Packet\Request object for this request
     * @param \NetDNS2\Packet\Response $_response the \NetDNS2\Packet\Response object for this request
     *
     */
    public function __construct(string $_message = '', \NetDNS2\ENUM\Error $_code = \NetDNS2\ENUM\Error::NONE, ?\Throwable $_previous = null,
        ?\NetDNS2\Packet\Request $_request = null, ?\NetDNS2\Packet\Response $_response = null)
    {
        //
        // store the request/response objects (if passed)
        //
        if (is_null($_request) == false)
        {
            $this->m_request  = clone $_request;
        }
        if (is_null($_response) == false)
        {
            $this->m_response = clone $_response;
        }

        //
        // call the parent constructor
        //
        parent::__construct($_message, $_code->value, $_previous);
    }

    /**
     * returns the \NetDNS2\Packet\Request object (if available)
     *
     */
    public function getRequest(): ?\NetDNS2\Packet\Request
    {
        return $this->m_request;
    }

    /**
     * returns the \NetDNS2\Packet\Response object (if available)
     *
     */
    public function getResponse(): ?\NetDNS2\Packet\Response
    {
        return $this->m_response;
    }
}
