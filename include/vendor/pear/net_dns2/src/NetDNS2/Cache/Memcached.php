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

namespace NetDNS2\Cache;

/**
 * Memcache-based caching for the NetDNS2\Cache class using the PECL memcached extension (not memcache)
 *
 */
final class Memcached extends \NetDNS2\Cache
{
    /**
     * the local memcached object
     */
    private \Memcached $m_cache;

    /**
     * @see \NetDNS2\Cache::__construct()
     */
    public function __construct(array $_options = [])
    {
        //
        // copy over the options
        //
        $this->m_options = $_options;

        //
        // make sure we have at at least one server to connect to
        //
        if ( (isset($this->m_options['server']) === false) || (count($this->m_options['server']) == 0) )
        {
            throw new \NetDNS2\Exception('you must provide a memcache server list to use.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        try
        {
            $this->m_cache = new \Memcached();

            //
            // store the servers to use
            //
            $this->m_cache->addServers($this->m_options['server']);

            //
            // pass the options array in as Memcache options;
            //
            if ( (isset($_options['options']) === true) && (count($_options['options']) > 0) )
            {
                $this->m_cache->setOptions($_options['options']);
            }

        } catch(\MemcachedException $e)
        {
            throw new \NetDNS2\Exception(sprintf('memcache error: %s', $e->getMessage()), \NetDNS2\ENUM\Error::INT_FAILED_MEMCACHED);
        }
    }

    /**
     * @see \NetDNS2\Cache::get()
     */
    public function get(string $_key): \NetDNS2\Packet\Response|false
    {
        try
        {
            $res = $this->m_cache->get($_key);
            if ($res !== false)
            {
                return $res;
            }

        } catch(\MemcachedException $e)
        {
            throw new \NetDNS2\Exception(sprintf('memcache error: %s', $e->getMessage()), \NetDNS2\ENUM\Error::INT_FAILED_MEMCACHED);
        }

        return false;
    }

    /**
     * @see \NetDNS2\Cache::put()
     */
    public function put(string $_key, \NetDNS2\Packet\Response $_data): void
    {
        //
        // find the TTL
        //
        $ttl = $this->calcuate_ttl($_data);
        try
        {
            $this->m_cache->set($_key, $_data, $ttl);

        } catch(\MemcachedException $e)
        {
            throw new \NetDNS2\Exception(sprintf('memcache error: %s', $e->getMessage()), \NetDNS2\ENUM\Error::INT_FAILED_MEMCACHED);
        }
    }
}
