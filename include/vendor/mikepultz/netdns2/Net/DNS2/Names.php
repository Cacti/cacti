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
 * text compression/expansion and labeling class
 *
 */
class Net_DNS2_Names
{
    /**
     * pack a text string
     *
     * @param string $name a name to be packed
     *
     * @return string
     * @access public
     *
     */
    public static function pack($name)
    {
        return (is_null($name) == true) ? null : pack('Ca*', strlen($name), $name);
    }

    /**
     * returns the canonical wire-format representation of the domain name
     *
     * @param string $name a name to be packed
     *
     * @return string
     * @access public
     *
     */
    public static function canonical($name)
    {
        $names = explode('.', $name);
        $compname = '';

        while (!empty($names)) {

            $first = array_shift($names);
            $length = strlen($first);

            $compname .= pack('Ca*', $length, $first);
        }

        $compname .= "\0";

        return $compname;
    }

    /**
     * parses a domain string into a single string
     *
     * @param string  $rdata the DNS packet to look in for the domain name
     * @param integer &$offset the offset into the given packet object
     *
     * @return mixed either a name string or null if it's not found.
     * @access public
     *
     */
    public static function unpack($rdata, &$offset)
    {
        if ($offset > strlen($rdata))
        {
            return null;
        }

        $name = '';

        $len = ord($rdata[$offset++]);
        if ($len == 0)
        {
            return null;
        }

        if ( ($len + $offset) > strlen($rdata)) {

            $name = substr($rdata, $offset);
        } else {

            $name = substr($rdata, $offset, $len);
        }

        $offset += strlen($name);

        return $name;
    }
}
