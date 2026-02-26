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
 * A class to provide simple dns lookup caching.
 */
abstract class Cache
{
    /**
     * supported cache types
     */
    public const CACHE_TYPE_NONE        = 0;
    public const CACHE_TYPE_FILE        = 1;
    public const CACHE_TYPE_SHM         = 2;
    public const CACHE_TYPE_MEMCACHED   = 3;
    public const CACHE_TYPE_REDIS       = 4;

    /**
     * defaul max cache size
     */
    public const CACHE_DEFAULT_MAX_SIZE = 50000;

    /**
     * the stored options array
     *
     * @var array<mixed>
     */
    protected array $m_options = [];

    /**
     * create an instance of the cache objec
     *
     * @param array<mixed> $_options a list of caching options to be used by the underlying caching object
     *
     */
    abstract public function __construct(array $_options = []);

    /**
     * returns the value for the given key
     *
     * @param string $_key the key to lookup in the local cache
     *
     * @return \NetDNS2\Packet\Response returns the cache data on sucess, false on error
     *
     */
    abstract public function get(string $_key): \NetDNS2\Packet\Response|false;

    /**
     * adds a new key/value pair to the cache
     *
     * @param string                   $_key  the key for the new cache entry
     * @param \NetDNS2\Packet\Response $_data the data to store in cache
     *
     */
    abstract public function put(string $_key, \NetDNS2\Packet\Response $_data): void;

    /**
     * return an instance of a caching object based on the type selected
     *
     * @param int $_type the type name of the caching object to use
     * @param array<mixed> $_options options used by the underlying caching objects
     *
     * @throws \NetDNS2\Exception
     *
     */
    public static function factory(int $_type, array $_options = []): \NetDNS2\Cache
    {
        switch($_type)
        {
            case self::CACHE_TYPE_FILE:
            {
                return new \NetDNS2\Cache\File($_options);
            }
            case self::CACHE_TYPE_SHM:
            {
                if (extension_loaded('shmop') == false)
                {
                    throw new \NetDNS2\Exception('the shmop extension is not available for cache.', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
                }

                return new \NetDNS2\Cache\Shm($_options);
            }
            case self::CACHE_TYPE_MEMCACHED:
            {
                if (extension_loaded('memcached') == false)
                {
                    throw new \NetDNS2\Exception('the memcached extension is not available for cache.', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
                }

                return new \NetDNS2\Cache\Memcached($_options);
            }
            case self::CACHE_TYPE_REDIS:
            {
                if (extension_loaded('redis') == false)
                {
                    throw new \NetDNS2\Exception('the redis extension is not available for cache.', \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION);
                }

                return new \NetDNS2\Cache\Redis($_options);
            }
            case self::CACHE_TYPE_NONE:
            default:
                ;
        }

        throw new \NetDNS2\Exception(sprintf('invalid cache type %s defined.', $_type), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
    }

    /**
     * find the right TTL to use for this object
     *
     * @param \NetDNS2\Packet\Response $_data the data to store in cache
     *
     */
    protected function calcuate_ttl(\NetDNS2\Packet\Response $_data): int
    {
        //
        // if there's an override for the TTL, use that instead
        //
        if (isset($this->m_options['ttl_override']) === true)
        {
            return $this->m_options['ttl_override'];
        }

        //
        // default time to live
        //
        $ttl = 86400 * 365;

        //
        // find the lowest TTL, and use that as the TTL for the whole cached object. The downside to using one TTL for the whole object, is that
        // we'll invalidate entries before they actuall expire, causing a real lookup to happen.
        //
        // The upside is that we don't need to require() each RR type in the cache, so we can look at their individual TTL's on each run- we only
        // unserialize the actual RR object when it's get() from the cache.
        //
        foreach($_data->answer as $index => $rr)
        {
            if ($rr->ttl < $ttl)
            {
                $ttl = $rr->ttl;
            }
        }

        return $ttl;
    }
}
