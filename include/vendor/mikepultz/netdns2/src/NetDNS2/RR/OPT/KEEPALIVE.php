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
 * RFC 7828 - The edns-tcp-keepalive EDNS0 Option
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-------------------------------+-------------------------------+
 *   !         OPTION-CODE           !         OPTION-LENGTH         !
 *   +-------------------------------+-------------------------------+
 *   |           TIMEOUT             !
 *   +-------------------------------+
 *
 * @property int $timeout
 */
final class KEEPALIVE extends \NetDNS2\RR\OPT
{
    /**
     * an idle timeout value for the TCP connection, specified in units of 100 milliseconds, encoded in network byte order.
     */
    protected int $timeout = 0;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->timeout;
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

        $val = unpack('nx', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        list('x' => $this->timeout) = (array)$val;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->timeout > 0)
        {
            $this->option_length = 2;
            $this->option_data   = pack('n', $this->timeout);
        } else
        {
            $this->option_length = 0;
            $this->option_data   = '';
        }

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
