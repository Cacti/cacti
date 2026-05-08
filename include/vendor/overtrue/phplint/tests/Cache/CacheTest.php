<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint\Tests\Cache;

use Closure;
use Generator;
use Overtrue\PHPLint\Cache;
use Overtrue\PHPLint\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\CacheItem;

use function dirname;
use function md5_file;
use function sha1_file;
use function str_replace;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    private static Cache $cache;
    private static Closure $createCacheItem;

    protected function setUp(): void
    {
        self::$createCacheItem = Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                return $item;
            },
            null,
            CacheItem::class
        );

        $testsDir = dirname(__DIR__);

        $cache = new Cache();
        // initialize cache value(s)
        $values = [
            // considered as hit
            __FILE__ => function ($filename) { return md5_file($filename); },
            // considered as missed (wrong fingerprint)
            $testsDir . '/Configuration/YamlConfigTest.php' => function ($filename) { return sha1_file($filename); },
            // considered as purely missed
            $testsDir . '/End2End/LintCommandTest.php' => null,
        ];
        foreach ($this->generateItems($values) as $filename => $item) {
            $cache->saveItem($item);
        }
        self::$cache = $cache;
    }

    public function testHasItem(): void
    {
        $this->assertTrue(self::$cache->hasItem(__FILE__));
    }

    public function testGetItem(): void
    {
        // expected
        $item = $this->generateItems([__FILE__ => function ($filename) { return md5_file($filename); }])->current();
        // actual
        $cacheItem = self::$cache->getItem(__FILE__);

        $this->assertSame($item->getKey(), $cacheItem->getKey());
        $this->assertSame($item->get(), $cacheItem->get());
    }

    public function testSaveItem(): void
    {
        $filename = dirname(__DIR__) . '/EndToEnd/LintCommandTest.php';
        $fingerprint = md5_file($filename);

        $cacheItem = self::$cache->getItem($filename);
        $cacheItem->set($fingerprint);
        $saved = self::$cache->saveItem($cacheItem);

        $this->assertTrue($saved);
        $this->assertEquals($fingerprint, self::$cache->getItem($filename)->get());
    }

    public function testClearPool(): void
    {
        $cleared = self::$cache->clear();
        $this->assertTrue($cleared);
    }

    public function testCacheHit(): void
    {
        $this->assertTrue(self::$cache->isHit(__FILE__));
    }

    public function testCacheMiss(): void
    {
        $this->assertFalse(self::$cache->isHit(dirname(__DIR__) . '/EndToEnd/LintCommandTest.php'));
    }

    public function testCacheMissWithFileUnknown(): void
    {
        $this->assertFalse(self::$cache->isHit(dirname(__DIR__) . '/Finder/FinderTest.php'));
    }

    public function testCacheMissWithWrongFileFingerprint(): void
    {
        $this->assertFalse(self::$cache->isHit(dirname(__DIR__) . '/Configuration/ConfigResolverTest.php'));
    }

    public function testGetCalls(): void
    {
        // cache init calls count
        $this->assertCount(3, self::$cache->getCalls());
    }

    public function testFilenameHasReservedCharacters(): void
    {
        $filename = dirname(__DIR__) . '/EndToEnd/Reserved@Keywords.php';
        $fingerprint = md5_file($filename);

        $cacheItem = self::$cache->getItem($filename);
        $cacheItem->set($fingerprint);
        $saved = self::$cache->saveItem($cacheItem);

        $this->assertTrue($saved);
        $this->assertEquals($fingerprint, self::$cache->getItem($filename)->get());
    }

    private function generateItems(array $values): Generator
    {
        foreach ($values as $filename => $processor) {
            $key = str_replace('/', '_', $filename);
            $value = $processor ? $processor($filename) : null;
            $isHit = (null !== $value);
            yield $key => (self::$createCacheItem)($key, $value, $isHit);
        }
    }
}
