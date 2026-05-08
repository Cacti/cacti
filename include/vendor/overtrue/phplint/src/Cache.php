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

namespace Overtrue\PHPLint;

use LogicException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function class_exists;
use function get_debug_type;
use function is_string;
use function md5_file;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_split;

/**
 * @author Overtrue
 * @author Laurent Laville (code-rewrites since v9.0)
 */
final class Cache
{
    private CacheItemPoolInterface $pool;
    private AdapterInterface $adapter;
    private int $hits = 0;
    private int $misses = 0;

    public function __construct(string|object|null $cachePoolAdapter = null)
    {
        if (null === $cachePoolAdapter) {
            $adapter = new ArrayAdapter();
        } elseif (is_string($cachePoolAdapter)) {
            if (!str_contains($cachePoolAdapter, '\\')) {
                // could be alias for standard Symfony Cache Adapters
                $cachePoolAdapter = 'Symfony\\Component\\Cache\\Adapter\\' . $cachePoolAdapter . 'Adapter';
            }
            if (!class_exists($cachePoolAdapter, true)) {
                throw new LogicException(sprintf('Unable to load class "%s"', $cachePoolAdapter));
            }
            $adapter = new $cachePoolAdapter();
        } else {
            $adapter = $cachePoolAdapter;
        }

        if (!$adapter instanceof AdapterInterface) {
            throw new LogicException(
                sprintf(
                    'Invalid cache pool adapter. "%s" must implement %s.',
                    $cachePoolAdapter,
                    AdapterInterface::class
                )
            );
        }

        $this->adapter = $adapter;
        $this->pool = $this->createCachePool($adapter);
    }

    public function createCachePool(AdapterInterface $adapter): CacheItemPoolInterface
    {
        return new TraceableAdapter($adapter);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function hasItem(string $filename): bool
    {
        return $this->pool->hasItem($this->getKey($filename));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getItem(string $filename): CacheItemInterface
    {
        return $this->pool->getItem($this->getKey($filename));
    }

    public function saveItem(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    public function clear(string $prefix = ''): bool
    {
        if ($this->pool instanceof AdapterInterface) {
            return $this->pool->clear($prefix);
        }
        return $this->pool->clear();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isHit(string $filename): bool
    {
        // Try to fetch item from cache
        $item = $this->getItem($filename);
        if (!$item->isHit()) {
            ++$this->misses;
            return false;
        }

        $fingerprintSaved = $item->get();
        $currentFingerprint = md5_file($filename);

        if ($currentFingerprint !== $fingerprintSaved) {
            ++$this->misses;
            return false;
        }

        ++$this->hits;
        return true;
    }

    public function getCalls(): array
    {
        if ($this->pool instanceof TraceableAdapter) {
            return $this->pool->getCalls();
        }
        return [];
    }

    public function __debugInfo(): array
    {
        return [
            'inner-adapter' => get_debug_type($this->adapter),
            'cache-pool' => get_debug_type($this->pool),
            'hits' => $this->hits,
            'misses' => $this->misses,
            'calls' => $this->getCalls(),
        ];
    }

    private function getKey(string $filename): string
    {
        return str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), '_', $filename);
    }

    /**
     * @since Release 9.6.0
     */
    public function prune(): bool
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }
}
