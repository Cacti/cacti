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

namespace NetDNS2\RR\OPT;

/**
 * RFC 7873 - Domain Name System (DNS) Cookies
 *
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |        OPTION-CODE = 10      |   OPTION-LENGTH >= 16, <= 40   |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                                                               |
 *   +-+-    Client Cookie (fixed size, 8 bytes)              -+-+-+-+
 *   |                                                               |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                                                               |
 *   /       Server Cookie  (variable size, 8 to 32 bytes)           /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class COOKIE extends \NetDNS2\RR\OPT
{
    /**
     * client cookie
     */
    protected string $client_cookie = '';

    /**
     * server cookie (if known)
     */
    protected string $server_cookie = '';

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->client_cookie;

        if (strlen($this->server_cookie) > 0)
        {
            $out .= ' ' . $this->server_cookie;
        }

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->option_length == 0)
        {
            return true;
        }

        //
        // copy out the client cookie
        //
        $this->client_cookie = bin2hex(substr($this->option_data, 0, 8));

        //
        // if the length is > 8, then we have a server cookie
        //
        if ($this->option_length > 8)
        {
            $this->server_cookie = bin2hex(substr($this->option_data, 8));
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $this->option_data   = pack('H16H*', $this->client_cookie, $this->server_cookie);
        $this->option_length = strlen($this->option_data);

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
