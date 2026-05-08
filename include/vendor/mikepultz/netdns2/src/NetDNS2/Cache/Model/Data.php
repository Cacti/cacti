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

namespace NetDNS2\Cache\Model;

/**
 * data model used by File & Shm based caching
 */
trait Data
{
    /**
     * the local data store for the cache
     *
     * @var array<string,mixed>
     */
    protected array $cache_data = [];

    /**
     * an internal flag to make sure we don't load the cache content more than once per instance.
     */
    protected bool $cache_opened = false;

    /**
     * @see \NetDNS2\Cache::get()
     */
    public function get(string $_key): \NetDNS2\Packet\Response|false
    {
        if (isset($this->cache_data[$_key]) === true)
        {
            return unserialize($this->cache_data[$_key]['object'], ['allowed_classes' => \NetDNS2\Cache::allowedCacheClasses()]);
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
        $ttl = $this->calculate_ttl($_data);

        //
        // clone the response so we don't mutate the caller's object when we clear rdata
        //
        $data           = clone $_data;
        $data->rdata    = '';
        $data->rdlength = 0;

        foreach($data->answer as $index => $rr)
        {
            $data->answer[$index]           = clone $rr;
            $data->answer[$index]->rdata    = '';
            $data->answer[$index]->rdlength = 0;
        }
        foreach($data->authority as $index => $rr)
        {
            $data->authority[$index]           = clone $rr;
            $data->authority[$index]->rdata    = '';
            $data->authority[$index]->rdlength = 0;
        }
        foreach($data->additional as $index => $rr)
        {
            $data->additional[$index]           = clone $rr;
            $data->additional[$index]->rdata    = '';
            $data->additional[$index]->rdlength = 0;
        }

        $this->cache_data[$_key] = [

            'cache_date'    => time(),
            'ttl'           => $ttl
        ];

        $this->cache_data[$_key]['object'] = serialize($data);
    }

    /**
     * @see \NetDNS2\Cache::clean()
     */
    protected function clean(): void
    {
        if (count($this->cache_data) > 0)
        {
            //
            // go through each entry and adjust their TTL, and remove entries that
            // have expired
            //
            $now = time();

            foreach($this->cache_data as $key => $data)
            {
                $diff = $now - $data['cache_date'];

                if ($data['ttl'] <= $diff)
                {
                    unset($this->cache_data[$key]);
                } else
                {
                    $this->cache_data[$key]['ttl'] -= $diff;
                    $this->cache_data[$key]['cache_date'] = $now;
                }
            }
        }
    }

    /**
     * @see \NetDNS2\Cache::resize()
     */
    protected function resize(): ?string
    {
        if (count($this->cache_data) > 0)
        {
            //
            // serialize the cache data
            //
            $cache = serialize($this->cache_data);

            //
            // only do this part if the size allocated to the cache storage
            // is smaller than the actual cache data
            //
            if (strlen($cache) > $this->m_options['size'])
            {
                while(strlen($cache) > $this->m_options['size'])
                {
                    //
                    // go through the data, and remove the entries closed to
                    // their expiration date.
                    //
                    $smallest_ttl = PHP_INT_MAX;
                    $smallest_key = null;

                    foreach($this->cache_data as $key => $data)
                    {
                        if ($data['ttl'] < $smallest_ttl)
                        {
                            $smallest_ttl = $data['ttl'];
                            $smallest_key = $key;
                        }
                    }

                    //
                    // unset the key with the smallest TTL
                    //
                    if (is_null($smallest_key) == false)
                    {
                        unset($this->cache_data[$smallest_key]);
                    }

                    //
                    // re-serialize
                    //
                    $cache = serialize($this->cache_data);
                }
            }

            if ($cache == 'a:0:{}')
            {
                return null;
            } else
            {
                return $cache;
            }
        }

        return null;
    }
}
