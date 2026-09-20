<?php

/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2022, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2022 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 1.5.3
 *
 */

/**
 *
 * ZONEMD Resource Record - RFC8976 section 2.2
 * 
 *  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |                             Serial                            |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |    Scheme     |Hash Algorithm |                               |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               |
 * |                             Digest                            |
 * /                                                               /
 * /                                                               /
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
class Net_DNS2_RR_ZONEMD extends Net_DNS2_RR
{
    /*
     * ZONEMD schemes - there is currently only one defined.
     */
    const ZONEMD_SCHEME_SIMPLE  = 1;

    /*
     * ZONEMD hash algorithms
     */
    const ZONEMD_HASH_ALGORITHM_SHA384  = 1;
    const ZONEMD_HASH_ALGORITHM_SHA512  = 2;

    /*
     * the serial number from the zone's SOA record
     */
    public $serial;

    /*
     * the methods by which data is collated and presented as input to the hashing function.
     */
    public $scheme;

    /*
     * the cryptographic hash algorithm used to construct the digest.
     */
    public $hash_algorithm;

    /*
     * the output of the hash algorithm.
     */
    public $digest;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString()
    {
        return $this->serial . ' ' . $this->scheme . ' ' . $this->hash_algorithm .
            ' ' . implode('', (array)unpack('H*', $this->digest));
    }

    /**
     * parses the rdata portion from a standard DNS config line
     *
     * @param array $rdata a string split line of values for the rdata
     *
     * @return boolean
     * @access protected
     *
     */
    protected function rrFromString(array $rdata)
    {
        $this->serial           = array_shift($rdata);
        $this->scheme           = array_shift($rdata);
        $this->hash_algorithm   = array_shift($rdata);

        //
        // digest must be provided as base64 encoded.
        //
        $this->digest = pack('H*', implode('', $rdata));

        return true;
    }

    /**
     * parses the rdata of the Net_DNS2_Packet object
     *
     * @param Net_DNS2_Packet &$packet a Net_DNS2_Packet packet to parse the RR from
     *
     * @return boolean
     * @access protected
     *
     */
    protected function rrSet(Net_DNS2_Packet &$packet)
    {
        if ($this->rdlength > 0) {

            //
            // unpack the serial, scheme, and hash algorithm
            //
            $x = unpack('Nserial/Cscheme/Chash_algorithm', $this->rdata);

            $this->serial           = $x['serial'];
            $this->scheme           = $x['scheme'];
            $this->hash_algorithm   = $x['hash_algorithm'];

            //
            // copy the digest
            //
            $this->digest  = substr($this->rdata, 6, $this->rdlength - 6);

            return true;
        }

        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     *
     * @param Net_DNS2_Packet &$packet a Net_DNS2_Packet packet use for
     *                                 compressed names
     *
     * @return mixed                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     *
     */
    protected function rrGet(Net_DNS2_Packet &$packet)
    {
        if (strlen($this->digest) > 0) {

            $data = pack('NCCa*', $this->serial, $this->scheme, $this->hash_algorithm, $this->digest);

            $packet->offset += strlen($data);

            return $data;
        }

        return null;
    }
}
