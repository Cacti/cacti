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
 * File-based caching for the NetDNS2\Cache class
 *
 */
final class File extends \NetDNS2\Cache
{
    use \NetDNS2\Cache\Model\Data;

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
        // check that the file exists first
        //
        if ( ($this->cache_opened == false) && (file_exists($this->m_options['file']) == true) )
        {
            //
            // check the file size
            //
            $file_size = filesize($this->m_options['file']);
            if ( ($file_size === false) || ($file_size <= 0) )
            {
                return;
            }

            //
            // open the file for reading
            //
            $fp = @fopen($this->m_options['file'], 'r');
            if ($fp !== false)
            {
                //
                // lock the file just in case
                //
                flock($fp, LOCK_EX);

                //
                // read the file contents
                //
                $data = fread($fp, $file_size);
                if ($data !== false)
                {
                    $decoded = unserialize(strval($data));

                    if (is_array($decoded) == true)
                    {
                        $this->cache_data = $decoded;
                    } else
                    {
                        $this->cache_data = [];
                    }
                }

                //
                // unlock
                //
                flock($fp, LOCK_UN);

                //
                // close the file
                //
                fclose($fp);

                //
                // clean up the data
                //
                $this->clean();

                //
                // mark this so we don't read this contents more than once per instance.
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
        if (strlen($this->m_options['file']) == 0)
        {
            return;
        }

        //
        // open the file for reading/writing
        //
        $fp = fopen($this->m_options['file'], 'a+');
        if ($fp !== false)
        {
            //
            // lock the file just in case
            //
            flock($fp, LOCK_EX);

            //
            // seek to the start of the file to read
            //
            fseek($fp, 0, SEEK_SET);

            //
            // get the file size first; in PHP 8.0 fread() was changed to throw an exception if you try and read 0 bytes from a file.
            //
            $file_size = @filesize($this->m_options['file']);

            if ( ($file_size !== false) && ($file_size > 0) )
            {
                //
                // read the file contents
                //
                $data = @fread($fp, $file_size);

                if ( ($data !== false) && (strlen($data) > 0) )
                {
                    //
                    // unserialize and store the data
                    //
                    $c = $this->cache_data;

                    $decoded = unserialize(strval($data));

                    if (is_array($decoded) == true)
                    {
                        $this->cache_data = array_merge($c, $decoded);
                    }
                }
            }

            //
            // trucate the file
            //
            ftruncate($fp, 0);

            //
            // clean the data
            //
            $this->clean();

            //
            // resize the data
            //
            $data = $this->resize();

            if (is_null($data) == false)
            {
                //
                // write the file contents
                //
                fwrite($fp, $data);
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
