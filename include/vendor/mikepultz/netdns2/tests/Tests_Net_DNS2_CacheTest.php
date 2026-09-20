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
 * @since     File available since Release 1.0.0
 *
 */

require_once 'Net/DNS2.php';

/**
 * This test uses the Google public DNS servers to perform a resolution test;
 * this should work on *nix and Windows, but will require an internet connection.
 *
 */
class Tests_Net_DNS2_CacheTest extends PHPUnit\Framework\TestCase
{
    /**
     * function to test the file cache
     *
     * @return void
     * @access public
     *
     */
    public function testCache()
    {
        $cache_file = '/tmp/net_dns2_test.cache';

        $r = new Net_DNS2_Resolver(
        [ 
            'nameservers'   => [ '8.8.8.8', '8.8.4.4' ],
            'use_cache'     => true,
            'cache_type'    => 'file',
            'cache_file'    => $cache_file 
        ]);

        $result = $r->query('google.com', 'MX');

        //
        // force __destruct() to execute
        //
        unset($r);

        $this->assertTrue(file_exists($cache_file));
    }
}
