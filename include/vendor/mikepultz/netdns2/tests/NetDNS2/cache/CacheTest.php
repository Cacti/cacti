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

        } catch(\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('CacheFileTest::testCache(): exception thrown: %s', $e->getMessage()));
        } finally
        {
            if (file_exists($cache_file) === true)
            {
                unlink($cache_file);
            }
        }
    }

    /**
     * function to test cache put/get hit and miss without any network calls
     *
     * @return void
     * @access public
     *
     */
    public function testFileCacheHitMiss()
    {
        $cache_file = tempnam('/tmp', 'netdns2');

        try
        {
            //
            // build a minimal DNS packet so we have a Response object to cache
            //
            $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
            $a       = \NetDNS2\RR::fromString('example.com. 300 IN A 1.2.3.4');

            $request->answer[]        = $a;
            $request->header->ancount = 1;

            $data     = $request->get();
            $response = new \NetDNS2\Packet\Response($data, strlen($data));

            $cache = new \NetDNS2\Cache\File(['file' => $cache_file, 'size' => 50000]);

            //
            // cache miss on a fresh (empty) cache
            //
            $this->assertFalse(
                $cache->get('test.key.A.IN'),
                'CacheTest::testFileCacheHitMiss(): expected a cache miss on an empty cache'
            );

            //
            // put an entry and verify it is immediately retrievable (cache hit)
            //
            $cache->put('test.key.A.IN', $response);

            $this->assertInstanceOf(
                \NetDNS2\Packet\Response::class,
                $cache->get('test.key.A.IN'),
                'CacheTest::testFileCacheHitMiss(): expected a cache hit after put()'
            );

            //
            // a key that was never stored must still return false
            //
            $this->assertFalse(
                $cache->get('other.key.A.IN'),
                'CacheTest::testFileCacheHitMiss(): expected a cache miss for an unknown key'
            );

        } catch(\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('CacheTest::testFileCacheHitMiss(): exception thrown: %s', $e->getMessage()));
        } finally
        {
            if (file_exists($cache_file) === true)
            {
                unlink($cache_file);
            }
        }
    }

    /**
     * function to test that Cache::factory() throws on an unrecognised cache type
     *
     * @return void
     * @access public
     *
     */
    public function testFactoryInvalidTypeThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\Cache::factory(99);
    }

    /**
     * function to test that Cache::factory() throws when the required 'file' option is absent
     *
     * @return void
     * @access public
     *
     */
    public function testFactoryMissingFileThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\Cache::factory(\NetDNS2\Cache::CACHE_TYPE_FILE, []);
    }

    /**
     * function to test that Cache::put() does not mutate the caller's Response object
     *
     * put() must work on a clone so that the caller's rdata fields are not cleared.
     *
     * @return void
     * @access public
     *
     */
    public function testPutDoesNotMutate()
    {
        $cache_file = tempnam('/tmp', 'netdns2');

        try
        {
            $request = new \NetDNS2\Packet\Request('example.com', 'A', 'IN');
            $a       = \NetDNS2\RR::fromString('example.com. 300 IN A 1.2.3.4');

            $request->answer[]        = $a;
            $request->header->ancount = 1;

            $data     = $request->get();
            $response = new \NetDNS2\Packet\Response($data, strlen($data));

            //
            // record the rdata of the first answer before caching; it must be non-empty
            //
            $original_rdata = $response->answer[0]->rdata;
            $this->assertGreaterThan(0, strlen($original_rdata), 'CacheTest::testPutDoesNotMutate(): rdata should be non-empty before put()');

            $cache = new \NetDNS2\Cache\File(['file' => $cache_file, 'size' => 50000]);
            $cache->put('test.key.A.IN', $response);

            //
            // the caller's rdata must not have been cleared by put()
            //
            $this->assertSame(
                $original_rdata,
                $response->answer[0]->rdata,
                'CacheTest::testPutDoesNotMutate(): put() must not mutate the caller\'s Response object'
            );

        } catch(\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('CacheTest::testPutDoesNotMutate(): exception thrown: %s', $e->getMessage()));
        } finally
        {
            if (file_exists($cache_file) === true)
            {
                unlink($cache_file);
            }
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
            $this->markTestSkipped('shmop extension not loaded.');
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

        } catch(\NetDNS2\Exception $e)
        {
            $this->fail(sprintf('CacheShmTest::testCache(): exception thrown: %s', $e->getMessage()));
        } finally
        {
            if (file_exists($cache_file) === true)
            {
                unlink($cache_file);
            }
        }
    }
}
