<?php

/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 0.6.0
 *
 */

/**
 * Exception handler used by Net_DNS2
 * 
 */
class Net_DNS2_Exception extends Exception
{
    private $_request;
    private $_response;

    /**
     * Constructor - overload the constructor so we can pass in the request
     *               and response object (when it's available)
     *
     * @param string                   $message  the exception message
     * @param int                      $code     the exception code
     * @param Exception                $previous the previous Exception object
     * @param Net_DNS2_Packet_Request  $request  the Net_DNS2_Packet_Request object for this request
     * @param Net_DNS2_Packet_Response $response the Net_DNS2_Packet_Response object for this request
     *
     * @access public
     *
     */
    public function __construct(
        $message = '', 
        $code = 0, 
        $previous = null, 
        ?Net_DNS2_Packet_Request $request = null,
        ?Net_DNS2_Packet_Response $response = null
    ) {
        //
        // store the request/response objects (if passed)
        //
        $this->_request = $request;
        $this->_response = $response;

        parent::__construct($message, $code, $previous);
    }

    /**
     * returns the Net_DNS2_Packet_Request object (if available)
     *
     * @return Net_DNS2_Packet_Request object
     * @access public
     * @since  function available since release 1.3.1
     *
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * returns the Net_DNS2_Packet_Response object (if available)
     *
     * @return Net_DNS2_Packet_Response object
     * @access public
     * @since  function available since release 1.3.1
     *
     */
    public function getResponse()
    {
        return $this->_response;
    }
}
