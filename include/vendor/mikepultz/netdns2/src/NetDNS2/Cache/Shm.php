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
 * Shared Memory-based caching for the NetDNS2\Cache class
 *
 */
final class Shm extends \NetDNS2\Cache
{
    use \NetDNS2\Cache\Model\Data;

    /**
     * resource id of the shared memory cache
     */
    private \Shmop $m_cache;

    /**
     * the IPC key
     */
    private int $m_cache_file_tok = -1;

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
        // make sure we have a file location
        //
        if (isset($this->m_options['file']) === false)
        {
            throw new \NetDNS2\Exception('you must provide a file to cache dns results to.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        //
        // check for a default max cache size
        //
        if (isset($this->m_options['size']) === false)
        {
            $this->m_options['size'] = \NetDNS2\Cache::CACHE_DEFAULT_MAX_SIZE;
        }

        //
        // if we've already loaded the cache data, then just return right away
        //
        if ($this->cache_opened == true)
        {
            return;
        }

        //
        // make sure the file exists first
        //
        if (file_exists($this->m_options['file']) == false)
        {
            if (file_put_contents($this->m_options['file'], '') === false)
            {
                throw new \NetDNS2\Exception(sprintf('failed to create empty SHM file: %s', $this->m_options['file']), \NetDNS2\ENUM\Error::INT_FAILED_SHMOP);
            }
        }

        //
        // convert the filename to a IPC key
        //
        $this->m_cache_file_tok = ftok($this->m_options['file'], strval($this->m_options['id'] ?? 't'));
        if ($this->m_cache_file_tok == -1)
        {
            throw new \NetDNS2\Exception(sprintf('failed on ftok() file: %s', $this->m_options['file']), \NetDNS2\ENUM\Error::INT_FAILED_SHMOP);
        }

        //
        // try to open an existing cache; if it doesn't exist, then there's no cache, and nothing to do.
        //
        $cache = @shmop_open($this->m_cache_file_tok, 'c', 0644, $this->m_options['size']);
        if ($cache === false)
        {
            throw new \NetDNS2\Exception(sprintf('failed on shmop_open() file: %s', $this->m_cache_file_tok), \NetDNS2\ENUM\Error::INT_FAILED_SHMOP);
        }

        $this->m_cache = $cache;

        //
        // this returns the size allocated, and not the size used, but it's still a good check to make sure there's space allocated.
        //
        $allocated = shmop_size($this->m_cache);
        if ($allocated > 0)
        {
            //
            // read the data from the shared memory segment
            //
            $data = trim(shmop_read($this->m_cache, 0, $allocated));
            if (strlen($data) > 0)
            {
                //
                // unserialize and store the data
                //
                $decoded = unserialize(strval($data), ['allowed_classes' => false]);

                if (is_array($decoded) == true)
                {
                    $this->cache_data = $decoded;
                } else
                {
                    $this->cache_data = [];
                }

                //
                // call clean to clean up old entries
                //
                $this->clean();

                //
                // mark the cache as loaded, so we don't load it more than once
                //
                $this->cache_opened = true;
            }
        }
    }

    /**
     * destructor
     */
    public function __destruct()
    {
        //
        // if there's no cache file set, then there's nothing to do
        //
        if (strlen($this->m_options['file'] ?? '') == 0)
        {
            return;
        }

        $fp = fopen($this->m_options['file'], 'r');
        if ($fp !== false)
        {
            //
            // lock the file
            //
            flock($fp, LOCK_EX);

            //
            // get the size allocated to the segment
            //
            $allocated = shmop_size($this->m_cache);

            //
            // read the contents
            //
            $data = trim(shmop_read($this->m_cache, 0, $allocated));

            //
            // if there was some data
            //
            if (strlen($data) > 0)
            {
                //
                // unserialize and store the data
                //
                $c = $this->cache_data;

                $decoded = unserialize(strval($data), ['allowed_classes' => false]);

                if (is_array($decoded) == true)
                {
                    $this->cache_data = array_merge($c, $decoded);
                }
            }

            //
            // delete the segment
            //
            shmop_delete($this->m_cache);

            //
            // clean the data
            //
            $this->clean();

            //
            // clean up and write the data
            //
            $data = $this->resize();

            if (is_null($data) == false)
            {
                //
                // re-create segment
                //
                $cache = @shmop_open($this->m_cache_file_tok, 'c', 0644, $this->m_options['size']);
                if ($cache === false)
                {
                    return;
                }

                shmop_write($cache, $data, 0);
            }

            //
            // unlock
            //
            flock($fp, LOCK_UN);

            //
            // close the file
            //
            fclose($fp);
        }
    }
}
