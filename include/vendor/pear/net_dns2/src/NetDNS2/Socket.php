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
 * Socket handling class using the PHP Streams
 *
 */
final class Socket
{
    /**
     * type of sockets
     */
    public const SOCK_STREAM = 1;
    public const SOCK_DGRAM  = 2;

    /**
     * the last error message on the object
     */
    public ?string $last_error;

    /**
     * date the socket connection was created, and the date it was last used
     */
    public float $date_created;
    public float $date_last_used;

    /**
     * if tls (DoT) is enabled
     */
    public bool $m_use_tls = false;

    /**
     * TLS socket context values to customize the DoT connection
     *
     * @var array<string,mixed>
     */
    public array $m_tls_context = [];

    /**
     * the local socket connection
     *
     */
    private mixed $m_sock = null;

    /**
     * the local context object for the socket connection
     */
    private mixed $m_context;

    /**
     * the host (IPv4 or IPv6) to connect to
     */
    private string $m_host;

    /**
     * the type of socket (TCP or UDP)
     */
    private int $m_type;

    /**
     * the port to use when connecting
     */
    private int $m_port;

    /**
     * socket timeout value, in <seconds>.<microseconds>
     */
    private float $m_timeout;

    /**
     * the local IP and port we'll send the request from
     */
    private string $m_local_host = '';
    private int $m_local_port = 0;

    /**
     * constructor - set the port details
     *
     * @param integer $_type    the socket type
     * @param string  $_host    the IP address of the DNS server to connect to
     * @param integer $_port    the port of the DNS server to connect to
     * @param float   $_timeout the timeout value to use for socket functions
     *
     */
    public function __construct(int $_type, string $_host, int $_port, float $_timeout)
    {
        $this->m_type           = $_type;
        $this->m_host           = $_host;
        $this->m_port           = $_port;
        $this->m_timeout        = $_timeout;
        $this->date_created     = microtime(true);
    }

    /**
     * destructor
     *
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * sets the local address/port for the socket to bind to
     *
     * @param string $_address the local IP address to bind to
     * @param int    $_port    the local port to bind to, or 0 to let the socket function select a port
     *
     */
    public function bindAddress(string $_address, int $_port = 0): void
    {
        $this->m_local_host = $_address;
        $this->m_local_port = $_port;
    }

