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
            $this->assertTrue(false, sprintf('DNSSECTest::testDNSSEC(): exception thrown: %s', $e->getMessage()));
        }
    }
}
