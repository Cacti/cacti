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
 * this test does a basic DNS lookup, and test the file caching feature
 *
 */
class CacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test the file cache
     *
     * @return void
     * @access public
     *
     */
    public function testFileCache()
    {
        //
        // create a random temporary name
        //
        $cache_file = tempnam('/tmp', 'netdns2');

        try
        {
            $r = new \NetDNS2\Resolver(
            [
                'nameservers'   => [ '8.8.8.8', '8.8.4.4' ],
                'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_FILE,
                'cache_options' => [

                   'file' => $cache_file,
                   'size' => 50000
                ]
            ]);

            $result = $r->query('google.com', 'MX');

            //
            // force __destruct() to execute
            //
            unset($r);

            $this->assertTrue(file_exists($cache_file), sprintf('CacheFileTest::testCache(): cache file %s doesn\'t exist!', $cache_file));

            //
            // clean up
            //
            unlink($cache_file);

        } catch(\NetDNS2\Exception $e)
        {
            $this->assertTrue(false, sprintf('CacheFileTest::testCache(): exception thrown: %s', $e->getMessage()));
        }
    }

    /**
     * function to test the shared memory cache
     *
     * @return void
     * @access public
     *
     */
    public function testShmCache()
    {
        //
        // we can only do this test if the shmop extension is loaded
        //
        if (extension_loaded('shmop') == false)
        {
            $this->assertTrue(true);
            return;
        }

        //
        // create a random temporary name
        //
        $cache_file = tempnam('/tmp', 'netdns2');

        try
        {
            $r = new \NetDNS2\Resolver(
            [
                'nameservers'   => [ '8.8.8.8', '8.8.4.4' ],
                'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_SHM,
                'cache_options' => [

                   'file' => $cache_file,
                   'size' => 50000
                ]
            ]);

            $result = $r->query('google.com', 'MX');

            //
            // force __destruct() to execute
            //
            unset($r);

            $this->assertTrue(file_exists($cache_file), sprintf('CacheShmTest::testCache(): cache file %s doesn\'t exist!', $cache_file));

            //
            // clean up
            //
            unlink($cache_file);

        } catch(\NetDNS2\Exception $e)
        {
            $this->assertTrue(false, sprintf('CacheShmTest::testCache(): exception thrown: %s', $e->getMessage()));
        }
    }
}