    /**
     * opens a socket connection to the DNS server
     *
     */
    public function open(): bool
    {
        //
        // create a list of options for the context
        //
        $opts = [ 'socket' => [] ];

        //
        // bind to a local IP/port if it's set
        //
        if ( (strlen($this->m_local_host) > 0) || ($this->m_local_port > 0) )
        {
            //
            // build the host
            //
            if (strlen($this->m_local_host) > 0)
            {
                //
                // it's possible users are already setting the IPv6 brackets, so I'll just clean them off first
                //
                $host = str_replace([ '[', ']' ], '', $this->m_local_host);

                if (\NetDNS2\Client::isIPv4($host) == true)
                {
                    $opts['socket']['bindto'] = $host;

                } elseif (\NetDNS2\Client::isIPv6($host) == true)
                {
                    $opts['socket']['bindto'] = '[' . $host . ']';

                } else
                {
                    $this->last_error = 'invalid bind address value: ' . $this->m_local_host;
                    return false;
                }

            } else
            {
                $opts['socket']['bindto'] = '0';
            }

            //
            // then add the port
            //
            if ($this->m_local_port > 0)
            {
                $opts['socket']['bindto'] .= ':' . $this->m_local_port;
            } else
            {
                $opts['socket']['bindto'] .= ':0';
            }
        }

        //
        // if TLS is enabled, then copy over any context values (if defined)
        //
        if ( ($this->m_use_tls == true) && (count($this->m_tls_context) > 0) )
        {
            $opts['ssl'] = $this->m_tls_context;
        }

        //
        // create the context
        //
        $this->m_context = @stream_context_create($opts);

        //
        // create socket
        //
        $errno  = 0;
        $errstr = '';
        $urn    = '';

        switch($this->m_type)
        {
            //
            // TCP socket
            //
            case \NetDNS2\Socket::SOCK_STREAM:
            {
                if (\NetDNS2\Client::isIPv4($this->m_host) == true)
                {
                    $urn = (($this->m_use_tls == true) ? 'tls' : 'tcp') . '://' . $this->m_host . ':' . $this->m_port;

                } elseif (\NetDNS2\Client::isIPv6($this->m_host) == true)
                {
                    $urn = (($this->m_use_tls == true) ? 'tls' : 'tcp') . '://[' . $this->m_host . ']:' . $this->m_port;

                } else
                {
                    $this->last_error = 'invalid address type: ' . $this->m_host;
                    return false;
                }
            }
            break;

            //
            // UDP socket
            //
            case \NetDNS2\Socket::SOCK_DGRAM:
            {
                if (\NetDNS2\Client::isIPv4($this->m_host) == true)
                {
                    $urn = 'udp://' . $this->m_host . ':' . $this->m_port;

                } elseif (\NetDNS2\Client::isIPv6($this->m_host) == true)
                {
                    $urn = 'udp://[' . $this->m_host . ']:' . $this->m_port;

                } else
                {
                    $this->last_error = 'invalid address type: ' . $this->m_host;
                    return false;
                }
            }
            break;

            default:
            {
                $this->last_error = 'Invalid socket type: ' . $this->m_type;
                return false;
            }
        }

        //
        // just make sure we built a valid URL to connect to
        //
        if (filter_var($urn, FILTER_VALIDATE_URL) == false)
        {
            $this->last_error = 'failed to build valid server URL to connect to: '. $urn;
            return false;
        }

        //
        // create the socket
        //
        // TODO: when using tls://, if there's a TLS error of some kind, like the name doesn't match, we don't get
        //       and error back through errstr, and since we've @'d the error messages, nothing comes through, just
        //       the generic socket failed error.
        //
        $sock = @stream_socket_client($urn, $errno, $errstr, $this->m_timeout, STREAM_CLIENT_CONNECT, $this->m_context);
        if ($sock === false)
        {
            $this->last_error = $errstr;
            return false;
        }

        $this->m_sock = $sock;

        //
        // set it to non-blocking and set the timeout
        //
        stream_set_blocking($this->m_sock, false);
        stream_set_timeout($this->m_sock, 0, intval($this->m_timeout * 1000000));

        return true;
    }

    /**
     * closes a socket connection to the DNS server
     *
     */
    public function close(): void
    {
        if (is_resource($this->m_sock) === true)
        {
            @fclose($this->m_sock);
        }
    }

    /**
     * writes the given string to the DNS server socket
     *
     * @param string $_data a binary packed DNS packet
     *
     */
    public function write(string $_data): bool
    {
        $length = strlen($_data);

        if ($length == 0)
        {
            $this->last_error = 'empty data on write()';
            return false;
        }

        $read   = null;
        $write  = [ $this->m_sock ];
        $except = null;

        //
        // increment the date last used timestamp
        //
        $this->date_last_used = microtime(true);

        //
        // select on write
        //
        $result = @stream_select($read, $write, $except, 0, intval($this->m_timeout * 1000000));
        if ($result === false)
        {
            $this->last_error = 'failed on write select()';
            return false;

        } elseif ($result == 0)
        {
            $this->last_error = 'timeout on write select()';
            return false;
        }

        //
        // if it's a TCP socket, then we need to packet and send the length of the data as the first 16bit of data.
        //
        // to avoid any TCP segmentation issues, we changed this to prefix the data and only do a single write.
        //
        if ($this->m_type == \NetDNS2\Socket::SOCK_STREAM)
        {
            $_data = chr($length >> 8) . chr($length) . $_data;
            $length += 2;
        }

        //
        // write the data to the socket
        //
        $size = @fwrite($this->m_sock, $_data);
        if ( ($size === false) || ($size != $length) )
        {
            $this->last_error = 'failed to fwrite() packet';
            return false;
        }

        return true;
    }

    /**
     * reads a response from a DNS server
     *
     * @param integer &$_size    the size of the DNS packet read is passed back
     * @param integer $_max_size the max data size returned.
     *
     * @return mixed         returns the data on success and false on error
     *
     */
    public function read(int &$_size, int $_max_size): mixed
    {
        $read   = [ $this->m_sock ];
        $write  = null;
        $except = null;

        //
        // this doesnt' make sense
        //
        if ($_max_size <= 0)
        {
            $this->last_error = 'invalid max_size value provided.';
            return false;
        }

        //
        // increment the date last used timestamp
        //
        $this->date_last_used = microtime(true);

        //
        // make sure our socket is non-blocking
        //
        stream_set_blocking($this->m_sock, false);

        //
        // select on read
        //
        $result = @stream_select($read, $write, $except, 0, intval($this->m_timeout * 1000000));
        if ($result === false)
        {
            $this->last_error = 'error on read select()';
            return false;

        } elseif ($result == 0)
        {
            $this->last_error = 'timeout on read select()';
            return false;
        }

        //
        // at this point, we know that there is data on the socket to be read so the easiest thing to do, is just turn off socket 
        // blocking, and wait for the data.
        //
        stream_set_blocking($this->m_sock, true);

        $data = '';
        $length = $_max_size;

        //
        // if it's a TCP socket, then the first two bytes is the length of the DNS packet- we need to read that off first, then 
        // use that value for the packet read.
        //
        if ($this->m_type == \NetDNS2\Socket::SOCK_STREAM)
        {
            $chunk = '';
            $chunk_size = 2;

            //
            // loop so we make sure we read all the data
            //
            while(1)
            {
                $chunk = fread($this->m_sock, $chunk_size);
                if ($chunk === false)
                {
                    $this->last_error = 'failed on fread() for data length';
                    return false;
                }

                $data .= $chunk;
                $chunk_size -= strlen($chunk);

                if ( (strlen($data) >= $length) || ($chunk_size <= 0) || (feof($this->m_sock) == true) )
                {
                    break;
                }
            }

            //
            // if we don't get at least two bytes, then the request failed
            //
            if (strlen($data) != 2)
            {
                $this->last_error = 'empty data on fread() for data length';
                return false;
            }

            //
            // build the length value
            //
            $length = ord($data[0]) << 8 | ord($data[1]);
            if ($length < \NetDNS2\Header::DNS_HEADER_SIZE)
            {
                return false;
            }
        }

        //
        // read the data from the socket
        //
        $data = '';

        //
        // the streams socket is weird for TCP sockets; it doesn't seem to always return all the data properly; but the looping code I added broke UDP
        // packets- my fault-
        //
        if ($this->m_type == \NetDNS2\Socket::SOCK_STREAM)
        {
            $chunk = '';
            $chunk_size = $length;

            //
            // loop so we make sure we read all the data
            //
            while(1)
            {
                $chunk = fread($this->m_sock, $chunk_size);
                if ($chunk === false)
                {
                    $this->last_error = 'failed on fread() for data';
                    return false;
                }

                $data .= $chunk;
                $chunk_size -= strlen($chunk);

                if ( (strlen($data) >= $length) || ($chunk_size <= 0) || (feof($this->m_sock) == true) )
                {
                    break;
                }
            }

        } else
        {
            //
            // if it's UDP, it's a single fixed-size frame, and the streams library doesn't seem to have a problem reading it.
            //
            $data = fread($this->m_sock, $length);
            if ($data === false)
            {
                $this->last_error = 'failed on fread() for data';
                return false;
            }
        }

        $_size = strlen(strval($data));

        return $data;
    }
}
